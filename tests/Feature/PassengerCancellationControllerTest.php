<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Branch;
use App\Models\CancelledPassenger;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Passenger;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PassengerCancellationControllerTest extends TestCase
{
    use RefreshDatabase;

    // Override to avoid starting a DB transaction. DDL (Schema::create)
    // in setUp() on MySQL implicitly commits, which breaks nested
    // DB::transaction() calls in the service layer.
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

        // Drop tables that already exist from migrations so we can recreate
        // simplified schemas for this test.
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('payments');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('cancelled_passengers');
        Schema::dropIfExists('bookings');
        Schema::dropIfExists('passengers');
        Schema::dropIfExists('passenger_statuses');
        Schema::dropIfExists('user_roles');
        Schema::dropIfExists('roles');
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
            $table->foreignId('confirmed_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reverted_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
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

        Schema::enableForeignKeyConstraints();
    }

    private function createRoles(): void
    {
        Role::create(['name' => 'Super Admin']);
        Role::create(['name' => 'Co Admin']);
        Role::create(['name' => 'Branch Manager']);
        Role::create(['name' => 'Fingerprint Admin']);
        Role::create(['name' => 'Delivery Staff']);
    }

    private function createUserWithRole(string $roleName, ?int $branchId = null): User
    {
        $user = User::factory()->create(['branch_id' => $branchId]);
        $role = Role::where('name', $roleName)->first();
        $user->roles()->attach($role);

        return $user;
    }

    private function createBookingWithPassengers(int $passengerCount = 2): array
    {
        $user = User::factory()->create();
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

    public function test_preview_requires_auth(): void
    {
        $this->createRoles();

        ['passengers' => $passengers] = $this->createBookingWithPassengers();
        $passenger = $passengers->first();

        $this->getJson(route('passengers.cancellation.preview', $passenger->id))
            ->assertForbidden();
    }

    public function test_preview_requires_super_or_co_admin(): void
    {
        $this->createRoles();
        $staff = $this->createUserWithRole('Delivery Staff');

        ['passengers' => $passengers] = $this->createBookingWithPassengers();
        $passenger = $passengers->first();

        $this->actingAs($staff)
            ->getJson(route('passengers.cancellation.preview', $passenger->id))
            ->assertForbidden();
    }

    public function test_preview_returns_json_for_authorized(): void
    {
        $this->createRoles();
        $admin = $this->createUserWithRole('Super Admin');

        ['passengers' => $passengers] = $this->createBookingWithPassengers();
        $passenger = $passengers->first();

        $this->actingAs($admin)
            ->getJson(route('passengers.cancellation.preview', $passenger->id))
            ->assertOk()
            ->assertJsonStructure([
                'package_value',
                'visa_cost',
                'ticket_cost',
                'total_cost',
                'refund_payable',
                'refundable_amount',
                'branches',
            ]);
    }

    public function test_initiate_requires_super_or_co_admin(): void
    {
        $this->createRoles();
        $staff = $this->createUserWithRole('Delivery Staff');

        ['passengers' => $passengers, 'branch' => $branch] = $this->createBookingWithPassengers();
        $passenger = $passengers->first();

        $this->actingAs($staff)
            ->postJson(route('passengers.cancellation.initiate', $passenger->id), [
                'cancellation_branch_id' => $branch->id,
            ])
            ->assertForbidden();
    }

    public function test_initiate_creates_cancellation(): void
    {
        $this->createRoles();
        $admin = $this->createUserWithRole('Super Admin');

        ['passengers' => $passengers, 'branch' => $branch] = $this->createBookingWithPassengers();
        $passenger = $passengers->first();

        $this->actingAs($admin)
            ->postJson(route('passengers.cancellation.initiate', $passenger->id), [
                'cancellation_branch_id' => $branch->id,
                'service_charge_deduction' => 0,
            ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('cancelled_passengers', [
            'passenger_id' => $passenger->id,
            'status' => 'cancellation processing',
        ]);
    }

    public function test_confirm_requires_branch_manager_or_fingerprint_admin(): void
    {
        $this->createRoles();
        $admin = $this->createUserWithRole('Super Admin');
        $staff = $this->createUserWithRole('Delivery Staff');

        ['passengers' => $passengers, 'branch' => $branch] = $this->createBookingWithPassengers();
        $passenger = $passengers->first();

        $this->actingAs($admin)
            ->postJson(route('passengers.cancellation.initiate', $passenger->id), [
                'cancellation_branch_id' => $branch->id,
            ]);

        $cp = CancelledPassenger::where('passenger_id', $passenger->id)->first();

        $this->actingAs($staff)
            ->postJson(route('cancelled-passengers.confirm.submit', $cp->id), [
                'payment_method' => 'cash',
                'balance_adjusted_amount' => 2500,
            ])
            ->assertForbidden();
    }

    public function test_revert_requires_branch_manager_or_fingerprint_admin(): void
    {
        $this->createRoles();
        $admin = $this->createUserWithRole('Super Admin');
        $staff = $this->createUserWithRole('Delivery Staff');

        ['passengers' => $passengers, 'branch' => $branch] = $this->createBookingWithPassengers();
        $passenger = $passengers->first();

        $this->actingAs($admin)
            ->postJson(route('passengers.cancellation.initiate', $passenger->id), [
                'cancellation_branch_id' => $branch->id,
            ]);

        $cp = CancelledPassenger::where('passenger_id', $passenger->id)->first();

        $this->actingAs($staff)
            ->postJson(route('cancelled-passengers.revert', $cp->id))
            ->assertForbidden();
    }

    public function test_branch_scoping_enforced(): void
    {
        $this->createRoles();

        $branch1 = Branch::create(['name' => 'Branch 1']);
        $branch2 = Branch::create(['name' => 'Branch 2']);

        $user = User::factory()->create(['branch_id' => $branch1->id]);
        $role = Role::where('name', 'Fingerprint Admin')->first();
        $user->roles()->attach($role);

        $customer = Customer::create(['name' => 'Test Customer']);
        $bookingUser = User::factory()->create();

        $booking = Booking::create([
            'user_id' => $bookingUser->id,
            'customer_id' => $customer->id,
            'booking_branch_id' => $branch2->id,
            'pax_qty' => 2,
            'total_value' => 5000.00,
        ]);

        Invoice::create([
            'booking_id' => $booking->id,
            'branch_id' => $branch2->id,
            'user_id' => $bookingUser->id,
            'total_amount' => 5000.00,
            'paid_amount' => 3000.00,
            'balance' => 2000.00,
        ]);

        $passenger1 = Passenger::create([
            'booking_id' => $booking->id,
            'first_name' => 'Passenger 1',
            'last_name' => 'Test',
            'package_value' => 2500.00,
        ]);

        $passenger2 = Passenger::create([
            'booking_id' => $booking->id,
            'first_name' => 'Passenger 2',
            'last_name' => 'Test',
            'package_value' => 2500.00,
        ]);

        // Admin in branch1 initiates cancellation for passenger on a booking in branch2
        $superAdmin = $this->createUserWithRole('Super Admin');
        $this->actingAs($superAdmin)
            ->postJson(route('passengers.cancellation.initiate', $passenger1->id), [
                'cancellation_branch_id' => $branch2->id,
            ]);

        $cp = CancelledPassenger::where('passenger_id', $passenger1->id)->first();

        // User in branch1 trying to confirm a cancellation in branch2 should be blocked
        $this->actingAs($user)
            ->postJson(route('cancelled-passengers.revert', $cp->id))
            ->assertForbidden();
    }

    public function test_initiate_validation(): void
    {
        $this->createRoles();
        $admin = $this->createUserWithRole('Super Admin');

        ['passengers' => $passengers] = $this->createBookingWithPassengers();
        $passenger = $passengers->first();

        $this->actingAs($admin)
            ->postJson(route('passengers.cancellation.initiate', $passenger->id), [])
            ->assertJsonValidationErrors(['cancellation_branch_id']);
    }
}
