<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Services\BookingService;
use Illuminate\Console\Command;

class SyncBookingFinancials extends Command
{
    protected $signature = 'bookings:sync-financials';

    protected $description = 'Recalculate booking totals and invoice amounts from passenger package values';

    public function handle(): int
    {
        $bookingService = app(BookingService::class);
        $count = 0;

        Booking::has('passengers')->chunk(100, function ($bookings) use ($bookingService, &$count) {
            foreach ($bookings as $booking) {
                $bookingService->syncFinancials($booking);
                $count++;
            }
        });

        $this->info("Synced financials for {$count} booking(s).");

        return self::SUCCESS;
    }
}
