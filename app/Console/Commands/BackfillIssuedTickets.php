<?php

namespace App\Console\Commands;

use App\Models\IssuedTicket;
use App\Models\Passenger;
use Illuminate\Console\Command;

class BackfillIssuedTickets extends Command
{
    protected $signature = 'tickets:backfill-issued';

    protected $description = 'Create issued_tickets records for all existing passengers';

    public function handle(): void
    {
        IssuedTicket::whereHas('passenger', fn ($q) => $q->where('service_required', 'visa_only'))->delete();

        $count = 0;

        Passenger::with('booking')
            ->where('service_required', '!=', 'visa_only')
            ->chunk(100, function ($passengers) use (&$count) {
                foreach ($passengers as $passenger) {
                    if (IssuedTicket::where('passenger_id', $passenger->id)->exists()) {
                        continue;
                    }

                    $currentStatus = $passenger->ticket_status?->value;
                    $mapStatus = match ($currentStatus) {
                        'issued' => 'issued',
                        're-issued' => 'issued',
                        'refunded' => 'refunded',
                        default => 'pending',
                    };

                    IssuedTicket::create([
                        'passenger_id' => $passenger->id,
                        'booking_id' => $passenger->booking_id,
                        'user_id' => $passenger->booking?->user_id ?? 1,
                        'ticket_fare_id' => $passenger->ticket_fare_id,
                        'issue_type' => null,
                        'status' => $mapStatus,
                        'is_refundable' => false,
                        'is_exchangeable' => false,
                        'outbound_pending' => false,
                    ]);

                    if ($currentStatus === null) {
                        $passenger->update(['ticket_status' => 'pending']);
                    }

                    $count++;
                }
            });

        $this->info("Created {$count} issued_ticket records.");
    }
}
