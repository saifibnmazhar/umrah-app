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
use App\Models\ReIssuedTicket;
use App\Models\ReIssueRefundReason;
use App\Models\Role;
use App\Models\Route;
use App\Models\StayDurationLimit;
use App\Models\TicketAgent;
use App\Models\TicketFare;
use App\Models\TicketRequest;
use App\Models\TransactionType;
use App\Models\TravelClass;
use App\Models\User;
use App\Models\VisaSellingPrice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * A1 + A2: issued_date is required on issue / edit / process-additional,
 * the backfill migration scopes strictly to additional tickets with a NULL
 * date, and backfilled dates move previously-NULL rows into range filtering
 * (the orWhereNull sites in Profit/Loss, Dashboard and BranchWise).
 */
class IssuedDateRequiredTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private array $deps;

    private Booking $booking;

    private Passenger $passenger;

    protected function setUp(): void
    {
        parent::setUp();

        $branch = Branch::create([
            'name' => 'Main Branch',
            'address' => 'Addr',
            'contacts' => '0123456789',
            'location' => 'KSA',
            'fingerprint_operation' => true,
            'branch_code' => 'MAIN01',
        ]);

        $this->user = User::create([
            'name' => 'Admin User',
            'email' => uniqid().'@example.com',
            'password' => bcrypt('password'),
            'is_active' => true,
            'branch_id' => $branch->id,
        ]);
        $this->user->roles()->attach(Role::create(['name' => 'Super Admin']));

        $this->deps = $this->seedPrerequisites();
        $this->booking = $this->createBooking($this->deps);
        $this->passenger = $this->addPassenger($this->deps, $this->booking);

        $this->actingAs($this->user);
    }

    private function seedPrerequisites(): array
    {
        $district = District::create(['name' => 'D', 'division' => 'Div']);
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
            'additional_gap' => null,
        ]);
        FlightDateGap::getOrCreate();
        StayDurationLimit::getOrCreate();
        CurrencyRate::create(['user_id' => $this->user->id, 'rate' => 28.0000]);
        TransactionType::create(['name' => 'Initial Payment', 'type' => 'debit']);
        TransactionType::create(['name' => 'Ticket Refund - Re-issue', 'type' => 'debit']);
        $visaPrice = VisaSellingPrice::create(['user_id' => $this->user->id, 'selling_price' => 2000.00]);
        $fare = TicketFare::create([
            'airline_id' => $airline->id,
            'airline_classes_id' => $airlineClass->id,
            'route_id' => $route->id,
            'ticket_type' => 'regular',
            'effective_from' => now()->subDays(30),
            'effective_to' => now()->addDays(30),
            'net_fare' => 24000.00,
            'selling_fare' => 30000.00,
            'offer_price' => null,
            'child_fare_percentage' => 50.00,
            'infant_fare_percentage' => 20.00,
            'with_meal' => true,
            'user_id' => $this->user->id,
            'is_active' => true,
        ]);
        $package = Package::create([
            'package_name' => 'Pkg',
            'ticket_fare_id' => $fare->id,
            'visa_selling_price_id' => $visaPrice->id,
            'regular_price' => 40000.00,
            'offer_price' => 36000.00,
            'service_charge' => 500.00,
            'is_active' => true,
            'is_double_ticket' => false,
        ]);
        $fingerprintCharge = FingerprintCharge::create([
            'district_id' => $district->id,
            'user_id' => $this->user->id,
            'fingerprint_charge' => 300.00,
        ]);
        $reason = ReIssueRefundReason::create([
            'reason_of' => 're-issue',
            'name' => 'Date Change',
            'default_payment_by' => 'customer',
        ]);
        $agent = TicketAgent::create(['name' => 'Agent '.uniqid(), 'address' => 'Addr', 'contacts' => '0123']);

        return compact('district', 'visaPrice', 'fare', 'package', 'fingerprintCharge', 'reason', 'agent');
    }

    private function createBooking(array $deps): Booking
    {
        $branch = $this->user->branch;
        $customer = Customer::create([
            'name' => 'Cust',
            'passport_no' => 'P'.substr(uniqid(), -5),
            'iqama_type' => 'none',
            'mobile_no' => '0500000000',
            'address' => 'Addr',
        ]);
        $booking = Booking::create([
            'user_id' => $this->user->id,
            'customer_id' => $customer->id,
            'fingerprint_branch_id' => $branch->id,
            'district_id' => $deps['district']->id,
            'package_id' => $deps['package']->id,
            'fingerprint_charge_id' => $deps['fingerprintCharge']->id,
            'booking_branch_id' => $branch->id,
            'invoice_id' => 'INV-'.substr(uniqid(), -8),
            'date_gap_id' => FlightDateGap::getOrCreate()->id,
            'fingerprint_location' => 'office',
            'pax_qty' => 1,
            'discount_type' => 'fixed_amount',
            'discount_value' => 0,
            'discount_amount' => 0,
            'total_value' => 40000.00,
            'remarks' => '',
            'is_cancelled' => false,
        ]);
        Invoice::create([
            'booking_id' => $booking->id,
            'branch_id' => $branch->id,
            'user_id' => $this->user->id,
            'total_amount' => 40000.00,
            'paid_amount' => 0,
            'balance' => 40000.00,
            'status' => 'pending',
        ]);

        return $booking;
    }

    private function addPassenger(array $deps, Booking $booking): Passenger
    {
        return Passenger::create([
            'booking_id' => $booking->id,
            'first_name' => 'Pax'.substr(uniqid(), -4),
            'last_name' => 'Test',
            'passport_no' => 'PP'.substr(uniqid(), -8),
            'mobile_no' => '0500000000',
            'date_of_birth' => '1990-01-01',
            'passenger_type' => 'adult',
            'passport_expiry' => '2030-12-31',
            'stay_duration' => 14,
            'service_required' => 'all',
            'flight_date_from' => now()->addDays(5)->toDateString(),
            'flight_date_to' => now()->addDays(15)->toDateString(),
            'ticket_status' => 'pending',
            'address' => 'Addr',
            'package_value' => 25000.00,
            'booking_service_charge' => $deps['package']->service_charge ?? 0,
        ]);
    }

    private function createTicket(array $attrs = []): IssuedTicket
    {
        return IssuedTicket::create(array_merge([
            'passenger_id' => $this->passenger->id,
            'booking_id' => $this->booking->id,
            'user_id' => $this->user->id,
            'ticket_agent_id' => $this->deps['agent']->id,
            'ticket_fare_id' => $this->deps['fare']->id,
            'selling_fare' => 30000.00,
            'offer_price' => 26000.00,
            'net_fare' => 24000.00,
            'status' => 'issued',
            'issue_type' => 'additional',
            'issued_date' => now()->toDateString(),
        ], $attrs));
    }

    private function runBackfill(): void
    {
        $migration = require database_path('migrations/2026_09_30_000001_backfill_additional_ticket_issued_date.php');
        $migration->up();
    }

    public function test_ticket_issue_rejects_missing_issued_date(): void
    {
        $ticket = $this->createTicket(['status' => 'pending', 'issue_type' => 'regular', 'issued_date' => null]);

        $response = $this->postJson(route('bookings.passengers.ticket-issue', [
            $this->booking->id, $this->passenger->id,
        ]), [
            'issued_ticket_id' => $ticket->id,
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['issued_date']);
    }

    public function test_ticket_issue_stores_issued_date_when_provided(): void
    {
        $ticket = $this->createTicket(['status' => 'pending', 'issue_type' => 'regular', 'issued_date' => null]);

        $response = $this->postJson(route('bookings.passengers.ticket-issue', [
            $this->booking->id, $this->passenger->id,
        ]), [
            'issued_ticket_id' => $ticket->id,
            'ticket_agent_id' => $this->deps['agent']->id,
            'issued_date' => now()->toDateString(),
        ]);

        $response->assertOk()->assertJson(['success' => true]);
        $fresh = $ticket->fresh();
        $this->assertSame('issued', $fresh->status);
        $this->assertSame(now()->toDateString(), $fresh->issued_date->toDateString());
    }

    public function test_process_additional_rejects_missing_issued_date(): void
    {
        $ticketRequest = TicketRequest::create([
            'user_id' => $this->user->id,
            'request_branch_id' => $this->user->branch_id,
            'booking_id' => $this->booking->id,
            'passenger_id' => $this->passenger->id,
            'request_type' => 'additional',
            'status' => 'pending',
            'requested_at' => now(),
        ]);

        $response = $this->putJson(route('ticket-requests.process-additional', $ticketRequest->id), [
            'ticket_fare_id' => $this->deps['fare']->id,
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['issued_date']);
    }

    public function test_ticket_edit_rejects_missing_issued_date(): void
    {
        $ticket = $this->createTicket(['issue_type' => 'regular']);

        $response = $this->putJson(route('bookings.passengers.ticket-edit', [
            $this->booking->id, $this->passenger->id,
        ]), [
            'issued_ticket_id' => $ticket->id,
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['issued_date']);
    }

    public function test_re_issue_edit_without_issued_date_preserves_re_issue_date(): void
    {
        $ticket = $this->createTicket(['issue_type' => 'regular']);
        $reIssued = ReIssuedTicket::create([
            'issued_ticket_id' => $ticket->id,
            'user_id' => $this->user->id,
            'ticket_agent_id' => $this->deps['agent']->id,
            're_issue_date' => '2026-01-15',
            'selling_fare' => 30000,
            'net_fare' => 24000,
            'offer_price' => 0,
            're_issue_charge' => 100,
            'fare_difference' => 0,
            'other_costs' => 0,
            'service_charge' => 0,
            'total_cost' => 100,
            'total_customer_payment' => 0,
            'payment_by' => 'company',
            'reason_id' => $this->deps['reason']->id,
        ]);
        $ticket->update(['status' => 're-issued']);

        $response = $this->putJson(route('bookings.passengers.ticket-edit', [
            $this->booking->id, $this->passenger->id,
        ]), [
            'issued_ticket_id' => $ticket->id,
            'ticket_agent_id' => $this->deps['agent']->id,
            're_issue_charge' => 100,
            'payment_by' => 'company',
        ]);

        $response->assertOk()->assertJson(['success' => true]);
        $this->assertSame('2026-01-15', $reIssued->fresh()->re_issue_date->toDateString());
    }

    public function test_backfill_updates_only_additional_tickets_with_null_issued_date(): void
    {
        $backfilled = $this->createTicket(['issued_date' => null]);
        $alreadySet = $this->createTicket(['issued_date' => '2020-05-05']);
        $regularNull = $this->createTicket(['issue_type' => 'regular', 'issued_date' => null]);
        $pendingNull = $this->createTicket(['issue_type' => 'pending_outbound', 'status' => 'pending', 'issued_date' => null]);

        $created = now()->subDays(40);
        DB::table('issued_tickets')->whereIn('id', [
            $backfilled->id, $alreadySet->id, $regularNull->id, $pendingNull->id,
        ])->update(['created_at' => $created]);

        $this->runBackfill();

        $this->assertSame($created->toDateString(), $backfilled->fresh()->issued_date->toDateString());
        $this->assertSame('2020-05-05', $alreadySet->fresh()->issued_date->toDateString());
        $this->assertNull($regularNull->fresh()->issued_date);
        $this->assertNull($pendingNull->fresh()->issued_date);
    }

    public function test_backfill_leaves_zero_null_additional_issued_dates(): void
    {
        $this->createTicket(['issued_date' => null]);
        $this->createTicket(['issued_date' => null]);

        $this->runBackfill();

        $remaining = IssuedTicket::where('issue_type', 'additional')
            ->whereNull('issued_date')
            ->count();
        $this->assertSame(0, $remaining);
    }

    public function test_backfilled_additional_ticket_moves_out_of_effective_summary_range(): void
    {
        // In-range passenger-level component keeps the passenger in the
        // effective filter: since A4 the exists-arm reads it.issued_date
        // directly, which a NULL date cannot match (the COALESCE log
        // fallback that previously included it is gone by design — the
        // backfill migration guarantees non-NULL dates before traffic).
        $ticket = $this->createTicket(['issued_date' => null, 'offer_price' => null]);
        DB::table('issued_tickets')->where('id', $ticket->id)
            ->update(['created_at' => now()->subDays(60)]);
        IssuedTicketLog::create([
            'issued_ticket_id' => $ticket->id,
            'user_id' => $this->user->id,
            'action' => 'issued',
            'old_data' => null,
            'new_data' => ['status' => 'issued'],
        ]);

        // AFTER the ticket fixtures: IssuedTicketObserver::created recalculates
        // and overwrites ticket_profit* columns.
        $this->passenger->updateQuietly([
            'ticket_profit' => 100.00,
            'ticket_profit_effective_at' => now()->subDays(5)->toDateTimeString(),
        ]);

        $params = [
            'effective_date_from' => now()->subDays(30)->toDateString(),
            'effective_date_to' => now()->toDateString(),
        ];

        // Pre-backfill: the orWhereNull fetch arm still pulls the NULL-dated
        // row and values it via the issue-log fallback (created_at = now,
        // inside the range): 30000 - 24000 = 6000.
        $pre = $this->getJson(route('api.reports.profit-loss.summary', $params));
        $pre->assertOk();
        $this->assertEqualsWithDelta(
            6000.0,
            (float) $pre->json('passenger.total_additional_ticket_profit'),
            0.001,
            'NULL-dated additional ticket with an in-range issue log must count before backfill'
        );

        $this->runBackfill();

        // Post-backfill: issued_date = created_at (60 days ago) is outside the
        // 30-day window, so the row no longer matches the fetch arm.
        $post = $this->getJson(route('api.reports.profit-loss.summary', $params));
        $post->assertOk();
        $this->assertEqualsWithDelta(
            0.0,
            (float) $post->json('passenger.total_additional_ticket_profit'),
            0.001,
            'Backfilled issued_date must move the ticket out of the effective range'
        );
        // The passenger remains in the set via ticket_profit_effective_at.
        $this->assertSame(1, (int) $post->json('passenger.count'));
    }
}
