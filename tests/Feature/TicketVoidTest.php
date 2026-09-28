<?php

namespace Tests\Feature;

use App\Models\Airline;
use App\Models\AirlineClass;
use App\Models\Booking;
use App\Models\Branch;
use App\Models\CityCode;
use App\Models\CurrencyRate;
use App\Models\Customer;
use App\Models\District;
use App\Models\FingerprintCharge;
use App\Models\FlightDateGap;
use App\Models\Invoice;
use App\Models\IssuedTicket;
use App\Models\IssuedTicketLog;
use App\Models\Package;
use App\Models\Passenger;
use App\Models\PassengerStatus;
use App\Models\Role;
use App\Models\Route;
use App\Models\TicketFare;
use App\Models\TicketRequest;
use App\Models\TravelClass;
use App\Models\User;
use App\Models\VisaSellingPrice;
use App\Services\ProfitCalculationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketVoidTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private array $deps = [];

    private Booking $booking;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = $this->makeAdmin();
        $this->actingAs($this->user);
        $this->deps = $this->seedDeps($this->user);
        $this->booking = $this->makeBooking();
    }

    private function makeAdmin(): User
    {
        $user = User::create([
            'name' => 'Admin',
            'email' => uniqid().'@example.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $role = Role::create(['name' => 'Super Admin']);
        $user->roles()->attach($role);

        return $user;
    }

    private function seedDeps(User $user): array
    {
        $district = District::create(['name' => 'D'.uniqid(), 'division' => 'Div']);
        $cityFrom = CityCode::create(['city_name' => 'Dhaka', 'code' => uniqid('D'), 'country' => 'BD']);
        $cityTo = CityCode::create(['city_name' => 'Riyadh', 'code' => uniqid('R'), 'country' => 'SA']);
        $airline = Airline::create(['name' => 'SV '.uniqid(), 'code' => substr(uniqid(), -2)]);
        $travelClass = TravelClass::create(['name' => 'Eco '.uniqid()]);
        $airlineClass = AirlineClass::create(['airline_id' => $airline->id, 'class_id' => $travelClass->id]);
        $route = Route::create([
            'airline_id' => $airline->id,
            'route_type' => 'round',
            'flight_type' => 'direct',
            'from_city_id' => $cityFrom->id,
            'to_city_id' => $cityTo->id,
            'return_city_id' => $cityFrom->id,
        ]);
        FlightDateGap::getOrCreate();
        CurrencyRate::create(['user_id' => $user->id, 'rate' => 28.0]);
        $visaPrice = VisaSellingPrice::create(['user_id' => $user->id, 'selling_price' => 2000]);
        $fare = TicketFare::create([
            'airline_id' => $airline->id,
            'airline_classes_id' => $airlineClass->id,
            'route_id' => $route->id,
            'ticket_type' => 'regular',
            'effective_from' => now()->subDays(30),
            'effective_to' => now()->addDays(30),
            'net_fare' => 24000,
            'selling_fare' => 30000,
            'child_fare_percentage' => 50,
            'infant_fare_percentage' => 20,
            'with_meal' => true,
            'user_id' => $user->id,
            'is_active' => true,
        ]);
        $package = Package::create([
            'package_name' => 'Pkg '.uniqid(),
            'ticket_fare_id' => $fare->id,
            'visa_selling_price_id' => $visaPrice->id,
            'regular_price' => 40000,
            'service_charge' => 500,
            'is_active' => true,
            'is_double_ticket' => false,
        ]);
        $fpCharge = FingerprintCharge::create([
            'district_id' => $district->id,
            'user_id' => $user->id,
            'fingerprint_charge' => 300,
        ]);

        return compact('district', 'package', 'fpCharge', 'visaPrice', 'fare');
    }

    private function makeBooking(): Booking
    {
        $branch = Branch::create([
            'name' => 'B'.uniqid(),
            'address' => 'Addr',
            'contacts' => '0123',
            'location' => 'KSA',
            'fingerprint_operation' => true,
            'branch_code' => 'BR'.substr(uniqid(), -6),
        ]);
        $customer = Customer::create([
            'name' => 'C'.uniqid(),
            'passport_no' => 'P'.substr(uniqid(), -5),
            'iqama_type' => 'none',
            'mobile_no' => '0500000000',
            'address' => 'Addr',
        ]);

        return Booking::create([
            'user_id' => $this->user->id,
            'customer_id' => $customer->id,
            'district_id' => $this->deps['district']->id,
            'package_id' => $this->deps['package']->id,
            'fingerprint_charge_id' => $this->deps['fpCharge']->id,
            'booking_branch_id' => $branch->id,
            'fingerprint_branch_id' => $branch->id,
            'invoice_id' => 'INV-'.substr(uniqid(), -8),
            'date_gap_id' => FlightDateGap::getOrCreate()->id,
            'fingerprint_location' => 'home',
            'pax_qty' => 1,
            'discount_type' => 'fixed_amount',
            'discount_value' => 0,
            'discount_amount' => 0,
            'total_value' => 40000,
            'is_cancelled' => false,
        ]);
    }

    private function makePassenger(array $attrs = []): Passenger
    {
        return Passenger::create(array_merge([
            'booking_id' => $this->booking->id,
            'first_name' => 'Pax',
            'last_name' => 'V'.substr(uniqid(), -4),
            'passport_no' => 'PP'.substr(uniqid(), -8),
            'mobile_no' => '0500000000',
            'date_of_birth' => '1990-01-01',
            'passenger_type' => 'adult',
            'passport_expiry' => '2030-12-31',
            'stay_duration' => 14,
            'service_required' => 'ticket_only',
            'flight_date_from' => now()->addDays(5)->toDateString(),
            'flight_date_to' => now()->addDays(15)->toDateString(),
            'ticket_status' => 'pending',
            'address' => 'Addr',
            'package_value' => 25000,
        ], $attrs));
    }

    private function makeTicket(Passenger $passenger, array $attrs = []): IssuedTicket
    {
        return IssuedTicket::create(array_merge([
            'passenger_id' => $passenger->id,
            'booking_id' => $this->booking->id,
            'user_id' => $this->user->id,
            'status' => 'pending',
        ], $attrs));
    }

    private function issuePayload(IssuedTicket $ticket, array $overrides = []): array
    {
        return array_merge([
            'issued_ticket_id' => $ticket->id,
            'ticket_number' => '176-'.substr(uniqid(), -9),
            'pnr' => strtoupper(substr(uniqid(), -6)),
            'ticket_fare_id' => $this->deps['fare']->id,
            'net_fare' => 24000,
            'issued_date' => now()->toDateString(),
            'inbound_date' => now()->addDays(5)->toDateString(),
            'outbound_date' => now()->addDays(10)->toDateString(),
            'outbound_pending' => false,
        ], $overrides);
    }

    private function issueTicket(Passenger $passenger, IssuedTicket $ticket, array $overrides = [])
    {
        return $this->postJson(
            route('bookings.passengers.ticket-issue', [$this->booking->id, $passenger->id]),
            $this->issuePayload($ticket, $overrides)
        );
    }

    private function callVoid(Passenger $passenger, IssuedTicket $ticket, ?Booking $booking = null)
    {
        return $this->postJson(
            route('bookings.passengers.ticket-void', [($booking ?? $this->booking)->id, $passenger->id]),
            ['issued_ticket_id' => $ticket->id]
        );
    }

    public function test_void_reverts_ticket_to_pre_issue_state(): void
    {
        $passenger = $this->makePassenger();
        $ticket = $this->makeTicket($passenger, ['selling_fare' => 30000]);

        $this->issueTicket($passenger, $ticket)->assertOk();

        $ticket->refresh();
        $this->assertEquals('issued', $ticket->status);
        $this->assertNotNull($ticket->ticket_number);

        $this->callVoid($passenger, $ticket)->assertOk()->assertJson(['success' => true]);

        $ticket->refresh();
        $this->assertEquals('pending', $ticket->status);
        $this->assertNull($ticket->ticket_number);
        $this->assertNull($ticket->pnr);
        $this->assertEquals(0.0, (float) $ticket->net_fare);
        $this->assertNull($ticket->ticket_fare_id);
        $this->assertNull($ticket->issued_date);
    }

    public function test_void_reverts_passenger_ticket_status(): void
    {
        $passenger = $this->makePassenger();
        $ticket = $this->makeTicket($passenger, ['selling_fare' => 30000]);

        $this->issueTicket($passenger, $ticket)->assertOk();
        $this->assertEquals('issued', $passenger->refresh()->ticket_status->value);

        $this->callVoid($passenger, $ticket)->assertOk();

        $this->assertEquals('pending', $passenger->refresh()->ticket_status->value);
    }

    public function test_void_keeps_passenger_ticket_status_when_other_ticket_issued(): void
    {
        $passenger = $this->makePassenger();
        $first = $this->makeTicket($passenger, ['selling_fare' => 30000]);
        $second = $this->makeTicket($passenger, ['selling_fare' => 30000]);

        $this->issueTicket($passenger, $first)->assertOk();
        $this->issueTicket($passenger, $second)->assertOk();

        $this->callVoid($passenger, $first)->assertOk();

        $this->assertEquals('issued', $passenger->refresh()->ticket_status->value);
        $this->assertEquals('pending', $first->refresh()->status);
        $this->assertEquals('issued', $second->refresh()->status);
    }

    public function test_void_writes_void_audit_log(): void
    {
        $passenger = $this->makePassenger();
        $ticket = $this->makeTicket($passenger, ['selling_fare' => 30000]);

        $this->issueTicket($passenger, $ticket)->assertOk();
        $this->callVoid($passenger, $ticket)->assertOk();

        $log = IssuedTicketLog::where('issued_ticket_id', $ticket->id)
            ->where('action', 'void')
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $this->assertEquals('issued', $log->old_data['status']);
        $this->assertEquals('pending', $log->new_data['status']);
    }

    public function test_void_rejected_when_ticket_not_issued(): void
    {
        $passenger = $this->makePassenger();
        $ticket = $this->makeTicket($passenger);

        $this->callVoid($passenger, $ticket)->assertStatus(400);
    }

    public function test_void_rejected_when_no_issue_log_for_regular_ticket(): void
    {
        $passenger = $this->makePassenger();
        $ticket = $this->makeTicket($passenger, [
            'status' => 'issued',
            'issue_type' => 'regular',
            'selling_fare' => 30000,
            'issued_date' => now()->toDateString(),
        ]);

        $this->callVoid($passenger, $ticket)->assertStatus(400);
    }

    public function test_void_rejected_when_pending_request_exists(): void
    {
        $passenger = $this->makePassenger();
        $ticket = $this->makeTicket($passenger, ['selling_fare' => 30000]);
        $this->issueTicket($passenger, $ticket)->assertOk();

        TicketRequest::create([
            'user_id' => $this->user->id,
            'booking_id' => $this->booking->id,
            'passenger_id' => $passenger->id,
            'issued_ticket_id' => $ticket->id,
            'request_type' => 're_issue',
            'status' => 'pending',
            'requested_at' => now(),
        ]);

        $this->callVoid($passenger, $ticket)->assertStatus(400);
    }

    public function test_void_rejected_for_hold_or_cancel_passenger(): void
    {
        $hold = PassengerStatus::create(['name' => 'Hold']);
        $passenger = $this->makePassenger(['passenger_status_id' => $hold->id]);
        $ticket = $this->makeTicket($passenger, ['status' => 'issued', 'issue_type' => 'regular']);

        $this->callVoid($passenger, $ticket)->assertStatus(422);
    }

    public function test_void_blade_renders_void_button_gated_on_issued_status(): void
    {
        $src = file_get_contents(resource_path('views/bookings/index.blade.php'));

        $this->assertStringContainsString('handleTicketVoid', $src);
        $this->assertStringContainsString("<template x-if=\"ticket.status === 'issued'\">", $src);
    }

    public function test_void_issue_void_issue_void_cycle_restores_latest_pre_issue_state(): void
    {
        $passenger = $this->makePassenger();
        $ticket = $this->makeTicket($passenger, ['selling_fare' => 30000]);

        $this->issueTicket($passenger, $ticket, [
            'ticket_number' => 'TN-1',
            'pnr' => 'PNR1',
            'net_fare' => 10000,
        ])->assertOk();
        $this->callVoid($passenger, $ticket)->assertOk();

        $this->putJson(route('bookings.passengers.ticket-edit', [$this->booking->id, $passenger->id]), [
            'issued_ticket_id' => $ticket->id,
            'ticket_number' => 'TN-EDITED',
            'pnr' => 'PNR-EDITED',
            'net_fare' => 5000,
        ])->assertOk();

        $this->issueTicket($passenger, $ticket, [
            'ticket_number' => 'TN-2',
            'pnr' => 'PNR2',
            'net_fare' => 20000,
        ])->assertOk();

        $this->callVoid($passenger, $ticket)->assertOk();

        $ticket->refresh();
        $this->assertEquals('pending', $ticket->status);
        $this->assertEquals('TN-EDITED', $ticket->ticket_number);
        $this->assertEquals('PNR-EDITED', $ticket->pnr);
        $this->assertEquals(5000.0, (float) $ticket->net_fare);
    }

    public function test_void_restores_awaiting_group_state(): void
    {
        $passenger = $this->makePassenger();
        $ticket = $this->makeTicket($passenger, ['selling_fare' => 30000]);

        $this->putJson(route('passengers.confirm-group', $passenger->id), [
            'action' => 'all',
            'booking_id' => $this->booking->id,
        ])->assertOk();

        $this->assertEquals('awaiting-group', $ticket->refresh()->status);

        $this->issueTicket($passenger, $ticket)->assertOk();
        $this->callVoid($passenger, $ticket)->assertOk();

        $this->assertEquals('awaiting-group', $ticket->refresh()->status);
        $this->assertEquals('pending', $passenger->refresh()->ticket_status->value);
    }

    public function test_void_excludes_ticket_profit_and_effectiveness(): void
    {
        $passenger = $this->makePassenger(['service_required' => 'ticket_only']);
        $ticket = $this->makeTicket($passenger, ['selling_fare' => 30000, 'net_fare' => 0]);

        $this->issueTicket($passenger, $ticket, ['net_fare' => 24000])->assertOk();

        $passenger->refresh();
        $this->assertGreaterThan(0, (float) $passenger->ticket_profit);
        $this->assertNotNull($passenger->ticket_profit_effective_at);
        $this->assertGreaterThan(0, (float) $this->booking->refresh()->profit);

        $this->callVoid($passenger, $ticket)->assertOk();

        $passenger->refresh();
        $this->assertEquals(0.0, (float) $passenger->ticket_profit);
        $this->assertNull($passenger->ticket_profit_effective_at);
        $this->assertEquals(0.0, (float) $this->booking->refresh()->profit);
    }

    public function test_void_one_leg_of_double_ticket_makes_ticket_profit_not_effective(): void
    {
        $passenger = $this->makePassenger(['service_required' => 'ticket_only']);
        $inbound = $this->makeTicket($passenger, ['selling_fare' => 30000, 'net_fare' => 0]);
        $outbound = $this->makeTicket($passenger, [
            'selling_fare' => 30000,
            'net_fare' => 0,
            'issue_type' => 'pending_outbound',
        ]);

        $this->issueTicket($passenger, $inbound, ['net_fare' => 24000])->assertOk();
        $this->issueTicket($passenger, $outbound, ['net_fare' => 24000])->assertOk();

        $passenger->refresh();
        $this->assertGreaterThan(0, (float) $passenger->ticket_profit);
        $this->assertNotNull($passenger->ticket_profit_effective_at);

        $this->callVoid($passenger, $inbound)->assertOk();

        $passenger->refresh();
        $this->assertEquals(0.0, (float) $passenger->ticket_profit);
        $this->assertNull($passenger->ticket_profit_effective_at);
        $this->assertEquals(0.0, (float) $this->booking->refresh()->profit);
    }

    public function test_void_regular_hard_deletes_pending_companion(): void
    {
        $passenger = $this->makePassenger();
        $regular = $this->makeTicket($passenger, ['selling_fare' => 30000, 'outbound_pending' => true]);
        $companion = $this->makeTicket($passenger, ['issue_type' => 'pending_outbound', 'status' => 'pending']);

        $this->issueTicket($passenger, $regular, ['outbound_pending' => true])->assertOk();
        $this->assertEquals('issued', $regular->refresh()->status);

        $this->callVoid($passenger, $regular)->assertOk();

        $this->assertDatabaseMissing('issued_tickets', ['id' => $companion->id]);

        $regular->refresh();
        $this->assertEquals('pending', $regular->status);
        $this->assertFalse((bool) $regular->outbound_pending);
    }

    public function test_void_regular_keeps_issued_companion_sets_outbound_pending_true(): void
    {
        $passenger = $this->makePassenger();
        $regular = $this->makeTicket($passenger, ['selling_fare' => 30000]);
        $companion = $this->makeTicket($passenger, [
            'issue_type' => 'pending_outbound',
            'selling_fare' => 30000,
            'net_fare' => 0,
        ]);

        $this->issueTicket($passenger, $regular, ['outbound_pending' => true])->assertOk();
        $this->issueTicket($passenger, $companion, ['net_fare' => 24000])->assertOk();

        $this->callVoid($passenger, $regular)->assertOk();

        $companion->refresh();
        $this->assertNull($companion->deleted_at);
        $this->assertEquals('issued', $companion->status);

        $regular->refresh();
        $this->assertEquals('pending', $regular->status);
        $this->assertTrue((bool) $regular->outbound_pending);
    }

    public function test_void_additional_soft_deletes_and_excludes_profit(): void
    {
        $passenger = $this->makePassenger(['service_required' => 'ticket_only']);
        $ticket = $this->makeTicket($passenger, [
            'issue_type' => 'additional',
            'status' => 'issued',
            'selling_fare' => 30000,
            'net_fare' => 24000,
            'ticket_fare_id' => $this->deps['fare']->id,
            'issued_date' => now()->toDateString(),
        ]);
        $passenger->updateQuietly(['ticket_status' => 'issued']);
        app(ProfitCalculationService::class)->recalculateBookingProfit($this->booking);

        $this->assertGreaterThan(0, (float) $passenger->refresh()->profit);

        $this->callVoid($passenger, $ticket)->assertOk();

        $ticket->refresh();
        $this->assertNotNull($ticket->deleted_at);

        $this->assertEquals(0.0, (float) $passenger->refresh()->profit);

        $response = $this->getJson(route('api.bookings.passengers'))->assertOk();
        $payloadIds = collect($response->json('data.0.ticket_data.all_issued_tickets') ?? [])->pluck('id');
        $this->assertNotContains($ticket->id, $payloadIds->all());
    }

    public function test_void_additional_reverses_invoice_and_reopens_request(): void
    {
        $passenger = $this->makePassenger();
        $invoice = Invoice::create([
            'booking_id' => $this->booking->id,
            'branch_id' => $this->booking->booking_branch_id,
            'user_id' => $this->user->id,
            'total_amount' => 40000,
            'paid_amount' => 0,
            'balance' => 40000,
        ]);

        $ticket = $this->makeTicket($passenger, [
            'issue_type' => 'additional',
            'status' => 'issued',
            'selling_fare' => 30000,
            'net_fare' => 24000,
            'ticket_fare_id' => $this->deps['fare']->id,
            'issued_date' => now()->toDateString(),
        ]);

        $ticketRequest = TicketRequest::create([
            'user_id' => $this->user->id,
            'booking_id' => $this->booking->id,
            'passenger_id' => $passenger->id,
            'request_type' => 'additional',
            'status' => 'processed',
            'processed_at' => now(),
            'result_issued_ticket_id' => $ticket->id,
            'requested_at' => now()->subDay(),
        ]);

        $this->callVoid($passenger, $ticket)->assertOk();

        $this->assertEquals(10000.0, (float) $invoice->refresh()->total_amount);

        $ticketRequest->refresh();
        $this->assertEquals('pending', $ticketRequest->status);
        $this->assertNull($ticketRequest->processed_at);
        $this->assertNull($ticketRequest->result_issued_ticket_id);
    }

    public function test_void_rejected_for_visa_only_passenger(): void
    {
        $passenger = $this->makePassenger(['service_required' => 'visa_only']);
        $ticket = $this->makeTicket($passenger, ['status' => 'issued', 'issue_type' => 'regular']);

        $this->callVoid($passenger, $ticket)->assertStatus(403);
    }

    public function test_issue_log_superseded_by_void_not_counted(): void
    {
        $passenger = $this->makePassenger();
        $ticket = $this->makeTicket($passenger, ['status' => 'issued']);

        $issuedLog = IssuedTicketLog::create([
            'issued_ticket_id' => $ticket->id,
            'user_id' => $this->user->id,
            'action' => 'issued',
            'old_data' => ['status' => 'pending'],
            'new_data' => ['status' => 'issued'],
        ]);
        IssuedTicketLog::create([
            'issued_ticket_id' => $ticket->id,
            'user_id' => $this->user->id,
            'action' => 'void',
            'old_data' => ['status' => 'issued'],
            'new_data' => ['status' => 'pending'],
        ]);

        $counted = fn () => IssuedTicketLog::where('issued_ticket_id', $ticket->id)
            ->where('action', 'issued')
            ->notSupersededByVoid()
            ->count();

        $this->assertEquals(0, $counted());

        IssuedTicketLog::create([
            'issued_ticket_id' => $ticket->id,
            'user_id' => $this->user->id,
            'action' => 'issued',
            'old_data' => ['status' => 'pending'],
            'new_data' => ['status' => 'issued'],
        ]);

        $this->assertEquals(1, $counted());
        $this->assertNotNull($issuedLog->id);
    }

    public function test_voided_ticket_excluded_from_ticket_issued_filter(): void
    {
        $passenger = $this->makePassenger();
        $ticket = $this->makeTicket($passenger, ['selling_fare' => 30000]);

        $this->issueTicket($passenger, $ticket)->assertOk();

        $before = $this->getJson(route('api.bookings.passengers', ['status_change_action' => 'ticket_issued']))
            ->assertOk();
        $beforeIds = collect($before->json('data'))->pluck('id')->all();
        $this->assertContains($passenger->id, $beforeIds);

        $this->callVoid($passenger, $ticket)->assertOk();

        $after = $this->getJson(route('api.bookings.passengers', ['status_change_action' => 'ticket_issued']))
            ->assertOk();
        $afterIds = collect($after->json('data'))->pluck('id')->all();
        $this->assertNotContains($passenger->id, $afterIds);
    }

    public function test_void_booking_passenger_mismatch_403(): void
    {
        $otherBooking = $this->makeBooking();
        $passenger = $this->makePassenger();
        $ticket = $this->makeTicket($passenger, ['status' => 'issued', 'issue_type' => 'regular']);

        $this->postJson(route('bookings.passengers.ticket-void', [$otherBooking->id, $passenger->id]), [
            'issued_ticket_id' => $ticket->id,
        ])->assertStatus(403);
    }
}
