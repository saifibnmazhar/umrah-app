<?php

namespace App\Observers;

use App\Models\TicketFare;
use App\Models\TicketFareUpdateLog;
use Illuminate\Support\Facades\Auth;

class TicketFareObserver
{
    public function created(TicketFare $ticketFare): void
    {
        $user = Auth::user();
        if (! $user) {
            return;
        }

        TicketFareUpdateLog::create([
            'ticket_fare_id' => $ticketFare->id,
            'user_id' => $user->id,
            'action' => 'created',
            'old_values' => null,
            'new_values' => $ticketFare->attributesToArray(),
        ]);
    }

    public function updated(TicketFare $ticketFare): void
    {
        $dirty = $ticketFare->getDirty();
        if (empty($dirty)) {
            return;
        }

        $user = Auth::user();
        if (! $user) {
            return;
        }

        $original = $ticketFare->getOriginal();
        $oldValues = [];
        $newValues = [];
        foreach ($dirty as $key => $newValue) {
            $oldValues[$key] = $original[$key] ?? null;
            $newValues[$key] = $newValue;
        }

        TicketFareUpdateLog::create([
            'ticket_fare_id' => $ticketFare->id,
            'user_id' => $user->id,
            'action' => 'updated',
            'old_values' => $oldValues,
            'new_values' => $newValues,
        ]);
    }

    public function deleting(TicketFare $ticketFare): void
    {
        $user = Auth::user();
        if (! $user) {
            return;
        }

        $oldValues = collect($ticketFare->attributesToArray())
            ->except(['created_at', 'updated_at'])
            ->toArray();

        TicketFareUpdateLog::create([
            'ticket_fare_id' => $ticketFare->id,
            'user_id' => $user->id,
            'action' => 'deleted',
            'old_values' => $oldValues,
            'new_values' => null,
        ]);
    }
}
