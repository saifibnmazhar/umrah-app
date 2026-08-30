<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Passenger;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class VisaHoldTest extends TestCase
{
    use RefreshDatabase;

    // Override to avoid starting a DB transaction. DDL (Schema::create)
    // in setUp() on MySQL implicitly commits, which breaks nested
    // DB::transaction() calls in services.
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

        // Drop tables that reference the ones we recreate with simplified schemas.
        foreach (['payments', 'invoices', 'cancelled_passengers', 'bookings', 'passengers',
            'passenger_statuses', 'currency_rates', 'customers', 'branches'] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('branches', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('address')->nullable();
            $table->string('contacts')->nullable();
            $table->boolean('fingerprint_operation')->default(false);
            $table->string('location')->nullable();
            $table->string('branch_code')->nullable();
            $table->timestamps();
        });

        Schema::create('customers', function ($table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('currency_rates', function ($table) {
            $table->id();
            $table->decimal('sar_to_bdt', 10, 4)->default(1);
            $table->timestamps();
        });

        Schema::create('bookings', function ($table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('booking_branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('fingerprint_branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->integer('pax_qty')->default(1);
            $table->decimal('total_value', 14, 6)->default(0);
            $table->decimal('discount_amount', 14, 6)->default(0);
            $table->decimal('profit', 14, 6)->default(0);
            $table->boolean('is_cancelled')->default(false);
            $table->timestamps();
        });

        Schema::create('passenger_statuses', function ($table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        Schema::create('passengers', function ($table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('passenger_status_id')->nullable()->constrained('passenger_statuses')->nullOnDelete();
            $table->string('first_name');
            $table->string('last_name');
            $table->boolean('is_ticket_held')->default(false);
            $table->boolean('is_visa_held')->default(false);
            $table->foreignId('visa_held_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('visa_held_at')->nullable();
            $table->boolean('is_cancelled')->default(false);
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
        });

        Schema::create('invoices', function ($table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('total_amount', 14, 6)->default(0);
            $table->decimal('paid_amount', 14, 6)->default(0);
            $table->decimal('balance', 14, 6)->default(0);
            $table->enum('status', ['pending', 'partial', 'paid', 'cancelled', 'refunded'])->default('pending');
            $table->timestamps();
        });

        Schema::enableForeignKeyConstraints();
    }

    private function createUserWithRole(string $roleName): User
    {
        $role = Role::where('name', $roleName)->firstOrCreate(['name' => $roleName]);
        $user = User::factory()->create();
        $user->roles()->attach($role);

        return $user;
    }

    private function createBookingWithPassenger(): array
    {
        $owner = User::factory()->create();
        $customer = Customer::create(['name' => 'Test Customer']);
        $branch = Branch::create(['name' => 'Main Branch']);

        $booking = Booking::create([
            'user_id' => $owner->id,
            'customer_id' => $customer->id,
            'booking_branch_id' => $branch->id,
            'pax_qty' => 1,
        ]);

        $passenger = Passenger::create([
            'booking_id' => $booking->id,
            'first_name' => 'Test',
            'last_name' => 'Passenger',
        ]);

        return compact('owner', 'customer', 'branch', 'booking', 'passenger');
    }

    public function test_visa_hold_can_be_applied(): void
    {
        $admin = $this->createUserWithRole('Visa Admin');
        ['passenger' => $passenger] = $this->createBookingWithPassenger();

        $this->actingAs($admin)
            ->patchJson(route('passengers.toggle-visa-hold', $passenger->id))
            ->assertOk()
            ->assertJson(['success' => true, 'is_visa_held' => true]);

        $passenger->refresh();
        $this->assertTrue($passenger->is_visa_held);
        $this->assertEquals($admin->id, $passenger->visa_held_by);
        $this->assertNotNull($passenger->visa_held_at);
    }

    public function test_visa_hold_can_be_released(): void
    {
        $admin = $this->createUserWithRole('Visa Admin');
        ['passenger' => $passenger] = $this->createBookingWithPassenger();

        $passenger->update([
            'is_visa_held' => true,
            'visa_held_by' => $admin->id,
            'visa_held_at' => now(),
        ]);

        $this->actingAs($admin)
            ->patchJson(route('passengers.toggle-visa-hold', $passenger->id))
            ->assertOk()
            ->assertJson(['success' => true, 'is_visa_held' => false]);

        $passenger->refresh();
        $this->assertFalse($passenger->is_visa_held);
        $this->assertNull($passenger->visa_held_by);
        $this->assertNull($passenger->visa_held_at);
    }

    public function test_visa_submit_blocked_when_visa_held(): void
    {
        $admin = $this->createUserWithRole('Visa Admin');
        ['booking' => $booking, 'passenger' => $passenger] = $this->createBookingWithPassenger();
        $passenger->update(['is_visa_held' => true]);

        $this->actingAs($admin)
            ->postJson(route('bookings.passengers.visa-submit', [$booking->id, $passenger->id]))
            ->assertStatus(422);
    }

    public function test_visa_issue_blocked_when_visa_held(): void
    {
        $admin = $this->createUserWithRole('Visa Admin');
        ['booking' => $booking, 'passenger' => $passenger] = $this->createBookingWithPassenger();
        $passenger->update(['is_visa_held' => true]);

        $this->actingAs($admin)
            ->postJson(route('bookings.passengers.visa-issue', [$booking->id, $passenger->id]))
            ->assertStatus(422);
    }

    public function test_visa_edit_blocked_when_visa_held(): void
    {
        $admin = $this->createUserWithRole('Visa Admin');
        ['booking' => $booking, 'passenger' => $passenger] = $this->createBookingWithPassenger();
        $passenger->update(['is_visa_held' => true]);

        $this->actingAs($admin)
            ->putJson(route('bookings.passengers.visa-edit', [$booking->id, $passenger->id]))
            ->assertStatus(422);
    }

    public function test_visa_cancel_blocked_when_visa_held(): void
    {
        $admin = $this->createUserWithRole('Visa Admin');
        ['booking' => $booking, 'passenger' => $passenger] = $this->createBookingWithPassenger();
        $passenger->update(['is_visa_held' => true]);

        $this->actingAs($admin)
            ->postJson(route('bookings.passengers.visa-cancel', [$booking->id, $passenger->id]))
            ->assertStatus(422);
    }

    public function test_visa_resubmit_blocked_when_visa_held(): void
    {
        $admin = $this->createUserWithRole('Visa Admin');
        ['booking' => $booking, 'passenger' => $passenger] = $this->createBookingWithPassenger();
        $passenger->update(['is_visa_held' => true]);

        $this->actingAs($admin)
            ->postJson(route('bookings.passengers.visa-resubmit', [$booking->id, $passenger->id]))
            ->assertStatus(422);
    }

    public function test_visa_staff_cannot_toggle_visa_hold(): void
    {
        $staff = $this->createUserWithRole('Visa Staff');
        ['passenger' => $passenger] = $this->createBookingWithPassenger();

        $this->actingAs($staff)
            ->patchJson(route('passengers.toggle-visa-hold', $passenger->id))
            ->assertForbidden();
    }

    public function test_visa_admin_can_toggle_visa_hold(): void
    {
        $admin = $this->createUserWithRole('Visa Admin');
        ['passenger' => $passenger] = $this->createBookingWithPassenger();

        $this->actingAs($admin)
            ->patchJson(route('passengers.toggle-visa-hold', $passenger->id))
            ->assertOk()
            ->assertJson(['success' => true, 'is_visa_held' => true]);
    }
}
