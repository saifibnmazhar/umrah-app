<?php

namespace App\Observers;

use App\Models\FingerprintDetail;

class FingerprintDetailObserver
{
    public function created(FingerprintDetail $fingerprintDetail): void
    {
        $fingerprintDetail->passenger->syncComputedStatus();
    }

    public function updated(FingerprintDetail $fingerprintDetail): void
    {
        $fingerprintDetail->passenger->syncComputedStatus();
    }
}
