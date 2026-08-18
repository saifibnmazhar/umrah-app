<?php

namespace Tests\Feature;

use App\Livewire\Package\PackageListTable;
use App\Models\Airline;
use App\Models\AirlineClass;
use App\Models\CityCode;
use App\Models\Package;
use App\Models\Role;
use App\Models\Route;
use App\Models\TicketFare;
use App\Models\TravelClass;
use App\Models\User;
use App\Models\VisaSellingPrice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;
use Tests\TestCase;

class PackageListTest extends TestCase
{
    use RefreshDatabase;

    private ?User $user = null;

    private ?int $ticketFareId = null;

    private ?int $visaPriceId = null;

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
        $visaPrice = VisaSellingPrice::create([
            'user_id' => $this->user->id,
            'selling_price' => 2000.00,
        ]);
        $this->visaPriceId = $visaPrice->id;

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
            'user_id' => $this->user->id,
            'is_active' => true,
        ]);
        $this->ticketFareId = $ticketFare->id;
    }

    private function createPackage(string $name, bool $isActive = true): Package
    {
        $ticketFare = TicketFare::create([
            'airline_id' => Airline::first()->id,
            'airline_classes_id' => AirlineClass::first()->id,
            'route_id' => Route::first()->id,
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
            'is_active' => true,
        ]);

        return Package::create([
            'package_name' => $name,
            'ticket_fare_id' => $ticketFare->id,
            'visa_selling_price_id' => $this->visaPriceId,
            'regular_price' => 35000.00,
            'service_charge' => 1500.00,
            'is_active' => $isActive,
            'is_double_ticket' => false,
        ]);
    }

    /** @test */
    public function test_package_list_renders_as_livewire_component(): void
    {
        $this->seedPrerequisites();
        $package = $this->createPackage('Active Package', true);

        Auth::login($this->user);

        $response = $this->get(route('packages.index'));
        $response->assertOk();
        $response->assertSee('wire:id');
        $response->assertSee('Package Name');
        $response->assertSee($package->package_name);
    }

    /** @test */
    public function test_package_list_filter_by_status(): void
    {
        $this->seedPrerequisites();
        $this->createPackage('Active Package', true);
        $this->createPackage('Inactive Package', false);

        Auth::login($this->user);

        Livewire::test(PackageListTable::class)
            ->set('statusFilter', 'inactive')
            ->assertSee('Inactive Package')
            ->assertDontSee('Active Package');
    }

    /** @test */
    public function test_package_list_active_filter(): void
    {
        $this->seedPrerequisites();
        $this->createPackage('Active Package 1', true);
        $this->createPackage('Active Package 2', true);
        $this->createPackage('Inactive Package', false);

        Auth::login($this->user);

        Livewire::test(PackageListTable::class)
            ->set('statusFilter', '')
            ->assertSee('Active Package 1')
            ->assertSee('Active Package 2')
            ->assertDontSee('Inactive Package');
    }
}
