<?php

namespace App\Observers;

use App\Models\IssuedTicket;

class IssuedTicketObserver
{
    public function created(IssuedTicket $issuedTicket): void
    {
        $issuedTicket->passenger->syncComputedStatus();
    }

    public function updated(IssuedTicket $issuedTicket): void
    {
        if ($issuedTicket->wasChanged('ticket_fare_id') && $issuedTicket->status === 'issued') {
            $issuedTicket->passenger()->update(['ticket_fare_id' => $issuedTicket->ticket_fare_id]);
        }

        $issuedTicket->passenger->syncComputedStatus();
    }
}
