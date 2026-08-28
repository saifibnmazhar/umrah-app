<?php

namespace Tests\Unit;

use App\Models\CurrencyRate;
use App\Models\User;
use App\Services\CurrencyRateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CurrencyRateServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Reset the singleton cache between tests.
        app()->forgetInstance(CurrencyRateService::class);
    }

    public function test_get_rate_for_date_does_not_refetch_same_date(): void
    {
        $user = User::create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => bcrypt('x')]);
        CurrencyRate::create([
            'user_id' => $user->id,
            'rate' => 28.0000,
        ]);

        $service = app(CurrencyRateService::class);

        DB::enableQueryLog();
        $service->getRateForDate(now());
        $service->getRateForDate(now());
        $service->getRateForDate(now());
        DB::disableQueryLog();

        $this->assertLessThan(
            2,
            count(DB::getQueryLog()),
            'CurrencyRateService should cache getRateForDate within a date to avoid repeated queries.'
        );
    }

    public function test_different_dates_are_not_cached_together(): void
    {
        $user = User::create(['name' => 'Admin', 'email' => 'admin2@example.com', 'password' => bcrypt('x')]);
        $userId = $user->id;
        // A rate valid 10 days ago (created_at not mass-assignable, so use DB::table)
        DB::table('currency_rates')->insert([
            'user_id' => $userId,
            'rate' => 28.00,
            'created_at' => now()->subDays(10),
            'updated_at' => now()->subDays(10),
        ]);
        // A rate valid now
        DB::table('currency_rates')->insert([
            'user_id' => $userId,
            'rate' => 30.00,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $service = app(CurrencyRateService::class);

        // Two distinct dates should produce separate lookups (not collapse to one cached value).
        $a = $service->getRateForDate(now()->subDays(5));
        $b = $service->getRateForDate(now());

        $this->assertNotNull($a, 'Should resolve the older rate for an earlier date.');
        $this->assertNotNull($b, 'Should resolve the newer rate for now.');
        $this->assertNotEquals($a->rate, $b->rate, 'Different dates should resolve to different rates.');
    }
}
