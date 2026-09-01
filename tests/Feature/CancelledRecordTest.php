<?php

namespace Tests\Feature;

use App\Enums\CancelledBookingStatus;
use App\Enums\PaymentMethod;
use App\Models\Booking;
use App\Models\Branch;
use App\Models\CancelledBooking;
use App\Models\CancelledPassenger;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Passenger;
use App\Models\Payment;
use App\Models\Role;
use App\Models\TransactionType;
use App\Models\User;
use App\Models\Voucher;
use App\Services\CancellationService;
use App\Services\PassengerCancellationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CancelledRecordTest extends TestCase
{
    use RefreshDatabase;

    // Avoid starting a transaction — DDL (Schema::create) in setUp() on MySQL
    // implicitly commits, which breaks nested DB::transaction() calls.
    protected function beginDatabaseTransaction(): void
    {
        // no-op
    }

    public static function tearDownAfterClass(): void
    {
        try {
            Artisan::call('migrate:fresh');
        } catch (\Throwable $e) {
            RefreshDatabaseState::$migrated = false;
        }
    }

    protected function setUp(): void
    {
        parent::setUp();

        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('cancelled_passengers');
        Schema::dropIfExists('cancelled_bookings');
        Schema::dropIfExists('vouchers');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('transaction_types');
        Schema::dropIfExists('passengers');
        Schema::dropIfExists('passenger_statuses');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('bookings');
        Schema::dropIfExists('user_roles');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('currency_rates');
        Schema::dropIfExists('stay_duration_limits');
        Schema::dropIfExists('customers');
        Schema::dropIfExists('branches');
        Schema::dropIfExists('users');

        Schema::create('users', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('branches', function ($table) {
            $table->id();
            $table->string('name');
            $table->boolean('fingerprint_operation')->default(false);
            $table->timestamps();
        });

        Schema::create('customers', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('mobile_no')->nullable();
            $table->timestamps();
        });

        Schema::create('currency_rates', function ($table) {
            $table->id();
            $table->decimal('rate', 10, 4)->default(1);
            $table->timestamps();
        });

        Schema::create('stay_duration_limits', function ($table) {
            $table->id();
            $table->integer('min_days')->nullable()->default(1);
            $table->integer('max_days')->nullable()->default(85);
            $table->timestamps();
        });

        Schema::create('roles', function ($table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        Schema::create('user_roles', function ($table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('bookings', function ($table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->foreignId('booking_branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('fingerprint_branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('invoice_id')->nullable();
            $table->decimal('pax_qty', 10, 0)->default(1);
            $table->decimal('total_value', 14, 6)->default(0);
            $table->decimal('discount_amount', 14, 6)->default(0);
            $table->boolean('is_cancelled')->default(false);
            $table->timestamps();
        });

        Schema::create('invoices', function ($table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings')->restrictOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->decimal('total_amount', 14, 6)->default(0);
            $table->decimal('paid_amount', 14, 6)->default(0);
            $table->decimal('balance', 14, 6)->default(0);
            $table->string('status')->default('pending');
            $table->timestamps();
        });

        Schema::create('passenger_statuses', function ($table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        Schema::create('passengers', function ($table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings')->restrictOnDelete();
            $table->foreignId('passenger_status_id')->nullable()->constrained('passenger_statuses')->nullOnDelete();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('passport_no')->nullable();
            $table->string('mobile_no')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('gender')->nullable();
            $table->enum('passenger_type', ['adult', 'child', 'infant'])->default('adult');
            $table->decimal('package_value', 14, 6)->default(0);
            $table->boolean('is_cancelled')->default(false);
            $table->timestamps();
        });

        Schema::create('payments', function ($table) {
            $table->id();
            $table->string('invoice_id')->nullable();
            $table->foreignId('booking_id')->nullable()->constrained('bookings')->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('currency_rate_id')->nullable();
            $table->unsignedBigInteger('cancelled_booking_id')->nullable();
            $table->date('payment_date')->nullable();
            $table->enum('payment_method', ['cash', 'bank']);
            $table->string('transaction_id')->nullable();
            $table->decimal('amount', 14, 6)->default(0);
            $table->decimal('bdt_amount', 14, 6)->default(0);
            $table->string('remarks')->nullable();
            $table->timestamps();
        });

        Schema::create('vouchers', function ($table) {
            $table->id();
            $table->string('voucher_id')->unique();
            $table->unsignedBigInteger('invoice_id')->nullable();
            $table->foreignId('payment_id')->nullable()->constrained('payments')->restrictOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained('bookings')->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('currency_rate_id')->nullable();
            $table->unsignedBigInteger('transaction_type_id')->nullable();
            $table->unsignedBigInteger('cancelled_booking_id')->nullable();
            $table->date('payment_date')->nullable();
            $table->enum('payment_method', ['cash', 'bank']);
            $table->string('transaction_id')->nullable();
            $table->decimal('amount', 14, 6)->default(0);
            $table->decimal('bdt_amount', 14, 6)->default(0);
            $table->string('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('cancelled_bookings', function ($table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings')->restrictOnDelete();
            $table->string('invoice_id')->nullable();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->decimal('total_paid', 14, 6)->default(0);
            $table->decimal('service_charge_deduction', 14, 6)->default(0);
            $table->decimal('refund_amount', 14, 6)->default(0);
            $table->foreignId('cancellation_branch_id')->constrained('branches')->restrictOnDelete();
            $table->enum('status', ['cancellation processing', 'cancelled'])->default('cancellation processing');
            $table->foreignId('deduction_payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->foreignId('deduction_voucher_id')->nullable()->constrained('vouchers')->nullOnDelete();
            $table->foreignId('refund_payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->foreignId('refund_voucher_id')->nullable()->constrained('vouchers')->nullOnDelete();
            $table->foreignId('confirmed_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reverted_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('cancelled_passengers', function ($table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings')->restrictOnDelete();
            $table->foreignId('passenger_id')->constrained('passengers')->restrictOnDelete();
            $table->foreignId('invoice_id')->constrained('invoices')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->decimal('package_value', 14, 6)->default(0);
            $table->decimal('additional_ticket_value', 14, 6)->default(0);
            $table->decimal('total_passenger_due', 14, 6)->default(0);
            $table->decimal('visa_cost', 14, 6)->default(0);
            $table->decimal('ticket_cost', 14, 6)->default(0);
            $table->decimal('service_charge_deduction', 14, 6)->nullable();
            $table->decimal('refundable_amount', 14, 6)->default(0);
            $table->decimal('balance_adjusted_amount', 14, 6)->default(0);
            $table->decimal('refund_amount', 14, 6)->default(0);
            $table->foreignId('cancellation_branch_id')->constrained('branches')->restrictOnDelete();
            $table->enum('status', ['cancellation processing', 'cancelled'])->default('cancellation processing');
            $table->foreignId('deduction_payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->foreignId('deduction_voucher_id')->nullable()->constrained('vouchers')->nullOnDelete();
            $table->foreignId('refund_payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->foreignId('refund_voucher_id')->nullable()->constrained('vouchers')->nullOnDelete();
            $table->foreignId('adjustment_payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->foreignId('adjustment_voucher_id')->nullable()->constrained('vouchers')->nullOnDelete();
            $table->foreignId('confirmed_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reverted_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('transaction_types', function ($table) {
            $table->id();
            $table->string('name')->unique();
            $table->enum('type', ['debit', 'credit'])->default('credit');
            $table->timestamps();
        });

        Schema::enableForeignKeyConstraints();
    }

    private function createRoles(): void
    {
        Role::create(['name' => 'Super Admin']);
        Role::create(['name' => 'Co Admin']);
        Role::create(['name' => 'Branch Manager']);
        Role::create(['name' => 'Fingerprint Admin']);
    }

    private function createUserWithRole(string $roleName, ?int $branchId = null): User
    {
        $user = User::factory()->create(['branch_id' => $branchId]);
        $role = Role::where('name', $roleName)->first();
        $user->roles()->attach($role);

        return $user;
    }

    private function createBookingWithInvoice(Branch $branch, string $invoiceId = 'BM-100'): array
    {
        $saleUser = User::factory()->create();
        $customer = Customer::create(['name' => 'Test Customer', 'mobile_no' => '0123456789']);

        $booking = Booking::create([
            'user_id' => $saleUser->id,
            'customer_id' => $customer->id,
            'booking_branch_id' => $branch->id,
            'fingerprint_branch_id' => $branch->id,
            'invoice_id' => $invoiceId,
            'pax_qty' => 1,
            'total_value' => 5000.00,
            'discount_amount' => 0,
        ]);

        $invoice = Invoice::create([
            'booking_id' => $booking->id,
            'branch_id' => $branch->id,
            'user_id' => $saleUser->id,
            'total_amount' => 5000.00,
            'paid_amount' => 3000.00,
            'balance' => 2000.00,
        ]);

        return compact('booking', 'invoice', 'customer', 'saleUser');
    }

    private function createPayment(string $method, int $amount, int $bookingId): Payment
    {
        return Payment::create([
            'booking_id' => $bookingId,
            'payment_method' => $method,
            'payment_date' => now()->toDateString(),
            'transaction_id' => 'TXN-'.uniqid(),
            'amount' => $amount,
            'bdt_amount' => $amount * 10,
        ]);
    }

    private function createVoucher(Payment $payment, int $bookingId): Voucher
    {
        return Voucher::create([
            'voucher_id' => 'VCH-'.uniqid(),
            'payment_id' => $payment->id,
            'booking_id' => $bookingId,
            'payment_method' => $payment->payment_method,
            'payment_date' => $payment->payment_date,
            'amount' => $payment->amount,
            'bdt_amount' => $payment->bdt_amount,
        ]);
    }

    private function makeCancelledBooking(User $canceller, Branch $branch, Booking $booking): CancelledBooking
    {
        $refund = $this->createPayment('cash', 2500, $booking->id);
        $refundVoucher = $this->createVoucher($refund, $booking->id);

        $deduction = $this->createPayment('bank', 500, $booking->id);
        $deductionVoucher = $this->createVoucher($deduction, $booking->id);

        return CancelledBooking::create([
            'booking_id' => $booking->id,
            'invoice_id' => $booking->invoice_id,
            'user_id' => $canceller->id,
            'total_paid' => 3000.00,
            'service_charge_deduction' => 500.00,
            'refund_amount' => 2500.00,
            'cancellation_branch_id' => $branch->id,
            'status' => CancelledBookingStatus::CANCELLED,
            'deduction_payment_id' => $deduction->id,
            'deduction_voucher_id' => $deductionVoucher->id,
            'refund_payment_id' => $refund->id,
            'refund_voucher_id' => $refundVoucher->id,
            'confirmed_by_id' => $canceller->id,
        ]);
    }

    private function makeCancelledPassenger(User $canceller, Branch $branch, Booking $booking): CancelledPassenger
    {
        $passenger = Passenger::create([
            'booking_id' => $booking->id,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'passport_no' => 'P123456',
            'mobile_no' => '0987654321',
            'gender' => 'male',
            'package_value' => 3000.00,
        ]);

        $invoice = Invoice::where('booking_id', $booking->id)->first();

        $refund = $this->createPayment('cash', 1500, $booking->id);
        $refundVoucher = $this->createVoucher($refund, $booking->id);
        $deduction = $this->createPayment('bank', 300, $booking->id);
        $deductionVoucher = $this->createVoucher($deduction, $booking->id);
        $adjustment = $this->createPayment('bank', 1200, $booking->id);
        $adjustmentVoucher = $this->createVoucher($adjustment, $booking->id);

        return CancelledPassenger::create([
            'booking_id' => $booking->id,
            'passenger_id' => $passenger->id,
            'invoice_id' => $invoice->id,
            'user_id' => $canceller->id,
            'package_value' => 3000.00,
            'additional_ticket_value' => 0,
            'total_passenger_due' => 3000.00,
            'service_charge_deduction' => 300.00,
            'refundable_amount' => 2700.00,
            'balance_adjusted_amount' => 1200.00,
            'refund_amount' => 1500.00,
            'cancellation_branch_id' => $branch->id,
            'status' => CancelledBookingStatus::CANCELLED,
            'deduction_payment_id' => $deduction->id,
            'deduction_voucher_id' => $deductionVoucher->id,
            'refund_payment_id' => $refund->id,
            'refund_voucher_id' => $refundVoucher->id,
            'adjustment_payment_id' => $adjustment->id,
            'adjustment_voucher_id' => $adjustmentVoucher->id,
            'confirmed_by_id' => $canceller->id,
        ]);
    }

    // ------------------------------------------------------------------
    // Auth / Role access
    // ------------------------------------------------------------------

    public function test_routes_require_authentication(): void
    {
        // The index routes are guarded only by the `role` middleware (which
        // aborts 403 when no user is present), not by the `auth` middleware.
        $this->get(route('cancelled-bookings.index'))->assertForbidden();
        $this->get(route('cancelled-passengers.index'))->assertForbidden();
    }

    public function test_index_requires_authorized_role(): void
    {
        $this->createRoles();
        $staff = $this->createUserWithRole('Super Admin');
        $staff->roles()->sync([]); // remove role -> treat as unauthorized

        $this->actingAs($staff)
            ->get(route('cancelled-bookings.index'))
            ->assertForbidden();
    }

    public function test_super_admin_can_access_index(): void
    {
        $this->createRoles();
        $admin = $this->createUserWithRole('Super Admin');

        $this->actingAs($admin)
            ->get(route('cancelled-bookings.index'))
            ->assertOk();
    }

    // ------------------------------------------------------------------
    // Branch scoping
    // ------------------------------------------------------------------

    public function test_branch_manager_sees_only_own_branch_records_on_index(): void
    {
        $this->createRoles();
        $branch1 = Branch::create(['name' => 'Branch 1']);
        $branch2 = Branch::create(['name' => 'Branch 2']);

        $manager = $this->createUserWithRole('Branch Manager', $branch1->id);
        $canceller = $this->createUserWithRole('Super Admin');

        ['booking' => $b1] = $this->createBookingWithInvoice($branch1, 'BM-100');
        ['booking' => $b2] = $this->createBookingWithInvoice($branch2, 'BM-200');
        $this->makeCancelledBooking($canceller, $branch1, $b1);
        $this->makeCancelledBooking($canceller, $branch2, $b2);

        // Row data is loaded via the JSON endpoint (see
        // test_booking_index_data_respects_branch_scope); assert the page shell renders.
        $this->actingAs($manager)->get(route('cancelled-bookings.index'))
            ->assertOk()
            ->assertSee('cancelledIndex');
    }

    public function test_show_forbids_record_from_another_branch(): void
    {
        $this->createRoles();
        $branch1 = Branch::create(['name' => 'Branch 1']);
        $branch2 = Branch::create(['name' => 'Branch 2']);

        $manager = $this->createUserWithRole('Branch Manager', $branch1->id);
        $canceller = $this->createUserWithRole('Super Admin');

        ['booking' => $b2] = $this->createBookingWithInvoice($branch2);
        $cb = $this->makeCancelledBooking($canceller, $branch2, $b2);

        $this->actingAs($manager)
            ->get(route('cancelled-bookings.show', $cb))
            ->assertForbidden();
    }

    public function test_fingerprint_admin_without_branch_forbidden(): void
    {
        $this->createRoles();
        $fpAdmin = $this->createUserWithRole('Fingerprint Admin'); // no branch
        $branch = Branch::create(['name' => 'Branch 1']);
        $canceller = $this->createUserWithRole('Super Admin');

        ['booking' => $booking] = $this->createBookingWithInvoice($branch);
        $cb = $this->makeCancelledBooking($canceller, $branch, $booking);

        $this->actingAs($fpAdmin)
            ->get(route('cancelled-bookings.index'))
            ->assertForbidden();

        $this->actingAs($fpAdmin)
            ->get(route('cancelled-bookings.show', $cb))
            ->assertForbidden();
    }

    // ------------------------------------------------------------------
    // View rendering
    // ------------------------------------------------------------------

    public function test_booking_show_renders_details(): void
    {
        $this->createRoles();
        $branch = Branch::create(['name' => 'Main Branch']);
        $canceller = $this->createUserWithRole('Super Admin');

        ['booking' => $booking] = $this->createBookingWithInvoice($branch);
        $cb = $this->makeCancelledBooking($canceller, $branch, $booking);

        $response = $this->actingAs($canceller)->get(route('cancelled-bookings.show', $cb));
        $response->assertOk()
            ->assertSee('Cancelled Booking Details')
            ->assertSee('Print Refund Voucher');
    }

    public function test_booking_print_voucher_renders_refund_voucher(): void
    {
        $this->createRoles();
        $branch = Branch::create(['name' => 'Main Branch']);
        $canceller = $this->createUserWithRole('Super Admin');

        ['booking' => $booking] = $this->createBookingWithInvoice($branch);
        $cb = $this->makeCancelledBooking($canceller, $branch, $booking);

        $response = $this->actingAs($canceller)->get(route('cancelled-bookings.print', $cb));
        $response->assertOk()
            ->assertSee('REFUND VOUCHER')
            ->assertSee('BIN MISHAL GLOBAL SERVICES LTD.')
            ->assertSee('data-sar="2500.000000"', false);    // refund_amount
    }

    public function test_passenger_show_renders_details(): void
    {
        $this->createRoles();
        $branch = Branch::create(['name' => 'Main Branch']);
        $canceller = $this->createUserWithRole('Super Admin');

        ['booking' => $booking] = $this->createBookingWithInvoice($branch);
        $cp = $this->makeCancelledPassenger($canceller, $branch, $booking);

        $response = $this->actingAs($canceller)->get(route('cancelled-passengers.show', $cp));
        $response->assertOk()
            ->assertSee('Cancelled Passenger Details')
            ->assertSee('John Doe');
    }

    public function test_passenger_print_voucher_renders_adjustment_from_due(): void
    {
        $this->createRoles();
        $branch = Branch::create(['name' => 'Main Branch']);
        $canceller = $this->createUserWithRole('Super Admin');

        ['booking' => $booking] = $this->createBookingWithInvoice($branch);
        $cp = $this->makeCancelledPassenger($canceller, $branch, $booking);

        $response = $this->actingAs($canceller)->get(route('cancelled-passengers.print', $cp));
        $response->assertOk()
            ->assertSee('REFUND VOUCHER')
            ->assertSee('Adjustment from Due')
            ->assertSee('data-sar="300.000000"', false)       // service_charge_deduction
            ->assertSee('data-sar="1200.000000"', false)      // balance_adjusted_amount
            ->assertSee('data-sar="1500.000000"', false);     // refund_amount
    }

    // ------------------------------------------------------------------
    // Confirm redirects
    // ------------------------------------------------------------------

    public function test_booking_confirm_redirects_to_print_voucher(): void
    {
        $this->createRoles();
        $branch = Branch::create(['name' => 'Main Branch']);
        $manager = $this->createUserWithRole('Branch Manager', $branch->id);
        $canceller = $this->createUserWithRole('Super Admin');

        ['booking' => $booking] = $this->createBookingWithInvoice($branch);
        $cb = $this->makeCancelledBooking($canceller, $branch, $booking);

        // Stub the heavy cancellation service to avoid running the full
        // voucher/payment side-effects against the test-only schema.
        $service = \Mockery::mock(CancellationService::class);
        $service->shouldReceive('confirmCancellation')->once();
        $this->app->instance(CancellationService::class, $service);

        $response = $this->actingAs($manager)
            ->post(route('cancelled-bookings.confirm.submit', $cb), [
                'payment_method' => PaymentMethod::CASH->value,
                'refund_amount' => 2500,
            ]);

        $response->assertRedirect(route('cancelled-bookings.print', $cb));
    }

    public function test_booking_confirm_sets_confirmed_by_and_renders_it_on_show(): void
    {
        $this->createRoles();
        $branch = Branch::create(['name' => 'Main Branch']);
        $canceller = $this->createUserWithRole('Super Admin');
        $confirmer = $this->createUserWithRole('Co Admin');

        ['booking' => $booking, 'invoice' => $invoice] = $this->createBookingWithInvoice($branch);

        $cb = CancelledBooking::create([
            'booking_id' => $booking->id,
            'invoice_id' => $invoice->id,
            'user_id' => $canceller->id,
            'total_paid' => 3000.00,
            'service_charge_deduction' => 0,
            'refund_amount' => 2500.00,
            'cancellation_branch_id' => $branch->id,
            'status' => CancelledBookingStatus::PROCESSING,
        ]);

        TransactionType::create(['name' => 'Service Charge Deduction', 'type' => 'debit']);
        TransactionType::create(['name' => 'Customer Refund', 'type' => 'credit']);

        $this->actingAs($confirmer);
        app(CancellationService::class)->confirmCancellation($cb, [
            'payment_method' => PaymentMethod::CASH->value,
            'refund_amount' => 2500,
        ]);

        $this->assertEquals($confirmer->id, $cb->fresh()->confirmed_by_id);

        $response = $this->actingAs($canceller)->get(route('cancelled-bookings.show', $cb->fresh()));
        $response->assertOk()
            ->assertSee('Confirmed By')
            ->assertSee($confirmer->name);
    }

    public function test_passenger_confirm_redirects_to_print_voucher(): void
    {
        $this->createRoles();
        $branch = Branch::create(['name' => 'Main Branch']);
        $manager = $this->createUserWithRole('Branch Manager', $branch->id);
        $canceller = $this->createUserWithRole('Super Admin');

        ['booking' => $booking] = $this->createBookingWithInvoice($branch);
        $cp = $this->makeCancelledPassenger($canceller, $branch, $booking);

        $service = \Mockery::mock(PassengerCancellationService::class);
        $service->shouldReceive('confirmCancellation')->once();
        $this->app->instance(PassengerCancellationService::class, $service);

        $response = $this->actingAs($manager)
            ->post(route('cancelled-passengers.confirm.submit', $cp->id), [
                'payment_method' => PaymentMethod::CASH->value,
                'balance_adjusted_amount' => 1200,
            ]);

        $response->assertRedirect(route('cancelled-passengers.print', $cp));
    }

    // ------------------------------------------------------------------
    // Index tab rendering (bookings vs passengers)
    // ------------------------------------------------------------------

    public function test_bookings_index_renders_bookings_tab(): void
    {
        $this->createRoles();
        $branch = Branch::create(['name' => 'Main Branch']);
        $canceller = $this->createUserWithRole('Super Admin');

        ['booking' => $booking] = $this->createBookingWithInvoice($branch, 'BM-TAB-1');
        $this->makeCancelledBooking($canceller, $branch, $booking);

        $response = $this->actingAs($canceller)->get(route('cancelled-bookings.index'));

        $response->assertOk()
            ->assertSee('Cancelled Bookings')
            ->assertSee('cancelledIndex')
            ->assertSee('api\\/cancelled-bookings');
    }

    public function test_passengers_index_renders_passengers_tab(): void
    {
        $this->createRoles();
        $branch = Branch::create(['name' => 'Main Branch']);
        $canceller = $this->createUserWithRole('Super Admin');

        ['booking' => $booking] = $this->createBookingWithInvoice($branch, 'BM-TAB-2');
        $this->makeCancelledPassenger($canceller, $branch, $booking);

        $response = $this->actingAs($canceller)->get(route('cancelled-passengers.index'));

        $response->assertOk()
            ->assertSee('Cancelled Passengers')
            ->assertSee('cancelledIndex')
            ->assertSee('api\\/cancelled-passengers');
    }

    public function test_passengers_tab_link_points_to_cancelled_passengers_route(): void
    {
        $this->createRoles();
        $branch = Branch::create(['name' => 'Main Branch']);
        $canceller = $this->createUserWithRole('Super Admin');

        ['booking' => $booking] = $this->createBookingWithInvoice($branch);
        $this->makeCancelledPassenger($canceller, $branch, $booking);

        $response = $this->actingAs($canceller)->get(route('cancelled-bookings.index'));

        $response->assertOk();
        $this->assertStringContainsString(route('cancelled-passengers.index'), $response->getContent());
    }

    // ------------------------------------------------------------------
    // JSON index endpoints (live search)
    // ------------------------------------------------------------------

    public function test_booking_index_data_returns_json(): void
    {
        $this->createRoles();
        $branch = Branch::create(['name' => 'Main Branch']);
        $canceller = $this->createUserWithRole('Super Admin');

        ['booking' => $booking] = $this->createBookingWithInvoice($branch, 'BM-JSON-1');
        $this->makeCancelledBooking($canceller, $branch, $booking);

        $response = $this->actingAs($canceller)->getJson(route('api.cancelled-bookings.data'));

        $response->assertOk()
            ->assertJsonStructure(['data', 'pagination' => ['current_page', 'last_page', 'per_page', 'total']])
            ->assertJsonPath('pagination.total', 1);
        $this->assertEquals('BM-JSON-1', $response->json('data.0.invoice_id'));
        $this->assertEquals(3000.00, (float) $response->json('data.0.total_paid'));
    }

    public function test_booking_index_data_filters_by_search(): void
    {
        $this->createRoles();
        $branch = Branch::create(['name' => 'Main Branch']);
        $canceller = $this->createUserWithRole('Super Admin');

        ['booking' => $b1] = $this->createBookingWithInvoice($branch, 'BM-AAA-1');
        ['booking' => $b2] = $this->createBookingWithInvoice($branch, 'BM-BBB-2');
        $this->makeCancelledBooking($canceller, $branch, $b1);
        $this->makeCancelledBooking($canceller, $branch, $b2);

        $response = $this->actingAs($canceller)->getJson(route('api.cancelled-bookings.data', ['search' => 'AAA']));

        $response->assertOk()->assertJsonPath('pagination.total', 1);
        $this->assertEquals('BM-AAA-1', $response->json('data.0.invoice_id'));
    }

    public function test_passenger_index_data_returns_json_and_searches(): void
    {
        $this->createRoles();
        $branch = Branch::create(['name' => 'Main Branch']);
        $canceller = $this->createUserWithRole('Super Admin');

        ['booking' => $booking] = $this->createBookingWithInvoice($branch, 'BM-PASS-1');
        $this->makeCancelledPassenger($canceller, $branch, $booking);

        $response = $this->actingAs($canceller)->getJson(route('api.cancelled-passengers.data'));
        $response->assertOk()->assertJsonPath('pagination.total', 1);
        $this->assertStringContainsString('John Doe', $response->json('data.0.passenger'));

        $search = $this->actingAs($canceller)->getJson(route('api.cancelled-passengers.data', ['search' => 'Doe']));
        $search->assertOk()->assertJsonPath('pagination.total', 1);

        $noMatch = $this->actingAs($canceller)->getJson(route('api.cancelled-passengers.data', ['search' => 'NoSuchPassenger']));
        $noMatch->assertOk()->assertJsonPath('pagination.total', 0);
    }

    public function test_booking_index_data_respects_branch_scope(): void
    {
        $this->createRoles();
        $branch1 = Branch::create(['name' => 'Branch 1']);
        $branch2 = Branch::create(['name' => 'Branch 2']);

        $manager = $this->createUserWithRole('Branch Manager', $branch1->id);
        $canceller = $this->createUserWithRole('Super Admin');

        ['booking' => $b1] = $this->createBookingWithInvoice($branch1, 'BM-SCOPE-1');
        ['booking' => $b2] = $this->createBookingWithInvoice($branch2, 'BM-SCOPE-2');
        $this->makeCancelledBooking($canceller, $branch1, $b1);
        $this->makeCancelledBooking($canceller, $branch2, $b2);

        $response = $this->actingAs($manager)->getJson(route('api.cancelled-bookings.data'));

        $response->assertOk()->assertJsonPath('pagination.total', 1);
        $this->assertEquals('BM-SCOPE-1', $response->json('data.0.invoice_id'));
    }
}
