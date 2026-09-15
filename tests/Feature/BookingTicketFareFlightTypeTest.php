<?php

namespace Tests\Feature;

use App\Models\Airline;
use App\Models\AirlineClass;
use App\Models\Bank;
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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingTicketFareFlightTypeTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = $this->createUser();
    }

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

    private function createPrerequisites(): array
    {
        $district = District::create(['name' => 'D', 'division' => 'Div']);
        $c1 = CityCode::create(['city_name' => 'Dhaka', 'code' => 'DAC', 'country' => 'BD']);
        $c2 = CityCode::create(['city_name' => 'Riyadh', 'code' => 'RUH', 'country' => 'SA']);
        $airline = Airline::create(['name' => 'SV', 'code' => 'SV']);
        $travelClass = TravelClass::create(['name' => 'Economy']);
        $airlineClass = AirlineClass::create(['airline_id' => $airline->id, 'class_id' => $travelClass->id]);
        $route = Route::create([
            'airline_id' => $airline->id,
            'route_type' => 'round',
            'flight_type' => 'direct',
            'from_city_id' => $c1->id,
            'to_city_id' => $c2->id,
            'return_city_id' => $c1->id,
        ]);

        CurrencyRate::create(['user_id' => $this->user->id, 'rate' => 1.0]);
        StayDurationLimit::getOrCreate();
        FlightDateGap::getOrCreate();
        TransactionType::create(['name' => 'Initial Payment', 'type' => 'debit']);
        PassengerStatus::firstOrCreate(['name' => 'Processing'], ['color' => '#000']);
        Bank::create(['name' => 'B', 'description' => 'd', 'currency' => 'SAR', 'location' => 'KSA']);

        $visaPrice = VisaSellingPrice::create(['user_id' => $this->user->id, 'selling_price' => 2000.00]);
        $fpCharge = FingerprintCharge::create([
            'district_id' => $district->id,
            'user_id' => $this->user->id,
            'fingerprint_charge' => 50.00,
        ]);

        $fare = TicketFare::create([
            'airline_id' => $airline->id,
            'airline_classes_id' => $airlineClass->id,
            'route_id' => $route->id,
            'ticket_type' => 'regular',
            'effective_from' => now()->subDays(30),
            'effective_to' => now()->addDays(30),
            'net_fare' => 25000.00,
            'selling_fare' => 28000.00,
            'child_fare_percentage' => 75.00,
            'infant_fare_percentage' => 10.00,
            'with_meal' => true,
            'user_id' => $this->user->id,
            'is_active' => true,
        ]);

        $package = Package::create([
            'package_name' => 'Pkg',
            'ticket_fare_id' => $fare->id,
            'visa_selling_price_id' => $visaPrice->id,
            'regular_price' => 35000.00,
            'offer_price' => 32000.00,
            'service_charge' => 1500.00,
            'is_active' => true,
            'is_double_ticket' => false,
        ]);

        $customer = Customer::create([
            'name' => 'Cust',
            'passport_no' => 'T1',
            'mobile_no' => '0501',
            'iqama_type' => 'none',
            'address' => 'A',
        ]);

        return compact('district', 'customer', 'package', 'fpCharge', 'fare', 'airline', 'airlineClass', 'route');
    }

    public function test_compute_latest_issued_ticket_includes_flight_type(): void
    {
        $deps = $this->createPrerequisites();
        $this->actingAs($this->user);

        $this->post(route('bookings.store'), [
            'customer_id' => $deps['customer']->id,
            'district_id' => $deps['district']->id,
            'fingerprint_charge_id' => $deps['fpCharge']->id,
            'fingerprint_location' => 'office',
            'pax_qty' => 1,
            'package_id' => $deps['package']->id,
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
                'flight_date_from' => '2025-02-10',
                'flight_date_to' => '2025-02-20',
                'address' => 'Addr',
            ]],
            'payment' => [
                'amount' => 100,
                'bdt_amount' => 0,
                'currency' => 'SAR',
                'payment_method' => 'cash',
                'payment_date' => now()->toDateString(),
            ],
        ])->assertRedirect();

        $passenger = Passenger::first();
        $issuedTicket = IssuedTicket::where('passenger_id', $passenger->id)
            ->whereNull('issue_type')
            ->first();
        $this->assertNotNull($issuedTicket);
        $this->assertEquals($deps['fare']->id, $issuedTicket->ticket_fare_id);

        $response = $this->getJson(route('api.bookings.passengers', [
            'booking' => $passenger->booking_id,
        ]));

        $response->assertOk();
        $passengerData = $response->json('data.0');
        $ticketData = $passengerData['ticket_data'];
        $this->assertNotNull($ticketData['latest_issued_ticket'], 'latest_issued_ticket should exist');
        $this->assertEquals(
            'direct',
            $ticketData['latest_issued_ticket']['flight_type'],
            'latest_issued_ticket must include flight_type from the issued ticket fare'
        );
    }

    public function test_flight_type_survives_when_passenger_fare_changes(): void
    {
        $deps = $this->createPrerequisites();
        $this->actingAs($this->user);

        $this->post(route('bookings.store'), [
            'customer_id' => $deps['customer']->id,
            'district_id' => $deps['district']->id,
            'fingerprint_charge_id' => $deps['fpCharge']->id,
            'fingerprint_location' => 'office',
            'pax_qty' => 1,
            'package_id' => $deps['package']->id,
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
                'flight_date_from' => '2025-02-10',
                'flight_date_to' => '2025-02-20',
                'address' => 'Addr',
            ]],
            'payment' => [
                'amount' => 100,
                'bdt_amount' => 0,
                'currency' => 'SAR',
                'payment_method' => 'cash',
                'payment_date' => now()->toDateString(),
            ],
        ])->assertRedirect();

        $passenger = Passenger::first();
        $issuedTicket = IssuedTicket::where('passenger_id', $passenger->id)
            ->whereNull('issue_type')
            ->first();

        $c1 = CityCode::create(['city_name' => 'C1', 'code' => 'C1', 'country' => 'X']);
        $c2 = CityCode::create(['city_name' => 'C2', 'code' => 'C2', 'country' => 'X']);
        $newRoute = Route::create([
            'airline_id' => $deps['airline']->id,
            'route_type' => 'oneway_outbound',
            'flight_type' => 'transit',
            'from_city_id' => $c1->id,
            'to_city_id' => $c2->id,
        ]);
        $newFare = TicketFare::create([
            'airline_id' => $deps['airline']->id,
            'airline_classes_id' => $deps['airlineClass']->id,
            'route_id' => $newRoute->id,
            'ticket_type' => 'regular',
            'effective_from' => now()->subDays(30),
            'effective_to' => now()->addDays(30),
            'net_fare' => 30000.00,
            'selling_fare' => 35000.00,
            'child_fare_percentage' => 75.00,
            'infant_fare_percentage' => 10.00,
            'with_meal' => true,
            'user_id' => $this->user->id,
            'is_active' => true,
        ]);

        $passenger->update(['ticket_fare_id' => $newFare->id]);

        $response = $this->getJson(route('api.bookings.passengers', [
            'booking' => $passenger->booking_id,
        ]));

        $response->assertOk();
        $passengerData = $response->json('data.0');
        $ticketData = $passengerData['ticket_data'];

        $this->assertEquals(
            'direct',
            $ticketData['latest_issued_ticket']['flight_type'],
            'flight_type must come from the issued ticket fare, not the passenger current fare'
        );

        $this->assertEquals(
            'Transit',
            $ticketData['ticket_fare']['flight_type'],
            'passenger current fare has Transit'
        );
    }
}
