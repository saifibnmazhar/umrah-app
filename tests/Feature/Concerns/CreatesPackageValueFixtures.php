<?php

namespace Tests\Feature\Concerns;

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
use App\Models\Role;
use App\Models\Route as FlightRoute;
use App\Models\TicketFare;
use App\Models\TicketFareUpdateLog;
use App\Models\TravelClass;
use App\Models\User;
use App\Models\VisaSellingPrice;
use Illuminate\Support\Carbon;

/**
 * Fixtures for the immutable-package-value / historic-recalc test suite
 * (plan 17). Log-writers here are explicit and timestamp-controlled so
 * replay scenarios are deterministic.
 */
trait CreatesPackageValueFixtures
{
    protected function pvBranch(): Branch
    {
        return Branch::create([
            'name' => 'Main Branch',
            'address' => 'Addr',
            'contacts' => '0123456789',
            'location' => 'KSA',
            'fingerprint_operation' => true,
            'branch_code' => 'MAIN01',
        ]);
    }

    protected function pvUser(Branch $branch): User
    {
        $user = User::factory()->create(['branch_id' => $branch->id]);
        $user->roles()->attach(Role::firstOrCreate(['name' => 'Super Admin']));

        return $user;
    }

    protected function pvDistrict(string $name = 'D'): District
    {
        return District::create(['name' => $name, 'division' => 'Div']);
    }

    protected function pvCustomer(): Customer
    {
        return Customer::create([
            'name' => 'Cust', 'passport_no' => 'T'.substr(uniqid(), -6), 'mobile_no' => '0501',
            'iqama_type' => 'none', 'address' => 'A',
        ]);
    }

    protected function pvFpCharge(District $district, User $user, float $charge = 50.00): FingerprintCharge
    {
        return FingerprintCharge::create([
            'district_id' => $district->id, 'user_id' => $user->id, 'fingerprint_charge' => $charge,
        ]);
    }

    protected function pvVisaPrice(User $user, float $price): VisaSellingPrice
    {
        return VisaSellingPrice::create(['user_id' => $user->id, 'selling_price' => $price]);
    }

    /**
     * Package creation suppresses observer rows so a test's manual
     * package_update_logs rows form the exact replay history.
     */
    protected function pvPackage(User $user, ?VisaSellingPrice $visaPrice = null, array $overrides = []): Package
    {
        $visaPrice ??= $this->pvVisaPrice($user, 2000.00);

        return Package::withoutEvents(fn () => Package::create(array_merge([
            'package_name' => 'Pkg '.uniqid(),
            'visa_selling_price_id' => $visaPrice->id,
            'regular_price' => 35000.00,
            'offer_price' => 32000.00,
            'service_charge' => 1500.00,
            'is_active' => true,
            'is_double_ticket' => false,
        ], $overrides)));
    }

    /** Fare creation without observer rows — pair with pvLogFare() for exact history. */
    protected function pvTicketFare(User $user, array $overrides = []): TicketFare
    {
        $suffix = substr(uniqid(), -6);
        $airline = Airline::create(['name' => 'SV'.$suffix, 'code' => 'S'.$suffix]);
        $airlineClass = AirlineClass::create([
            'airline_id' => $airline->id,
            'class_id' => TravelClass::create(['name' => 'Economy'.$suffix])->id,
        ]);
        $route = FlightRoute::create([
            'airline_id' => $airline->id, 'route_type' => 'round', 'flight_type' => 'direct',
            'from_city_id' => CityCode::create(['city_name' => 'Dhaka'.$suffix, 'code' => 'D'.$suffix, 'country' => 'BD'])->id,
            'to_city_id' => CityCode::create(['city_name' => 'Riyadh'.$suffix, 'code' => 'R'.$suffix, 'country' => 'SA'])->id,
            'return_city_id' => CityCode::where('code', 'D'.$suffix)->first()->id, 'additional_gap' => null,
        ]);

        return TicketFare::withoutEvents(fn () => TicketFare::create(array_merge([
            'airline_id' => $airline->id,
            'airline_classes_id' => $airlineClass->id,
            'route_id' => $route->id,
            'ticket_type' => 'regular',
            'effective_from' => now()->toDateString(),
            'effective_to' => now()->addYear()->toDateString(),
            'net_fare' => 25000.00,
            'selling_fare' => 28000.00,
            'offer_price' => null,
            'child_fare_percentage' => 70.00,
            'infant_fare_percentage' => 30.00,
            'with_meal' => false,
            'is_active' => true,
            'user_id' => $user->id,
        ], $overrides)));
    }

    protected function pvBooking(Package $package, User $user, FingerprintCharge $fpCharge, District $district, array $overrides = []): Booking
    {
        FlightDateGap::getOrCreate();

        return Booking::withoutEvents(fn () => Booking::create(array_merge([
            'user_id' => $user->id,
            'customer_id' => $this->pvCustomer()->id,
            'fingerprint_branch_id' => $user->branch_id,
            'district_id' => $district->id,
            'package_id' => $package->id,
            'package_name' => $package->package_name,
            'fingerprint_charge_id' => $fpCharge->id,
            'booking_branch_id' => $user->branch_id,
            'invoice_id' => 'INV-'.substr(uniqid(), -8),
            'date_gap_id' => FlightDateGap::getOrCreate()->id,
            'fingerprint_location' => 'office',
            'pax_qty' => 1,
            'discount_type' => 'fixed_amount',
            'discount_value' => 0,
            'discount_amount' => 0,
            'total_value' => 0,
            'remarks' => '',
            'is_cancelled' => false,
        ], $overrides)));
    }

    protected function pvPassenger(Booking $booking, array $overrides = []): Passenger
    {
        return Passenger::withoutEvents(fn () => Passenger::create(array_merge([
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
            'package_value' => 0,
        ], $overrides)));
    }

    protected function pvSetCreatedAt($model, string $at): void
    {
        $model->created_at = Carbon::parse($at);
        $model->saveQuietly();
    }

    protected function pvLogFare(TicketFare $fare, string $action, ?array $old, ?array $new, string $at, User $user): void
    {
        $log = new TicketFareUpdateLog([
            'ticket_fare_id' => $fare->id,
            'user_id' => $user->id,
            'action' => $action,
            'old_values' => $old,
            'new_values' => $new,
        ]);
        $log->created_at = Carbon::parse($at);
        $log->save();
    }

    protected function pvLogPackage(Package $package, string $action, ?array $old, ?array $new, string $at, User $user): void
    {
        $log = new PackageUpdateLog([
            'package_id' => $package->id,
            'user_id' => $user->id,
            'action' => $action,
            'old_values' => $old,
            'new_values' => $new,
        ]);
        $log->created_at = Carbon::parse($at);
        $log->save();
    }

    protected function pvLogSwap(Booking $booking, int $fromPackageId, int $toPackageId, string $at, User $user): void
    {
        $log = new BookingUpdateLog([
            'booking_id' => $booking->id,
            'user_id' => $user->id,
            'booking_invoice_id' => $booking->invoice_id,
            'action' => 'updated',
            'old_values' => ['package_id' => $fromPackageId],
            'new_values' => ['package_id' => $toPackageId],
        ]);
        $log->created_at = Carbon::parse($at);
        $log->save();
    }
}
