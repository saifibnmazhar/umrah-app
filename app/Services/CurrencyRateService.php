<?php

namespace App\Services;

use App\Models\CurrencyRate;
use Illuminate\Support\Carbon;

class CurrencyRateService
{
    /**
     * In-request cache keyed by date string, so repeated lookups for the
     * same rate date (e.g. one-per-row in report loops) don't hit the DB.
     */
    protected array $cache = [];

    public function getRateForDate($date): ?CurrencyRate
    {
        $key = $date instanceof Carbon
            ? $date->toDateTimeString()
            : (string) $date;

        if (array_key_exists($key, $this->cache)) {
            return $this->cache[$key];
        }

        $rate = CurrencyRate::where('created_at', '<=', $date)
            ->orderBy('created_at', 'desc')
            ->first();

        return $this->cache[$key] = $rate;
    }

    public function getCurrentRate(): ?CurrencyRate
    {
        return $this->getRateForDate(now());
    }

    public function getFirstRate(): ?CurrencyRate
    {
        return CurrencyRate::orderBy('created_at')->first();
    }

    public function getCurrentRateValue(): float
    {
        $rate = $this->getCurrentRate();

        return $rate ? (float) $rate->rate : 0;
    }

    public function convertSarToBdt(float $sarAmount, $date = null): float
    {
        $rate = $date
            ? ((float) ($this->getRateForDate($date)?->rate ?? 0))
            : $this->getCurrentRateValue();

        return $sarAmount * $rate;
    }

    public function convertBdtToSar(float $bdtAmount, $date = null): float
    {
        $rate = $date
            ? ((float) ($this->getRateForDate($date)?->rate ?? 0))
            : $this->getCurrentRateValue();
        if ($rate <= 0) {
            return 0;
        }

        return $bdtAmount / $rate;
    }

    public function convert(float $amount, string $fromCurrency, string $toCurrency, $date = null): float
    {
        if ($fromCurrency === $toCurrency) {
            return $amount;
        }

        $rate = $date
            ? ((float) ($this->getRateForDate($date)?->rate ?? 0))
            : $this->getCurrentRateValue();
        if ($rate <= 0) {
            return 0;
        }

        return match ([$fromCurrency, $toCurrency]) {
            ['SAR', 'BDT'] => $amount * $rate,
            ['BDT', 'SAR'] => $amount / $rate,
            default => $amount,
        };
    }
}
