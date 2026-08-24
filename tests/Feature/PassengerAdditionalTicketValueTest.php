<?php

namespace Tests\Feature;

use App\Enums\CancelledBookingStatus;
use App\Enums\InvoiceStatus;
use App\Models\CancelledPassenger;
use App\Models\Invoice;
use App\Models\IssuedTicket;
use App\Models\Passenger;
use App\Models\TicketFare;
use App\Models\User;
use App\Services\PassengerCancellationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PassengerAdditionalTicketValueTest extends TestCase
{
    use RefreshDatabase;

    // Override to avoid starting a DB transaction. DDL (Schema::create/drop)
    // in setUp() on MySQL implicitly commits, which breaks nested
    // DB::transaction() savepoints in the service layer under test.
    protected function beginDatabaseTransaction(): void
    {
        // no-op
    }

    public static function tearDownAfterClass(): void
    {
        // Restore migration schema so other test classes aren't affected by
        // our drop/recreate pattern in setUp().
        try {
            Artisan::call('migrate:fresh');
        } catch (\Throwable $e) {
            RefreshDatabaseState::$migrated = false;
        }
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Fixture rows reference sentinel ids (customer_id = 0, package_id = 0,
        // ...) that do not exist as parents. FK enforcement is disabled for this
        // class only and restored in tearDown.
        if (DB::getDriverName() !== 'sqlite') {
            DB::unprepared('SET FOREIGN_KEY_CHECKS=0');
        }

        // Drop tables that already exist from migrations or prior test classes
        // so we can recreate simplified schemas matching the service layer.
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('payments');
        Schema::dropIfExists('vouchers');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('invoice_update_logs');
        Schema::dropIfExists('passenger_update_logs');
        Schema::dropIfExists('booking_update_logs');
        Schema::dropIfExists('cancelled_passengers');
        Schema::dropIfExists('issued_tickets');
        Schema::dropIfExists('ticket_fares');
        Schema::dropIfExists('passengers');
        Schema::dropIfExists('bookings');
        Schema::dropIfExists('transaction_types');
        Schema::dropIfExists('visa_submissions');
        Schema::dropIfExists('passenger_statuses');
        Schema::dropIfExists('users');
        Schema::dropIfExists('branches');

        Schema::create('users', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->foreignId('branch_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('email_verified_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('branches', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('address')->default('');
            $table->string('contacts')->default('');
            $table->timestamps();
        });

        Schema::create('bookings', function ($table) {
            $table->id();
            // Real column is a string storing the formatted invoice number.
            $table->string('invoice_id')->default('');
            $table->unsignedBigInteger('user_id')->default(0);
            $table->unsignedBigInteger('customer_id')->default(0);
            $table->unsignedBigInteger('package_id')->default(0);
            $table->unsignedBigInteger('booking_branch_id')->default(1);
            $table->unsignedBigInteger('fingerprint_branch_id')->default(1);
            $table->unsignedBigInteger('fingerprint_charge_id')->default(0);
            $table->unsignedBigInteger('district_id')->default(0);
            $table->unsignedBigInteger('date_gap_id')->default(0);
            $table->unsignedInteger('pax_qty')->default(0);
            $table->decimal('total_value', 14, 6)->default(0);
            $table->decimal('discount_amount', 14, 6)->default(0);
            $table->string('discount_type')->default('');
            $table->decimal('discount_value', 14, 6)->default(0);
            $table->string('fingerprint_location')->default('');
            $table->unsignedBigInteger('currency_rate_id')->nullable();
            $table->boolean('is_cancelled')->default(false);
            $table->timestamps();
        });

        Schema::create('passengers', function ($table) {
            $table->id();
            $table->unsignedBigInteger('booking_id')->nullable();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('address')->default('');
            $table->date('date_of_birth')->nullable();
            $table->date('flight_date_from')->nullable();
            $table->date('flight_date_to')->nullable();
            $table->string('mobile_no')->default('');
            $table->string('passport_no')->default('');
            $table->date('passport_expiry')->nullable();
            $table->string('passenger_type')->default('adult');
            $table->string('gender')->nullable();
            $table->string('service_required')->default('all');
            $table->string('stay_duration')->default('');
            $table->string('ticket_status')->default('');
            $table->decimal('package_value', 14, 6)->default(0);
            $table->decimal('refund_payable', 14, 6)->default(0);
            $table->unsignedBigInteger('passenger_status_id')->nullable();
            $table->boolean('is_cancelled')->default(false);
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
        });

        Schema::create('ticket_fares', function ($table) {
            $table->id();
            $table->unsignedBigInteger('airline_id')->default(0);
            $table->unsignedBigInteger('airline_classes_id')->default(0);
            $table->unsignedBigInteger('route_id')->default(0);
            $table->unsignedBigInteger('user_id')->default(0);
            $table->decimal('selling_fare', 14, 6)->default(0);
            $table->decimal('net_fare', 14, 6)->default(0);
            $table->decimal('child_fare_percentage', 8, 2)->default(0);
            $table->decimal('infant_fare_percentage', 8, 2)->default(0);
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->string('ticket_type')->default('regular');
            $table->boolean('with_meal')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('issued_tickets', function ($table) {
            $table->id();
            $table->unsignedBigInteger('passenger_id');
            $table->unsignedBigInteger('booking_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('ticket_agent_id')->nullable();
            $table->unsignedBigInteger('ticket_fare_id')->nullable();
            $table->unsignedBigInteger('group_ticket_id')->nullable();
            $table->string('ticket_number')->nullable();
            $table->string('pnr')->nullable();
            $table->date('issued_date')->nullable();
            $table->date('inbound_date')->nullable();
            $table->date('outbound_date')->nullable();
            $table->decimal('selling_fare', 14, 6)->default(0);
            $table->decimal('net_fare', 14, 6)->default(0);
            $table->decimal('offer_price', 14, 6)->default(0);
            $table->boolean('is_refundable')->default(false);
            $table->boolean('is_exchangeable')->default(false);
            $table->string('baggage_inbound')->nullable();
            $table->string('baggage_outbound')->nullable();
            $table->boolean('outbound_pending')->default(false);
            $table->string('issue_type')->default('regular');
            $table->string('status')->default('issued');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('cancelled_passengers', function ($table) {
            $table->id();
            $table->unsignedBigInteger('booking_id');
            $table->unsignedBigInteger('passenger_id');
            $table->unsignedBigInteger('invoice_id');
            $table->unsignedBigInteger('user_id');
            $table->decimal('package_value', 14, 6)->default(0);
            $table->decimal('additional_ticket_value', 14, 6)->default(0);
            $table->decimal('total_passenger_due', 14, 6)->default(0);
            $table->decimal('visa_cost', 14, 6)->default(0);
            $table->decimal('ticket_cost', 14, 6)->default(0);
            $table->decimal('service_charge_deduction', 14, 6)->nullable();
            $table->decimal('refundable_amount', 14, 6)->default(0);
            $table->decimal('balance_adjusted_amount', 14, 6)->default(0);
            $table->decimal('refund_amount', 14, 6)->default(0);
            $table->unsignedBigInteger('cancellation_branch_id');
            $table->string('status')->default('cancellation processing');
            $table->unsignedBigInteger('deduction_payment_id')->nullable();
            $table->unsignedBigInteger('deduction_voucher_id')->nullable();
            $table->unsignedBigInteger('refund_payment_id')->nullable();
            $table->unsignedBigInteger('refund_voucher_id')->nullable();
            $table->unsignedBigInteger('adjustment_payment_id')->nullable();
            $table->unsignedBigInteger('adjustment_voucher_id')->nullable();
            $table->unsignedBigInteger('confirmed_by_id')->nullable();
            $table->unsignedBigInteger('reverted_by_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('passenger_statuses', function ($table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        // visaSubmission hasOne(latestOfMany) lazy-loads even on detached models.
        Schema::create('visa_submissions', function ($table) {
            $table->id();
            $table->unsignedBigInteger('passenger_id')->nullable();
            $table->decimal('net_visa_cost', 14, 6)->default(0);
            $table->decimal('agent_commission', 14, 6)->default(0);
            $table->decimal('additional_cost', 14, 6)->default(0);
            $table->timestamps();
        });

        Schema::create('invoices', function ($table) {
            $table->id();
            $table->unsignedBigInteger('booking_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('branch_id');
            $table->unsignedBigInteger('currency_rate_id')->nullable();
            $table->decimal('total_amount', 14, 6)->default(0);
            $table->decimal('paid_amount', 14, 6)->default(0);
            $table->decimal('balance', 14, 6)->default(0);
            $table->string('status')->default('pending');
            $table->timestamps();
        });

        Schema::create('transaction_types', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('type')->default('credit');
            $table->timestamps();
        });

        Schema::create('payments', function ($table) {
            $table->id();
            $table->unsignedBigInteger('invoice_id')->nullable();
            $table->unsignedBigInteger('booking_id')->nullable();
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('currency_rate_id')->nullable();
            $table->dateTime('payment_date')->nullable();
            $table->string('payment_method')->nullable();
            $table->decimal('amount', 14, 6)->default(0);
            $table->decimal('bdt_amount', 14, 6)->default(0);
            $table->unsignedBigInteger('cancelled_booking_id')->nullable();
            $table->unsignedBigInteger('cancelled_passenger_id')->nullable();
            $table->unsignedBigInteger('refunded_ticket_id')->nullable();
            $table->unsignedBigInteger('re_issued_ticket_id')->nullable();
            $table->string('remarks')->nullable();
            $table->timestamps();
        });

        Schema::create('vouchers', function ($table) {
            $table->id();
            $table->string('voucher_id');
            $table->unsignedBigInteger('invoice_id')->nullable();
            $table->unsignedBigInteger('booking_id')->nullable();
            $table->unsignedBigInteger('payment_id')->nullable();
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('currency_rate_id')->nullable();
            $table->unsignedBigInteger('bank_id')->nullable();
            $table->unsignedBigInteger('ticket_agent_id')->nullable();
            $table->unsignedBigInteger('visa_agent_id')->nullable();
            $table->unsignedBigInteger('commission_agent_id')->nullable();
            $table->unsignedBigInteger('transaction_type_id')->nullable();
            $table->date('payment_date')->nullable();
            $table->string('payment_method')->nullable();
            $table->string('transaction_id')->nullable();
            $table->decimal('amount', 14, 6)->default(0);
            $table->decimal('bdt_amount', 14, 6)->default(0);
            $table->unsignedBigInteger('cancelled_booking_id')->nullable();
            $table->unsignedBigInteger('cancelled_passenger_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // BookingObserver::updated writes here on every booking update.
        Schema::create('booking_update_logs', function ($table) {
            $table->id();
            $table->unsignedBigInteger('booking_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('booking_invoice_id')->nullable();
            $table->string('action')->default('updated');
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->timestamps();
        });

        // InvoiceObserver::updated writes here on every invoice update.
        Schema::create('invoice_update_logs', function ($table) {
            $table->id();
            $table->unsignedBigInteger('invoice_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('booking_invoice_id')->nullable();
            $table->string('action')->default('updated');
            $table->string('reason')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        // PassengerObserver::updated writes here on every passenger update.
        Schema::create('passenger_update_logs', function ($table) {
            $table->id();
            $table->unsignedBigInteger('passenger_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('passport_no')->nullable();
            $table->string('action')->default('updated');
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->timestamps();
        });

        Schema::enableForeignKeyConstraints();
    }

    protected function signIn(): User
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        return $user;
    }

    private function insertBranch(int $id = 1): void
    {
        DB::table('branches')->updateOrInsert(
            ['id' => $id],
            [
                'name' => 'Main Branch',
                'address' => '',
                'contacts' => '',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    private function insertBooking(array $overrides = []): void
    {
        DB::table('bookings')->updateOrInsert(
            ['id' => 101],
            array_merge([
                'invoice_id' => '(###)-127826',
                'user_id' => 1,
                'customer_id' => 0,
                'package_id' => 0,
                'booking_branch_id' => 1,
                'fingerprint_branch_id' => 1,
                'fingerprint_charge_id' => 0,
                'district_id' => 0,
                'date_gap_id' => 0,
                'pax_qty' => 2,
                'total_value' => 12000,
                'discount_amount' => 0,
                'discount_type' => 'fixed_amount',
                'discount_value' => 0,
                'fingerprint_location' => 'home',
                'is_cancelled' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ], $overrides)
        );
    }

    private function insertPassenger(array $overrides = []): void
    {
        $id = $overrides['id'] ?? 201;
        DB::table('passengers')->updateOrInsert(
            ['id' => $id],
            array_merge([
                'booking_id' => 101,
                'first_name' => 'John',
                'last_name' => 'Doe',
                'address' => '',
                'mobile_no' => '123',
                'passport_no' => 'P123',
                'passport_expiry' => '2030-01-01',
                'date_of_birth' => '1990-01-01',
                'flight_date_from' => '2026-09-01',
                'flight_date_to' => '2026-09-15',
                'passenger_type' => 'adult',
                'gender' => 'male',
                'service_required' => 'all',
                'stay_duration' => 7,
                'ticket_status' => 'pending',
                'package_value' => 3000,
                'refund_payable' => 100,
                'is_cancelled' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ], $overrides)
        );
    }

    private function insertInvoice(array $overrides = []): void
    {
        DB::table('invoices')->updateOrInsert(
            ['id' => 301],
            array_merge([
                'booking_id' => 101,
                'user_id' => 1,
                'branch_id' => 1,
                'total_amount' => 10000,
                'paid_amount' => 9900,
                'balance' => 100,
                'status' => 'partial',
                'created_at' => now(),
                'updated_at' => now(),
            ], $overrides)
        );
    }

    private function insertTransactionType(string $name, string $type): int
    {
        return DB::table('transaction_types')->insertGetId([
            'name' => $name,
            'type' => $type,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function insertRegularPayment(array $overrides = []): int
    {
        return DB::table('payments')->insertGetId(array_merge([
            'invoice_id' => 301,
            'booking_id' => 101,
            'branch_id' => 1,
            'user_id' => 1,
            'payment_date' => now(),
            'payment_method' => 'cash',
            'amount' => 9900,
            'bdt_amount' => 0,
        ], $overrides));
    }

    /**
     * Full confirm-scenario fixture: booking 101 (pax 2, total 12000),
     * invoice 301 (10000 total / 9900 paid / 100 balance), cancelled row
     * for passenger 201 (due 5000, refundable 100) plus seeded transaction
     * types. Returns the cancelled_passengers id.
     */
    private function seedConfirmScenario(User $user): int
    {
        $this->insertBranch();
        $this->insertBooking(['user_id' => $user->id]);
        $this->insertPassenger();
        $this->insertInvoice(['user_id' => $user->id]);
        $this->insertRegularPayment(['user_id' => $user->id]);

        $this->insertTransactionType('Due Adjustment', 'credit');
        $this->insertTransactionType('Customer Refund', 'debit');
        $this->insertTransactionType('Service Charge Deduction', 'credit');

        return DB::table('cancelled_passengers')->insertGetId([
            'booking_id' => 101,
            'passenger_id' => 201,
            'invoice_id' => 301,
            'user_id' => $user->id,
            'package_value' => 3000,
            'additional_ticket_value' => 2000,
            'total_passenger_due' => 5000,
            'visa_cost' => 0,
            'ticket_cost' => 0,
            'service_charge_deduction' => null,
            'refundable_amount' => 100,
            'balance_adjusted_amount' => 0,
            'refund_amount' => 0,
            'cancellation_branch_id' => 1,
            'status' => 'cancellation processing',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function insertFare(string $type, ?int $id = null): int
    {
        $row = [
            'airline_id' => 0,
            'airline_classes_id' => 0,
            'route_id' => 0,
            'user_id' => 1,
            'selling_fare' => 0,
            'net_fare' => 0,
            'child_fare_percentage' => 0,
            'infant_fare_percentage' => 0,
            'effective_from' => '2026-01-01',
            'effective_to' => '2026-12-31',
            'ticket_type' => $type,
            'with_meal' => false,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ];
        if ($id !== null) {
            $row['id'] = $id;
        }

        return DB::table('ticket_fares')->insertGetId($row);
    }

    private function insertTicket(array $overrides = []): int
    {
        return DB::table('issued_tickets')->insertGetId(array_merge([
            'passenger_id' => 201,
            'booking_id' => 101,
            'user_id' => 1,
            'ticket_number' => 'TCK-001',
            'selling_fare' => 0,
            'net_fare' => 0,
            'offer_price' => 0,
            'issue_type' => 'regular',
            'status' => 'issued',
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    /**
     * Detached domain objects mirroring a passenger with mixed tickets.
     * Used by preview-math tests without touching heavy schema.
     */
    private function makeDetachedPassenger(): Passenger
    {
        $fareOffer = new TicketFare(['ticket_type' => 'offer']);
        $fareRegular = new TicketFare(['ticket_type' => 'regular']);

        $mk = function (array $attrs) use ($fareRegular) {
            $t = new IssuedTicket;
            foreach ($attrs as $k => $v) {
                if ($k === 'fare') {
                    continue;
                }
                $t->$k = $v;
            }
            $t->setRelation('ticketFare', $attrs['fare'] ?? $fareRegular);

            return $t;
        };

        $tickets = collect([
            // Regular ticket: never counts toward ATV.
            $mk(['issue_type' => 'regular', 'status' => 'issued', 'selling_fare' => '1000', 'net_fare' => '800', 'offer_price' => '0']),
            // Additional, offer fare -> offer_price wins.
            $mk(['issue_type' => 'additional', 'status' => 'issued', 'selling_fare' => '2500', 'net_fare' => '900', 'offer_price' => '2000', 'fare' => $fareOffer]),
            // Additional, regular fare -> selling_fare.
            $mk(['issue_type' => 'additional', 'status' => 'issued', 'selling_fare' => '1500', 'net_fare' => '700', 'offer_price' => '0']),
            // Additional but pending_outbound issue type -> excluded.
            $mk(['issue_type' => 'pending_outbound', 'status' => 'issued', 'selling_fare' => '999', 'net_fare' => '50', 'offer_price' => '0']),
        ]);

        $passenger = new Passenger;
        $passenger->id = 201;
        $passenger->package_value = '3000';
        $passenger->refund_payable = '100';
        $passenger->setRelation('allIssuedTickets', $tickets);

        return $passenger;
    }

    public function test_preview_returns_additional_ticket_value_and_total_passenger_due(): void
    {
        $this->signIn();
        $this->insertBranch();

        $passenger = $this->makeDetachedPassenger();

        $preview = app(PassengerCancellationService::class)
            ->getCancellationPreview($passenger);

        // ATV = 2000 (offer price) + 1500 (selling) = 3500; pending_outbound excluded.
        $this->assertEqualsWithDelta(3500, $preview['additional_ticket_value'], 0.000001);
        // Total due = 3000 package + 3500 ATV.
        $this->assertEqualsWithDelta(6500, $preview['total_passenger_due'], 0.000001);
        // Costs: pre-existing behaviour counts every issued-status ticket,
        // including the pending_outbound one (800+900+700+50).
        $this->assertEqualsWithDelta(2450, $preview['ticket_cost']['total'], 0.000001);
        // Refundable = 6500 - 2450 + 100 refund payable.
        $this->assertEqualsWithDelta(4150, $preview['refundable_amount'], 0.000001);
    }

    public function test_preview_counts_refunded_and_re_issued_additional_tickets(): void
    {
        $this->signIn();
        $this->insertBranch();

        $fareOffer = new TicketFare(['ticket_type' => 'offer']);
        $fareRegular = new TicketFare(['ticket_type' => 'regular']);

        $mk = function (array $attrs) use ($fareRegular) {
            $t = new IssuedTicket;
            foreach ($attrs as $k => $v) {
                if ($k === 'fare') {
                    continue;
                }
                $t->$k = $v;
            }
            $t->setRelation('ticketFare', $attrs['fare'] ?? $fareRegular);

            return $t;
        };

        $tickets = collect([
            $mk(['issue_type' => 'additional', 'status' => 'refunded', 'selling_fare' => '600', 'net_fare' => '0', 'offer_price' => '300', 'fare' => $fareOffer]),
            $mk(['issue_type' => 'additional', 'status' => 're-issued', 'selling_fare' => '400', 'net_fare' => '0', 'offer_price' => '0']),
        ]);

        $passenger = new Passenger;
        $passenger->id = 201;
        $passenger->package_value = '0';
        $passenger->refund_payable = '0';
        $passenger->setRelation('allIssuedTickets', $tickets);

        $preview = app(PassengerCancellationService::class)
            ->getCancellationPreview($passenger);

        // 300 (offer) + 400 (selling) — same statuses the cost side counts.
        $this->assertEqualsWithDelta(700, $preview['additional_ticket_value'], 0.000001);
    }

    public function test_preview_has_zero_values_when_no_tickets_or_visa(): void
    {
        $this->signIn();
        $this->insertBranch();

        $passenger = new Passenger;
        $passenger->id = 201;
        $passenger->package_value = '3000';
        $passenger->refund_payable = '0';
        $passenger->setRelation('allIssuedTickets', collect([]));

        $preview = app(PassengerCancellationService::class)
            ->getCancellationPreview($passenger);

        $this->assertArrayHasKey('additional_ticket_value', $preview);
        $this->assertArrayHasKey('total_passenger_due', $preview);
        $this->assertEqualsWithDelta(0, $preview['additional_ticket_value'], 0.000001);
        $this->assertEqualsWithDelta(0, $preview['visa_cost']['total'], 0.000001);
        $this->assertEqualsWithDelta(3000, $preview['total_passenger_due'], 0.000001);
        $this->assertEqualsWithDelta(3000, $preview['refundable_amount'], 0.000001);
    }

    public function test_initiate_persists_additional_ticket_value_and_total_passenger_due(): void
    {
        $user = $this->signIn();
        $this->insertBranch();
        $this->insertBooking(['user_id' => $user->id]);
        $this->insertPassenger();

        // Persisted sibling so "last active passenger" guard passes.
        $this->insertPassenger(['id' => 202, 'passport_no' => 'P456', 'first_name' => 'Jane']);
        $this->insertInvoice(['user_id' => $user->id]);

        $fareId = $this->insertFare('offer');
        $this->insertTicket(['issue_type' => 'regular', 'selling_fare' => '1000', 'net_fare' => '800']);
        $this->insertTicket(['issue_type' => 'additional', 'ticket_fare_id' => $fareId, 'selling_fare' => '2500', 'net_fare' => '900', 'offer_price' => '2000']);

        $passenger = Passenger::findOrFail(201);

        $cancelled = app(PassengerCancellationService::class)
            ->initiateCancellation($passenger, [
                'cancellation_branch_id' => 1,
                'service_charge_deduction' => '50',
            ]);

        $this->assertEqualsWithDelta(2000, (float) $cancelled->additional_ticket_value, 0.000001);
        $this->assertEqualsWithDelta(5000, (float) $cancelled->total_passenger_due, 0.000001);
        // 5000 - (0 visa + 1700 tickets) - 50 service + 100 refund payable.
        $this->assertEqualsWithDelta(3350, (float) $cancelled->refundable_amount, 0.000001);
    }

    public function test_initiate_resolves_numeric_invoice_id_from_relationship(): void
    {
        $user = $this->signIn();
        $this->insertBranch();
        // bookings.invoice_id holds a formatted invoice number, not invoices.id.
        $this->insertBooking(['user_id' => $user->id]);
        $this->insertPassenger();
        $this->insertPassenger(['id' => 202, 'passport_no' => 'P456', 'first_name' => 'Jane']);
        $this->insertInvoice(['user_id' => $user->id]); // id 301, booking_id 101

        $passenger = Passenger::findOrFail(201);

        $cancelled = app(PassengerCancellationService::class)
            ->initiateCancellation($passenger, [
                'cancellation_branch_id' => 1,
            ]);

        $this->assertSame(301, (int) $cancelled->invoice_id);
        $this->assertNotSame('(###)-127826', (string) $cancelled->invoice_id);
    }

    public function test_confirm_keeps_invoice_and_booking_totals_and_reduces_pax_qty(): void
    {
        $user = $this->signIn();
        $cancelledId = $this->seedConfirmScenario($user);

        app(PassengerCancellationService::class)
            ->confirmCancellation(CancelledPassenger::findOrFail($cancelledId), [
                'balance_adjusted_amount' => '0',
                'payment_method' => 'cash',
            ]);

        // Totals must stay untouched: cancellation settles via credit,
        // it never rewrites historical amounts.
        $this->assertEqualsWithDelta(10000, (float) Invoice::findOrFail(301)->total_amount, 0.000001);

        $booking = DB::table('bookings')->where('id', 101)->first();
        $this->assertEqualsWithDelta(12000, (float) $booking->total_value, 0.000001);
        $this->assertEquals(1, (int) $booking->pax_qty);

        $this->assertEquals(
            CancelledBookingStatus::CANCELLED,
            CancelledPassenger::findOrFail($cancelledId)->status
        );
    }

    public function test_confirm_with_full_adjustment_settles_balance_via_due_adjustment_payment(): void
    {
        $user = $this->signIn();
        $cancelledId = $this->seedConfirmScenario($user);

        app(PassengerCancellationService::class)
            ->confirmCancellation(CancelledPassenger::findOrFail($cancelledId), [
                // Full refundable adjusted against the invoice due.
                'balance_adjusted_amount' => '100',
                'payment_method' => 'cash',
            ]);

        // Exactly one settlement payment: a Due Adjustment, no cash refund.
        $settlementPayments = DB::table('payments')
            ->where('cancelled_passenger_id', $cancelledId)
            ->get();
        $this->assertCount(1, $settlementPayments);
        $this->assertEqualsWithDelta(100, (float) $settlementPayments[0]->amount, 0.000001);

        $voucher = DB::table('vouchers')
            ->where('payment_id', $settlementPayments[0]->id)
            ->first();
        $typeName = DB::table('transaction_types')->where('id', $voucher->transaction_type_id)->value('name');
        $this->assertSame('Due Adjustment', $typeName);

        // Balance formula: total - paid - due adjustments = 10000 - 9900 - 100.
        $invoice = Invoice::findOrFail(301);
        $this->assertEqualsWithDelta(10000, (float) $invoice->total_amount, 0.000001);
        $this->assertEqualsWithDelta(9900, (float) $invoice->paid_amount, 0.000001);
        $this->assertEqualsWithDelta(0, (float) $invoice->balance, 0.000001);
        $this->assertSame(InvoiceStatus::PAID, $invoice->status);

        // Links stored on the cancelled record.
        $cancelled = CancelledPassenger::findOrFail($cancelledId);
        $this->assertEqualsWithDelta(100, (float) $cancelled->balance_adjusted_amount, 0.000001);
        $this->assertEqualsWithDelta(0, (float) $cancelled->refund_amount, 0.000001);
        $this->assertSame((int) $settlementPayments[0]->id, (int) $cancelled->adjustment_payment_id);
        $this->assertSame((int) $voucher->id, (int) $cancelled->adjustment_voucher_id);
        $this->assertNull($cancelled->refund_payment_id);
        $this->assertNull($cancelled->refund_voucher_id);

        // Audit log records the balance change with the adjustment reason.
        $log = DB::table('invoice_update_logs')
            ->where('invoice_id', 301)
            ->orderByDesc('id')
            ->first();
        $this->assertSame('passenger_cancellation_due_adjustment', $log->reason);
        $old = json_decode($log->old_values, true);
        $new = json_decode($log->new_values, true);
        $this->assertEqualsWithDelta(100, (float) $old['balance'], 0.000001);
        $this->assertEqualsWithDelta(0, (float) $new['balance'], 0.000001);
    }

    public function test_confirm_with_refund_settlement_creates_customer_refund_payment(): void
    {
        $user = $this->signIn();
        $cancelledId = $this->seedConfirmScenario($user);

        app(PassengerCancellationService::class)
            ->confirmCancellation(CancelledPassenger::findOrFail($cancelledId), [
                'balance_adjusted_amount' => '0',
                'payment_method' => 'cash',
            ]);

        $refundPayments = DB::table('payments')
            ->where('cancelled_passenger_id', $cancelledId)
            ->get();
        $this->assertCount(1, $refundPayments);
        $this->assertEqualsWithDelta(100, (float) $refundPayments[0]->amount, 0.000001);

        $voucher = DB::table('vouchers')
            ->where('payment_id', $refundPayments[0]->id)
            ->first();
        $typeName = DB::table('transaction_types')->where('id', $voucher->transaction_type_id)->value('name');
        $this->assertSame('Customer Refund', $typeName);

        // No adjustment happened: balance keeps its paid-vs-total delta.
        $invoice = Invoice::findOrFail(301);
        $this->assertEqualsWithDelta(10000, (float) $invoice->total_amount, 0.000001);
        $this->assertEqualsWithDelta(9900, (float) $invoice->paid_amount, 0.000001);
        $this->assertEqualsWithDelta(100, (float) $invoice->balance, 0.000001);
        $this->assertSame(InvoiceStatus::PARTIAL, $invoice->status);

        // Refund links populated, adjustment links untouched.
        $cancelled = CancelledPassenger::findOrFail($cancelledId);
        $this->assertEqualsWithDelta(0, (float) $cancelled->balance_adjusted_amount, 0.000001);
        $this->assertEqualsWithDelta(100, (float) $cancelled->refund_amount, 0.000001);
        $this->assertSame((int) $refundPayments[0]->id, (int) $cancelled->refund_payment_id);
        $this->assertSame((int) $voucher->id, (int) $cancelled->refund_voucher_id);
        $this->assertNull($cancelled->adjustment_payment_id);
        $this->assertNull($cancelled->adjustment_voucher_id);

        // Nothing changed on the invoice itself, so no invoice_update_logs
        // row is expected here; the audit trail is the refund payment.
        $logCount = DB::table('invoice_update_logs')->where('invoice_id', 301)->count();
        $this->assertSame(0, $logCount);
    }

    public function test_confirm_with_mixed_settlement_creates_both_adjustment_and_refund_payments(): void
    {
        $user = $this->signIn();
        $cancelledId = $this->seedConfirmScenario($user);

        app(PassengerCancellationService::class)
            ->confirmCancellation(CancelledPassenger::findOrFail($cancelledId), [
                'balance_adjusted_amount' => '40',
                'payment_method' => 'cash',
            ]);

        // Two settlement payments: 40 credited to the due, 60 paid out.
        $settlementPayments = DB::table('payments')
            ->where('cancelled_passenger_id', $cancelledId)
            ->orderBy('id')
            ->get();
        $this->assertCount(2, $settlementPayments);
        $this->assertEqualsWithDelta(40, (float) $settlementPayments[0]->amount, 0.000001);
        $this->assertEqualsWithDelta(60, (float) $settlementPayments[1]->amount, 0.000001);

        $adjustmentVoucher = DB::table('vouchers')->where('payment_id', $settlementPayments[0]->id)->first();
        $refundVoucher = DB::table('vouchers')->where('payment_id', $settlementPayments[1]->id)->first();
        $adjustmentTypeName = DB::table('transaction_types')->where('id', $adjustmentVoucher->transaction_type_id)->value('name');
        $refundTypeName = DB::table('transaction_types')->where('id', $refundVoucher->transaction_type_id)->value('name');
        $this->assertSame('Due Adjustment', $adjustmentTypeName);
        $this->assertSame('Customer Refund', $refundTypeName);

        // Balance formula: 10000 - 9900 - 40 = 60.
        $invoice = Invoice::findOrFail(301);
        $this->assertEqualsWithDelta(10000, (float) $invoice->total_amount, 0.000001);
        $this->assertEqualsWithDelta(9900, (float) $invoice->paid_amount, 0.000001);
        $this->assertEqualsWithDelta(60, (float) $invoice->balance, 0.000001);
        $this->assertSame(InvoiceStatus::PARTIAL, $invoice->status);

        $cancelled = CancelledPassenger::findOrFail($cancelledId);
        $this->assertEqualsWithDelta(40, (float) $cancelled->balance_adjusted_amount, 0.000001);
        $this->assertEqualsWithDelta(60, (float) $cancelled->refund_amount, 0.000001);
        $this->assertSame((int) $settlementPayments[0]->id, (int) $cancelled->adjustment_payment_id);
        $this->assertSame((int) $adjustmentVoucher->id, (int) $cancelled->adjustment_voucher_id);
        $this->assertSame((int) $settlementPayments[1]->id, (int) $cancelled->refund_payment_id);
        $this->assertSame((int) $refundVoucher->id, (int) $cancelled->refund_voucher_id);

        $log = DB::table('invoice_update_logs')
            ->where('invoice_id', 301)
            ->orderByDesc('id')
            ->first();
        $this->assertSame('passenger_cancellation_due_adjustment', $log->reason);
        $old = json_decode($log->old_values, true);
        $new = json_decode($log->new_values, true);
        $this->assertEqualsWithDelta(100, (float) $old['balance'], 0.000001);
        $this->assertEqualsWithDelta(60, (float) $new['balance'], 0.000001);
    }
}
