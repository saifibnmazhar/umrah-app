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

class TicketFareRoleAccessTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    private TicketFare $fare;

    private Airline $airline;

    private AirlineClass $airlineClass;

    private Route $route;

    private VisaSellingPrice $visa;

    private float $visaPrice = 2000.00;

    protected function setUp(): void
    {
        parent::setUp();

        $branch = Branch::create([
            'name' => 'TB', 'address' => 'A', 'contacts' => '01',
            'location' => 'KSA', 'fingerprint_operation' => true, 'branch_code' => 'TB01',
        ]);
        $this->superAdmin = $this->makeUser('Super Admin', $branch);

        $c1 = CityCode::create(['city_name' => 'Dhaka', 'code' => 'DAC', 'country' => 'BD']);
        $c2 = CityCode::create(['city_name' => 'Riyadh', 'code' => 'RUH', 'country' => 'SA']);
        $this->airline = Airline::create(['name' => 'SV', 'code' => 'SV']);
        $travelClass = TravelClass::create(['name' => 'Economy']);
        $this->airlineClass = AirlineClass::create(['airline_id' => $this->airline->id, 'class_id' => $travelClass->id]);
        $this->route = Route::create([
            'airline_id' => $this->airline->id, 'route_type' => 'round', 'flight_type' => 'direct',
            'from_city_id' => $c1->id, 'to_city_id' => $c2->id,
            'return_city_id' => $c1->id, 'additional_gap' => null,
        ]);
        $this->visa = VisaSellingPrice::create(['user_id' => $this->superAdmin->id, 'selling_price' => $this->visaPrice]);

        $this->fare = TicketFare::create([
            'airline_id' => $this->airline->id,
            'airline_classes_id' => $this->airlineClass->id,
            'route_id' => $this->route->id,
            'ticket_type' => 'regular',
            'effective_from' => now()->subDays(30)->format('Y-m-d'),
            'effective_to' => now()->addDays(30)->format('Y-m-d'),
            'net_fare' => 25000.00,
            'selling_fare' => 28000.00,
            'child_fare_percentage' => 75.00,
            'infant_fare_percentage' => 10.00,
            'with_meal' => true,
            'user_id' => $this->superAdmin->id,
            'is_active' => true,
        ]);
    }

    private function makeUser(string $role, Branch $branch): User
    {
        $user = User::create([
            'name' => $role.' '.uniqid(), 'email' => uniqid().'@example.com',
            'password' => bcrypt('password'), 'is_active' => true, 'branch_id' => $branch->id,
        ]);
        $user->roles()->attach(Role::firstOrCreate(['name' => $role]));

        return $user;
    }

    private function makeSinglePackage(): Package
    {
        return Package::create([
            'package_name' => 'Single Pkg '.uniqid(),
            'ticket_fare_id' => $this->fare->id,
            'visa_selling_price_id' => $this->visa->id,
            'regular_price' => 30000.00,
            'service_charge' => 1500.00,
            'is_active' => true,
            'is_double_ticket' => false,
        ]);
    }

    public function test_co_admin_blocked_from_fare_edit_update_destroy(): void
    {
        $coAdmin = $this->makeUser('Co Admin', Branch::first());

        $this->actingAs($coAdmin)->get(route('ticket-fares.edit', $this->fare))->assertForbidden();
        $this->actingAs($coAdmin)->put(route('ticket-fares.update', $this->fare), [])->assertForbidden();
        $this->actingAs($coAdmin)->delete(route('ticket-fares.destroy', $this->fare))->assertForbidden();
        $this->actingAs($coAdmin)->delete(route('fare.admin.fare.destroy', $this->fare))->assertForbidden();

        $this->actingAs($coAdmin)->get(route('ticket-fares.index'))->assertOk();
        $this->actingAs($coAdmin)->get(route('ticket-fares.show', $this->fare))->assertOk();
        $this->actingAs($coAdmin)->get(route('ticket-fares.create'))->assertOk();
    }

    public function test_ticket_staff_blocked_from_fare_edit_update_destroy_and_toggle(): void
    {
        $staff = $this->makeUser('Ticket Staff', Branch::first());

        $this->actingAs($staff)->get(route('ticket-fares.edit', $this->fare))->assertForbidden();
        $this->actingAs($staff)->put(route('ticket-fares.update', $this->fare), [])->assertForbidden();
        $this->actingAs($staff)->delete(route('ticket-fares.destroy', $this->fare))->assertForbidden();
        $this->actingAs($staff)->delete(route('fare.admin.fare.destroy', $this->fare))->assertForbidden();
        $this->actingAs($staff)->patch(route('ticket-fares.toggle-active', $this->fare))->assertForbidden();

        $this->actingAs($staff)->get(route('ticket-fares.index'))->assertOk();
    }

    public function test_non_ticket_role_blocked_everywhere_including_quick_create(): void
    {
        $delivery = $this->makeUser('Delivery Staff', Branch::first());

        $this->actingAs($delivery)->get(route('ticket-fares.index'))->assertForbidden();
        $this->actingAs($delivery)->get(route('ticket-fares.show', $this->fare))->assertForbidden();
        $this->actingAs($delivery)->get(route('ticket-fares.edit', $this->fare))->assertForbidden();
        $this->actingAs($delivery)->put(route('ticket-fares.update', $this->fare), [])->assertForbidden();
        $this->actingAs($delivery)->delete(route('ticket-fares.destroy', $this->fare))->assertForbidden();
        $this->actingAs($delivery)->get(route('fare.admin'))->assertForbidden();
        $this->actingAs($delivery)->post('/api/ticket-fares/quick-create', [])->assertForbidden();
    }

    public function test_quick_create_allowed_for_ticket_roles(): void
    {
        $staff = $this->makeUser('Ticket Staff', Branch::first());

        $payload = [
            'route_type' => 'round',
            'flight_type' => 'direct',
            'airline_id' => $this->airline->id,
            'airline_classes_id' => $this->airlineClass->id,
            'route_id' => $this->route->id,
            'ticket_type' => 'regular',
            'selling_fare' => 31000.00,
        ];

        $this->actingAs($staff)
            ->postJson('/api/ticket-fares/quick-create', $payload)
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('ticket_fares', ['selling_fare' => 31000.00, 'user_id' => $staff->id]);
    }

    public function test_super_admin_index_shows_live_edit_and_disabled_delete_for_in_use_fare(): void
    {
        $this->makeSinglePackage();
        $this->assertTrue($this->fare->isLocked());

        $response = $this->actingAs($this->superAdmin)->get(route('ticket-fares.index'));

        $response->assertOk();
        $response->assertSee(route('ticket-fares.edit', $this->fare->id), false);
        $response->assertSee('In use by packages or passengers');
        $response->assertDontSee(
            'action="'.route('ticket-fares.destroy', $this->fare->id).'"',
            false
        );
    }

    public function test_co_admin_index_has_no_edit_or_delete_links(): void
    {
        $coAdmin = $this->makeUser('Co Admin', Branch::first());

        $response = $this->actingAs($coAdmin)->get(route('ticket-fares.index'));

        $response->assertOk();
        $response->assertDontSee(route('ticket-fares.edit', $this->fare->id), false);
        $response->assertDontSee(
            'action="'.route('ticket-fares.destroy', $this->fare->id).'"',
            false
        );
        $response->assertSee(route('ticket-fares.show', $this->fare->id), false);
    }

    public function test_ticket_staff_index_hides_toggle_form(): void
    {
        $staff = $this->makeUser('Ticket Staff', Branch::first());

        $response = $this->actingAs($staff)->get(route('ticket-fares.index'));

        $response->assertOk();
        $response->assertDontSee(route('ticket-fares.toggle-active', $this->fare->id), false);
        $response->assertDontSee(route('ticket-fares.edit', $this->fare->id), false);
    }
}
