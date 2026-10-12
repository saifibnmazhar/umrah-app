<?php

namespace Tests\Feature;

use App\Models\Package;
use App\Models\Passenger;
use App\Models\PassengerUpdateLog;
use App\Models\TicketFare;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\CreatesPackageValueFixtures;
use Tests\TestCase;

/**
 * Test 8 (plan 17): passengers:sync-ticket-fare / -v2 keep their FK
 * re-pointing half but recompute package_value at historicAnchor() — never at
 * live prices. Also documents the two intentional scope limits: V1's
 * null-package-fare skip and V2's `if ($changed)` gate.
 */
class SyncPassengerTicketFareHistoricTest extends TestCase
{
    use CreatesPackageValueFixtures, RefreshDatabase;

    private $user;

    private $district;

    private $fpCharge;

    private $visaPrice;

    private TicketFare $fare;

    private TicketFare $staleFare;

    private Package $package;

    protected function setUp(): void
    {
        parent::setUp();

        $branch = $this->pvBranch();
        $this->user = $this->pvUser($branch);
        $this->district = $this->pvDistrict();
        $this->fpCharge = $this->pvFpCharge($this->district, $this->user);
        $this->visaPrice = $this->pvVisaPrice($this->user, 2000.00);

        // Package fare with history: 28000 at anchor, 30000 live.
        $this->fare = $this->pvTicketFare($this->user, ['selling_fare' => 30000.00]);
        $this->pvLogFare($this->fare, 'created', null, [
            'selling_fare' => '28000.000000', 'offer_price' => null, 'ticket_type' => 'regular',
            'child_fare_percentage' => '70.00', 'infant_fare_percentage' => '30.00',
        ], '2026-07-01 10:00:00', $this->user);
        $this->pvLogFare($this->fare, 'updated', ['selling_fare' => '28000.000000'], ['selling_fare' => '30000.000000'], '2026-08-01 10:00:00', $this->user);

        $this->staleFare = $this->pvTicketFare($this->user, ['selling_fare' => 20000.00]);

        $this->package = $this->pvPackage($this->user, $this->visaPrice, [
            'ticket_fare_id' => $this->fare->id,
            'regular_price' => 30000.00,
        ]);
    }

    private function driftedPassenger(Package $package, int $fareId, string $createdAt = '2026-07-10 10:00:00', float $value = 12345.00): Passenger
    {
        $booking = $this->pvBooking($package, $this->user, $this->fpCharge, $this->district, [
            'total_value' => $value,
        ]);
        $passenger = $this->pvPassenger($booking, [
            'ticket_fare_id' => $fareId,
            'package_value' => $value,
        ]);
        $this->pvSetCreatedAt($passenger, $createdAt);

        return $passenger;
    }

    public function test_v1_repoints_fk_and_recomputes_at_historic_anchor(): void
    {
        $passenger = $this->driftedPassenger($this->package, $this->staleFare->id);

        $this->artisan('passengers:sync-ticket-fare')->assertSuccessful();

        $fresh = $passenger->fresh();
        $this->assertEquals($this->fare->id, $fresh->ticket_fare_id, 'FK re-pointed to the package fare');
        // Anchor = 2026-07-10 => fare 28000 (+ visa 2000 + service 1500).
        // NOT live 30000 (33500), NOT the stale fare (13500).
        $this->assertEqualsWithDelta(31500.00, (float) $fresh->package_value, 0.001, 'recomputed at historicAnchor()');
        $this->assertSame(0, PassengerUpdateLog::where('action', 'recalculated')->count(),
            'unauthenticated command must not write recalculated logs (D9)');
    }

    public function test_v1_skips_passengers_whose_package_has_no_fare(): void
    {
        $nullFarePackage = $this->pvPackage($this->user, $this->visaPrice, [
            'ticket_fare_id' => null,
            'regular_price' => 30000.00,
        ]);
        $passenger = $this->driftedPassenger($nullFarePackage, $this->staleFare->id);

        $this->artisan('passengers:sync-ticket-fare')->assertSuccessful();

        $fresh = $passenger->fresh();
        // Documented intentional limit (plan 17 §2d): a null package fare
        // skips the passenger entirely — FK and value both untouched.
        $this->assertEquals($this->staleFare->id, $fresh->ticket_fare_id, 'V1 null-fare skip leaves the FK alone');
        $this->assertEqualsWithDelta(12345.00, (float) $fresh->package_value, 0.001, 'V1 null-fare skip leaves the value alone');
    }

    public function test_v2_repoints_fk_and_recomputes_but_gates_matching_fares(): void
    {
        // Passenger A: FK already matches the package => `if ($changed)` gate
        // skips recomputation even though package_value has drifted.
        $gated = $this->driftedPassenger($this->package, $this->fare->id);

        // Passenger B: FK stale => re-pointed, then recomputed at the anchor.
        $repaired = $this->driftedPassenger($this->package, $this->staleFare->id);

        $this->artisan('passengers:sync-ticket-fare-v2')->assertSuccessful();

        $gatedFresh = $gated->fresh();
        $this->assertEquals($this->fare->id, $gatedFresh->ticket_fare_id);
        $this->assertEqualsWithDelta(12345.00, (float) $gatedFresh->package_value, 0.001,
            'documented V2 limit: if ($changed) gate skips value repair when FKs already match');

        $repairedFresh = $repaired->fresh();
        $this->assertEquals($this->fare->id, $repairedFresh->ticket_fare_id, 'FK re-pointed');
        $this->assertEqualsWithDelta(31500.00, (float) $repairedFresh->package_value, 0.001,
            'V2 recomputes at historicAnchor(), not live prices');
    }

    public function test_v2_recompute_uses_anchor_even_after_live_fare_raise(): void
    {
        // Same expectation from the live-value side: after the command, the
        // stored value must equal the fare as of the anchor, proving the
        // calculator did not replay to live (30000 => 33500).
        $passenger = $this->driftedPassenger($this->package, $this->staleFare->id);

        $this->artisan('passengers:sync-ticket-fare-v2')->assertSuccessful();

        $this->assertEqualsWithDelta(31500.00, (float) $passenger->fresh()->package_value, 0.001);
        $this->assertNotEqualsWithDelta(33500.00, (float) $passenger->fresh()->package_value, 0.001);
    }
}
