<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Passenger;
use App\Models\PassengerStatus;
use App\Models\TransactionType;
use App\Models\User;
use App\Services\CancellationService;
use App\Services\CostTrackingService;
use App\Services\RefundCapService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class BookingCancellationPassengerStatusTest extends TestCase
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

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    protected function setUp(): void
    {
        parent::setUp();

        Schema::disableForeignKeyConstraints();
        foreach (['cancelled_bookings', 'passenger_update_logs', 'payments', 'vouchers', 'refunded_tickets', 'issued_tickets', 'invoices', 'passengers', 'passenger_statuses', 'bookings', 'ticket_fares', 'transaction_types', 'customers', 'branches'] as $t) {
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

        Schema::create('bookings', function ($table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('booking_branch_id')->constrained('branches')->restrictOnDelete();
            $table->integer('pax_qty')->default(1);
            $table->decimal('total_value', 14, 6)->default(0);
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
            $table->string('status')->default('pending');
            $table->string('audit_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('passenger_statuses', function ($table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        Schema::create('ticket_fares', function ($table) {
            $table->id();
            $table->timestamps();
        });

        Schema::create('passengers', function ($table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->restrictOnDelete();
            $table->foreignId('passenger_status_id')->nullable()->constrained('passenger_statuses')->nullOnDelete();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('passport_no')->nullable();
            $table->decimal('package_value', 14, 6)->default(0);
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

        Schema::create('issued_tickets', function ($table) {
            $table->id();
            $table->foreignId('passenger_id')->constrained()->restrictOnDelete();
            $table->string('ticket_number')->nullable();
            $table->string('status')->default('issued');
            $table->decimal('net_fare', 14, 6)->default(0);
            $table->decimal('selling_fare', 14, 6)->nullable();
            $table->decimal('offer_price', 14, 6)->nullable();
            $table->string('issue_type')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('refunded_tickets', function ($table) {
            $table->id();
            $table->foreignId('issued_ticket_id')->constrained()->restrictOnDelete();
            $table->decimal('refund_to_customer', 14, 6)->default(0);
            $table->decimal('net_fare', 14, 6)->default(0);
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('passenger_update_logs', function ($table) {
            $table->id();
            $table->foreignId('passenger_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('passport_no')->nullable();
            $table->string('action');
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
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
            $table->unsignedBigInteger('currency_rate_id')->nullable();
            $table->decimal('amount', 14, 6);
            $table->decimal('bdt_amount', 14, 6)->default(0);
            $table->string('payment_method')->default('cash');
            $table->date('payment_date')->nullable();
            $table->string('remarks')->nullable();
            $table->unsignedBigInteger('cancelled_booking_id')->nullable();
            $table->unsignedBigInteger('cancelled_passenger_id')->nullable();
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
            $table->unsignedBigInteger('currency_rate_id')->nullable();
            $table->foreignId('transaction_type_id')->constrained('transaction_types')->restrictOnDelete();
            $table->decimal('amount', 14, 6);
            $table->decimal('bdt_amount', 14, 6)->default(0);
            $table->string('payment_method')->default('cash');
            $table->date('payment_date')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('cancelled_booking_id')->nullable();
            $table->unsignedBigInteger('cancelled_passenger_id')->nullable();
            $table->timestamps();
        });

        Schema::create('cancelled_bookings', function ($table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->restrictOnDelete();
            $table->foreignId('invoice_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->decimal('total_paid', 14, 6);
            $table->decimal('service_charge_deduction', 14, 6)->nullable();
            $table->decimal('refund_amount', 14, 6)->default(0);
            $table->decimal('total_passenger_refundable', 14, 6)->default(0);
            $table->json('passenger_statuses_snapshot')->nullable();
            $table->foreignId('cancellation_branch_id')->constrained('branches')->restrictOnDelete();
            $table->enum('status', ['cancellation processing', 'cancelled'])->default('cancellation processing');
            $table->foreignId('refund_payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->foreignId('refund_voucher_id')->nullable()->constrained('vouchers')->nullOnDelete();
            $table->foreignId('deduction_payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->foreignId('deduction_voucher_id')->nullable()->constrained('vouchers')->nullOnDelete();
            $table->foreignId('confirmed_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reverted_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::enableForeignKeyConstraints();
    }

    private function createBookingWithMixedStatusPassengers(): array
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $branch = Branch::create(['name' => 'Main']);
        $customer = Customer::create(['name' => 'C']);

        $booking = Booking::create([
            'user_id' => $user->id,
            'customer_id' => $customer->id,
            'booking_branch_id' => $branch->id,
            'pax_qty' => 4,
            'total_value' => 10000,
        ]);

        $invoice = Invoice::create([
            'booking_id' => $booking->id,
            'branch_id' => $branch->id,
            'user_id' => $user->id,
            'total_amount' => 10000,
            'paid_amount' => 8000,
            'balance' => 2000,
        ]);

        TransactionType::create(['name' => 'Initial Payment', 'type' => 'credit']);
        TransactionType::create(['name' => 'Customer Refund', 'type' => 'debit']);
        TransactionType::create(['name' => 'Service Charge Deduction', 'type' => 'debit']);

        $holdStatus = PassengerStatus::create(['name' => 'Hold']);
        $cancelStatus = PassengerStatus::create(['name' => 'Cancel']);
        $deliveredStatus = PassengerStatus::create(['name' => 'Delivered']);

        $pNull = Passenger::create([
            'booking_id' => $booking->id,
            'first_name' => 'Null',
            'last_name' => 'Status',
            'package_value' => 2500,
            'passenger_status_id' => null,
        ]);

        $pHold = Passenger::create([
            'booking_id' => $booking->id,
            'first_name' => 'Already',
            'last_name' => 'Hold',
            'package_value' => 2500,
            'passenger_status_id' => $holdStatus->id,
        ]);

        $pCancel = Passenger::create([
            'booking_id' => $booking->id,
            'first_name' => 'Already',
            'last_name' => 'Cancel',
            'package_value' => 2500,
            'passenger_status_id' => $cancelStatus->id,
        ]);

        $pDelivered = Passenger::create([
            'booking_id' => $booking->id,
            'first_name' => 'Was',
            'last_name' => 'Delivered',
            'package_value' => 2500,
            'passenger_status_id' => $deliveredStatus->id,
        ]);

        return compact('user', 'branch', 'booking', 'invoice', 'holdStatus', 'cancelStatus', 'deliveredStatus', 'pNull', 'pHold', 'pCancel', 'pDelivered');
    }

    private function serviceWithCost(float $totalCost, float $refundCapRemaining = 999999): CancellationService
    {
        $cost = Mockery::mock(CostTrackingService::class);
        $cost->shouldReceive('getBookingCostSummary')->andReturn([
            'fingerprint_cost' => 0,
            'visa_cost' => 0,
            'ticket_cost' => $totalCost,
            'total_cost' => $totalCost,
            'passengers' => collect([]),
        ]);

        $capService = Mockery::mock(RefundCapService::class);
        $capService->shouldReceive('getCap')->andReturn([
            'paid' => 8000,
            'refunded' => 0,
            'remaining' => $refundCapRemaining,
        ]);
        $capService->shouldReceive('normalizeToSar')->andReturnUsing(fn (float $amount) => $amount);
        $capService->shouldReceive('assertRefundAllowed')->andReturnNull();
        $this->app->instance(RefundCapService::class, $capService);

        return new CancellationService($cost);
    }

    public function test_initiate_sets_hold_except_hold_and_cancel(): void
    {
        ['booking' => $booking, 'branch' => $branch, 'pNull' => $pNull, 'pHold' => $pHold, 'pCancel' => $pCancel, 'pDelivered' => $pDelivered, 'holdStatus' => $holdStatus, 'cancelStatus' => $cancelStatus, 'deliveredStatus' => $deliveredStatus] = $this->createBookingWithMixedStatusPassengers();

        $this->serviceWithCost(0)->initiateCancellation($booking, [
            'cancellation_branch_id' => $branch->id,
        ]);

        $this->assertDatabaseHas('passengers', ['id' => $pNull->id, 'passenger_status_id' => $holdStatus->id]);
        $this->assertDatabaseHas('passengers', ['id' => $pHold->id, 'passenger_status_id' => $holdStatus->id]);
        $this->assertDatabaseHas('passengers', ['id' => $pCancel->id, 'passenger_status_id' => $cancelStatus->id]);
        $this->assertDatabaseHas('passengers', ['id' => $pDelivered->id, 'passenger_status_id' => $holdStatus->id]);
    }

    public function test_confirm_sets_all_to_cancel_status_and_flag(): void
    {
        ['booking' => $booking, 'branch' => $branch, 'pNull' => $pNull, 'pHold' => $pHold, 'pCancel' => $pCancel, 'pDelivered' => $pDelivered, 'cancelStatus' => $cancelStatus] = $this->createBookingWithMixedStatusPassengers();

        $service = $this->serviceWithCost(0);
        $cancelledBooking = $service->initiateCancellation($booking, [
            'cancellation_branch_id' => $branch->id,
        ]);

        $service->confirmCancellation($cancelledBooking, [
            'payment_method' => 'cash',
            'refund_amount' => 0,
        ]);

        $this->assertDatabaseHas('passengers', ['id' => $pNull->id, 'passenger_status_id' => $cancelStatus->id, 'is_cancelled' => true]);
        $this->assertDatabaseHas('passengers', ['id' => $pHold->id, 'passenger_status_id' => $cancelStatus->id, 'is_cancelled' => true]);
        $this->assertDatabaseHas('passengers', ['id' => $pCancel->id, 'passenger_status_id' => $cancelStatus->id, 'is_cancelled' => true]);
        $this->assertDatabaseHas('passengers', ['id' => $pDelivered->id, 'passenger_status_id' => $cancelStatus->id, 'is_cancelled' => true]);

        foreach ([$pNull, $pHold, $pCancel, $pDelivered] as $p) {
            $this->assertNotNull($p->fresh()->cancelled_at);
        }
    }

    public function test_revert_restores_exact_pre_initiate_statuses(): void
    {
        ['booking' => $booking, 'branch' => $branch, 'pNull' => $pNull, 'pHold' => $pHold, 'pCancel' => $pCancel, 'pDelivered' => $pDelivered, 'deliveredStatus' => $deliveredStatus] = $this->createBookingWithMixedStatusPassengers();

        $service = $this->serviceWithCost(0);
        $cancelledBooking = $service->initiateCancellation($booking, [
            'cancellation_branch_id' => $branch->id,
        ]);

        $service->revertCancellation($cancelledBooking);

        $this->assertNull($pNull->fresh()->passenger_status_id);
        $this->assertNotNull($pHold->fresh()->passenger_status_id);
        $this->assertEquals('Hold', $pHold->fresh()->status->name);
        $this->assertNotNull($pCancel->fresh()->passenger_status_id);
        $this->assertEquals('Cancel', $pCancel->fresh()->status->name);
        $this->assertEquals($deliveredStatus->id, $pDelivered->fresh()->passenger_status_id);
    }

    public function test_revert_preserves_cancel_passenger_that_was_cancel_before_initiate(): void
    {
        ['booking' => $booking, 'branch' => $branch, 'pCancel' => $pCancel, 'cancelStatus' => $cancelStatus] = $this->createBookingWithMixedStatusPassengers();

        $service = $this->serviceWithCost(0);
        $cancelledBooking = $service->initiateCancellation($booking, [
            'cancellation_branch_id' => $branch->id,
        ]);

        $this->assertDatabaseHas('passengers', ['id' => $pCancel->id, 'passenger_status_id' => $cancelStatus->id]);

        $service->revertCancellation($cancelledBooking);

        $this->assertEquals('Cancel', $pCancel->fresh()->status->name);
        $this->assertFalse($pCancel->fresh()->is_cancelled);
    }

    public function test_initiate_does_not_touch_is_cancelled(): void
    {
        ['booking' => $booking, 'branch' => $branch, 'pNull' => $pNull, 'pHold' => $pHold, 'pCancel' => $pCancel, 'pDelivered' => $pDelivered] = $this->createBookingWithMixedStatusPassengers();

        $this->serviceWithCost(0)->initiateCancellation($booking, [
            'cancellation_branch_id' => $branch->id,
        ]);

        $this->assertFalse($pNull->fresh()->is_cancelled);
        $this->assertFalse($pHold->fresh()->is_cancelled);
        $this->assertFalse($pCancel->fresh()->is_cancelled);
        $this->assertFalse($pDelivered->fresh()->is_cancelled);
    }

    public function test_confirm_zeroes_refund_payable_before_flag_flip(): void
    {
        ['booking' => $booking, 'branch' => $branch, 'pNull' => $pNull] = $this->createBookingWithMixedStatusPassengers();

        $pNull->update(['refund_payable' => 500]);

        $service = $this->serviceWithCost(0);
        $cancelledBooking = $service->initiateCancellation($booking, [
            'cancellation_branch_id' => $branch->id,
        ]);

        $service->confirmCancellation($cancelledBooking, [
            'payment_method' => 'cash',
            'refund_amount' => 0,
        ]);

        $this->assertEquals(0, (float) $pNull->fresh()->refund_payable);
        $this->assertTrue($pNull->fresh()->is_cancelled);
    }
}
