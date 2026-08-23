<?php

namespace Tests\Feature;

use App\Enums\CancelledBookingStatus;
use App\Models\CancelledPassenger;
use App\Models\Invoice;
use App\Models\IssuedTicket;
use App\Models\Passenger;
use App\Models\TicketFare;
use App\Models\User;
use App\Services\PassengerCancellationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PassengerAdditionalTicketValueTest extends TestCase
{
    use DatabaseTransactions;

    private array $createdTables = [];

    private function ensureTable(string $name, \Closure $definition): void
    {
        if (! Schema::hasTable($name)) {
            Schema::create($name, $definition);
            $this->createdTables[] = $name;
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

        $this->ensureTable('users', function ($table) {
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

        $this->ensureTable('branches', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('address')->default('');
            $table->string('contacts')->default('');
            $table->timestamps();
        });

        $this->ensureTable('bookings', function ($table) {
            $table->id();
            $table->unsignedBigInteger('invoice_id')->default(0);
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

        $this->ensureTable('passengers', function ($table) {
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

        $this->ensureTable('ticket_fares', function ($table) {
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

        $this->ensureTable('issued_tickets', function ($table) {
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

        $this->ensureTable('cancelled_passengers', function ($table) {
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
            $table->unsignedBigInteger('confirmed_by_id')->nullable();
            $table->unsignedBigInteger('reverted_by_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        $this->ensureTable('passenger_statuses', function ($table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        // visaSubmission hasOne(latestOfMany) lazy-loads even on detached models.
        $this->ensureTable('visa_submissions', function ($table) {
            $table->id();
            $table->unsignedBigInteger('passenger_id')->nullable();
            $table->decimal('net_visa_cost', 14, 6)->default(0);
            $table->decimal('agent_commission', 14, 6)->default(0);
            $table->decimal('additional_cost', 14, 6)->default(0);
            $table->timestamps();
        });

        $this->ensureTable('invoices', function ($table) {
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

        $this->ensureTable('transaction_types', function ($table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        $this->ensureTable('payments', function ($table) {
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

        // BookingObserver::updated writes here on every booking update.
        $this->ensureTable('booking_update_logs', function ($table) {
            $table->id();
            $table->unsignedBigInteger('booking_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('booking_invoice_id')->nullable();
            $table->string('action')->default('updated');
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->timestamps();
        });

        // InvoiceObserver::updated writes here on every invoice update.
        $this->ensureTable('invoice_update_logs', function ($table) {
            $table->id();
            $table->unsignedBigInteger('invoice_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('booking_invoice_id')->nullable();
            $table->string('action')->default('updated');
            $table->string('reason')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        // PassengerObserver::updated writes here on every passenger update.
        $this->ensureTable('passenger_update_logs', function ($table) {
            $table->id();
            $table->unsignedBigInteger('passenger_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('passport_no')->nullable();
            $table->string('action')->default('updated');
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        foreach ($this->createdTables as $table) {
            Schema::dropIfExists($table);
        }
        $this->createdTables = [];

        if (DB::getDriverName() !== 'sqlite') {
            DB::unprepared('SET FOREIGN_KEY_CHECKS=1');
        }

        parent::tearDown();
    }

    protected function signIn(): User
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        return $user;
    }

    private function insertBranch(int $id = 1): void
    {
        DB::table('branches')->insert([
            'id' => $id,
            'name' => 'Main Branch',
            'address' => '',
            'contacts' => '',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function insertBooking(array $overrides = []): void
    {
        DB::table('bookings')->insert(array_merge([
            'id' => 101,
            'invoice_id' => 301,
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
        ], $overrides));
    }

    private function insertPassenger(array $overrides = []): void
    {
        DB::table('passengers')->insert(array_merge([
            'id' => 201,
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
        ], $overrides));
    }

    private function insertInvoice(array $overrides = []): void
    {
        DB::table('invoices')->insert(array_merge([
            'id' => 301,
            'booking_id' => 101,
            'user_id' => 1,
            'branch_id' => 1,
            'total_amount' => 10000,
            'paid_amount' => 9900,
            'balance' => 100,
            'status' => 'partial',
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
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

    public function test_confirm_deducts_invoice_by_total_due_and_booking_by_package_only(): void
    {
        $user = $this->signIn();
        $this->insertBranch();
        $this->insertBooking(['user_id' => $user->id]);
        $this->insertPassenger();
        $this->insertInvoice(['user_id' => $user->id]);

        $cancelledId = DB::table('cancelled_passengers')->insertGetId([
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

        $cancelled = CancelledPassenger::findOrFail($cancelledId);

        app(PassengerCancellationService::class)
            ->confirmCancellation($cancelled, [
                'balance_adjusted_amount' => '100', // >= refundable -> no refund payment
                'payment_method' => 'cash',
            ]);

        // Invoice reduced by total_passenger_due (5000), not package_value.
        $this->assertEqualsWithDelta(5000, (float) Invoice::findOrFail(301)->total_amount, 0.000001);

        $booking = DB::table('bookings')->where('id', 101)->first();
        // Booking reduced by package_value only (3000).
        $this->assertEqualsWithDelta(9000, (float) $booking->total_value, 0.000001);
        $this->assertEquals(1, (int) $booking->pax_qty);

        $this->assertEquals(
            CancelledBookingStatus::CANCELLED,
            CancelledPassenger::findOrFail($cancelledId)->status
        );
    }
}
