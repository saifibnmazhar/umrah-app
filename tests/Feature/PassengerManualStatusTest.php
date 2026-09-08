<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Passenger;
use App\Models\PassengerStatus;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PassengerManualStatusTest extends TestCase
{
    use RefreshDatabase;

    protected function beginDatabaseTransaction(): void {}

    protected function setUp(): void
    {
        parent::setUp();
        Schema::disableForeignKeyConstraints();
        foreach (['payments', 'invoices', 'cancelled_passengers', 'bookings', 'passengers', 'passenger_statuses', 'currency_rates', 'customers', 'branches'] as $table) {
            Schema::dropIfExists($table);
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
            $table->decimal('sar_to_bdt', 10, 4)->default(1);
            $table->timestamps();
        });
        Schema::create('bookings', function ($table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('booking_branch_id')->constrained('branches')->cascadeOnDelete();
            $table->integer('pax_qty')->default(1);
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
            $table->timestamps();
        });
        Schema::create('cancelled_passengers', function ($table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('passenger_id')->constrained('passengers')->cascadeOnDelete();
            $table->softDeletes();
            $table->timestamps();
        });
        Schema::enableForeignKeyConstraints();
    }

    private function makePassenger(?string $statusName = null): Passenger
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
        $statusId = $statusName ? PassengerStatus::firstOrCreate(['name' => $statusName])->id : null;

        return Passenger::create([
            'booking_id' => $booking->id,
            'first_name' => 'Test',
            'last_name' => 'Passenger',
            'passenger_status_id' => $statusId,
        ]);
    }

    public function test_sync_keeps_manual_statuses_untouched(): void
    {
        foreach (['Hold', 'Cancel', 'Delivered', 'Ticket Refund Done', 'Departure Done'] as $manual) {
            $passenger = $this->makePassenger($manual);
            $passenger->syncComputedStatus();
            $passenger->refresh();
            $this->assertEquals($manual, $passenger->status?->name, "manual {$manual} must survive sync");
        }
    }

    public function test_sync_clears_computed_status_to_null(): void
    {
        $passenger = $this->makePassenger('Visa Issued');
        $passenger->syncComputedStatus();
        $passenger->refresh();
        $this->assertNull($passenger->passenger_status_id);
        $this->assertNull($passenger->status);
    }

    public function test_sync_does_not_persist_computed_status(): void
    {
        $passenger = $this->makePassenger(null);
        $passenger->syncComputedStatus();
        $passenger->refresh();
        $this->assertNull($passenger->passenger_status_id);
    }

    public function test_display_status_prefers_manual_over_computed(): void
    {
        $passenger = $this->makePassenger('Delivered');
        $this->assertEquals('Delivered', $passenger->display_status);
    }

    public function test_display_status_falls_back_to_computed_when_null(): void
    {
        $passenger = $this->makePassenger(null);
        $passenger->setRelation('fingerprintDetail', null);
        $passenger->setRelation('visaSubmission', null);
        $passenger->setRelation('allIssuedTickets', collect([]));
        $passenger->setRelation('latestIssuedTicket', null);
        $this->assertNull($passenger->display_status);
    }

    private function adminUser(): User
    {
        $admin = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'Super Admin']);
        $admin->roles()->syncWithoutDetaching([$role->id]);

        return $admin;
    }

    public function test_update_status_rejects_computed_name(): void
    {
        $admin = $this->adminUser();
        $passenger = $this->makePassenger(null);
        $computedId = PassengerStatus::firstOrCreate(['name' => 'Visa Issued'])->id;
        $this->actingAs($admin)
            ->patchJson(route('passengers.update-status', $passenger->id), ['passenger_status_id' => $computedId])
            ->assertStatus(422);
        $this->assertNull($passenger->fresh()->passenger_status_id);
    }

    public function test_update_status_accepts_manual_and_null(): void
    {
        $admin = $this->adminUser();
        $passenger = $this->makePassenger(null);
        $manualId = PassengerStatus::firstOrCreate(['name' => 'Delivered'])->id;
        $this->actingAs($admin)
            ->patchJson(route('passengers.update-status', $passenger->id), ['passenger_status_id' => $manualId])
            ->assertOk();
        $this->assertEquals('Delivered', $passenger->fresh()->status?->name);
        $this->actingAs($admin)
            ->patchJson(route('passengers.update-status', $passenger->id), ['passenger_status_id' => null])
            ->assertOk();
        $this->assertNull($passenger->fresh()->passenger_status_id);
    }
}
