<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\CurrencyRateService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CancelPassengerModalPlacementTest extends TestCase
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

        $this->ensureTable('passengers', function ($table) {
            $table->id();
            $table->foreignId('booking_id')->nullable();
            $table->foreignId('ticket_fare_id')->nullable();
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

    private function renderIndex(): string
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $emptyPaginator = new LengthAwarePaginator(collect([]), 0, 20);

        return view('bookings.index', [
            'tab' => 'bookings',
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
            'selectedBookingStatus' => null,
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
            'statusChangeOptions' => collect([]),
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
        ])->render();
    }

    private function assertNotNestedInside(string $html, string $outerMarker, string $innerMarker): void
    {
        $outerPos = strpos($html, $outerMarker);
        $innerPos = strpos($html, $innerMarker);

        $this->assertNotFalse($outerPos, "Outer marker '{$outerMarker}' not found in rendered HTML");
        $this->assertNotFalse($innerPos, "Inner marker '{$innerMarker}' not found in rendered HTML");

        if ($innerPos < $outerPos) {
            return;
        }

        preg_match_all('/<div\b|<\/div>/', substr($html, 0, $outerPos), $prefixMatches);
        $depth = 0;
        foreach ($prefixMatches[0] as $tag) {
            $tag === '<div' ? $depth++ : $depth--;
        }
        $baseDepth = $depth;

        preg_match_all('/<div\b|<\/div>/', substr($html, $outerPos, $innerPos - $outerPos), $betweenMatches);
        foreach ($betweenMatches[0] as $tag) {
            $tag === '<div' ? $depth++ : $depth--;
        }

        $this->assertEquals($baseDepth, $depth,
            "'{$innerMarker}' element is nested inside the '{$outerMarker}' container (relative depth "
            .($depth - $baseDepth).'). It must be a sibling so x-show can display it independently.');
    }

    public function test_cancel_passenger_modal_is_not_nested_inside_remarks_modal(): void
    {
        $html = $this->renderIndex();

        $this->assertStringContainsString('x-show="cancelPassengerModalVisible"', $html);
        $this->assertStringContainsString('x-show="remarksModalVisible"', $html);

        $this->assertNotNestedInside(
            $html,
            'x-show="remarksModalVisible"',
            'x-show="cancelPassengerModalVisible"'
        );
    }

    public function test_booking_cancel_modal_is_not_nested_inside_other_modals(): void
    {
        $html = $this->renderIndex();

        $this->assertStringContainsString('x-show="cancelModalVisible"', $html);

        $this->assertNotNestedInside(
            $html,
            'x-show="remarksModalVisible"',
            'x-show="cancelModalVisible"'
        );
    }

    public function test_cancel_passenger_modal_always_shows_due_and_cost_summary_rows(): void
    {
        $html = $this->renderIndex();

        // Zero-value rows must render unconditionally (not behind x-if).
        foreach (['Additional Tickets', 'Total Passenger Due', 'Total Visa Cost'] as $label) {
            $this->assertStringContainsString($label, $html);
        }
    }
}
