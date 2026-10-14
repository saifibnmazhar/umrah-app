<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\Passenger;
use App\Models\TicketFare;
use App\Rules\FlightDateSlot;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Feature\Concerns\CreatesPackageValueFixtures;
use Tests\TestCase;

/**
 * Earliest-era restoration (plan 17 amendment): a swap-back to a package
 * the booking was already on re-prices every passenger from that package's
 * FIRST era — undoing mid-period price edits. First-time swaps still anchor
 * at their swap moment, passengers added later floor at add time, and the
 * repair command never fights restored values.
 *
 * Worked numbers (single adult, office => no fingerprint charge):
 *   A first era : 28000 + 2000(visa) + 1500(sc) = 31500
 *   B           : 30000 + 2500        + 1800    = 34300
 *   A live later: 33000 + 2000        + 2000    = 37000 (must NOT appear)
 */
class SwapBackRestorationTest extends TestCase
{
    use CreatesPackageValueFixtures, RefreshDatabase;

    private $user;

    private $district;

    private $fpCharge;

    private TicketFare $fareA;

    private TicketFare $fareB;

    private Package $packageA;

    private Package $packageB;

    protected function setUp(): void
    {
        parent::setUp();

        $branch = $this->pvBranch();
        $this->user = $this->pvUser($branch);
        $this->district = $this->pvDistrict();
        $this->fpCharge = $this->pvFpCharge($this->district, $this->user);

        $visaA = $this->pvVisaPrice($this->user, 2000.00);
        $visaB = $this->pvVisaPrice($this->user, 2500.00);

        $this->fareA = $this->pvTicketFare($this->user, ['selling_fare' => 28000.00]);
        $this->fareB = $this->pvTicketFare($this->user, ['selling_fare' => 30000.00]);

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
            'package_value' => 31500.00, // first-era A
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

    private function raiseFare(TicketFare $fare, float $from, float $to, string $at): void
    {
        $fare->updateQuietly(['selling_fare' => $to]);
        $this->pvLogFare($fare, 'updated', ['selling_fare' => number_format($from, 6, '.', '')], ['selling_fare' => number_format($to, 6, '.', '')], $at, $this->user);
    }

    private function raiseServiceCharge(Package $package, float $from, float $to, string $at): void
    {
        $package->updateQuietly(['service_charge' => $to]);
        $this->pvLogPackage($package, 'updated', ['service_charge' => $from], ['service_charge' => $to], $at, $this->user);
    }

    private function editPassenger(Passenger $passenger, array $overrides = []): void
    {
        $this->put(route('passengers.update', $passenger->id), array_merge([
            'first_name' => $passenger->first_name,
            'last_name' => $passenger->last_name,
            'passport_no' => $passenger->passport_no,
            'date_of_birth' => '1990-01-01',
            'stay_duration' => 14,
            'flight_date_from' => FlightDateSlot::validPairForTesting()[0],
            'flight_date_to' => FlightDateSlot::validPairForTesting()[1],
        ], $overrides))->assertSessionHasNoErrors();
    }

    public function test_swap_back_restores_first_era_values(): void
    {
        [$booking, $passenger, $invoice] = $this->createBookingOnA();
        $this->actingAs($this->user);

        Carbon::setTestNow('2026-06-15 10:00:00');
        $this->swapPackage($booking, $this->packageB);
        $this->assertEqualsWithDelta(34300.00, (float) $passenger->fresh()->package_value, 0.005, 'first-time swap prices at the swap moment');
        $this->assertEqualsWithDelta(34300.00, (float) $booking->fresh()->total_value, 0.005);

        // Price edits between the two swaps — what made swap-back drift
        // under the old lastSwapAt rule.
        Carbon::setTestNow('2026-07-01 10:00:00');
        $this->raiseFare($this->fareA, 28000.00, 33000.00, '2026-07-01 10:00:00');
        $this->raiseServiceCharge($this->packageA, 1500.00, 2000.00, '2026-07-01 10:00:00');

        Carbon::setTestNow('2026-07-15 10:00:00');
        $this->swapPackage($booking, $this->packageA);

        $this->assertEqualsWithDelta(31500.00, (float) $passenger->fresh()->package_value, 0.005, 'swap-back restores V0 exactly (live would be 37000)');
        $this->assertEqualsWithDelta(31500.00, (float) $booking->fresh()->total_value, 0.005, 'total follows restoration');
        $this->assertEqualsWithDelta(31500.00, (float) $invoice->fresh()->total_amount, 0.005, 'invoice follows restoration');
        $this->assertSame($this->packageA->id, $booking->fresh()->package_id);
    }

    public function test_second_return_to_a_new_package_restores_its_first_era(): void
    {
        [$booking, $passenger] = $this->createBookingOnA();
        $this->actingAs($this->user);

        Carbon::setTestNow('2026-06-15 10:00:00');
        $this->swapPackage($booking, $this->packageB); // B first era => 34300

        Carbon::setTestNow('2026-07-01 10:00:00');
        $this->raiseFare($this->fareB, 30000.00, 35000.00, '2026-07-01 10:00:00');

        Carbon::setTestNow('2026-07-15 10:00:00');
        $this->swapPackage($booking, $this->packageA);
        $this->assertEqualsWithDelta(31500.00, (float) $passenger->fresh()->package_value, 0.005, 'first return restores A');

        Carbon::setTestNow('2026-08-01 10:00:00');
        $this->swapPackage($booking, $this->packageB); // SECOND visit to B

        $this->assertEqualsWithDelta(34300.00, (float) $passenger->fresh()->package_value, 0.005, "second return restores B's first era (live would be 39300)");
    }

    public function test_restored_values_survive_a_later_type_change(): void
    {
        [$booking, $passenger] = $this->createBookingOnA();
        $this->actingAs($this->user);

        Carbon::setTestNow('2026-06-15 10:00:00');
        $this->swapPackage($booking, $this->packageB);

        Carbon::setTestNow('2026-07-01 10:00:00');
        $this->raiseFare($this->fareA, 28000.00, 33000.00, '2026-07-01 10:00:00');
        $this->raiseServiceCharge($this->packageA, 1500.00, 2000.00, '2026-07-01 10:00:00');

        Carbon::setTestNow('2026-07-15 10:00:00');
        $this->swapPackage($booking, $this->packageA);
        $this->assertEqualsWithDelta(31500.00, (float) $passenger->fresh()->package_value, 0.005);

        // A later type change re-derives from the SAME first era — the
        // restored value must not drift toward live prices.
        $this->editPassenger($passenger, ['passenger_type' => 'child']);

        $this->assertEqualsWithDelta(28000.00 * 0.70 + 2000.00 + 1500.00, (float) $passenger->fresh()->package_value, 0.005, 'child at first-era fare (23100), not live 33000');
        $this->assertTrue(
            Carbon::parse('2026-06-01 10:00:00')->equalTo($passenger->fresh()->historicAnchor()),
            'anchor stays sticky at the first era'
        );
    }

    public function test_passenger_added_after_restore_is_valued_at_add_time(): void
    {
        [$booking, $passenger] = $this->createBookingOnA();
        $this->actingAs($this->user);

        Carbon::setTestNow('2026-06-15 10:00:00');
        $this->swapPackage($booking, $this->packageB);

        Carbon::setTestNow('2026-07-01 10:00:00');
        $this->raiseFare($this->fareA, 28000.00, 33000.00, '2026-07-01 10:00:00');
        $this->raiseServiceCharge($this->packageA, 1500.00, 2000.00, '2026-07-01 10:00:00');

        Carbon::setTestNow('2026-07-15 10:00:00');
        $this->swapPackage($booking, $this->packageA);
        $this->assertEqualsWithDelta(31500.00, (float) $passenger->fresh()->package_value, 0.005);

        // New passengers floor at their own creation: they join at CURRENT
        // prices while the restored passenger stays on the first era.
        Carbon::setTestNow('2026-07-20 10:00:00');
        $response = $this->postJson(route('bookings.passengers.store', $booking->id), [
            'first_name' => 'New',
            'last_name' => 'Pax',
            'passport_no' => 'NP'.substr(uniqid(), -8),
            'date_of_birth' => '1990-01-01',
            'mobile_no' => '0500000000',
            'passport_expiry' => '2030-12-31',
            'address' => 'Addr',
            'stay_duration' => 14,
            'service_required' => 'all',
            'flight_date_from' => FlightDateSlot::validPairForTesting()[0],
            'flight_date_to' => FlightDateSlot::validPairForTesting()[1],
        ]);
        $response->assertOk();

        $newPassenger = Passenger::where('first_name', 'New')->firstOrFail();
        $this->assertEqualsWithDelta(37000.00, (float) $newPassenger->package_value, 0.005, 'new passenger at add-time (live A) prices');
        $this->assertEqualsWithDelta(31500.00, (float) $passenger->fresh()->package_value, 0.005, 'restored passenger untouched by the add');
        $this->assertEqualsWithDelta(31500.00 + 37000.00, (float) $booking->fresh()->total_value, 0.005);
    }

    public function test_first_time_swap_anchor_survives_later_price_edits(): void
    {
        [$booking, $passenger] = $this->createBookingOnA();
        $this->actingAs($this->user);

        Carbon::setTestNow('2026-06-15 10:00:00');
        $this->swapPackage($booking, $this->packageB);
        $this->assertEqualsWithDelta(34300.00, (float) $passenger->fresh()->package_value, 0.005);

        Carbon::setTestNow('2026-07-01 10:00:00');
        $this->raiseFare($this->fareB, 30000.00, 35000.00, '2026-07-01 10:00:00');

        // Re-valuation while still ON B: anchor = B's first era (the swap
        // moment), never the later price edit.
        $this->editPassenger($passenger, ['passenger_type' => 'child']);

        $this->assertEqualsWithDelta(30000.00 * 0.70 + 2500.00 + 1800.00, (float) $passenger->fresh()->package_value, 0.005, 'child at swap-era fare (25300), not live 35000');
    }

    public function test_repair_command_is_a_no_op_after_restore(): void
    {
        [$booking, $passenger, $invoice] = $this->createBookingOnA();
        $this->actingAs($this->user);

        Carbon::setTestNow('2026-06-15 10:00:00');
        $this->swapPackage($booking, $this->packageB);

        Carbon::setTestNow('2026-07-01 10:00:00');
        $this->raiseFare($this->fareA, 28000.00, 33000.00, '2026-07-01 10:00:00');
        $this->raiseServiceCharge($this->packageA, 1500.00, 2000.00, '2026-07-01 10:00:00');

        Carbon::setTestNow('2026-07-15 10:00:00');
        $this->swapPackage($booking, $this->packageA);

        $this->artisan('bookings:sync-financials')->assertSuccessful();

        $this->assertEqualsWithDelta(31500.00, (float) $passenger->fresh()->package_value, 0.005, 'repair must not fight restoration');
        $this->assertEqualsWithDelta(31500.00, (float) $booking->fresh()->total_value, 0.005);
        $this->assertEqualsWithDelta(31500.00, (float) $invoice->fresh()->total_amount, 0.005);
    }
}
