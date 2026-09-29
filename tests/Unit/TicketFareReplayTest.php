<?php

namespace Tests\Unit;

use App\Models\TicketFare;
use Tests\TestCase;

class TicketFareReplayTest extends TestCase
{
    private function event(string $action, ?array $old, ?array $new, string $at): array
    {
        return [
            'action' => $action,
            'old_values' => $old ?? [],
            'new_values' => $new ?? [],
            'created_at' => $at,
        ];
    }

    public function test_replay_applies_updates_up_to_anchor_only(): void
    {
        $events = [
            $this->event('updated', ['selling_fare' => '28000.000000'], ['selling_fare' => '30000.000000'], '2026-09-24 09:00:00'),
        ];

        // Anchor before the edit -> pre-edit value (seeded from old_values).
        $this->assertEquals(
            28000.0,
            TicketFare::percentageAtFromLogs($events, 'selling_fare', '2026-09-23 10:00:00', 30000.0)
        );

        // Anchor after the edit -> edited value.
        $this->assertEquals(
            30000.0,
            TicketFare::percentageAtFromLogs($events, 'selling_fare', '2026-09-25 00:00:00', 30000.0)
        );
    }

    public function test_created_log_provides_exact_creation_seed(): void
    {
        $events = [
            $this->event('created', null, ['selling_fare' => '28000.000000', 'offer_price' => '25000.000000'], '2026-09-22 10:00:00'),
            $this->event('updated', ['offer_price' => '25000.000000'], ['offer_price' => '27000.000000'], '2026-09-22 11:00:00'),
        ];

        $this->assertEquals(
            27000.0,
            TicketFare::percentageAtFromLogs($events, 'offer_price', '2026-09-22 12:00:00', 27000.0)
        );
        $this->assertEquals(
            25000.0,
            TicketFare::percentageAtFromLogs($events, 'offer_price', '2026-09-22 10:30:00', 27000.0)
        );
        $this->assertEquals(
            28000.0,
            TicketFare::percentageAtFromLogs($events, 'selling_fare', '2026-09-22 12:00:00', 28000.0)
        );
    }

    public function test_no_log_history_falls_back_to_live_value(): void
    {
        $this->assertEquals(
            28000.0,
            TicketFare::percentageAtFromLogs([], 'selling_fare', '2026-09-23 10:00:00', 28000.0)
        );
    }

    public function test_anchor_before_all_events_returns_earliest_known_value(): void
    {
        $events = [
            $this->event('updated', ['selling_fare' => '28000.000000'], ['selling_fare' => '30000.000000'], '2026-09-24 09:00:00'),
        ];

        $this->assertEquals(
            28000.0,
            TicketFare::percentageAtFromLogs($events, 'selling_fare', '2026-09-01 00:00:00', 30000.0)
        );
    }

    public function test_zero_edits_are_detected_not_skipped(): void
    {
        $events = [
            $this->event('updated', ['selling_fare' => '28000.000000'], ['selling_fare' => 0], '2026-09-24 09:00:00'),
        ];

        $this->assertEquals(
            0.0,
            TicketFare::percentageAtFromLogs($events, 'selling_fare', '2026-09-25 00:00:00', 0.0)
        );

        $zeroChild = [
            $this->event('updated', ['child_fare_percentage' => '75.00'], ['child_fare_percentage' => 0], '2026-09-24 09:00:00'),
        ];

        $this->assertEquals(
            0.0,
            TicketFare::percentageAtFromLogs($zeroChild, 'child_fare_percentage', '2026-09-25 00:00:00', 0.0)
        );
    }

    public function test_chained_edits_land_on_correct_rung(): void
    {
        $events = [
            $this->event('updated', ['selling_fare' => '28000.000000'], ['selling_fare' => '30000.000000'], '2026-09-24 09:00:00'),
            $this->event('updated', ['selling_fare' => '30000.000000'], ['selling_fare' => '32000.000000'], '2026-09-26 09:00:00'),
        ];

        $this->assertEquals(
            28000.0,
            TicketFare::percentageAtFromLogs($events, 'selling_fare', '2026-09-23 00:00:00', 32000.0)
        );
        $this->assertEquals(
            30000.0,
            TicketFare::percentageAtFromLogs($events, 'selling_fare', '2026-09-25 00:00:00', 32000.0)
        );
        $this->assertEquals(
            32000.0,
            TicketFare::percentageAtFromLogs($events, 'selling_fare', '2026-09-27 00:00:00', 32000.0)
        );
    }

    public function test_replay_is_column_isolated(): void
    {
        $events = [
            $this->event('updated', ['selling_fare' => '28000.000000'], ['selling_fare' => '30000.000000'], '2026-09-24 09:00:00'),
        ];

        // A selling_fare edit must not leak into the offer_price replay.
        $this->assertEquals(
            25000.0,
            TicketFare::percentageAtFromLogs($events, 'offer_price', '2026-09-25 00:00:00', 25000.0)
        );

        $pctEvents = [
            $this->event('updated', ['child_fare_percentage' => '75.00'], ['child_fare_percentage' => '50.00'], '2026-09-24 09:00:00'),
        ];

        $this->assertEquals(
            10.0,
            TicketFare::percentageAtFromLogs($pctEvents, 'infant_fare_percentage', '2026-09-25 00:00:00', 10.0)
        );
    }

    public function test_anchor_boundary_is_inclusive(): void
    {
        $events = [
            $this->event('updated', ['selling_fare' => '28000.000000'], ['selling_fare' => '30000.000000'], '2026-09-24 09:00:00'),
        ];

        $this->assertEquals(
            30000.0,
            TicketFare::percentageAtFromLogs($events, 'selling_fare', '2026-09-24 09:00:00', 28000.0)
        );
    }

    public function test_null_offer_price_falls_back_to_zero(): void
    {
        $this->assertEquals(
            0.0,
            TicketFare::percentageAtFromLogs([], 'offer_price', '2026-09-25 00:00:00', 0.0)
        );
    }
}
