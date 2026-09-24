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
use App\Models\Passenger;
use App\Models\Role;
use App\Models\Route;
use App\Models\TicketFare;
use App\Models\TravelClass;
use App\Models\User;
use App\Models\VisaSellingPrice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketFareDeleteInUseTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

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
        $this->user = User::create([
            'name' => 'Admin', 'email' => uniqid().'@example.com',
            'password' => bcrypt('password'), 'is_active' => true, 'branch_id' => $branch->id,
        ]);
        $this->user->roles()->attach(Role::create(['name' => 'Super Admin']));

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

        $this->visa = VisaSellingPrice::create(['user_id' => $this->user->id, 'selling_price' => $this->visaPrice]);

        $this->fare = $this->makeFare(28000.00);
    }

    private function makeFare(float $selling): TicketFare
    {
        return TicketFare::create([
            'airline_id' => $this->airline->id,
            'airline_classes_id' => $this->airlineClass->id,
            'route_id' => $this->route->id,
            'ticket_type' => 'regular',
            'effective_from' => now()->subDays(30)->format('Y-m-d'),
            'effective_to' => now()->addDays(30)->format('Y-m-d'),
            'net_fare' => 25000.00,
            'selling_fare' => $selling,
            'offer_price' => null,
            'child_fare_percentage' => 75.00,
            'infant_fare_percentage' => 10.00,
            'with_meal' => true,
            'user_id' => $this->user->id,
            'is_active' => true,
        ]);
    }

    private function makeSinglePackage(TicketFare $fare): Package
    {
        return Package::create([
            'package_name' => 'Single Pkg '.uniqid(),
            'ticket_fare_id' => $fare->id,
            'visa_selling_price_id' => $this->visa->id,
            'regular_price' => $fare->selling_fare + $this->visaPrice,
            'service_charge' => 1500.00,
            'is_active' => true,
            'is_double_ticket' => false,
        ]);
    }

    private function makeDoublePackage(?TicketFare $inbound, ?TicketFare $outbound): Package
    {
        return Package::create([
            'package_name' => 'Double Pkg '.uniqid(),
            'ticket_fare_id' => null,
            'ticket_fare_inbound_id' => $inbound?->id,
            'ticket_fare_outbound_id' => $outbound?->id,
            'visa_selling_price_id' => $this->visa->id,
            'regular_price' => 50000.00,
            'service_charge' => 1500.00,
            'is_active' => true,
            'is_double_ticket' => true,
        ]);
    }

    private function makePassenger(?TicketFare $single, ?TicketFare $inbound, ?TicketFare $outbound): Passenger
    {
        $district = District::create(['name' => 'D'.uniqid(), 'division' => 'Div']);
        FlightDateGap::getOrCreate();
        $fpCharge = FingerprintCharge::create([
            'district_id' => $district->id, 'user_id' => $this->user->id, 'fingerprint_charge' => 50.00,
        ]);
        $branch = Branch::create([
            'name' => 'B'.uniqid(), 'address' => 'Addr', 'contacts' => '0123',
            'location' => 'KSA', 'fingerprint_operation' => true, 'branch_code' => 'BR'.substr(uniqid(), -6),
        ]);
        $customer = Customer::create([
            'name' => 'C'.uniqid(), 'passport_no' => 'P'.substr(uniqid(), -5),
            'iqama_type' => 'none', 'mobile_no' => '0500000000', 'address' => 'Addr',
        ]);
        $bookingPackage = Package::create([
            'package_name' => 'Booking Pkg '.uniqid(),
            'ticket_fare_id' => null,
            'ticket_fare_inbound_id' => null,
            'ticket_fare_outbound_id' => null,
            'visa_selling_price_id' => $this->visa->id,
            'regular_price' => 40000.00,
            'service_charge' => 1500.00,
            'is_active' => true,
            'is_double_ticket' => false,
        ]);
        $booking = Booking::create([
            'user_id' => $this->user->id,
            'customer_id' => $customer->id,
            'district_id' => $district->id,
            'package_id' => $bookingPackage->id,
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

        return Passenger::create([
            'booking_id' => $booking->id,
            'first_name' => 'Pax',
            'last_name' => 'Test',
            'passport_no' => 'PP'.substr(uniqid(), -8),
            'mobile_no' => '0500000000',
            'date_of_birth' => '1990-01-01',
            'passenger_type' => 'adult',
            'passport_expiry' => '2030-12-31',
            'stay_duration' => 14,
            'service_required' => 'all',
            'flight_date_from' => now()->addDays(5)->format('Y-m-d'),
            'flight_date_to' => now()->addDays(15)->format('Y-m-d'),
            'ticket_status' => 'pending',
            'address' => 'Addr',
            'ticket_fare_id' => $single?->id,
            'ticket_fare_inbound_id' => $inbound?->id,
            'ticket_fare_outbound_id' => $outbound?->id,
        ]);
    }

    public function test_backend_delete_blocked_for_single_ticket_package(): void
    {
        $this->makeSinglePackage($this->fare);

        $this->assertTrue($this->fare->isLocked());

        $this->actingAs($this->user)
            ->delete(route('ticket-fares.destroy', $this->fare->id))
            ->assertRedirect();

        $this->assertDatabaseHas('ticket_fares', ['id' => $this->fare->id]);
    }

    public function test_backend_delete_blocked_for_double_package_inbound(): void
    {
        $this->makeDoublePackage($this->fare, $this->makeFare(16000.00));

        $this->assertTrue($this->fare->isLocked());

        $this->actingAs($this->user)
            ->delete(route('ticket-fares.destroy', $this->fare->id))
            ->assertRedirect();

        $this->assertDatabaseHas('ticket_fares', ['id' => $this->fare->id]);
    }

    public function test_backend_delete_blocked_for_double_package_outbound(): void
    {
        $this->makeDoublePackage($this->makeFare(15000.00), $this->fare);

        $this->assertTrue($this->fare->isLocked());

        $this->actingAs($this->user)
            ->delete(route('ticket-fares.destroy', $this->fare->id))
            ->assertRedirect();

        $this->assertDatabaseHas('ticket_fares', ['id' => $this->fare->id]);
    }

    public function test_backend_delete_blocked_for_passenger_inbound_reference(): void
    {
        $this->makePassenger(null, $this->fare, null);

        $this->assertTrue($this->fare->isLocked());

        $this->actingAs($this->user)
            ->delete(route('ticket-fares.destroy', $this->fare->id))
            ->assertRedirect();

        $this->assertDatabaseHas('ticket_fares', ['id' => $this->fare->id]);
    }

    public function test_fare_admin_backend_delete_blocked_for_double_package(): void
    {
        $this->makeDoublePackage($this->fare, null);

        $this->actingAs($this->user)
            ->delete(route('fare.admin.fare.destroy', $this->fare->id))
            ->assertRedirect();

        $this->assertDatabaseHas('ticket_fares', ['id' => $this->fare->id]);
    }

    public function test_backend_delete_allowed_when_fare_not_in_use(): void
    {
        $this->assertFalse($this->fare->isLocked());

        $this->actingAs($this->user)
            ->delete(route('ticket-fares.destroy', $this->fare->id))
            ->assertRedirect(route('fare.admin', ['tab' => 'fares']));

        $this->assertDatabaseMissing('ticket_fares', ['id' => $this->fare->id]);
    }

    public function test_show_page_disables_delete_for_single_ticket_package(): void
    {
        $this->makeSinglePackage($this->fare);

        $response = $this->actingAs($this->user)->get(route('ticket-fares.show', $this->fare->id));

        $response->assertOk();
        $response->assertSee('In use by packages or passengers');
        $response->assertDontSee(
            'action="'.route('ticket-fares.destroy', $this->fare->id).'"',
            false
        );
    }

    public function test_show_page_disables_delete_for_double_package(): void
    {
        $this->makeDoublePackage($this->fare, null);

        $response = $this->actingAs($this->user)->get(route('ticket-fares.show', $this->fare->id));

        $response->assertOk();
        $response->assertSee('In use by packages or passengers');
        $response->assertDontSee(
            'action="'.route('ticket-fares.destroy', $this->fare->id).'"',
            false
        );
    }

    public function test_index_renders_active_delete_when_fare_not_in_use(): void
    {
        $response = $this->actingAs($this->user)->get(route('ticket-fares.index'));

        $response->assertOk();
        $response->assertSee(
            'action="'.route('ticket-fares.destroy', $this->fare->id).'"',
            false
        );
    }

    public function test_index_disables_delete_for_double_package_usage(): void
    {
        $this->makeDoublePackage(null, $this->fare);

        $response = $this->actingAs($this->user)->get(route('ticket-fares.index'));

        $response->assertOk();
        $response->assertSee('In use by packages or passengers');
        $response->assertDontSee(
            'action="'.route('ticket-fares.destroy', $this->fare->id).'"',
            false
        );
    }

    public function test_fare_admin_index_disables_delete_for_double_package_usage(): void
    {
        $this->makeDoublePackage($this->fare, null);

        $response = $this->actingAs($this->user)->get(route('fare.admin', ['tab' => 'fares']));

        $response->assertOk();
        $response->assertSee('In use by packages or passengers');
        $response->assertDontSee(
            'action="'.route('fare.admin.fare.destroy', $this->fare->id).'"',
            false
        );
    }
}
