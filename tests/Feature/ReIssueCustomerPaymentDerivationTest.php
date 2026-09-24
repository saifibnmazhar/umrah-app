<?php

namespace Tests\Feature;

use App\Models\Airline;
use App\Models\AirlineClass;
use App\Models\Bank;
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
use App\Models\RefundedTicket;
use App\Models\ReIssuedTicket;
use App\Models\ReIssueRefundReason;
use App\Models\Role;
use App\Models\Route;
use App\Models\StayDurationLimit;
use App\Models\TicketFare;
use App\Models\TicketRequest;
use App\Models\TransactionType;
use App\Models\TravelClass;
use App\Models\User;
use App\Models\VisaSellingPrice;
use App\Rules\FlightDateSlot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReIssueCustomerPaymentDerivationTest extends TestCase
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
        TransactionType::create(['name' => 'Ticket Refund - Re-issue', 'type' => 'debit']);
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

        $reason = ReIssueRefundReason::create([
            'reason_of' => 're-issue',
            'name' => 'Date Change',
            'default_payment_by' => 'customer',
        ]);

        return compact('district', 'customer', 'package', 'fpCharge', 'fare', 'airline', 'airlineClass', 'route', 'reason');
    }

    private function createBookingWithIssuedTicket(): array
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
        $issuedTicket->update(['status' => 'issued', 'net_fare' => 25000, 'selling_fare' => 28000]);

        return [$passenger->booking, $passenger->fresh(), $issuedTicket->fresh()];
    }

    public function test_reissue_store_defaults_fare_difference_and_other_costs_to_zero(): void
    {
        [$booking, $passenger, $issuedTicket] = $this->createBookingWithIssuedTicket();

        $response = $this->postJson(route('bookings.passengers.re-issue', [$booking->id, $passenger->id]), [
            'issued_ticket_id' => $issuedTicket->id,
            'reason_id' => $this->deps['reason']->id,
            're_issue_charge' => 100,
            'payment_by' => 'company',
            'payment_option' => 'customer_payment',
        ]);

        $response->assertOk()->assertJson(['success' => true]);

        $reIssued = ReIssuedTicket::latest('id')->first();
        $this->assertEqualsWithDelta(0, (float) $reIssued->fare_difference, 0.001);
        $this->assertEqualsWithDelta(0, (float) $reIssued->other_costs, 0.001);
        $this->assertEqualsWithDelta(100, (float) $reIssued->total_cost, 0.001);
    }

    public function test_reissue_store_derives_service_charge_from_total_customer_payment(): void
    {
        [$booking, $passenger, $issuedTicket] = $this->createBookingWithIssuedTicket();
        $invoiceBefore = (float) ($booking->invoice?->fresh()->total_amount ?? 0);

        $response = $this->postJson(route('bookings.passengers.re-issue', [$booking->id, $passenger->id]), [
            'issued_ticket_id' => $issuedTicket->id,
            'reason_id' => $this->deps['reason']->id,
            're_issue_charge' => 100,
            'payment_by' => 'customer',
            'payment_option' => 'customer_payment',
            'total_customer_payment' => 150,
        ]);

        $response->assertOk()->assertJson(['success' => true]);

        $reIssued = ReIssuedTicket::latest('id')->first();
        $this->assertEqualsWithDelta(100, (float) $reIssued->total_cost, 0.001);
        $this->assertEqualsWithDelta(50, (float) $reIssued->service_charge, 0.001);
        $this->assertEqualsWithDelta(150, (float) $reIssued->total_customer_payment, 0.001);

        if ($booking->invoice) {
            $this->assertEqualsWithDelta(
                $invoiceBefore + 150,
                (float) $booking->invoice->fresh()->total_amount,
                0.001
            );
        }
    }

    public function test_reissue_store_rejects_total_customer_payment_below_cost(): void
    {
        [$booking, $passenger, $issuedTicket] = $this->createBookingWithIssuedTicket();

        $response = $this->postJson(route('bookings.passengers.re-issue', [$booking->id, $passenger->id]), [
            'issued_ticket_id' => $issuedTicket->id,
            'reason_id' => $this->deps['reason']->id,
            're_issue_charge' => 100,
            'payment_by' => 'customer',
            'payment_option' => 'customer_payment',
            'total_customer_payment' => 50,
        ]);

        $response->assertStatus(422);
    }

    public function test_reissue_store_non_customer_forces_zero_service_and_payment(): void
    {
        [$booking, $passenger, $issuedTicket] = $this->createBookingWithIssuedTicket();
        $invoiceBefore = (float) ($booking->invoice?->fresh()->total_amount ?? 0);

        $response = $this->postJson(route('bookings.passengers.re-issue', [$booking->id, $passenger->id]), [
            'issued_ticket_id' => $issuedTicket->id,
            'reason_id' => $this->deps['reason']->id,
            're_issue_charge' => 100,
            'payment_by' => 'company',
            'payment_option' => 'customer_payment',
        ]);

        $response->assertOk()->assertJson(['success' => true]);

        $reIssued = ReIssuedTicket::latest('id')->first();
        $this->assertEqualsWithDelta(0, (float) $reIssued->service_charge, 0.001);
        $this->assertEqualsWithDelta(0, (float) $reIssued->total_customer_payment, 0.001);

        if ($booking->invoice) {
            $this->assertEqualsWithDelta(
                $invoiceBefore,
                (float) $booking->invoice->fresh()->total_amount,
                0.001
            );
        }
    }

    private function createBookingWithRefundedTicket(): array
    {
        [$booking, $passenger, $issuedTicket] = $this->createBookingWithIssuedTicket();

        RefundedTicket::create([
            'issued_ticket_id' => $issuedTicket->id,
            'user_id' => $this->user->id,
            'net_fare' => 1000,
        ]);
        $issuedTicket->update(['status' => 'refunded']);
        $passenger->update(['refund_payable' => 500]);

        return [$booking->fresh(), $passenger->fresh(), $issuedTicket->fresh()];
    }

    public function test_was_refunded_non_customer_refund_adjustment_has_no_customer_payment_or_invoice_impact(): void
    {
        [$booking, $passenger, $issuedTicket] = $this->createBookingWithRefundedTicket();
        $invoiceBefore = (float) ($booking->invoice?->fresh()->total_amount ?? 0);

        $response = $this->postJson(route('bookings.passengers.re-issue', [$booking->id, $passenger->id]), [
            'issued_ticket_id' => $issuedTicket->id,
            'reason_id' => $this->deps['reason']->id,
            're_issue_charge' => 100,
            'payment_by' => 'company',
            'payment_option' => 'refund_adjustment',
            'refund_adjustment_amount' => 200,
        ]);

        $response->assertOk()->assertJson(['success' => true]);

        $reIssued = ReIssuedTicket::latest('id')->first();
        // total_cost = 100 (charge) + 1000 (refunded fare) - 200 (adjustment)
        $this->assertEqualsWithDelta(900, (float) $reIssued->total_cost, 0.001);
        $this->assertEqualsWithDelta(0, (float) $reIssued->service_charge, 0.001);
        $this->assertEqualsWithDelta(0, (float) $reIssued->total_customer_payment, 0.001);

        if ($booking->invoice) {
            $this->assertEqualsWithDelta(
                $invoiceBefore,
                (float) $booking->invoice->fresh()->total_amount,
                0.001
            );
        }

        // Refund-adjustment flow itself is preserved: balance consumed + trail rows.
        $this->assertDatabaseHas('passengers', [
            'id' => $passenger->id,
            'refund_payable' => 300,
        ]);
        $this->assertDatabaseHas('payments', [
            're_issued_ticket_id' => $reIssued->id,
            'amount' => 200,
        ]);
    }

    public function test_process_reissue_was_refunded_non_customer_has_no_customer_payment_or_invoice_impact(): void
    {
        [$booking, $passenger, $issuedTicket] = $this->createBookingWithRefundedTicket();
        $invoiceBefore = (float) ($booking->invoice?->fresh()->total_amount ?? 0);

        $ticketRequest = TicketRequest::create([
            'user_id' => $this->user->id,
            'request_branch_id' => $this->user->branch_id,
            'booking_id' => $booking->id,
            'passenger_id' => $passenger->id,
            'issued_ticket_id' => $issuedTicket->id,
            'request_type' => 're_issue',
            'status' => 'pending',
            'requested_at' => now(),
        ]);

        $response = $this->putJson(route('ticket-requests.process-reissue', $ticketRequest->id), [
            'reason_id' => $this->deps['reason']->id,
            're_issue_charge' => 100,
            'payment_by' => 'company',
            'payment_option' => 'refund_adjustment',
            'refund_adjustment_amount' => 200,
            'ticket_fare_id' => $this->deps['fare']->id,
        ]);

        $response->assertOk()->assertJson(['success' => true]);

        $reIssued = ReIssuedTicket::latest('id')->first();
        $this->assertEqualsWithDelta(900, (float) $reIssued->total_cost, 0.001);
        $this->assertEqualsWithDelta(0, (float) $reIssued->service_charge, 0.001);
        $this->assertEqualsWithDelta(0, (float) $reIssued->total_customer_payment, 0.001);

        if ($booking->invoice) {
            $this->assertEqualsWithDelta(
                $invoiceBefore,
                (float) $booking->invoice->fresh()->total_amount,
                0.001
            );
        }

        $this->assertDatabaseHas('passengers', [
            'id' => $passenger->id,
            'refund_payable' => 300,
        ]);
        $this->assertDatabaseHas('payments', [
            're_issued_ticket_id' => $reIssued->id,
            'amount' => 200,
        ]);
    }

    public function test_process_reissue_derives_service_charge_from_total_customer_payment(): void
    {
        [$booking, $passenger, $issuedTicket] = $this->createBookingWithIssuedTicket();

        $ticketRequest = TicketRequest::create([
            'user_id' => $this->user->id,
            'request_branch_id' => $this->user->branch_id,
            'booking_id' => $booking->id,
            'passenger_id' => $passenger->id,
            'issued_ticket_id' => $issuedTicket->id,
            'request_type' => 're_issue',
            'status' => 'pending',
            'requested_at' => now(),
        ]);

        $response = $this->putJson(route('ticket-requests.process-reissue', $ticketRequest->id), [
            'reason_id' => $this->deps['reason']->id,
            're_issue_charge' => 100,
            'payment_by' => 'customer',
            'payment_option' => 'customer_payment',
            'total_customer_payment' => 150,
            'ticket_fare_id' => $this->deps['fare']->id,
        ]);

        $response->assertOk()->assertJson(['success' => true]);

        $reIssued = ReIssuedTicket::latest('id')->first();
        $this->assertEqualsWithDelta(0, (float) $reIssued->fare_difference, 0.001);
        $this->assertEqualsWithDelta(0, (float) $reIssued->other_costs, 0.001);
        $this->assertEqualsWithDelta(100, (float) $reIssued->total_cost, 0.001);
        $this->assertEqualsWithDelta(50, (float) $reIssued->service_charge, 0.001);
        $this->assertEqualsWithDelta(150, (float) $reIssued->total_customer_payment, 0.001);
    }

    public function test_edit_reissue_preserves_legacy_fare_difference_and_other_costs(): void
    {
        [$booking, $passenger, $issuedTicket] = $this->createBookingWithIssuedTicket();

        $reIssued = ReIssuedTicket::create([
            'issued_ticket_id' => $issuedTicket->id,
            'user_id' => $this->user->id,
            're_issue_charge' => 100,
            'fare_difference' => 50,
            'other_costs' => 25,
            'service_charge' => 0,
            'total_cost' => 175,
            'total_customer_payment' => 0,
            'payment_by' => 'company',
            'reason_id' => $this->deps['reason']->id,
        ]);
        $issuedTicket->update(['status' => 're-issued']);

        $response = $this->putJson(route('bookings.passengers.ticket-edit', [$booking->id, $passenger->id]), [
            'issued_ticket_id' => $issuedTicket->id,
            're_issue_charge' => 120,
            'payment_by' => 'company',
        ]);

        $response->assertOk()->assertJson(['success' => true]);

        // Legacy 50 + 25 preserved: total_cost = 120 + 50 + 25 = 195
        $this->assertDatabaseHas('re_issued_tickets', [
            'id' => $reIssued->id,
            'fare_difference' => 50,
            'other_costs' => 25,
            'total_cost' => 195,
        ]);
    }

    public function test_edit_reissue_recomputes_service_from_total_payment(): void
    {
        [$booking, $passenger, $issuedTicket] = $this->createBookingWithIssuedTicket();

        $this->postJson(route('bookings.passengers.re-issue', [$booking->id, $passenger->id]), [
            'issued_ticket_id' => $issuedTicket->id,
            'reason_id' => $this->deps['reason']->id,
            're_issue_charge' => 100,
            'payment_by' => 'customer',
            'payment_option' => 'customer_payment',
            'total_customer_payment' => 150,
        ])->assertOk();

        $invoiceBefore = (float) $booking->invoice?->fresh()->total_amount;

        $response = $this->putJson(route('bookings.passengers.ticket-edit', [$booking->id, $passenger->id]), [
            'issued_ticket_id' => $issuedTicket->id,
            'payment_by' => 'customer',
            'total_customer_payment' => 180,
        ]);

        $response->assertOk()->assertJson(['success' => true]);

        $reIssued = ReIssuedTicket::latest('id')->first();
        $this->assertEqualsWithDelta(100, (float) $reIssued->total_cost, 0.001);
        $this->assertEqualsWithDelta(80, (float) $reIssued->service_charge, 0.001);
        $this->assertEqualsWithDelta(180, (float) $reIssued->total_customer_payment, 0.001);

        if ($booking->invoice) {
            $this->assertEqualsWithDelta(
                $invoiceBefore + 30,
                (float) $booking->invoice->fresh()->total_amount,
                0.001
            );
        }
    }

    public function test_edit_reissue_rejects_total_customer_payment_below_cost(): void
    {
        [$booking, $passenger, $issuedTicket] = $this->createBookingWithIssuedTicket();

        $this->postJson(route('bookings.passengers.re-issue', [$booking->id, $passenger->id]), [
            'issued_ticket_id' => $issuedTicket->id,
            'reason_id' => $this->deps['reason']->id,
            're_issue_charge' => 100,
            'payment_by' => 'customer',
            'payment_option' => 'customer_payment',
            'total_customer_payment' => 150,
        ])->assertOk();

        $response = $this->putJson(route('bookings.passengers.ticket-edit', [$booking->id, $passenger->id]), [
            'issued_ticket_id' => $issuedTicket->id,
            'payment_by' => 'customer',
            'total_customer_payment' => 50,
        ]);

        $response->assertStatus(422);
    }

    public function test_process_reissue_rejects_total_customer_payment_below_cost(): void
    {
        [$booking, $passenger, $issuedTicket] = $this->createBookingWithIssuedTicket();

        $ticketRequest = TicketRequest::create([
            'user_id' => $this->user->id,
            'request_branch_id' => $this->user->branch_id,
            'booking_id' => $booking->id,
            'passenger_id' => $passenger->id,
            'issued_ticket_id' => $issuedTicket->id,
            'request_type' => 're_issue',
            'status' => 'pending',
            'requested_at' => now(),
        ]);

        $response = $this->putJson(route('ticket-requests.process-reissue', $ticketRequest->id), [
            'reason_id' => $this->deps['reason']->id,
            're_issue_charge' => 100,
            'payment_by' => 'customer',
            'payment_option' => 'customer_payment',
            'total_customer_payment' => 50,
            'ticket_fare_id' => $this->deps['fare']->id,
        ]);

        $response->assertStatus(422);
    }
}
