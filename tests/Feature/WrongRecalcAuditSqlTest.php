<?php

namespace Tests\Feature;

use App\Models\PassengerUpdateLog;
use App\Services\BookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Concerns\CreatesPackageValueFixtures;
use Tests\TestCase;

/**
 * The wrong-recalculation audit SQL (docs/plans/17-wrong-package-value-recalc-queries.sql)
 * replays historic_package_value from the update-log streams. It must agree with the
 * single source of truth — BookingService::calculatePackageValue($p, $p->historicAnchor()).
 *
 * TDD guard: if the calculator changes, this test goes red first.
 */
class WrongRecalcAuditSqlTest extends TestCase
{
    use CreatesPackageValueFixtures, RefreshDatabase;

    private static ?string $sqlFile = null;

    private static function auditSql(): string
    {
        self::$sqlFile ??= file_get_contents(base_path('docs/plans/17-wrong-package-value-recalc-queries.sql'));

        return self::$sqlFile;
    }

    /**
     * Extract a named query block (-- Query <letter> — ... up to the next
     * -- Query marker or EOF) from the audit SQL file.
     */
    private function block(string $letter): string
    {
        $sql = self::auditSql();
        $start = strpos($sql, "-- Query {$letter} ");
        $this->assertNotFalse($start, "Query {$letter} block not found in audit SQL");

        $rest = substr($sql, strpos($sql, "\n", $start) + 1);
        $next = strpos($rest, '-- Query ');
        if ($next !== false) {
            $rest = substr($rest, 0, $next);
        }

        // Drop trailing blank/comment-only lines (the *next* query's header
        // rule would otherwise be pasted into our statement and break it).
        $lines = explode("\n", $rest);
        while ($lines !== []) {
            $last = trim(end($lines));
            if ($last === '' || str_starts_with($last, '--')) {
                array_pop($lines);
            } else {
                break;
            }
        }

        return implode("\n", $lines);
    }

    private $user;

    private $branch;

    private $district;

    private $fpCharge;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = $this->pvBranch();
        $this->user = $this->pvUser($this->branch);
        $this->district = $this->pvDistrict();
        $this->fpCharge = $this->pvFpCharge($this->district, $this->user);
    }

    /**
     * Standard scene: fare 26000 (created 07-01) -> 28000 (07-05) -> 30000 live (08-01);
     * visa price 2000; service_charge 1500; booking created 07-10 09:00, passenger 07-10 10:00.
     * Anchor = 07-10 => historic selling 28000 => historic value 31500.
     */
    private function scene(array $passengerOverrides = []): array
    {
        $visaPrice = $this->pvVisaPrice($this->user, 2000.00);
        $fare = $this->pvTicketFare($this->user, ['selling_fare' => 30000.00]);
        $package = $this->pvPackage($this->user, $visaPrice, [
            'ticket_fare_id' => $fare->id,
            'regular_price' => 30000.00,
        ]);
        $this->pvLogFare($fare, 'created', null, [
            'selling_fare' => '26000.000000', 'offer_price' => null, 'ticket_type' => 'regular',
            'child_fare_percentage' => '70.00', 'infant_fare_percentage' => '30.00',
        ], '2026-07-01 10:00:00', $this->user);
        $this->pvLogFare($fare, 'updated', ['selling_fare' => '26000.000000'], ['selling_fare' => '28000.000000'], '2026-07-05 10:00:00', $this->user);
        $this->pvLogFare($fare, 'updated', ['selling_fare' => '28000.000000'], ['selling_fare' => '30000.000000'], '2026-08-01 10:00:00', $this->user);

        $booking = $this->pvBooking($package, $this->user, $this->fpCharge, $this->district, ['total_value' => 33500.00]);
        $booking->created_at = '2026-07-10 09:00:00';
        $booking->saveQuietly();

        $passenger = $this->pvPassenger($booking, array_merge([
            'ticket_fare_id' => $fare->id,
            'package_value' => 33500.00,
        ], $passengerOverrides));
        $this->pvSetCreatedAt($passenger, '2026-07-10 10:00:00');

        return [$booking, $passenger->fresh(), $package, $fare, $visaPrice];
    }

    /**
     * Run Query H (historic replay per passenger) restricted to one passenger
     * and return the replayed row.
     */
    private function replayRow(int $passengerId): object
    {
        // Query H is a standalone statement; wrapping it in a derived table
        // scopes it to one passenger without depending on its internal text.
        $block = rtrim($this->block('H'), " \t\n\r;");

        $rows = DB::select("SELECT * FROM (\n{$block}\n) q WHERE q.passenger_id = ?", [$passengerId]);
        $this->assertCount(1, $rows);

        return $rows[0];
    }

    private function expected(object $passenger): float
    {
        return app(BookingService::class)->calculatePackageValue($passenger, $passenger->historicAnchor());
    }

    public function test_replays_single_adult_fare(): void
    {
        [$booking, $passenger] = $this->scene();

        $row = $this->replayRow($passenger->id);

        $this->assertEqualsWithDelta(31500.00, (float) $row->historic_package_value, 0.005);
        $this->assertEqualsWithDelta($this->expected($passenger), (float) $row->historic_package_value, 0.005);
        $this->assertEquals('2026-07-10 10:00:00', $row->anchor_at);
    }

    public function test_replays_child_percentage(): void
    {
        [, $passenger] = $this->scene(['passenger_type' => 'child']);

        $row = $this->replayRow($passenger->id);

        // 28000 * 70% + 2000 + 1500 = 23100.
        $this->assertEqualsWithDelta(23100.00, (float) $row->historic_package_value, 0.005);
        $this->assertEqualsWithDelta($this->expected($passenger), (float) $row->historic_package_value, 0.005);
    }

    public function test_replays_offer_type_fare(): void
    {
        $visaPrice = $this->pvVisaPrice($this->user, 2000.00);
        $fare = $this->pvTicketFare($this->user, ['selling_fare' => 30000.00, 'ticket_type' => 'offer', 'offer_price' => 27000.00]);
        $package = $this->pvPackage($this->user, $visaPrice, ['ticket_fare_id' => $fare->id, 'regular_price' => 30000.00]);

        $this->pvLogFare($fare, 'created', null, [
            'selling_fare' => '30000.000000', 'offer_price' => '25000.000000', 'ticket_type' => 'offer',
            'child_fare_percentage' => '70.00', 'infant_fare_percentage' => '30.00',
        ], '2026-07-01 10:00:00', $this->user);
        $this->pvLogFare($fare, 'updated', ['offer_price' => '25000.000000'], ['offer_price' => '26000.000000'], '2026-07-05 10:00:00', $this->user);
        $this->pvLogFare($fare, 'updated', ['offer_price' => '26000.000000'], ['offer_price' => '27000.000000'], '2026-08-01 10:00:00', $this->user);

        $booking = $this->pvBooking($package, $this->user, $this->fpCharge, $this->district);
        $booking->created_at = '2026-07-10 09:00:00';
        $booking->saveQuietly();
        $passenger = $this->pvPassenger($booking, ['ticket_fare_id' => $fare->id]);
        $this->pvSetCreatedAt($passenger, '2026-07-10 10:00:00');
        $passenger = $passenger->fresh();

        $row = $this->replayRow($passenger->id);

        // offer 26000 at anchor + 2000 + 1500 = 29500.
        $this->assertEqualsWithDelta(29500.00, (float) $row->historic_package_value, 0.005);
        $this->assertEqualsWithDelta($this->expected($passenger), (float) $row->historic_package_value, 0.005);
    }

    public function test_replays_visa_only_and_ticket_only(): void
    {
        [$booking, $visaOnly] = $this->scene(['service_required' => 'visa_only']);
        $ticketOnly = $this->pvPassenger($booking, [
            'ticket_fare_id' => $booking->package->ticket_fare_id,
            'service_required' => 'ticket_only',
        ]);
        $this->pvSetCreatedAt($ticketOnly, '2026-07-10 10:00:00');
        $ticketOnly = $ticketOnly->fresh();

        // visa_only: 0 + 2000 + 1500 = 3500.
        $row = $this->replayRow($visaOnly->id);
        $this->assertEqualsWithDelta(3500.00, (float) $row->historic_package_value, 0.005);
        $this->assertEqualsWithDelta($this->expected($visaOnly), (float) $row->historic_package_value, 0.005);

        // ticket_only: 28000 + 0 + 1500 = 29500.
        $row = $this->replayRow($ticketOnly->id);
        $this->assertEqualsWithDelta(29500.00, (float) $row->historic_package_value, 0.005);
        $this->assertEqualsWithDelta($this->expected($ticketOnly), (float) $row->historic_package_value, 0.005);
    }

    public function test_replays_double_ticket_legs(): void
    {
        $visaPrice = $this->pvVisaPrice($this->user, 2000.00);
        $inbound = $this->pvTicketFare($this->user, ['selling_fare' => 20000.00]);
        $outbound = $this->pvTicketFare($this->user, ['selling_fare' => 16000.00]);
        $package = $this->pvPackage($this->user, $visaPrice, [
            'is_double_ticket' => true,
            'ticket_fare_id' => null,
            'ticket_fare_inbound_id' => $inbound->id,
            'ticket_fare_outbound_id' => $outbound->id,
        ]);

        $this->pvLogFare($inbound, 'created', null, [
            'selling_fare' => '18000.000000', 'offer_price' => null, 'ticket_type' => 'regular',
            'child_fare_percentage' => '70.00', 'infant_fare_percentage' => '30.00',
        ], '2026-07-01 10:00:00', $this->user);
        $this->pvLogFare($inbound, 'updated', ['selling_fare' => '18000.000000'], ['selling_fare' => '20000.000000'], '2026-08-01 10:00:00', $this->user);
        $this->pvLogFare($outbound, 'created', null, [
            'selling_fare' => '15000.000000', 'offer_price' => null, 'ticket_type' => 'regular',
            'child_fare_percentage' => '70.00', 'infant_fare_percentage' => '30.00',
        ], '2026-07-01 10:00:00', $this->user);
        $this->pvLogFare($outbound, 'updated', ['selling_fare' => '15000.000000'], ['selling_fare' => '16000.000000'], '2026-08-01 10:00:00', $this->user);

        $booking = $this->pvBooking($package, $this->user, $this->fpCharge, $this->district);
        $booking->created_at = '2026-07-10 09:00:00';
        $booking->saveQuietly();
        $passenger = $this->pvPassenger($booking, [
            'ticket_fare_id' => null,
            'ticket_fare_inbound_id' => $inbound->id,
            'ticket_fare_outbound_id' => $outbound->id,
        ]);
        $this->pvSetCreatedAt($passenger, '2026-07-10 10:00:00');
        $passenger = $passenger->fresh();

        $row = $this->replayRow($passenger->id);

        // 18000 + 15000 + 2000 + 1500 = 36500.
        $this->assertEqualsWithDelta(36500.00, (float) $row->historic_package_value, 0.005);
        $this->assertEqualsWithDelta($this->expected($passenger), (float) $row->historic_package_value, 0.005);
    }

    public function test_replays_swapped_package_anchor(): void
    {
        [$booking, $passenger, $package] = $this->scene();

        $packageB = $this->pvPackage($this->user, $this->pvVisaPrice($this->user, 2000.00), [
            'ticket_fare_id' => $package->ticket_fare_id,
        ]);
        $booking->package_id = $packageB->id;
        $booking->saveQuietly();
        $this->pvLogSwap($booking, $package->id, $packageB->id, '2026-07-20 10:00:00', $this->user);
        $passenger = $passenger->fresh();

        $row = $this->replayRow($passenger->id);

        // Anchor moves to the swap (2026-07-20): fare still 28000 until 08-01.
        $this->assertEquals('2026-07-20 10:00:00', $row->anchor_at);
        $this->assertEqualsWithDelta($this->expected($passenger), (float) $row->historic_package_value, 0.005);
    }

    public function test_event_query_reports_historic_value(): void
    {
        [$booking, $passenger] = $this->scene();
        $log = new PassengerUpdateLog([
            'passenger_id' => $passenger->id,
            'user_id' => $this->user->id,
            'passport_no' => $passenger->passport_no,
            'action' => 'updated',
            'old_values' => ['package_value' => 31500.00],
            'new_values' => ['package_value' => 33500.00],
        ]);
        $log->created_at = '2026-08-05 10:00:00';
        $log->save();

        $block = $this->block('A');
        $scoped = preg_replace(
            '/\s+FROM events e\s+JOIN historic h ON h\.passenger_id = e\.passenger_id\s+ORDER BY e\.recalc_at DESC;/',
            ' FROM events e JOIN historic h ON h.passenger_id = e.passenger_id WHERE e.passenger_id = ? ORDER BY e.recalc_at DESC;',
            $block,
            1,
            $count
        );
        $this->assertSame(1, $count);

        $rows = DB::select($scoped, [$passenger->id]);
        $this->assertCount(1, $rows);
        $this->assertEqualsWithDelta(31500.00, (float) $rows[0]->historic_package_value, 0.005);
        $this->assertEqualsWithDelta(33500.00, (float) $rows[0]->new_package_value, 0.005);
        $this->assertEqualsWithDelta(2000.00, (float) $rows[0]->stored_minus_historic, 0.005);
    }

    /**
     * Run Query B scoped to one passenger. Query B embeds the shared replay
     * chain, so each row carries the historic "supposed to be" value next to
     * the stored one. Returns [] when the passenger is not flagged.
     */
    private function queryBRows(int $passengerId): array
    {
        $block = rtrim($this->block('B'), " \t\n\r;");

        return DB::select("SELECT * FROM (\n{$block}\n) q WHERE q.passenger_id = ?", [$passengerId]);
    }

    /**
     * Query B flags the wrong recalc AND reports the historic "supposed to be"
     * value (and the drift) from the shared replay chain it LEFT JOINs.
     */
    public function test_query_b_reports_historic_value_for_wrong_recalc(): void
    {
        $visaPrice = $this->pvVisaPrice($this->user, 2000.00);
        $fare = $this->pvTicketFare($this->user, ['selling_fare' => 30000.00]);
        $package = $this->pvPackage($this->user, $visaPrice, [
            'ticket_fare_id' => $fare->id,
            'regular_price' => 30000.00,
        ]);
        // Fare history stops BEFORE the anchor (07-10): last price is 28000.
        $this->pvLogFare($fare, 'created', null, [
            'selling_fare' => '26000.000000', 'offer_price' => null, 'ticket_type' => 'regular',
            'child_fare_percentage' => '70.00', 'infant_fare_percentage' => '30.00',
        ], '2026-07-01 10:00:00', $this->user);
        $this->pvLogFare($fare, 'updated', ['selling_fare' => '26000.000000'], ['selling_fare' => '28000.000000'], '2026-07-05 10:00:00', $this->user);

        $booking = $this->pvBooking($package, $this->user, $this->fpCharge, $this->district, ['total_value' => 33500.00]);
        $booking->created_at = '2026-07-10 09:00:00';
        $booking->saveQuietly();

        $passenger = $this->pvPassenger($booking, [
            'ticket_fare_id' => $fare->id,
            'package_value' => 33500.00,
        ]);
        $this->pvSetCreatedAt($passenger, '2026-07-10 10:00:00');
        $passenger = $passenger->fresh();

        // A wrong recalc event overwrote 31500 (anchor replay) with 33500 (live).
        $log = new PassengerUpdateLog([
            'passenger_id' => $passenger->id,
            'user_id' => $this->user->id,
            'passport_no' => $passenger->passport_no,
            'action' => 'updated',
            'old_values' => ['package_value' => 31500.00],
            'new_values' => ['package_value' => 33500.00],
        ]);
        $log->created_at = '2026-08-05 10:00:00';
        $log->save();

        $rows = $this->queryBRows($passenger->id);
        $this->assertCount(1, $rows);

        $row = $rows[0];
        $this->assertSame($passenger->id, (int) $row->passenger_id);
        $this->assertEqualsWithDelta(33500.00, (float) $row->current_stored_value, 0.005);
        $this->assertEqualsWithDelta(31500.00, (float) $row->historic_package_value, 0.005);
        $this->assertEqualsWithDelta($this->expected($passenger), (float) $row->historic_package_value, 0.005);
        $this->assertEqualsWithDelta(2000.00, (float) $row->stored_minus_historic, 0.005);
        $this->assertEquals('2026-07-10 10:00:00', $row->anchor_at);
    }

    /**
     * Query B is event-based: a later fare/package price update that only
     * shifted the live fares is NOT flagged, as long as no package_value
     * rewrite event exists for the passenger.
     */
    public function test_query_b_ignores_passengers_without_a_wrong_event(): void
    {
        [, $passenger] = $this->scene(); // fare 28000 (07-05) -> 30000 (08-01), anchor 07-10.

        $this->assertCount(0, $this->queryBRows($passenger->id));
    }
}
