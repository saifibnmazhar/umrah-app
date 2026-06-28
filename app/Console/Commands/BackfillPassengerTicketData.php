<?php

namespace App\Console\Commands;

use App\Enums\ServiceRequired;
use App\Enums\TicketStatus;
use App\Models\Passenger;
use Illuminate\Console\Command;

class BackfillPassengerTicketData extends Command
{
    protected $signature = 'tickets:backfill-passenger-data';

    protected $description = 'Backfill actual_flight_date and ticket_fare_id on passengers from their latest issued/re-issued ticket';

    public function handle(): void
    {
        $count = 0;

        Passenger::whereIn('service_required', [ServiceRequired::ALL, ServiceRequired::TICKET_ONLY])
            ->whereHas('issuedTickets', function ($q) {
                $q->whereIn('status', [TicketStatus::ISSUED, TicketStatus::RE_ISSUED]);
            })
            ->chunk(100, function ($passengers) use (&$count) {
                foreach ($passengers as $passenger) {
                    $ticket = $passenger->issuedTickets()
                        ->whereIn('status', [TicketStatus::ISSUED, TicketStatus::RE_ISSUED])
                        ->latest('id')
                        ->first();

                    if (! $ticket) {
                        continue;
                    }

                    $passenger->update([
                        'actual_flight_date' => $ticket->inbound_date,
                        'ticket_fare_id' => $ticket->ticket_fare_id,
                    ]);

                    $count++;
                }
            });

        $this->info("Backfilled {$count} passenger record(s).");
    }
}
