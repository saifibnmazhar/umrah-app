<?php

namespace App\Observers;

use App\Models\FingerprintDetail;
use App\Models\FingerprintDetailLog;
use Illuminate\Support\Facades\Auth;

class FingerprintDetailObserver
{
    protected array $trackedFields = [
        'fingerprint_id', 'passenger_id', 'status',
    ];

    public function updated(FingerprintDetail $fingerprintDetail): void
    {
        $user = Auth::user();
        if (!$user) return;

        $dirty = $fingerprintDetail->getDirty();
        $changedTracked = array_intersect_key($dirty, array_flip($this->trackedFields));
        if (empty($changedTracked)) return;

        $original = $fingerprintDetail->getOriginal();
        $oldValues = array_intersect_key($original, $changedTracked);

        $action = isset($changedTracked['status']) ? 'status_updated' : 'updated';

        FingerprintDetailLog::create([
            'fingerprint_detail_id' => $fingerprintDetail->id,
            'user_id' => $user->id,
            'action' => $action,
            'old_values' => $oldValues,
            'new_values' => $changedTracked,
        ]);
    }
}
