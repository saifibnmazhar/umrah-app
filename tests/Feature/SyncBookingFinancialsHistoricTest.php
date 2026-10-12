<?php

namespace Tests\Feature;

use App\Enums\CancelledBookingStatus;
use App\Models\Booking;
use App\Models\CancelledBooking;
use App\Models\Invoice;
use App\Models\InvoiceUpdateLog;
use App\Models\Package;
use App\Models\Passenger;
use App\Models\PassengerUpdateLog;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\CreatesPackageValueFixtures;
use Tests\TestCase;

/**
 * Test 7 (plan 17): bookings:sync-financials is the historic repair tool —
 * --dry-run reports without writing; the real run repairs drift with invoice
 * reason='historic_backfill'; swapped passengers keep swap-time pricing;
 * second run is a no-op; cancelled bookings/passengers are untouched.
 */
class SyncBookingFinancialsHistoricTest extends TestCase
{
    use CreatesPackageValueFixtures, RefreshDatabase;

    private $user;

    private $branch;

    private $district;

    private $fpCharge;

    private $visaPrice;

    /** Standard scene: fare era history 26000 -> 28000 -> 30000 (live). */
    private function scene(): array
    {
        $this->branch = $this->pvBranch();
        $this->user = $this->pvUser($this->branch);
        $this->district = $this->pvDistrict();
        $this->fpCharge = $this->pvFpCharge($this->district, $this->user);
        $this->visaPrice = $this->pvVisaPrice($this->user, 2000.00);

        $fare = $this->pvTicketFare($this->user, ['selling_fare' => 30000.00]); // live
        $package = $this->pvPackage($this->user, $this->visaPrice, [
            'ticket_fare_id' => $fare->id,
            'regular_price' => 30000.00,
        ]);

        // Historic fare record: creation 26000, raised to 28000 before the
        // typical anchor, raised again to 30000 (live).
        $this->pvLogFare($fare, 'created', null, [
            'selling_fare' => '26000.000000', 'offer_price' => null, 'ticket_type' => 'regular',
            'child_fare_percentage' => '70.00', 'infant_fare_percentage' => '30.00',
        ], '2026-07-01 10:00:00', $this->user);
        $this->pvLogFare($fare, 'updated', ['selling_fare' => '26000.000000'], ['selling_fare' => '28000.000000'], '2026-07-05 10:00:00', $this->user);
        $this->pvLogFare($fare, 'updated', ['selling_fare' => '28000.000000'], ['selling_fare' => '30000.000000'], '2026-08-01 10:00:00', $this->user);

        return compact('fare', 'package');
    }

    /** Drifted booking + invoice on the standard package (office => no fp charge). */
    private function driftedBooking(Package $package, float $passengerValue = 12345.00, float $bookingTotal = 9999.00, ?string $passengerCreatedAt = '2026-07-10 10:00:00'): array
    {
        $booking = $this->pvBooking($package, $this->user, $this->fpCharge, $this->district, [
            'total_value' => $bookingTotal,
        ]);
        $passenger = $this->pvPassenger($booking, [
            'ticket_fare_id' => $package->ticket_fare_id,
            'package_value' => $passengerValue,
        ]);
        if ($passengerCreatedAt) {
            $this->pvSetCreatedAt($passenger, $passengerCreatedAt);
        }
        $invoice = app(InvoiceService::class)->createForBooking($booking);

        return [$booking, $passenger, $invoice];
    }

    private function fmt(float $v): string
    {
        return number_format($v, 2, '.', '');
    }

    public function test_dry_run_reports_drift_without_writing(): void
    {
        ['package' => $package] = $this->scene();
        [$booking, $passenger, $invoice] = $this->driftedBooking($package);

        $this->artisan('bookings:sync-financials --dry-run')
            ->expectsOutputToContain($this->fmt(12345.00).' -> '.$this->fmt(31500.00)) // passenger
            ->expectsOutputToContain($this->fmt(9999.00).' -> '.$this->fmt(31500.00))  // total + invoice
            ->assertSuccessful();

        // Nothing was written.
        $this->assertEqualsWithDelta(12345.00, (float) $passenger->fresh()->package_value, 0.001, 'dry-run must not write package_value');
        $this->assertEqualsWithDelta(9999.00, (float) $booking->fresh()->total_value, 0.001, 'dry-run must not write total_value');
        $this->assertEqualsWithDelta(9999.00, (float) $invoice->fresh()->total_amount, 0.001, 'dry-run must not write invoice');
    }

    public function test_real_run_repairs_drift_with_historic_backfill_reason(): void
    {
        ['package' => $package] = $this->scene();
        [$booking, $passenger, $invoice] = $this->driftedBooking($package);

        $this->artisan('bookings:sync-financials')->assertSuccessful();

        // Repaired to the fare AS OF the passenger's anchor (2026-07-10 => 28000),
        // not the live 30000.
        $this->assertEqualsWithDelta(31500.00, (float) $passenger->fresh()->package_value, 0.001, 'package_value repaired at anchor');
        $this->assertEqualsWithDelta(31500.00, (float) $booking->fresh()->total_value, 0.001, 'total_value re-summed');
        $this->assertEqualsWithDelta(31500.00, (float) $invoice->fresh()->total_amount, 0.001, 'invoice delta applied');

        $this->assertTrue(
            InvoiceUpdateLog::where('invoice_id', $invoice->id)
                ->where('reason', 'historic_backfill')
                ->exists(),
            'every backfill invoice movement must be greppable as historic_backfill'
        );
        $this->assertSame(0, PassengerUpdateLog::where('action', 'recalculated')->count(),
            'unauthenticated command must not write recalculated logs (D9)');
    }

    public function test_swapped_passenger_keeps_swap_time_pricing(): void
    {
        ['fare' => $fare, 'package' => $packageA] = $this->scene();
        $packageB = $this->pvPackage($this->user, $this->visaPrice, [
            'ticket_fare_id' => $fare->id,
            'regular_price' => 30000.00,
        ]);

        // Creation era: 26000; raise to 28000 on 07-05; swap on 07-10;
        // raise to 30000 on 08-01 (live).
        [$booking, $passenger] = $this->driftedBooking($packageA, 11111.00, 9999.00, '2026-07-02 10:00:00');
        $booking->updateQuietly(['package_id' => $packageB->id, 'package_name' => $packageB->package_name]);
        $this->pvLogSwap($booking, $packageA->id, $packageB->id, '2026-07-10 10:00:00', $this->user);

        $this->artisan('bookings:sync-financials')->assertSuccessful();

        // Anchor = swap (07-10): fare was 28000 — not creation-era 26000,
        // not live 30000 (worked 4,000 -> 4,500 example, plan 17 test 7).
        $this->assertEqualsWithDelta(31500.00, (float) $passenger->fresh()->package_value, 0.001, 'swap-time pricing preserved');
    }

    public function test_second_run_is_a_no_op(): void
    {
        ['package' => $package] = $this->scene();
        [$booking, $passenger, $invoice] = $this->driftedBooking($package);

        $this->artisan('bookings:sync-financials')->assertSuccessful();

        $afterFirst = [
            (float) $passenger->fresh()->package_value,
            (float) $booking->fresh()->total_value,
            (float) $invoice->fresh()->total_amount,
        ];

        $this->artisan('bookings:sync-financials')->assertSuccessful();

        $this->assertSame($afterFirst, [
            (float) $passenger->fresh()->package_value,
            (float) $booking->fresh()->total_value,
            (float) $invoice->fresh()->total_amount,
        ], 'second run must not move any value');
    }

    public function test_cancelled_bookings_and_passengers_are_untouched(): void
    {
        ['package' => $package] = $this->scene();

        // Booking 1: fully cancelled — excluded from the iteration entirely.
        [$cancelledBooking, $cancelledPax, $invoice] = $this->driftedBooking($package, 12345.00, 9999.00);
        CancelledBooking::create([
            'booking_id' => $cancelledBooking->id,
            'invoice_id' => $invoice->id,
            'user_id' => $this->user->id,
            'total_paid' => 0,
            'cancellation_branch_id' => $this->branch->id,
            'status' => CancelledBookingStatus::CANCELLED,
        ]);

        // Booking 2: one frozen (is_cancelled) passenger + one live passenger.
        [$booking2, $pax2, $invoice2] = $this->driftedBooking($package, 12345.00, 90122.00);
        $frozen = $this->pvPassenger($booking2, [
            'ticket_fare_id' => $package->ticket_fare_id,
            'is_cancelled' => true,
            'package_value' => 77777.00,
        ]);
        $booking2->updateQuietly(['pax_qty' => 2, 'total_value' => 90122.00]);
        $invoice2->updateQuietly(['total_amount' => 90122.00]);

        $this->artisan('bookings:sync-financials')->assertSuccessful();

        $this->assertEqualsWithDelta(12345.00, (float) $cancelledPax->fresh()->package_value, 0.001, 'cancelled booking passenger untouched');
        $this->assertEqualsWithDelta(9999.00, (float) $cancelledBooking->fresh()->total_value, 0.001, 'cancelled booking total untouched');
        $this->assertEqualsWithDelta(9999.00, (float) $invoice->fresh()->total_amount, 0.001, 'cancelled booking invoice untouched');

        $this->assertEqualsWithDelta(77777.00, (float) $frozen->fresh()->package_value, 0.001, 'is_cancelled passenger frozen');
        $this->assertEqualsWithDelta(31500.00, (float) $pax2->fresh()->package_value, 0.001, 'live passenger repaired');
        // Re-sum still counts the frozen value (D4).
        $this->assertEqualsWithDelta(77777.00 + 31500.00, (float) $booking2->fresh()->total_value, 0.001);
        $this->assertEqualsWithDelta(77777.00 + 31500.00, (float) $invoice2->fresh()->total_amount, 0.001);
    }

    public function test_dry_run_excludes_cancelled_bookings(): void
    {
        ['package' => $package] = $this->scene();
        [$cancelledBooking, $cancelledPax, $invoice] = $this->driftedBooking($package);
        CancelledBooking::create([
            'booking_id' => $cancelledBooking->id,
            'invoice_id' => $invoice->id,
            'user_id' => $this->user->id,
            'total_paid' => 0,
            'cancellation_branch_id' => $this->branch->id,
            'status' => CancelledBookingStatus::CANCELLED,
        ]);

        $this->artisan('bookings:sync-financials --dry-run')
            ->doesntExpectOutputToContain($cancelledBooking->invoice_id)
            ->assertSuccessful();
    }
}
