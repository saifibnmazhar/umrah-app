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

                    // Known, intentional scope limit (plan 17 §2d): a package
                    // without a fare (incl. double-ticket packages, whose fares
                    // live on the inbound/outbound columns) skips the passenger
                    // entirely — FK and value both untouched.
                    if ($packageFareId === null) {
                        continue;
                    }

                    if ($passenger->ticket_fare_id != $packageFareId) {
                        $passenger->update(['ticket_fare_id' => $packageFareId]);
                        $passenger->refresh();
                    }

                    $oldValue = (float) ($passenger->package_value ?? 0);

                    // Historic recompute at the passenger's own anchor
                    // (earliest era start, floored at own creation) — never
                    // at live prices.
                    $bookingService->recalculatePassengerValues(
                        $passenger->booking,
                        $passenger->historicAnchor(),
                        $passenger
                    );

                    if (abs($oldValue - (float) $passenger->fresh()->package_value) >= 0.005) {
                        $updated++;
                    }
                }
            });

        $this->info("Synced {$updated} passenger record(s).");

        return self::SUCCESS;
    }
}
