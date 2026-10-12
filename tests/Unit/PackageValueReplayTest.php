<?php

namespace Tests\Unit;

use App\Models\Package;
use App\Models\PackageUpdateLog;
use App\Models\User;
use App\Models\VisaSellingPrice;
use App\Support\LogReplay;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PackageValueReplayTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private VisaSellingPrice $priceA;

    private VisaSellingPrice $priceB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->priceA = VisaSellingPrice::create(['user_id' => $this->user->id, 'selling_price' => 2000.00]);
        $this->priceB = VisaSellingPrice::create(['user_id' => $this->user->id, 'selling_price' => 2500.00]);
    }

    private function makePackage(array $overrides = []): Package
    {
        // withoutEvents: PackageObserver::created would otherwise write its own
        // log row and make these tests depend on observer behavior.
        return Package::withoutEvents(fn () => Package::create(array_merge([
            'package_name' => 'Pkg '.uniqid(),
            'visa_selling_price_id' => $this->priceA->id,
            'regular_price' => 35000.00,
            'offer_price' => 32000.00,
            'service_charge' => 1500.00,
            'is_active' => true,
            'is_double_ticket' => false,
        ], $overrides)));
    }

    private function log(Package $package, string $action, ?array $old, ?array $new, ?string $at): void
    {
        $log = new PackageUpdateLog([
            'package_id' => $package->id,
            'user_id' => $this->user->id,
            'action' => $action,
            'old_values' => $old,
            'new_values' => $new,
        ]);
        $log->created_at = $at; // nullable column — null rows must be skipped by replay
        $log->save();
    }

    public function test_seed_from_created_event_and_walk_updates(): void
    {
        $package = $this->makePackage();
        $this->log($package, 'created', null, ['service_charge' => '1000.00'], '2026-09-20 10:00:00');
        $this->log($package, 'updated', ['service_charge' => '1000.00'], ['service_charge' => '2000.00'], '2026-09-24 10:00:00');

        $package = $package->fresh();

        $this->assertSame('1000.00', $package->valueAt('service_charge', '2026-09-22 00:00:00'));
        // Anchor boundary is inclusive.
        $this->assertSame('2000.00', $package->valueAt('service_charge', '2026-09-24 10:00:00'));
        $this->assertSame('2000.00', $package->valueAt('service_charge', '2026-09-25 00:00:00'));
    }

    public function test_no_logs_falls_back_to_live_value(): void
    {
        $package = $this->makePackage(['service_charge' => 1500.00]);

        $this->assertEqualsWithDelta(
            1500.0,
            (float) $package->fresh()->valueAt('service_charge', '2026-09-22 00:00:00'),
            0.001
        );
    }

    public function test_leading_fare_updated_row_seeds_from_its_old_values(): void
    {
        // The fare→package cascade writes `fare_updated` rows manually; the very
        // first row for a package may be one, so seeding must honor any action.
        $package = $this->makePackage();
        $this->log($package, 'fare_updated',
            ['regular_price' => '34000.00', 'offer_price' => '31000.00'],
            ['regular_price' => '35000.00', 'offer_price' => '32000.00'],
            '2026-09-21 09:00:00');

        $package = $package->fresh();

        $this->assertSame('34000.00', $package->valueAt('regular_price', '2026-09-20 00:00:00'));
        $this->assertSame('35000.00', $package->valueAt('regular_price', '2026-09-21 09:00:00'));
    }

    public function test_fare_updated_row_is_applied_during_walk(): void
    {
        $package = $this->makePackage();
        $this->log($package, 'created', null, ['regular_price' => '34000.00'], '2026-09-20 10:00:00');
        $this->log($package, 'fare_updated', ['regular_price' => '34000.00'], ['regular_price' => '36000.00'], '2026-09-23 10:00:00');

        $package = $package->fresh();

        $this->assertSame('34000.00', $package->valueAt('regular_price', '2026-09-22 00:00:00'));
        $this->assertSame('36000.00', $package->valueAt('regular_price', '2026-09-24 00:00:00'));
    }

    public function test_null_created_at_rows_are_skipped(): void
    {
        $package = $this->makePackage();
        $this->log($package, 'created', null, ['service_charge' => '1000.00'], '2026-09-20 10:00:00');
        $this->log($package, 'updated', ['service_charge' => '1000.00'], ['service_charge' => '5555.00'], null);
        $this->log($package, 'updated', ['service_charge' => '1000.00'], ['service_charge' => '2000.00'], '2026-09-24 10:00:00');

        $package = $package->fresh();

        // The null-timestamp row must not shadow the well-formed one, even at a
        // far-future anchor.
        $this->assertSame('2000.00', $package->valueAt('service_charge', '2030-01-01 00:00:00'));
        $this->assertSame('1000.00', $package->valueAt('service_charge', '2026-09-22 00:00:00'));
    }

    public function test_ticket_type_replays_as_string_via_shared_engine(): void
    {
        $events = [
            ['action' => 'created', 'old_values' => [], 'new_values' => ['ticket_type' => 'regular'], 'created_at' => '2026-09-20 10:00:00'],
            ['action' => 'updated', 'old_values' => ['ticket_type' => 'regular'], 'new_values' => ['ticket_type' => 'offer'], 'created_at' => '2026-09-24 10:00:00'],
        ];

        $this->assertSame('regular', LogReplay::valueFromEvents($events, 'ticket_type', '2026-09-22 00:00:00', 'regular'));
        $this->assertSame('offer', LogReplay::valueFromEvents($events, 'ticket_type', '2026-09-25 00:00:00', 'regular'));
    }

    public function test_fk_column_replays_as_int(): void
    {
        $fromId = (int) $this->priceA->id;
        $toId = (int) $this->priceB->id;

        $package = $this->makePackage(['visa_selling_price_id' => $fromId]);
        $this->log($package, 'created', null, ['visa_selling_price_id' => $fromId], '2026-09-20 10:00:00');
        $this->log($package, 'updated', ['visa_selling_price_id' => $fromId], ['visa_selling_price_id' => $toId], '2026-09-24 10:00:00');

        $package = $package->fresh();

        $this->assertSame($fromId, $package->valueAt('visa_selling_price_id', '2026-09-22 00:00:00'));
        $this->assertSame($toId, $package->valueAt('visa_selling_price_id', '2026-09-25 00:00:00'));
    }

    public function test_replay_is_column_isolated(): void
    {
        $package = $this->makePackage();
        $this->log($package, 'created', null, ['service_charge' => '1000.00', 'regular_price' => '34000.00'], '2026-09-20 10:00:00');
        $this->log($package, 'updated', ['service_charge' => '1000.00'], ['service_charge' => '2000.00'], '2026-09-24 10:00:00');

        $package = $package->fresh();

        $this->assertSame('34000.00', $package->valueAt('regular_price', '2026-09-25 00:00:00'));
    }
}
