<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\IssuedTicket;
use App\Models\Passenger;
use App\Models\RefundedTicket;
use App\Models\ReIssuedTicket;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ReIssueEditRefundedNonCustomerTest extends TestCase
{
    use RefreshDatabase;

    protected function beginDatabaseTransaction(): void
    {
        // no-op: DDL (Schema::create) in setUp() on MySQL implicitly commits,
        // which breaks nested DB::transaction() calls in the controller.
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
        Schema::dropIfExists('issued_ticket_logs');
        Schema::dropIfExists('re_issued_tickets');
        Schema::dropIfExists('refunded_tickets');
        Schema::dropIfExists('issued_tickets');
        Schema::dropIfExists('passengers');
        Schema::dropIfExists('bookings');

        Schema::create('bookings', function ($table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('booking_branch_id')->nullable();
            $table->unsignedBigInteger('currency_rate_id')->nullable();
            $table->integer('pax_qty')->default(1);
            $table->decimal('total_value', 14, 6)->default(0);
            $table->decimal('discount_amount', 14, 6)->default(0);
            $table->decimal('profit', 14, 6)->default(0);
            $table->boolean('is_cancelled')->default(false);
            $table->timestamps();
        });

        Schema::create('passengers', function ($table) {
            $table->id();
            $table->unsignedBigInteger('booking_id')->nullable();
            $table->unsignedBigInteger('passenger_status_id')->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->decimal('refund_payable', 14, 6)->default(0);
            $table->decimal('package_value', 14, 6)->default(0);
            $table->decimal('profit', 14, 6)->default(0);
            $table->boolean('is_cancelled')->default(false);
            $table->timestamps();
        });

        Schema::create('issued_tickets', function ($table) {
            $table->id();
            $table->unsignedBigInteger('passenger_id')->nullable();
            $table->unsignedBigInteger('booking_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('status')->default('pending');
            $table->string('issue_type')->nullable();
            $table->decimal('net_fare', 14, 6)->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('refunded_tickets', function ($table) {
            $table->id();
            $table->unsignedBigInteger('issued_ticket_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->decimal('net_fare', 14, 6)->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('re_issued_tickets', function ($table) {
            $table->id();
            $table->unsignedBigInteger('issued_ticket_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('ticket_agent_id')->nullable();
            $table->unsignedBigInteger('ticket_fare_id')->nullable();
            $table->unsignedBigInteger('group_ticket_id')->nullable();
            $table->unsignedBigInteger('route_id')->nullable();
            $table->date('re_issue_date')->nullable();
            $table->date('inbound_date')->nullable();
            $table->date('outbound_date')->nullable();
            $table->boolean('is_refundable')->default(false);
            $table->boolean('is_exchangeable')->default(false);
            $table->string('baggage_inbound')->nullable();
            $table->string('baggage_outbound')->nullable();
            $table->string('payment_by')->nullable();
            $table->string('payment_option')->nullable();
            $table->decimal('total_customer_payment', 14, 6)->default(0);
            $table->decimal('refund_adjustment_amount', 14, 6)->default(0);
            $table->decimal('re_issue_charge', 14, 6)->default(0);
            $table->decimal('fare_difference', 14, 6)->default(0);
            $table->decimal('other_costs', 14, 6)->default(0);
            $table->decimal('service_charge', 14, 6)->default(0);
            $table->decimal('total_cost', 14, 6)->default(0);
            $table->unsignedBigInteger('reason_id')->nullable();
            $table->text('remarks')->nullable();
            $table->string('ticket_number', 100)->nullable();
            $table->string('pnr', 50)->nullable();
            $table->decimal('selling_fare', 14, 6)->default(0);
            $table->decimal('net_fare', 14, 6)->default(0);
            $table->decimal('offer_price', 14, 6)->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('issued_ticket_logs', function ($table) {
            $table->id();
            $table->unsignedBigInteger('issued_ticket_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('action')->nullable();
            $table->json('old_data')->nullable();
            $table->json('new_data')->nullable();
            $table->timestamps();
        });

        Schema::enableForeignKeyConstraints();

        \App\Models\TransactionType::firstOrCreate(
            ['name' => 'Ticket Refund - Re-issue'],
            ['type' => 'debit']
        );
    }

    private function makeStaff(): User
    {
        Role::firstOrCreate(['name' => 'Ticket Admin']);
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('name', 'Ticket Admin')->first());

        return $user;
    }

    public function test_edit_refunded_reissue_with_non_customer_payment_keeps_refund_adjustment(): void
    {
        $staff = $this->makeStaff();

        $booking = Booking::create([
            'user_id' => $staff->id,
            'pax_qty' => 1,
            'total_value' => 0,
            'discount_amount' => 0,
        ]);

        $passenger = Passenger::create([
            'booking_id' => $booking->id,
            'first_name' => 'Test',
            'last_name' => 'Passenger',
            'refund_payable' => 500,
        ]);

        $issuedTicket = IssuedTicket::create([
            'passenger_id' => $passenger->id,
            'booking_id' => $booking->id,
            'user_id' => $staff->id,
            'status' => 're-issued',
            'net_fare' => 1000,
        ]);

        RefundedTicket::create([
            'issued_ticket_id' => $issuedTicket->id,
            'user_id' => $staff->id,
            'net_fare' => 1000,
        ]);

        $reIssued = ReIssuedTicket::create([
            'issued_ticket_id' => $issuedTicket->id,
            'user_id' => $staff->id,
            'payment_by' => 'airline',
            'payment_option' => 'refund_adjustment',
            'refund_adjustment_amount' => 100,
            'total_customer_payment' => 0,
        ]);

        $response = $this->actingAs($staff)->putJson(
            route('bookings.passengers.ticket-edit', [$booking->id, $passenger->id]),
            [
                'issued_ticket_id' => $issuedTicket->id,
                'payment_by' => 'airline',
                'payment_option' => 'refund_adjustment',
                'refund_adjustment_amount' => 100,
            ]
        );

        $response->assertOk()->assertJson(['success' => true]);

        $this->assertDatabaseHas('re_issued_tickets', [
            'id' => $reIssued->id,
            'payment_by' => 'airline',
            'payment_option' => 'refund_adjustment',
            'total_customer_payment' => 0,
        ]);
    }
}
