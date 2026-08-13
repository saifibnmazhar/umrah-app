<?php

namespace App\Console\Commands;

use App\Enums\ServiceRequired;
use App\Models\Passenger;
use App\Services\BookingService;
use Illuminate\Console\Command;

class SyncPassengerTicketFare extends Command
{
    protected $signature = 'passengers:sync-ticket-fare';

    protected $description = 'Sync passenger ticket_fare_id and package_value to match the package ticket_fare_id under which the passenger was booked';

    public function handle(): int
    {
        $updated = 0;
        $bookingService = app(BookingService::class);

        Passenger::query()
            ->where(function ($q) {
                $q->where('service_required', '!=', ServiceRequired::VISA_ONLY)
                    ->orWhereNull('service_required');
            })
            ->whereHas('booking.package')
            ->with('booking.package')
            ->chunk(100, function ($passengers) use (&$updated, $bookingService) {
                foreach ($passengers as $passenger) {
                    $packageFareId = $passenger->booking->package->ticket_fare_id;

                    if ($packageFareId === null) {
                        continue;
                    }

                    if ($passenger->ticket_fare_id != $packageFareId) {
                        $passenger->update(['ticket_fare_id' => $packageFareId]);
                        $passenger->refresh();
                    }

                    $newPackageValue = $bookingService->calculatePackageValue($passenger);

                    if ((float) ($passenger->package_value ?? 0) !== $newPackageValue) {
                        $passenger->update(['package_value' => $newPackageValue]);
                        $updated++;
                    }
                }
            });

        $this->info("Synced {$updated} passenger record(s).");

        return self::SUCCESS;
    }
}
