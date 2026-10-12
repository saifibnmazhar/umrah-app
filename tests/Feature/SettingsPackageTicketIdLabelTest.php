<?php

namespace Tests\Feature;

use App\Enums\RouteType;
use App\Models\Airline;
use App\Models\AirlineClass;
use App\Models\Branch;
use App\Models\CityCode;
use App\Models\Role;
use App\Models\Route;
use App\Models\TicketFare;
use App\Models\TravelClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsPackageTicketIdLabelTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private TicketFare $fare;

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

        $c1 = CityCode::create(['city_name' => 'Dhaka', 'code' => 'DAC', 'country' => 'BD']);
        $c2 = CityCode::create(['city_name' => 'Riyadh', 'code' => 'RUH', 'country' => 'SA']);
        $airline = Airline::create(['name' => 'SV', 'code' => 'SV']);
        $travelClass = TravelClass::create(['name' => 'Economy']);
        $airlineClass = AirlineClass::create(['airline_id' => $airline->id, 'class_id' => $travelClass->id]);
        $route = Route::create([
            'airline_id' => $airline->id, 'route_type' => RouteType::ROUND->value, 'flight_type' => 'direct',
            'from_city_id' => $c1->id, 'to_city_id' => $c2->id,
            'return_city_id' => $c1->id, 'additional_gap' => null,
        ]);

        $this->fare = TicketFare::create([
            'airline_id' => $airline->id,
            'airline_classes_id' => $airlineClass->id,
            'route_id' => $route->id,
            'ticket_type' => 'regular',
            'effective_from' => now()->subDays(30)->format('Y-m-d'),
            'effective_to' => now()->addDays(30)->format('Y-m-d'),
            'net_fare' => 25000,
            'selling_fare' => 28000,
            'child_fare_percentage' => 75.00,
            'infant_fare_percentage' => 10.00,
            'with_meal' => true,
            'user_id' => $this->admin->id,
            'is_active' => true,
        ]);
    }

    public function test_package_modal_ticket_label_appends_tkt_id_in_js(): void
    {
        $response = $this->actingAs($this->admin)->get(route('settings'));
        $response->assertOk();

        // The visible dropdown label is rebuilt at runtime by updateSelectLabels();
        // it must append the fare id (opt.value) after the fare so each option
        // reads like "... | SAR 3,076.923 | TKT ID: 42".
        $response->assertSee("display += ' | TKT ID: ' + opt.value;", false);
    }

    public function test_package_modal_ticket_options_render_tkt_id_in_blade_default(): void
    {
        $response = $this->actingAs($this->admin)->get(route('settings'));
        $response->assertOk();

        // Server-rendered default (before JS runs / no-JS) must already carry
        // the fare id after the selling fare in the single-ticket select.
        $html = $response->getContent();
        $matched = preg_match('/<option value=\"'.$this->fare->id.'\"[\s\S]*?<\/option>/', $html, $m);
        $this->assertSame(1, $matched, 'Option for fare #'.$this->fare->id.' not rendered.');
        $this->assertStringContainsString('| TKT ID: '.$this->fare->id, $m[0]);
    }
}
