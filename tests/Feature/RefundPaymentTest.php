<?php

namespace Tests\Feature;

use App\Enums\RefundPaymentStatus;
use App\Models\Booking;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Passenger;
use App\Models\Payment;
use App\Models\Role;
use App\Models\TransactionType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RefundPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function beginDatabaseTransaction(): void {}

    public static function tearDownAfterClass(): void
    {
        try {
            Artisan::call('migrate:fresh');
        } catch (\Throwable $e) {
            RefreshDatabaseState::$migrated = false;
        }
    }

    private User $admin;

    private Booking $booking;

    private Passenger $passenger;

    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::disableForeignKeyConstraints();
        foreach (['vouchers', 'payments', 'transaction_types', 'passengers', 'invoices', 'bookings', 'currency_rates', 'customers', 'branches', 'user_roles', 'roles'] as $t) {
            Schema::dropIfExists($t);
        }

        Schema::create('branches', function ($table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('customers', function ($table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('currency_rates', function ($table) {
            $table->id();
            $table->decimal('rate', 10, 2)->default(1);
            $table->timestamps();
        });

        Schema::create('bookings', function ($table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('booking_branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('currency_rate_id')->nullable()->constrained()->nullOnDelete();
            $table->integer('pax_qty')->default(1);
            $table->decimal('total_value', 14, 6)->default(0);
            $table->timestamps();
        });

        Schema::create('invoices', function ($table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->decimal('total_amount', 14, 6)->default(0);
            $table->decimal('paid_amount', 14, 6)->default(0);
            $table->decimal('balance', 14, 6)->default(0);
            $table->string('status')->default('pending');
            $table->timestamps();
        });

        Schema::create('passengers', function ($table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings')->restrictOnDelete();
            $table->string('first_name');
            $table->string('last_name')->nullable();
            $table->decimal('refund_payable', 14, 6)->default(0);
            $table->foreignId('refund_payment_branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('refund_payment_status')->nullable();
            $table->timestamps();
        });

        Schema::create('roles', function ($table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        Schema::create('user_roles', function ($table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('transaction_types', function ($table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('type')->default('credit');
            $table->timestamps();
        });

        Schema::create('payments', function ($table) {
            $table->id();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('booking_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('currency_rate_id')->nullable()->constrained()->nullOnDelete();
            $table->date('payment_date')->nullable();
            $table->string('payment_method')->default('cash');
            $table->decimal('amount', 14, 6)->default(0);
            $table->decimal('bdt_amount', 14, 6)->default(0);
            $table->foreignId('passenger_id')->nullable()->constrained('passengers')->nullOnDelete();
            $table->text('remarks')->nullable();
            $table->timestamps();
        });

        Schema::create('vouchers', function ($table) {
            $table->id();
            $table->string('voucher_id')->unique();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('booking_id')->constrained()->restrictOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('currency_rate_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('transaction_type_id')->constrained('transaction_types')->restrictOnDelete();
            $table->date('payment_date')->nullable();
            $table->string('payment_method')->default('cash');
            $table->decimal('amount', 14, 6)->default(0);
            $table->decimal('bdt_amount', 14, 6)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::enableForeignKeyConstraints();

        foreach (['Super Admin', 'Co Admin', 'Ticket Admin', 'Branch Manager', 'Fingerprint Admin'] as $name) {
            Role::create(['name' => $name]);
        }

        TransactionType::create(['name' => 'Initial Payment', 'type' => 'credit']);
        TransactionType::create(['name' => 'Due Collection', 'type' => 'credit']);
        TransactionType::create(['name' => 'Ticket Refund - Payment', 'type' => 'debit']);

        $this->branch = Branch::create(['name' => 'Refund Branch']);
        $this->admin = User::factory()->create();
        $this->admin->roles()->attach(Role::where('name', 'Super Admin')->first());

        $customer = Customer::create(['name' => 'Test Customer']);
        $this->booking = Booking::create([
            'user_id' => $this->admin->id,
            'customer_id' => $customer->id,
            'booking_branch_id' => $this->branch->id,
            'pax_qty' => 1,
            'total_value' => 1000,
        ]);
        $this->passenger = Passenger::create([
            'booking_id' => $this->booking->id,
            'first_name' => 'Test',
            'last_name' => 'Passenger',
            'refund_payable' => 500,
        ]);

        Invoice::create([
            'booking_id' => $this->booking->id,
            'branch_id' => $this->branch->id,
            'user_id' => $this->admin->id,
            'total_amount' => 1000,
            'paid_amount' => 0,
            'balance' => 1000,
            'status' => 'pending',
        ]);
    }

    public function test_can_assign_branch(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson(route('passengers.refund-pay-assign-branch', $this->passenger->id), [
                'branch_id' => $this->branch->id,
            ]);

        $response->assertOk()->assertJson(['success' => true]);

        $this->passenger->refresh();
        $this->assertEquals(RefundPaymentStatus::PROCESSING, $this->passenger->refund_payment_status);
        $this->assertEquals($this->branch->id, $this->passenger->refund_payment_branch_id);
    }

    public function test_cannot_assign_branch_with_zero_refund_payable(): void
    {
        $this->passenger->update(['refund_payable' => 0]);

        $response = $this->actingAs($this->admin)
            ->postJson(route('passengers.refund-pay-assign-branch', $this->passenger->id), [
                'branch_id' => $this->branch->id,
            ]);

        $response->assertStatus(422)->assertJson(['success' => false]);
    }

    public function test_can_confirm_payment(): void
    {
        $this->passenger->update([
            'refund_payment_status' => RefundPaymentStatus::PROCESSING,
            'refund_payment_branch_id' => $this->branch->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson(route('passengers.refund-pay-confirm', $this->passenger->id), [
                'payment_method' => 'cash',
                'remarks' => 'Test',
            ]);

        $response->assertOk()->assertJson(['success' => true]);

        $this->passenger->refresh();
        $this->assertEquals(RefundPaymentStatus::PAID, $this->passenger->refund_payment_status);
        $this->assertEquals(0, (float) $this->passenger->refund_payable);
    }

    public function test_can_revert(): void
    {
        $this->passenger->update([
            'refund_payment_status' => RefundPaymentStatus::PROCESSING,
            'refund_payment_branch_id' => $this->branch->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson(route('passengers.refund-pay-revert', $this->passenger->id));

        $response->assertOk()->assertJson(['success' => true]);

        $this->passenger->refresh();
        $this->assertEquals(RefundPaymentStatus::PENDING, $this->passenger->refund_payment_status);
        $this->assertNull($this->passenger->refund_payment_branch_id);
    }

    public function test_unauthorized_user_gets_403_on_assign(): void
    {
        $regularUser = User::factory()->create();

        $response = $this->actingAs($regularUser)
            ->postJson(route('passengers.refund-pay-assign-branch', $this->passenger->id), [
                'branch_id' => $this->branch->id,
            ]);

        $response->assertStatus(403);
    }

    public function test_voucher_uses_correct_transaction_type(): void
    {
        $this->passenger->update([
            'refund_payment_status' => RefundPaymentStatus::PROCESSING,
            'refund_payment_branch_id' => $this->branch->id,
        ]);

        $this->actingAs($this->admin)
            ->postJson(route('passengers.refund-pay-confirm', $this->passenger->id), [
                'payment_method' => 'cash',
            ]);

        $payment = Payment::where('passenger_id', $this->passenger->id)->first();
        $this->assertNotNull($payment);

        $voucher = $payment->voucher;
        $this->assertNotNull($voucher);
        $this->assertEquals('Ticket Refund - Payment', $voucher->transactionType->name);
    }

    public function test_paid_amount_not_affected_by_refund_payment(): void
    {
        $this->passenger->update([
            'refund_payment_status' => RefundPaymentStatus::PROCESSING,
            'refund_payment_branch_id' => $this->branch->id,
        ]);

        $this->actingAs($this->admin)
            ->postJson(route('passengers.refund-pay-confirm', $this->passenger->id), [
                'payment_method' => 'cash',
            ]);

        $this->booking->invoice->refresh();
        $this->assertEquals(0, (float) $this->booking->invoice->paid_amount);
    }
}
