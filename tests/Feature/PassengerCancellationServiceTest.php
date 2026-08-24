<?php

namespace Tests\Feature;

use App\Enums\CancelledBookingStatus;
use App\Models\Booking;
use App\Models\Branch;
use App\Models\CancelledPassenger;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Passenger;
use App\Models\TransactionType;
use App\Models\User;
use App\Services\PassengerCancellationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PassengerCancellationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected PassengerCancellationService $service;

    // Override to avoid starting a DB transaction. DDL (Schema::create)
    // in setUp() on MySQL implicitly commits, which breaks nested
    // DB::transaction() calls in the service layer.
    protected function beginDatabaseTransaction(): void
    {
        // no-op
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(PassengerCancellationService::class);

        // Drop tables that already exist from migrations so we can recreate
        // simplified schemas for this test.
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('cancelled_passengers');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('vouchers');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('bookings');
        Schema::dropIfExists('passengers');
        Schema::dropIfExists('passenger_statuses');
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
            $table->enum('passenger_type', ['adult', 'child', 'infant'])->default('adult');
            $table->date('passport_expiry')->nullable();
            $table->integer('stay_duration')->default(7);
            $table->enum('service_required', ['all', 'visa_only', 'ticket_only'])->default('all');
            $table->date('flight_date_from')->nullable();
            $table->date('flight_date_to')->nullable();
            $table->date('actual_flight_date')->nullable();
            $table->enum('ticket_status', ['pending', 'issued', 're-issued', 'refunded'])->default('pending');
            $table->string('address')->nullable();
            $table->decimal('package_value', 12, 2)->default(0);
            $table->decimal('refund_payable', 14, 6)->default(0);
            $table->boolean('is_cancelled')->default(false);
            $table->timestamp('cancelled_at')->nullable();
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
            $table->foreignId('cancelled_passenger_id')->nullable()->constrained('cancelled_passengers')->nullOnDelete();
            $table->unsignedBigInteger('cancelled_booking_id')->nullable();
            $table->unsignedBigInteger('refunded_ticket_id')->nullable();
            $table->unsignedBigInteger('re_issued_ticket_id')->nullable();
            $table->timestamps();
        });

        Schema::create('transaction_types', function ($table) {
            $table->id();
            $table->string('name')->unique();
            $table->enum('type', ['debit', 'credit']);
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
            $table->foreignId('cancelled_passenger_id')->nullable()->constrained('cancelled_passengers')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('cancelled_passengers', function ($table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings')->restrictOnDelete();
            $table->foreignId('passenger_id')->constrained('passengers')->restrictOnDelete();
            $table->foreignId('invoice_id')->constrained('invoices')->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->decimal('package_value', 14, 6);
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

        Schema::enableForeignKeyConstraints();
    }

    private function createBookingWithPassengers(int $passengerCount = 2): array
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $customer = Customer::create(['name' => 'Test Customer']);
        $branch = Branch::create(['name' => 'Main Branch']);

        $booking = Booking::create([
            'user_id' => $user->id,
            'customer_id' => $customer->id,
            'booking_branch_id' => $branch->id,
            'pax_qty' => $passengerCount,
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

        $passengers = collect();
        for ($i = 0; $i < $passengerCount; $i++) {
            $passengers->push(Passenger::create([
                'booking_id' => $booking->id,
                'first_name' => "Passenger {$i}",
                'last_name' => 'Test',
                'package_value' => 2500.00,
            ]));
        }

        return compact('user', 'customer', 'branch', 'booking', 'invoice', 'passengers');
    }

    public function test_initiate_sets_hold_status_and_creates_row(): void
    {
        ['passengers' => $passengers, 'branch' => $branch] = $this->createBookingWithPassengers();
        $passenger = $passengers->first();

        $result = $this->service->initiateCancellation($passenger, [
            'cancellation_branch_id' => $branch->id,
            'service_charge_deduction' => 0,
        ]);

        $this->assertInstanceOf(CancelledPassenger::class, $result);
        $this->assertEquals(CancelledBookingStatus::PROCESSING, $result->status);
        $this->assertTrue($passenger->fresh()->is_cancelled);
        $this->assertNotNull($passenger->fresh()->cancelled_at);
        $this->assertEquals('Hold', $passenger->fresh()->status?->name);
    }

    public function test_initiate_blocks_on_already_cancelled_passenger(): void
    {
        ['passengers' => $passengers, 'branch' => $branch] = $this->createBookingWithPassengers();
        $passenger = $passengers->first();

        $this->service->initiateCancellation($passenger, [
            'cancellation_branch_id' => $branch->id,
        ]);

        $this->expectException(\Exception::class);
        $this->service->initiateCancellation($passenger, [
            'cancellation_branch_id' => $branch->id,
        ]);
    }

    public function test_initiate_blocks_last_active_passenger(): void
    {
        ['passengers' => $passengers, 'branch' => $branch] = $this->createBookingWithPassengers(2);
        $passenger = $passengers->first();

        $passenger->update(['is_cancelled' => true]);

        $this->expectException(\Exception::class);
        $this->service->initiateCancellation($passengers->last(), [
            'cancellation_branch_id' => $branch->id,
        ]);
    }

    public function test_revert_deletes_row_restores_passenger(): void
    {
        ['passengers' => $passengers, 'branch' => $branch] = $this->createBookingWithPassengers();
        $passenger = $passengers->first();

        $cancelled = $this->service->initiateCancellation($passenger, [
            'cancellation_branch_id' => $branch->id,
        ]);

        $this->service->revertCancellation($cancelled);

        $this->assertSoftDeleted('cancelled_passengers', ['id' => $cancelled->id]);
        $this->assertFalse($passenger->fresh()->is_cancelled);
        $this->assertNull($passenger->fresh()->cancelled_at);
    }

    public function test_revert_does_not_touch_refund_payable(): void
    {
        ['passengers' => $passengers, 'branch' => $branch] = $this->createBookingWithPassengers();
        $passenger = $passengers->first();
        $passenger->update(['refund_payable' => 500.00]);

        $cancelled = $this->service->initiateCancellation($passenger, [
            'cancellation_branch_id' => $branch->id,
        ]);

        $this->service->revertCancellation($cancelled);

        $this->assertEquals(500.00, (float) $passenger->fresh()->refund_payable);
    }

    public function test_confirm_reduces_totals(): void
    {
        TransactionType::create(['name' => 'Due Adjustment', 'type' => 'credit']);
        TransactionType::create(['name' => 'Customer Refund', 'type' => 'debit']);
        ['passengers' => $passengers, 'booking' => $booking, 'invoice' => $invoice, 'branch' => $branch] = $this->createBookingWithPassengers();
        $passenger = $passengers->first();

        $cancelled = $this->service->initiateCancellation($passenger, [
            'cancellation_branch_id' => $branch->id,
        ]);

        $this->service->confirmCancellation($cancelled, [
            'balance_adjusted_amount' => 2000,
            'payment_method' => 'cash',
            'remarks' => null,
        ]);

        $this->assertEquals(1, $booking->fresh()->pax_qty);
    }

    public function test_confirm_full_adjustment_no_refund(): void
    {
        TransactionType::create(['name' => 'Due Adjustment', 'type' => 'credit']);
        ['passengers' => $passengers, 'invoice' => $invoice, 'branch' => $branch] = $this->createBookingWithPassengers();
        $passenger = $passengers->first();

        $cancelled = $this->service->initiateCancellation($passenger, [
            'cancellation_branch_id' => $branch->id,
        ]);

        $this->service->confirmCancellation($cancelled, [
            'balance_adjusted_amount' => 2500,
            'payment_method' => 'cash',
        ]);

        $this->assertEquals(0.00, (float) $cancelled->fresh()->refund_amount);
        $this->assertNull($cancelled->fresh()->refund_payment_id);
    }

    public function test_confirm_partial_adjustment_creates_refund(): void
    {
        TransactionType::create(['name' => 'Due Adjustment', 'type' => 'credit']);
        TransactionType::create(['name' => 'Customer Refund', 'type' => 'debit']);
        ['passengers' => $passengers, 'branch' => $branch] = $this->createBookingWithPassengers();
        $passenger = $passengers->first();

        $cancelled = $this->service->initiateCancellation($passenger, [
            'cancellation_branch_id' => $branch->id,
        ]);

        $this->service->confirmCancellation($cancelled, [
            'balance_adjusted_amount' => 1000,
            'payment_method' => 'cash',
        ]);

        $this->assertEquals(1500.00, (float) $cancelled->fresh()->refund_amount);
        $this->assertNotNull($cancelled->fresh()->refund_payment_id);
        $this->assertNotNull($cancelled->fresh()->refund_voucher_id);
    }

    public function test_confirm_creates_deduction_when_service_charge(): void
    {
        TransactionType::create(['name' => 'Due Adjustment', 'type' => 'credit']);
        TransactionType::create(['name' => 'Service Charge Deduction', 'type' => 'credit']);
        TransactionType::create(['name' => 'Customer Refund', 'type' => 'debit']);
        ['passengers' => $passengers, 'branch' => $branch] = $this->createBookingWithPassengers();
        $passenger = $passengers->first();

        $cancelled = $this->service->initiateCancellation($passenger, [
            'cancellation_branch_id' => $branch->id,
            'service_charge_deduction' => 200,
        ]);

        $this->service->confirmCancellation($cancelled, [
            'balance_adjusted_amount' => 2000,
            'payment_method' => 'cash',
        ]);

        $this->assertNotNull($cancelled->fresh()->deduction_payment_id);
        $this->assertNotNull($cancelled->fresh()->deduction_voucher_id);
        $this->assertEquals(200.00, (float) $cancelled->fresh()->deductionPayment->amount);
    }

    public function test_confirm_sets_permanent_status(): void
    {
        TransactionType::create(['name' => 'Customer Refund', 'type' => 'debit']);
        ['passengers' => $passengers, 'branch' => $branch] = $this->createBookingWithPassengers();
        $passenger = $passengers->first();

        $cancelled = $this->service->initiateCancellation($passenger, [
            'cancellation_branch_id' => $branch->id,
        ]);

        $this->service->confirmCancellation($cancelled, [
            'balance_adjusted_amount' => 0,
            'payment_method' => 'cash',
        ]);

        $this->assertEquals('Cancel', $passenger->fresh()->status?->name);
        $this->assertEquals(CancelledBookingStatus::CANCELLED, $cancelled->fresh()->status);
    }
}
