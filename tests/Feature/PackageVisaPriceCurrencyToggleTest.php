<?php

namespace Tests\Feature;

use App\Models\Airline;
use App\Models\AirlineClass;
use App\Models\Branch;
use App\Models\CityCode;
use App\Models\Package;
use App\Models\Role;
use App\Models\Route;
use App\Models\TicketFare;
use App\Models\TravelClass;
use App\Models\User;
use App\Models\VisaSellingPrice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PackageVisaPriceCurrencyToggleTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private VisaSellingPrice $visa;

    private Package $package;

    protected function setUp(): void
    {
        parent::setUp();

        $branch = Branch::create([
            'name' => 'PB', 'address' => 'A', 'contacts' => '01',
            'location' => 'KSA', 'fingerprint_operation' => true, 'branch_code' => 'PB01',
        ]);

        $this->admin = User::create([
            'name' => 'Super Admin', 'email' => uniqid().'@example.com',
            'password' => bcrypt('password'), 'is_active' => true, 'branch_id' => $branch->id,
        ]);
        $this->admin->roles()->attach(Role::firstOrCreate(['name' => 'Super Admin']));

        $this->visa = VisaSellingPrice::create(['user_id' => $this->admin->id, 'selling_price' => 2000.00]);

        $c1 = CityCode::create(['city_name' => 'Dhaka', 'code' => 'DAC', 'country' => 'BD']);
        $c2 = CityCode::create(['city_name' => 'Riyadh', 'code' => 'RUH', 'country' => 'SA']);
        $airline = Airline::create(['name' => 'SV', 'code' => 'SV']);
        $travelClass = TravelClass::create(['name' => 'Economy']);
        $airlineClass = AirlineClass::create(['airline_id' => $airline->id, 'class_id' => $travelClass->id]);
        $route = Route::create([
            'airline_id' => $airline->id, 'route_type' => 'round', 'flight_type' => 'direct',
            'from_city_id' => $c1->id, 'to_city_id' => $c2->id,
            'return_city_id' => $c1->id, 'additional_gap' => null,
        ]);

        $fare = TicketFare::create([
            'airline_id' => $airline->id,
            'airline_classes_id' => $airlineClass->id,
            'route_id' => $route->id,
            'ticket_type' => 'regular',
            'effective_from' => now()->subDays(30)->format('Y-m-d'),
            'effective_to' => now()->addDays(30)->format('Y-m-d'),
            'net_fare' => 25000.00,
            'selling_fare' => 28000.00,
            'child_fare_percentage' => 75.00,
            'infant_fare_percentage' => 10.00,
            'with_meal' => true,
            'user_id' => $this->admin->id,
            'is_active' => true,
        ]);

        $this->package = Package::create([
            'package_name' => 'Pkg '.uniqid(),
            'ticket_fare_id' => $fare->id,
            'visa_selling_price_id' => $this->visa->id,
            'regular_price' => 30000.00,
            'service_charge' => 1500.00,
            'is_active' => true,
            'is_double_ticket' => false,
        ]);
    }

    private function assertLatestVisaRowIsPrefixed(string $html): void
    {
        $this->assertMatchesRegularExpression(
            '/Visa Selling Price \(Latest\):.*?x-show="\$store\.currency\.mode === .BDT."/s',
            $html
        );
        $this->assertMatchesRegularExpression(
            '/Visa Selling Price \(Latest\):.*?data-sar=/s',
            $html
        );
    }

    public function test_disabled_package_pages_return_404(): void
    {
        foreach (['/packages', '/packages/create', '/packages/'.$this->package->id.'/edit'] as $uri) {
            $this->actingAs($this->admin)->get($uri)->assertNotFound();
        }
    }

    public function test_settings_package_modal_current_visa_uses_currency_store(): void
    {
        $response = $this->actingAs($this->admin)->get(route('settings'));

        $response->assertOk();
        $html = $response->getContent();

        $this->assertStringContainsString('renderModalCurrentVisaPrice', $html);
        $this->assertStringNotContainsString("'SAR ' + currentVisa.toFixed(2)", $html);
        $this->assertLatestVisaRowIsPrefixed($html);
    }
}
