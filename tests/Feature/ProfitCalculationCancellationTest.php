<?php

namespace Tests\Feature;

use App\Models\Airline;
use App\Models\AirlineClass;
use App\Models\Booking;
use App\Models\Branch;
use App\Models\CityCode;
use App\Models\CurrencyRate;
use App\Models\Customer;
use App\Models\District;
use App\Models\Fingerprint;
use App\Models\FingerprintCharge;
use App\Models\FlightDateGap;
use App\Models\Invoice;
use App\Models\IssuedTicket;
use App\Models\Package;
use App\Models\Passenger;
use App\Models\Route;
use App\Models\StayDurationLimit;
use App\Models\TicketFare;
use App\Models\TravelClass;
use App\Models\User;
use App\Models\VisaSellingPrice;
use App\Models\VisaSubmission;
use App\Services\ProfitCalculationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProfitCalculationCancellationTest extends TestCase
{
    use RefreshDatabase;

    private ProfitCalculationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ProfitCalculationService;
    }

    private function setupUser(): User
    {
        return User::create([
            'name' => 'Test User',
            'email' => uniqid().'@example.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
    }

    private function seedPrerequisites(User $user): array
    {
        $district = District::create(['name' => 'D', 'division' => 'Div']);
        $cityFrom = CityCode::create(['city_name' => 'Dhaka', 'code' => uniqid('D'), 'country' => 'BD']);
        $cityTo = CityCode::create(['city_name' => 'Riyadh', 'code' => uniqid('R'), 'country' => 'SA']);
        $airline = Airline::create(['name' => 'SV '.uniqid(), 'code' => substr(uniqid(), -2)]);
        $travelClass = TravelClass::create(['name' => 'Eco '.uniqid()]);
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
        CurrencyRate::create(['user_id' => $user->id, 'rate' => 28.0000]);
        $visaPrice = VisaSellingPrice::create(['user_id' => $user->id, 'selling_price' => 2000.00]);
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
            'package_name' => 'Pkg',
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

        return compact('district', 'visaPrice', 'fare', 'package', 'fingerprintCharge');
    }

    private function createBooking(User $user, array $deps): Booking
    {
        $branch = Branch::create([
            'name' => 'Br '.uniqid(),
            'address' => 'Addr',
            'contacts' => '0123',
            'location' => 'KSA',
            'fingerprint_operation' => true,
            'branch_code' => 'BR'.substr(uniqid(), -6),
        ]);
        $customer = Customer::create([
            'name' => 'Cust',
            'passport_no' => 'P'.substr(uniqid(), -5),
            'iqama_type' => 'none',
            'mobile_no' => '0500000000',
            'address' => 'Addr',
        ]);
        $booking = Booking::create([
            'user_id' => $user->id,
            'customer_id' => $customer->id,
            'fingerprint_branch_id' => $branch->id,
            'district_id' => $deps['district']->id,
            'package_id' => $deps['package']->id,
            'fingerprint_charge_id' => $deps['fingerprintCharge']->id,
            'booking_branch_id' => $branch->id,
            'invoice_id' => 'INV-'.substr(uniqid(), -8),
            'date_gap_id' => FlightDateGap::getOrCreate()->id,
            'fingerprint_location' => 'home',
            'pax_qty' => 2,
            'discount_type' => 'fixed_amount',
            'discount_value' => 0,
            'discount_amount' => 0,
            'total_value' => 40000.00,
            'remarks' => '',
            'is_cancelled' => false,
        ]);
        Invoice::create([
            'booking_id' => $booking->id,
            'branch_id' => $branch->id,
            'user_id' => $user->id,
            'total_amount' => 40000.00,
            'paid_amount' => 0,
            'balance' => 40000.00,
            'status' => 'pending',
        ]);
        Fingerprint::create(['booking_id' => $booking->id, 'deadline' => now()->addDays(7), 'cost' => 100.00]);

        return $booking;
    }

    private function addPassenger(User $user, array $deps, Booking $booking, array $overrides = []): Passenger
    {
        $passenger = Passenger::create(array_merge([
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
            'package_value' => 25000.00,
        ], $overrides));
        VisaSubmission::create([
            'passenger_id' => $passenger->id,
            'visa_selling_price_id' => $deps['visaPrice']->id,
            'agent_commission' => 100.00,
            'net_visa_cost' => 1000.00,
            'additional_cost' => 50.00,
            'status' => 'issued',
            'is_cancelled' => false,
        ]);
        IssuedTicket::create([
            'passenger_id' => $passenger->id,
            'booking_id' => $booking->id,
            'user_id' => $user->id,
            'ticket_fare_id' => $deps['fare']->id,
            'selling_fare' => 28000.00,
            'net_fare' => 27000.00,
            'issue_type' => 'regular',
            'status' => 'issued',
            'issued_date' => now(),
        ]);

        return $passenger->refresh();
    }

    public function test_cancelled_passenger_profit_zeroed_in_booking_total(): void
    {
        $user = $this->setupUser();
        $deps = $this->seedPrerequisites($user);
        $booking = $this->createBooking($user, $deps);
        $active = $this->addPassenger($user, $deps, $booking);
        $cancelled = $this->addPassenger($user, $deps, $booking, ['is_cancelled' => true, 'profit' => 9999]);

        $profit = $this->service->recalculateBookingProfit($booking->refresh());

        $this->assertEqualsWithDelta(0.0, (float) $cancelled->refresh()->profit, 0.001);
        $this->assertEqualsWithDelta(4350.0 + 200.0, $profit, 0.001);
        $this->assertTrue($active->refresh()->profit > 0);
    }

    public function test_cancelled_passenger_excluded_from_customer_breakdown(): void
    {
        $user = $this->setupUser();
        $deps = $this->seedPrerequisites($user);
        $booking = $this->createBooking($user, $deps);
        $this->addPassenger($user, $deps, $booking);
        $this->addPassenger($user, $deps, $booking, ['is_cancelled' => true, 'profit' => 5000]);
        $this->service->recalculateBookingProfit($booking->refresh());

        $breakdown = $this->service->getCustomerProfitBreakdown($booking->refresh());

        $this->assertCount(1, $breakdown['passengers']);
        $this->assertEqualsWithDelta(4550.0, $breakdown['total'], 0.001);
    }

    public function test_cancelled_passenger_excluded_from_report_passenger_query(): void
    {
        $user = $this->setupUser();
        $deps = $this->seedPrerequisites($user);
        $booking = $this->createBooking($user, $deps);
        $active = $this->addPassenger($user, $deps, $booking);
        $cancelled = $this->addPassenger($user, $deps, $booking, ['is_cancelled' => true]);

        $count = Passenger::query()
            ->join('bookings', 'passengers.booking_id', '=', 'bookings.id')
            ->where('bookings.is_cancelled', false)
            ->where('passengers.is_cancelled', false)
            ->where('passengers.booking_id', $booking->id)
            ->count();

        $this->assertEquals(1, $count);
        $this->assertTrue(Passenger::find($active->id)->is_cancelled === false || Passenger::find($active->id)->is_cancelled == 0);
        $this->assertTrue((bool) Passenger::find($cancelled->id)->is_cancelled);
    }

    public function test_cancelled_booking_excluded_from_report_summary(): void
    {
        $user = $this->setupUser();
        $deps = $this->seedPrerequisites($user);
        $booking = $this->createBooking($user, $deps);
        $this->addPassenger($user, $deps, $booking);
        $booking->update(['is_cancelled' => true]);

        $count = Booking::where('is_cancelled', false)->where('id', $booking->id)->count();

        $this->assertEquals(0, $count);
    }

    public function test_backfill_excludes_cancelled_bookings(): void
    {
        $user = $this->setupUser();
        $deps = $this->seedPrerequisites($user);
        $booking = $this->createBooking($user, $deps);
        $this->addPassenger($user, $deps, $booking);
        $booking->update(['is_cancelled' => true]);
        DB::table('bookings')->where('id', $booking->id)->update(['profit' => 1234.00]);

        $this->service->backfillAllBookings();

        $this->assertEqualsWithDelta(1234.00, (float) $booking->refresh()->profit, 0.001);
    }

    public function test_profit_recalculated_when_passenger_cancelled_flag_changes(): void
    {
        $user = $this->setupUser();
        $deps = $this->seedPrerequisites($user);
        $booking = $this->createBooking($user, $deps);
        $p1 = $this->addPassenger($user, $deps, $booking);
        $p2 = $this->addPassenger($user, $deps, $booking);

        $before = $this->service->recalculateBookingProfit($booking->refresh());
        $this->assertEqualsWithDelta(4350.0 * 2 + 200.0, $before, 0.001);

        $p2->update(['is_cancelled' => true]);
        $after = $this->service->recalculateBookingProfit($booking->refresh());

        $this->assertEqualsWithDelta(4350.0 + 200.0, $after, 0.001);
        $this->assertEqualsWithDelta(0.0, (float) $p1->refresh()->profit - 4350.0, 0.001);
    }
}
