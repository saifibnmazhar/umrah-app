<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingUpdateLog;
use App\Models\Branch;
use App\Models\Invoice;
use App\Models\InvoiceUpdateLog;
use App\Models\Package;
use App\Models\Passenger;
use App\Models\PassengerUpdateLog;
use App\Models\TicketFare;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Feature\Concerns\CreatesPackageValueFixtures;
use Tests\TestCase;

/**
 * GET bookings/{booking}/swap-preview: read-only truth for the edit page's
 * pre-save package swap. A previously used target previews its earliest-era
 * (restored) values; a never-used target previews current prices — exactly
 * what a save would store. Nothing may be written: passenger values,
 * booking totals, invoice and every audit stream stay untouched.
 */
class SwapPreviewEndpointTest extends TestCase
{
    use CreatesPackageValueFixtures, RefreshDatabase;

    private $user;

    private $district;

    private $fpCharge;

    private TicketFare $fareA;

    private TicketFare $fareB;

    private TicketFare $fareC;

    private Package $packageA;

    private Package $packageB;

    private Package $packageC;

    protected function setUp(): void
    {
        parent::setUp();

        $branch = $this->pvBranch();
        $this->user = $this->pvUser($branch);
        $this->district = $this->pvDistrict();
        $this->fpCharge = $this->pvFpCharge($this->district, $this->user);

        $visaA = $this->pvVisaPrice($this->user, 2000.00);
        $visaB = $this->pvVisaPrice($this->user, 2500.00);
        $visaC = $this->pvVisaPrice($this->user, 2200.00);

        $this->fareA = $this->pvTicketFare($this->user, ['selling_fare' => 28000.00]);
        $this->fareB = $this->pvTicketFare($this->user, ['selling_fare' => 30000.00]);
        $this->fareC = $this->pvTicketFare($this->user, ['selling_fare' => 26000.00]);

        $this->packageA = $this->pvPackage($this->user, $visaA, [
            'ticket_fare_id' => $this->fareA->id,
            'service_charge' => 1500.00,
            'regular_price' => 30000.00,
        ]);
        $this->packageB = $this->pvPackage($this->user, $visaB, [
            'ticket_fare_id' => $this->fareB->id,
            'service_charge' => 1800.00,
            'regular_price' => 30500.00,
        ]);
        $this->packageC = $this->pvPackage($this->user, $visaC, [
            'ticket_fare_id' => $this->fareC->id,
            'service_charge' => 1600.00,
            'regular_price' => 29000.00,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /** @return array{0: Booking, 1: Passenger, 2: Invoice} */
    private function createBookingOnA(): array
    {
        Carbon::setTestNow('2026-06-01 10:00:00');

        $booking = $this->pvBooking($this->packageA, $this->user, $this->fpCharge, $this->district, [
            'total_value' => 31500.00,
        ]);
        $passenger = $this->pvPassenger($booking, [
            'ticket_fare_id' => $this->fareA->id,
            'package_value' => 31500.00,
        ]);
        $invoice = app(InvoiceService::class)->createForBooking($booking);
        $invoice->updateQuietly(['total_amount' => 31500.00, 'paid_amount' => 0]);

        return [$booking, $passenger, $invoice];
    }

    private function swapPackage(Booking $booking, Package $package): void
    {
        $this->put(route('bookings.update', $booking->id), [
            'package_id' => $package->id,
        ])->assertRedirect();
    }

    private function preview(Booking $booking, Package $package)
    {
        return $this->getJson(route('bookings.swap-preview', [
            'booking' => $booking->id,
            'package_id' => $package->id,
        ]));
    }

    public function test_preview_returns_restored_values_and_writes_nothing(): void
    {
        [$booking, $passenger, $invoice] = $this->createBookingOnA();
        $this->actingAs($this->user);

        Carbon::setTestNow('2026-06-15 10:00:00');
        $this->swapPackage($booking, $this->packageB);

        // A's price moved after the booking left it — restoration territory.
        Carbon::setTestNow('2026-07-01 10:00:00');
        $fare = $this->fareA;
        $fare->updateQuietly(['selling_fare' => 33000.00]);
        $this->pvLogFare($fare, 'updated', ['selling_fare' => '28000.000000'], ['selling_fare' => '33000.000000'], '2026-07-01 10:00:00', $this->user);

        Carbon::setTestNow('2026-07-15 10:00:00');
        $logsBefore = [
            BookingUpdateLog::count(),
            PassengerUpdateLog::count(),
            InvoiceUpdateLog::count(),
        ];

        $response = $this->preview($booking, $this->packageA);
        $response->assertOk();

        $data = $response->json();
        $this->assertTrue($data['restored'], 'package A was used before');
        $this->assertSame('2026-06-01 10:00:00', $data['anchor']);
        $this->assertEqualsWithDelta(31500.00, (float) $data['passengers'][0]['package_value'], 0.005, 'restored first-era value (live would be 37000)');
        $this->assertEqualsWithDelta(31500.00, (float) $data['total_value'], 0.005);
        $this->assertEqualsWithDelta(0.00, (float) $data['discount_amount'], 0.005);

        // NOTHING written — stored state and audit streams untouched.
        $this->assertEqualsWithDelta(34300.00, (float) $passenger->fresh()->package_value, 0.005, 'preview must not touch passengers');
        $this->assertEqualsWithDelta(34300.00, (float) $booking->fresh()->total_value, 0.005);
        $this->assertEqualsWithDelta(34300.00, (float) $invoice->fresh()->total_amount, 0.005);
        $this->assertSame($this->packageB->id, $booking->fresh()->package_id, 'booking still on B');
        $this->assertSame($logsBefore, [
            BookingUpdateLog::count(),
            PassengerUpdateLog::count(),
            InvoiceUpdateLog::count(),
        ], 'a preview writes no audit rows');
    }

    public function test_preview_for_never_used_package_uses_current_prices(): void
    {
        [$booking] = $this->createBookingOnA();
        $this->actingAs($this->user);

        Carbon::setTestNow('2026-06-15 10:00:00');
        $this->swapPackage($booking, $this->packageB);

        Carbon::setTestNow('2026-07-15 10:00:00');
        $response = $this->preview($booking, $this->packageC);
        $response->assertOk();

        $data = $response->json();
        $this->assertFalse($data['restored'], 'package C was never on this booking');
        $this->assertSame('2026-07-15 10:00:00', $data['anchor'], 'never-used package previews at now()');
        $this->assertEqualsWithDelta(26000.00 + 2200.00 + 1600.00, (float) $data['passengers'][0]['package_value'], 0.005, 'current prices (29800)');
    }

    public function test_preview_of_current_package_shows_stored_values(): void
    {
        [$booking] = $this->createBookingOnA();
        $this->actingAs($this->user);

        Carbon::setTestNow('2026-06-01 12:00:00');
        $response = $this->preview($booking, $this->packageA);
        $response->assertOk();

        $data = $response->json();
        $this->assertTrue($data['restored'], 'the current package counts as used');
        $this->assertSame('2026-06-01 10:00:00', $data['anchor']);
        $this->assertEqualsWithDelta(31500.00, (float) $data['passengers'][0]['package_value'], 0.005, 'no-op preview equals stored value');
    }

    public function test_preview_requires_authentication(): void
    {
        [$booking] = $this->createBookingOnA();

        $this->preview($booking, $this->packageB)->assertUnauthorized();
    }

    public function test_preview_enforces_branch_access(): void
    {
        [$booking] = $this->createBookingOnA();

        $otherBranch = Branch::create([
            'name' => 'Other Branch',
            'address' => 'Addr',
            'contacts' => '0123456789',
            'location' => 'KSA',
            'fingerprint_operation' => false,
            'branch_code' => 'OTH01',
        ]);
        $this->actingAs($this->pvUser($otherBranch));

        $this->preview($booking, $this->packageB)->assertForbidden();
    }
}
