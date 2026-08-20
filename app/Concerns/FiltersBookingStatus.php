<?php

namespace App\Concerns;

use Illuminate\Database\Eloquent\Builder;

trait FiltersBookingStatus
{
    /**
     * Apply the booking_status filter to a query directly on the bookings table.
     *
     * Status values:
     *  - 'active'               → not cancelled
     *  - 'cancellation_processing' → is_cancelled + has a cancelled_booking with status 'cancellation processing'
     *  - 'cancelled'            → is_cancelled AND (no cancelled_booking OR status = 'cancelled')
     *  - null / unrecognized    → no filtering
     */
    public function scopeBookingStatus(Builder $query, ?string $status): Builder
    {
        if ($status === 'active') {
            return $query->where('is_cancelled', false);
        }

        if ($status === 'cancellation_processing') {
            return $query->where('is_cancelled', true)
                ->whereHas('cancelledBooking', fn ($q) => $q->where('status', 'cancellation processing'));
        }

        if ($status === 'cancelled') {
            return $query->where('is_cancelled', true)
                ->where(function ($q) {
                    $q->whereDoesntHave('cancelledBooking')
                        ->orWhereHas('cancelledBooking', fn ($q) => $q->where('status', 'cancelled'));
                });
        }

        return $query;
    }

    /**
     * Apply the booking_status filter to a query on a related model
     * (e.g. Passenger) where the booking is accessed via a 'booking' relation.
     */
    public function scopeBookingStatusViaBooking(Builder $query, ?string $status): Builder
    {
        if ($status === 'active') {
            return $query->whereHas('booking', fn ($bq) => $bq->where('is_cancelled', false));
        }

        if ($status === 'cancellation_processing') {
            return $query->whereHas('booking', fn ($bq) => $bq->where('is_cancelled', true)
                ->whereHas('cancelledBooking', fn ($cq) => $cq->where('status', 'cancellation processing'))
            );
        }

        if ($status === 'cancelled') {
            return $query->whereHas('booking', fn ($bq) => $bq->where('is_cancelled', true)
                ->where(fn ($bw) => $bw->whereDoesntHave('cancelledBooking')
                    ->orWhereHas('cancelledBooking', fn ($cq) => $cq->where('status', 'cancelled'))
                )
            );
        }

        return $query;
    }
}
