<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
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
use App\Models\Invoice;
use App\Models\Package;
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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DueReportTest extends TestCase
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

        $currencyRate = CurrencyRate::create([
            'user_id' => $this->superAdmin->id,
            'rate' => 28.0000,
        ]);

        $visaPrice = VisaSellingPrice::create([
            'user_id' => $this->superAdmin->id,
            'selling_price' => 2000.00,
        ]);

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
            'net_fare' => 25000.00,
            'selling_fare' => 28000.00,
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

        Bank::create([
            'name' => 'Test Bank',
            'description' => 'Test Bank Description',
            'currency' => 'SAR',
            'location' => 'KSA',
        ]);

        PassengerStatus::firstOrCreate(['name' => 'Processing']);

        $visaAgent = VisaAgent::create(['name' => 'Test Visa Agent', 'address' => 'Test', 'contacts' => '0123456789']);
        VisaAgentCost::create(['visa_agent_id' => $visaAgent->id, 'user_id' => $this->superAdmin->id, 'visa_agent_cost' => 500.00]);

        return compact('district', 'currencyRate', 'visaPrice', 'ticketFare', 'package', 'fingerprintCharge', 'flightDateGap');
    }

    private function createBranchWithDue(string $invoiceId): Branch
    {
        $branch = Branch::create([
            'name' => 'Test Branch',
            'address' => 'Address',
            'contacts' => '0123456789',
            'location' => 'KSA',
            'fingerprint_operation' => true,
            'branch_code' => 'B0001',
        ]);

        $customer = Customer::create([
            'name' => 'Test Customer',
            'passport_no' => 'P12345678',
            'iqama_type' => 'none',
            'iqama_no' => 'IQAMA001',
            'mobile_no' => '0501234567',
            'address' => 'Test Address',
        ]);

        $deps = $this->seedPrerequisites();

        $booking = Booking::create([
            'user_id' => $this->superAdmin->id,
            'customer_id' => $customer->id,
            'fingerprint_branch_id' => $branch->id,
            'district_id' => $deps['district']->id,
            'package_id' => $deps['package']->id,
            'fingerprint_charge_id' => $deps['fingerprintCharge']->id,
            'booking_branch_id' => $branch->id,
            'invoice_id' => $invoiceId,
            'date_gap_id' => $deps['flightDateGap']->id,
            'fingerprint_location' => 'office',
            'pax_qty' => 2,
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
            'user_id' => $this->superAdmin->id,
            'total_amount' => 50000.00,
            'paid_amount' => 20000.00,
            'balance' => 30000.00,
            'status' => InvoiceStatus::PARTIAL->value,
        ]);

        return $branch;
    }

    #[Test]
    public function test_due_report_renders_livewire_component(): void
    {
        $this->createBranchWithDue('INV-2025-0001');

        $response = $this->get('/reports/due');
        $response->assertOk();
        $response->assertSee('Due Report');
        $response->assertSeeLivewire('report.due-report-table');
    }

    #[Test]
    public function test_due_report_renders_empty_state(): void
    {
        Livewire::test('report.due-report-table')
            ->assertSee('No due data found');
    }

    #[Test]
    public function test_due_report_shows_branch_with_due_amount(): void
    {
        $this->createBranchWithDue('INV-2025-0001');

        Livewire::test('report.due-report-table')
            ->assertSee('Test Branch')
            ->assertSee('30,000.00')
            ->assertOk();
    }
}
