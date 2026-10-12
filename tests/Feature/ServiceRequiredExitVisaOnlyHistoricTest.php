<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Invoice;
use App\Models\IssuedTicket;
use App\Models\Package;
use App\Models\Passenger;
use App\Models\TicketFare;
use App\Rules\FlightDateSlot;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\CreatesPackageValueFixtures;
use Tests\TestCase;

/**
 * P2 (Complete): visa_only -> all/ticket_only transition must create the new
 * IssuedTicket snapshot from HISTORIC fare prices at the passenger's
 * historicAnchor() — never live — AND assign the passenger fare-FKs from the
 * package so package_value picks up the ticket component.
 */
class ServiceRequiredExitVisaOnlyHistoricTest extends TestCase
{
    use CreatesPackageValueFixtures, RefreshDatabase;

    private $user;

    private $district;

    private $fpCharge;

    private Package $package;

    private TicketFare $fare;

    private Booking $booking;

    private Invoice $invoice;

    protected function setUp(): void
    {
        parent::setUp();

        $branch = $this->pvBranch();
        $this->user = $this->pvUser($branch);
        $this->district = $this->pvDistrict();
        $this->fpCharge = $this->pvFpCharge($this->district, $this->user);
        $visaPrice = $this->pvVisaPrice($this->user, 2000.00);

        $this->fare = $this->pvTicketFare($this->user);
        $this->pvLogFare($this->fare, 'created', null, [
            'selling_fare' => '28000.000000', 'offer_price' => null, 'ticket_type' => 'regular',
            'child_fare_percentage' => '70.00', 'infant_fare_percentage' => '30.00',
        ], '2026-07-01 10:00:00', $this->user);

        $this->package = $this->pvPackage($this->user, $visaPrice, [
            'ticket_fare_id' => $this->fare->id,
            'regular_price' => 30000.00,
            'service_charge' => 1500.00,
        ]);

        $this->booking = $this->pvBooking($this->package, $this->user, $this->fpCharge, $this->district, [
            'total_value' => 3500.00,
        ]);
        $this->invoice = app(InvoiceService::class)->createForBooking($this->booking);
        $this->invoice->updateQuietly(['total_amount' => 3500.00, 'balance' => 3500.00, 'paid_amount' => 0]);
    }

    private function visaOnlyPassenger(): Passenger
    {
        $passenger = $this->pvPassenger($this->booking, [
            'service_required' => 'visa_only',
            'ticket_fare_id' => null,
            'package_value' => 3500.00,
        ]);
        // Booking + passenger share the era: the earliest-era anchor floors
        // at max(booking creation, passenger creation).
        $this->pvSetCreatedAt($this->booking, '2026-07-10 10:00:00');
        $this->pvSetCreatedAt($passenger, '2026-07-10 10:00:00');
        $this->booking->updateQuietly(['pax_qty' => 1]);

        return $passenger;
    }

    private function editServiceRequired(Passenger $passenger, string $service): void
    {
        $this->put(route('passengers.update', $passenger->id), [
            'first_name' => $passenger->first_name,
            'last_name' => $passenger->last_name,
            'passport_no' => $passenger->passport_no,
            'date_of_birth' => '1990-01-01',
            'stay_duration' => 14,
            'flight_date_from' => FlightDateSlot::validPairForTesting()[0],
            'flight_date_to' => FlightDateSlot::validPairForTesting()[1],
            'service_required' => $service,
        ])->assertRedirect();
    }

    public function test_visa_only_to_all_creates_historic_snapshot_and_assigns_fk(): void
    {
        $passenger = $this->visaOnlyPassenger();

        // Fare repriced AFTER the passenger anchor: live must not leak in.
        $this->fare->updateQuietly(['selling_fare' => 30000.00]);
        $this->pvLogFare($this->fare, 'updated', ['selling_fare' => '28000.000000'], ['selling_fare' => '30000.000000'], '2026-08-01 10:00:00', $this->user);

        $this->actingAs($this->user);
        $this->editServiceRequired($passenger, 'all');

        $ticket = IssuedTicket::where('passenger_id', $passenger->id)->first();
        $this->assertNotNull($ticket, 'exit must create a ticket');
        $this->assertEqualsWithDelta(28000.00, (float) $ticket->selling_fare, 0.001, 'snapshot at anchor, not live 30000');

        $this->assertSame($this->fare->id, $passenger->fresh()->ticket_fare_id, 'FK assigned from package');
        $this->assertEqualsWithDelta(28000.00 + 2000.00 + 1500.00, (float) $passenger->fresh()->package_value, 0.001, 'ticket component now valued');
    }

    public function test_visa_only_to_ticket_only_creates_historic_snapshot_and_assigns_fk(): void
    {
        $passenger = $this->visaOnlyPassenger();

        $this->fare->updateQuietly(['selling_fare' => 30000.00]);
        $this->pvLogFare($this->fare, 'updated', ['selling_fare' => '28000.000000'], ['selling_fare' => '30000.000000'], '2026-08-01 10:00:00', $this->user);

        $this->actingAs($this->user);
        $this->editServiceRequired($passenger, 'ticket_only');

        $ticket = IssuedTicket::where('passenger_id', $passenger->id)->first();
        $this->assertNotNull($ticket);
        $this->assertEqualsWithDelta(28000.00, (float) $ticket->selling_fare, 0.001);
        $this->assertSame($this->fare->id, $passenger->fresh()->ticket_fare_id);
    }
}
