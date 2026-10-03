<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingUpdateLog;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\District;
use App\Models\FingerprintCharge;
use App\Models\FlightDateGap;
use App\Models\Package;
use App\Models\PackageUpdateLog;
use App\Models\Passenger;
use App\Models\Role;
use App\Models\User;
use App\Models\VisaSellingPrice;
use App\Models\VisaSubmission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BackfillVisaSubmissionPricesTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private array $deps = [];

    protected function setUp(): void
    {
        parent::setUp();

        $branch = Branch::create([
            'name' => 'Main Branch',
            'address' => 'Addr',
            'contacts' => '0123456789',
            'location' => 'KSA',
            'fingerprint_operation' => true,
            'branch_code' => 'MAIN01',
        ]);

        $this->user = User::create([
            'name' => 'Admin',
            'email' => uniqid().'@example.com',
            'password' => bcrypt('password'),
            'is_active' => true,
            'branch_id' => $branch->id,
        ]);
        $this->user->roles()->attach(Role::create(['name' => 'Super Admin']));

        $district = District::create(['name' => 'D', 'division' => 'Div']);
        $customer = Customer::create([
            'name' => 'Cust', 'passport_no' => 'T1', 'mobile_no' => '0501',
            'iqama_type' => 'none', 'address' => 'A',
        ]);
        $fpCharge = FingerprintCharge::create([
            'district_id' => $district->id, 'user_id' => $this->user->id, 'fingerprint_charge' => 50.00,
        ]);
        FlightDateGap::getOrCreate();

        $priceA = VisaSellingPrice::create(['user_id' => $this->user->id, 'selling_price' => 2000.00]);
        $priceB = VisaSellingPrice::create(['user_id' => $this->user->id, 'selling_price' => 2500.00]);
        $priceC = VisaSellingPrice::create(['user_id' => $this->user->id, 'selling_price' => 3000.00]);

        $packageA = $this->makePackage('Pkg A', $priceA->id);
        $packageB = $this->makePackage('Pkg B', $priceB->id);

        $this->deps = compact('branch', 'district', 'customer', 'fpCharge', 'priceA', 'priceB', 'priceC', 'packageA', 'packageB');
    }

    private function makePackage(string $name, int $visaPriceId): Package
    {
        return Package::create([
            'package_name' => $name,
            'visa_selling_price_id' => $visaPriceId,
            'regular_price' => 35000.00,
            'offer_price' => 32000.00,
            'service_charge' => 1500.00,
            'is_active' => true,
            'is_double_ticket' => false,
        ]);
    }

    private function createBooking(Package $package): Booking
    {
        return Booking::create([
            'user_id' => $this->user->id,
            'customer_id' => $this->deps['customer']->id,
            'fingerprint_branch_id' => $this->deps['branch']->id,
            'district_id' => $this->deps['district']->id,
            'package_id' => $package->id,
            'package_name' => $package->package_name,
            'fingerprint_charge_id' => $this->deps['fpCharge']->id,
            'booking_branch_id' => $this->deps['branch']->id,
            'invoice_id' => 'INV-'.substr(uniqid(), -8),
            'date_gap_id' => FlightDateGap::getOrCreate()->id,
            'fingerprint_location' => 'office',
            'pax_qty' => 1,
            'discount_type' => 'fixed_amount',
            'discount_value' => 0,
            'discount_amount' => 0,
            'total_value' => 40000.00,
            'remarks' => '',
            'is_cancelled' => false,
        ]);
    }

    private function createPassenger(Booking $booking): Passenger
    {
        return Passenger::create([
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
            'package_value' => 40000.00,
        ]);
    }

    private function createVisaSubmission(Passenger $passenger, VisaSellingPrice $price): VisaSubmission
    {
        return VisaSubmission::create([
            'passenger_id' => $passenger->id,
            'visa_selling_price_id' => $price->id,
            'status' => 'pending',
        ]);
    }

    private function logPackageSwap(Booking $booking, int $oldPackageId, int $newPackageId, \DateTimeInterface $at): void
    {
        $log = BookingUpdateLog::create([
            'booking_id' => $booking->id,
            'user_id' => $this->user->id,
            'booking_invoice_id' => $booking->invoice_id,
            'action' => 'updated',
            'old_values' => ['package_id' => $oldPackageId],
            'new_values' => ['package_id' => $newPackageId],
        ]);

        DB::table('booking_update_logs')->where('id', $log->id)->update(['created_at' => $at]);
    }

    private function logPackagePriceChange(Package $package, int $oldPriceId, int $newPriceId, \DateTimeInterface $at): void
    {
        $log = PackageUpdateLog::create([
            'package_id' => $package->id,
            'user_id' => $this->user->id,
            'action' => 'updated',
            'old_values' => ['visa_selling_price_id' => $oldPriceId],
            'new_values' => ['visa_selling_price_id' => $newPriceId],
        ]);

        DB::table('package_update_logs')->where('id', $log->id)->update(['created_at' => $at]);
    }

    public function test_keeps_submission_when_booking_package_never_changed(): void
    {
        $booking = $this->createBooking($this->deps['packageA']);
        $passenger = $this->createPassenger($booking);
        $visa = $this->createVisaSubmission($passenger, $this->deps['priceB']);

        $this->artisan('umrah:backfill-visa-submission-prices')
            ->expectsOutputToContain('Kept: 1')
            ->assertExitCode(0);

        $this->assertEquals(
            $this->deps['priceB']->id,
            $visa->fresh()->visa_selling_price_id
        );
    }

    public function test_syncs_to_current_price_when_package_changed_but_price_unchanged(): void
    {
        // Booking now on package B; submission still holds package A's price (stale swap).
        $booking = $this->createBooking($this->deps['packageB']);
        $passenger = $this->createPassenger($booking);
        $visa = $this->createVisaSubmission($passenger, $this->deps['priceA']);

        $this->logPackageSwap($booking, $this->deps['packageA']->id, $this->deps['packageB']->id, now());

        $this->artisan('umrah:backfill-visa-submission-prices')
            ->expectsOutputToContain('Applied: 1')
            ->assertExitCode(0);

        $this->assertEquals(
            $this->deps['priceB']->id,
            $visa->fresh()->visa_selling_price_id
        );
    }

    public function test_uses_historic_price_when_package_price_changed_after_swap(): void
    {
        // Booking swapped to package B (at T); package B later bumped priceB -> priceC.
        $booking = $this->createBooking($this->deps['packageB']);
        $passenger = $this->createPassenger($booking);
        $visa = $this->createVisaSubmission($passenger, $this->deps['priceA']);

        $swapAt = now()->subDays(5);
        $this->logPackageSwap($booking, $this->deps['packageA']->id, $this->deps['packageB']->id, $swapAt);

        $this->deps['packageB']->update(['visa_selling_price_id' => $this->deps['priceC']->id]);
        $this->logPackagePriceChange(
            $this->deps['packageB'],
            $this->deps['priceB']->id,
            $this->deps['priceC']->id,
            now()
        );

        $this->artisan('umrah:backfill-visa-submission-prices')
            ->expectsOutputToContain('Applied: 1')
            ->assertExitCode(0);

        // Historic price at swap time was priceB.
        $this->assertEquals(
            $this->deps['priceB']->id,
            $visa->fresh()->visa_selling_price_id
        );
    }

    public function test_keeps_submission_already_on_historic_price(): void
    {
        // Same bump scenario, but the submission already holds the price from swap time.
        $booking = $this->createBooking($this->deps['packageB']);
        $passenger = $this->createPassenger($booking);
        $visa = $this->createVisaSubmission($passenger, $this->deps['priceB']);

        $swapAt = now()->subDays(5);
        $this->logPackageSwap($booking, $this->deps['packageA']->id, $this->deps['packageB']->id, $swapAt);

        $this->deps['packageB']->update(['visa_selling_price_id' => $this->deps['priceC']->id]);
        $this->logPackagePriceChange(
            $this->deps['packageB'],
            $this->deps['priceB']->id,
            $this->deps['priceC']->id,
            now()
        );

        $this->artisan('umrah:backfill-visa-submission-prices')
            ->expectsOutputToContain('Kept: 1')
            ->assertExitCode(0);

        $this->assertEquals(
            $this->deps['priceB']->id,
            $visa->fresh()->visa_selling_price_id
        );
    }

    public function test_syncs_when_package_updated_after_swap_without_logs(): void
    {
        $booking = $this->createBooking($this->deps['packageB']);
        $passenger = $this->createPassenger($booking);
        $visa = $this->createVisaSubmission($passenger, $this->deps['priceA']);

        $this->logPackageSwap($booking, $this->deps['packageA']->id, $this->deps['packageB']->id, now()->subDays(5));

        // updated_at bumped after the swap but no price-change row in
        // package_update_logs -> price never changed -> apply current price.
        DB::table('packages')->where('id', $this->deps['packageB']->id)->update(['updated_at' => now()]);

        $this->artisan('umrah:backfill-visa-submission-prices')
            ->expectsOutputToContain('Applied: 1')
            ->assertExitCode(0);

        $this->assertEquals(
            $this->deps['priceB']->id,
            $visa->fresh()->visa_selling_price_id
        );
    }

    public function test_syncs_when_only_non_price_fields_logged_after_swap(): void
    {
        $booking = $this->createBooking($this->deps['packageB']);
        $passenger = $this->createPassenger($booking);
        $visa = $this->createVisaSubmission($passenger, $this->deps['priceA']);

        $this->logPackageSwap($booking, $this->deps['packageA']->id, $this->deps['packageB']->id, now()->subDays(5));

        // A logged edit that does NOT touch visa_selling_price_id (fare cascade)
        // must not be treated as a price update.
        $log = PackageUpdateLog::create([
            'package_id' => $this->deps['packageB']->id,
            'user_id' => $this->user->id,
            'action' => 'fare_updated',
            'old_values' => ['regular_price' => 35000.00],
            'new_values' => ['regular_price' => 36000.00],
        ]);
        DB::table('package_update_logs')->where('id', $log->id)->update(['created_at' => now()]);

        $this->artisan('umrah:backfill-visa-submission-prices')
            ->expectsOutputToContain('Applied: 1')
            ->assertExitCode(0);

        $this->assertEquals(
            $this->deps['priceB']->id,
            $visa->fresh()->visa_selling_price_id
        );
    }

    public function test_skips_when_swap_happened_but_swap_log_missing(): void
    {
        // Created snapshot shows package A, booking now on package B, no swap log row.
        $booking = $this->createBooking($this->deps['packageB']);
        $passenger = $this->createPassenger($booking);
        $visa = $this->createVisaSubmission($passenger, $this->deps['priceA']);

        BookingUpdateLog::create([
            'booking_id' => $booking->id,
            'user_id' => $this->user->id,
            'booking_invoice_id' => $booking->invoice_id,
            'action' => 'created',
            'old_values' => null,
            'new_values' => ['package_id' => $this->deps['packageA']->id],
        ]);

        $this->artisan('umrah:backfill-visa-submission-prices')
            ->expectsOutputToContain('Skipped: 1')
            ->assertExitCode(0);

        $this->assertEquals(
            $this->deps['priceA']->id,
            $visa->fresh()->visa_selling_price_id
        );
    }

    public function test_dry_run_does_not_write(): void
    {
        $booking = $this->createBooking($this->deps['packageB']);
        $passenger = $this->createPassenger($booking);
        $visa = $this->createVisaSubmission($passenger, $this->deps['priceA']);

        $this->logPackageSwap($booking, $this->deps['packageA']->id, $this->deps['packageB']->id, now());

        $this->artisan('umrah:backfill-visa-submission-prices', ['--dry-run' => true])
            ->expectsOutputToContain('Applied: 1')
            ->assertExitCode(0);

        $this->assertEquals(
            $this->deps['priceA']->id,
            $visa->fresh()->visa_selling_price_id
        );
    }
}
