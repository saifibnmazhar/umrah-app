<?php

namespace Tests\Unit;

use App\Concerns\HandlesBranchAccess;
use App\Models\Booking;
use App\Models\Branch;
use App\Models\CancelledBooking;
use App\Models\Customer;
use App\Models\District;
use App\Models\FingerprintCharge;
use App\Models\FlightDateGap;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\Role;
use App\Models\User;
use App\Models\VisaSellingPrice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class HandlesBranchAccessTest extends TestCase
{
    use RefreshDatabase;

    protected $controller;

    protected function setUp(): void
    {
        parent::setUp();

        $this->controller = new class
        {
            use HandlesBranchAccess;
        };
    }

    private function createAdmin(string $role, ?int $branchId = null): User
    {
        $user = User::create([
            'name' => $role,
            'email' => $role.'@test.com',
            'password' => Hash::make('password'),
            'branch_id' => $branchId,
        ]);
        $roleModel = Role::firstOrCreate(['name' => $role]);
        $user->roles()->attach($roleModel);

        return $user;
    }

    private function createBranch(): Branch
    {
        return Branch::create([
            'name' => 'Test Branch', 'address' => '', 'contacts' => '',
            'location' => 'KSA', 'branch_code' => 'B001', 'fingerprint_operation' => false,
        ]);
    }

    private function seedBookingDeps(?User $user = null): array
    {
        $district = District::create(['name' => 'D', 'division' => 'Div']);
        $customer = Customer::create([
            'name' => 'C', 'iqama_type' => 'none', 'passport_no' => 'P1',
            'mobile_no' => '050', 'address' => 'A',
        ]);
        $visaPrice = VisaSellingPrice::create(['user_id' => $user ? $user->id : 1, 'selling_price' => 2000]);
        $package = Package::create([
            'package_name' => 'P', 'visa_selling_price_id' => $visaPrice->id,
            'regular_price' => 35000, 'service_charge' => 1500, 'is_active' => true,
        ]);
        $flightDateGap = FlightDateGap::getOrCreate();
        $fingerprintCharge = FingerprintCharge::create([
            'district_id' => $district->id, 'user_id' => $user ? $user->id : 1, 'fingerprint_charge' => 50,
        ]);

        return compact('district', 'customer', 'package', 'flightDateGap', 'fingerprintCharge');
    }

    private function createBooking(User $user, Branch $branch, array $deps): Booking
    {
        return Booking::create([
            'user_id' => $user->id,
            'customer_id' => $deps['customer']->id,
            'booking_branch_id' => $branch->id,
            'fingerprint_branch_id' => $branch->id,
            'district_id' => $deps['district']->id,
            'package_id' => $deps['package']->id,
            'fingerprint_charge_id' => $deps['fingerprintCharge']->id,
            'invoice_id' => 'INV-'.uniqid(),
            'date_gap_id' => $deps['flightDateGap']->id,
            'fingerprint_location' => 'office',
            'pax_qty' => 1,
            'discount_type' => 'fixed_amount',
            'discount_value' => 0,
            'discount_amount' => 0,
            'is_cancelled' => false,
        ]);
    }

    private function createCancelledBooking(User $user, Branch $cancellationBranch): CancelledBooking
    {
        $deps = $this->seedBookingDeps($user);
        $booking = $this->createBooking($user, $cancellationBranch, $deps);
        $invoice = Invoice::create([
            'booking_id' => $booking->id, 'branch_id' => $cancellationBranch->id,
            'user_id' => $user->id, 'total_amount' => 0, 'paid_amount' => 0,
            'balance' => 0, 'status' => 'pending',
        ]);

        return CancelledBooking::create([
            'booking_id' => $booking->id,
            'invoice_id' => $invoice->id,
            'user_id' => $user->id,
            'total_paid' => 0, 'service_charge_deduction' => 0, 'refund_amount' => 0,
            'cancellation_branch_id' => $cancellationBranch->id,
            'status' => 'cancellation processing',
        ]);
    }

    // --- isAdmin tests ---

    public function test_is_admin_returns_true_for_super_admin(): void
    {
        $this->actingAs($this->createAdmin('Super Admin'));
        $this->assertTrue($this->controller->isAdmin());
    }

    public function test_is_admin_returns_true_for_co_admin(): void
    {
        $this->actingAs($this->createAdmin('Co Admin'));
        $this->assertTrue($this->controller->isAdmin());
    }

    public function test_is_admin_returns_false_for_regular_user(): void
    {
        $this->actingAs($this->createAdmin('Branch Staff'));
        $this->assertFalse($this->controller->isAdmin());
    }

    // --- isBranchScoped tests ---

    public function test_is_branch_scoped_returns_true_for_branch_manager(): void
    {
        $branch = $this->createBranch();
        $this->actingAs($this->createAdmin('Branch Manager', $branch->id));
        $this->assertTrue($this->controller->isBranchScoped());
    }

    // --- isGlobalNonAdmin tests ---

    public function test_is_global_non_admin_returns_true_for_user_without_branch_or_admin_role(): void
    {
        $this->actingAs($this->createAdmin('Branch Manager', null));
        $this->assertTrue($this->controller->isGlobalNonAdmin());
    }

    public function test_is_global_non_admin_returns_false_for_branch_user(): void
    {
        $branch = $this->createBranch();
        $this->actingAs($this->createAdmin('Branch Staff', $branch->id));
        $this->assertFalse($this->controller->isGlobalNonAdmin());
    }

    // --- hasAnyRole tests ---

    public function test_has_any_role_returns_true_when_user_has_one_of_roles(): void
    {
        $this->actingAs($this->createAdmin('Branch Manager'));
        $this->assertTrue($this->controller->hasAnyRole(['Super Admin', 'Branch Manager']));
    }

    public function test_has_any_role_returns_false_when_user_has_none(): void
    {
        $this->actingAs($this->createAdmin('Branch Manager'));
        $this->assertFalse($this->controller->hasAnyRole(['Super Admin', 'Co Admin']));
    }

    // --- canEdit / permission tests ---

    public function test_can_edit_visa_returns_true_for_visa_admin(): void
    {
        $this->actingAs($this->createAdmin('Visa Admin'));
        $this->assertTrue($this->controller->canEditVisa());
    }

    public function test_can_edit_visa_returns_false_for_branch_manager(): void
    {
        $this->actingAs($this->createAdmin('Branch Manager'));
        $this->assertFalse($this->controller->canEditVisa());
    }

    public function test_can_delete_booking_returns_true_only_for_super_admin(): void
    {
        $this->actingAs($this->createAdmin('Super Admin'));
        $this->assertTrue($this->controller->canDeleteBooking());

        $this->actingAs($this->createAdmin('Co Admin'));
        $this->assertFalse($this->controller->canDeleteBooking());
    }

    public function test_can_filter_by_agent_returns_true_for_ticket_admin(): void
    {
        $this->actingAs($this->createAdmin('Ticket Admin'));
        $this->assertTrue($this->controller->canFilterByAgent());
    }

    public function test_can_filter_by_agent_returns_false_for_branch_manager(): void
    {
        $this->actingAs($this->createAdmin('Branch Manager'));
        $this->assertFalse($this->controller->canFilterByAgent());
    }

    public function test_can_view_summary_cards_for_super_admin(): void
    {
        $this->actingAs($this->createAdmin('Super Admin'));
        $this->assertTrue($this->controller->canViewSummaryCards());
    }

    public function test_can_view_summary_cards_for_branch_manager(): void
    {
        $this->actingAs($this->createAdmin('Branch Manager'));
        $this->assertTrue($this->controller->canViewSummaryCards());
    }

    public function test_can_view_summary_cards_for_regular_staff(): void
    {
        $this->actingAs($this->createAdmin('Branch Staff'));
        $this->assertFalse($this->controller->canViewSummaryCards());
    }

    public function test_can_view_profit_cards_only_for_admin(): void
    {
        $this->actingAs($this->createAdmin('Super Admin'));
        $this->assertTrue($this->controller->canViewProfitCards());

        $this->actingAs($this->createAdmin('Co Admin'));
        $this->assertTrue($this->controller->canViewProfitCards());

        $this->actingAs($this->createAdmin('Auditor'));
        $this->assertTrue($this->controller->canViewProfitCards());

        $this->actingAs($this->createAdmin('Branch Manager'));
        $this->assertFalse($this->controller->canViewProfitCards());
    }

    // --- ensureBranchAccess tests ---

    public function test_ensure_branch_access_allows_admin(): void
    {
        $branch = $this->createBranch();
        $otherBranch = Branch::create(['name' => 'B2', 'address' => '', 'contacts' => '', 'location' => 'KSA', 'branch_code' => 'B002', 'fingerprint_operation' => false]);
        $user = $this->createAdmin('Super Admin');
        $this->actingAs($user);

        $deps = $this->seedBookingDeps($user);
        $booking = $this->createBooking($user, $branch, $deps);
        $booking->fingerprint_branch_id = $otherBranch->id;
        $booking->save();

        $this->controller->ensureBranchAccess($booking);
        $this->assertTrue(true);
    }

    public function test_ensure_branch_access_blocks_cross_branch_non_admin(): void
    {
        $this->expectException(HttpException::class);

        $branch1 = $this->createBranch();
        $branch2 = Branch::create(['name' => 'B2', 'address' => '', 'contacts' => '', 'location' => 'KSA', 'branch_code' => 'B002', 'fingerprint_operation' => false]);
        $user = $this->createAdmin('Branch Staff', $branch1->id);
        $this->actingAs($user);

        $deps = $this->seedBookingDeps($user);

        // Need to create a booking whose branches differ from user's
        $booking = $this->createBooking($user, $branch2, $deps);
        $this->controller->ensureBranchAccess($booking);
    }

    public function test_ensure_branch_access_allows_own_branch(): void
    {
        $branch = $this->createBranch();
        $user = $this->createAdmin('Branch Staff', $branch->id);
        $this->actingAs($user);

        $deps = $this->seedBookingDeps($user);
        $booking = $this->createBooking($user, $branch, $deps);

        $this->controller->ensureBranchAccess($booking);
        $this->assertTrue(true);
    }

    // --- ensureCancellationAccess tests ---

    public function test_ensure_cancellation_access_blocks_fingerprint_admin_without_branch(): void
    {
        $this->expectException(HttpException::class);

        $user = $this->createAdmin('Fingerprint Admin');
        $this->actingAs($user);

        $branch = $this->createBranch();
        $cancelledBooking = $this->createCancelledBooking($user, $branch);

        $this->controller->ensureCancellationAccess($cancelledBooking);
    }

    public function test_ensure_cancellation_access_allows_admin(): void
    {
        $user = $this->createAdmin('Super Admin');
        $this->actingAs($user);

        $branch = $this->createBranch();
        $cancelledBooking = $this->createCancelledBooking($user, $branch);

        $this->controller->ensureCancellationAccess($cancelledBooking);
        $this->assertTrue(true);
    }

    public function test_ensure_cancellation_access_blocks_cross_branch(): void
    {
        $this->expectException(HttpException::class);

        $branch1 = Branch::create(['name' => 'B1', 'address' => '', 'contacts' => '', 'location' => 'KSA', 'branch_code' => 'B001', 'fingerprint_operation' => false]);
        $branch2 = Branch::create(['name' => 'B2', 'address' => '', 'contacts' => '', 'location' => 'KSA', 'branch_code' => 'B002', 'fingerprint_operation' => false]);
        $user = $this->createAdmin('Fingerprint Admin', $branch1->id);
        $this->actingAs($user);

        $cancelledBooking = $this->createCancelledBooking($user, $branch2);

        $this->controller->ensureCancellationAccess($cancelledBooking);
    }

    // --- resolveBookingBranch tests ---

    public function test_resolve_booking_branch_uses_request_for_unbranched_admin(): void
    {
        $branch = $this->createBranch();
        $this->actingAs($this->createAdmin('Super Admin'));

        $request = Request::create('/', 'GET');
        $request->query->set('booking_branch_id', $branch->id);

        $resolved = $this->controller->resolveBookingBranch($request, false);
        $this->assertEquals($branch->id, $resolved);
    }
}
