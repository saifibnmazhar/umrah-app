<?php

namespace Tests\Feature;

use App\Models\Airline;
use App\Models\AirlineClass;
use App\Models\Bank;
use App\Models\Booking;
use App\Models\Branch;
use App\Models\CityCode;
use App\Models\CurrencyRate;
use App\Models\Customer;
use App\Models\District;
use App\Models\FingerprintCharge;
use App\Models\FlightDateGap;
use App\Models\IssuedTicket;
use App\Models\Package;
use App\Models\Passenger;
use App\Models\PassengerStatus;
use App\Models\Role;
use App\Models\Route;
use App\Models\StayDurationLimit;
use App\Models\TicketAgent;
use App\Models\TicketFare;
use App\Models\TransactionType;
use App\Models\TravelClass;
use App\Models\User;
use App\Models\VisaSellingPrice;
use App\Rules\FlightDateSlot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Selling fare / offer price in the Issue and Issue-Out forms (create + edit)
 * are display-only snapshots sourced from the issued_tickets row. They must
 * never be re-derived from the fare master and never submitted.
 */
class IssueFormIssuedTicketFareSourceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private array $deps;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = $this->createUser();
        $this->deps = $this->createPrerequisites();
    }

    private function createUser(): User
    {
        $branch = Branch::create([
            'name' => 'Main Branch',
            'address' => 'Addr',
            'contacts' => '0123456789',
            'location' => 'KSA',
            'fingerprint_operation' => true,
            'branch_code' => 'MAIN01',
        ]);

        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
            'is_active' => true,
            'branch_id' => $branch->id,
        ]);
        $user->roles()->attach(Role::create(['name' => 'Ticket Admin']));

        return $user;
    }

    private function createPrerequisites(): array
    {
        $district = District::create(['name' => 'D', 'division' => 'Div']);
        $c1 = CityCode::create(['city_name' => 'Dhaka', 'code' => 'DAC', 'country' => 'BD']);
        $c2 = CityCode::create(['city_name' => 'Riyadh', 'code' => 'RUH', 'country' => 'SA']);
        $airline = Airline::create(['name' => 'SV', 'code' => 'SV']);
        $travelClass = TravelClass::create(['name' => 'Economy']);
        $airlineClass = AirlineClass::create(['airline_id' => $airline->id, 'class_id' => $travelClass->id]);
        $route = Route::create([
            'airline_id' => $airline->id,
            'route_type' => 'round',
            'flight_type' => 'direct',
            'from_city_id' => $c1->id,
            'to_city_id' => $c2->id,
            'return_city_id' => $c1->id,
        ]);

        CurrencyRate::create(['user_id' => $this->user->id, 'rate' => 1.0]);
        StayDurationLimit::getOrCreate();
        FlightDateGap::getOrCreate();
        TransactionType::create(['name' => 'Initial Payment', 'type' => 'debit']);
        PassengerStatus::firstOrCreate(['name' => 'Processing'], ['color' => '#000']);
        Bank::create(['name' => 'B', 'description' => 'd', 'currency' => 'SAR', 'location' => 'KSA']);

        $visaPrice = VisaSellingPrice::create(['user_id' => $this->user->id, 'selling_price' => 2000.00]);
        $fpCharge = FingerprintCharge::create([
            'district_id' => $district->id,
            'user_id' => $this->user->id,
            'fingerprint_charge' => 50.00,
        ]);

        $fare = TicketFare::create([
            'airline_id' => $airline->id,
            'airline_classes_id' => $airlineClass->id,
            'route_id' => $route->id,
            'ticket_type' => 'regular',
            'effective_from' => now()->subDays(30),
            'effective_to' => now()->addDays(30),
            'net_fare' => 25000.00,
            'selling_fare' => 28000.00,
            'child_fare_percentage' => 75.00,
            'infant_fare_percentage' => 10.00,
            'with_meal' => true,
            'user_id' => $this->user->id,
            'is_active' => true,
        ]);

        $package = Package::create([
            'package_name' => 'Pkg',
            'ticket_fare_id' => $fare->id,
            'visa_selling_price_id' => $visaPrice->id,
            'regular_price' => 35000.00,
            'offer_price' => 32000.00,
            'service_charge' => 1500.00,
            'is_active' => true,
            'is_double_ticket' => false,
        ]);

        $customer = Customer::create([
            'name' => 'Cust',
            'passport_no' => 'T1',
            'mobile_no' => '0501',
            'iqama_type' => 'none',
            'address' => 'A',
        ]);

        return compact('district', 'customer', 'package', 'fpCharge', 'fare', 'airline', 'airlineClass', 'route');
    }

    /**
     * Creates a booking with a regular issued ticket whose snapshot fares are
     * 28000 selling / 26000 offer, left in "pending" status.
     *
     * @return array{0: Booking, 1: Passenger, 2: IssuedTicket}
     */
    private function createBookingWithPendingTicket(): array
    {
        $this->actingAs($this->user);

        $this->post(route('bookings.store'), [
            'customer_id' => $this->deps['customer']->id,
            'district_id' => $this->deps['district']->id,
            'fingerprint_charge_id' => $this->deps['fpCharge']->id,
            'fingerprint_location' => 'office',
            'pax_qty' => 1,
            'package_id' => $this->deps['package']->id,
            'passengers' => [[
                'first_name' => 'John',
                'last_name' => 'Doe',
                'passport_no' => 'PASS'.uniqid(),
                'date_of_birth' => '1990-01-15',
                'gender' => 'male',
                'passport_expiry' => '2030-12-31',
                'mobile_no' => '0501234567',
                'service_required' => 'all',
                'stay_duration' => 14,
                'flight_date_from' => FlightDateSlot::validPairForTesting()[0],
                'flight_date_to' => FlightDateSlot::validPairForTesting()[1],
                'address' => 'Addr',
            ]],
            'payment' => [
                'amount' => 100,
                'bdt_amount' => 0,
                'currency' => 'SAR',
                'payment_method' => 'cash',
                'payment_date' => now()->toDateString(),
            ],
        ])->assertRedirect();

        $passenger = Passenger::latest('id')->first();
        $issuedTicket = IssuedTicket::where('passenger_id', $passenger->id)->latest('id')->first();
        $issuedTicket->update([
            'selling_fare' => 28000,
            'offer_price' => 26000,
            'net_fare' => 25000,
        ]);

        return [$passenger->booking, $passenger, $issuedTicket->fresh()];
    }

    private function bookingsIndex(): string
    {
        return (string) file_get_contents(resource_path('views/bookings/index.blade.php'));
    }

    private function pendingOutboundReport(): string
    {
        return (string) file_get_contents(resource_path('views/reports/pending-outbound.blade.php'));
    }

    private function methodBody(string $html, string $start, string $end): string
    {
        $i = strpos($html, $start);
        $this->assertNotFalse($i, "start marker not found: {$start}");
        $j = strpos($html, $end, $i);
        $this->assertNotFalse($j, "end marker not found: {$end}");

        return substr($html, $i, $j - $i);
    }

    public function test_issue_modal_reads_fares_from_the_issued_ticket(): void
    {
        $body = $this->methodBody($this->bookingsIndex(), 'openTicketFareModal(rowIndex, ticket = null) {', 'openTicketInfoModal(');

        $this->assertStringContainsString('this.ticketFareForm.selling_fare = lit.selling_fare || 0;', $body,
            'issue modal selling fare must be sourced from the issued ticket');
        $this->assertStringContainsString('this.ticketFareForm.offer_price = lit.offer_price || 0;', $body,
            'issue modal offer price must be sourced from the issued ticket');
        $this->assertStringNotContainsString('this.ticketFareForm.selling_fare = src.selling_fare', $body,
            'selling fare must not be sourced from the re-issued snapshot');
        $this->assertStringNotContainsString('this.ticketFareForm.offer_price = src.offer_price', $body,
            'offer price must not be sourced from the re-issued snapshot');
        $this->assertStringNotContainsString('row.ticket_fare.selling_fare', $body,
            'selling fare must not be sourced from the fare master');
        $this->assertStringNotContainsString('row.ticket_fare.offer_price', $body,
            'offer price must not be sourced from the fare master');
        $this->assertStringContainsString('this.ticketFareForm.net_fare = src.net_fare', $body,
            'net fare sourcing must remain unchanged');

        $handleAt = strpos($body, 'this.handleTicketOptionChange();');
        $reapplyAt = strrpos($body, 'this.ticketFareForm.selling_fare = lit.selling_fare');
        $this->assertNotFalse($handleAt, 'handleTicketOptionChange call not found in issue modal');
        $this->assertNotFalse($reapplyAt, 'issued-ticket selling fare re-apply not found in issue modal');
        $this->assertGreaterThan($handleAt, $reapplyAt,
            'issued-ticket selling/offer fares must be re-applied after handleTicketOptionChange');
    }

    public function test_issue_out_modal_reads_fares_from_the_pending_outbound_issued_ticket(): void
    {
        $body = $this->methodBody($this->bookingsIndex(), 'openOutboundTicketFareModal(rowIndex) {', 'openOutboundEditTicketFareModal(rowIndex) {');

        $this->assertStringContainsString('this.ticketFareForm.selling_fare = pendingOutbound?.selling_fare || 0;', $body,
            'issue-out selling fare must be sourced from the pending outbound issued ticket');
        $this->assertStringContainsString('this.ticketFareForm.offer_price = pendingOutbound?.offer_price || 0;', $body,
            'issue-out offer price must be sourced from the pending outbound issued ticket');
        $this->assertStringNotContainsString('pendingSnap', $body,
            'fares must not be borrowed from the regular ticket snapshot');
        $this->assertStringNotContainsString('calculateFareForPassengerType(fare.selling_fare', $body,
            'selling fare must not be recomputed from the fare master');
        $this->assertStringNotContainsString('calculateFareForPassengerType(fare.offer_price', $body,
            'offer price must not be recomputed from the fare master');

        $handleAt = strpos($body, 'this.handleTicketOptionChange();');
        $reapplyAt = strrpos($body, '= pendingOutbound?.selling_fare');
        $this->assertNotFalse($handleAt, 'handleTicketOptionChange call not found in issue-out modal');
        $this->assertNotFalse($reapplyAt, 'pending outbound selling fare re-apply not found');
        $this->assertGreaterThan($handleAt, $reapplyAt,
            'pending outbound fares must be re-applied after handleTicketOptionChange');
    }

    public function test_issue_out_edit_modal_reads_fares_from_the_pending_outbound_issued_ticket(): void
    {
        $body = $this->methodBody($this->bookingsIndex(), 'openOutboundEditTicketFareModal(rowIndex) {', 'openTicketFareModal(rowIndex, ticket = null) {');

        $this->assertStringContainsString('this.ticketFareForm.selling_fare = poit.selling_fare || 0;', $body,
            'issue-out edit selling fare must be sourced from the pending outbound issued ticket');
        $this->assertStringContainsString('this.ticketFareForm.offer_price = poit.offer_price || 0;', $body,
            'issue-out edit offer price must be sourced from the pending outbound issued ticket');
        $this->assertStringNotContainsString('calculateFareForPassengerType(fare.selling_fare', $body,
            'selling fare must not be recomputed from the fare master');
        $this->assertStringNotContainsString('calculateFareForPassengerType(fare.offer_price', $body,
            'offer price must not be recomputed from the fare master');

        $handleAt = strpos($body, 'this.handleTicketOptionChange();');
        $reapplyAt = strrpos($body, 'this.ticketFareForm.selling_fare = poit.selling_fare');
        $this->assertNotFalse($handleAt, 'handleTicketOptionChange call not found in issue-out edit modal');
        $this->assertNotFalse($reapplyAt, 'issued-ticket selling fare re-apply not found');
        $this->assertGreaterThan($handleAt, $reapplyAt,
            'issued-ticket selling/offer fares must be re-applied after handleTicketOptionChange');
    }

    public function test_option_and_type_changes_never_overwrite_the_snapshot_fares(): void
    {
        $html = $this->bookingsIndex();

        $option = $this->methodBody($html, 'handleTicketOptionChange() {', 'get isSectorChangeReason()');
        $this->assertStringNotContainsString('selling_fare', $option,
            'changing the ticket option must not touch the issued-ticket selling fare');
        $this->assertStringNotContainsString('offer_price', $option,
            'changing the ticket option must not touch the issued-ticket offer price');

        $type = $this->methodBody($html, 'handleTicketTypeChange() {', 'getAgentIdByName(');
        $this->assertStringNotContainsString('selling_fare', $type,
            'changing the ticket type must not touch the issued-ticket selling fare');
        $this->assertStringNotContainsString('offer_price', $type,
            'changing the ticket type must not touch the issued-ticket offer price');
        $this->assertStringContainsString('net_fare', $type,
            'net fare reset behavior must remain unchanged');
    }

    public function test_issue_form_allows_zero_snapshot_fares_and_never_submits_them(): void
    {
        $html = $this->bookingsIndex();

        $this->assertStringNotContainsString('Selling fare must be greater than 0', $html,
            'selling fare is a snapshot, so 0 must be submittable');
        $this->assertStringNotContainsString('Offer price must be greater than 0', $html,
            'offer price is a snapshot, so 0 must be submittable');
        $this->assertStringContainsString("'Selling fare cannot be negative'", $html,
            'selling fare still needs a non-negative guard');
        $this->assertStringContainsString("'Offer price cannot be negative'", $html,
            'offer price still needs a non-negative guard');

        $submit = $this->methodBody($html, 'handleTicketFareSubmit() {', 'handleRouteTypeOrFlightTypeChange() {');
        $this->assertStringNotContainsString('selling_fare: parseFloat(this.ticketFareForm.selling_fare)', $submit,
            'selling fare must not be submitted');
        $this->assertStringNotContainsString('offer_price: parseFloat(this.ticketFareForm.offer_price)', $submit,
            'offer price must not be submitted');
        $this->assertStringContainsString('net_fare: parseFloat(this.ticketFareForm.net_fare) || 0,', $submit,
            'net fare submission must remain unchanged');
    }

    public function test_issue_form_shows_offer_price_only_when_the_ticket_has_one_and_locks_bdt_inputs(): void
    {
        $html = $this->bookingsIndex();

        $this->assertStringContainsString('x-show="ticketFareForm.ticket_type === \'offer\' || ticketFareForm.offer_price > 0"', $html,
            'offer price must be displayed whenever the issued ticket carries one');
        $this->assertStringContainsString('x-model="ticketFareForm.selling_fare_bdt" min="0" step="0.000001" readonly', $html,
            'BDT selling fare is a snapshot and must be readonly');
        $this->assertStringContainsString('x-model="ticketFareForm.offer_price_bdt" min="0" step="0.000001" readonly', $html,
            'BDT offer price is a snapshot and must be readonly');
    }

    public function test_saved_ticket_state_carries_offer_price_back_into_the_form(): void
    {
        $html = $this->bookingsIndex();

        $this->assertGreaterThanOrEqual(3, substr_count($html, 'offer_price: t.offer_price ?? 0'),
            'locally cached issued tickets must keep their offer price for the next open');
        $this->assertGreaterThanOrEqual(2, substr_count($html, 'offer_price: po.offer_price ?? 0'),
            'locally cached pending outbound tickets must keep their offer price for the next open');
    }

    public function test_report_issue_and_edit_modals_read_fares_from_the_current_ticket(): void
    {
        $html = $this->pendingOutboundReport();

        $issue = $this->methodBody($html, 'openIssueModal(row) {', 'openEditModal(row) {');
        $this->assertStringContainsString('row.current_ticket.selling_fare', $issue,
            'report issue selling fare must be sourced from the issued ticket');
        $this->assertStringContainsString('row.current_ticket.offer_price', $issue,
            'report issue offer price must be sourced from the issued ticket');

        $edit = $this->methodBody($html, 'openEditModal(row) {', 'closeModal() {');
        $this->assertStringContainsString('cur.selling_fare', $edit,
            'report edit selling fare must be sourced from the current issued ticket');
        $this->assertStringContainsString('cur.offer_price', $edit,
            'report edit offer price must be sourced from the current issued ticket');
        $this->assertStringNotContainsString('regular.selling_fare', $edit,
            'selling fare must not fall back to the regular ticket');
        $this->assertStringNotContainsString('regular.offer_price', $edit,
            'offer price must not fall back to the regular ticket');
        $this->assertStringNotContainsString('calculateFareForPassengerType(outboundFare.selling_fare', $edit,
            'selling fare must not be recomputed from the fare master');
        $this->assertStringNotContainsString('calculateFareForPassengerType(outboundFare.offer_price', $edit,
            'offer price must not be recomputed from the fare master');

        $option = $this->methodBody($html, 'handleTicketOptionChange() {', 'async loadData() {');
        $this->assertStringNotContainsString('selling_fare', $option,
            'changing the ticket option must not touch the snapshot selling fare');
        $this->assertStringNotContainsString('offer_price', $option,
            'changing the ticket option must not touch the snapshot offer price');

        $type = $this->methodBody($html, 'handleTicketTypeChange() {', 'handleTicketOptionChange() {');
        $this->assertStringNotContainsString('selling_fare', $type,
            'changing the ticket type must not touch the snapshot selling fare');
    }

    public function test_report_modal_displays_only_and_submits_no_snapshot_fares(): void
    {
        $html = $this->pendingOutboundReport();

        $this->assertStringContainsString('x-model="form.selling_fare" min="0" step="0.000001" readonly', $html,
            'report selling fare must be readonly');
        $this->assertStringContainsString('x-model="form.offer_price" min="0" step="0.000001" readonly', $html,
            'report offer price must be readonly');
        $this->assertStringContainsString('x-show="form.ticket_type === \'offer\' || form.offer_price > 0"', $html,
            'report offer price must be displayed whenever the issued ticket carries one');

        $submit = $this->methodBody($html, 'async handleSubmit() {', 'showToast(message, type = \'info\') {');
        $this->assertStringNotContainsString('selling_fare', $submit,
            'selling fare must not be submitted by the report modal');
        $this->assertStringNotContainsString('offer_price', $submit,
            'offer price must not be submitted by the report modal');
        $this->assertStringContainsString('net_fare: parseFloat(f.net_fare) || 0,', $submit,
            'net fare submission must remain unchanged');
    }

    public function test_pending_outbound_report_exposes_the_current_ticket_offer_price(): void
    {
        [$booking, $passenger, $regular] = $this->createBookingWithPendingTicket();
        $regular->update(['status' => 'issued']);

        IssuedTicket::create([
            'passenger_id' => $passenger->id,
            'booking_id' => $booking->id,
            'user_id' => $this->user->id,
            'issue_type' => 'pending_outbound',
            'status' => 'pending',
            'selling_fare' => 9000,
            'net_fare' => 8000,
            'offer_price' => 7000,
        ]);

        $response = $this->getJson(route('api.reports.pending-outbound'));
        $response->assertOk();

        $current = $response->json('data.0.current_ticket');
        $this->assertIsArray($current, 'report must return the current ticket payload');
        $this->assertArrayHasKey('offer_price', $current,
            'current_ticket must expose offer_price so the edit form can source it');
        $this->assertEqualsWithDelta(7000, (float) $current['offer_price'], 0.001);
        $this->assertEqualsWithDelta(9000, (float) $current['selling_fare'], 0.001);
    }

    public function test_ticket_issue_endpoint_ignores_submitted_selling_and_offer_fares(): void
    {
        [, $passenger, $ticket] = $this->createBookingWithPendingTicket();
        $ticketAgent = TicketAgent::create(['name' => 'Agent1', 'phone' => '0500', 'address' => 'Riyadh', 'contacts' => '0500']);

        $response = $this->postJson(route('bookings.passengers.ticket-issue', [
            'booking' => $passenger->booking_id,
            'passenger' => $passenger->id,
        ]), [
            'issued_ticket_id' => $ticket->id,
            'ticket_number' => 'TKT001',
            'pnr' => 'PNR001',
            'ticket_agent_id' => $ticketAgent->id,
            'issued_date' => now()->toDateString(),
            'net_fare' => 25000,
            'selling_fare' => 999999,
            'offer_price' => 888888,
        ]);

        $response->assertOk()->assertJson(['success' => true]);

        $fresh = $ticket->fresh();
        $this->assertEqualsWithDelta(28000, (float) $fresh->selling_fare, 0.001,
            'issue must keep the issued-ticket selling fare snapshot');
        $this->assertEqualsWithDelta(26000, (float) $fresh->offer_price, 0.001,
            'issue must keep the issued-ticket offer price snapshot');
        $this->assertEqualsWithDelta(25000, (float) $fresh->net_fare, 0.001,
            'net fare submission must remain honored');
    }

    public function test_ticket_edit_endpoint_ignores_submitted_selling_and_offer_fares(): void
    {
        [, $passenger, $ticket] = $this->createBookingWithPendingTicket();
        $ticket->update(['status' => 'issued']);

        $response = $this->putJson(route('bookings.passengers.ticket-edit', [
            'booking' => $passenger->booking_id,
            'passenger' => $passenger->id,
        ]), [
            'issued_ticket_id' => $ticket->id,
            'ticket_number' => 'TKT002',
            'pnr' => 'PNR002',
            'net_fare' => 24000,
            'selling_fare' => 111111,
            'offer_price' => 222222,
        ]);

        $response->assertOk()->assertJson(['success' => true]);

        $fresh = $ticket->fresh();
        $this->assertEqualsWithDelta(28000, (float) $fresh->selling_fare, 0.001,
            'edit must keep the issued-ticket selling fare snapshot');
        $this->assertEqualsWithDelta(26000, (float) $fresh->offer_price, 0.001,
            'edit must keep the issued-ticket offer price snapshot');
        $this->assertEqualsWithDelta(24000, (float) $fresh->net_fare, 0.001,
            'net fare submission must remain honored');
    }
}
