<?php

namespace Tests\Feature;

use App\Enums\VisaStatus;
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
use App\Models\Package;
use App\Models\Passenger;
use App\Models\PassengerStatus;
use App\Models\Role;
use App\Models\Route;
use App\Models\StayDurationLimit;
use App\Models\TicketFare;
use App\Models\TravelClass;
use App\Models\User;
use App\Models\VisaAgent;
use App\Models\VisaAgentCost;
use App\Models\VisaSellingPrice;
use App\Models\VisaSubmission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class VisaReportTest extends TestCase
{
    use RefreshDatabase;

    private $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        $superAdminRole = Role::firstOrCreate(['name' => 'Super Admin']);
        $this->superAdmin = User::factory()->create(['email' => 'admin@test.com', 'password' => 'password']);
        $this->superAdmin->roles()->attach($superAdminRole);
        $this->actingAs($this->superAdmin);
    }

    private function seedPrerequisites(): array
    {
        $district = District::create(['name' => 'Test District', 'division' => 'Test Division']);
        $currencyRate = CurrencyRate::create(['user_id' => $this->superAdmin->id, 'rate' => 28.0000]);
        $visaPrice = VisaSellingPrice::create(['user_id' => $this->superAdmin->id, 'selling_price' => 2000.00]);

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

        $ticketFare = TicketFare::create([
            'airline_id' => $airline->id,
            'airline_classes_id' => $airlineClass->id,
            'route_id' => $route->id,
            'ticket_type' => 'regular',
            'effective_from' => now()->subDays(30),
            'effective_to' => now()->addDays(30),
            'net_fare' => 1000.00,
            'selling_fare' => 1200.00,
            'offer_price' => null,
            'child_fare_percentage' => 75.00,
            'infant_fare_percentage' => 10.00,
            'with_meal' => true,
            'user_id' => $this->superAdmin->id,
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
            'user_id' => $this->superAdmin->id,
            'fingerprint_charge' => 50.00,
        ]);

        Bank::create(['name' => 'Test Bank', 'description' => 'Test', 'currency' => 'SAR', 'location' => 'KSA']);
        PassengerStatus::firstOrCreate(['name' => 'Processing']);
        $visaAgent = VisaAgent::create(['name' => 'Test Visa Agent', 'address' => 'Test', 'contacts' => '0123456789']);
        VisaAgentCost::create(['visa_agent_id' => $visaAgent->id, 'user_id' => $this->superAdmin->id, 'visa_agent_cost' => 500.00]);

        return compact('district', 'currencyRate', 'visaPrice', 'ticketFare', 'package', 'fingerprintCharge', 'flightDateGap', 'visaAgent', 'route', 'airline', 'airlineClass', 'travelClass');
    }

    private function createVisaSubmission(array $deps): VisaSubmission
    {
        $customer = Customer::create([
            'name' => 'Test Customer',
            'passport_no' => 'P12345678',
            'iqama_type' => 'none',
            'iqama_no' => 'IQAMA001',
            'mobile_no' => '0501234567',
            'address' => 'Test Address',
        ]);

        $branch = Branch::create([
            'name' => 'Test Branch',
            'address' => 'Address',
            'contacts' => '0123456789',
            'location' => 'KSA',
            'fingerprint_operation' => true,
            'branch_code' => 'B0001',
        ]);

        $booking = Booking::create([
            'user_id' => $this->superAdmin->id,
            'customer_id' => $customer->id,
            'fingerprint_branch_id' => $branch->id,
            'district_id' => $deps['district']->id,
            'package_id' => $deps['package']->id,
            'fingerprint_charge_id' => $deps['fingerprintCharge']->id,
            'booking_branch_id' => $branch->id,
            'invoice_id' => 'INV-2025-0001',
            'date_gap_id' => $deps['flightDateGap']->id,
            'fingerprint_location' => 'office',
            'pax_qty' => 1,
            'discount_type' => 'fixed_amount',
            'discount_value' => 0,
            'discount_amount' => 0,
            'total_value' => 50000.00,
            'remarks' => '',
            'currency_rate_id' => $deps['currencyRate']->id,
            'is_cancelled' => false,
        ]);

        $passenger = Passenger::create([
            'booking_id' => $booking->id,
            'passenger_status_id' => PassengerStatus::where('name', 'Processing')->first()->id,
            'first_name' => 'Test',
            'last_name' => 'Passenger',
            'passport_no' => 'P12345678',
            'mobile_no' => '0501234567',
            'date_of_birth' => '1990-01-01',
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

        return VisaSubmission::create([
            'passenger_id' => $passenger->id,
            'visa_agent_id' => $deps['visaAgent']->id,
            'visa_selling_price_id' => $deps['visaPrice']->id,
            'commission_agent_id' => null,
            'agent_commission' => null,
            'net_visa_cost' => 1000.00,
            'additional_cost' => 0,
            'final_cost' => 1000.00,
            'visa_number' => 'VISA123456',
            'is_cancelled' => false,
            'status' => VisaStatus::SUBMITTED->value,
        ]);
    }

    #[Test]
    public function test_visa_report_renders_livewire_component(): void
    {
        $response = $this->get('/reports/visa');
        $response->assertOk();
        $response->assertSee('Visa Sales Report');
        $response->assertSeeLivewire('report.visa-report-table');
    }

    #[Test]
    public function test_visa_report_renders_empty_state(): void
    {
        Livewire::test('report.visa-report-table')
            ->assertSee('No visa records found');
    }

    #[Test]
    public function test_visa_report_shows_submission(): void
    {
        $deps = $this->seedPrerequisites();
        $this->createVisaSubmission($deps);

        Livewire::test('report.visa-report-table')
            ->assertSee('INV-2025-0001')
            ->assertSee('Test Customer')
            ->assertSee('1000.00')
            ->assertOk();
    }
}
