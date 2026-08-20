<?php

namespace App\Livewire\TicketFare;

use App\Livewire\BaseListTable;
use App\Models\Airline;
use App\Models\TicketFare;
use App\Models\TravelClass;

class TicketFareIndexTable extends BaseListTable
{
    public string $airlineFilter = '';

    public string $ticketTypeFilter = '';

    public string $statusFilter = '';

    public function render()
    {
        return view('livewire.ticket-fare.index-table', [
            'ticketFares' => $this->ticketFares,
            'airlines' => $this->airlines,
            'travelClasses' => $this->travelClasses,
        ]);
    }

    public function getTicketFaresProperty()
    {
        $query = TicketFare::with(['airline', 'airlineClass', 'route', 'user', 'groupTicket', 'baggageAllowances'])
            ->withCount(['packages', 'passengers']);

        if ($this->airlineFilter) {
            $query->where('airline_id', $this->airlineFilter);
        }

        if ($this->ticketTypeFilter) {
            $query->where('ticket_type', $this->ticketTypeFilter);
        }

        if ($this->search) {
            $query->whereHas('airline', function ($q) {
                $q->where('name', 'like', "%{$this->search}%");
            });
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

    public function getAirlinesProperty()
    {
        return Airline::orderBy('name')->get();
    }

    public function getTravelClassesProperty()
    {
        return TravelClass::orderBy('name')->get();
    }
}
