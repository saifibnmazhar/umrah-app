<?php

namespace Tests\Feature;

use App\Models\Airline;
use App\Models\AirlineClass;
use App\Models\Booking;
use App\Models\Branch;
use App\Models\CityCode;
use App\Models\Customer;
use App\Models\District;
use App\Models\FingerprintCharge;
use App\Models\FlightDateGap;
use App\Models\Package;
use App\Models\Role;
use App\Models\Route;
use App\Models\TicketFare;
use App\Models\TravelClass;
use App\Models\User;
use App\Models\VisaSellingPrice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PackageAccessControlTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private TicketFare $fare;

    private TicketFare $otherFare;

    private VisaSellingPrice $visa;

    private float $visaPrice = 2000.00;

    protected function setUp(): void
    {
        parent::setUp();

        $branch = Branch::create([
            'name' => 'PB', 'address' => 'A', 'contacts' => '01',
            'location' => 'KSA', 'fingerprint_operation' => true, 'branch_code' => 'PB01',
        ]);
        $this->admin = $this->makeUser('Super Admin', $branch);
        $this->visa = VisaSellingPrice::create(['user_id' => $this->admin->id, 'selling_price' => $this->visaPrice]);

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

        $this->fare = $this->makeFare($airline, $airlineClass, $route, 28000.00);
        $this->otherFare = $this->makeFare($airline, $airlineClass, $route, 30000.00);
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

    private function makePackage(TicketFare $fare, string $name = 'Pkg'): Package
    {
        return Package::create([
            'package_name' => $name.' '.uniqid(),
            'ticket_fare_id' => $fare->id,
            'visa_selling_price_id' => $this->visa->id,
            'regular_price' => $fare->selling_fare + $this->visaPrice,
            'service_charge' => 1500.00,
            'is_active' => true,
            'is_double_ticket' => false,
        ]);
    }

    private function makeBookingForPackage(Package $package): void
    {
        $district = District::create(['name' => 'D'.uniqid(), 'division' => 'Div']);
        FlightDateGap::getOrCreate();
        $fpCharge = FingerprintCharge::create([
            'district_id' => $district->id, 'user_id' => $this->admin->id, 'fingerprint_charge' => 50.00,
        ]);
        $branch = Branch::create([
            'name' => 'B'.uniqid(), 'address' => 'Addr', 'contacts' => '0123',
            'location' => 'KSA', 'fingerprint_operation' => true, 'branch_code' => 'BR'.substr(uniqid(), -6),
        ]);
        $customer = Customer::create([
            'name' => 'C'.uniqid(), 'passport_no' => 'P'.substr(uniqid(), -5),
            'iqama_type' => 'none', 'mobile_no' => '0500000000', 'address' => 'Addr',
        ]);

        Booking::create([
            'user_id' => $this->admin->id,
            'customer_id' => $customer->id,
            'district_id' => $district->id,
            'package_id' => $package->id,
            'fingerprint_charge_id' => $fpCharge->id,
            'booking_branch_id' => $branch->id,
            'fingerprint_branch_id' => $branch->id,
            'invoice_id' => 'INV-'.substr(uniqid(), -8),
            'date_gap_id' => FlightDateGap::getOrCreate()->id,
            'fingerprint_location' => 'home',
            'pax_qty' => 1,
            'discount_type' => 'fixed_amount',
            'discount_value' => 0,
            'discount_amount' => 0,
            'total_value' => 40000.00,
            'is_cancelled' => false,
        ]);
    }

    public function test_disabled_package_routes_return_404(): void
    {
        $package = $this->makePackage($this->fare);
        $staff = $this->makeUser('Ticket Staff', Branch::first());

        foreach (['/packages', '/packages/create', '/packages/'.$package->id, '/packages/'.$package->id.'/edit'] as $uri) {
            $this->actingAs($this->admin)->get($uri)->assertNotFound();
            $this->actingAs($staff)->get($uri)->assertNotFound();
        }

        $this->actingAs($this->admin)->post('/packages', ['package_name' => 'X'])->assertNotFound();
        $this->actingAs($this->admin)->put('/packages/'.$package->id, ['package_name' => 'X'])->assertNotFound();
        $this->actingAs($this->admin)->delete('/packages/'.$package->id)->assertNotFound();
        $this->actingAs($staff)->delete('/packages/'.$package->id)->assertNotFound();
    }

    public function test_get_on_toggle_active_returns_404(): void
    {
        $package = $this->makePackage($this->fare);

        $this->actingAs($this->admin)->get('/packages/'.$package->id.'/toggle-active')->assertNotFound();
    }

    public function test_package_toggle_active_still_available_for_settings_tab(): void
    {
        $package = $this->makePackage($this->fare);
        $staff = $this->makeUser('Ticket Staff', Branch::first());

        $this->actingAs($staff)->patch(route('packages.toggle-active', $package))->assertForbidden();

        $this->actingAs($this->admin)
            ->patch(route('packages.toggle-active', $package))
            ->assertRedirect();

        $this->assertFalse((bool) $package->refresh()->is_active);
    }

    public function test_is_locked_accessor_falls_back_to_query_without_count(): void
    {
        $inUse = $this->makePackage($this->fare, 'InUse');
        $this->makeBookingForPackage($inUse);
        $unused = $this->makePackage($this->fare, 'Unused');

        $freshInUse = Package::find($inUse->id);
        $freshUnused = Package::find($unused->id);

        $this->assertNull($freshInUse->getAttributes()['bookings_count'] ?? null);
        $this->assertTrue($freshInUse->is_locked);
        $this->assertFalse($freshUnused->is_locked);
    }
}
