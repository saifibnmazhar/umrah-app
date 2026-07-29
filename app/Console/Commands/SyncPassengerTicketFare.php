<?php

namespace App\Console\Commands;

use App\Enums\ServiceRequired;
use App\Models\Passenger;
use Illuminate\Console\Command;

class SyncPassengerTicketFare extends Command
{
    protected $signature = 'passengers:sync-ticket-fare';

    protected $description = 'Sync passenger ticket_fare_id to match the package ticket_fare_id under which the passenger was booked';

    public function handle(): int
    {
        $updated = 0;

        Passenger::query()
            ->where(function ($q) {
                $q->where('service_required', '!=', ServiceRequired::VISA_ONLY)
                  ->orWhereNull('service_required');
            })
            ->whereHas('booking.package')
            ->with('booking.package')
            ->chunk(100, function ($passengers) use (&$updated) {
                foreach ($passengers as $passenger) {
                    $packageFareId = $passenger->booking->package->ticket_fare_id;

                    if ($packageFareId === null) {
                        continue;
                    }

                    if ($passenger->ticket_fare_id != $packageFareId) {
                        $passenger->update(['ticket_fare_id' => $packageFareId]);
                        $updated++;
                    }
                }
            });

        $this->info("Synced {$updated} passenger ticket_fare_id(s) to their package ticket_fare_id.");

        return self::SUCCESS;
    }
}
