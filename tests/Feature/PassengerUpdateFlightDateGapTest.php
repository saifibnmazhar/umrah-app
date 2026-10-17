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
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PassengerUpdateFlightDateGapTest extends TestCase
{
    use RefreshDatabase;

    private function createAdmin(): User
    {
        $branch = Branch::create([
            'name' => 'Gap Branch',
            'address' => 'Addr',
            'contacts' => '0123456789',
            'location' => 'KSA',
            'fingerprint_operation' => true,
            'branch_code' => 'GAP01',
        ]);

        $user = User::create([
            'name' => 'Gap Admin',
            'email' => 'gap@example.com',
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
        Branch::create([
            'name' => 'B2', 'address' => 'A', 'contacts' => '01',
            'location' => 'KSA', 'fingerprint_operation' => true, 'branch_code' => 'GAP02',
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

        return compact('district', 'customer', 'package', 'fpCharge', 'fare', 'route');
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

    public function test_update_skips_gap_for_unchanged_stale_pair(): void
    {
        $this->withoutMiddleware([ValidateCsrfToken::class]);
        $user = $this->createAdmin();
        $deps = $this->createPrerequisites($user);
        $this->actingAs($user);

        $this->postJson(route('bookings.store'), $this->storePayload($deps))->assertStatus(200);
        $passenger = Passenger::firstOrFail();

        // Force a well-shaped but gap-early pair directly in DB (bypasses create validation).
        $passenger->forceFill([
            'flight_date_from' => '2026-01-01',
            'flight_date_to' => '2026-01-10',
        ])->save();
        $passenger->refresh();

        $this->assertTrue(FlightDateSlot::isValidSlot('2026-01-01', '2026-01-10'));

        $this->putJson(route('passengers.update', $passenger), $this->updatePayload($passenger, [
            'address' => 'New Address',
        ]))->assertOk()->assertJson(['success' => true]);

        $this->assertDatabaseHas('passengers', [
            'id' => $passenger->id,
            'flight_date_from' => '2026-01-01',
            'address' => 'New Address',
        ]);
    }

    public function test_update_still_rejects_bad_shape_with_gap_off(): void
    {
        $this->withoutMiddleware([ValidateCsrfToken::class]);
        $user = $this->createAdmin();
        $deps = $this->createPrerequisites($user);
        $this->actingAs($user);

        $this->postJson(route('bookings.store'), $this->storePayload($deps))->assertStatus(200);
        $passenger = Passenger::firstOrFail();

        $this->putJson(route('passengers.update', $passenger), $this->updatePayload($passenger, [
            'flight_date_from' => '2026-09-04',
            'flight_date_to' => '2026-09-18',
        ]))->assertStatus(422);

        $this->assertDatabaseMissing('passengers', [
            'id' => $passenger->id, 'flight_date_from' => '2026-09-04',
        ]);
    }

    public function test_non_admin_cannot_set_protected_fields_via_query_string(): void
    {
        $this->withoutMiddleware([ValidateCsrfToken::class]);
        $admin = $this->createAdmin();
        $deps = $this->createPrerequisites($admin);
        $this->actingAs($admin);

        $this->postJson(route('bookings.store'), $this->storePayload($deps))->assertStatus(200);
        $passenger = Passenger::firstOrFail();
        $originalFrom = $passenger->flight_date_from->format('Y-m-d');
        $originalTo = $passenger->flight_date_to->format('Y-m-d');

        $staff = User::create([
            'name' => 'Branch Staff',
            'email' => 'staff@example.com',
            'password' => bcrypt('password'),
            'is_active' => true,
            'branch_id' => $admin->branch_id,
        ]);
        $staff->roles()->attach(Role::firstOrCreate(['name' => 'Branch Staff']));
        $this->actingAs($staff);

        $body = $this->updatePayload($passenger->fresh(), ['address' => 'Staff Addr']);

        $url = route('passengers.update', $passenger).'?flight_date_from=2026-01-01&flight_date_to=2026-01-10&extra_charge=999';

        $this->putJson($url, $body)->assertOk();

        $fresh = $passenger->fresh();
        $this->assertEquals($originalFrom, $fresh->flight_date_from->format('Y-m-d'));
        $this->assertEquals($originalTo, $fresh->flight_date_to->format('Y-m-d'));
        $this->assertEqualsWithDelta(0.0, (float) $fresh->extra_charge, 0.01);
    }
}
