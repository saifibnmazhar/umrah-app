<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Package;
use App\Models\Passenger;
use App\Models\TicketFare;
use App\Rules\FlightDateSlot;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\CreatesPackageValueFixtures;
use Tests\TestCase;

/**
 * Test 5 (plan 17): passenger_type changes recompute THAT passenger at
 * historicAnchor() = earliest era start of the booking's current package,
 * floored at own creation — never at live prices.
 * (b) locks the D2 amendment: on a swapped booking the anchor is the SWAP
 * moment, so later re-valuations keep swap-time pricing.
 */
class PassengerTypeChangeHistoricRecalcTest extends TestCase
{
    use CreatesPackageValueFixtures, RefreshDatabase;

    private $user;

    private $fpCharge;

    private $district;

    private Package $package;

    private TicketFare $fare;

    protected function setUp(): void
    {
        parent::setUp();

        $branch = $this->pvBranch();
        $this->user = $this->pvUser($branch);
        $district = $this->pvDistrict();
        $fpCharge = $this->pvFpCharge($district, $this->user);
        $visaPrice = $this->pvVisaPrice($this->user, 2000.00);

        $this->fare = $this->pvTicketFare($this->user);
        $this->package = $this->pvPackage($this->user, $visaPrice, [
            'ticket_fare_id' => $this->fare->id,
            'regular_price' => 30000.00,
        ]);

        // Fare history: created selling 28000 @ 2026-07-01, raised to 30000 @ 2026-08-01.
        $this->pvLogFare($this->fare, 'created', null, [
            'selling_fare' => '28000.000000', 'offer_price' => null, 'ticket_type' => 'regular',
            'child_fare_percentage' => '70.00', 'infant_fare_percentage' => '30.00',
        ], '2026-07-01 10:00:00', $this->user);
        $this->pvLogFare($this->fare, 'updated', ['selling_fare' => '28000.000000'], ['selling_fare' => '30000.000000'], '2026-08-01 10:00:00', $this->user);
        // Live row reflects the raise (quiet — keeps the manual log history authoritative).
        $this->fare->updateQuietly(['selling_fare' => 30000.00]);

        $this->fpCharge = $fpCharge;
        $this->district = $district;
    }

    private function bookingWithPassenger(string $passengerCreatedAt): array
    {
        $booking = $this->pvBooking($this->package, $this->user, $this->fpCharge, $this->district, [
            'total_value' => 31500.00,
        ]);
        $passenger = $this->pvPassenger($booking, [
            'ticket_fare_id' => $this->fare->id,
            'package_value' => 31500.00, // adult: 28000 (as-of creation era) + 2000 + 1500
        ]);
        // Booking + passenger share the era (earliest-era anchor floors at
        // max(booking creation, passenger creation)).
        $this->pvSetCreatedAt($booking, $passengerCreatedAt);
        $this->pvSetCreatedAt($passenger, $passengerCreatedAt);
        $invoice = app(InvoiceService::class)->createForBooking($booking);
        $invoice->updateQuietly(['total_amount' => 31500.00, 'paid_amount' => 0]);

        return [$booking, $passenger, $invoice];
    }

    private function editPassenger(Passenger $passenger, array $overrides = []): void
    {
        $this->put(route('passengers.update', $passenger->id), array_merge([
            'first_name' => $passenger->first_name,
            'last_name' => $passenger->last_name,
            'passport_no' => $passenger->passport_no,
            'date_of_birth' => '1990-01-01',
            'stay_duration' => 14,
            'flight_date_from' => FlightDateSlot::validPairForTesting()[0],
            'flight_date_to' => FlightDateSlot::validPairForTesting()[1],
        ], $overrides))->assertSessionHasNoErrors();
    }

    public function test_never_swapped_type_change_uses_creation_era_fare(): void
    {
        [$booking, $passenger, $invoice] = $this->bookingWithPassenger('2026-07-10 10:00:00');

        // Second passenger must stay untouched.
        $other = $this->pvPassenger($booking, [
            'ticket_fare_id' => $this->fare->id,
            'package_value' => 99999.00,
        ]);
        $booking->updateQuietly(['total_value' => 131499.00, 'pax_qty' => 2]);
        $invoice->updateQuietly(['total_amount' => 131499.00]);

        $this->actingAs($this->user);
        $this->editPassenger($passenger, ['passenger_type' => 'child']);

        // Anchor = created_at (2026-07-10) => fare 28000 (NOT live 30000).
        $expected = 28000.00 * 0.70 + 2000 + 1500; // 23100
        $this->assertEqualsWithDelta($expected, (float) $passenger->fresh()->package_value, 0.001, 'recomputed at creation-era fare');
        $this->assertEqualsWithDelta(99999.00, (float) $other->fresh()->package_value, 0.001, 'other passenger untouched');
        $this->assertEqualsWithDelta($expected + 99999.00, (float) $booking->fresh()->total_value, 0.001);
        $this->assertEqualsWithDelta($expected + 99999.00, (float) $invoice->fresh()->total_amount, 0.001);
    }

    public function test_swapped_type_change_uses_swap_time_fare(): void
    {
        // Booking created 2026-07-02 (fare 28000 era), swapped ONTO this
        // package at 2026-07-10, fare raised after the swap (2026-08-01 ->
        // 30000, live), type change at now(): the ticket leg must use the
        // fare AS OF THE SWAP (the package's earliest era here).
        [$booking, $passenger] = $this->bookingWithPassenger('2026-07-02 10:00:00');

        // Log + current state stay coherent: the booking is ON this package,
        // having come from another one at swap time.
        $this->pvLogSwap($booking, $this->package->id + 1, $this->package->id, '2026-07-10 10:00:00', $this->user);

        $this->actingAs($this->user);
        $this->editPassenger($passenger, ['passenger_type' => 'child']);

        // Anchor = swap (2026-07-10): fare was 28000 then (raised only on
        // 2026-08-01) => NOT the live 30000.
        $expected = 28000.00 * 0.70 + 2000 + 1500; // 23100
        $this->assertEqualsWithDelta($expected, (float) $passenger->fresh()->package_value, 0.001, 'swap-time fare, not live');
    }

    public function test_non_type_field_edit_does_not_recompute_package_value(): void
    {
        [$booking, $passenger] = $this->bookingWithPassenger('2026-07-10 10:00:00');

        $this->actingAs($this->user);
        $this->editPassenger($passenger, ['first_name' => 'Renamed']);

        $this->assertSame('Renamed', $passenger->fresh()->first_name);
        $this->assertEqualsWithDelta(31500.00, (float) $passenger->fresh()->package_value, 0.001);
    }
}
