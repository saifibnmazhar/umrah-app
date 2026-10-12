<?php

namespace App\Console\Commands;

use App\Enums\ServiceRequired;
use App\Models\IssuedTicket;
use App\Models\Passenger;
use App\Services\BookingService;
use Illuminate\Console\Command;

class SyncPassengerTicketFareV2 extends Command
{
    protected $signature = 'passengers:sync-ticket-fare-v2';

    protected $description = 'Sync passenger ticket_fare fields to match the package for both single and double ticket packages';

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
                    $package = $passenger->booking->package;
                    $changed = false;

                    if ($package->is_double_ticket) {
                        if ($passenger->ticket_fare_id !== null) {
                            $passenger->update(['ticket_fare_id' => null]);
                            $passenger->refresh();
                            $changed = true;
                        }

                        if ($passenger->ticket_fare_inbound_id !== $package->ticket_fare_inbound_id) {
                            $passenger->update(['ticket_fare_inbound_id' => $package->ticket_fare_inbound_id]);
                            $passenger->refresh();
                            $changed = true;
                        }

                        if ($passenger->ticket_fare_outbound_id !== $package->ticket_fare_outbound_id) {
                            $passenger->update(['ticket_fare_outbound_id' => $package->ticket_fare_outbound_id]);
                            $passenger->refresh();
                            $changed = true;
                        }
                    } else {
                        if ($passenger->ticket_fare_inbound_id !== null) {
                            $passenger->update(['ticket_fare_inbound_id' => null]);
                            $passenger->refresh();
                            $changed = true;
                        }

                        if ($passenger->ticket_fare_outbound_id !== null) {
                            $passenger->update(['ticket_fare_outbound_id' => null]);
                            $passenger->refresh();
                            $changed = true;
                        }

                        $packageFareId = $package->ticket_fare_id;
                        if ($passenger->ticket_fare_id !== $packageFareId) {
                            $passenger->update(['ticket_fare_id' => $packageFareId]);
                            $passenger->refresh();
                            $changed = true;
                        }
                    }

                    if ($changed) {
                        IssuedTicket::where('passenger_id', $passenger->id)
                            ->whereNull('issue_type')
                            ->where('status', 'pending')
                            ->update(['ticket_fare_id' => $passenger->ticket_fare_id]);

                        $oldValue = (float) ($passenger->package_value ?? 0);

                        // Historic recompute at the passenger's own anchor
                        // (earliest era start, floored at own creation) —
                        // never at live prices.
                        // Known, intentional scope limit (plan 17 §2d): value
                        // repair only runs when an FK actually changed, so pure
                        // value drift with matching FKs is left to
                        // bookings:sync-financials.
                        $bookingService->recalculatePassengerValues(
                            $passenger->booking,
                            $passenger->historicAnchor(),
                            $passenger
                        );

                        if (abs($oldValue - (float) $passenger->fresh()->package_value) >= 0.005) {
                            $updated++;
                        }
                    }
                }
            });

        $this->info("Synced {$updated} passenger record(s).");

        return self::SUCCESS;
    }
}
