<?php

namespace App\Observers;

use App\Models\IssuedTicket;
use App\Services\ProfitCalculationService;

class IssuedTicketObserver
{
    public function created(IssuedTicket $issuedTicket): void
    {
        $issuedTicket->passenger->syncComputedStatus();

        $this->recalculateProfit($issuedTicket);
    }

    public function updated(IssuedTicket $issuedTicket): void
    {
        if ($issuedTicket->wasChanged('inbound_date') && $issuedTicket->status === 'issued' && $issuedTicket->issue_type === 'regular') {
            $issuedTicket->passenger()->update(['actual_flight_date' => $issuedTicket->inbound_date]);
        }

        $issuedTicket->passenger->syncComputedStatus();

        if ($issuedTicket->wasChanged(['status', 'net_fare', 'issue_type'])) {
            $this->recalculateProfit($issuedTicket);
        }
    }

    public function deleted(IssuedTicket $issuedTicket): void
    {
        $this->recalculateProfit($issuedTicket);
    }

    public function restored(IssuedTicket $issuedTicket): void
    {
        $this->recalculateProfit($issuedTicket);
    }

    protected function recalculateProfit(IssuedTicket $issuedTicket): void
    {
        $passenger = $issuedTicket->passenger;

        if ($passenger?->booking) {
            app(ProfitCalculationService::class)->recalculateBookingProfit($passenger->booking);
        }
    }
}
