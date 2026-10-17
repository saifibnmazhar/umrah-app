<?php

namespace Tests\Feature;

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

class TicketFareEditOnchangeAttributeTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private TicketFare $fare;

    protected function setUp(): void
    {
        parent::setUp();

        $branch = Branch::create([
            'name' => 'TB', 'address' => 'A', 'contacts' => '01',
            'location' => 'KSA', 'fingerprint_operation' => true, 'branch_code' => 'TB01',
        ]);
        $this->user = User::create([
            'name' => 'Admin', 'email' => 'admin@example.com',
            'password' => bcrypt('password'), 'is_active' => true, 'branch_id' => $branch->id,
        ]);
        $this->user->roles()->attach(Role::create(['name' => 'Super Admin']));

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

        // No package / passenger binding => fare is unlocked => onchange handlers must be wired.
        $this->fare = TicketFare::create([
            'airline_id' => $airline->id,
            'airline_classes_id' => $airlineClass->id,
            'route_id' => $route->id,
            'ticket_type' => 'regular',
            'effective_from' => now()->subDays(30)->format('Y-m-d'),
            'effective_to' => now()->addDays(30)->format('Y-m-d'),
            'net_fare' => 25000.00,
            'selling_fare' => 28000.00,
            'offer_price' => null,
            'child_fare_percentage' => 75.00,
            'infant_fare_percentage' => 10.00,
            'with_meal' => true,
            'user_id' => $this->user->id,
            'is_active' => true,
        ]);
    }

    public function test_unlocked_fare_edit_emits_raw_onchange_attributes(): void
    {
        $this->assertFalse($this->fare->isLocked());

        $response = $this->actingAs($this->user)
            ->get(route('ticket-fares.edit', $this->fare->id));
        $response->assertOk();
        $html = $response->getContent();

        foreach ([
            'handleRouteTypeChange()',
            'handleFlightTypeChange()',
            'handleAirlineChange()',
            'handleRouteChange()',
            'toggleFields()',
        ] as $handler) {
            $this->assertStringContainsString(
                'onchange="'.$handler.'"',
                $html,
                "Expected raw onchange=\"{$handler}\" on an unlocked fare edit page."
            );
        }

        $this->assertStringNotContainsString(
            'onchange=&quot;',
            $html,
            'Blade-escaped onchange attribute detected; the handler would never execute.'
        );
    }
}
