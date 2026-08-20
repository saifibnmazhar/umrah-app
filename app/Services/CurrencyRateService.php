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

    /**
     * Returns all currency rates ordered by created_at ascending.
     */
    public function getAllRates(): array
    {
        return CurrencyRate::orderBy('created_at')->get()->all();
    }

    /**
     * Returns the rate value of the first (oldest) currency rate,
     * or 0.0 when no rate has been set.
     */
    public function getFirstRateValue(): float
    {
        return (float) ($this->getFirstRate()?->rate ?? 0);
    }

    public function getCurrentRateValue(): float
    {
        $rate = $this->getCurrentRate();

        return $rate ? (float) $rate->rate : 0;
    }

    /**
     * Unified three-tier rate resolution:
     *  1. Explicit CurrencyRate relation (e.g. booking.currencyRate)
     *  2. Rate effective on the given date (e.g. booking.created_at)
     *  3. First (oldest) rate on file
     * Returns 0.0 if none found.
     */
    public function resolveRate(?CurrencyRate $explicitRate = null, $date = null): float
    {
        if ($explicitRate) {
            return (float) $explicitRate->rate;
        }

        if ($date) {
            $rate = $this->getRateForDate($date);
            if ($rate) {
                return (float) $rate->rate;
            }
        }

        return $this->getFirstRateValue();
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
