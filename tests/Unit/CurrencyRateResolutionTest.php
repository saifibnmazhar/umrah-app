<?php

namespace Tests\Unit;

use App\Models\CurrencyRate;
use App\Models\User;
use App\Services\CurrencyRateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CurrencyRateResolutionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()->forgetInstance(CurrencyRateService::class);
    }

    private function seedRates(float $oldRate = 28.0, float $newRate = 30.0): void
    {
        $user = User::create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => bcrypt('x')]);

        DB::table('currency_rates')->insert([
            'user_id' => $user->id,
            'rate' => $oldRate,
            'created_at' => now()->subDays(10),
            'updated_at' => now()->subDays(10),
        ]);

        DB::table('currency_rates')->insert([
            'user_id' => $user->id,
            'rate' => $newRate,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_get_first_rate_value_returns_oldest_rate(): void
    {
        $this->seedRates(28.0, 30.0);

        $service = app(CurrencyRateService::class);

        $this->assertEquals(28.0, $service->getFirstRateValue());
    }

    public function test_get_first_rate_value_returns_zero_when_no_rates(): void
    {
        $service = app(CurrencyRateService::class);

        $this->assertEquals(0.0, $service->getFirstRateValue());
    }

    public function test_resolve_rate_uses_explicit_rate_when_provided(): void
    {
        $this->seedRates(28.0, 30.0);
        $explicit = CurrencyRate::where('rate', 30.0)->first();

        $service = app(CurrencyRateService::class);

        $this->assertEquals(30.0, $service->resolveRate($explicit));
    }

    public function test_resolve_rate_falls_back_to_rate_for_date(): void
    {
        $this->seedRates(28.0, 30.0);

        $service = app(CurrencyRateService::class);

        // 5 days ago should resolve to the old rate (28.0)
        $this->assertEquals(28.0, $service->resolveRate(null, now()->subDays(5)));
    }

    public function test_resolve_rate_falls_back_to_first_rate_when_no_date(): void
    {
        $this->seedRates(28.0, 30.0);

        $service = app(CurrencyRateService::class);

        $this->assertEquals(28.0, $service->resolveRate(null));
    }

    public function test_resolve_rate_three_tier_fallback(): void
    {
        $this->seedRates(28.0, 30.0);

        $service = app(CurrencyRateService::class);

        // Explicit null + explicit date should resolve via date
        $rate = $service->resolveRate(null, now()->subDays(5));
        $this->assertEquals(28.0, $rate);
    }

    public function test_resolve_rate_returns_zero_when_no_rates_exist(): void
    {
        $service = app(CurrencyRateService::class);

        $this->assertEquals(0.0, $service->resolveRate(null, now()));
    }

    public function test_get_all_rates_returns_all_ordered_by_created_at(): void
    {
        $this->seedRates(28.0, 30.0);

        $service = app(CurrencyRateService::class);
        $rates = $service->getAllRates();

        $this->assertCount(2, $rates);
        $this->assertEquals(28.0, $rates[0]->rate);
        $this->assertEquals(30.0, $rates[1]->rate);
    }

    public function test_get_all_rates_returns_empty_array_when_no_rates(): void
    {
        $service = app(CurrencyRateService::class);

        $this->assertEquals([], $service->getAllRates());
    }
}
