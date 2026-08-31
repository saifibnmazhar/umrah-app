<?php

namespace Tests\Feature\Concerns;

use App\Enums\FingerprintStatus;
use App\Models\Airline;
use App\Models\AirlineClass;
use App\Models\Booking;
use App\Models\Branch;
use App\Models\CityCode;
use App\Models\Customer;
use App\Models\District;
use App\Models\Fingerprint;
use App\Models\FingerprintCharge;
use App\Models\FingerprintDetail;
use App\Models\FlightDateGap;
use App\Models\Package;
use App\Models\Passenger;
use App\Models\Role;
use App\Models\Route;
use App\Models\StayDurationLimit;
use App\Models\TicketFare;
use App\Models\TravelClass;
use App\Models\User;
use App\Models\VisaSellingPrice;

trait CreatesFingerprintFixtures
{
    protected function roleUser(string $roleName): User
    {
        $user = User::create([
            'name' => 'Test User '.uniqid(),
            'email' => uniqid().'@example.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $user->roles()->attach(Role::firstOrCreate(['name' => $roleName]));

        return $user;
    }

    protected function seedDeps(User $user): array
    {
        $district = District::create(['name' => 'Test District '.uniqid(), 'division' => 'Test Division']);

        $cityFrom = CityCode::create(['city_name' => 'Dhaka', 'code' => uniqid('D'), 'country' => 'Bangladesh']);
        $cityTo = CityCode::create(['city_name' => 'Riyadh', 'code' => uniqid('R'), 'country' => 'Saudi Arabia']);

        $airline = Airline::create(['name' => 'SV '.uniqid(), 'code' => substr(uniqid(), -2)]);
        $travelClass = TravelClass::create(['name' => 'Economy '.uniqid()]);
        $airlineClass = AirlineClass::create(['airline_id' => $airline->id, 'class_id' => $travelClass->id]);

        $route = Route::create([
            'airline_id' => $airline->id,
            'route_type' => 'round',
            'flight_type' => 'direct',
            'from_city_id' => $cityFrom->id,
            'to_city_id' => $cityTo->id,
            'return_city_id' => $cityFrom->id,
            'additional_gap' => null,
        ]);

        FlightDateGap::getOrCreate();
        StayDurationLimit::getOrCreate();

        $visaPrice = VisaSellingPrice::create([
            'user_id' => $user->id,
            'selling_price' => 2000.00,
        ]);

        $fare = TicketFare::create([
            'airline_id' => $airline->id,
            'airline_classes_id' => $airlineClass->id,
            'route_id' => $route->id,
            'ticket_type' => 'regular',
            'effective_from' => now()->subDays(30),
            'effective_to' => now()->addDays(30),
            'net_fare' => 24000.00,
            'selling_fare' => 30000.00,
            'offer_price' => null,
            'child_fare_percentage' => 50.00,
            'infant_fare_percentage' => 20.00,
            'with_meal' => true,
            'user_id' => $user->id,
            'is_active' => true,
        ]);

        $package = Package::create([
            'package_name' => 'Test Package '.uniqid(),
            'ticket_fare_id' => $fare->id,
            'visa_selling_price_id' => $visaPrice->id,
            'regular_price' => 40000.00,
            'service_charge' => 500.00,
            'is_active' => true,
            'is_double_ticket' => false,
        ]);

        $fingerprintCharge = FingerprintCharge::create([
            'district_id' => $district->id,
            'user_id' => $user->id,
            'fingerprint_charge' => 300.00,
        ]);

        return compact('district', 'visaPrice', 'fare', 'package', 'fingerprintCharge', 'route');
    }

    protected function createBooking(User $user, array $deps, array $options = []): Booking
    {
        $branch = Branch::create([
            'name' => 'Branch '.uniqid(),
            'address' => 'Addr',
            'contacts' => '0123',
            'location' => 'KSA',
            'fingerprint_operation' => true,
            'branch_code' => 'BR'.substr(uniqid(), -6),
        ]);

        $customer = Customer::create([
            'name' => 'Customer '.uniqid(),
            'passport_no' => 'P'.substr(uniqid(), -5),
            'iqama_type' => 'none',
            'mobile_no' => '0500000000',
            'address' => 'Addr',
        ]);

        return Booking::create([
            'user_id' => $user->id,
            'customer_id' => $customer->id,
            'fingerprint_branch_id' => $branch->id,
            'district_id' => $deps['district']->id,
            'package_id' => $deps['package']->id,
            'fingerprint_charge_id' => $deps['fingerprintCharge']->id,
            'booking_branch_id' => $branch->id,
            'invoice_id' => 'INV-'.substr(uniqid(), -8),
            'date_gap_id' => FlightDateGap::getOrCreate()->id,
            'fingerprint_location' => $options['location'] ?? 'home',
            'pax_qty' => $options['passengers'] ?? 1,
            'discount_type' => 'fixed_amount',
            'discount_value' => 0,
            'discount_amount' => 0,
            'total_value' => 40000.00,
            'remarks' => '',
            'is_cancelled' => $options['is_cancelled'] ?? false,
        ]);
    }

    protected function fingerprintFixture(User $user, array $options = []): array
    {
        $deps = $this->seedDeps($user);
        $booking = $this->createBooking($user, $deps, $options);

        $fingerprint = Fingerprint::create([
            'booking_id' => $booking->id,
            'deadline' => now()->addDays(7),
            'cost' => $options['cost'] ?? 100,
            'assigned_staff_id' => $options['assigned_staff_id'] ?? null,
        ]);

        $count = $options['passengers'] ?? 1;
        $statuses = $options['statuses'] ?? array_fill(0, $count, 'processing');
        $details = collect();

        for ($i = 0; $i < $count; $i++) {
            $passenger = Passenger::create([
                'booking_id' => $booking->id,
                'passenger_status_id' => null,
                'first_name' => 'Passenger',
                'last_name' => 'Number'.$i,
                'passport_no' => 'P'.substr(uniqid('', true), -6),
                'mobile_no' => '05000000000',
                'date_of_birth' => '1990-01-01',
                'passenger_type' => 'adult',
                'passport_expiry' => now()->addYears(5)->format('Y-m-d'),
                'stay_duration' => 30,
                'service_required' => 'all',
                'flight_date_from' => now()->addDays(10)->format('Y-m-d'),
                'flight_date_to' => now()->addDays(15)->format('Y-m-d'),
                'ticket_status' => 'pending',
                'visa_status' => 'pending',
                'address' => 'Test Address',
            ]);

            $detail = FingerprintDetail::create([
                'fingerprint_id' => $fingerprint->id,
                'passenger_id' => $passenger->id,
                'status' => $statuses[$i] ?? 'processing',
            ]);

            $details->push($detail);
        }

        return compact('deps', 'booking', 'fingerprint', 'details');
    }

    protected function markApproved(FingerprintDetail $detail, int $minutesAgo = 0): void
    {
        $detail->update(['status' => FingerprintStatus::APPROVED]);
        if ($minutesAgo > 0) {
            $log = $detail->approvedLog;
            if ($log) {
                $log->forceFill(['created_at' => now()->subMinutes($minutesAgo)])->save();
            }
        }
    }
}
