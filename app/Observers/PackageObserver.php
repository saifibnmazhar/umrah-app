<?php

namespace App\Observers;

use App\Models\Package;
use App\Services\ProfitCalculationService;

class PackageObserver
{
    private const PROFIT_FIELDS = [
        'service_charge',
        'ticket_fare_id',
        'ticket_fare_inbound_id',
        'ticket_fare_outbound_id',
        'is_double_ticket',
    ];

    public function updated(Package $package): void
    {
        if (! $package->wasChanged(self::PROFIT_FIELDS)) {
            return;
        }

        $package->bookings()
            ->where('is_cancelled', false)
            ->chunkById(100, function ($bookings): void {
                $service = app(ProfitCalculationService::class);
                foreach ($bookings as $booking) {
                    $service->recalculateBookingProfit($booking);
                }
            });
    }
}
