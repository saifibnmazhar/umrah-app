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
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class SettingsPackageUsedTicketMarkerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private TicketFare $usedFare;

    private TicketFare $freeFare;

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

        $visa = VisaSellingPrice::create(['user_id' => $this->admin->id, 'selling_price' => 2000.00]);

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

        $this->usedFare = $this->makeFare($airline, $airlineClass, $route, 28000.00);
        $this->freeFare = $this->makeFare($airline, $airlineClass, $route, 30000.00);

        Package::create([
            'package_name' => 'Pkg '.uniqid(),
            'ticket_fare_id' => $this->usedFare->id,
            'visa_selling_price_id' => $visa->id,
            'regular_price' => 30000.00,
            'service_charge' => 1500.00,
            'is_active' => true,
            'is_double_ticket' => false,
        ]);
    }

    private function makeFare(Airline $airline, AirlineClass $airlineClass, Route $route, float $selling): TicketFare
    {
        return TicketFare::create([
            'airline_id' => $airline->id,
            'airline_classes_id' => $airlineClass->id,
            'route_id' => $route->id,
            'ticket_type' => 'regular',
            'effective_from' => now()->subDays(30)->format('Y-m-d'),
            'effective_to' => now()->addDays(30)->format('Y-m-d'),
            'net_fare' => $selling - 3000,
            'selling_fare' => $selling,
            'child_fare_percentage' => 75.00,
            'infant_fare_percentage' => 10.00,
            'with_meal' => true,
            'user_id' => $this->admin->id,
            'is_active' => true,
        ]);
    }

    private function optionFor(TestResponse $response, TicketFare $fare): string
    {
        $html = $response->getContent();
        $matched = preg_match('/<option value="'.$fare->id.'"[\s\S]*?<\/option>/', $html, $m);
        $this->assertSame(1, $matched, 'Option for fare #'.$fare->id.' not rendered.');

        return $m[0];
    }

    public function test_used_fare_option_is_rendered_with_used_marker(): void
    {
        $response = $this->actingAs($this->admin)->get(route('settings'));
        $response->assertOk();

        $usedOption = $this->optionFor($response, $this->usedFare);
        $this->assertStringContainsString('data-used="true"', $usedOption);
        $this->assertStringContainsString(' (USED)', $usedOption);

        $freeOption = $this->optionFor($response, $this->freeFare);
        $this->assertStringContainsString('data-used="false"', $freeOption);
        $this->assertStringNotContainsString(' (USED)', $freeOption);
    }

    public function test_package_modal_js_no_longer_hides_used_fares(): void
    {
        $response = $this->actingAs($this->admin)->get(route('settings'));
        $response->assertOk();

        $response->assertDontSee('option.dataset.used', false);
        $response->assertDontSee('isExcepted', false);
    }
}
