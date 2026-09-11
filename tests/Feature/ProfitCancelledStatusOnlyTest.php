<?php

namespace Tests\Feature;

use App\Enums\CancelledBookingStatus;
use App\Models\Airline;
use App\Models\AirlineClass;
use App\Models\Booking;
use App\Models\Branch;
use App\Models\CancelledBooking;
use App\Models\CancelledPassenger;
use App\Models\CityCode;
use App\Models\CurrencyRate;
use App\Models\Customer;
use App\Models\District;
use App\Models\FingerprintCharge;
use App\Models\FlightDateGap;
use App\Models\Invoice;
use App\Models\IssuedTicket;
use App\Models\Package;
use App\Models\Passenger;
use App\Models\Role;
use App\Models\Route;
use App\Models\StayDurationLimit;
use App\Models\TicketFare;
use App\Models\TravelClass;
use App\Models\User;
use App\Models\VisaSellingPrice;
use App\Models\VisaSubmission;
use App\Services\PassengerCancellationService;
use App\Services\ProfitCalculationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class ProfitCancelledStatusOnlyTest extends TestCase
{
    use RefreshDatabase;

    private function setupUser(): User
    {
        $user = User::create([
            'name' => 'Profit Status Test User',
            'email' => uniqid().'@example.com',
            'password' => bcrypt('password'),
            'is_active' => true,
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

    private function createBookingWithInvoice(User $user, array $deps, string $invoiceNo, float $profit = 100.00): array
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
            'name' => 'Cust '.$invoiceNo,
            'passport_no' => 'P'.$invoiceNo,
            'iqama_type' => 'none',
            'mobile_no' => '0500000000',
            'address' => 'Addr',
        ]);
        $booking = Booking::create([
            'user_id' => $user->id,
            'customer_id' => $customer->id,
            'booking_branch_id' => $branch->id,
            'fingerprint_branch_id' => $branch->id,
            'district_id' => $deps['district']->id,
            'package_id' => $deps['package']->id,
            'fingerprint_charge_id' => $deps['fingerprintCharge']->id,
            'invoice_id' => $invoiceNo,
            'date_gap_id' => FlightDateGap::getOrCreate()->id,
            'fingerprint_location' => 'office',
            'pax_qty' => 1,
            'discount_type' => 'fixed_amount',
            'discount_value' => 0,
            'discount_amount' => 0,
            'total_value' => 40000.00,
            'remarks' => '',
            'is_cancelled' => false,
            'profit' => $profit,
        ]);
        $invoice = Invoice::create([
            'booking_id' => $booking->id,
            'branch_id' => $branch->id,
            'user_id' => $user->id,
            'total_amount' => 40000.00,
            'paid_amount' => 0,
            'balance' => 40000.00,
            'status' => 'pending',
        ]);
        $passenger = Passenger::create([
            'booking_id' => $booking->id,
            'first_name' => 'Pax'.$invoiceNo,
            'last_name' => 'Test',
            'passport_no' => 'PP'.$invoiceNo,
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
            'profit' => 50.00,
        ]);

        return compact('branch', 'booking', 'invoice', 'passenger');
    }

    public function test_processing_booking_included_but_cancelled_booking_excluded(): void
    {
        $user = $this->setupUser();
        $deps = $this->seedPrerequisites($user);
        $processing = $this->createBookingWithInvoice($user, $deps, 'INV-PROC', 100.00);
        $cancelled = $this->createBookingWithInvoice($user, $deps, 'INV-CANC', 200.00);

        CancelledBooking::create([
            'booking_id' => $processing['booking']->id,
            'invoice_id' => $processing['invoice']->id,
            'user_id' => $user->id,
            'total_paid' => 0,
            'cancellation_branch_id' => $processing['branch']->id,
            'status' => CancelledBookingStatus::PROCESSING,
        ]);
        CancelledBooking::create([
            'booking_id' => $cancelled['booking']->id,
            'invoice_id' => $cancelled['invoice']->id,
            'user_id' => $user->id,
            'total_paid' => 0,
            'cancellation_branch_id' => $cancelled['branch']->id,
            'status' => CancelledBookingStatus::CANCELLED,
        ]);
        Auth::login($user);

        $response = $this->get(route('api.reports.profit-loss.summary'));
        $response->assertOk();
        $this->assertEquals(1, $response->json('customer.count'));
        $this->assertEqualsWithDelta(100.00, (float) $response->json('customer.total_profit'), 0.001);

        $data = $this->get(route('api.reports.profit-loss', ['tab' => 'customer']));
        $data->assertOk();
        $this->assertEquals(1, $data->json('total'));
        $this->assertEquals('INV-PROC', $data->json('data.0.invoice_id'));
    }

    public function test_processing_passenger_included_but_cancelled_passenger_excluded(): void
    {
        $user = $this->setupUser();
        $deps = $this->seedPrerequisites($user);
        $ctx = $this->createBookingWithInvoice($user, $deps, 'INV-MIX', 300.00);
        $hold = Passenger::create([
            'booking_id' => $ctx['booking']->id,
            'first_name' => 'Hold',
            'last_name' => 'Pax',
            'passport_no' => 'PPHOLD'.substr(uniqid(), -5),
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
            'profit' => 60.00,
            'is_cancelled' => true,
        ]);
        $gone = Passenger::create([
            'booking_id' => $ctx['booking']->id,
            'first_name' => 'Gone',
            'last_name' => 'Pax',
            'passport_no' => 'PPGONE'.substr(uniqid(), -5),
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
            'profit' => 70.00,
            'is_cancelled' => true,
        ]);
        CancelledPassenger::create([
            'booking_id' => $ctx['booking']->id,
            'passenger_id' => $hold->id,
            'invoice_id' => $ctx['invoice']->id,
            'user_id' => $user->id,
            'package_value' => 25000.00,
            'cancellation_branch_id' => $ctx['branch']->id,
            'status' => CancelledBookingStatus::PROCESSING,
        ]);
        CancelledPassenger::create([
            'booking_id' => $ctx['booking']->id,
            'passenger_id' => $gone->id,
            'invoice_id' => $ctx['invoice']->id,
            'user_id' => $user->id,
            'package_value' => 25000.00,
            'cancellation_branch_id' => $ctx['branch']->id,
            'status' => CancelledBookingStatus::CANCELLED,
        ]);
        Auth::login($user);

        $data = $this->get(route('api.reports.profit-loss', ['tab' => 'passenger']));
        $data->assertOk();
        $names = collect($data->json('data'))->pluck('passenger_name')->all();
        $this->assertContains('Hold Pax', $names);
        $this->assertNotContains('Gone Pax', $names);
    }

    public function test_recalculate_keeps_hold_passenger_profit(): void
    {
        $user = $this->setupUser();
        $deps = $this->seedPrerequisites($user);
        $ctx = $this->createBookingWithInvoice($user, $deps, 'INV-RECALC', 0);
        $hold = Passenger::create([
            'booking_id' => $ctx['booking']->id,
            'first_name' => 'Hold',
            'last_name' => 'Recalc',
            'passport_no' => 'PPHR'.substr(uniqid(), -5),
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
            'profit' => 60.00,
            'is_cancelled' => true,
        ]);
        VisaSubmission::create([
            'passenger_id' => $hold->id,
            'visa_selling_price_id' => $deps['visaPrice']->id,
            'agent_commission' => 100.00,
            'net_visa_cost' => 1000.00,
            'additional_cost' => 50.00,
            'status' => 'issued',
            'is_cancelled' => false,
        ]);
        IssuedTicket::create([
            'passenger_id' => $hold->id,
            'booking_id' => $ctx['booking']->id,
            'user_id' => $user->id,
            'ticket_fare_id' => $deps['fare']->id,
            'selling_fare' => 28000.00,
            'net_fare' => 27000.00,
            'issue_type' => 'regular',
            'status' => 'issued',
            'issued_date' => now(),
        ]);
        CancelledPassenger::create([
            'booking_id' => $ctx['booking']->id,
            'passenger_id' => $hold->id,
            'invoice_id' => $ctx['invoice']->id,
            'user_id' => $user->id,
            'package_value' => 25000.00,
            'cancellation_branch_id' => $ctx['branch']->id,
            'status' => CancelledBookingStatus::PROCESSING,
        ]);

        app(ProfitCalculationService::class)->recalculateBookingProfit($ctx['booking']->refresh());

        $this->assertGreaterThan(0, (float) $hold->refresh()->profit);
    }

    public function test_confirm_cancellation_recomputes_booking_profit_column(): void
    {
        $user = $this->setupUser();
        $this->actingAs($user);
        $deps = $this->seedPrerequisites($user);
        $ctx = $this->createBookingWithInvoice($user, $deps, 'INV-CONFIRM', 0);
        $booking = $ctx['booking'];

        foreach ([$ctx['passenger']->id, null] as $index => $existingId) {
            if ($existingId !== null) {
                $passenger = Passenger::find($existingId);
            } else {
                $passenger = Passenger::create([
                    'booking_id' => $booking->id,
                    'first_name' => 'Second',
                    'last_name' => 'Pax',
                    'passport_no' => 'PPCONF'.substr(uniqid(), -5),
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
            }
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
        }

        $booking->update(['pax_qty' => 2]);
        app(ProfitCalculationService::class)->recalculateBookingProfit($booking->refresh());
        $profitBefore = (float) $booking->refresh()->profit;
        $this->assertGreaterThan(0, $profitBefore);

        $victim = $booking->refresh()->passengers()->orderBy('id')->first();
        $victimProfit = (float) $victim->profit;

        $service = app(PassengerCancellationService::class);
        $cancelled = $service->initiateCancellation($victim, [
            'cancellation_branch_id' => $ctx['branch']->id,
        ]);
        $service->confirmCancellation($cancelled, [
            'balance_adjusted_amount' => 0,
            'payment_method' => 'cash',
        ]);

        $bookingProfit = (float) $booking->refresh()->profit;
        $breakdownTotal = (float) app(ProfitCalculationService::class)->getCustomerProfitBreakdown($booking->refresh())['total'];

        $this->assertEqualsWithDelta(0.0, (float) $victim->refresh()->profit, 0.001);
        $this->assertEqualsWithDelta($profitBefore - $victimProfit, $bookingProfit, 0.001);
        $this->assertEqualsWithDelta($breakdownTotal, $bookingProfit, 0.001);
    }
}
