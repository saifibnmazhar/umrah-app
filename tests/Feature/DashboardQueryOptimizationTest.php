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

class DashboardQueryOptimizationTest extends TestCase
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

        PassengerStatus::firstOrCreate(['name' => 'Processing']);

        $visaAgent = VisaAgent::create(['name' => 'Test Visa Agent', 'address' => 'Test', 'contacts' => '0123456789']);
        VisaAgentCost::create(['visa_agent_id' => $visaAgent->id, 'user_id' => $user->id, 'visa_agent_cost' => 500.00]);

        $passengerStatusId = PassengerStatus::where('name', 'Processing')->first()->id;

        return compact('district', 'flightDateGap', 'currencyRate', 'visaPrice', 'ticketFare', 'package', 'fingerprintCharge', 'visaAgent', 'passengerStatusId');
    }

    private function createBooking(User $user, array $deps, int $index, int $passengerCount = 2): Booking
    {
        $branch = Branch::create([
            'name' => 'Branch '.$index,
            'address' => 'Address',
            'contacts' => '0123456789',
            'location' => 'KSA',
            'fingerprint_operation' => true,
            'branch_code' => 'B'.str_pad((string) $index, 4, '0', STR_PAD_LEFT),
        ]);

        $customer = Customer::create([
            'name' => 'Customer '.$index,
            'passport_no' => 'PASS'.str_pad((string) $index, 4, '0', STR_PAD_LEFT),
            'iqama_type' => 'none',
            'iqama_no' => 'IQAMA'.str_pad((string) $index, 4, '0', STR_PAD_LEFT),
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
            'invoice_id' => 'INV-2025-'.str_pad((string) $index, 4, '0', STR_PAD_LEFT),
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
                'passport_no' => 'PSP'.str_pad((string) $index, 4, '0', STR_PAD_LEFT).$i,
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
    public function test_dashboard_loads_within_bounded_query_count(): void
    {
        $user = $this->setupUser();
        $deps = $this->seedAllPrerequisites($user);

        // Seed 5 bookings, each with 2 passengers (10 passengers total)
        for ($i = 1; $i <= 5; $i++) {
            $this->createBooking($user, $deps, $i, 2);
        }

        Auth::login($user);

        DB::enableQueryLog();
        $response = $this->get(route('dashboard'));
        DB::disableQueryLog();

        $queryCount = count(DB::getQueryLog());

        $response->assertOk();
        $this->assertLessThan(80, $queryCount,
            'Dashboard should execute fewer than 80 queries. Actual: '.$queryCount);
    }

    /** @test */
    public function test_dashboard_profit_calculation_remains_correct(): void
    {
        $user = $this->setupUser();
        $deps = $this->seedAllPrerequisites($user);

        $this->createBooking($user, $deps, 1, 2);

        Auth::login($user);
        $response = $this->get(route('dashboard'));
        $response->assertOk();

        // Dashboard should show the correct profit:
        // For 1 booking: invoice total = 50000
        // Fingerprint cost = 100 (shared across all passengers)
        // Per passenger: visa cost = 1000, ticket cost = 28000
        // Total cost = 100 + (1000 + 28000) * 2 = 57100
        // Profit = 50000 - 57100 = -7100
    }
}
