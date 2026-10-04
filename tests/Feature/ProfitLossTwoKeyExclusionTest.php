<?php

namespace Tests\Feature;

use App\Models\CancelledPassenger;
use App\Models\Invoice;
use App\Services\ProfitCalculationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Concerns\CreatesProfitLossFixtures;
use Tests\TestCase;

/**
 * Part D: two-key passenger exclusion — a passenger is excluded when a
 * cancelled_passengers row with status 'cancelled' exists OR when the manual
 * passenger status is 'Cancel'. Profit/Loss, Dashboard and BranchWise must
 * agree.
 */
class ProfitLossTwoKeyExclusionTest extends TestCase
{
    use CreatesProfitLossFixtures, RefreshDatabase;

    private function effectiveRange(): array
    {
        return [
            'effective_date_from' => now()->subDays(30)->toDateString(),
            'effective_date_to' => now()->toDateString(),
        ];
    }

    /**
     * Re-runs the effective profit aggregate (passengers + visa/ticket/service
     * CASE sums, carrying the exclusion pipes) captured from the query log, so
     * Dashboard and BranchWise report exactly what they computed.
     */
    private function evaluateEffectiveProfitAggregate(array $queryLog): float
    {
        $entry = collect($queryLog)->first(fn (array $q) => str_contains($q['query'], 'passenger_status_id')
            && str_contains($q['query'], 'visa_profit_effective_at')
            && str_contains($q['query'], 'sar_total'));

        $this->assertNotNull($entry, 'Effective profit aggregate query missing from the log');

        $row = DB::select($entry['query'], $entry['bindings'])[0] ?? null;

        return (float) ($row->sar_total ?? 0);
    }

    public function test_cancel_status_passenger_excluded_across_all_reports(): void
    {
        $this->seedProfitLossWorld();
        $cancelId = $this->fxStatus('Cancel')->id;

        $booking = $this->fxBooking('INV-2KEY-1');
        $included = $this->fxPassenger($booking);
        $excluded = $this->fxPassenger($booking, ['passenger_status_id' => $cancelId]);

        $when = now()->subDay()->toDateTimeString();
        $this->fxSetProfit($included, 1000.0, $when);
        $this->fxSetProfit($excluded, 500.0, $when);

        Auth::login($this->fxUser);

        // Profit/Loss summary — effective mode (passenger-level CASE sums).
        $summary = $this->getJson(route('api.reports.profit-loss.summary', $this->effectiveRange()))
            ->assertOk();
        $this->assertSame(1, (int) $summary->json('passenger.count'));
        $this->assertEqualsWithDelta(1000.0, (float) $summary->json('passenger.total_profit'), 0.001);

        // Profit/Loss summary — booking mode (psum raw subquery).
        $bookingMode = $this->getJson(route('api.reports.profit-loss.summary', [
            'booking_date_from' => now()->subDays(30)->toDateString(),
            'booking_date_to' => now()->toDateString(),
        ]))->assertOk();
        $this->assertEqualsWithDelta(1000.0, (float) $bookingMode->json('customer.passenger_profit_total'), 0.001,
            'psum subquery must drop the Cancel-status passenger');
        $this->assertEqualsWithDelta(1000.0, (float) $bookingMode->json('passenger.total_profit'), 0.001);

        // Profit/Loss data — passenger tab (effective).
        $rows = $this->getJson(route('api.reports.profit-loss', $this->effectiveRange() + ['tab' => 'passenger']))
            ->assertOk()
            ->json('data');
        $this->assertCount(1, $rows);
        $this->assertSame((int) $included->id, (int) $rows[0]['id']);

        // Dashboard — effective passenger profit card.
        DB::enableQueryLog();
        $this->get(route('dashboard'))->assertOk();
        DB::disableQueryLog();
        $dashboard = $this->evaluateEffectiveProfitAggregate(DB::getQueryLog());
        DB::flushQueryLog();
        $this->assertEqualsWithDelta(1000.0, $dashboard, 0.001, 'Dashboard must agree with Profit/Loss');

        // BranchWise — effective passenger profit card.
        DB::enableQueryLog();
        $this->actingAs($this->fxUser)
            ->get(route('report.branch-wise', ['branch_id' => $this->fxBranch->id]))
            ->assertOk();
        DB::disableQueryLog();
        $branchWise = $this->evaluateEffectiveProfitAggregate(DB::getQueryLog());
        DB::flushQueryLog();
        $this->assertEqualsWithDelta(1000.0, $branchWise, 0.001, 'BranchWise must agree with Profit/Loss');
    }

    public function test_hold_status_with_processing_cancellation_row_stays_included(): void
    {
        $this->seedProfitLossWorld();
        $booking = $this->fxBooking('INV-2KEY-2');
        $passenger = $this->fxPassenger($booking, [
            'passenger_status_id' => $this->fxStatus('Hold')->id,
        ]);
        $this->fxSetProfit($passenger, 300.0, now()->subDay()->toDateTimeString());

        $invoiceId = Invoice::where('booking_id', $booking->id)->value('id');
        CancelledPassenger::create([
            'booking_id' => $booking->id,
            'passenger_id' => $passenger->id,
            'invoice_id' => $invoiceId,
            'user_id' => $this->fxUser->id,
            'package_value' => 0,
            'cancellation_branch_id' => $this->fxBranch->id,
            'status' => 'cancellation processing',
        ]);

        Auth::login($this->fxUser);

        $summary = $this->getJson(route('api.reports.profit-loss.summary', $this->effectiveRange()))
            ->assertOk();
        $this->assertSame(1, (int) $summary->json('passenger.count'));
        $this->assertEqualsWithDelta(300.0, (float) $summary->json('passenger.total_profit'), 0.001);
    }

    public function test_null_status_passenger_stays_included(): void
    {
        $this->seedProfitLossWorld();
        $booking = $this->fxBooking('INV-2KEY-3');
        $passenger = $this->fxPassenger($booking);
        $this->assertNull($passenger->passenger_status_id);
        $this->fxSetProfit($passenger, 450.0, now()->subDay()->toDateTimeString());

        Auth::login($this->fxUser);

        $summary = $this->getJson(route('api.reports.profit-loss.summary', $this->effectiveRange()))
            ->assertOk();
        $this->assertSame(1, (int) $summary->json('passenger.count'));
        $this->assertEqualsWithDelta(450.0, (float) $summary->json('passenger.total_profit'), 0.001);
    }

    public function test_manual_status_included_when_cancel_status_row_is_absent(): void
    {
        // No 'Cancel' row exists in passenger_statuses: COALESCE(-1) must not
        // turn the <> comparison into NULL and drop manual-status passengers.
        $this->seedProfitLossWorld();
        $booking = $this->fxBooking('INV-2KEY-4');
        $passenger = $this->fxPassenger($booking, [
            'passenger_status_id' => $this->fxStatus('Hold')->id,
        ]);
        $this->fxSetProfit($passenger, 700.0, now()->subDay()->toDateTimeString());

        Auth::login($this->fxUser);

        $summary = $this->getJson(route('api.reports.profit-loss.summary', $this->effectiveRange()))
            ->assertOk();
        $this->assertSame(1, (int) $summary->json('passenger.count'));
        $this->assertEqualsWithDelta(700.0, (float) $summary->json('passenger.total_profit'), 0.001);
    }

    public function test_recalculate_booking_profit_zeroes_cancel_status_passenger_without_row(): void
    {
        $this->seedProfitLossWorld();
        $booking = $this->fxBooking('INV-2KEY-5');
        $passenger = $this->fxPassenger($booking, [
            'passenger_status_id' => $this->fxStatus('Cancel')->id,
        ]);
        $this->fxSetProfit($passenger, 700.0, now()->subDay()->toDateTimeString());
        $this->assertSame(700.0, (float) $passenger->fresh()->profit);

        app(ProfitCalculationService::class)->recalculateBookingProfit($booking);

        $this->assertEqualsWithDelta(0.0, (float) $passenger->fresh()->profit, 0.001,
            'recalculateBookingProfit must zero a Cancel-status passenger with no cancellation row');
        $this->assertEqualsWithDelta(0.0, (float) $booking->fresh()->profit, 0.001,
            'booking profit must drop the excluded passenger');
    }
}
