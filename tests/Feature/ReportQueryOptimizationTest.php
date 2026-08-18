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
use App\Models\Fingerprint;
use App\Models\FingerprintCharge;
use App\Models\FlightDateGap;
use App\Models\Invoice;
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
use App\Models\VisaAgent;
use App\Models\VisaAgentCost;
use App\Models\VisaSellingPrice;
use App\Models\VisaSubmission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReportQueryOptimizationTest extends TestCase
{
    use RefreshDatabase;

    private function setupUser(): User
    {
        $user = User::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'is_active' => true,
            'branch_id' => null,
        ]);
        $user->roles()->attach(Role::create(['name' => 'Super Admin']));

        return $user;
    }

    private function seedAllPrerequisites(User $user): array
    {
        $district = District::create(['name' => 'Test District', 'division' => 'Test Division']);

        $cityFrom = CityCode::create(['city_name' => 'Dhaka', 'code' => 'DAC', 'country' => 'Bangladesh']);
        $cityTo = CityCode::create(['city_name' => 'Riyadh', 'code' => 'RUH', 'country' => 'Saudi Arabia']);

        $airline = Airline::create(['name' => 'Saudi Arabian Airlines', 'code' => 'SV']);
        $travelClass = TravelClass::create(['name' => 'Economy']);
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

        $flightDateGap = FlightDateGap::getOrCreate();
        StayDurationLimit::getOrCreate();

        $currencyRate = CurrencyRate::create([
            'user_id' => $user->id,
            'rate' => 28.0000,
        ]);

        $visaPrice = VisaSellingPrice::create([
            'user_id' => $user->id,
            'selling_price' => 2000.00,
        ]);

        $ticketFare = TicketFare::create([
            'airline_id' => $airline->id,
            'airline_classes_id' => $airlineClass->id,
            'route_id' => $route->id,
            'ticket_type' => 'regular',
            'effective_from' => now()->subDays(30),
            'effective_to' => now()->addDays(30),
            'net_fare' => 25000.00,
            'selling_fare' => 28000.00,
            'offer_price' => null,
            'child_fare_percentage' => 75.00,
            'infant_fare_percentage' => 10.00,
            'with_meal' => true,
            'user_id' => $user->id,
            'is_active' => true,
        ]);

        $package = Package::create([
            'package_name' => 'Test Umrah Package',
            'ticket_fare_id' => $ticketFare->id,
            'visa_selling_price_id' => $visaPrice->id,
            'regular_price' => 35000.00,
            'service_charge' => 1500.00,
            'is_active' => true,
            'is_double_ticket' => false,
        ]);

        $fingerprintCharge = FingerprintCharge::create([
            'district_id' => $district->id,
            'user_id' => $user->id,
            'fingerprint_charge' => 50.00,
        ]);

        Bank::create([
            'name' => 'Test Bank',
            'description' => 'Test Bank Description',
            'currency' => 'SAR',
            'location' => 'KSA',
        ]);

        TransactionType::create(['name' => 'Initial Payment', 'type' => 'debit']);
        TransactionType::create(['name' => 'Due Collection', 'type' => 'debit']);
        TransactionType::create(['name' => 'Customer Refund', 'type' => 'credit']);
        TransactionType::create(['name' => 'Service Charge Deduction', 'type' => 'credit']);
        TransactionType::create(['name' => 'Ticket Refund - Payment', 'type' => 'credit']);
        TransactionType::create(['name' => 'Ticket Refund - Re-issue', 'type' => 'credit']);
        TransactionType::create(['name' => 'Visa Agent Payment', 'type' => 'credit']);

        $passengerStatusId = PassengerStatus::firstOrCreate(['name' => 'Processing'])->id;

        $visaAgent = VisaAgent::create(['name' => 'Test Visa Agent', 'address' => 'Test', 'contacts' => '0123456789']);
        VisaAgentCost::create(['visa_agent_id' => $visaAgent->id, 'user_id' => $user->id, 'visa_agent_cost' => 500.00]);

        $ticketAgent = TicketAgent::create(['name' => 'Test Ticket Agent', 'address' => 'Agent Address', 'contacts' => '0123456789']);

        return compact('district', 'flightDateGap', 'currencyRate', 'visaPrice', 'ticketFare', 'package', 'fingerprintCharge', 'visaAgent', 'ticketAgent', 'passengerStatusId');
    }

    private function createBookingWithPassengers(User $user, array $deps, int $index, int $passengerCount = 2): Booking
    {
        $branch = Branch::create([
            'name' => 'Branch '.$index,
            'address' => 'Address',
            'contacts' => '0123456789',
            'location' => 'KSA',
            'fingerprint_operation' => true,
            'branch_code' => 'BK'.str_pad((string) $index, 4, '0', STR_PAD_LEFT),
        ]);

        $customer = Customer::create([
            'name' => 'Customer '.$index,
            'passport_no' => 'CPASS'.str_pad((string) $index, 4, '0', STR_PAD_LEFT),
            'iqama_type' => 'none',
            'iqama_no' => 'CIQAMA'.str_pad((string) $index, 4, '0', STR_PAD_LEFT),
            'mobile_no' => '0501234567',
            'address' => 'Test Address',
        ]);

        $booking = Booking::create([
            'user_id' => $user->id,
            'customer_id' => $customer->id,
            'fingerprint_branch_id' => $branch->id,
            'district_id' => $deps['district']->id,
            'package_id' => $deps['package']->id,
            'fingerprint_charge_id' => $deps['fingerprintCharge']->id,
            'booking_branch_id' => $branch->id,
            'invoice_id' => 'RINV-'.str_pad((string) $index, 4, '0', STR_PAD_LEFT),
            'date_gap_id' => $deps['flightDateGap']->id,
            'fingerprint_location' => 'office',
            'pax_qty' => $passengerCount,
            'discount_type' => 'fixed_amount',
            'discount_value' => 0,
            'discount_amount' => 0,
            'total_value' => 50000.00,
            'remarks' => '',
            'currency_rate_id' => $deps['currencyRate']->id,
            'is_cancelled' => false,
        ]);

        Invoice::create([
            'booking_id' => $booking->id,
            'branch_id' => $branch->id,
            'user_id' => $user->id,
            'total_amount' => 50000.00,
            'paid_amount' => 25000.00,
            'balance' => 25000.00,
            'status' => 'partial',
        ]);

        Fingerprint::create([
            'booking_id' => $booking->id,
            'deadline' => now()->addDays(7),
            'cost' => 100.00,
            'assigned_staff_id' => null,
        ]);

        for ($i = 0; $i < $passengerCount; $i++) {
            $passenger = Passenger::create([
                'booking_id' => $booking->id,
                'passenger_status_id' => $deps['passengerStatusId'],
                'first_name' => 'Passenger'.$index.$i,
                'last_name' => 'Last',
                'passport_no' => 'RPSP'.str_pad((string) $index, 4, '0', STR_PAD_LEFT).$i,
                'mobile_no' => '0501234567',
                'date_of_birth' => '1990-01-01',
                'passenger_type' => 'adult',
                'passport_expiry' => '2030-12-31',
                'stay_duration' => 14,
                'service_required' => 'all',
                'flight_date_from' => now()->addDays(5)->toDateString(),
                'flight_date_to' => now()->addDays(15)->toDateString(),
                'ticket_status' => 'pending',
                'visa_status' => 'pending',
                'address' => 'Test Address',
                'ticket_fare_id' => $deps['ticketFare']->id,
                'package_value' => 25000.00,
            ]);

            VisaSubmission::create([
                'passenger_id' => $passenger->id,
                'visa_agent_id' => $deps['visaAgent']->id,
                'visa_selling_price_id' => $deps['visaPrice']->id,
                'commission_agent_id' => null,
                'agent_commission' => null,
                'net_visa_cost' => 1000.00,
                'additional_cost' => 0,
                'final_cost' => 1000.00,
                'visa_number' => null,
                'is_cancelled' => false,
                'status' => 'issued',
            ]);

            IssuedTicket::create([
                'passenger_id' => $passenger->id,
                'booking_id' => $booking->id,
                'user_id' => $user->id,
                'ticket_fare_id' => $deps['ticketFare']->id,
                'ticket_agent_id' => $deps['ticketAgent']->id,
                'selling_fare' => 28000.00,
                'net_fare' => 28000.00,
                'issue_type' => 'regular',
                'status' => 'issued',
                'issued_date' => now(),
                'inbound_date' => now()->addDays(5),
                'outbound_date' => now()->addDays(15),
            ]);
        }

        return $booking;
    }

    /** @test */
    public function test_visa_agent_report_data_stays_bounded_query_count(): void
    {
        $user = $this->setupUser();
        $deps = $this->seedAllPrerequisites($user);

        // Create 10 visa submissions in one go, each with unique data
        $visaAgent = $deps['visaAgent'];
        for ($i = 0; $i < 10; $i++) {
            $this->createBookingWithPassengers($user, $deps, $i, 1);
        }

        Auth::login($user);

        DB::enableQueryLog();
        $response = $this->get(route('api.reports.visa-agent'));
        DB::disableQueryLog();

        $queryCount = count(DB::getQueryLog());

        $response->assertOk();
        $this->assertLessThan(40, $queryCount,
            'Visa agent report should execute fewer than 40 queries regardless of agent count. Actual: '.$queryCount);
    }

    /** @test */
    public function test_visa_agent_combined_report_data_stays_bounded_query_count(): void
    {
        $user = $this->setupUser();
        $deps = $this->seedAllPrerequisites($user);

        for ($i = 0; $i < 10; $i++) {
            $this->createBookingWithPassengers($user, $deps, $i, 1);
        }

        Auth::login($user);

        DB::enableQueryLog();
        $response = $this->get(route('api.reports.visa-agent.combined', $deps['visaAgent']));
        DB::disableQueryLog();

        $queryCount = count(DB::getQueryLog());

        $response->assertOk();
        $this->assertLessThan(35, $queryCount,
            'Visa agent combined report should execute fewer than 35 queries. Actual: '.$queryCount);
    }

    /** @test */
    public function test_visa_agent_combined_report_returns_correct_totals(): void
    {
        $user = $this->setupUser();
        $deps = $this->seedAllPrerequisites($user);

        $this->createBookingWithPassengers($user, $deps, 0, 1);

        Auth::login($user);
        $response = $this->get(route('api.reports.visa-agent.combined', $deps['visaAgent']));

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(1, $data, 'Should return 1 transaction row');
    }

    /** @test */
    public function test_profit_loss_report_stays_bounded_query_count(): void
    {
        $user = $this->setupUser();
        $deps = $this->seedAllPrerequisites($user);

        // Create 5 bookings, each with 2 passengers (10 passengers total)
        for ($i = 0; $i < 5; $i++) {
            $this->createBookingWithPassengers($user, $deps, $i, 2);
        }

        Auth::login($user);

        DB::enableQueryLog();
        $response = $this->get(route('api.reports.profit-loss', [
            'date_from' => now()->subDays(60)->toDateString(),
            'date_to' => now()->addDays(1)->toDateString(),
        ]));
        DB::disableQueryLog();

        $queryCount = count(DB::getQueryLog());

        $response->assertOk();
        $this->assertLessThan(50, $queryCount,
            'Profit loss report should execute fewer than 50 queries for 5 bookings. Actual: '.$queryCount);
    }

    /** @test */
    public function test_profit_loss_report_returns_correct_profit(): void
    {
        $user = $this->setupUser();
        $deps = $this->seedAllPrerequisites($user);

        $this->createBookingWithPassengers($user, $deps, 0, 2);

        Auth::login($user);
        $response = $this->get(route('api.reports.profit-loss', [
            'date_from' => now()->subDays(60)->toDateString(),
            'date_to' => now()->addDays(1)->toDateString(),
        ]));

        $response->assertOk();
        $data = $response->json();
        $this->assertArrayHasKey('customers', $data);
        $this->assertArrayHasKey('passengers', $data);
        $this->assertCount(1, $data['customers'], 'Should have 1 customer row');
        $this->assertCount(2, $data['passengers'], 'Should have 2 passenger rows');
    }

    /** @test */
    public function test_ticket_agent_report_stays_bounded_query_count(): void
    {
        $user = $this->setupUser();
        $deps = $this->seedAllPrerequisites($user);

        // Create 5 bookings (each linked to the same ticket agent, but that's fine for N+1 testing)
        for ($i = 0; $i < 5; $i++) {
            $this->createBookingWithPassengers($user, $deps, $i, 2);
        }

        Auth::login($user);

        DB::enableQueryLog();
        $response = $this->get(route('api.reports.ticket-agent', [
            'date_from' => now()->subDays(60)->toDateString(),
            'date_to' => now()->addDays(1)->toDateString(),
        ]));
        DB::disableQueryLog();

        $queryCount = count(DB::getQueryLog());

        $response->assertOk();
        $this->assertLessThan(40, $queryCount,
            'Ticket agent report should execute fewer than 40 queries for 5 agents. Actual: '.$queryCount);
    }

    /** @test */
    public function test_ticket_agent_report_returns_correct_data(): void
    {
        $user = $this->setupUser();
        $deps = $this->seedAllPrerequisites($user);

        $this->createBookingWithPassengers($user, $deps, 0, 1);

        Auth::login($user);
        $response = $this->get(route('api.reports.ticket-agent', [
            'date_from' => now()->subDays(60)->toDateString(),
            'date_to' => now()->addDays(1)->toDateString(),
        ]));

        $response->assertOk();
        $data = $response->json();
        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('summary', $data);
        $this->assertGreaterThanOrEqual(1, count($data['data']));
    }

    /** @test */
    public function test_branch_wise_report_stays_bounded_query_count(): void
    {
        $user = $this->setupUser();
        $deps = $this->seedAllPrerequisites($user);

        // Create 5 bookings, each with 2 passengers
        for ($i = 0; $i < 5; $i++) {
            $this->createBookingWithPassengers($user, $deps, $i, 2);
        }

        Auth::login($user);

        DB::enableQueryLog();
        $response = $this->get(route('report.branch-wise', [
            'date_from' => now()->subDays(60)->toDateString(),
            'date_to' => now()->addDays(1)->toDateString(),
        ]));
        DB::disableQueryLog();

        $queryCount = count(DB::getQueryLog());

        $response->assertOk();
        $this->assertLessThan(80, $queryCount,
            'Branch-wise report should execute fewer than 80 queries for 5 bookings. Actual: '.$queryCount);
    }
}
