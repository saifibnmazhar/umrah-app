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

class FareAdminFilterPersistenceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Airline $airline;

    private AirlineClass $airlineClass;

    private Route $route;

    private TicketFare $fare;

    protected function setUp(): void
    {
        parent::setUp();

        $branch = Branch::create([
            'name' => 'TB', 'address' => 'A', 'contacts' => '01',
            'location' => 'KSA', 'fingerprint_operation' => true, 'branch_code' => 'TB01',
        ]);
        $this->user = User::create([
            'name' => 'Ticket Admin', 'email' => uniqid().'@example.com',
            'password' => bcrypt('password'), 'is_active' => true, 'branch_id' => $branch->id,
        ]);
        $this->user->roles()->attach(Role::create(['name' => 'Ticket Admin']));

        $c1 = CityCode::create(['city_name' => 'Dhaka', 'code' => 'DAC', 'country' => 'BD']);
        $c2 = CityCode::create(['city_name' => 'Riyadh', 'code' => 'RUH', 'country' => 'SA']);
        $this->airline = Airline::create(['name' => 'Biman', 'code' => 'BG']);
        $tc = TravelClass::create(['name' => 'Economy']);
        $this->airlineClass = AirlineClass::create(['airline_id' => $this->airline->id, 'class_id' => $tc->id]);
        $this->route = Route::create([
            'airline_id' => $this->airline->id, 'route_type' => 'round', 'flight_type' => 'direct',
            'from_city_id' => $c1->id, 'to_city_id' => $c2->id,
            'return_city_id' => $c1->id, 'additional_gap' => null,
        ]);

        $this->fare = TicketFare::create([
            'airline_id' => $this->airline->id,
            'airline_classes_id' => $this->airlineClass->id,
            'route_id' => $this->route->id,
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

    public function test_list_view_edit_links_carry(): void
    {
        $r = $this->actingAs($this->user)->get(route('fare.admin', [
            'tab' => 'fares', 'search' => 'Biman', 'airline_id' => $this->airline->id,
            'ticket_type' => 'regular', 'status' => 'active', 'page' => 1,
        ]));
        $r->assertOk();
        $html = html_entity_decode($r->getContent());
        $this->assertStringContainsString('airline_id='.$this->airline->id, $html);
        $this->assertStringContainsString('ticket_type=regular', $html);
        $this->assertStringContainsString('status=active', $html);
    }

    public function test_show_back_link_carries(): void
    {
        $q = http_build_query(['search' => 'Biman', 'airline_id' => $this->airline->id, 'ticket_type' => 'regular', 'status' => 'active', 'page' => 1]);
        $r = $this->actingAs($this->user)->get(route('ticket-fares.show', $this->fare->id).'?'.$q);
        $r->assertOk();
        $exp = route('fare.admin', ['tab' => 'fares', 'search' => 'Biman', 'airline_id' => $this->airline->id, 'ticket_type' => 'regular', 'status' => 'active', 'page' => 1]);
        $this->assertStringContainsString(str_replace('&', '&amp;', $exp), $r->getContent());
    }

    public function test_edit_back_link_carries(): void
    {
        $q = http_build_query(['search' => 'Biman', 'airline_id' => $this->airline->id, 'ticket_type' => 'regular', 'status' => 'active', 'page' => 1]);
        $r = $this->actingAs($this->user)->get(route('ticket-fares.edit', $this->fare->id).'?'.$q);
        $r->assertOk();
        $exp = route('fare.admin', ['tab' => 'fares', 'search' => 'Biman', 'airline_id' => $this->airline->id, 'ticket_type' => 'regular', 'status' => 'active', 'page' => 1]);
        $this->assertStringContainsString(str_replace('&', '&amp;', $exp), $r->getContent());
    }

    public function test_update_redirect_carries(): void
    {
        $r = $this->actingAs($this->user)->put(route('ticket-fares.update', $this->fare->id), [
            'airline_id' => $this->airline->id, 'airline_classes_id' => $this->airlineClass->id,
            'route_id' => $this->route->id, 'route_type' => 'round', 'ticket_type' => 'regular',
            'effective_from' => now()->subDays(5)->format('Y-m-d'), 'effective_to' => now()->addDays(25)->format('Y-m-d'),
            'selling_fare' => 29000, 'child_fare_percentage' => 75, 'infant_fare_percentage' => 10,
            'search' => 'Biman', 'airline_id' => (string) $this->airline->id, 'ticket_type' => 'regular', 'status' => 'active', 'page' => '1',
        ]);
        $r->assertRedirect(route('fare.admin', ['tab' => 'fares', 'search' => 'Biman', 'airline_id' => (string) $this->airline->id, 'ticket_type' => 'regular', 'status' => 'active', 'page' => '1']));
    }
}
