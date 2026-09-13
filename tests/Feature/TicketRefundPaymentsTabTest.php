<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Branch;
use App\Models\CurrencyRate;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Passenger;
use App\Models\Payment;
use App\Models\Role;
use App\Models\TransactionType;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TicketRefundPaymentsTabTest extends TestCase
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

    private Branch $branch;

    private TransactionType $refundType;

    private TransactionType $otherType;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::disableForeignKeyConstraints();
        foreach (['vouchers', 'payments', 'transaction_types', 'passengers', 'invoices', 'bookings', 'currency_rates', 'customers', 'branches', 'users', 'user_roles', 'roles'] as $t) {
            Schema::dropIfExists($t);
        }

        Schema::create('roles', function ($table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        Schema::create('branches', function ($table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

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

        Schema::create('user_roles', function ($table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
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

        Schema::create('bookings', function ($table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->foreignId('booking_branch_id')->constrained('branches')->restrictOnDelete();
            $table->string('invoice_id')->nullable();
            $table->integer('pax_qty')->default(1);
            $table->decimal('total_value', 14, 6)->default(0);
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

        Schema::create('passengers', function ($table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings')->restrictOnDelete();
            $table->string('first_name');
            $table->string('last_name')->nullable();
            $table->decimal('refund_payable', 14, 6)->default(0);
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

        foreach (['Super Admin', 'Co Admin', 'Branch Manager', 'Fingerprint Admin'] as $name) {
            Role::create(['name' => $name]);
        }

        $this->refundType = TransactionType::create(['name' => 'Ticket Refund - Payment', 'type' => 'debit']);
        $this->otherType = TransactionType::create(['name' => 'Initial Payment', 'type' => 'credit']);

        CurrencyRate::create(['rate' => 32.5]);

        $this->branch = Branch::create(['name' => 'Main']);
        $this->admin = User::factory()->create();
        $this->admin->roles()->attach(Role::where('name', 'Super Admin')->first());
    }

    private function makeRefundPayment(Branch $branch, array $overrides = []): Payment
    {
        $user = $overrides['user'] ?? $this->admin;
        $customer = Customer::create(['name' => $overrides['customer_name'] ?? 'Test Customer']);
        $booking = Booking::create([
            'user_id' => $user->id,
            'customer_id' => $customer->id,
            'booking_branch_id' => $branch->id,
            'invoice_id' => $overrides['invoice_id'] ?? ('BM-'.uniqid()),
            'pax_qty' => 1,
            'total_value' => 1000,
        ]);
        $invoice = Invoice::create([
            'booking_id' => $booking->id,
            'branch_id' => $branch->id,
            'user_id' => $user->id,
            'total_amount' => 1000,
            'paid_amount' => 0,
            'balance' => 1000,
            'status' => 'pending',
        ]);
        $passenger = Passenger::create([
            'booking_id' => $booking->id,
            'first_name' => $overrides['first_name'] ?? 'Test',
            'last_name' => $overrides['last_name'] ?? 'Passenger',
            'refund_payable' => 0,
        ]);
        $payment = Payment::create([
            'invoice_id' => $invoice->id,
            'booking_id' => $booking->id,
            'branch_id' => $branch->id,
            'user_id' => $user->id,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'cash',
            'amount' => $overrides['amount'] ?? 500,
            'bdt_amount' => 0,
            'passenger_id' => $passenger->id,
        ]);
        Voucher::create([
            'voucher_id' => 'VCH-'.uniqid(),
            'invoice_id' => $invoice->id,
            'booking_id' => $booking->id,
            'payment_id' => $payment->id,
            'branch_id' => $branch->id,
            'user_id' => $user->id,
            'transaction_type_id' => ($overrides['transaction_type_id'] ?? $this->refundType->id),
            'payment_date' => now()->toDateString(),
            'payment_method' => 'cash',
            'amount' => $payment->amount,
            'bdt_amount' => 0,
        ]);

        return $payment->fresh();
    }

    public function test_index_renders_ticket_refund_payments_tab(): void
    {
        $response = $this->actingAs($this->admin)->get(route('ticket-refund-payments.index'));

        $response->assertOk();
        $response->assertSee('Ticket Refund Payments');
    }

    public function test_api_returns_paid_record_with_routes(): void
    {
        $payment = $this->makeRefundPayment($this->branch);

        $response = $this->actingAs($this->admin)->getJson(route('api.ticket-refund-payments.data'));

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals($payment->id, $data[0]['id']);
        $this->assertEquals('paid', $data[0]['status']);
        $this->assertArrayHasKey('show_route', $data[0]);
        $this->assertArrayHasKey('print_route', $data[0]);
        $this->assertStringContainsString((string) $payment->id, $data[0]['show_route']);
    }

    public function test_non_matching_transaction_types_excluded(): void
    {
        $this->makeRefundPayment($this->branch, ['transaction_type_id' => $this->otherType->id]);

        $response = $this->actingAs($this->admin)->getJson(route('api.ticket-refund-payments.data'));

        $response->assertOk();
        $this->assertCount(0, $response->json('data'));
    }

    public function test_payment_without_voucher_is_excluded(): void
    {
        $payment = $this->makeRefundPayment($this->branch);
        $payment->voucher()->delete();

        $response = $this->actingAs($this->admin)->getJson(route('api.ticket-refund-payments.data'));

        $response->assertOk();
        $this->assertCount(0, $response->json('data'));
    }

    public function test_branch_manager_sees_only_own_branch(): void
    {
        $otherBranch = Branch::create(['name' => 'Other']);
        $this->makeRefundPayment($this->branch);
        $this->makeRefundPayment($otherBranch);

        $manager = User::factory()->create(['branch_id' => $this->branch->id]);
        $manager->roles()->attach(Role::where('name', 'Branch Manager')->first());

        $response = $this->actingAs($manager)->getJson(route('api.ticket-refund-payments.data'));

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    public function test_super_admin_sees_all_branches(): void
    {
        $otherBranch = Branch::create(['name' => 'Other']);
        $this->makeRefundPayment($this->branch);
        $this->makeRefundPayment($otherBranch);

        $response = $this->actingAs($this->admin)->getJson(route('api.ticket-refund-payments.data'));

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
    }

    public function test_show_and_print_return_200(): void
    {
        $payment = $this->makeRefundPayment($this->branch);

        $this->actingAs($this->admin)->get(route('ticket-refund-payments.show', $payment->id))->assertOk();
        $this->actingAs($this->admin)->get(route('ticket-refund-payments.print', $payment->id))->assertOk();
    }

    public function test_show_other_branch_forbidden_for_branch_user(): void
    {
        $otherBranch = Branch::create(['name' => 'Other']);
        $payment = $this->makeRefundPayment($otherBranch);

        $manager = User::factory()->create(['branch_id' => $this->branch->id]);
        $manager->roles()->attach(Role::where('name', 'Branch Manager')->first());

        $this->actingAs($manager)->get(route('ticket-refund-payments.show', $payment->id))->assertForbidden();
    }

    public function test_non_existent_payment_returns_404(): void
    {
        $this->actingAs($this->admin)->get(route('ticket-refund-payments.show', 999999))->assertNotFound();
    }

    public function test_pagination_works(): void
    {
        for ($i = 0; $i < 25; $i++) {
            $this->makeRefundPayment($this->branch);
        }

        $response = $this->actingAs($this->admin)->getJson(route('api.ticket-refund-payments.data', ['page' => 2]));

        $response->assertOk();
        $this->assertEquals(2, $response->json('pagination.current_page'));
        $this->assertEquals(20, $response->json('pagination.per_page'));
        $this->assertEquals(25, $response->json('pagination.total'));
        $this->assertCount(5, $response->json('data'));
    }

    public function test_search_by_invoice_customer_and_passenger(): void
    {
        $this->makeRefundPayment($this->branch, ['invoice_id' => 'BM-SEARCH-1', 'customer_name' => 'SearchCustomer', 'first_name' => 'SearchFirst']);

        $this->actingAs($this->admin)->getJson(route('api.ticket-refund-payments.data', ['search' => 'BM-SEARCH-1']))
            ->assertOk()->assertJsonCount(1, 'data');

        $this->actingAs($this->admin)->getJson(route('api.ticket-refund-payments.data', ['search' => 'SearchCustomer']))
            ->assertOk()->assertJsonCount(1, 'data');

        $this->actingAs($this->admin)->getJson(route('api.ticket-refund-payments.data', ['search' => 'SearchFirst']))
            ->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_branch_filter_via_query_parameter(): void
    {
        $otherBranch = Branch::create(['name' => 'Other']);
        $this->makeRefundPayment($this->branch);
        $this->makeRefundPayment($otherBranch);

        $response = $this->actingAs($this->admin)
            ->getJson(route('api.ticket-refund-payments.data', ['branch_id' => $otherBranch->id]));

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    public function test_currency_rate_passed_to_print_view(): void
    {
        $payment = $this->makeRefundPayment($this->branch);

        $response = $this->actingAs($this->admin)->get(route('ticket-refund-payments.print', $payment->id));

        $response->assertOk();
        $response->assertViewHas('currencyRate', 32.5);
    }
}
