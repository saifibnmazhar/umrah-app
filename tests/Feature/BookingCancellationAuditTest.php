<?php

namespace Tests\Feature;

use App\Enums\CancelledBookingStatus;
use App\Models\Booking;
use App\Models\Branch;
use App\Models\CancelledBooking;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Passenger;
use App\Models\TransactionType;
use App\Models\User;
use App\Services\CancellationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BookingCancellationAuditTest extends TestCase
{
    use RefreshDatabase;

    protected CancellationService $service;

    protected function beginDatabaseTransaction(): void {}

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
        $this->service = app(CancellationService::class);

        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('cancelled_bookings');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('vouchers');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('bookings');
        Schema::dropIfExists('passengers');
        Schema::dropIfExists('transaction_types');
        Schema::dropIfExists('currency_rates');
        Schema::dropIfExists('customers');
        Schema::dropIfExists('branches');

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
            $table->decimal('sar_to_bdt', 10, 4);
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
            $table->decimal('discount_amount', 14, 6)->default(0);
            $table->decimal('profit', 14, 6)->default(0);
            $table->boolean('is_cancelled')->default(false);
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
            $table->enum('status', ['pending', 'partial', 'paid', 'cancelled', 'refunded'])->default('pending');
            $table->timestamps();
        });

        Schema::create('passengers', function ($table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings')->restrictOnDelete();
            $table->string('first_name');
            $table->string('last_name');
            $table->decimal('package_value', 12, 2)->default(0);
            $table->decimal('refund_payable', 14, 6)->default(0);
            $table->decimal('profit', 14, 6)->default(0);
            $table->decimal('visa_profit', 14, 6)->default(0);
            $table->timestamp('visa_profit_effective_at')->nullable();
            $table->decimal('ticket_profit', 14, 6)->default(0);
            $table->timestamp('ticket_profit_effective_at')->nullable();
            $table->decimal('service_charge', 14, 6)->default(0);
            $table->timestamp('service_charge_effective_at')->nullable();
            $table->boolean('is_cancelled')->default(false);
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
        });

        Schema::create('transaction_types', function ($table) {
            $table->id();
            $table->string('name')->unique();
            $table->enum('type', ['debit', 'credit']);
            $table->timestamps();
        });

        Schema::create('payments', function ($table) {
            $table->id();
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained('bookings')->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('currency_rate_id')->nullable()->constrained('currency_rates')->nullOnDelete();
            $table->decimal('amount', 14, 6);
            $table->decimal('bdt_amount', 14, 6)->default(0);
            $table->enum('payment_method', ['cash', 'bank'])->default('cash');
            $table->date('payment_date');
            $table->text('notes')->nullable();
            $table->string('remarks')->nullable();
            $table->unsignedBigInteger('cancelled_passenger_id')->nullable();
            $table->unsignedBigInteger('cancelled_booking_id')->nullable();
            $table->unsignedBigInteger('passenger_id')->nullable();
            $table->unsignedBigInteger('refunded_ticket_id')->nullable();
            $table->unsignedBigInteger('re_issued_ticket_id')->nullable();
            $table->timestamps();
        });

        Schema::create('vouchers', function ($table) {
            $table->id();
            $table->string('voucher_id')->unique();
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained('bookings')->nullOnDelete();
            $table->foreignId('payment_id')->constrained('payments')->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('currency_rate_id')->nullable()->constrained('currency_rates')->nullOnDelete();
            $table->foreignId('transaction_type_id')->constrained('transaction_types')->restrictOnDelete();
            $table->decimal('amount', 14, 6);
            $table->decimal('bdt_amount', 14, 6)->default(0);
            $table->enum('payment_method', ['cash', 'bank'])->default('cash');
            $table->date('payment_date');
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('cancelled_passenger_id')->nullable();
            $table->unsignedBigInteger('cancelled_booking_id')->nullable();
            $table->timestamps();
        });

        Schema::create('cancelled_bookings', function ($table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->restrictOnDelete();
            $table->foreignId('invoice_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('confirmed_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reverted_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('total_paid', 14, 6);
            $table->decimal('service_charge_deduction', 14, 6)->nullable();
            $table->decimal('refund_amount', 14, 6)->default(0);
            $table->decimal('total_passenger_refundable', 14, 6)->default(0);
            $table->foreignId('cancellation_branch_id')->constrained('branches')->restrictOnDelete();
            $table->enum('status', ['cancellation processing', 'cancelled'])->default('cancellation processing');
            $table->unsignedBigInteger('deduction_payment_id')->nullable();
            $table->unsignedBigInteger('deduction_voucher_id')->nullable();
            $table->unsignedBigInteger('refund_payment_id')->nullable();
            $table->unsignedBigInteger('refund_voucher_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::enableForeignKeyConstraints();
    }

    private function createBooking(): array
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $customer = Customer::create(['name' => 'Test Customer']);
        $branch = Branch::create(['name' => 'Main Branch']);

        $booking = Booking::create([
            'user_id' => $user->id,
            'customer_id' => $customer->id,
            'booking_branch_id' => $branch->id,
            'pax_qty' => 1,
            'total_value' => 5000.00,
            'discount_amount' => 0,
        ]);

        $invoice = Invoice::create([
            'booking_id' => $booking->id,
            'branch_id' => $branch->id,
            'user_id' => $user->id,
            'total_amount' => 5000.00,
            'paid_amount' => 3000.00,
            'balance' => 2000.00,
        ]);

        Passenger::create([
            'booking_id' => $booking->id,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'package_value' => 2500.00,
        ]);

        return compact('user', 'branch', 'booking', 'invoice');
    }

    public function test_confirm_sets_confirmed_by_id(): void
    {
        TransactionType::create(['name' => 'Customer Refund', 'type' => 'debit']);
        ['user' => $user, 'branch' => $branch, 'booking' => $booking] = $this->createBooking();

        $cancelled = $this->service->initiateCancellation($booking, [
            'cancellation_branch_id' => $branch->id,
        ]);

        $this->service->confirmCancellation($cancelled, [
            'payment_method' => 'cash',
            'refund_amount' => 100,
        ]);

        $this->assertEquals($user->id, $cancelled->fresh()->confirmed_by_id);
        $this->assertEquals(CancelledBookingStatus::CANCELLED, $cancelled->fresh()->status);
    }

    public function test_revert_sets_reverted_by_id(): void
    {
        ['user' => $user, 'branch' => $branch, 'booking' => $booking] = $this->createBooking();

        $cancelled = $this->service->initiateCancellation($booking, [
            'cancellation_branch_id' => $branch->id,
        ]);

        $this->service->revertCancellation($cancelled);

        $trashed = CancelledBooking::withTrashed()->find($cancelled->id);
        $this->assertNotNull($trashed->deleted_at);
        $this->assertEquals($user->id, $trashed->reverted_by_id);
    }
}
