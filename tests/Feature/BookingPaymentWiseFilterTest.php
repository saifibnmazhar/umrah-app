<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Branch;
use App\Models\CurrencyRate;
use App\Models\Customer;
use App\Models\District;
use App\Models\FingerprintCharge;
use App\Models\FlightDateGap;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\Role;
use App\Models\StayDurationLimit;
use App\Models\User;
use App\Models\VisaSellingPrice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingPaymentWiseFilterTest extends TestCase
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

        $this->district = District::create(['name' => 'Test District', 'division' => 'Test Division']);
        FlightDateGap::getOrCreate();
        StayDurationLimit::getOrCreate();
        CurrencyRate::create(['user_id' => $this->user->id, 'rate' => 25.0000]);

        $visaPrice = VisaSellingPrice::create(['user_id' => $this->user->id, 'selling_price' => 2000.00]);
        $this->package = Package::create([
            'package_name' => 'Booking Filter Package',
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

    private function createBookingWithBalance(float $balanceSar, string $invoiceId): Booking
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
            'invoice_id' => $invoiceId,
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
            'user_id' => $this->user->id,
            'total_amount' => 40000.00,
            'paid_amount' => 40000.00 - $balanceSar,
            'balance' => $balanceSar,
            'status' => $balanceSar > 0 ? 'pending' : 'paid',
        ]);

        return $booking;
    }

    private function invoiceIds($paginator): array
    {
        return collect($paginator->items())->pluck('invoice_id')->all();
    }

    public function test_payment_wise_clear_filters_booking_tab(): void
    {
        $clear = $this->createBookingWithBalance(0, 'INV-CLEAR-'.substr(uniqid(), -4));
        $due = $this->createBookingWithBalance(60, 'INV-DUE-'.substr(uniqid(), -4));

        $response = $this->actingAs($this->user)->get(route('bookings.index', [
            'tab' => 'booking',
            'payment_wise' => 'clear',
        ]));

        $response->assertOk();
        $ids = $this->invoiceIds($response->viewData('bookings'));

        $this->assertContains($clear->invoice_id, $ids);
        $this->assertNotContains($due->invoice_id, $ids);
    }

    public function test_payment_wise_due_below_1000_filters_booking_tab(): void
    {
        $below = $this->createBookingWithBalance(10, 'INV-BELOW-'.substr(uniqid(), -4));
        $above = $this->createBookingWithBalance(60, 'INV-ABOVE-'.substr(uniqid(), -4));
        $clear = $this->createBookingWithBalance(0, 'INV-CLEAR-'.substr(uniqid(), -4));

        $response = $this->actingAs($this->user)->get(route('bookings.index', [
            'tab' => 'booking',
            'payment_wise' => 'due_below_1000',
        ]));

        $response->assertOk();
        $ids = $this->invoiceIds($response->viewData('bookings'));

        $this->assertContains($below->invoice_id, $ids);
        $this->assertNotContains($above->invoice_id, $ids);
        $this->assertNotContains($clear->invoice_id, $ids);
    }
}
