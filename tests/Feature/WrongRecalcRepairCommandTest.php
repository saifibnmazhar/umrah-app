<?php

namespace Tests\Feature;

use App\Enums\CancelledBookingStatus;
use App\Models\CancelledBooking;
use App\Models\InvoiceUpdateLog;
use App\Models\Package;
use App\Models\Passenger;
use App\Models\PassengerUpdateLog;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Feature\Concerns\CreatesPackageValueFixtures;
use Tests\TestCase;

/**
 * bookings:repair-wrong-recalcs - resets package_value ONLY for passengers
 * whose last package_value change was a wrong live-fare recalculation (the
 * audit-query rules). Valid triggers (passenger_type / extra_charge /
 * service_required, package swap) and events without price drift after the
 * anchor are never touched. Cancelled bookings stay frozen (D4).
 */
class WrongRecalcRepairCommandTest extends TestCase
{
    use CreatesPackageValueFixtures, RefreshDatabase;

    private $user;

    private $branch;

    private $district;

    private $fpCharge;

    private $visaPrice;

    /**
     * Standard scene (shared with SyncBookingFinancialsHistoricTest): fare
     * history 26000 -> 28000 (2026-07-05) -> 30000 live (2026-08-01).
     * Anchor 2026-07-10 => historic ticket 28000 => package_value 31500
     * (28000 + visa 2000 + service_charge 1500); live value => 33500.
     */
    private function scene(bool $postAnchorRaise = true): array
    {
        $this->branch = $this->pvBranch();
        $this->user = $this->pvUser($this->branch);
        $this->district = $this->pvDistrict();
        $this->fpCharge = $this->pvFpCharge($this->district, $this->user);
        $this->visaPrice = $this->pvVisaPrice($this->user, 2000.00);

        $fare = $this->pvTicketFare($this->user, ['selling_fare' => 30000.00]);
        $package = $this->pvPackage($this->user, $this->visaPrice, [
            'ticket_fare_id' => $fare->id,
            'regular_price' => 30000.00,
        ]);

        $this->pvLogFare($fare, 'created', null, [
            'selling_fare' => '26000.000000', 'offer_price' => null, 'ticket_type' => 'regular',
            'child_fare_percentage' => '70.00', 'infant_fare_percentage' => '30.00',
        ], '2026-07-01 10:00:00', $this->user);
        $this->pvLogFare($fare, 'updated', ['selling_fare' => '26000.000000'], ['selling_fare' => '28000.000000'], '2026-07-05 10:00:00', $this->user);

        if ($postAnchorRaise) {
            // Price drift AFTER the anchor - the precondition for counting a
            // wrong difference (the "package update" rule).
            $this->pvLogFare($fare, 'updated', ['selling_fare' => '28000.000000'], ['selling_fare' => '30000.000000'], '2026-08-01 10:00:00', $this->user);
        }

        return compact('fare', 'package');
    }

    /** Booking + one passenger stored at the LIVE value 33500 (wrong). */
    private function wrongBooking(Package $package, float $stored = 33500.00): array
    {
        $booking = $this->pvBooking($package, $this->user, $this->fpCharge, $this->district, [
            'total_value' => $stored,
        ]);
        $booking->created_at = Carbon::parse('2026-07-10 09:00:00');
        $booking->saveQuietly();
        $passenger = $this->pvPassenger($booking, [
            'ticket_fare_id' => $package->ticket_fare_id,
            'package_value' => $stored,
        ]);
        $this->pvSetCreatedAt($passenger, '2026-07-10 10:00:00');
        $invoice = app(InvoiceService::class)->createForBooking($booking);
        $invoice->updateQuietly(['total_amount' => $stored]);

        return [$booking, $passenger, $invoice];
    }

    /** Observer rows a recalculation produces (package_value dirty). */
    private function logPax(Passenger $passenger, array $old, array $new, string $at): void
    {
        $log = new PassengerUpdateLog([
            'passenger_id' => $passenger->id,
            'user_id' => $this->user->id,
            'passport_no' => $passenger->passport_no,
            'action' => 'updated',
            'old_values' => $old,
            'new_values' => $new,
        ]);
        $log->created_at = Carbon::parse($at);
        $log->save();
    }

    public function test_repairs_wrongly_recalculated_passenger_and_is_idempotent(): void
    {
        ['package' => $package] = $this->scene();
        [$booking, $passenger, $invoice] = $this->wrongBooking($package);

        // An unrelated edit triggered a live recompute after the fare raise.
        $this->logPax($passenger, ['package_value' => 31500.00], ['package_value' => 33500.00], '2026-08-05 10:00:00');

        $this->artisan('bookings:repair-wrong-recalcs')->assertSuccessful();

        $this->assertEqualsWithDelta(31500.00, (float) $passenger->fresh()->package_value, 0.001, 'reset to anchor-era value');
        $this->assertEqualsWithDelta(31500.00, (float) $booking->fresh()->total_value, 0.001, 'booking re-totaled');
        $this->assertEqualsWithDelta(31500.00, (float) $invoice->fresh()->total_amount, 0.001, 'invoice moved by the delta');
        $this->assertTrue(
            InvoiceUpdateLog::where('invoice_id', $invoice->id)->where('reason', 'wrong_recalc_repair')->exists(),
            'invoice movement must be greppable as wrong_recalc_repair'
        );

        $afterFirst = [
            (float) $passenger->fresh()->package_value,
            (float) $booking->fresh()->total_value,
            (float) $invoice->fresh()->total_amount,
        ];

        $this->artisan('bookings:repair-wrong-recalcs')->assertSuccessful();

        $this->assertSame($afterFirst, [
            (float) $passenger->fresh()->package_value,
            (float) $booking->fresh()->total_value,
            (float) $invoice->fresh()->total_amount,
        ], 'second run must not move any value');
    }

    public function test_dry_run_reports_without_writing(): void
    {
        ['package' => $package] = $this->scene();
        [$booking, $passenger, $invoice] = $this->wrongBooking($package);
        $this->logPax($passenger, ['package_value' => 31500.00], ['package_value' => 33500.00], '2026-08-05 10:00:00');

        $this->artisan('bookings:repair-wrong-recalcs --dry-run')
            ->expectsOutputToContain('passenger #'.$passenger->id.' '.$passenger->first_name.' '.$passenger->last_name.' (passport '.$passenger->passport_no.'): stored 33500.00 -> historic 31500.00')
            ->expectsOutputToContain('[DRY-RUN] Flagged 1 wrongly recalculated passenger(s)')
            ->assertSuccessful();

        $this->assertEqualsWithDelta(33500.00, (float) $passenger->fresh()->package_value, 0.001, 'dry run must not write');
        $this->assertEqualsWithDelta(33500.00, (float) $booking->fresh()->total_value, 0.001);
        $this->assertEqualsWithDelta(33500.00, (float) $invoice->fresh()->total_amount, 0.001);
    }

    public function test_passenger_type_change_is_never_touched(): void
    {
        ['package' => $package] = $this->scene();
        [, $passenger] = $this->wrongBooking($package);

        // Same request: the type-change row + the recomputed package_value row.
        $this->logPax($passenger, ['passenger_type' => 'adult'], ['passenger_type' => 'child'], '2026-08-05 10:00:00');
        $this->logPax($passenger, ['package_value' => 31500.00], ['package_value' => 33500.00], '2026-08-05 10:00:00');

        $this->artisan('bookings:repair-wrong-recalcs')->assertSuccessful();

        $this->assertEqualsWithDelta(33500.00, (float) $passenger->fresh()->package_value, 0.001, 'type-change recalcs are valid per the audit rules');
    }

    public function test_event_without_price_update_after_anchor_is_never_touched(): void
    {
        ['package' => $package] = $this->scene(postAnchorRaise: false);
        [, $passenger] = $this->wrongBooking($package);
        $this->logPax($passenger, ['package_value' => 31500.00], ['package_value' => 33500.00], '2026-08-05 10:00:00');

        $this->artisan('bookings:repair-wrong-recalcs')->assertSuccessful();

        $this->assertEqualsWithDelta(33500.00, (float) $passenger->fresh()->package_value, 0.001, 'no package update after the anchor => no countable difference');
    }

    public function test_cancelled_booking_is_never_touched(): void
    {
        ['package' => $package] = $this->scene();
        [$booking, $passenger, $invoice] = $this->wrongBooking($package);
        $this->logPax($passenger, ['package_value' => 31500.00], ['package_value' => 33500.00], '2026-08-05 10:00:00');

        CancelledBooking::create([
            'booking_id' => $booking->id,
            'invoice_id' => $invoice->id,
            'user_id' => $this->user->id,
            'total_paid' => 0,
            'cancellation_branch_id' => $this->branch->id,
            'status' => CancelledBookingStatus::CANCELLED,
        ]);

        $this->artisan('bookings:repair-wrong-recalcs')->assertSuccessful();

        $this->assertEqualsWithDelta(33500.00, (float) $passenger->fresh()->package_value, 0.001, 'cancelled booking frozen (D4)');
    }
}
