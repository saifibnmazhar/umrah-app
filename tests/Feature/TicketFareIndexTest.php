<?php

namespace Tests\Feature;

use App\Enums\FlightType;
use App\Enums\RouteType;
use App\Livewire\TicketFare\TicketFareIndexTable;
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

class TicketFareIndexTest extends TestCase
{
    use RefreshDatabase;

    private ?User $user = null;

    private function setupUser(): User
    {
        if ($this->user) {
            return $this->user;
        }
        $this->user = User::create([
            'name' => 'Fare Admin',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $this->user->roles()->attach(Role::create(['name' => 'Super Admin']));

        return $this->user;
    }

    private function createFare(string $airlineName = 'Emirates', string $ticketType = 'regular', bool $isActive = true): TicketFare
    {
        $airline = Airline::create(['name' => $airlineName, 'code' => strtoupper(substr($airlineName, 0, 3))]);
        $travelClass = TravelClass::firstOrCreate(['name' => 'Economy']);
        $airlineClass = AirlineClass::create(['airline_id' => $airline->id, 'class_id' => $travelClass->id]);
        $fromCity = CityCode::create(['city_name' => 'Dhaka', 'code' => 'DAC', 'country' => 'Bangladesh']);
        $toCity = CityCode::create(['city_name' => 'Riyadh', 'code' => 'RUH', 'country' => 'Saudi Arabia']);
        $route = Route::create([
            'airline_id' => $airline->id,
            'from_city_id' => $fromCity->id,
            'to_city_id' => $toCity->id,
            'route_type' => RouteType::ROUND,
            'flight_type' => FlightType::DIRECT,
            'is_active' => true,
        ]);

        return TicketFare::create([
            'airline_id' => $airline->id,
            'airline_classes_id' => $airlineClass->id,
            'route_id' => $route->id,
            'ticket_type' => $ticketType,
            'effective_from' => now()->subDays(10),
            'effective_to' => now()->addDays(30),
            'net_fare' => 500.00,
            'selling_fare' => 600.00,
            'offer_price' => null,
            'child_fare_percentage' => 10,
            'infant_fare_percentage' => 5,
            'with_meal' => true,
            'user_id' => $this->user->id,
            'is_active' => $isActive,
        ]);
    }

    /** @test */
    public function test_fare_index_renders_as_livewire_component(): void
    {
        $this->setupUser();
        $this->createFare();
        Auth::login($this->user);

        $response = $this->get(route('ticket-fares.index'));
        $response->assertOk();
        $response->assertSee('wire:id', false);
        $response->assertSee('Ticket Fares');
    }

    /** @test */
    public function test_fare_index_filter_by_airline(): void
    {
        $this->setupUser();
        $emirates = $this->createFare('Emirates');
        $saudi = $this->createFare('Saudi Arabian Airlines');
        Auth::login($this->user);

        Livewire::test(TicketFareIndexTable::class)
            ->set('search', 'Saudi')
            ->assertSee('Saudi');
    }

    /** @test */
    public function test_fare_index_filter_by_status(): void
    {
        $this->setupUser();
        $this->createFare('Emirates', 'regular', true);
        $this->createFare('Saudi Arabian Airlines', 'regular', false);
        Auth::login($this->user);

        Livewire::test(TicketFareIndexTable::class)
            ->set('statusFilter', 'inactive')
            ->assertSee('Saudi Arabian Airlines');
    }
}
