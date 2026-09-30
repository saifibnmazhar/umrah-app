<?php

namespace Tests\Feature\Concerns;

use App\Models\Airline;
use App\Models\AirlineClass;
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
use App\Models\TravelClass;
use App\Models\User;
use App\Models\VisaSellingPrice;

trait CreatesProfitLossFixtures
{
    protected User $fxUser;

    protected array $fxDeps = [];

    protected Branch $fxBranch;

    protected Customer $fxCustomer;

    /**
     * Creates branch + Super Admin user + the prerequisite rows needed by the
     * Profit/Loss, Dashboard and BranchWise routes (booking FKs, fare, package,
     * currency rate, Processing status). Call once per test.
     */
    protected function seedProfitLossWorld(): User
    {
        $this->fxBranch = Branch::create([
            'name' => 'FX Branch '.uniqid(),
            'address' => 'Addr',
            'contacts' => '0123',
            'location' => 'KSA',
            'fingerprint_operation' => true,
            'branch_code' => 'FX'.substr(uniqid(), -6),
        ]);

        $this->fxUser = User::create([
            'name' => 'FX Admin',
            'email' => uniqid().'@example.com',
            'password' => bcrypt('password'),
            'is_active' => true,
            'branch_id' => $this->fxBranch->id,
        ]);
        $this->fxUser->roles()->attach(Role::create(['name' => 'Super Admin']));

        $district = District::create(['name' => 'District '.uniqid(), 'division' => 'Div']);
        $cityFrom = CityCode::create(['city_name' => 'Dhaka', 'code' => uniqid('D'), 'country' => 'BD']);
        $cityTo = CityCode::create(['city_name' => 'Riyadh', 'code' => uniqid('R'), 'country' => 'SA']);
        $airline = Airline::create(['name' => 'SV '.uniqid(), 'code' => substr(uniqid(), -2)]);
        $travelClass = TravelClass::create(['name' => 'Eco '.uniqid()]);
        $airlineClass = AirlineClass::create(['airline_id' => $airline->id, 'class_id' => $travelClass->id]);
        $routeModel = Route::create([
            'airline_id' => $airline->id,
            'route_type' => 'round',
            'flight_type' => 'direct',
            'from_city_id' => $cityFrom->id,
            'to_city_id' => $cityTo->id,
            'return_city_id' => $cityFrom->id,
            'additional_gap' => null,
        ]);
        $flightDateGap = FlightDateGap::getOrCreate();
        StayDurationLimit::getOrCreate();
        $currencyRate = CurrencyRate::create(['user_id' => $this->fxUser->id, 'rate' => 28.0]);
        $visaPrice = VisaSellingPrice::create(['user_id' => $this->fxUser->id, 'selling_price' => 2000.00]);
        $fare = TicketFare::create([
            'airline_id' => $airline->id,
            'airline_classes_id' => $airlineClass->id,
            'route_id' => $routeModel->id,
            'ticket_type' => 'regular',
            'effective_from' => now()->subDays(30),
            'effective_to' => now()->addDays(30),
            'net_fare' => 24000.00,
            'selling_fare' => 30000.00,
            'offer_price' => null,
            'child_fare_percentage' => 50.00,
            'infant_fare_percentage' => 20.00,
            'with_meal' => true,
            'user_id' => $this->fxUser->id,
            'is_active' => true,
        ]);
        $package = Package::create([
            'package_name' => 'FX Pkg '.uniqid(),
            'ticket_fare_id' => $fare->id,
            'visa_selling_price_id' => $visaPrice->id,
            'regular_price' => 40000.00,
            'offer_price' => 36000.00,
            'service_charge' => 500.00,
            'is_active' => true,
            'is_double_ticket' => false,
        ]);
        $fingerprintCharge = FingerprintCharge::create([
            'district_id' => $district->id,
            'user_id' => $this->fxUser->id,
            'fingerprint_charge' => 300.00,
        ]);

        $this->fxDeps = [
            'district' => $district,
            'flightDateGap' => $flightDateGap,
            'currencyRate' => $currencyRate,
            'visaPrice' => $visaPrice,
            'fare' => $fare,
            'package' => $package,
            'fingerprintCharge' => $fingerprintCharge,
            'processingStatusId' => PassengerStatus::firstOrCreate(['name' => 'Processing'])->id,
        ];

        $this->fxCustomer = Customer::create([
            'name' => 'FX Customer',
            'passport_no' => 'FP'.substr(uniqid(), -6),
            'iqama_type' => 'none',
            'mobile_no' => '0500000000',
            'address' => 'Addr',
        ]);

        return $this->fxUser;
    }

    /**
     * Booking + invoice on the shared branch. $invoice must be unique.
     */
    protected function fxBooking(string $invoice): Booking
    {
        $booking = Booking::create([
            'user_id' => $this->fxUser->id,
            'customer_id' => $this->fxCustomer->id,
            'fingerprint_branch_id' => $this->fxBranch->id,
            'district_id' => $this->fxDeps['district']->id,
            'package_id' => $this->fxDeps['package']->id,
            'fingerprint_charge_id' => $this->fxDeps['fingerprintCharge']->id,
            'booking_branch_id' => $this->fxBranch->id,
            'invoice_id' => $invoice,
            'date_gap_id' => $this->fxDeps['flightDateGap']->id,
            'fingerprint_location' => 'office',
            'pax_qty' => 1,
            'discount_type' => 'fixed_amount',
            'discount_value' => 0,
            'discount_amount' => 0,
            'total_value' => 40000.00,
            'remarks' => '',
            'currency_rate_id' => $this->fxDeps['currencyRate']->id,
            'is_cancelled' => false,
        ]);

        Invoice::create([
            'booking_id' => $booking->id,
            'branch_id' => $this->fxBranch->id,
            'user_id' => $this->fxUser->id,
            'total_amount' => 40000.00,
            'paid_amount' => 0,
            'balance' => 40000.00,
            'status' => 'pending',
        ]);

        return $booking;
    }

    protected function fxPassenger(Booking $booking, array $overrides = []): Passenger
    {
        return Passenger::create(array_merge([
            'booking_id' => $booking->id,
            'first_name' => 'Pax'.substr(uniqid(), -5),
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
            'ticket_fare_id' => $this->fxDeps['fare']->id,
            'package_value' => 25000.00,
        ], $overrides));
    }

    /**
     * Sets stored + effective profit AFTER creation: passenger/ticket observers
     * overwrite these columns during Passenger::create.
     */
    protected function fxSetProfit(Passenger $passenger, float $profit, string $effectiveAt): void
    {
        $passenger->updateQuietly([
            'profit' => $profit,
            'ticket_profit' => $profit,
            'ticket_profit_effective_at' => $effectiveAt,
        ]);
    }

    protected function fxStatus(string $name): PassengerStatus
    {
        return PassengerStatus::firstOrCreate(['name' => $name]);
    }
}
