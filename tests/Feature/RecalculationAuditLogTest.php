<?php

namespace Tests\Feature;

use App\Http\Controllers\TicketFareController;
use App\Models\Airline;
use App\Models\AirlineClass;
use App\Models\Booking;
use App\Models\BookingUpdateLog;
use App\Models\Branch;
use App\Models\CityCode;
use App\Models\Customer;
use App\Models\District;
use App\Models\FingerprintCharge;
use App\Models\FlightDateGap;
use App\Models\Package;
use App\Models\PackageUpdateLog;
use App\Models\Passenger;
use App\Models\PassengerUpdateLog;
use App\Models\Route;
use App\Models\TicketFare;
use App\Models\TicketFareUpdateLog;
use App\Models\TravelClass;
use App\Models\User;
use App\Models\VisaSellingPrice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 3 (D8) — history-gap hardening: observers and the fare→package
 * cascade log-writer always write, with user_id NULL meaning "system"/CLI.
 * The Phase 2 half (action='recalculated', quiet package_value writes) is
 * added with the service split.
 */
class RecalculationAuditLogTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Branch $branch;

    private District $district;

    private Customer $customer;

    private FingerprintCharge $fpCharge;

    private VisaSellingPrice $visaPrice;

    private Package $package;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::create([
            'name' => 'Main Branch',
            'address' => 'Addr',
            'contacts' => '0123456789',
            'location' => 'KSA',
            'fingerprint_operation' => true,
            'branch_code' => 'MAIN01',
        ]);
        $this->user = User::factory()->create(['branch_id' => $this->branch->id]);
        $this->district = District::create(['name' => 'D', 'division' => 'Div']);
        $this->customer = Customer::create([
            'name' => 'Cust', 'passport_no' => 'T1', 'mobile_no' => '0501',
            'iqama_type' => 'none', 'address' => 'A',
        ]);
        $this->fpCharge = FingerprintCharge::create([
            'district_id' => $this->district->id, 'user_id' => $this->user->id, 'fingerprint_charge' => 50.00,
        ]);
        FlightDateGap::getOrCreate();
        $this->visaPrice = VisaSellingPrice::create(['user_id' => $this->user->id, 'selling_price' => 2000.00]);
        $this->package = $this->makePackage();
    }

    private function makePackage(array $overrides = []): Package
    {
        return Package::create(array_merge([
            'package_name' => 'Pkg '.uniqid(),
            'visa_selling_price_id' => $this->visaPrice->id,
            'regular_price' => 35000.00,
            'offer_price' => 32000.00,
            'service_charge' => 1500.00,
            'is_active' => true,
            'is_double_ticket' => false,
        ], $overrides));
    }

    private function makeTicketFare(array $overrides = []): TicketFare
    {
        $suffix = substr(uniqid(), -6);
        $airline = Airline::create(['name' => 'SV'.$suffix, 'code' => 'S'.$suffix]);
        $airlineClass = AirlineClass::create([
            'airline_id' => $airline->id,
            'class_id' => TravelClass::create(['name' => 'Economy'.$suffix])->id,
        ]);
        $route = Route::create([
            'airline_id' => $airline->id, 'route_type' => 'round', 'flight_type' => 'direct',
            'from_city_id' => CityCode::create(['city_name' => 'Dhaka'.$suffix, 'code' => 'D'.$suffix, 'country' => 'BD'])->id,
            'to_city_id' => CityCode::create(['city_name' => 'Riyadh'.$suffix, 'code' => 'R'.$suffix, 'country' => 'SA'])->id,
            'return_city_id' => CityCode::where('code', 'D'.$suffix)->first()->id, 'additional_gap' => null,
        ]);

        return TicketFare::create(array_merge([
            'airline_id' => $airline->id,
            'airline_classes_id' => $airlineClass->id,
            'route_id' => $route->id,
            'ticket_type' => 'regular',
            'effective_from' => now()->toDateString(),
            'effective_to' => now()->addYear()->toDateString(),
            'net_fare' => 25000.00,
            'selling_fare' => 28000.00,
            'offer_price' => 27000.00,
            'child_fare_percentage' => 70.00,
            'infant_fare_percentage' => 30.00,
            'with_meal' => false,
            'is_active' => true,
            'user_id' => $this->user->id,
        ], $overrides));
    }

    private function makeBooking(): Booking
    {
        return Booking::create([
            'user_id' => $this->user->id,
            'customer_id' => $this->customer->id,
            'fingerprint_branch_id' => $this->branch->id,
            'district_id' => $this->district->id,
            'package_id' => $this->package->id,
            'package_name' => $this->package->package_name,
            'fingerprint_charge_id' => $this->fpCharge->id,
            'booking_branch_id' => $this->branch->id,
            'invoice_id' => 'INV-'.substr(uniqid(), -8),
            'date_gap_id' => FlightDateGap::getOrCreate()->id,
            'fingerprint_location' => 'office',
            'pax_qty' => 1,
            'discount_type' => 'fixed_amount',
            'discount_value' => 0,
            'discount_amount' => 0,
            'total_value' => 40000.00,
            'remarks' => '',
            'is_cancelled' => false,
        ]);
    }

    private function makePassenger(Booking $booking): Passenger
    {
        return Passenger::create([
            'booking_id' => $booking->id,
            'first_name' => 'Pax'.substr(uniqid(), -4),
            'last_name' => 'Test',
            'passport_no' => 'PP'.substr(uniqid(), -8),
            'mobile_no' => '0500000000',
            'date_of_birth' => '1990-01-01',
            'passenger_type' => 'adult',
            'passport_expiry' => '2030-12-31',
            'stay_duration' => 14,
            'service_required' => 'all',
            'flight_date_from' => now()->addDays(5)->toDateString(),
            'flight_date_to' => now()->addDays(15)->toDateString(),
            'ticket_status' => 'pending',
            'address' => 'Addr',
            'package_value' => 40000.00,
        ]);
    }

    public function test_unauthenticated_booking_writes_log_with_null_user(): void
    {
        $this->assertGuest();

        $booking = $this->makeBooking();

        $created = BookingUpdateLog::where('booking_id', $booking->id)
            ->where('action', 'created')->first();
        $this->assertNotNull($created, 'booking created must be logged even without a user');
        $this->assertNull($created->user_id);

        $booking->update(['remarks' => 'changed']);

        $updated = BookingUpdateLog::where('booking_id', $booking->id)
            ->where('action', 'updated')->first();
        $this->assertNotNull($updated, 'booking update must be logged even without a user');
        $this->assertNull($updated->user_id);
        $this->assertSame('changed', $updated->new_values['remarks']);
    }

    public function test_unauthenticated_passenger_writes_log_with_null_user(): void
    {
        $this->assertGuest();

        $booking = $this->makeBooking();
        $passenger = $this->makePassenger($booking);

        $created = PassengerUpdateLog::where('passenger_id', $passenger->id)
            ->where('action', 'created')->first();
        $this->assertNotNull($created, 'passenger created must be logged even without a user');
        $this->assertNull($created->user_id);

        $passenger->update(['address' => 'New Addr']);

        $updated = PassengerUpdateLog::where('passenger_id', $passenger->id)
            ->where('action', 'updated')->first();
        $this->assertNotNull($updated);
        $this->assertNull($updated->user_id);
    }

    public function test_unauthenticated_package_writes_log_with_null_user(): void
    {
        $this->assertGuest();

        $package = $this->makePackage();

        $created = PackageUpdateLog::where('package_id', $package->id)
            ->where('action', 'created')->first();
        $this->assertNotNull($created, 'package created must be logged even without a user');
        $this->assertNull($created->user_id);

        $package->update(['package_name' => 'Renamed']);

        $updated = PackageUpdateLog::where('package_id', $package->id)
            ->where('action', 'updated')->first();
        $this->assertNotNull($updated);
        $this->assertNull($updated->user_id);
    }

    public function test_unauthenticated_ticket_fare_writes_log_with_null_user(): void
    {
        $this->assertGuest();

        $fare = $this->makeTicketFare();

        $created = TicketFareUpdateLog::where('ticket_fare_id', $fare->id)
            ->where('action', 'created')->first();
        $this->assertNotNull($created, 'fare created must be logged even without a user');
        $this->assertNull($created->user_id);

        $fare->update(['selling_fare' => 29000.00]);

        $updated = TicketFareUpdateLog::where('ticket_fare_id', $fare->id)
            ->where('action', 'updated')->first();
        $this->assertNotNull($updated);
        $this->assertNull($updated->user_id);
    }

    public function test_authenticated_writes_still_attribute_the_user(): void
    {
        $this->actingAs($this->user);

        $package = $this->makePackage();
        $package->update(['package_name' => 'Renamed']);

        $this->assertTrue(
            PackageUpdateLog::where('package_id', $package->id)
                ->whereNotNull('user_id')
                ->where('user_id', $this->user->id)
                ->exists()
        );
    }

    public function test_cascade_fare_updated_log_written_without_auth(): void
    {
        // The fare→package cascade (TicketFareController::cascadeFarePriceToPackages)
        // is the sole production writer of `fare_updated` rows — replay depends
        // on it, so it must not be auth-gated.
        $this->assertGuest();

        $fare = $this->makeTicketFare();
        $package = Package::create([
            'package_name' => 'PkgCascade '.uniqid(),
            'ticket_fare_id' => $fare->id,
            'visa_selling_price_id' => $this->visaPrice->id,
            'regular_price' => 30000.00, // selling 28000 + visa 2000
            'offer_price' => 29000.00,
            'service_charge' => 1500.00,
            'is_active' => true,
            'is_double_ticket' => false,
        ]);

        $controller = app(TicketFareController::class);
        $method = new \ReflectionMethod($controller, 'cascadeFarePriceToPackages');
        $method->invoke($controller, $fare, 28000.00, 27000.00, 31000.00, null);

        $log = PackageUpdateLog::where('package_id', $package->id)
            ->where('action', 'fare_updated')->first();

        $this->assertNotNull($log, 'fare_updated cascade row must be written without a user');
        $this->assertNull($log->user_id);
        $this->assertEqualsWithDelta(30000.0, (float) $log->old_values['regular_price'], 0.001);
        $this->assertEqualsWithDelta(33000.0, (float) $log->new_values['regular_price'], 0.001);
        $this->assertEqualsWithDelta(33000.0, (float) $package->fresh()->regular_price, 0.001);
    }
}
