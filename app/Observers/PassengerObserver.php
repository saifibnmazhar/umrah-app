<?php

namespace App\Observers;

use App\Models\Passenger;
use App\Models\PassengerUpdateLog;
use Illuminate\Support\Facades\Auth;

class PassengerObserver
{
    public function updated(Passenger $passenger): void
    {
        $user = Auth::user();
        if (!$user) return;

        $dirty = $passenger->getDirty();
        if (empty($dirty)) return;

        $original = $passenger->getOriginal();
        $oldValues = [];
        $newValues = [];
        foreach ($dirty as $key => $newValue) {
            $oldValues[$key] = $original[$key] ?? null;
            $newValues[$key] = $newValue;
        }

        PassengerUpdateLog::create([
            'passenger_id' => $passenger->id,
            'user_id' => $user->id,
            'action' => 'updated',
            'old_values' => $oldValues,
            'new_values' => $newValues,
        ]);
    }

    public function deleting(Passenger $passenger): void
    {
        $user = Auth::user();
        if (!$user) return;

        $oldValues = collect($passenger->attributesToArray())->except(['created_at', 'updated_at'])->toArray();

        PassengerUpdateLog::create([
            'passenger_id' => $passenger->id,
            'user_id' => $user->id,
            'action' => 'deleted',
            'old_values' => $oldValues,
            'new_values' => null,
        ]);
    }
}
