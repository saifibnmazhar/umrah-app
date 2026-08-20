<?php

namespace Tests\Unit;

use App\Concerns\FiltersBookingStatus;
use App\Models\Booking;
use App\Models\Branch;
use App\Models\CancelledBooking;
use App\Models\Customer;
use App\Models\District;
use App\Models\FingerprintCharge;
use App\Models\FlightDateGap;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\User;
use App\Models\VisaSellingPrice;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FiltersBookingStatusTest extends TestCase
{
    use FiltersBookingStatus;
    use RefreshDatabase;

    private function seededDeps(): array
    {
        $user = User::firstOrCreate(
            ['email' => 'admin@test.com'],
            ['name' => 'Admin', 'password' => 'x']
        );
        $district = District::firstOrCreate(
            ['name' => 'D'],
            ['division' => 'Div']
        );
        $customer = Customer::firstOrCreate(
            ['passport_no' => 'P1'],
            ['name' => 'C', 'iqama_type' => 'none', 'mobile_no' => '050', 'address' => 'A']
        );
        $visaPrice = VisaSellingPrice::firstOrCreate(
            ['user_id' => $user->id],
            ['selling_price' => 2000]
        );
        $package = Package::firstOrCreate(
            ['package_name' => 'P'],
            ['visa_selling_price_id' => $visaPrice->id, 'regular_price' => 35000, 'service_charge' => 1500, 'is_active' => true]
        );
        $dateGap = FlightDateGap::getOrCreate();
        $fpCharge = FingerprintCharge::firstOrCreate(
            ['district_id' => $district->id, 'user_id' => $user->id],
            ['fingerprint_charge' => 50]
        );
        $branch = Branch::firstOrCreate(
            ['branch_code' => 'B001'],
            ['name' => 'Test Branch', 'address' => '', 'contacts' => '', 'location' => 'KSA', 'fingerprint_operation' => false]
        );

        return compact('user', 'district', 'customer', 'visaPrice', 'package', 'dateGap', 'fpCharge', 'branch');
    }

    private function booking(bool $isCancelled = false): Booking
    {
        static $counter = 1000;
        $deps = $this->seededDeps();
        $counter++;

        $booking = Booking::create([
            'user_id' => $deps['user']->id,
            'customer_id' => $deps['customer']->id,
            'booking_branch_id' => $deps['branch']->id,
            'fingerprint_branch_id' => $deps['branch']->id,
            'district_id' => $deps['district']->id,
            'package_id' => $deps['package']->id,
            'fingerprint_charge_id' => $deps['fpCharge']->id,
            'invoice_id' => 'INV-'.$counter,
            'date_gap_id' => $deps['dateGap']->id,
            'fingerprint_location' => 'office',
            'pax_qty' => 1,
            'discount_type' => 'fixed_amount',
            'discount_value' => 0,
            'discount_amount' => 0,
            'is_cancelled' => $isCancelled,
        ]);

        Invoice::create([
            'booking_id' => $booking->id, 'branch_id' => $deps['branch']->id,
            'user_id' => $deps['user']->id,
            'total_amount' => 0, 'paid_amount' => 0, 'balance' => 0, 'status' => 'pending',
        ]);

        return $booking;
    }

    private function cancelledBooking(Booking $booking, string $status): void
    {
        $invoice = Invoice::where('booking_id', $booking->id)->first();

        CancelledBooking::create([
            'booking_id' => $booking->id,
            'invoice_id' => $invoice->id,
            'user_id' => $booking->user_id,
            'total_paid' => 0,
            'service_charge_deduction' => 0,
            'refund_amount' => 0,
            'cancellation_branch_id' => $booking->booking_branch_id,
            'status' => $status,
        ]);
    }

    private function apply(Builder $query, ?string $status): Builder
    {
        return $this->scopeBookingStatus($query, $status);
    }

    public function test_active_excludes_cancelled(): void
    {
        $this->booking(false);
        $this->booking(true);

        $this->assertSame(1, $this->apply(Booking::query(), 'active')->count());
    }

    public function test_cancellation_processing(): void
    {
        $b1 = $this->booking(true);
        $this->booking(false);

        $this->cancelledBooking($b1, 'cancellation processing');

        $this->assertSame(1, $this->apply(Booking::query(), 'cancellation_processing')->count());
    }

    public function test_cancelled_includes_no_cancelled_booking_record(): void
    {
        $this->booking(true);
        $this->booking(true);

        $this->assertSame(2, $this->apply(Booking::query(), 'cancelled')->count());
    }

    public function test_cancelled_excludes_processing(): void
    {
        $b1 = $this->booking(true);
        $b2 = $this->booking(true);
        $this->booking(true);

        $this->cancelledBooking($b1, 'cancellation processing');
        $this->cancelledBooking($b2, 'cancelled');

        $this->assertSame(2, $this->apply(Booking::query(), 'cancelled')->count());
    }

    public function test_null_status_returns_all(): void
    {
        $this->booking(false);
        $this->booking(true);

        $this->assertSame(2, $this->apply(Booking::query(), null)->count());
    }

    public function test_unrecognized_status_returns_all(): void
    {
        $this->booking(false);
        $this->booking(true);

        $this->assertSame(2, $this->apply(Booking::query(), 'unknown')->count());
    }

    public function test_trait_has_both_scope_methods(): void
    {
        $this->assertTrue(method_exists($this, 'scopeBookingStatus'));
        $this->assertTrue(method_exists($this, 'scopeBookingStatusViaBooking'));
    }
}
