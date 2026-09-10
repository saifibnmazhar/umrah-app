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
use App\Models\IssuedTicket;
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
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingTicketFareSyncTest extends TestCase
{
    use RefreshDatabase;

    private function createUser(): User
    {
        $branch = Branch::create([
            'name' => 'Main Branch',
            'address' => 'Addr',
            'contacts' => '0123456789',
            'location' => 'KSA',
            'fingerprint_operation' => true,
            'branch_code' => 'MAIN01',
        ]);

        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
            'is_active' => true,
            'branch_id' => $branch->id,
        ]);
        $user->roles()->attach(Role::create(['name' => 'admin']));

        return $user;
    }

    private function makeFare(User $user, Airline $airline, AirlineClass $airlineClass, Route $route, float $selling = 28000): TicketFare
    {
        return TicketFare::create([
            'airline_id' => $airline->id,
            'airline_classes_id' => $airlineClass->id,
            'route_id' => $route->id,
            'ticket_type' => 'regular',
            'effective_from' => now()->subDays(30),
            'effective_to' => now()->addDays(30),
            'net_fare' => 25000.00,
            'selling_fare' => $selling,
            'offer_price' => null,
            'child_fare_percentage' => 75.00,
            'infant_fare_percentage' => 10.00,
            'with_meal' => true,
            'user_id' => $user->id,
            'is_active' => true,
        ]);
    }

    private function createPrerequisites(User $user): array
    {
        $district = District::create(['name' => 'D', 'division' => 'Div']);
        $branch = Branch::create([
            'name' => 'TB', 'address' => 'A', 'contacts' => '01',
            'location' => 'KSA', 'fingerprint_operation' => true, 'branch_code' => 'TB01',
        ]);
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

        CurrencyRate::create(['user_id' => $user->id, 'rate' => 1.0]);
        $visaPrice = VisaSellingPrice::create(['user_id' => $user->id, 'selling_price' => 2000.00]);

        $farePackage = $this->makeFare($user, $airline, $airlineClass, $route, 28000);
        $fareStale = $this->makeFare($user, $airline, $airlineClass, $route, 20000);

        $package = Package::create([
            'package_name' => 'Pkg',
            'ticket_fare_id' => $farePackage->id,
            'visa_selling_price_id' => $visaPrice->id,
            'regular_price' => 35000.00,
            'offer_price' => 32000.00,
            'service_charge' => 1500.00,
            'is_active' => true,
            'is_double_ticket' => false,
        ]);

        $customer = Customer::create([
            'name' => 'Cust', 'passport_no' => 'T1', 'mobile_no' => '0501',
            'iqama_type' => 'none', 'address' => 'A',
        ]);

        $fpCharge = FingerprintCharge::create([
            'district_id' => $district->id, 'user_id' => $user->id, 'fingerprint_charge' => 50.00,
        ]);

        StayDurationLimit::getOrCreate();
        FlightDateGap::getOrCreate();
        TransactionType::create(['name' => 'Initial Payment', 'type' => 'debit']);
        PassengerStatus::firstOrCreate(['name' => 'Processing'], ['color' => '#000']);
        Bank::create(['name' => 'B', 'description' => 'd', 'currency' => 'SAR', 'location' => 'KSA']);

        return compact('district', 'branch', 'customer', 'package', 'fpCharge', 'farePackage', 'fareStale', 'visaPrice', 'airline', 'airlineClass', 'route');
    }

    private function passengerPayload(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'passport_no' => 'PASS12345',
            'date_of_birth' => '1990-01-01',
            'gender' => 'male',
            'passport_expiry' => '2030-12-31',
            'mobile_no' => '0501234567',
            'service_required' => 'all',
            'stay_duration' => 14,
            'flight_date_from' => '2025-02-10',
            'flight_date_to' => '2025-02-20',
            'address' => 'Addr',
        ], $overrides);
    }

    private function storePayload(array $deps, array $passengerOverrides = []): array
    {
        return [
            'customer_id' => $deps['customer']->id,
            'district_id' => $deps['district']->id,
            'fingerprint_charge_id' => $deps['fpCharge']->id,
            'fingerprint_location' => 'office',
            'pax_qty' => 1,
            'package_id' => $deps['package']->id,
            'passengers' => [$this->passengerPayload($passengerOverrides)],
            'payment' => [
                'amount' => 100, 'bdt_amount' => 0, 'currency' => 'SAR',
                'payment_method' => 'cash', 'payment_date' => now()->toDateString(),
            ],
        ];
    }

    public function test_store_ignores_stale_ticket_fare_id(): void
    {
        $this->withoutMiddleware();
        $user = $this->createUser();
        $deps = $this->createPrerequisites($user);
        $this->actingAs($user);

        $payload = $this->storePayload($deps, ['ticket_fare_id' => $deps['fareStale']->id]);

        $this->post(route('bookings.store'), $payload)->assertRedirect();

        $passenger = Passenger::first();
        $this->assertEquals($deps['farePackage']->id, $passenger->ticket_fare_id);

        $ticket = IssuedTicket::where('passenger_id', $passenger->id)->first();
        $this->assertNotNull($ticket);
        $this->assertEquals($deps['farePackage']->id, $ticket->ticket_fare_id);
    }

    public function test_add_passenger_ignores_stale_ticket_fare_id(): void
    {
        $this->withoutMiddleware([ValidateCsrfToken::class]);
        $user = $this->createUser();
        $deps = $this->createPrerequisites($user);
        $this->actingAs($user);

        $this->post(route('bookings.store'), $this->storePayload($deps))->assertRedirect();
        $booking = Booking::first();

        $response = $this->postJson(route('bookings.passengers.store', $booking), array_merge(
            $this->passengerPayload(['passport_no' => 'PASS99999', 'first_name' => 'Jane']),
            ['ticket_fare_id' => $deps['fareStale']->id]
        ));
        $response->assertOk()->assertJson(['success' => true]);

        $passenger = Passenger::where('passport_no', 'PASS99999')->first();
        $this->assertEquals($deps['farePackage']->id, $passenger->ticket_fare_id);
        $this->assertEquals($deps['farePackage']->id, IssuedTicket::where('passenger_id', $passenger->id)->value('ticket_fare_id'));
    }

    public function test_store_visa_only_has_null_fare(): void
    {
        $this->withoutMiddleware();
        $user = $this->createUser();
        $deps = $this->createPrerequisites($user);
        $this->actingAs($user);

        $payload = $this->storePayload($deps, [
            'service_required' => 'visa_only',
            'ticket_fare_id' => $deps['fareStale']->id,
        ]);

        $this->post(route('bookings.store'), $payload)->assertRedirect();

        $passenger = Passenger::first();
        $this->assertNull($passenger->ticket_fare_id);
        $this->assertNull(IssuedTicket::where('passenger_id', $passenger->id)->value('ticket_fare_id'));
    }

    public function test_store_double_ticket_syncs_inbound_outbound(): void
    {
        $this->withoutMiddleware();
        $user = $this->createUser();
        $deps = $this->createPrerequisites($user);
        $this->actingAs($user);

        $inbound = $this->makeFare($user, $deps['airline'], $deps['airlineClass'], $deps['route'], 15000);
        $outbound = $this->makeFare($user, $deps['airline'], $deps['airlineClass'], $deps['route'], 16000);
        $deps['package']->update([
            'is_double_ticket' => true,
            'ticket_fare_id' => null,
            'ticket_fare_inbound_id' => $inbound->id,
            'ticket_fare_outbound_id' => $outbound->id,
        ]);

        $payload = $this->storePayload($deps, [
            'ticket_fare_id' => $deps['fareStale']->id,
            'ticket_fare_inbound_id' => $deps['fareStale']->id,
            'ticket_fare_outbound_id' => $deps['fareStale']->id,
        ]);

        $this->post(route('bookings.store'), $payload)->assertRedirect();

        $passenger = Passenger::first();
        $this->assertNull($passenger->ticket_fare_id);
        $this->assertEquals($inbound->id, $passenger->ticket_fare_inbound_id);
        $this->assertEquals($outbound->id, $passenger->ticket_fare_outbound_id);
    }
}
