<?php

namespace App\Observers;

use App\Models\IssuedTicket;

class IssuedTicketObserver
{
    public function updated(IssuedTicket $issuedTicket): void
    {
        if ($issuedTicket->wasChanged('ticket_fare_id') && $issuedTicket->status === 'issued') {
            $issuedTicket->passenger()->update(['ticket_fare_id' => $issuedTicket->ticket_fare_id]);
        }

        if ($issuedTicket->wasChanged('inbound_date') && $issuedTicket->status === 'issued') {
            $issuedTicket->passenger()->update(['actual_flight_date' => $issuedTicket->inbound_date]);
        }
    }
}
