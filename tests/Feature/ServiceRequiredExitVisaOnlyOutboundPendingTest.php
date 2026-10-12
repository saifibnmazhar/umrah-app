<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\District;
use App\Models\Invoice;
use App\Models\IssuedTicket;
use App\Models\Package;
use App\Models\Passenger;
use App\Rules\FlightDateSlot;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\CreatesPackageValueFixtures;
use Tests\TestCase;

/**
 * Plan 17 continuation: exit from visa_only -> 'all'/'ticket_only'.
 *
 * Requirements covered:
 *   1. Single ticket     -> exactly one Regular placeholder row flagged
 *      outbound_pending=true (no duplicate on rerun).
 *   2. Double ticket     -> one Regular row + one issue_type='pending_outbound'
 *      row; flag ONLY on the Regular row; inbound/outbound fare FKs mapped.
 *   3. Existing placeholder rows preserved (edit flow, handler early-return).
 *   4. Fares snapshotted at the passenger's historicAnchor() (never live).
 *   5. Protected fields (status, ticket_number, pnr, issued_date,
 *      ticket_agent_id, net_fare, baggage) untouched.
 *   6. package_value / booking total / invoice total consistent.
 */
class ServiceRequiredExitVisaOnlyOutboundPendingTest extends TestCase
{
    use CreatesPackageValueFixtures, RefreshDatabase;

    private $user;

    private $district;

    private $fpCharge;

    private $fare;

    private Package $package;

    private Booking $booking;

    private Invoice $invoice;

    protected function setUp(): void
    {
        parent::setUp();

        $branch = $this->pvBranch();
        $this->user = $this->pvUser($branch);
        $this->setDistrictAndFingerprintCharge($this->pvDistrict());
        $visaPrice = $this->pvVisaPrice($this->user, 2000.00);

        $this->fare = $this->pvTicketFare($this->user);

        $this->package = $this->pvPackage($this->user, $visaPrice, [
            'ticket_fare_id' => $this->fare->id,
            'regular_price' => 30000.00,
            'offer_price' => 28000.00,
            'service_charge' => 1500.00,
        ]);

        $this->booking = $this->pvBooking($this->package, $this->user, $this->fpCharge, $this->district, [
            'total_value' => 3500.00,
        ]);
        $this->invoice = app(InvoiceService::class)->createForBooking($this->booking);
        $this->invoice->updateQuietly(['total_amount' => 3500.00, 'balance' => 3500.00, 'paid_amount' => 0]);
    }

    private function visaOnlyPassenger(array $overrides = []): Passenger
    {
        $passenger = $this->pvPassenger($this->booking, array_merge([
            'service_required' => 'visa_only',
            'ticket_fare_id' => null,
            'package_value' => 3500.00,
        ], $overrides));

        $this->pvSetCreatedAt($passenger, '2026-07-10 10:00:00');
        $this->booking->updateQuietly(['pax_qty' => 1]);

        return $passenger;
    }

    private function editServiceRequired(Passenger $passenger, string $service): void
    {
        $this->actingAs($this->user);
        $this->put(route('passengers.update', $passenger->id), [
            'first_name' => $passenger->first_name,
            'last_name' => $passenger->last_name,
            'passport_no' => $passenger->passport_no,
            'date_of_birth' => '1990-01-01',
            'stay_duration' => 14,
            'flight_date_from' => $this->flightFrom(),
            'flight_date_to' => $this->flightTo(),
            'service_required' => $service,
        ])->assertRedirect();
    }

    private function flightFrom(): string
    {
        return FlightDateSlot::validPairForTesting()[0];
    }

    private function flightTo(): string
    {
        return FlightDateSlot::validPairForTesting()[1];
    }

    private function setDistrictAndFingerprintCharge(District $district): void
    {
        $this->district = $district;
        $this->fpCharge = $this->pvFpCharge($district, $this->user);
    }

    private function revalueFareTo(float $sellingFare): void
    {
        $this->fare->updateQuietly(['selling_fare' => $sellingFare]);
        $this->pvLogFare($this->fare, 'updated', ['selling_fare' => '28000.000000'], ['selling_fare' => (string) $sellingFare], '2026-08-01 10:00:00', $this->user);
    }

    public function test_single_ticket_exit_creates_regular_placeholder_flagged_outbound_pending(): void
    {
        $passenger = $this->visaOnlyPassenger();
        $this->revalueFareTo(30000.00);
        $this->editServiceRequired($passenger, 'all');
        $this->assertSingleTicketState($passenger, 28000.00);
        $this->assertEqualsWithDelta(31500.00, (float) $passenger->fresh()->package_value, 0.001);
        $this->assertEqualsWithDelta(31500.00, (float) $this->booking->fresh()->total_value, 0.001);
        $this->assertEqualsWithDelta(31500.00, (float) $this->invoice->fresh()->total_amount, 0.001);
    }

    public function test_single_ticket_exit_to_ticket_only_flagged_outbound_pending(): void
    {
        $passenger = $this->visaOnlyPassenger();
        $this->revalueFareTo(30000.00);
        $this->editServiceRequired($passenger, 'ticket_only');
        $this->assertSingleTicketState($passenger, 28000.00);
    }

    public function test_double_ticket_exit_creates_regular_and_pending_outbound_rows(): void
    {
        $inboundFare = $this->pvTicketFare($this->user);
        $outboundFare = $this->pvTicketFare($this->user, ['selling_fare' => 24000.00]);
        $this->package = $this->pvPackage($this->user, $this->package->visaSellingPrice, [
            'is_double_ticket' => true,
            'ticket_fare_id' => $inboundFare->id,
            'ticket_fare_inbound_id' => $inboundFare->id,
            'ticket_fare_outbound_id' => $outboundFare->id,
            'regular_price' => 30000.00,
            'offer_price' => 28000.00,
            'service_charge' => 1500.00,
        ]);
        $this->booking = $this->pvBooking($this->package, $this->user, $this->fpCharge, $this->district, ['total_value' => 3500.00]);
        $this->invoice = app(InvoiceService::class)->createForBooking($this->booking);
        $this->invoice->updateQuietly(['total_amount' => 3500.00, 'balance' => 3500.00, 'paid_amount' => 0]);
        $passenger = $this->visaOnlyPassenger();
        $this->editServiceRequired($passenger, 'all');
        $rows = IssuedTicket::where('passenger_id', $passenger->id)->orderBy('id')->get();
        $this->assertCount(2, $rows, 'double ticket must produce exactly two rows');
        $regular = $rows->firstWhere(fn ($row) => is_null($row->issue_type));
        $pendingOutbound = $rows->firstWhere(fn ($row) => $row->issue_type === 'pending_outbound');
        $this->assertNotNull($regular, 'regular row must exist');
        $this->assertNotNull($pendingOutbound, 'pending_outbound row must exist');
        $this->assertTrue((bool) $regular->outbound_pending, 'flag lives on the Regular row only');
        $this->assertFalse((bool) $pendingOutbound->outbound_pending, 'pending_outbound companion must not be flagged');
        $this->assertNull($regular->ticket_fare_id, 'regular row must not reference a fare source');
        $this->assertNull($pendingOutbound->ticket_fare_id, 'pending_outbound row must not reference a fare source');
        $this->assertSame($inboundFare->id, $passenger->fresh()->ticket_fare_inbound_id, 'inbound FK from package');
        $this->assertSame($outboundFare->id, $passenger->fresh()->ticket_fare_outbound_id, 'outbound FK from package');
        $this->assertSame(null, $passenger->fresh()->ticket_fare_id, 'no base fare FK for double ticket');
        $this->assertEqualsWithDelta(55500.00, (float) $passenger->fresh()->package_value, 0.001, 'package_value = both legs + visa + service');
        $this->assertEqualsWithDelta(55500.00, (float) $this->booking->fresh()->total_value, 0.001, 'booking total includes ticket component + fp charge');
        $this->assertEqualsWithDelta(55500.00, (float) $this->invoice->fresh()->total_amount, 0.001, 'invoice follows booking total');
    }

    public function test_service_required_change_reruns_do_not_duplicate_rows(): void
    {
        $passenger = $this->visaOnlyPassenger();
        $this->editServiceRequired($passenger, 'all');
        $firstCount = IssuedTicket::where('passenger_id', $passenger->id)->count();
        $this->editServiceRequired($passenger, 'ticket_only');
        $this->editServiceRequired($passenger, 'all');
        $this->assertSame($firstCount, IssuedTicket::where('passenger_id', $passenger->id)->count(), 're-runs must not create duplicate rows');
        $this->assertTrue((bool) IssuedTicket::where('passenger_id', $passenger->id)->first()->outbound_pending);
        $this->assertEqualsWithDelta(31500.00, (float) $passenger->fresh()->package_value, 0.001);
    }

    public function test_service_required_exit_with_existing_placeholder_row_preserves_rows(): void
    {
        $passenger = $this->visaOnlyPassenger();
        IssuedTicket::create([
            'booking_id' => $this->booking->id,
            'passenger_id' => $passenger->id,
            'ticket_fare_id' => null,
            'user_id' => $this->user->id,
            'status' => 'pending',
        ]);
        $this->editServiceRequired($passenger, 'all');
        $this->assertCount(1, IssuedTicket::where('passenger_id', $passenger->id)->get(), 'existing placeholder row must be preserved');
        $this->assertSame(null, $passenger->fresh()->ticket_fare_id, 'base FK must not be created');
        $this->assertEqualsWithDelta(3500.00, (float) $passenger->fresh()->package_value, 0.001, 'ticket component stays away while still visa_only');
    }

    private function assertSingleTicketState(Passenger $passenger, float $expectedSellingFare): void
    {
        $rows = IssuedTicket::where('passenger_id', $passenger->id)->get();
        $this->assertCount(1, $rows, 'single ticket must produce exactly one row');

        $row = $rows->first();
        $this->assertNull($row->issue_type, 'single ticket row has no issue_type');
        $this->assertTrue((bool) $row->outbound_pending, 'regular placeholder must be flagged outbound_pending');
        $this->assertSame($this->fare->id, $passenger->fresh()->ticket_fare_id, 'base FK assigned from package');
        $this->assertSame($row->ticket_fare_id ?? null, $this->fare->id, 'row FK assigned from package');
        $this->assertSame(null, $passenger->fresh()->ticket_fare_inbound_id);
        $this->assertSame(null, $passenger->fresh()->ticket_fare_outbound_id);
        $this->assertEqualsWithDelta($expectedSellingFare, (float) $row->selling_fare, 0.001, 'snapshot at anchor, not live fare');
        $this->assertSame(0.0, (float) $row->offer_price, 'no offer price for adult regular row');

        // Protected fields: handler never writes them.
        $this->assertNull($row->ticket_number);
        $this->assertNull($row->pnr);
        $this->assertNull($row->issued_date);
        $this->assertNull($row->ticket_agent_id);
        $this->assertEquals(0.0, (float) $row->net_fare);
        $this->assertNull($row->baggage_inbound);
        $this->assertNull($row->baggage_outbound);
    }
}
