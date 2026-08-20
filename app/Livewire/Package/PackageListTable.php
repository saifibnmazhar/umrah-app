<?php

namespace App\Livewire\Package;

use App\Livewire\BaseListTable;
use App\Models\Package;
use Illuminate\Support\Collection;
use Livewire\Attributes\On;

class PackageListTable extends BaseListTable
{
    public string $statusFilter = '';

    public ?Collection $ticketFares = null;

    public ?Collection $inboundFares = null;

    public ?Collection $outboundFares = null;

    public array $usedFareIds = [];

    public ?float $latestVisaPrice = null;

    #[On('refresh')]
    public function refresh(): void
    {
        // Re-render is handled automatically by Livewire after method execution.
    }

    public function render()
    {
        return view('livewire.package.list-table', [
            'packages' => $this->packages,
        ]);
    }

    public function getPackagesProperty()
    {
        $query = Package::with([
            'ticketFare',
            'ticketFare.route.fromCity',
            'ticketFare.route.toCity',
            'ticketFare.route.returnCity',
            'ticketFare.route.multiSegments.fromCity',
            'ticketFare.route.multiSegments.toCity',
            'ticketFare.groupTicket',
            'ticketFareInbound',
            'ticketFareInbound.route.fromCity',
            'ticketFareInbound.route.toCity',
            'ticketFareInbound.groupTicket',
            'ticketFareOutbound',
            'ticketFareOutbound.route.fromCity',
            'ticketFareOutbound.route.toCity',
            'ticketFareOutbound.groupTicket',
            'visaSellingPrice',
        ])->withCount('bookings');

        if ($this->statusFilter === 'inactive') {
            $query->where('is_active', false);
        } elseif ($this->statusFilter === 'all') {
            // show all
        } else {
            $query->where('is_active', true);
        }

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('package_name', 'like', "%{$this->search}%")
                    ->orWhere('regular_price', 'like', "%{$this->search}%");
            });
        }

        return $query->orderBy('id')->paginate(10);
    }
}
