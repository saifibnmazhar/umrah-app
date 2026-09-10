<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Branch;
use App\Models\CancelledBooking;
use App\Models\Customer;
use App\Models\District;
use App\Models\FingerprintCharge;
use App\Models\FlightDateGap;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\Payment;
use App\Models\Role;
use App\Models\StayDurationLimit;
use App\Models\TransactionType;
use App\Models\User;
use App\Models\VisaSellingPrice;
use App\Models\Voucher;
use App\Services\CostTrackingService;
use App\Services\CurrencyRateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Tests\TestCase;

class BookingCancelRefundPreviewCapTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private District $district;

    private Package $package;

    private FingerprintCharge $fingerprintCharge;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Test User',
            'email' => uniqid().'@example.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $this->user->roles()->attach(Role::create(['name' => 'Super Admin']));
        $this->actingAs($this->user);

        $this->district = District::create(['name' => 'Test District', 'division' => 'Test Division']);
        FlightDateGap::getOrCreate();
        StayDurationLimit::getOrCreate();

        $visaPrice = VisaSellingPrice::create(['user_id' => $this->user->id, 'selling_price' => 2000.00]);
        $this->package = Package::create([
            'package_name' => 'Cancel Cap Package',
            'visa_selling_price_id' => $visaPrice->id,
            'regular_price' => 40000.00,
            'service_charge' => 500.00,
            'is_active' => true,
            'is_double_ticket' => false,
        ]);
        $this->fingerprintCharge = FingerprintCharge::create([
            'district_id' => $this->district->id,
            'user_id' => $this->user->id,
            'fingerprint_charge' => 300.00,
        ]);
    }

    private function seedBooking(float $paid, float $priorPassengerRefund = 0): array
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
        $booking = Booking::create([
            'user_id' => $this->user->id,
            'customer_id' => $customer->id,
            'fingerprint_branch_id' => $branch->id,
            'district_id' => $this->district->id,
            'package_id' => $this->package->id,
            'fingerprint_charge_id' => $this->fingerprintCharge->id,
            'booking_branch_id' => $branch->id,
            'invoice_id' => 'INV-'.substr(uniqid(), -8),
            'date_gap_id' => FlightDateGap::getOrCreate()->id,
            'fingerprint_location' => 'home',
            'pax_qty' => 2,
            'discount_type' => 'fixed_amount',
            'discount_value' => 0,
            'discount_amount' => 0,
            'total_value' => 6000,
            'remarks' => '',
            'is_cancelled' => false,
        ]);
        $invoice = Invoice::create([
            'booking_id' => $booking->id,
            'branch_id' => $branch->id,
            'user_id' => $this->user->id,
            'total_amount' => 6000,
            'paid_amount' => $paid,
            'balance' => 1000,
        ]);

        $initialType = TransactionType::create(['name' => 'Initial Payment', 'type' => 'credit']);
        $refundType = TransactionType::create(['name' => 'Customer Refund', 'type' => 'debit']);

        $make = function ($type, $amount) use ($invoice, $booking, $branch) {
            $payment = Payment::create([
                'invoice_id' => $invoice->id,
                'booking_id' => $booking->id,
                'branch_id' => $branch->id,
                'user_id' => $this->user->id,
                'amount' => $amount,
                'bdt_amount' => 0,
                'payment_method' => 'cash',
                'payment_date' => now()->toDateString(),
            ]);
            Voucher::create([
                'voucher_id' => 'VCH-'.uniqid(),
                'invoice_id' => $invoice->id,
                'booking_id' => $booking->id,
                'payment_id' => $payment->id,
                'branch_id' => $branch->id,
                'user_id' => $this->user->id,
                'transaction_type_id' => $type->id,
                'amount' => $amount,
                'bdt_amount' => 0,
                'payment_method' => 'cash',
                'payment_date' => now()->toDateString(),
            ]);
        };

        $make($initialType, $paid);
        if ($priorPassengerRefund > 0) {
            $make($refundType, $priorPassengerRefund);
        }

        return compact('branch', 'booking', 'invoice');
    }

    private function mockCosts(float $totalCost): void
    {
        $this->mock(CostTrackingService::class, function ($mock) use ($totalCost) {
            $mock->shouldReceive('getBookingCostSummary')->andReturn([
                'fingerprint_cost' => 0,
                'visa_cost' => 0,
                'ticket_cost' => $totalCost,
                'total_cost' => $totalCost,
                'passengers' => collect([]),
            ]);
        });
    }

    public function test_preview_exposes_remaining_net_of_passenger_refunds(): void
    {
        ['booking' => $booking] = $this->seedBooking(5000, 1200);
        $this->mockCosts(1000);

        $response = $this->getJson(route('bookings.cancellation.initiate', $booking));

        $response->assertOk();
        $this->assertEqualsWithDelta(5000, (float) $response->json('total_paid'), 0.000001);
        $this->assertEqualsWithDelta(3800, (float) $response->json('refund_cap_remaining'), 0.000001);
    }

    public function test_initiate_stores_refund_minus_passenger_refunds(): void
    {
        // raw = 5000 - 1000 - 200 = 3800, remaining = 5000 - 4500 = 500
        ['booking' => $booking, 'branch' => $branch] = $this->seedBooking(5000, 4500);
        $this->mockCosts(1000);

        $response = $this->postJson(route('bookings.cancellation.store', $booking), [
            'cancellation_branch_id' => $branch->id,
            'service_charge_deduction' => 200,
        ]);

        $response->assertOk();
        $this->assertEqualsWithDelta(500, (float) $response->json('data.refund_amount'), 0.000001);
        $this->assertEqualsWithDelta(
            500,
            (float) CancelledBooking::where('booking_id', $booking->id)->value('refund_amount'),
            0.000001
        );
    }

    public function test_initiate_modal_preview_clamps_to_cap(): void
    {
        $emptyPaginator = new LengthAwarePaginator(collect([]), 0, 20);

        $html = view('bookings.index', [
            'tab' => 'bookings',
            'bookings' => $emptyPaginator,
            'passengers' => $emptyPaginator,
            'passengerStatuses' => collect([]),
            'visaAgents' => collect([]),
            'ticketAgents' => collect([]),
            'canEditVisa' => false,
            'canFilterByVisaAgent' => true,
            'canFilterByTicketAgent' => true,
            'currencyRateService' => app(CurrencyRateService::class),
            'bookingBranches' => collect([]),
            'selectedBranchId' => null,
            'totalBookingCount' => 0,
            'totalBookingPassengerCount' => 0,
            'branchCounts' => collect([]),
            'allBookingCount' => 0,
            'selectedFingerprintStatus' => null,
            'selectedVisaStatus' => null,
            'selectedTicketStatus' => null,
            'selectedVisaAgentId' => null,
            'selectedBookingDateFrom' => null,
            'selectedBookingDateTo' => null,
            'selectedFingerprintLocation' => null,
            'selectedBookingStatus' => null,
            'selectedPassengerStatus' => null,
            'selectedRouteDisplay' => null,
            'routesList' => collect([]),
            'selectedPackageId' => null,
            'selectedTicketAgentId' => null,
            'selectedActualFlightFrom' => null,
            'selectedActualFlightTo' => null,
            'selectedReturnDateFrom' => null,
            'selectedReturnDateTo' => null,
            'selectedStatusChangeAction' => null,
            'selectedStatusChangeFrom' => null,
            'selectedStatusChangeTo' => null,
            'selectedPaymentWise' => null,
            'statusChangeOptions' => collect([]),
            'fingerprintStatuses' => [],
            'visaStatuses' => [],
            'ticketStatuses' => [],
            'fingerprintLocations' => [],
            'totalPassengerCount' => 0,
            'totalPackageValue' => 0,
            'totalDue' => 0,
            'totalPackageBdt' => 0,
            'totalDueBdt' => 0,
            'reIssueReasons' => collect([]),
        ])->render();

        $this->assertStringContainsString('cancelCapRemaining', $html);
        $this->assertStringContainsString('Math.min(raw, Math.max(0, remaining))', $html);
    }
}
