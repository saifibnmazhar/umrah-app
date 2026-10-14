<?php

namespace App\Observers;

use App\Models\Booking;
use App\Models\BookingUpdateLog;
use App\Services\ProfitCalculationService;
use Illuminate\Support\Facades\Auth;

class BookingObserver
{
    public function created(Booking $booking): void
    {
        // Always log (D8): unauthenticated/CLI writes carry user_id = NULL
        // ("system") — the replay engine depends on complete history.
        BookingUpdateLog::create([
            'booking_id' => $booking->id,
            'user_id' => Auth::id(),
            'booking_invoice_id' => $booking->invoice_id,
            'action' => 'created',
            'old_values' => null,
            'new_values' => $booking->attributesToArray(),
        ]);
    }

    public function updated(Booking $booking): void
    {
        $dirty = $booking->getDirty();

        if (! empty(array_intersect_key($dirty, array_flip(['discount_amount', 'discount_value', 'is_cancelled'])))) {
            app(ProfitCalculationService::class)->recalculateBookingProfit($booking);
        }

        if (empty($dirty)) {
            return;
        }

        $original = $booking->getOriginal();
        $oldValues = [];
        $newValues = [];
        foreach ($dirty as $key => $newValue) {
            $oldValues[$key] = $original[$key] ?? null;
            $newValues[$key] = $newValue;
        }

        BookingUpdateLog::create([
            'booking_id' => $booking->id,
            'user_id' => Auth::id(),
            'booking_invoice_id' => $booking->invoice_id,
            'action' => 'updated',
            'old_values' => $oldValues,
            'new_values' => $newValues,
        ]);
    }

    public function deleting(Booking $booking): void
    {
        $oldValues = collect($booking->attributesToArray())->except(['created_at', 'updated_at'])->toArray();

        BookingUpdateLog::create([
            'booking_id' => $booking->id,
            'user_id' => Auth::id(),
            'booking_invoice_id' => $booking->invoice_id,
            'action' => 'deleted',
            'old_values' => $oldValues,
            'new_values' => null,
        ]);
    }
}
