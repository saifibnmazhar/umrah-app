<?php

namespace App\Services;

use App\Models\CurrencyRate;

class CurrencyRateService
{
    public function getCurrentRate(): ?CurrencyRate
    {
        return CurrencyRate::orderBy('created_at', 'desc')->first();
    }

    public function getCurrentRateValue(): float
    {
        $rate = $this->getCurrentRate();
        return $rate ? (float) $rate->rate : 0;
    }

    public function convertSarToBdt(float $sarAmount): float
    {
        $rate = $this->getCurrentRateValue();
        return $sarAmount * $rate;
    }

    public function convertBdtToSar(float $bdtAmount): float
    {
        $rate = $this->getCurrentRateValue();
        if ($rate <= 0) {
            return 0;
        }
        return $bdtAmount / $rate;
    }

    public function convert(float $amount, string $fromCurrency, string $toCurrency): float
    {
        if ($fromCurrency === $toCurrency) {
            return $amount;
        }

        $rate = $this->getCurrentRateValue();
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