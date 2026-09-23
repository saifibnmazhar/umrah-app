<?php

namespace Tests\Feature;

use App\Models\Bank;
use App\Models\Booking;
use App\Models\BookingUpdateLog;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\District;
use App\Models\FingerprintCharge;
use App\Models\FlightDateGap;
use App\Models\Package;
use App\Models\PassengerStatus;
use App\Models\Role;
use App\Models\StayDurationLimit;
use App\Models\TransactionType;
use App\Models\User;
use App\Models\VisaSellingPrice;
use App\Services\BookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BookingPackageNameSnapshotTest extends TestCase
{
    use RefreshDatabase;

    private function createSuperAdmin(): User
    {
        $branch = Branch::create([
            'name' => 'Main Branch',
            'address' => 'Addr',
            'contacts' => '0123456789',
            'location' => 'KSA',
            'fingerprint_operation' => true,
            'branch_code' => 'MAIN01',
        ]);

        $user = User::create([
            'name' => 'Super Admin',
            'email' => 'super-admin@example.com',
            'password' => bcrypt('password'),
            'is_active' => true,
            'branch_id' => $branch->id,
        ]);
        // NOTE: must be 'Super Admin' (not 'admin') — BookingController::isAdminRole()
        // only accepts Super Admin / Co Admin.
        $user->roles()->attach(Role::create(['name' => 'Super Admin']));

        return $user;
    }

    private function createBranchStaff(Branch $branch): User
    {
        $user = User::create([
            'name' => 'Branch Staff',
            'email' => 'branch-staff@example.com',
            'password' => bcrypt('password'),
            'is_active' => true,
            'branch_id' => $branch->id,
        ]);
        $user->roles()->attach(Role::create(['name' => 'Branch Staff']));

        return $user;
    }

    private function createPrerequisites(User $user): array
    {
        $district = District::create(['name' => 'D', 'division' => 'Div']);
        $branch = Branch::create([
            'name' => 'TB', 'address' => 'A', 'contacts' => '01',
            'location' => 'KSA', 'fingerprint_operation' => true, 'branch_code' => 'TB01',
        ]);
        $visaPrice = VisaSellingPrice::create(['user_id' => $user->id, 'selling_price' => 2000.00]);

        $package = Package::create([
            'package_name' => 'Pkg A',
            'visa_selling_price_id' => $visaPrice->id,
            'regular_price' => 35000.00,
            'offer_price' => 32000.00,
            'service_charge' => 1500.00,
            'is_active' => true,
            'is_double_ticket' => false,
        ]);

        $customer = Customer::create([
            'name' => 'Cust', 'passport_no' => 'T1', 'mobile_no' => '0501',
            'iqama_type' => 'none', 'address' => 'A',
        ]);

        $fpCharge = FingerprintCharge::create([
            'district_id' => $district->id, 'user_id' => $user->id, 'fingerprint_charge' => 50.00,
        ]);

        StayDurationLimit::getOrCreate();
        FlightDateGap::getOrCreate();
        TransactionType::create(['name' => 'Initial Payment', 'type' => 'debit']);
        PassengerStatus::firstOrCreate(['name' => 'Processing'], ['color' => '#000']);
        Bank::create(['name' => 'B', 'description' => 'd', 'currency' => 'SAR', 'location' => 'KSA']);

        return compact('district', 'branch', 'customer', 'package', 'fpCharge', 'visaPrice');
    }

    private function makePackage(array $deps, string $name): Package
    {
        return Package::create([
            'package_name' => $name,
            'visa_selling_price_id' => $deps['visaPrice']->id,
            'regular_price' => 36000.00,
            'offer_price' => 33000.00,
            'service_charge' => 1500.00,
            'is_active' => true,
            'is_double_ticket' => false,
        ]);
    }

    private function passengerPayload(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'passport_no' => 'PASS12345',
            'date_of_birth' => '1990-01-01',
            'gender' => 'male',
            'passport_expiry' => '2030-12-31',
            'mobile_no' => '0501234567',
            'service_required' => 'all',
            'stay_duration' => 14,
            'flight_date_from' => '2025-02-10',
            'flight_date_to' => '2025-02-20',
            'address' => 'Addr',
        ], $overrides);
    }

    private function storePayload(array $deps): array
    {
        return [
            'customer_id' => $deps['customer']->id,
            'district_id' => $deps['district']->id,
            'fingerprint_charge_id' => $deps['fpCharge']->id,
            'fingerprint_location' => 'office',
            'pax_qty' => 1,
            'package_id' => $deps['package']->id,
            'passengers' => [$this->passengerPayload()],
            'payment' => [
                'amount' => 100, 'bdt_amount' => 0, 'currency' => 'SAR',
                'payment_method' => 'cash', 'payment_date' => now()->toDateString(),
            ],
        ];
    }

    public function test_store_rejects_missing_package_id(): void
    {
        $this->withoutMiddleware();
        $user = $this->createSuperAdmin();
        $deps = $this->createPrerequisites($user);
        $this->actingAs($user);

        $payload = $this->storePayload($deps);
        unset($payload['package_id']);

        $response = $this->post(route('bookings.store'), $payload);

        $response->assertSessionHasErrors('package_id');
        $this->assertEquals(0, Booking::count());
    }

    public function test_store_snapshots_package_name(): void
    {
        $this->withoutMiddleware();
        $user = $this->createSuperAdmin();
        $deps = $this->createPrerequisites($user);
        $this->actingAs($user);

        $this->post(route('bookings.store'), $this->storePayload($deps))->assertRedirect();

        $this->assertEquals('Pkg A', Booking::first()->package_name);
    }

    public function test_rename_package_leaves_booking_snapshot_untouched(): void
    {
        $this->withoutMiddleware();
        $user = $this->createSuperAdmin();
        $deps = $this->createPrerequisites($user);
        $this->actingAs($user);

        $this->post(route('bookings.store'), $this->storePayload($deps))->assertRedirect();
        $booking = Booking::first();

        $deps['package']->update(['package_name' => 'Renamed Pkg']);

        $this->assertEquals('Pkg A', $booking->fresh()->package_name);
    }

    private function createBooking(array $deps, User $user): Booking
    {
        // Direct model create (no HTTP POST): keeps route-model binding working
        // for the subsequent PUT ($this->withoutMiddleware() disables
        // SubstituteBindings, so POST-created bookings can't be PUT-tested in
        // the same test). Mirrors BookingFingerprintLocationAccessTest.
        return Booking::create([
            'user_id' => $user->id,
            'customer_id' => $deps['customer']->id,
            'fingerprint_branch_id' => $user->branch_id,
            'district_id' => $deps['district']->id,
            'package_id' => $deps['package']->id,
            'package_name' => $deps['package']->package_name,
            'fingerprint_charge_id' => $deps['fpCharge']->id,
            'booking_branch_id' => $user->branch_id,
            'invoice_id' => 'INV-'.substr(uniqid(), -8),
            'date_gap_id' => FlightDateGap::getOrCreate()->id,
            'fingerprint_location' => 'office',
            'pax_qty' => 1,
            'discount_type' => 'fixed_amount',
            'discount_value' => 0,
            'discount_amount' => 0,
            'total_value' => 0,
            'remarks' => '',
            'is_cancelled' => false,
        ]);
    }

    public function test_admin_update_refreshes_snapshot_in_single_log_row(): void
    {
        $user = $this->createSuperAdmin();
        $deps = $this->createPrerequisites($user);
        $this->actingAs($user);

        $booking = $this->createBooking($deps, $user);

        $packageB = $this->makePackage($deps, 'Pkg B');
        BookingUpdateLog::where('booking_id', $booking->id)->delete();

        $this->put(route('bookings.update', $booking->id), [
            'package_id' => $packageB->id,
        ])->assertRedirect();

        $this->assertEquals('Pkg B', $booking->fresh()->package_name);

        $logs = BookingUpdateLog::where('booking_id', $booking->id)
            ->where('action', 'updated')
            ->get();
        $this->assertCount(1, $logs);
        $this->assertArrayHasKey('package_id', $logs->first()->new_values);
        $this->assertArrayHasKey('package_name', $logs->first()->new_values);
    }

    public function test_backfill_populates_legacy_null_snapshot(): void
    {
        $this->withoutMiddleware();
        $user = $this->createSuperAdmin();
        $deps = $this->createPrerequisites($user);
        $this->actingAs($user);

        $this->post(route('bookings.store'), $this->storePayload($deps))->assertRedirect();
        $booking = Booking::first();

        // Simulate a legacy row written before the snapshot column existed.
        DB::table('bookings')->where('id', $booking->id)->update(['package_name' => null]);
        $this->assertNull($booking->fresh()->package_name);

        // Re-run the migration's join-update statement verbatim.
        DB::transaction(function () {
            DB::table('bookings')
                ->join('packages', 'bookings.package_id', '=', 'packages.id')
                ->update(['bookings.package_name' => DB::raw('packages.package_name')]);
        });

        $this->assertEquals('Pkg A', $booking->fresh()->package_name);
    }

    public function test_booking_service_sets_snapshot(): void
    {
        $user = $this->createSuperAdmin();
        $deps = $this->createPrerequisites($user);
        $this->actingAs($user);

        $booking = app(BookingService::class)->processBookingWithPassengers([
            'user_id' => $user->id,
            'customer_id' => $deps['customer']->id,
            'district_id' => $deps['district']->id,
            'package_id' => $deps['package']->id,
            'fingerprint_charge_id' => $deps['fpCharge']->id,
            'fingerprint_branch_id' => $user->branch_id,
            'fingerprint_location' => 'office',
            // Full payload: the service passes passenger data straight through
            // to Passenger::create, so callers must supply all NOT NULL columns
            // (same requirement as the HTTP store endpoint).
            'passengers' => [$this->passengerPayload(['ticket_status' => 'pending'])],
        ]);

        $this->assertEquals('Pkg A', $booking->fresh()->package_name);
    }

    public function test_non_admin_cannot_change_package_or_snapshot(): void
    {
        $admin = $this->createSuperAdmin();
        $deps = $this->createPrerequisites($admin);
        $this->actingAs($admin);

        $booking = $this->createBooking($deps, $admin);

        $packageB = $this->makePackage($deps, 'Pkg B');

        // Same branch as the booking so ensureBranchAccess passes; the role
        // gate (non-admin package_id unset) is what must block the change.
        $staff = $this->createBranchStaff($admin->branch);
        $this->actingAs($staff);

        $this->put(route('bookings.update', $booking->id), [
            'package_id' => $packageB->id,
        ])->assertRedirect();

        $fresh = $booking->fresh();
        $this->assertEquals($deps['package']->id, $fresh->package_id);
        $this->assertEquals('Pkg A', $fresh->package_name);
    }
}
