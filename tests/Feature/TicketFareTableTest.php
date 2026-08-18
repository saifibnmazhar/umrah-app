<?php

namespace Tests\Feature;

use App\Livewire\Fare\TicketFareTable;
use App\Models\Airline;
use App\Models\AirlineClass;
use App\Models\CityCode;
use App\Models\Role;
use App\Models\Route;
use App\Models\TicketFare;
use App\Models\TravelClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;
use Tests\TestCase;

class TicketFareTableTest extends TestCase
{
    use RefreshDatabase;

    private ?User $user = null;

    private ?int $airlineId = null;

    private ?int $routeId = null;

    private ?int $airlineClassId = null;

    private function setupUser(): User
    {
        if ($this->user) {
            return $this->user;
        }
        $this->user = User::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'is_active' => true,
            'branch_id' => null,
        ]);
        $this->user->roles()->attach(Role::create(['name' => 'Super Admin']));

        return $this->user;
    }

    private function seedPrerequisites(): void
    {
        $this->setupUser();

        CityCode::create(['city_name' => 'Dhaka', 'code' => 'DAC', 'country' => 'Bangladesh']);
        CityCode::create(['city_name' => 'Riyadh', 'code' => 'RUH', 'country' => 'Saudi Arabia']);
        $airline = Airline::create(['name' => 'Saudi Arabian Airlines', 'code' => 'SV']);
        $travelClass = TravelClass::create(['name' => 'Economy']);
        $airlineClass = AirlineClass::create(['airline_id' => $airline->id, 'class_id' => $travelClass->id]);
        $this->airlineId = $airline->id;
        $this->airlineClassId = $airlineClass->id;

        $cityFrom = CityCode::where('code', 'DAC')->first();
        $cityTo = CityCode::where('code', 'RUH')->first();
        $route = Route::create([
            'airline_id' => $airline->id,
            'route_type' => 'round',
            'flight_type' => 'direct',
            'from_city_id' => $cityFrom->id,
            'to_city_id' => $cityTo->id,
            'return_city_id' => $cityFrom->id,
        ]);
        $this->routeId = $route->id;
    }

    private function createFare(string $airlineName = 'Saudi Arabian Airlines', bool $isActive = true): TicketFare
    {
        $airline = Airline::where('name', $airlineName)->first()
            ?? Airline::create(['name' => $airlineName, 'code' => strtoupper(substr($airlineName, 0, 2))]);
        $airlineClass = AirlineClass::where('airline_id', $airline->id)->first()
            ?? AirlineClass::create(['airline_id' => $airline->id, 'class_id' => TravelClass::where('name', 'Economy')->first()->id]);

        $cityFrom = CityCode::where('code', 'DAC')->first();
        $cityTo = CityCode::where('code', 'RUH')->first();
        $route = Route::where('from_city_id', $cityFrom->id)->where('to_city_id', $cityTo->id)->first()
            ?? Route::create([
                'airline_id' => $airline->id,
                'route_type' => 'round',
                'flight_type' => 'direct',
                'from_city_id' => $cityFrom->id,
                'to_city_id' => $cityTo->id,
                'return_city_id' => $cityFrom->id,
            ]);

        if (! $this->routeId) {
            $this->routeId = $route->id;
        }
        if (! $this->airlineClassId) {
            $this->airlineClassId = $airlineClass->id;
        }

        return TicketFare::create([
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
            'user_id' => $this->user->id,
            'is_active' => $isActive,
        ]);
    }

    /** @test */
    public function test_fare_table_renders_as_livewire_component(): void
    {
        $this->seedPrerequisites();
        $fare = $this->createFare();

        Auth::login($this->user);

        $response = $this->get(route('fare.admin', ['tab' => 'fares']));
        $response->assertOk();
        $response->assertSee('wire:id', false);
        $response->assertSee('Airline');
        $response->assertSee('Ticket Fares');
    }

    /** @test */
    public function test_fare_table_search_by_airline(): void
    {
        $this->seedPrerequisites();
        $fare1 = $this->createFare('Saudi Arabian Airlines');
        $fare2 = $this->createFare('Emirates');

        Auth::login($this->user);

        Livewire::test(TicketFareTable::class, ['airlines' => collect(Airline::all())])
            ->set('search', 'Emirates')
            ->assertSee('Emirates')
            ->assertSee($fare2->id);
    }

    /** @test */
    public function test_fare_table_filter_by_status(): void
    {
        $this->seedPrerequisites();
        $activeFare = $this->createFare('Saudi Arabian Airlines', true);
        $inactiveFare = $this->createFare('Emirates', false);

        Auth::login($this->user);

        Livewire::test(TicketFareTable::class, ['airlines' => collect(Airline::all())])
            ->set('statusFilter', 'inactive')
            ->assertSee($inactiveFare->id);
    }
}
