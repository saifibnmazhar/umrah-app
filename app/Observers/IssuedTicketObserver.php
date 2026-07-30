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
        if ($issuedTicket->wasChanged('inbound_date') && $issuedTicket->status === 'issued' && $issuedTicket->issue_type === 'regular') {
            $issuedTicket->passenger()->update(['actual_flight_date' => $issuedTicket->inbound_date]);
        }

        $issuedTicket->passenger->syncComputedStatus();
    }
}
