<?php

namespace App\Livewire\Fare;

use App\Livewire\BaseListTable;
use App\Models\TicketFare;
use Illuminate\Support\Collection;

class TicketFareTable extends BaseListTable
{
    public string $airlineFilter = '';

    public string $ticketTypeFilter = '';

    public string $statusFilter = '';

    public Collection $airlines;

    public function render()
    {
        return view('livewire.fare.ticket-fare-table', [
            'ticketFares' => $this->ticketFares,
        ]);
    }

    public function getTicketFaresProperty()
    {
        $query = TicketFare::with([
            'airline',
            'airlineClass',
            'airlineClass.travelClass',
            'route',
            'route.fromCity',
            'route.toCity',
            'route.returnCity',
            'route.multiSegments',
            'route.multiSegments.fromCity',
            'route.multiSegments.toCity',
            'user',
            'groupTicket',
            'baggageAllowances',
        ])->withCount(['packages', 'passengers', 'issuedTickets']);

        if ($this->search) {
            $query->whereHas('airline', function ($q) {
                $q->where('name', 'like', "%{$this->search}%");
            });
        }

        if ($this->airlineFilter) {
            $query->where('airline_id', $this->airlineFilter);
        }

        if ($this->ticketTypeFilter) {
            $query->where('ticket_type', $this->ticketTypeFilter);
        }

        if ($this->statusFilter === 'inactive') {
            $query->where('is_active', false);
        } elseif ($this->statusFilter === 'all') {
            // no filter
        } else {
            $query->where('is_active', true);
        }

        return $query->orderBy('id', 'desc')->paginate(15);
    }
}
