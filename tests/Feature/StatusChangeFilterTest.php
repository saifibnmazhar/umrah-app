<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\CurrencyRateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class StatusChangeFilterTest extends TestCase
{
    use RefreshDatabase;

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

        $this->ensureTable('roles', function ($table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        $this->ensureTable('user_roles', function ($table) {
            $table->id();
            $table->foreignId('user_id');
            $table->foreignId('role_id');
            $table->timestamps();
        });

        $this->ensureTable('packages', function ($table) {
            $table->id();
            $table->string('package_name');
        });

        $this->ensureTable('airlines', function ($table) {
            $table->id();
            $table->string('name');
        });

        $this->ensureTable('classes', function ($table) {
            $table->id();
            $table->string('name');
        });

        $this->ensureTable('airline_classes', function ($table) {
            $table->foreignId('airline_id');
            $table->foreignId('class_id');
        });

        $this->ensureTable('re_issue_refund_reasons', function ($table) {
            $table->id();
            $table->string('reason_of');
            $table->string('reason');
        });

        $this->ensureTable('ticket_fares', function ($table) {
            $table->id();
            $table->boolean('is_active')->default(true);
        });

        $this->ensureTable('city_codes', function ($table) {
            $table->id();
            $table->string('city_name')->nullable();
            $table->string('code');
            $table->string('country')->nullable();
        });

        $this->ensureTable('currency_rates', function ($table) {
            $table->id();
            $table->foreignId('user_id')->nullable();
            $table->decimal('rate', 10, 2);
            $table->timestamps();
        });

        $this->ensureTable('stay_duration_limits', function ($table) {
            $table->id();
            $table->integer('min_days')->nullable();
            $table->integer('max_days')->nullable();
        });
    }

    protected function tearDown(): void
    {
        foreach ($this->createdTables as $table) {
            Schema::dropIfExists($table);
        }
        $this->createdTables = [];

        parent::tearDown();
    }

    private function renderIndex(array $overrides = []): string
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $emptyPaginator = new LengthAwarePaginator(collect([]), 0, 20);

        $data = [
            'tab' => 'passenger',
            'bookings' => $emptyPaginator,
            'passengers' => $emptyPaginator,
            'passengerStatuses' => collect([]),
            'visaAgents' => collect([]),
            'ticketAgents' => collect([]),
            'canEditVisa' => false,
            'canFilterByVisaAgent' => true,
            'canFilterByTicketAgent' => true,
            'currencyRateService' => app(CurrencyRateService::class),
            'bookingBranches' => collect([]),
            'selectedBranchId' => null,
            'totalBookingCount' => 0,
            'totalBookingPassengerCount' => 0,
            'branchCounts' => collect([]),
            'allBookingCount' => 0,
            'selectedFingerprintStatus' => null,
            'selectedVisaStatus' => null,
            'selectedTicketStatus' => null,
            'selectedVisaAgentId' => null,
            'selectedBookingDateFrom' => null,
            'selectedBookingDateTo' => null,
            'selectedFingerprintLocation' => null,
            'selectedBookingStatus' => 'active',
            'selectedPassengerStatus' => null,
            'selectedRouteDisplay' => null,
            'routesList' => collect([]),
            'selectedPackageId' => null,
            'selectedTicketAgentId' => null,
            'selectedActualFlightFrom' => null,
            'selectedActualFlightTo' => null,
            'selectedReturnDateFrom' => null,
            'selectedReturnDateTo' => null,
            'selectedStatusChangeAction' => null,
            'selectedStatusChangeFrom' => null,
            'selectedStatusChangeTo' => null,
            'selectedPaymentWise' => null,
            'statusChangeOptions' => collect([
                (object) ['id' => 'visa_submitted', 'name' => 'Visa Submitted'],
                (object) ['id' => 'visa_issued', 'name' => 'Visa Issued'],
                (object) ['id' => 'ticket_issued', 'name' => 'Ticket Issued'],
            ]),
            'fingerprintStatuses' => [],
            'visaStatuses' => [],
            'ticketStatuses' => [],
            'fingerprintLocations' => [],
            'totalPassengerCount' => 0,
            'totalPackageValue' => 0,
            'totalDue' => 0,
            'totalPackageBdt' => 0,
            'totalDueBdt' => 0,
            'reIssueReasons' => collect([]),
        ];

        return view('bookings.index', array_merge($data, $overrides))->render();
    }

    public function test_status_change_dropdown_renders_new_options(): void
    {
        $html = $this->renderIndex();

        $this->assertStringContainsString('Visa Submitted', $html);
        $this->assertStringContainsString('Visa Issued', $html);
        $this->assertStringContainsString('Ticket Issued', $html);
        $this->assertStringContainsString('value="visa_submitted"', $html);
        $this->assertStringContainsString('value="visa_issued"', $html);
        $this->assertStringContainsString('value="ticket_issued"', $html);
    }
}
