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
use App\Models\Package;
use App\Models\Payment;
use App\Models\Role;
use App\Models\Route;
use App\Models\StayDurationLimit;
use App\Models\TicketFare;
use App\Models\TransactionType;
use App\Models\TravelClass;
use App\Models\User;
use App\Models\VisaSellingPrice;
use App\Models\Voucher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BranchWiseReportServiceChargeDeductionTest extends TestCase
{
    use RefreshDatabase;

    private function setupUser(): User
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => uniqid().'@example.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $user->roles()->attach(Role::create(['name' => 'Super Admin']));

        return $user;
    }

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
        $rate = CurrencyRate::create(['user_id' => $user->id, 'rate' => 28.0000]);
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
            'package_name' => 'SC Test Package',
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
        TransactionType::create(['name' => 'Service Charge Deduction', 'type' => 'credit']);

        return compact('district', 'package', 'fingerprintCharge', 'rate');
    }

    private function createBooking(User $user, array $deps, Branch $branch): Booking
    {
        $customer = Customer::create([
            'name' => 'Customer '.uniqid(),
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
            'currency_rate_id' => $deps['rate']->id,
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

    private function createDeductionVoucher(User $user, Booking $booking, ?Branch $voucherBranch, float $amount): void
    {
        $type = TransactionType::where('name', 'Service Charge Deduction')->first();
        $payment = Payment::create([
            'invoice_id' => $booking->invoice->id,
            'booking_id' => $booking->id,
            'branch_id' => $voucherBranch?->id,
            'user_id' => $user->id,
            'currency_rate_id' => $booking->currency_rate_id,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'cash',
            'amount' => $amount,
            'bdt_amount' => 0,
        ]);
        Voucher::create([
            'voucher_id' => 'VCH-'.uniqid(),
            'invoice_id' => $booking->invoice->id,
            'booking_id' => $booking->id,
            'payment_id' => $payment->id,
            'branch_id' => $voucherBranch?->id,
            'user_id' => $user->id,
            'currency_rate_id' => $booking->currency_rate_id,
            'transaction_type_id' => $type->id,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'cash',
            'amount' => $amount,
            'bdt_amount' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeBranch(): Branch
    {
        return Branch::create([
            'name' => 'Branch '.uniqid(),
            'address' => 'Addr',
            'contacts' => '0123',
            'location' => 'KSA',
            'fingerprint_operation' => true,
            'branch_code' => 'BR'.substr(uniqid(), -6),
        ]);
    }

    public function test_branchwise_service_charge_card_sums_all_branches_without_filter(): void
    {
        $user = $this->setupUser();
        $deps = $this->seedPrerequisites($user);
        $branchA = $this->makeBranch();
        $branchB = $this->makeBranch();
        $bookingA = $this->createBooking($user, $deps, $branchA);
        $bookingB = $this->createBooking($user, $deps, $branchB);
        $this->createDeductionVoucher($user, $bookingA, $branchB, 200);
        $this->createDeductionVoucher($user, $bookingB, $branchB, 300);

        $this->actingAs($user)
            ->get(route('report.branch-wise', [
                'date_from' => now()->subDays(30)->toDateString(),
                'date_to' => now()->addDay()->toDateString(),
            ]))
            ->assertOk()
            ->assertSee('Total Service Charge Deduction')
            ->assertSee('data-sar="500.000000"', false)
            ->assertSee('data-bdt="14000.000000"', false);
    }

    public function test_branchwise_service_charge_card_filters_by_booking_branch(): void
    {
        $user = $this->setupUser();
        $deps = $this->seedPrerequisites($user);
        $branchA = $this->makeBranch();
        $branchB = $this->makeBranch();
        $bookingA = $this->createBooking($user, $deps, $branchA);
        $bookingB = $this->createBooking($user, $deps, $branchB);
        $this->createDeductionVoucher($user, $bookingA, $branchB, 200);
        $this->createDeductionVoucher($user, $bookingB, $branchB, 300);

        $this->actingAs($user)
            ->get(route('report.branch-wise', [
                'branch_id' => $branchA->id,
                'date_from' => now()->subDays(30)->toDateString(),
                'date_to' => now()->addDay()->toDateString(),
            ]))
            ->assertOk()
            ->assertSee('Total Service Charge Deduction')
            ->assertSee('data-sar="200.000000"', false)
            ->assertDontSee('data-sar="500.000000"', false);
    }
}
