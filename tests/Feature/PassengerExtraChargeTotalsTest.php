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
use App\Models\Invoice;
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

class PassengerExtraChargeTotalsTest extends TestCase
{
    use RefreshDatabase;

    /** Selling fare + visa selling price + package service charge. */
    private const BASE_VALUE = 31500.0;

    /** Child (75%) fare + visa + service. */
    private const CHILD_BASE_VALUE = 24500.0;

    /** Payment recorded at booking creation (invoice paid_amount). */
    private const PAYMENT = 100.0;

    private Branch $branch;

    private function createUser(string $role = 'Super Admin', ?Branch $branch = null): User
    {
        $branch ??= $this->branch ??= Branch::create([
            'name' => 'Main Branch',
            'address' => 'Addr',
            'contacts' => '0123456789',
            'location' => 'KSA',
            'fingerprint_operation' => true,
            'branch_code' => 'MAIN01',
        ]);

        $user = User::create([
            'name' => "{$role} User",
            'email' => uniqid($role, true).'@example.com',
            'password' => bcrypt('password'),
            'is_active' => true,
            'branch_id' => $branch->id,
        ]);
        $user->roles()->attach(Role::firstOrCreate(['name' => $role]));

        return $user;
    }

    private function createPrerequisites(User $user): array
    {
        $district = District::create(['name' => 'D', 'division' => 'Div']);
        Branch::create([
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

        $fare = TicketFare::create([
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
            'user_id' => $user->id,
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
            'flight_date_from' => FlightDateSlot::validPairForTesting()[0],
            'flight_date_to' => FlightDateSlot::validPairForTesting()[1],
            'address' => 'Addr',
        ], $overrides);
    }

    private function storeBooking(array $deps, array $passengerOverrides = [], array $bookingOverrides = []): Booking
    {
        $payload = array_merge([
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
        ], $bookingOverrides);

        $this->post(route('bookings.store'), $payload)->assertRedirect();

        return Booking::firstOrFail();
    }

    private function updatePassenger(Passenger $passenger, array $overrides = [])
    {
        return $this->put(route('passengers.update', $passenger->id), array_merge([
            'first_name' => $passenger->first_name,
            'last_name' => $passenger->last_name,
            'passport_no' => $passenger->passport_no,
            'date_of_birth' => $passenger->date_of_birth,
            'stay_duration' => $passenger->stay_duration,
            'flight_date_from' => FlightDateSlot::validPairForTesting()[0],
            'flight_date_to' => FlightDateSlot::validPairForTesting()[1],
        ], $overrides));
    }

    private function assertBookingTotals(float $expectedTotal): void
    {
        $booking = Booking::firstOrFail();
        $passenger = Passenger::firstOrFail();
        $invoice = Invoice::where('booking_id', $booking->id)->firstOrFail();

        $this->assertEqualsWithDelta($expectedTotal, (float) $passenger->package_value, 0.01, 'package_value');
        $this->assertEqualsWithDelta($expectedTotal, (float) $booking->total_value, 0.01, 'total_value');
        $this->assertEqualsWithDelta($expectedTotal, (float) $invoice->total_amount, 0.01, 'invoice total_amount');
        $this->assertEqualsWithDelta($expectedTotal - self::PAYMENT, (float) $invoice->balance, 0.01, 'invoice balance');
    }

    public function test_create_booking_includes_extra_charge_in_package_value_and_totals(): void
    {
        $user = $this->createUser();
        $deps = $this->createPrerequisites($user);
        $this->actingAs($user);

        $this->storeBooking($deps, ['extra_charge' => 100]);

        $this->assertBookingTotals(self::BASE_VALUE + 100);
    }

    public function test_create_booking_without_extra_charge_keeps_base_totals(): void
    {
        $user = $this->createUser();
        $deps = $this->createPrerequisites($user);
        $this->actingAs($user);

        $this->storeBooking($deps);

        $this->assertBookingTotals(self::BASE_VALUE);
    }

    public function test_updating_extra_charge_updates_package_value_total_and_invoice(): void
    {
        $user = $this->createUser();
        $deps = $this->createPrerequisites($user);
        $this->actingAs($user);

        $this->storeBooking($deps);
        $this->assertBookingTotals(self::BASE_VALUE);

        $passenger = Passenger::firstOrFail();
        $this->updatePassenger($passenger, ['extra_charge' => 150])
            ->assertRedirect();

        $this->assertBookingTotals(self::BASE_VALUE + 150);

        $this->assertDatabaseHas('invoice_update_logs', [
            'invoice_id' => Invoice::firstOrFail()->id,
            'action' => 'updated',
            'reason' => 'passenger_updated',
        ]);
    }

    public function test_lowering_extra_charge_reduces_totals(): void
    {
        $user = $this->createUser();
        $deps = $this->createPrerequisites($user);
        $this->actingAs($user);

        $this->storeBooking($deps, ['extra_charge' => 150]);
        $this->assertBookingTotals(self::BASE_VALUE + 150);

        $this->updatePassenger(Passenger::firstOrFail(), ['extra_charge' => 50])
            ->assertRedirect();

        $this->assertBookingTotals(self::BASE_VALUE + 50);
    }

    public function test_combined_extra_charge_and_passenger_type_edit_updates_totals(): void
    {
        $user = $this->createUser();
        $deps = $this->createPrerequisites($user);
        $this->actingAs($user);

        $this->storeBooking($deps);

        $p = Passenger::firstOrFail();
        $this->updatePassenger($p, [
            'extra_charge' => 100,
            'passenger_type' => 'child',
        ])->assertRedirect();

        $this->assertBookingTotals(self::CHILD_BASE_VALUE + 100);
    }

    public function test_percentage_discount_applies_to_extra_charge(): void
    {
        $user = $this->createUser();
        $deps = $this->createPrerequisites($user);
        $this->actingAs($user);

        $this->storeBooking($deps, ['extra_charge' => 1000], [
            'discount_type' => 'percentage',
            'discount_value' => 10,
        ]);

        $booking = Booking::firstOrFail();
        $invoice = Invoice::where('booking_id', $booking->id)->firstOrFail();

        // total = 31500 + 1000 = 32500; discount = 10% = 3250
        $this->assertEqualsWithDelta(32500.0, (float) $booking->total_value, 0.01);
        $this->assertEqualsWithDelta(3250.0, (float) $booking->discount_amount, 0.01);
        $this->assertEqualsWithDelta(29250.0, (float) $invoice->total_amount, 0.01);
        $this->assertEqualsWithDelta(29250.0 - self::PAYMENT, (float) $invoice->balance, 0.01);
    }

    public function test_add_passenger_with_extra_charge_updates_totals(): void
    {
        $user = $this->createUser();
        $deps = $this->createPrerequisites($user);
        $this->actingAs($user);

        $this->storeBooking($deps);
        $this->assertBookingTotals(self::BASE_VALUE);

        $booking = Booking::firstOrFail();
        $this->post(route('bookings.passengers.store', $booking->id), $this->passengerPayload([
            'extra_charge' => 200,
        ]))->assertOk()->assertJsonPath('success', true);

        $booking->refresh();
        $newPassenger = Passenger::where('id', '!=', Passenger::firstOrFail()->id)->firstOrFail();
        $invoice = Invoice::where('booking_id', $booking->id)->firstOrFail();

        $this->assertEqualsWithDelta(self::BASE_VALUE + 200, (float) $newPassenger->package_value, 0.01);
        $this->assertEqualsWithDelta((self::BASE_VALUE * 2) + 200, (float) $booking->total_value, 0.01);
        $this->assertEqualsWithDelta((self::BASE_VALUE * 2) + 200, (float) $invoice->total_amount, 0.01);
    }

    public function test_non_admin_extra_charge_value_is_ignored(): void
    {
        $admin = $this->createUser('Super Admin');
        $deps = $this->createPrerequisites($admin);
        $this->actingAs($admin);

        $this->storeBooking($deps);
        $this->assertBookingTotals(self::BASE_VALUE);

        $staff = $this->createUser('Branch Staff', $this->branch);
        $this->actingAs($staff);

        $this->updatePassenger(Passenger::firstOrFail(), ['extra_charge' => 999])
            ->assertRedirect();

        $passenger = Passenger::firstOrFail();
        $this->assertEqualsWithDelta(0.0, (float) $passenger->extra_charge, 0.01);
        $this->assertBookingTotals(self::BASE_VALUE);
    }
}
