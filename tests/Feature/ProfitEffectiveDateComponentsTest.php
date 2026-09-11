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
use App\Models\IssuedTicketLog;
use App\Models\Package;
use App\Models\Passenger;
use App\Models\RefundedTicket;
use App\Models\ReIssuedTicket;
use App\Models\Role;
use App\Models\Route;
use App\Models\StayDurationLimit;
use App\Models\TicketFare;
use App\Models\TravelClass;
use App\Models\User;
use App\Models\VisaSellingPrice;
use App\Models\VisaSubmission;
use App\Services\ProfitCalculationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class ProfitEffectiveDateComponentsTest extends TestCase
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
        $user = User::create([
            'name' => 'Admin User',
            'email' => uniqid().'@example.com',
            'password' => bcrypt('password'),
            'is_active' => true,
            'branch_id' => null,
        ]);
        $user->roles()->attach(Role::create(['name' => 'Super Admin']));

        return $user;
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
            'pax_qty' => 1,
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

    private function addPassenger(User $user, array $deps, Booking $booking): Passenger
    {
        $passenger = Passenger::create([
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
        ]);
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
            'issued_date' => '2020-01-01',
        ]);

        return $passenger->refresh();
    }

    private function range(): array
    {
        return [now()->subDays(30)->toDateTimeString(), now()->addDay()->toDateTimeString()];
    }

    public function test_additional_ticket_in_range_counts(): void
    {
        $user = $this->setupUser();
        $deps = $this->seedPrerequisites($user);
        $booking = $this->createBooking($user, $deps);
        $passenger = $this->addPassenger($user, $deps, $booking);
        IssuedTicket::create([
            'passenger_id' => $passenger->id,
            'booking_id' => $booking->id,
            'user_id' => $user->id,
            'ticket_fare_id' => $deps['fare']->id,
            'net_fare' => 24000.00,
            'issue_type' => 'additional',
            'status' => 'issued',
            'issued_date' => now()->toDateString(),
        ]);
        $this->service->recalculateBookingProfit($booking->refresh());
        [$from, $to] = $this->range();

        $this->assertEqualsWithDelta(6000.0, $this->service->effectiveAdditionalTicketProfit($passenger->refresh(), $from, $to), 0.001);
    }

    public function test_additional_ticket_out_of_range_excluded(): void
    {
        $user = $this->setupUser();
        $deps = $this->seedPrerequisites($user);
        $booking = $this->createBooking($user, $deps);
        $passenger = $this->addPassenger($user, $deps, $booking);
        IssuedTicket::create([
            'passenger_id' => $passenger->id,
            'booking_id' => $booking->id,
            'user_id' => $user->id,
            'ticket_fare_id' => $deps['fare']->id,
            'net_fare' => 24000.00,
            'issue_type' => 'additional',
            'status' => 'issued',
            'issued_date' => '2020-02-01',
        ]);
        [$from, $to] = $this->range();

        $this->assertEqualsWithDelta(0.0, $this->service->effectiveAdditionalTicketProfit($passenger->refresh(), $from, $to), 0.001);
    }

    public function test_additional_ticket_falls_back_to_issue_log(): void
    {
        $user = $this->setupUser();
        $deps = $this->seedPrerequisites($user);
        $booking = $this->createBooking($user, $deps);
        $passenger = $this->addPassenger($user, $deps, $booking);
        $ticket = IssuedTicket::create([
            'passenger_id' => $passenger->id,
            'booking_id' => $booking->id,
            'user_id' => $user->id,
            'ticket_fare_id' => $deps['fare']->id,
            'net_fare' => 24000.00,
            'issue_type' => 'additional',
            'status' => 'issued',
            'issued_date' => null,
        ]);
        IssuedTicketLog::create([
            'issued_ticket_id' => $ticket->id,
            'user_id' => $user->id,
            'action' => 'issued',
            'old_data' => null,
            'new_data' => ['status' => 'issued'],
        ]);
        [$from, $to] = $this->range();

        $this->assertEqualsWithDelta(6000.0, $this->service->effectiveAdditionalTicketProfit($passenger->refresh(), $from, $to), 0.001);
    }

    public function test_reissue_profit_uses_created_at_not_reissue_date(): void
    {
        $user = $this->setupUser();
        $deps = $this->seedPrerequisites($user);
        $booking = $this->createBooking($user, $deps);
        $passenger = $this->addPassenger($user, $deps, $booking);
        $ticket = $passenger->allIssuedTickets->first();
        $reissue = ReIssuedTicket::create([
            'user_id' => $user->id,
            'issued_ticket_id' => $ticket->id,
            're_issue_date' => '2020-03-01',
            'service_charge' => 200.00,
            'payment_by' => 'customer',
        ]);
        $reissue->created_at = now();
        $reissue->updated_at = now();
        $reissue->saveQuietly();
        [$from, $to] = $this->range();

        $this->assertEqualsWithDelta(200.0, $this->service->effectiveReIssueProfit($passenger->refresh(), $from, $to), 0.001);
    }

    public function test_reissue_cost_uses_created_at_and_is_subtracted(): void
    {
        $user = $this->setupUser();
        $deps = $this->seedPrerequisites($user);
        $booking = $this->createBooking($user, $deps);
        $passenger = $this->addPassenger($user, $deps, $booking);
        $ticket = $passenger->allIssuedTickets->first();
        $reissue = ReIssuedTicket::create([
            'user_id' => $user->id,
            'issued_ticket_id' => $ticket->id,
            're_issue_date' => '2020-03-01',
            'total_cost' => 500.00,
            'payment_by' => 'company',
        ]);
        $reissue->created_at = now();
        $reissue->updated_at = now();
        $reissue->saveQuietly();
        [$from, $to] = $this->range();

        $detailed = $this->service->calculateEffectiveDateProfitDetailed($passenger->refresh(), $from, $to);

        $this->assertEqualsWithDelta(500.0, $detailed['re_issue_cost'], 0.001);
        $this->assertEqualsWithDelta(-500.0, $detailed['total'], 0.001);
    }

    public function test_refund_profit_uses_created_at(): void
    {
        $user = $this->setupUser();
        $deps = $this->seedPrerequisites($user);
        $booking = $this->createBooking($user, $deps);
        $passenger = $this->addPassenger($user, $deps, $booking);
        $ticket = $passenger->allIssuedTickets->first();
        $refund = RefundedTicket::create([
            'user_id' => $user->id,
            'issued_ticket_id' => $ticket->id,
            'refund_date' => '2020-04-01',
            'service_charge' => 75.00,
        ]);
        $refund->created_at = now();
        $refund->updated_at = now();
        $refund->saveQuietly();
        [$from, $to] = $this->range();

        $this->assertEqualsWithDelta(75.0, $this->service->effectiveRefundProfit($passenger->refresh(), $from, $to), 0.001);
    }

    public function test_detailed_total_combines_all_components(): void
    {
        $user = $this->setupUser();
        $deps = $this->seedPrerequisites($user);
        $booking = $this->createBooking($user, $deps);
        $passenger = $this->addPassenger($user, $deps, $booking);
        IssuedTicket::create([
            'passenger_id' => $passenger->id,
            'booking_id' => $booking->id,
            'user_id' => $user->id,
            'ticket_fare_id' => $deps['fare']->id,
            'net_fare' => 24000.00,
            'issue_type' => 'additional',
            'status' => 'issued',
            'issued_date' => now()->toDateString(),
        ]);
        $ticket = $passenger->allIssuedTickets->first();
        $reissue = ReIssuedTicket::create([
            'user_id' => $user->id,
            'issued_ticket_id' => $ticket->id,
            're_issue_date' => '2020-03-01',
            'service_charge' => 200.00,
            'payment_by' => 'customer',
        ]);
        $reissue->created_at = now();
        $reissue->updated_at = now();
        $reissue->saveQuietly();
        $refund = RefundedTicket::create([
            'user_id' => $user->id,
            'issued_ticket_id' => $ticket->id,
            'refund_date' => '2020-04-01',
            'service_charge' => 75.00,
        ]);
        $refund->created_at = now();
        $refund->updated_at = now();
        $refund->saveQuietly();
        [$from, $to] = $this->range();

        $detailed = $this->service->calculateEffectiveDateProfitDetailed($passenger->refresh(), $from, $to);

        $this->assertEqualsWithDelta(6000.0, $detailed['additional_ticket_profit'], 0.001);
        $this->assertEqualsWithDelta(200.0, $detailed['re_issue_profit'], 0.001);
        $this->assertEqualsWithDelta(75.0, $detailed['refund_profit'], 0.001);
        $this->assertEqualsWithDelta(6275.0, $detailed['total'], 0.001);
    }

    public function test_summary_effective_includes_new_components(): void
    {
        $user = $this->setupUser();
        $deps = $this->seedPrerequisites($user);
        $booking = $this->createBooking($user, $deps);
        $passenger = $this->addPassenger($user, $deps, $booking);
        $passenger->allIssuedTickets()->update(['issued_date' => now()->toDateString()]);
        IssuedTicket::create([
            'passenger_id' => $passenger->id,
            'booking_id' => $booking->id,
            'user_id' => $user->id,
            'ticket_fare_id' => $deps['fare']->id,
            'net_fare' => 24000.00,
            'issue_type' => 'additional',
            'status' => 'issued',
            'issued_date' => now()->toDateString(),
        ]);
        $this->service->recalculateBookingProfit($booking->refresh());

        Auth::login($user);
        $response = $this->get(route('api.reports.profit-loss.summary', [
            'effective_date_from' => now()->subDays(30)->toDateString(),
            'effective_date_to' => now()->addDay()->toDateString(),
        ]));

        $response->assertOk();
        $summary = $response->json('passenger');
        // ticket 30000 - 27000 = 3000 + additional 30000 - 24000 = 6000
        $this->assertEqualsWithDelta(9000.0, $summary['total_profit'], 0.001);
        $this->assertEqualsWithDelta(6000.0, $summary['total_additional_ticket_profit'], 0.001);
    }
}
