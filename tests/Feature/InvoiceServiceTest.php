<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Models\Booking;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class InvoiceServiceTest extends TestCase
{
    use RefreshDatabase;

    protected InvoiceService $service;

    // Override to avoid starting a DB transaction. DDL (Schema::create)
    // in setUp() on MySQL implicitly commits, which breaks the
    // RefreshDatabase transaction state.
    protected function beginDatabaseTransaction(): void
    {
        // no-op
    }

    public static function tearDownAfterClass(): void
    {
        // Restore migration schema so other test classes aren't affected.
        try {
            Artisan::call('migrate:fresh');
        } catch (\Throwable $e) {
            RefreshDatabaseState::$migrated = false;
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(InvoiceService::class);

        // Drop tables that already exist from migrations so we can recreate
        // simplified schemas for this test.
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('payments');
        Schema::dropIfExists('cancelled_passengers');
        Schema::dropIfExists('cancelled_bookings');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('re_issued_tickets');
        Schema::dropIfExists('refunded_tickets');
        Schema::dropIfExists('passengers');
        Schema::dropIfExists('bookings');
        Schema::dropIfExists('customers');
        Schema::dropIfExists('branches');

        Schema::create('branches', function ($table) {
            $table->id();
            $table->string('name');
            $table->text('address')->nullable();
            $table->text('contacts')->nullable();
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
            $table->decimal('discount_amount', 14, 6)->default(0);
            $table->boolean('is_cancelled')->default(false);
            $table->timestamps();
        });

        Schema::create('passengers', function ($table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings')->restrictOnDelete();
            $table->string('first_name');
            $table->string('last_name');
            $table->decimal('package_value', 12, 2)->default(0);
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

        Schema::create('cancelled_bookings', function ($table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('cancelled_passengers', function ($table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings')->restrictOnDelete();
            $table->foreignId('passenger_id')->constrained('passengers')->restrictOnDelete();
            $table->foreignId('invoice_id')->constrained('invoices')->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->decimal('package_value', 14, 6);
            $table->foreignId('cancellation_branch_id')->constrained('branches')->restrictOnDelete();
            $table->enum('status', ['cancellation processing', 'cancelled'])->default('cancellation processing');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('refunded_tickets', function ($table) {
            $table->id();
            $table->timestamps();
        });

        Schema::create('re_issued_tickets', function ($table) {
            $table->id();
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
            $table->enum('payment_method', ['cash', 'bank'])->default('cash');
            $table->date('payment_date');
            $table->foreignId('cancelled_booking_id')->nullable()->constrained('cancelled_bookings')->nullOnDelete();
            $table->foreignId('cancelled_passenger_id')->nullable()->constrained('cancelled_passengers')->nullOnDelete();
            $table->foreignId('refunded_ticket_id')->nullable()->constrained('refunded_tickets')->nullOnDelete();
            $table->foreignId('re_issued_ticket_id')->nullable()->constrained('re_issued_tickets')->nullOnDelete();
            $table->timestamps();
        });

        Schema::enableForeignKeyConstraints();
    }

    private function createInvoiceWithPayments(): array
    {
        $user = User::factory()->create();
        $customer = Customer::create(['name' => 'Test Customer']);
        $branch = Branch::create(['name' => 'Main Branch']);

        $booking = Booking::create([
            'user_id' => $user->id,
            'customer_id' => $customer->id,
            'booking_branch_id' => $branch->id,
            'pax_qty' => 2,
            'total_value' => 5000.00,
        ]);

        $invoice = Invoice::create([
            'booking_id' => $booking->id,
            'branch_id' => $branch->id,
            'user_id' => $user->id,
            'total_amount' => 5000.00,
            'paid_amount' => 0,
            'balance' => 5000.00,
        ]);

        return compact('user', 'booking', 'invoice');
    }

    public function test_regular_payments_counted(): void
    {
        ['invoice' => $invoice, 'user' => $user, 'booking' => $booking] = $this->createInvoiceWithPayments();

        Payment::create([
            'invoice_id' => $invoice->id,
            'booking_id' => $booking->id,
            'user_id' => $user->id,
            'amount' => 1000,
            'bdt_amount' => 0,
            'payment_method' => 'cash',
            'payment_date' => now(),
        ]);

        $this->service->updatePaymentStatus($invoice->fresh());

        $this->assertEquals(1000.00, (float) $invoice->fresh()->paid_amount);
    }

    public function test_cancelled_booking_payments_excluded(): void
    {
        ['invoice' => $invoice, 'user' => $user, 'booking' => $booking] = $this->createInvoiceWithPayments();

        DB::table('cancelled_bookings')->insert(['id' => 1, 'booking_id' => $booking->id, 'created_at' => now(), 'updated_at' => now()]);

        Payment::create([
            'invoice_id' => $invoice->id,
            'booking_id' => $booking->id,
            'user_id' => $user->id,
            'amount' => 1000,
            'bdt_amount' => 0,
            'payment_method' => 'cash',
            'payment_date' => now(),
        ]);

        Payment::create([
            'invoice_id' => $invoice->id,
            'booking_id' => $booking->id,
            'user_id' => $user->id,
            'amount' => 500,
            'bdt_amount' => 0,
            'payment_method' => 'cash',
            'payment_date' => now(),
            'cancelled_booking_id' => 1,
        ]);

        $this->service->updatePaymentStatus($invoice->fresh());

        $this->assertEquals(1000.00, (float) $invoice->fresh()->paid_amount);
    }

    public function test_cancelled_passenger_payments_excluded(): void
    {
        ['invoice' => $invoice, 'user' => $user, 'booking' => $booking] = $this->createInvoiceWithPayments();

        DB::table('passengers')->insert(['id' => 1, 'booking_id' => $booking->id, 'first_name' => 'Test', 'last_name' => 'Pax', 'package_value' => 0, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('cancelled_passengers')->insert(['id' => 1, 'booking_id' => $booking->id, 'passenger_id' => 1, 'invoice_id' => $invoice->id, 'user_id' => $user->id, 'package_value' => 0, 'cancellation_branch_id' => $invoice->branch_id, 'created_at' => now(), 'updated_at' => now()]);

        Payment::create([
            'invoice_id' => $invoice->id,
            'booking_id' => $booking->id,
            'user_id' => $user->id,
            'amount' => 800,
            'bdt_amount' => 0,
            'payment_method' => 'cash',
            'payment_date' => now(),
        ]);

        Payment::create([
            'invoice_id' => $invoice->id,
            'booking_id' => $booking->id,
            'user_id' => $user->id,
            'amount' => 200,
            'bdt_amount' => 0,
            'payment_method' => 'cash',
            'payment_date' => now(),
            'cancelled_passenger_id' => 1,
        ]);

        $this->service->updatePaymentStatus($invoice->fresh());

        $this->assertEquals(800.00, (float) $invoice->fresh()->paid_amount);
    }

    public function test_refunded_ticket_payments_excluded(): void
    {
        ['invoice' => $invoice, 'user' => $user, 'booking' => $booking] = $this->createInvoiceWithPayments();

        DB::table('refunded_tickets')->insert(['id' => 1, 'created_at' => now(), 'updated_at' => now()]);

        Payment::create([
            'invoice_id' => $invoice->id,
            'booking_id' => $booking->id,
            'user_id' => $user->id,
            'amount' => 1500,
            'bdt_amount' => 0,
            'payment_method' => 'cash',
            'payment_date' => now(),
        ]);

        Payment::create([
            'invoice_id' => $invoice->id,
            'booking_id' => $booking->id,
            'user_id' => $user->id,
            'amount' => 300,
            'bdt_amount' => 0,
            'payment_method' => 'cash',
            'payment_date' => now(),
            'refunded_ticket_id' => 1,
        ]);

        $this->service->updatePaymentStatus($invoice->fresh());

        $this->assertEquals(1500.00, (float) $invoice->fresh()->paid_amount);
    }

    public function test_balance_recomputes_correctly(): void
    {
        ['invoice' => $invoice, 'user' => $user, 'booking' => $booking] = $this->createInvoiceWithPayments();

        Payment::create([
            'invoice_id' => $invoice->id,
            'booking_id' => $booking->id,
            'user_id' => $user->id,
            'amount' => 3000,
            'bdt_amount' => 0,
            'payment_method' => 'cash',
            'payment_date' => now(),
        ]);

        $this->service->updatePaymentStatus($invoice->fresh());

        $this->assertEquals(2000.00, (float) $invoice->fresh()->balance);
        $this->assertEquals(InvoiceStatus::PARTIAL, $invoice->fresh()->status);
    }
}
