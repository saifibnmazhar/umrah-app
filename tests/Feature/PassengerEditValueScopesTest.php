<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\Passenger;
use App\Models\TicketFare;
use App\Rules\FlightDateSlot;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\CreatesPackageValueFixtures;
use Tests\TestCase;

/**
 * Test 6 (plan 17): recompute scopes — extra_charge / service_required
 * changes recompute ONLY that passenger at historicAnchor(); add computes
 * only the new one; remove re-sums only; is_cancelled passengers are frozen;
 * the fingerprint charge resolves from the CURRENT district (D3).
 *
 * p2 carries a drift marker (88888): if any trigger recomputed it, the
 * marker would collapse to the live-derived value.
 */
class PassengerEditValueScopesTest extends TestCase
{
    use CreatesPackageValueFixtures, RefreshDatabase;

    private $user;

    private $district;

    private $fpCharge;

    private Package $package;

    private TicketFare $fare;

    private Booking $booking;

    private Invoice $invoice;

    private const MARKER = 88888.00;

    protected function setUp(): void
    {
        parent::setUp();

        $branch = $this->pvBranch();
        $this->user = $this->pvUser($branch);
        $this->district = $this->pvDistrict();
        $this->fpCharge = $this->pvFpCharge($this->district, $this->user);
        $visaPrice = $this->pvVisaPrice($this->user, 2000.00);

        $this->fare = $this->pvTicketFare($this->user);
        $this->package = $this->pvPackage($this->user, $visaPrice, [
            'ticket_fare_id' => $this->fare->id,
            'regular_price' => 30000.00,
        ]);
    }

    /** @return array{0: Passenger, 1: Passenger} p1 consistent, p2 drift-marked */
    private function bookingWithTwoPassengers(): array
    {
        $booking = $this->pvBooking($this->package, $this->user, $this->fpCharge, $this->district, [
            'total_value' => 31500.00 + self::MARKER,
        ]);
        $p1 = $this->pvPassenger($booking, [
            'ticket_fare_id' => $this->fare->id, 'package_value' => 31500.00,
        ]);
        $p2 = $this->pvPassenger($booking, [
            'ticket_fare_id' => $this->fare->id, 'package_value' => self::MARKER,
        ]);
        $booking->updateQuietly(['pax_qty' => 2]);
        $this->invoice = app(InvoiceService::class)->createForBooking($booking);
        $this->invoice->updateQuietly(['paid_amount' => 0]);
        $this->booking = $booking;

        return [$p1, $p2];
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
        ], $overrides))->assertRedirect();
    }

    public function test_extra_charge_change_recomputes_only_that_passenger(): void
    {
        [$p1, $p2] = $this->bookingWithTwoPassengers();

        $this->actingAs($this->user);
        $this->editPassenger($p1, ['extra_charge' => 500]);

        // p1 recomputed at historicAnchor() (live == anchor here) + extra.
        $this->assertEqualsWithDelta(31500.00 + 500, (float) $p1->fresh()->package_value, 0.001, 'p1 recomputed + extra');
        $this->assertEqualsWithDelta(self::MARKER, (float) $p2->fresh()->package_value, 0.001, 'p2 untouched (scope = that passenger)');
        $this->assertEqualsWithDelta(31500.00 + 500 + self::MARKER, (float) $this->booking->fresh()->total_value, 0.001);
        $this->assertEqualsWithDelta(31500.00 + 500 + self::MARKER, (float) $this->invoice->fresh()->total_amount, 0.001);
    }

    public function test_service_required_change_recomputes_only_that_passenger(): void
    {
        [$p1, $p2] = $this->bookingWithTwoPassengers();

        $this->actingAs($this->user);
        $this->editPassenger($p1, ['service_required' => 'ticket_only']);

        // ticket_only: fare + service charge, no visa => 28000 + 1500.
        $this->assertEqualsWithDelta(29500.00, (float) $p1->fresh()->package_value, 0.001, 'ticket_only drops visa');
        $this->assertEqualsWithDelta(self::MARKER, (float) $p2->fresh()->package_value, 0.001, 'p2 untouched');
        $this->assertEqualsWithDelta(29500.00 + self::MARKER, (float) $this->booking->fresh()->total_value, 0.001);
        $this->assertEqualsWithDelta(29500.00 + self::MARKER, (float) $this->invoice->fresh()->total_amount, 0.001);
    }

    public function test_add_passenger_computes_only_the_new_one(): void
    {
        [$p1, $p2] = $this->bookingWithTwoPassengers();

        $this->actingAs($this->user);
        $response = $this->postJson(route('bookings.passengers.store', $this->booking->id), [
            'first_name' => 'New',
            'last_name' => 'Pax',
            'passport_no' => 'NP'.substr(uniqid(), -8),
            'date_of_birth' => '1990-01-01',
            'mobile_no' => '0500000000',
            'passport_expiry' => '2030-12-31',
            'address' => 'Addr',
            'stay_duration' => 14,
            'service_required' => 'all',
            'flight_date_from' => FlightDateSlot::validPairForTesting()[0],
            'flight_date_to' => FlightDateSlot::validPairForTesting()[1],
        ]);
        $response->assertOk();

        $newPassenger = Passenger::where('first_name', 'New')->firstOrFail();
        $this->assertEqualsWithDelta(31500.00, (float) $newPassenger->package_value, 0.001, 'new passenger computed at now()');
        $this->assertEqualsWithDelta(31500.00, (float) $p1->fresh()->package_value, 0.001, 'p1 untouched');
        $this->assertEqualsWithDelta(self::MARKER, (float) $p2->fresh()->package_value, 0.001, 'p2 drift preserved');
        $this->assertEqualsWithDelta(31500.00 + 31500.00 + self::MARKER, (float) $this->booking->fresh()->total_value, 0.001);
    }

    public function test_remove_passenger_re_summs_only(): void
    {
        [$p1, $p2] = $this->bookingWithTwoPassengers();

        $this->actingAs($this->user);
        $this->deleteJson(route('bookings.passengers.destroy', [
            'booking' => $this->booking->id, 'passenger' => $p1->id,
        ]))->assertOk();

        $this->assertNull(Passenger::find($p1->id));
        $this->assertEqualsWithDelta(self::MARKER, (float) $p2->fresh()->package_value, 0.001, 'p2 value untouched');
        $this->assertEqualsWithDelta(self::MARKER, (float) $this->booking->fresh()->total_value, 0.001, 're-summed without recompute');
        $this->assertEqualsWithDelta(self::MARKER, (float) $this->invoice->fresh()->total_amount, 0.001);
    }

    public function test_remove_passenger_json_returns_stored_totals_and_values(): void
    {
        [$p1, $p2] = $this->bookingWithTwoPassengers();

        $this->actingAs($this->user);
        $response = $this->deleteJson(route('bookings.passengers.destroy', [
            'booking' => $this->booking->id, 'passenger' => $p1->id,
        ]))->assertOk();

        // Phase 4 (plan 17): the no-reload delete path must return the stored
        // post-delete truth so the edit page never re-derives it client-side.
        $response->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success', 'message', 'invoice', 'total_value', 'discount_amount',
                'passengers' => [['id', 'package_value']],
            ]);
        $this->assertEqualsWithDelta(self::MARKER, (float) $response->json('total_value'), 0.001);
        $this->assertEqualsWithDelta(0.0, (float) $response->json('discount_amount'), 0.001);

        $this->assertCount(1, $response->json('passengers'));
        $this->assertSame($p2->id, $response->json('passengers.0.id'));
        $this->assertEqualsWithDelta(self::MARKER, (float) $response->json('passengers.0.package_value'), 0.001);
    }

    public function test_cancelled_passenger_is_frozen_but_still_summed(): void
    {
        [$p1, $p2] = $this->bookingWithTwoPassengers();
        $p1->updateQuietly(['is_cancelled' => true, 'package_value' => 77777.00]);

        $this->actingAs($this->user);
        $this->editPassenger($p2, ['extra_charge' => 500]);

        $this->assertEqualsWithDelta(77777.00, (float) $p1->fresh()->package_value, 0.001, 'cancelled passenger frozen');
        $this->assertEqualsWithDelta(31500.00 + 500, (float) $p2->fresh()->package_value, 0.001, 'p2 recomputed');
        // Re-sum set unchanged (D4): the frozen value still counts.
        $this->assertEqualsWithDelta(77777.00 + 31500.00 + 500, (float) $this->booking->fresh()->total_value, 0.001);
    }

    public function test_fingerprint_charge_resolves_from_current_district(): void
    {
        $this->bookingWithTwoPassengers();

        $newDistrict = $this->pvDistrict('D2');
        $this->pvFpCharge($newDistrict, $this->user, 100.00);
        $this->booking->updateQuietly(['district_id' => $newDistrict->id]);

        $this->actingAs($this->user);
        $this->patchJson(route('bookings.fingerprint-location.update', $this->booking->id), [
            'fingerprint_location' => 'home',
        ])->assertOk();

        // Stored passenger values + 100 from the CURRENT district's charge (D3).
        $this->assertEqualsWithDelta(31500.00 + self::MARKER + 100.00, (float) $this->booking->fresh()->total_value, 0.001);
    }
}
