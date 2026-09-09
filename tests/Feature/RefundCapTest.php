<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Branch;
use App\Models\CurrencyRate;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\TransactionType;
use App\Models\User;
use App\Models\Voucher;
use App\Services\RefundCapService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RefundCapTest extends TestCase
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

    protected function setUp(): void
    {
        parent::setUp();

        Schema::disableForeignKeyConstraints();
        foreach (['vouchers', 'payments', 'invoices', 'bookings', 'transaction_types', 'currency_rates', 'customers', 'branches'] as $t) {
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
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('currency', 3)->default('BDT');
            $table->decimal('rate', 10, 4)->default(0);
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

        Schema::enableForeignKeyConstraints();
    }

    private function seedInvoiceWithVouchers(float $initial = 600, float $due = 400, float $refunded = 300): Invoice
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
            'total_value' => 2000,
        ]);
        $invoice = Invoice::create([
            'booking_id' => $booking->id,
            'branch_id' => $branch->id,
            'user_id' => $user->id,
            'total_amount' => 2000,
            'paid_amount' => $initial + $due,
            'balance' => 1000,
        ]);

        $initialType = TransactionType::create(['name' => 'Initial Payment', 'type' => 'credit']);
        $dueType = TransactionType::create(['name' => 'Due Collection', 'type' => 'credit']);
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

        $make($initialType, $initial);
        $make($dueType, $due);
        if ($refunded > 0) {
            $make($refundType, $refunded);
        }

        return $invoice->fresh();
    }

    public function test_cap_equals_paid_minus_refunded(): void
    {
        $invoice = $this->seedInvoiceWithVouchers(600, 400, 300);

        $cap = app(RefundCapService::class)->getCap($invoice);

        $this->assertEquals(1000.0, $cap['paid']);
        $this->assertEquals(300.0, $cap['refunded']);
        $this->assertEquals(700.0, $cap['remaining']);
    }

    public function test_refund_over_remaining_is_rejected(): void
    {
        $invoice = $this->seedInvoiceWithVouchers(600, 400, 300);

        $this->expectException(ValidationException::class);

        app(RefundCapService::class)->assertRefundAllowed($invoice, 701);
    }

    public function test_refund_at_remaining_is_allowed(): void
    {
        $invoice = $this->seedInvoiceWithVouchers(600, 400, 300);

        app(RefundCapService::class)->assertRefundAllowed($invoice, 700);

        $this->assertTrue(true);
    }

    public function test_bdt_amount_converted_to_sar_before_compare(): void
    {
        $user = User::first();
        CurrencyRate::create(['user_id' => $user->id, 'currency' => 'BDT', 'rate' => 30]);
        $invoice = $this->seedInvoiceWithVouchers(600, 400, 300);

        $service = app(RefundCapService::class);
        $sar = $service->normalizeToSar(21000, 'BDT');

        $this->assertEquals(700.0, $sar);
        $service->assertRefundAllowed($invoice, $sar);

        $this->expectException(ValidationException::class);
        $service->assertRefundAllowed($invoice, $service->normalizeToSar(21030, 'BDT'));
    }
}
