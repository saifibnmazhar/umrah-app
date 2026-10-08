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

class SettingsPackageDoubleTicketUsedMarkerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private TicketFare $usedInbound;

    private TicketFare $freeInbound;

    private TicketFare $usedOutbound;

    private TicketFare $freeOutbound;

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

        $inboundRoute = Route::create([
            'airline_id' => $airline->id, 'route_type' => 'oneway_inbound', 'flight_type' => 'direct',
            'from_city_id' => $c1->id, 'to_city_id' => $c2->id,
            'return_city_id' => null, 'additional_gap' => null,
        ]);

        $outboundRoute = Route::create([
            'airline_id' => $airline->id, 'route_type' => 'oneway_outbound', 'flight_type' => 'direct',
            'from_city_id' => $c2->id, 'to_city_id' => $c1->id,
            'return_city_id' => null, 'additional_gap' => null,
        ]);

        $this->usedInbound = $this->makeFare($airline, $airlineClass, $inboundRoute, 15000.00);
        $this->freeInbound = $this->makeFare($airline, $airlineClass, $inboundRoute, 16000.00);
        $this->usedOutbound = $this->makeFare($airline, $airlineClass, $outboundRoute, 17000.00);
        $this->freeOutbound = $this->makeFare($airline, $airlineClass, $outboundRoute, 18000.00);

        Package::create([
            'package_name' => 'Double Pkg '.uniqid(),
            'ticket_fare_id' => null,
            'ticket_fare_inbound_id' => $this->usedInbound->id,
            'ticket_fare_outbound_id' => $this->usedOutbound->id,
            'visa_selling_price_id' => $visa->id,
            'regular_price' => 34000.00,
            'service_charge' => 1500.00,
            'is_active' => true,
            'is_double_ticket' => true,
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

    private function optionForInSelect(string $html, string $selectId, TicketFare $fare): string
    {
        $selectMatched = preg_match(
            '/<select[^>]*id="'.$selectId.'"[^>]*>([\s\S]*?)<\/select>/',
            $html,
            $selectMatch
        );
        $this->assertSame(1, $selectMatched, 'Select #'.$selectId.' not rendered.');

        $optionMatched = preg_match(
            '/<option value="'.$fare->id.'"[\s\S]*?<\/option>/',
            $selectMatch[1],
            $optionMatch
        );
        $this->assertSame(1, $optionMatched, 'Option for fare #'.$fare->id.' not rendered in #'.$selectId.'.');

        return $optionMatch[0];
    }

    public function test_inbound_options_show_used_marker_slot_specific(): void
    {
        $response = $this->actingAs($this->admin)->get(route('settings'));
        $response->assertOk();

        $usedOption = $this->optionForInSelect($response->getContent(), 'modalTicketInboundSelect', $this->usedInbound);
        $this->assertStringContainsString('data-used="true"', $usedOption);
        $this->assertStringContainsString(' (USED)', $usedOption);

        $freeOption = $this->optionForInSelect($response->getContent(), 'modalTicketInboundSelect', $this->freeInbound);
        $this->assertStringContainsString('data-used="false"', $freeOption);
        $this->assertStringNotContainsString(' (USED)', $freeOption);
    }

    public function test_outbound_options_show_used_marker_slot_specific(): void
    {
        $response = $this->actingAs($this->admin)->get(route('settings'));
        $response->assertOk();

        $usedOption = $this->optionForInSelect($response->getContent(), 'modalTicketOutboundSelect', $this->usedOutbound);
        $this->assertStringContainsString('data-used="true"', $usedOption);
        $this->assertStringContainsString(' (USED)', $usedOption);

        $freeOption = $this->optionForInSelect($response->getContent(), 'modalTicketOutboundSelect', $this->freeOutbound);
        $this->assertStringContainsString('data-used="false"', $freeOption);
        $this->assertStringNotContainsString(' (USED)', $freeOption);
    }
}
