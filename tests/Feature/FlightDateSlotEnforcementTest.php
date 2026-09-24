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
use Carbon\Carbon;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FlightDateSlotEnforcementTest extends TestCase
{
    use RefreshDatabase;

    private function createUser(): User
    {
        $branch = Branch::create([
            'name' => 'Slot Branch',
            'address' => 'Addr',
            'contacts' => '0123456789',
            'location' => 'KSA',
            'fingerprint_operation' => true,
            'branch_code' => 'SLOT01',
        ]);

        $user = User::create([
            'name' => 'Slot Admin',
            'email' => 'slot@example.com',
            'password' => bcrypt('password'),
            'is_active' => true,
            'branch_id' => $branch->id,
        ]);
        $user->roles()->attach(Role::firstOrCreate(['name' => 'Super Admin']));

        return $user;
    }

    private function createPrerequisites(User $user): array
    {
        $district = District::create(['name' => 'D', 'division' => 'Div']);
        $branch = Branch::create([
            'name' => 'B2', 'address' => 'A', 'contacts' => '01',
            'location' => 'KSA', 'fingerprint_operation' => true, 'branch_code' => 'SLOT02',
        ]);
        $c1 = CityCode::create(['city_name' => 'Dhaka', 'code' => 'DAC', 'country' => 'BD']);
        $c2 = CityCode::create(['city_name' => 'Riyadh', 'code' => 'RUH', 'country' => 'SA']);
        $airline = Airline::create(['name' => 'SV', 'code' => 'SV']);
        $tc = TravelClass::create(['name' => 'Economy']);
        $ac = AirlineClass::create(['airline_id' => $airline->id, 'class_id' => $tc->id]);
        $route = Route::create([
            'airline_id' => $airline->id, 'route_type' => 'round', 'flight_type' => 'direct',
            'from_city_id' => $c1->id, 'to_city_id' => $c2->id, 'return_city_id' => $c1->id,
            'additional_gap' => 0,
        ]);
        CurrencyRate::create(['user_id' => $user->id, 'rate' => 1.0]);
        $visaPrice = VisaSellingPrice::create(['user_id' => $user->id, 'selling_price' => 2000.00]);
        $fare = TicketFare::create([
            'airline_id' => $airline->id, 'airline_classes_id' => $ac->id, 'route_id' => $route->id,
            'ticket_type' => 'regular', 'effective_from' => now()->subDays(30), 'effective_to' => now()->addDays(30),
            'net_fare' => 25000.00, 'selling_fare' => 28000.00, 'offer_price' => null,
            'child_fare_percentage' => 75.00, 'infant_fare_percentage' => 10.00,
            'with_meal' => true, 'user_id' => $user->id, 'is_active' => true,
        ]);
        $package = Package::create([
            'package_name' => 'Pkg', 'ticket_fare_id' => $fare->id,
            'visa_selling_price_id' => $visaPrice->id, 'regular_price' => 35000.00,
            'offer_price' => 32000.00, 'service_charge' => 1500.00,
            'is_active' => true, 'is_double_ticket' => false,
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
        TransactionType::firstOrCreate(['name' => 'Initial Payment'], ['type' => 'debit']);
        PassengerStatus::firstOrCreate(['name' => 'Processing'], ['color' => '#000']);
        Bank::create(['name' => 'B', 'description' => 'd', 'currency' => 'SAR', 'location' => 'KSA']);

        return compact('district', 'branch', 'customer', 'package', 'fpCharge', 'fare', 'route');
    }

    private function passengerPayload(array $deps, array $overrides = []): array
    {
        [$from, $to] = FlightDateSlot::validPairForTesting();

        return array_merge([
            'first_name' => 'John', 'last_name' => 'Doe', 'passport_no' => 'PASS12345',
            'date_of_birth' => '1990-01-01', 'gender' => 'male', 'passport_expiry' => '2030-12-31',
            'mobile_no' => '0501234567', 'service_required' => 'all', 'stay_duration' => 14,
            'flight_date_from' => $from, 'flight_date_to' => $to, 'address' => 'Addr',
            'ticket_fare_id' => $deps['fare']->id,
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
            'passengers' => [$this->passengerPayload($deps, $passengerOverrides)],
            'payment' => [
                'amount' => 100, 'bdt_amount' => 0, 'currency' => 'SAR',
                'payment_method' => 'cash', 'payment_date' => now()->toDateString(),
            ],
        ];
    }

    private function updatePayload(Passenger $passenger, array $overrides = []): array
    {
        return array_merge([
            'first_name' => $passenger->first_name,
            'last_name' => $passenger->last_name,
            'passport_no' => $passenger->passport_no,
            'date_of_birth' => $passenger->date_of_birth->format('Y-m-d'),
            'mobile_no' => $passenger->mobile_no,
            'passport_expiry' => $passenger->passport_expiry?->format('Y-m-d'),
            'service_required' => 'all',
            'stay_duration' => $passenger->stay_duration,
            'flight_date_from' => $passenger->flight_date_from->format('Y-m-d'),
            'flight_date_to' => $passenger->flight_date_to->format('Y-m-d'),
            'address' => $passenger->address,
        ], $overrides);
    }

    // ---------- Rule unit checks (no HTTP) ----------

    public function test_rule_rejects_today_plus_14_pattern(): void
    {
        $today = Carbon::today()->format('Y-m-d');
        $plus14 = Carbon::today()->addDays(14)->format('Y-m-d');

        $this->assertFalse(FlightDateSlot::isValidSlot($today, $plus14));
    }

    public function test_rule_rejects_gap_early_but_well_shaped_slot(): void
    {
        // Well-shaped but in the past -> must fail the gap check.
        $rule = new FlightDateSlot('2020-01-01', 30, 0, Carbon::parse('2026-09-24'));
        $failures = [];
        $rule->validate('flight_date_to', '2020-01-10', function ($msg) use (&$failures) {
            $failures[] = $msg;
        });

        $this->assertNotEmpty($failures);
    }

    public function test_valid_pair_helper_passes_rule(): void
    {
        [$from, $to] = FlightDateSlot::validPairForTesting(0, 30, Carbon::parse('2026-09-24'));
        $this->assertTrue(FlightDateSlot::isValidSlot($from, $to));

        $rule = new FlightDateSlot($from, 30, 0, Carbon::parse('2026-09-24'));
        $failures = [];
        $rule->validate('flight_date_to', $to, function ($msg) use (&$failures) {
            $failures[] = $msg;
        });

        $this->assertEmpty($failures);
    }

    // ---------- POST /bookings ----------

    public function test_store_rejects_missing_flight_dates(): void
    {
        $this->withoutMiddleware([ValidateCsrfToken::class]);
        $user = $this->createUser();
        $deps = $this->createPrerequisites($user);
        $this->actingAs($user);

        $payload = $this->storePayload($deps, ['flight_date_from' => null, 'flight_date_to' => null]);

        $this->postJson(route('bookings.store'), $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['passengers.0.flight_date_from', 'passengers.0.flight_date_to']);

        $this->assertDatabaseCount('bookings', 0);
        $this->assertDatabaseCount('passengers', 0);
    }

    public function test_store_rejects_today_plus_14_fallback_pattern(): void
    {
        $this->withoutMiddleware([ValidateCsrfToken::class]);
        $user = $this->createUser();
        $deps = $this->createPrerequisites($user);
        $this->actingAs($user);

        $payload = $this->storePayload($deps, [
            'flight_date_from' => now()->toDateString(),
            'flight_date_to' => now()->addDays(14)->toDateString(),
        ]);

        $this->postJson(route('bookings.store'), $payload)->assertStatus(422);
        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_store_rejects_cross_month_range(): void
    {
        $this->withoutMiddleware([ValidateCsrfToken::class]);
        $user = $this->createUser();
        $deps = $this->createPrerequisites($user);
        $this->actingAs($user);

        $payload = $this->storePayload($deps, [
            'flight_date_from' => '2026-09-21',
            'flight_date_to' => '2026-10-05',
        ]);

        $this->postJson(route('bookings.store'), $payload)->assertStatus(422);
        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_store_rejects_missing_stay_duration(): void
    {
        $this->withoutMiddleware([ValidateCsrfToken::class]);
        $user = $this->createUser();
        $deps = $this->createPrerequisites($user);
        $this->actingAs($user);

        $payload = $this->storePayload($deps, ['stay_duration' => null]);

        $this->postJson(route('bookings.store'), $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['passengers.0.stay_duration']);

        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_store_rejects_visa_only_with_bad_dates(): void
    {
        $this->withoutMiddleware([ValidateCsrfToken::class]);
        $user = $this->createUser();
        $deps = $this->createPrerequisites($user);
        $this->actingAs($user);

        $payload = $this->storePayload($deps, [
            'service_required' => 'visa_only',
            'flight_date_from' => '2026-09-05',
            'flight_date_to' => '2026-09-18',
        ]);

        $this->postJson(route('bookings.store'), $payload)->assertStatus(422);
        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_store_accepts_valid_slot_and_stay(): void
    {
        $this->withoutMiddleware([ValidateCsrfToken::class]);
        $user = $this->createUser();
        $deps = $this->createPrerequisites($user);
        $this->actingAs($user);

        $this->postJson(route('bookings.store'), $this->storePayload($deps))
            ->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseCount('bookings', 1);
        $this->assertDatabaseCount('passengers', 1);
    }

    // ---------- POST /bookings/{booking}/passengers ----------

    public function test_add_passenger_rejects_bad_range(): void
    {
        $this->withoutMiddleware([ValidateCsrfToken::class]);
        $user = $this->createUser();
        $deps = $this->createPrerequisites($user);
        $this->actingAs($user);

        $this->postJson(route('bookings.store'), $this->storePayload($deps))->assertStatus(200);
        $booking = Booking::first();

        $bad = $this->passengerPayload($deps, [
            'passport_no' => 'BAD99999',
            'flight_date_from' => '2026-09-15',
            'flight_date_to' => '2026-09-16',
        ]);

        $resp = $this->postJson(route('bookings.passengers.store', $booking), $bad);
        $resp->assertStatus(422);
        $this->assertDatabaseMissing('passengers', ['passport_no' => 'BAD99999']);
    }

    public function test_add_passenger_accepts_valid_range(): void
    {
        $this->withoutMiddleware([ValidateCsrfToken::class]);
        $user = $this->createUser();
        $deps = $this->createPrerequisites($user);
        $this->actingAs($user);

        $this->postJson(route('bookings.store'), $this->storePayload($deps))->assertStatus(200);
        $booking = Booking::first();

        $good = $this->passengerPayload($deps, ['passport_no' => 'GOOD99999', 'first_name' => 'Jane']);

        $this->postJson(route('bookings.passengers.store', $booking), $good)
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('passengers', ['passport_no' => 'GOOD99999']);
    }

    // ---------- PUT /passengers/{passenger} ----------

    public function test_update_rejects_bad_range(): void
    {
        $this->withoutMiddleware([ValidateCsrfToken::class]);
        $user = $this->createUser();
        $deps = $this->createPrerequisites($user);
        $this->actingAs($user);

        $this->postJson(route('bookings.store'), $this->storePayload($deps))->assertStatus(200);
        $passenger = Passenger::first();

        $this->putJson(route('passengers.update', $passenger), $this->updatePayload($passenger, [
            'flight_date_from' => '2026-09-04',
            'flight_date_to' => '2026-09-18',
        ]))->assertStatus(422);

        $this->assertDatabaseMissing('passengers', [
            'id' => $passenger->id, 'flight_date_from' => '2026-09-04',
        ]);
    }

    public function test_update_rejects_to_only_payload(): void
    {
        $this->withoutMiddleware([ValidateCsrfToken::class]);
        $user = $this->createUser();
        $deps = $this->createPrerequisites($user);
        $this->actingAs($user);

        $this->postJson(route('bookings.store'), $this->storePayload($deps))->assertStatus(200);
        $passenger = Passenger::first();
        $originalTo = $passenger->flight_date_to->format('Y-m-d');

        $payload = $this->updatePayload($passenger);
        unset($payload['flight_date_from']);
        $payload['flight_date_to'] = '2099-12-31';

        $this->putJson(route('passengers.update', $passenger), $payload)->assertStatus(422);
        $this->assertEquals($originalTo, $passenger->fresh()->flight_date_to->format('Y-m-d'));
    }

    public function test_update_accepts_valid_range(): void
    {
        $this->withoutMiddleware([ValidateCsrfToken::class]);
        $user = $this->createUser();
        $deps = $this->createPrerequisites($user);
        $this->actingAs($user);

        $this->postJson(route('bookings.store'), $this->storePayload($deps))->assertStatus(200);
        $passenger = Passenger::first();

        [$from, $to] = FlightDateSlot::validPairForTesting();

        $this->putJson(route('passengers.update', $passenger), $this->updatePayload($passenger, [
            'flight_date_from' => $from,
            'flight_date_to' => $to,
        ]))->assertOk()->assertJson(['success' => true]);

        $this->assertDatabaseHas('passengers', ['id' => $passenger->id, 'flight_date_from' => $from]);
    }
}
