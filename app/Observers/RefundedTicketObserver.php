<?php

namespace App\Observers;

use App\Models\RefundedTicket;
use App\Services\ProfitCalculationService;

class RefundedTicketObserver
{
    public function created(RefundedTicket $refundedTicket): void
    {
        $this->recalculateProfit($refundedTicket);
    }

    public function updated(RefundedTicket $refundedTicket): void
    {
        if ($refundedTicket->wasChanged('service_charge')) {
            $this->recalculateProfit($refundedTicket);
        }
    }

    public function deleted(RefundedTicket $refundedTicket): void
    {
        $this->recalculateProfit($refundedTicket);
    }

    public function restored(RefundedTicket $refundedTicket): void
    {
        $this->recalculateProfit($refundedTicket);
    }

    protected function recalculateProfit(RefundedTicket $refundedTicket): void
    {
        $passenger = $refundedTicket->issuedTicket?->passenger;

        if ($passenger?->booking) {
            app(ProfitCalculationService::class)->recalculateBookingProfit($passenger->booking);
        }
    }
}
