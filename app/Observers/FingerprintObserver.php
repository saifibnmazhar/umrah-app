<?php

namespace App\Observers;

use App\Models\Fingerprint;
use App\Services\ProfitCalculationService;

class FingerprintObserver
{
    public function created(Fingerprint $fingerprint): void
    {
        $this->recalculateProfit($fingerprint);
    }

    public function updated(Fingerprint $fingerprint): void
    {
        if ($fingerprint->wasChanged('cost')) {
            $this->recalculateProfit($fingerprint);
        }
    }

    public function deleted(Fingerprint $fingerprint): void
    {
        $this->recalculateProfit($fingerprint);
    }

    protected function recalculateProfit(Fingerprint $fingerprint): void
    {
        if ($fingerprint->booking) {
            app(ProfitCalculationService::class)->recalculateBookingProfit($fingerprint->booking);
        }
    }
}
