<?php

namespace Tests\Feature;

use App\Models\Airline;
use App\Models\AirlineClass;
use App\Models\Booking;
use App\Models\Branch;
use App\Models\CancelledSubmission;
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
use App\Models\RefundedTicket;
use App\Models\ReIssuedTicket;
use App\Models\Route;
use App\Models\StayDurationLimit;
use App\Models\TicketFare;
use App\Models\TravelClass;
use App\Models\User;
use App\Models\VisaSellingPrice;
use App\Models\VisaSubmission;
use App\Services\ProfitCalculationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfitCalculationServiceTest extends TestCase
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

    /**
     * Reference numbers used across assertions:
     * visa_profit      = 2000 - 1000 - 100 - 50            = 850
     * ticket_profit    = 30000 - 27000                     = 3000
     * service_charge   = 500
     * passenger total  = 4350
     * fingerprint      = 300 - 100                         = 200
     */
    private function seedPrerequisites(User $user): array
    {
        $district = District::create(['name' => 'Test District', 'division' => 'Test Division']);

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

        CurrencyRate::create(['user_id' => $user->id, 'rate' => 28.0000]);

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
            'package_name' => 'Profit Test Package',
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

    private function createBooking(User $user, array $deps, string $suffix = 'A', float $discountAmount = 0): Booking
    {
        $branch = Branch::create([
            'name' => 'Branch '.$suffix.uniqid(),
            'address' => 'Addr',
            'contacts' => '0123',
            'location' => 'KSA',
            'fingerprint_operation' => true,
            'branch_code' => 'BR'.substr(uniqid(), -6),
        ]);

        $customer = Customer::create([
            'name' => 'Customer '.$suffix,
            'passport_no' => 'P'.$suffix.substr(uniqid(), -5),
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
            'pax_qty' => 1,
            'discount_type' => 'fixed_amount',
            'discount_value' => $discountAmount,
            'discount_amount' => $discountAmount,
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

        Fingerprint::create([
            'booking_id' => $booking->id,
            'deadline' => now()->addDays(7),
            'cost' => 100.00,
        ]);

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

        if (($overrides['service_required'] ?? 'all') !== 'ticket_only') {
            $this->addVisa($deps, $passenger);
        }

        if (($overrides['service_required'] ?? 'all') !== 'visa_only') {
            $this->addRegularTicket($user, $deps, $passenger);
        }

        return $passenger->refresh();
    }

    private function addVisa(array $deps, Passenger $passenger, array $overrides = []): VisaSubmission
    {
        return VisaSubmission::create(array_merge([
            'passenger_id' => $passenger->id,
            'visa_selling_price_id' => $deps['visaPrice']->id,
            'agent_commission' => 100.00,
            'net_visa_cost' => 1000.00,
            'additional_cost' => 50.00,
            'status' => 'issued',
            'is_cancelled' => false,
        ], $overrides));
    }

    private function addRegularTicket(User $user, array $deps, Passenger $passenger, array $overrides = []): IssuedTicket
    {
        return IssuedTicket::create(array_merge([
            'passenger_id' => $passenger->id,
            'booking_id' => $passenger->booking_id,
            'user_id' => $user->id,
            'ticket_fare_id' => $deps['fare']->id,
            'selling_fare' => 28000.00,
            'net_fare' => 27000.00,
            'issue_type' => 'regular',
            'status' => 'issued',
            'issued_date' => now(),
        ], $overrides));
    }

    /** @test */
    public function test_full_effective_passenger_profit_and_booking_rollup(): void
    {
        $user = $this->setupUser();
        $deps = $this->seedPrerequisites($user);
        $booking = $this->createBooking($user, $deps);

        $passenger = $this->addPassenger($user, $deps, $booking);
        $breakdown = $this->service->getPassengerProfitBreakdown($passenger);

        $this->assertEqualsWithDelta(850.0, $breakdown['visa_profit'], 0.001);
        $this->assertEqualsWithDelta(3000.0, $breakdown['ticket_profit'], 0.001);
        $this->assertEqualsWithDelta(500.0, $breakdown['service_charge'], 0.001);
        $this->assertEqualsWithDelta(4350.0, $breakdown['total'], 0.001);

        $bookingProfit = $this->service->recalculateBookingProfit($booking);

        // 4350 (passenger) + 200 (fingerprint 300 - 100) - 0 (discount)
        $this->assertEqualsWithDelta(4550.0, $bookingProfit, 0.001);
        $this->assertDatabaseHas('passengers', ['id' => $passenger->id, 'profit' => 4350.0]);
        $this->assertDatabaseHas('bookings', ['id' => $booking->id, 'profit' => 4550.0]);
    }

    /** @test */
    public function test_visa_not_issued_zeroes_visa_profit_and_service_charge(): void
    {
        $user = $this->setupUser();
        $deps = $this->seedPrerequisites($user);
        $booking = $this->createBooking($user, $deps);

        $passenger = $this->addPassenger($user, $deps, $booking);
        $passenger->visaSubmission->update(['status' => 'submitted']);

        $breakdown = $this->service->getPassengerProfitBreakdown($passenger->refresh());

        $this->assertEqualsWithDelta(0.0, $breakdown['visa_profit'], 0.001);
        $this->assertEqualsWithDelta(0.0, $breakdown['service_charge'], 0.001);
        $this->assertEqualsWithDelta(3000.0, $breakdown['total'], 0.001);
    }

    /** @test */
    public function test_ticket_not_issued_zeroes_ticket_profit_and_service_charge(): void
    {
        $user = $this->setupUser();
        $deps = $this->seedPrerequisites($user);
        $booking = $this->createBooking($user, $deps);

        $passenger = $this->addPassenger($user, $deps, $booking);
        $passenger->allIssuedTickets()->update(['status' => 'pending']);

        $breakdown = $this->service->getPassengerProfitBreakdown($passenger->refresh());

        $this->assertEqualsWithDelta(0.0, $breakdown['ticket_profit'], 0.001);
        $this->assertEqualsWithDelta(0.0, $breakdown['service_charge'], 0.001);
        $this->assertEqualsWithDelta(850.0, $breakdown['total'], 0.001);
    }

    /** @test */
    public function test_cancellation_fees_reduce_visa_profit(): void
    {
        $user = $this->setupUser();
        $deps = $this->seedPrerequisites($user);
        $booking = $this->createBooking($user, $deps);

        $passenger = $this->addPassenger($user, $deps, $booking);

        CancelledSubmission::create([
            'visa_submission_id' => $passenger->visaSubmission->id,
            'visa_agent_id' => null,
            'cancellation_fee' => 150.00,
        ]);

        $breakdown = $this->service->getPassengerProfitBreakdown($passenger->refresh());

        // 850 - 150 cancellation fee
        $this->assertEqualsWithDelta(700.0, $breakdown['visa_profit'], 0.001);
    }

    /** @test */
    public function test_child_and_infant_passenger_type_adjusts_selling_fare(): void
    {
        $user = $this->setupUser();
        $deps = $this->seedPrerequisites($user);
        $booking = $this->createBooking($user, $deps);

        $child = $this->addPassenger($user, $deps, $booking, ['passenger_type' => 'child']);
        $infant = $this->addPassenger($user, $deps, $booking, ['passenger_type' => 'infant']);

        // child: 30000 * 50% - 27000 = -12000 ; infant: 30000 * 20% - 27000 = -21000
        $this->assertEqualsWithDelta(-12000.0, $this->service->getPassengerProfitBreakdown($child)['ticket_profit'], 0.001);
        $this->assertEqualsWithDelta(-21000.0, $this->service->getPassengerProfitBreakdown($infant)['ticket_profit'], 0.001);
    }

    /** @test */
    public function test_offer_price_used_when_package_fare_is_offer_type(): void
    {
        $user = $this->setupUser();
        $deps = $this->seedPrerequisites($user);
        $deps['fare']->update([
            'ticket_type' => 'offer',
            'offer_price' => 25000.00,
        ]);

        $booking = $this->createBooking($user, $deps);
        $passenger = $this->addPassenger($user, $deps, $booking);

        // 25000 offer price - 27000 net = -2000
        $this->assertEqualsWithDelta(-2000.0, $this->service->getPassengerProfitBreakdown($passenger)['ticket_profit'], 0.001);
    }

    /** @test */
    public function test_double_ticket_package_sums_inbound_and_outbound_fares(): void
    {
        $user = $this->setupUser();
        $deps = $this->seedPrerequisites($user);

        $outboundFare = TicketFare::create([
            'airline_id' => $deps['fare']->airline_id,
            'airline_classes_id' => $deps['fare']->airline_classes_id,
            'route_id' => $deps['route']->id,
            'ticket_type' => 'regular',
            'effective_from' => now()->subDays(30),
            'effective_to' => now()->addDays(30),
            'net_fare' => 10000.00,
            'selling_fare' => 8000.00,
            'child_fare_percentage' => 50.00,
            'infant_fare_percentage' => 20.00,
            'with_meal' => false,
            'user_id' => $user->id,
            'is_active' => true,
        ]);

        $deps['package']->update([
            'is_double_ticket' => true,
            'ticket_fare_inbound_id' => $deps['fare']->id,
            'ticket_fare_outbound_id' => $outboundFare->id,
        ]);
        $deps['package']->refresh();
        $deps['packageInbound'] = $deps['fare'];
        $deps['packageOutbound'] = $outboundFare;

        $booking = $this->createBooking($user, $deps);
        $passenger = $this->addPassenger($user, $deps, $booking);

        // inbound 30000 + outbound 8000 = 38000 selling - 27000 net = 11000
        $this->assertEqualsWithDelta(11000.0, $this->service->getPassengerProfitBreakdown($passenger)['ticket_profit'], 0.001);
    }

    /** @test */
    public function test_customer_paid_reissue_counts_service_charge_as_profit(): void
    {
        $user = $this->setupUser();
        $deps = $this->seedPrerequisites($user);
        $booking = $this->createBooking($user, $deps);

        $passenger = $this->addPassenger($user, $deps, $booking);
        $ticket = $passenger->allIssuedTickets->first();

        ReIssuedTicket::create([
            'user_id' => $user->id,
            'issued_ticket_id' => $ticket->id,
            're_issue_date' => now(),
            'service_charge' => 200.00,
            'payment_by' => 'customer',
        ]);

        $breakdown = $this->service->getPassengerProfitBreakdown($passenger->refresh());

        $this->assertEqualsWithDelta(200.0, $breakdown['re_issue_profit'], 0.001);
        $this->assertEqualsWithDelta(0.0, $breakdown['re_issue_cost'], 0.001);
    }

    /** @test */
    public function test_company_paid_reissue_counts_as_cost_not_profit(): void
    {
        $user = $this->setupUser();
        $deps = $this->seedPrerequisites($user);
        $booking = $this->createBooking($user, $deps);

        $passenger = $this->addPassenger($user, $deps, $booking);
        $ticket = $passenger->allIssuedTickets->first();

        ReIssuedTicket::create([
            'user_id' => $user->id,
            'issued_ticket_id' => $ticket->id,
            're_issue_date' => now(),
            'service_charge' => 0,
            're_issue_charge' => 100.00,
            'fare_difference' => 50.00,
            'other_costs' => 25.00,
            'net_fare' => 26500.00,
            'total_cost' => 26675.00,
            'payment_by' => 'company',
        ]);

        $breakdown = $this->service->getPassengerProfitBreakdown($passenger->refresh());

        $this->assertEqualsWithDelta(0.0, $breakdown['re_issue_profit'], 0.001);
        // 100 + 50 + 25 + 26500 = 26675
        $this->assertEqualsWithDelta(26675.0, $breakdown['re_issue_cost'], 0.001);
        // 850 + 3000 + 500 - 26675 = -22325
        $this->assertEqualsWithDelta(-22325.0, $breakdown['total'], 0.001);
    }

    /** @test */
    public function test_stored_passenger_profit_reflects_reissue_total_cost_update(): void
    {
        $user = $this->setupUser();
        $deps = $this->seedPrerequisites($user);
        $booking = $this->createBooking($user, $deps);

        $passenger = $this->addPassenger($user, $deps, $booking);
        $ticket = $passenger->allIssuedTickets->first();

        // total_cost is set AFTER create() via update(), mirroring ReIssueController::store
        $reissue = ReIssuedTicket::create([
            'user_id' => $user->id,
            'issued_ticket_id' => $ticket->id,
            're_issue_date' => now(),
            'service_charge' => 0,
            're_issue_charge' => 100.00,
            'fare_difference' => 50.00,
            'other_costs' => 25.00,
            'net_fare' => 26500.00,
            'payment_by' => 'company',
        ]);

        $reissue->update(['total_cost' => 26675.00]);

        // stored passenger profit must equal the recomputed breakdown total
        $stored = $passenger->refresh()->profit;
        $breakdown = $this->service->getPassengerProfitBreakdown($passenger);

        $this->assertEqualsWithDelta($breakdown['total'], (float) $stored, 0.001);
        // 850 + 3000 + 500 - 26675 = -22325
        $this->assertEqualsWithDelta(-22325.0, (float) $stored, 0.001);
    }

    /** @test */
    public function test_refund_service_charge_counts_as_profit(): void
    {
        $user = $this->setupUser();
        $deps = $this->seedPrerequisites($user);
        $booking = $this->createBooking($user, $deps);

        $passenger = $this->addPassenger($user, $deps, $booking);
        $ticket = $passenger->allIssuedTickets->first();

        RefundedTicket::create([
            'user_id' => $user->id,
            'issued_ticket_id' => $ticket->id,
            'refund_date' => now(),
            'service_charge' => 75.00,
        ]);

        $breakdown = $this->service->getPassengerProfitBreakdown($passenger->refresh());

        $this->assertEqualsWithDelta(75.0, $breakdown['refund_profit'], 0.001);
    }

    /** @test */
    public function test_visa_only_skips_ticket_effectiveness_for_service_charge(): void
    {
        $user = $this->setupUser();
        $deps = $this->seedPrerequisites($user);
        $booking = $this->createBooking($user, $deps);

        // No ticket exists at all — but visa_only treats ticket sector as effective
        $passenger = $this->addPassenger($user, $deps, $booking, ['service_required' => 'visa_only']);

        $breakdown = $this->service->getPassengerProfitBreakdown($passenger);

        $this->assertEqualsWithDelta(850.0, $breakdown['visa_profit'], 0.001);
        $this->assertEqualsWithDelta(500.0, $breakdown['service_charge'], 0.001);
        $this->assertEqualsWithDelta(1350.0, $breakdown['total'], 0.001);
    }

    /** @test */
    public function test_ticket_only_skips_visa_effectiveness_for_service_charge(): void
    {
        $user = $this->setupUser();
        $deps = $this->seedPrerequisites($user);
        $booking = $this->createBooking($user, $deps);

        // No visa submission exists at all — but ticket_only treats visa sector as effective
        $passenger = $this->addPassenger($user, $deps, $booking, ['service_required' => 'ticket_only']);

        $breakdown = $this->service->getPassengerProfitBreakdown($passenger);

        $this->assertEqualsWithDelta(3000.0, $breakdown['ticket_profit'], 0.001);
        $this->assertEqualsWithDelta(500.0, $breakdown['service_charge'], 0.001);
        $this->assertEqualsWithDelta(3500.0, $breakdown['total'], 0.001);
    }

    /** @test */
    public function test_booking_profit_subtracts_discount(): void
    {
        $user = $this->setupUser();
        $deps = $this->seedPrerequisites($user);
        $booking = $this->createBooking($user, $deps, 'B', 500.00);

        $this->addPassenger($user, $deps, $booking);
        $this->addPassenger($user, $deps, $booking);

        $bookingProfit = $this->service->recalculateBookingProfit($booking);

        // 4350 * 2 + 200 fingerprint - 500 discount = 8400
        $this->assertEqualsWithDelta(8400.0, $bookingProfit, 0.001);
    }

    /** @test */
    public function test_fingerprint_profit_zero_for_office_location(): void
    {
        $user = $this->setupUser();
        $deps = $this->seedPrerequisites($user);
        $booking = $this->createBooking($user, $deps);
        $booking->update(['fingerprint_location' => 'office']);

        $fingerprint = $booking->refresh()->fingerprint;

        $this->assertEqualsWithDelta(0.0, $this->service->calculateFingerprintProfit($fingerprint), 0.001);
    }

    /** @test */
    public function test_fingerprint_profit_zero_for_zero_cost(): void
    {
        $user = $this->setupUser();
        $deps = $this->seedPrerequisites($user);
        $booking = $this->createBooking($user, $deps);

        $booking->fingerprint->update(['cost' => 0]);

        $this->assertEqualsWithDelta(0.0, $this->service->calculateFingerprintProfit($booking->fingerprint), 0.001);
    }

    /** @test */
    public function test_fingerprint_profit_home_location_with_cost(): void
    {
        $user = $this->setupUser();
        $deps = $this->seedPrerequisites($user);
        $booking = $this->createBooking($user, $deps);

        // home + cost 100 => 300 - 100 = 200
        $this->assertEqualsWithDelta(200.0, $this->service->calculateFingerprintProfit($booking->fingerprint), 0.001);
    }

    /** @test */
    public function test_booking_discount_skipped_when_passenger_not_effective(): void
    {
        $user = $this->setupUser();
        $deps = $this->seedPrerequisites($user);
        $booking = $this->createBooking($user, $deps, 'A', 500.00);

        $passenger = $this->addPassenger($user, $deps, $booking);
        // visa not issued => passenger not effective => discount = 0
        $passenger->visaSubmission->update(['status' => 'submitted']);

        $bookingProfit = $this->service->recalculateBookingProfit($booking->refresh());

        // passenger is non-effective => excluded from booking sum
        // fingerprint 200 (home + cost). discount skipped.
        $this->assertEqualsWithDelta(200.0, $bookingProfit, 0.001);
    }

    /** @test */
    public function test_booking_discount_applied_when_all_passengers_effective(): void
    {
        $user = $this->setupUser();
        $deps = $this->seedPrerequisites($user);
        $booking = $this->createBooking($user, $deps, 'A', 500.00);

        $this->addPassenger($user, $deps, $booking);

        $bookingProfit = $this->service->recalculateBookingProfit($booking);

        // passenger 4350 + fingerprint 200 - discount 500 = 4050
        $this->assertEqualsWithDelta(4050.0, $bookingProfit, 0.001);
    }

    /** @test */
    public function test_booking_profit_excludes_non_effective_passenger(): void
    {
        $user = $this->setupUser();
        $deps = $this->seedPrerequisites($user);
        $booking = $this->createBooking($user, $deps);

        $p1 = $this->addPassenger($user, $deps, $booking);
        $p2 = $this->addPassenger($user, $deps, $booking);

        // p2 visa not issued => p2 excluded from booking sum
        $p2->visaSubmission->update(['status' => 'submitted']);

        $bookingProfit = $this->service->recalculateBookingProfit($booking->refresh());

        // p1: effective 4350 ; p2 not effective => excluded
        // fingerprint 200 (home + cost). no discount
        $this->assertEqualsWithDelta(4350.0 + 200.0, $bookingProfit, 0.001);
    }

    /** @test */
    public function test_backfill_updates_stored_values_without_errors(): void
    {
        $user = $this->setupUser();
        $deps = $this->seedPrerequisites($user);
        $booking = $this->createBooking($user, $deps);

        $this->addPassenger($user, $deps, $booking);

        Booking::query()->update(['profit' => 0]);

        $this->service->backfillAllBookings();

        $this->assertDatabaseHas('bookings', ['id' => $booking->id, 'profit' => 4550.0]);
    }
}
