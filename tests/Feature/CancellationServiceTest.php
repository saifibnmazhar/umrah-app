<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\TransactionType;
use App\Models\User;
use App\Models\Voucher;
use App\Services\CancellationService;
use App\Services\CostTrackingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class CancellationServiceTest extends TestCase
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
        foreach (['cancelled_bookings', 'vouchers', 'payments', 'invoices', 'bookings', 'transaction_types', 'customers', 'branches'] as $t) {
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
            $table->decimal('amount', 14, 6);
            $table->decimal('bdt_amount', 14, 6)->default(0);
            $table->string('payment_method')->default('cash');
            $table->date('payment_date')->nullable();
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
            $table->foreignId('transaction_type_id')->constrained('transaction_types')->restrictOnDelete();
            $table->decimal('amount', 14, 6);
            $table->decimal('bdt_amount', 14, 6)->default(0);
            $table->string('payment_method')->default('cash');
            $table->date('payment_date')->nullable();
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
            $table->foreignId('cancellation_branch_id')->constrained('branches')->restrictOnDelete();
            $table->enum('status', ['cancellation processing', 'cancelled'])->default('cancellation processing');
            $table->timestamps();
        });

        Schema::enableForeignKeyConstraints();
    }

    private function seedBooking(float $paid, float $priorRefund = 0): array
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $branch = Branch::create(['name' => 'Main']);
        $customer = Customer::create(['name' => 'C']);
        $booking = Booking::create([
            'user_id' => $user->id,
            'customer_id' => $customer->id,
            'booking_branch_id' => $branch->id,
            'pax_qty' => 2,
            'total_value' => 6000,
        ]);
        $invoice = Invoice::create([
            'booking_id' => $booking->id,
            'branch_id' => $branch->id,
            'user_id' => $user->id,
            'total_amount' => 6000,
            'paid_amount' => $paid,
            'balance' => 1000,
        ]);

        $initialType = TransactionType::create(['name' => 'Initial Payment', 'type' => 'credit']);
        $refundType = TransactionType::create(['name' => 'Customer Refund', 'type' => 'debit']);

        $make = function ($type, $amount) use ($invoice, $booking, $branch, $user) {
            $payment = Payment::create([
                'invoice_id' => $invoice->id,
                'booking_id' => $booking->id,
                'branch_id' => $branch->id,
                'user_id' => $user->id,
                'amount' => $amount,
                'bdt_amount' => 0,
                'payment_method' => 'cash',
                'payment_date' => now()->toDateString(),
            ]);
            Voucher::create([
                'voucher_id' => 'VCH-'.uniqid(),
                'invoice_id' => $invoice->id,
                'booking_id' => $booking->id,
                'payment_id' => $payment->id,
                'branch_id' => $branch->id,
                'user_id' => $user->id,
                'transaction_type_id' => $type->id,
                'amount' => $amount,
                'bdt_amount' => 0,
                'payment_method' => 'cash',
                'payment_date' => now()->toDateString(),
            ]);
        };

        $make($initialType, $paid);
        if ($priorRefund > 0) {
            $make($refundType, $priorRefund);
        }

        return compact('user', 'branch', 'booking', 'invoice');
    }

    private function serviceWithCost(float $totalCost): CancellationService
    {
        $cost = Mockery::mock(CostTrackingService::class);
        $cost->shouldReceive('getBookingCostSummary')->andReturn([
            'fingerprint_cost' => 0,
            'visa_cost' => 0,
            'ticket_cost' => $totalCost,
            'total_cost' => $totalCost,
            'passengers' => collect([]),
        ]);

        return new CancellationService($cost);
    }

    public function test_initiate_stores_full_refund_without_prior_refunds(): void
    {
        ['booking' => $booking, 'branch' => $branch] = $this->seedBooking(5000);

        $cancelled = $this->serviceWithCost(1000)->initiateCancellation($booking, [
            'cancellation_branch_id' => $branch->id,
            'service_charge_deduction' => 200,
        ]);

        $this->assertEquals(3800.00, (float) $cancelled->refund_amount);
    }

    public function test_initiate_clamps_snapshot_against_prior_passenger_refunds(): void
    {
        ['booking' => $booking, 'branch' => $branch] = $this->seedBooking(5000, 1500);

        $cancelled = $this->serviceWithCost(1000)->initiateCancellation($booking, [
            'cancellation_branch_id' => $branch->id,
            'service_charge_deduction' => 200,
        ]);

        $this->assertEquals(3500.00, (float) $cancelled->refund_amount);
    }
}
