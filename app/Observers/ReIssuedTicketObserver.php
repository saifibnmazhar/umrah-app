<?php

namespace App\Observers;

use App\Models\ReIssuedTicket;
use App\Services\ProfitCalculationService;

class ReIssuedTicketObserver
{
    protected array $profitFields = [
        'service_charge',
        'payment_by',
        're_issue_charge',
        'fare_difference',
        'other_costs',
        'net_fare',
    ];

    public function created(ReIssuedTicket $reIssuedTicket): void
    {
        $this->recalculateProfit($reIssuedTicket);
    }

    public function updated(ReIssuedTicket $reIssuedTicket): void
    {
        if ($reIssuedTicket->wasChanged($this->profitFields)) {
            $this->recalculateProfit($reIssuedTicket);
        }
    }

    public function deleted(ReIssuedTicket $reIssuedTicket): void
    {
        $this->recalculateProfit($reIssuedTicket);
    }

    public function restored(ReIssuedTicket $reIssuedTicket): void
    {
        $this->recalculateProfit($reIssuedTicket);
    }

    protected function recalculateProfit(ReIssuedTicket $reIssuedTicket): void
    {
        $passenger = $reIssuedTicket->issuedTicket?->passenger;

        if ($passenger?->booking) {
            app(ProfitCalculationService::class)->recalculateBookingProfit($passenger->booking);
        }
    }
}
