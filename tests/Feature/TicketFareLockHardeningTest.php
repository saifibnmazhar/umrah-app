<?php

namespace Tests\Feature;

use App\Models\Airline;
use App\Models\AirlineClass;
use App\Models\Bank;
use App\Models\Booking;
use App\Models\Branch;
use App\Models\CityCode;
use App\Models\CurrencyRate;
use App\Models\Customer;
use App\Models\District;
use App\Models\FingerprintCharge;
use App\Models\FlightDateGap;
use App\Models\Package;
use App\Models\Passenger;
use App\Models\PassengerStatus;
use App\Models\Role;
use App\Models\Route;
use App\Models\StayDurationLimit;
use App\Models\TicketFare;
use App\Models\TransactionType;
use App\Models\TravelClass;
use App\Models\User;
use App\Models\VisaSellingPrice;
use App\Rules\FlightDateSlot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketFareLockHardeningTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Airline $airline;

    private AirlineClass $airlineClass;

    private Route $route;

    private TicketFare $fare;

    private Package $package;

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
        $this->actingAs($this->user);

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

        $visa = VisaSellingPrice::create(['user_id' => $this->user->id, 'selling_price' => 2000.00]);

        $this->package = Package::create([
            'package_name' => 'Pkg',
            'ticket_fare_id' => $this->fare->id,
            'visa_selling_price_id' => $visa->id,
            'regular_price' => 30000.00,
            'offer_price' => 32000.00,
            'service_charge' => 1500.00,
            'is_active' => true,
            'is_double_ticket' => false,
        ]);
    }

    private function makeBareFare(): TicketFare
    {
        return TicketFare::create([
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

    private function fullFarePayload(array $overrides = []): array
    {
        return array_merge([
            'airline_id' => $this->airline->id,
            'airline_classes_id' => $this->airlineClass->id,
            'route_id' => $this->route->id,
            'route_type' => 'round',
            'ticket_type' => 'regular',
            'effective_from' => now()->subDays(30)->format('Y-m-d'),
            'effective_to' => now()->addDays(30)->format('Y-m-d'),
            'selling_fare' => 28000.00,
            'child_fare_percentage' => 75.00,
            'infant_fare_percentage' => 10.00,
        ], $overrides);
    }

    public function test_fare_admin_dead_update_endpoint_returns_404(): void
    {
        // Path still exists for DELETE → Laravel answers 405 Method Not Allowed;
        // either way the dead PUT handler is unreachable.
        $status = $this->put('/fares/admin/fare/'.$this->fare->id, $this->fullFarePayload())->getStatusCode();
        $this->assertContains($status, [404, 405], "Expected 404/405, got {$status}");
    }

    public function test_fare_admin_dead_store_endpoint_returns_404(): void
    {
        $this->post('/fares/admin/fare', $this->fullFarePayload())
            ->assertNotFound();
    }

    public function test_direct_package_binding_marks_fare_locked(): void
    {
        $this->assertTrue($this->fare->fresh()->isLocked());
    }

    public function test_package_inbound_binding_marks_fare_locked(): void
    {
        $inboundFare = $this->makeBareFare();
        $this->assertFalse($inboundFare->fresh()->isLocked());

        Package::create([
            'package_name' => 'Double Pkg',
            'ticket_fare_id' => null,
            'ticket_fare_inbound_id' => $inboundFare->id,
            'ticket_fare_outbound_id' => $this->fare->id,
            'visa_selling_price_id' => VisaSellingPrice::create(['user_id' => $this->user->id, 'selling_price' => 2000.00])->id,
            'regular_price' => 30000.00,
            'offer_price' => 32000.00,
            'service_charge' => 1500.00,
            'is_active' => true,
            'is_double_ticket' => true,
        ]);

        $this->assertTrue($inboundFare->fresh()->isLocked());
    }

    public function test_passenger_inbound_binding_marks_fare_locked(): void
    {
        $deps = $this->bookingDeps();
        $booking = $this->storeBooking($deps);
        $passenger = Passenger::where('booking_id', $booking->id)->firstOrFail();

        $inboundFare = $this->makeBareFare();
        $this->assertFalse($inboundFare->fresh()->isLocked());

        $passenger->update(['ticket_fare_id' => null, 'ticket_fare_inbound_id' => $inboundFare->id]);

        $this->assertTrue($inboundFare->fresh()->isLocked());
    }

    public function test_passenger_inbound_bound_fare_uses_restricted_update_rules(): void
    {
        $deps = $this->bookingDeps();
        $booking = $this->storeBooking($deps);
        $passenger = Passenger::where('booking_id', $booking->id)->firstOrFail();

        $inboundFare = $this->makeBareFare();
        $passenger->update(['ticket_fare_id' => null, 'ticket_fare_inbound_id' => $inboundFare->id]);

        $otherAirline = Airline::create(['name' => 'XY', 'code' => 'XY']);

        $this->put(
            route('ticket-fares.update', $inboundFare->id),
            $this->fullFarePayload(['airline_id' => $otherAirline->id, 'selling_fare' => 31000.00])
        )->assertRedirect();

        $inboundFare->refresh();
        // Restricted rules: locked fields ignored, price fields applied.
        $this->assertEquals($this->airline->id, (int) $inboundFare->airline_id);
        $this->assertEquals(31000.00, (float) $inboundFare->selling_fare);
    }

    private function bookingDeps(): array
    {
        $district = District::create(['name' => 'D', 'division' => 'Div']);

        $customer = Customer::create([
            'name' => 'Cust', 'passport_no' => 'T1', 'mobile_no' => '0501',
            'iqama_type' => 'none', 'address' => 'A',
        ]);

        $fpCharge = FingerprintCharge::create([
            'district_id' => $district->id, 'user_id' => $this->user->id, 'fingerprint_charge' => 50.00,
        ]);

        CurrencyRate::create(['user_id' => $this->user->id, 'rate' => 1.0]);
        StayDurationLimit::getOrCreate();
        FlightDateGap::getOrCreate();
        TransactionType::create(['name' => 'Initial Payment', 'type' => 'debit']);
        PassengerStatus::firstOrCreate(['name' => 'Processing'], ['color' => '#000']);
        Bank::create(['name' => 'B', 'description' => 'd', 'currency' => 'SAR', 'location' => 'KSA']);

        return compact('district', 'customer', 'fpCharge');
    }

    private function storeBooking(array $deps): Booking
    {
        [$flightFrom, $flightTo] = FlightDateSlot::validPairForTesting();

        $payload = [
            'customer_id' => $deps['customer']->id,
            'district_id' => $deps['district']->id,
            'fingerprint_charge_id' => $deps['fpCharge']->id,
            'fingerprint_location' => 'office',
            'pax_qty' => 1,
            'package_id' => $this->package->id,
            'passengers' => [[
                'first_name' => 'John',
                'last_name' => 'Doe',
                'passport_no' => 'PASS12345',
                'date_of_birth' => '1990-01-01',
                'gender' => 'male',
                'passport_expiry' => '2030-12-31',
                'mobile_no' => '0501234567',
                'service_required' => 'all',
                'stay_duration' => 14,
                'flight_date_from' => $flightFrom,
                'flight_date_to' => $flightTo,
                'address' => 'Addr',
            ]],
            'payment' => [
                'amount' => 100, 'bdt_amount' => 0, 'currency' => 'SAR',
                'payment_method' => 'cash', 'payment_date' => now()->toDateString(),
            ],
        ];

        $this->post(route('bookings.store'), $payload)->assertRedirect();

        return Booking::orderBy('id', 'desc')->first();
    }
}
