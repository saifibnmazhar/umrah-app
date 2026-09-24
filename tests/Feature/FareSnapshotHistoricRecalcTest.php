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
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FareSnapshotHistoricRecalcTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
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

    private function makeTravelGraph(string $suffix): array
    {
        $airline = Airline::create(['name' => 'SV'.$suffix, 'code' => 'S'.$suffix]);
        $airlineClass = AirlineClass::create([
            'airline_id' => $airline->id,
            'class_id' => TravelClass::create(['name' => 'Economy'.$suffix])->id,
        ]);
        $route = Route::create([
            'airline_id' => $airline->id, 'route_type' => 'round', 'flight_type' => 'direct',
            'from_city_id' => CityCode::create(['city_name' => 'Dhaka'.$suffix, 'code' => 'D'.$suffix.'1', 'country' => 'BD'])->id,
            'to_city_id' => CityCode::create(['city_name' => 'Riyadh'.$suffix, 'code' => 'R'.$suffix.'1', 'country' => 'SA'])->id,
            'return_city_id' => CityCode::where('code', 'D'.$suffix.'1')->first()->id, 'additional_gap' => null,
        ]);

        return [$airline, $airlineClass, $route];
    }

    private function makeFare(
        User $user,
        Airline $airline,
        AirlineClass $airlineClass,
        Route $route,
        float $selling = 28000,
        ?float $offer = null,
        string $type = 'regular',
        float $child = 75.00,
        float $infant = 10.00
    ): TicketFare {
        return TicketFare::create([
            'airline_id' => $airline->id,
            'airline_classes_id' => $airlineClass->id,
            'route_id' => $route->id,
            'ticket_type' => $type,
            'effective_from' => now()->subDays(30),
            'effective_to' => now()->addDays(30),
            'net_fare' => 25000.00,
            'selling_fare' => $selling,
            'offer_price' => $offer,
            'child_fare_percentage' => $child,
            'infant_fare_percentage' => $infant,
            'with_meal' => true,
            'user_id' => $user->id,
            'is_active' => true,
        ]);
    }

    private function createPrerequisites(User $user, TicketFare $fare): array
    {
        $district = District::create(['name' => 'D', 'division' => 'Div']);
        Branch::create([
            'name' => 'TB', 'address' => 'A', 'contacts' => '01',
            'location' => 'KSA', 'fingerprint_operation' => true, 'branch_code' => 'TB01',
        ]);

        CurrencyRate::create(['user_id' => $user->id, 'rate' => 1.0]);
        $visaPrice = VisaSellingPrice::create(['user_id' => $user->id, 'selling_price' => 2000.00]);

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

        return compact('district', 'customer', 'package', 'fpCharge');
    }

    private function storeBooking(array $deps): Booking
    {
        $payload = [
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
                'amount' => 100, 'bdt_amount' => 0, 'currency' => 'SAR',
                'payment_method' => 'cash', 'payment_date' => now()->toDateString(),
            ],
        ];

        $this->post(route('bookings.store'), $payload)->assertRedirect();

        return Booking::first();
    }

    private function regularTicketFor(Passenger $passenger): IssuedTicket
    {
        return IssuedTicket::where('passenger_id', $passenger->id)
            ->where(fn ($q) => $q->whereNull('issue_type')->orWhere('issue_type', 'regular'))
            ->firstOrFail();
    }

    private function changeTypeToChild(Passenger $passenger): void
    {
        $this->putJson(route('passengers.update', $passenger->id), [
            'first_name' => $passenger->first_name,
            'last_name' => $passenger->last_name,
            'passport_no' => $passenger->passport_no,
            'date_of_birth' => '1990-01-01',
            'gender' => 'male',
            'passenger_type' => 'child',
        ])->assertJsonPath('success', true);
    }

    public function test_type_change_uses_historic_selling_fare_and_percentage(): void
    {
        $user = $this->createUser();
        $this->actingAs($user);

        Carbon::setTestNow('2026-03-01 10:00:00');
        [$airline, $airlineClass, $route] = $this->makeTravelGraph('A');
        $fare = $this->makeFare($user, $airline, $airlineClass, $route);
        $deps = $this->createPrerequisites($user, $fare);

        // Ticket snapshot world begins here: selling 28000, child 75%.
        Carbon::setTestNow('2026-03-01 12:00:00');
        $this->storeBooking($deps);
        $passenger = Passenger::firstOrFail();
        $this->assertEquals(28000.0, (float) $this->regularTicketFor($passenger)->selling_fare);

        // Post-creation fare edits (logged): selling 28000 -> 30000, child 75 -> 50.
        Carbon::setTestNow('2026-03-01 14:00:00');
        $fare->update(['selling_fare' => 30000.00, 'child_fare_percentage' => 50.00]);
        Carbon::setTestNow();

        $this->changeTypeToChild($passenger);

        // Historic base (28000) x historic child pct (75%) = 21000, not 30000 x 75% = 22500.
        $this->assertEquals(21000.0, (float) $this->regularTicketFor($passenger->fresh())->selling_fare);
    }

    public function test_type_change_uses_historic_offer_price(): void
    {
        $user = $this->createUser();
        $this->actingAs($user);

        Carbon::setTestNow('2026-03-01 10:00:00');
        [$airline, $airlineClass, $route] = $this->makeTravelGraph('B');
        $fare = $this->makeFare($user, $airline, $airlineClass, $route, 28000, 25000, 'offer');
        $deps = $this->createPrerequisites($user, $fare);

        Carbon::setTestNow('2026-03-01 12:00:00');
        $this->storeBooking($deps);
        $passenger = Passenger::firstOrFail();
        $ticket = $this->regularTicketFor($passenger);
        $this->assertEquals(28000.0, (float) $ticket->selling_fare);
        $this->assertEquals(25000.0, (float) $ticket->offer_price);

        Carbon::setTestNow('2026-03-01 14:00:00');
        $fare->update(['selling_fare' => 30000.00, 'offer_price' => 27000.00, 'child_fare_percentage' => 50.00]);
        Carbon::setTestNow();

        $this->changeTypeToChild($passenger);

        $fresh = $this->regularTicketFor($passenger->fresh());
        $this->assertEquals(21000.0, (float) $fresh->selling_fare);
        $this->assertEquals(18750.0, (float) $fresh->offer_price);
    }
}
