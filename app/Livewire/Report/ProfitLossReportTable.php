<?php

namespace App\Livewire\Report;

use App\Models\Booking;
use App\Services\CostTrackingService;
use Illuminate\Support\Collection;
use Livewire\Component;

class ProfitLossReportTable extends Component
{
    public ?string $dateFrom = null;

    public ?string $dateTo = null;

    public string $search = '';

    public string $activeTab = 'customer';

    public $customers = [];

    public $passengers = [];

    public $grandTotalCustomer = ['package_value' => 0, 'total_cost' => 0, 'profit' => 0];

    public $grandTotalPassenger = ['package_value' => 0, 'total_cost' => 0, 'profit' => 0];

    public function boot()
    {
        $this->setDefaultDates();
        $this->loadData();
    }

    protected function setDefaultDates(): void
    {
        if (! $this->dateFrom) {
            $this->dateFrom = now()->subDays(30)->toDateString();
        }
        if (! $this->dateTo) {
            $this->dateTo = now()->toDateString();
        }
    }

    public function updatedDateFrom(): void
    {
        $this->loadData();
    }

    public function updatedDateTo(): void
    {
        $this->loadData();
    }

    public function updatedSearch(): void
    {
        $this->computeTotals();
    }

    public function updatedActiveTab(): void
    {
        $this->computeTotals();
    }

    public function resetFilters(): void
    {
        $this->dateFrom = null;
        $this->dateTo = null;
        $this->search = '';
        $this->activeTab = 'customer';
        $this->setDefaultDates();
        $this->loadData();
    }

    protected function loadData(): void
    {
        $query = Booking::with([
            'customer',
            'invoice',
            'fingerprint',
            'passengers.visaSubmission',
            'passengers.allIssuedTickets',
        ])
            ->where('is_cancelled', false)
            ->whereHas('invoice');

        if ($this->dateFrom) {
            $query->whereDate('created_at', '>=', $this->dateFrom);
        }
        if ($this->dateTo) {
            $query->whereDate('created_at', '<=', $this->dateTo);
        }

        $bookings = $query->get();

        $costService = app(CostTrackingService::class);
        $costSummaries = $bookings->mapWithKeys(function (Booking $booking) use ($costService) {
            return [$booking->id => $costService->getBookingCostSummary($booking)];
        })->toArray();

        $this->customers = $bookings->map(function (Booking $booking) use ($costSummaries) {
            $costSummary = $costSummaries[$booking->id];
            $totalCost = $costSummary['total_cost'];
            $totalAmount = (float) $booking->invoice->total_amount;

            return [
                'invoice_id' => $booking->invoice_id,
                'customer_name' => $booking->customer?->name ?? '',
                'customer_passport' => $booking->customer?->passport_no ?? '',
                'customer_iqama' => $booking->customer?->iqama_no ?? '',
                'mobile' => $booking->customer?->mobile_no ?? '',
                'pax_qty' => $booking->pax_qty,
                'package_value' => $totalAmount,
                'total_cost' => $totalCost,
                'profit' => $totalAmount - $totalCost,
            ];
        })->values();

        $this->passengers = $bookings->flatMap(function (Booking $booking) use ($costSummaries) {
            $passengerCosts = collect($costSummaries[$booking->id]['passengers']);

            return $booking->passengers->map(function ($passenger) use ($booking, $passengerCosts) {
                $cost = $passengerCosts->firstWhere('passenger_id', $passenger->id);
                $totalCost = $cost['total_cost'] ?? 0;
                $packageValue = (float) $passenger->package_value;

                return [
                    'invoice_id' => $booking->invoice_id,
                    'customer_name' => $booking->customer?->name ?? '',
                    'customer_passport' => $booking->customer?->passport_no ?? '',
                    'customer_iqama' => $booking->customer?->iqama_no ?? '',
                    'mobile' => $passenger->mobile_no,
                    'passenger_name' => $passenger->first_name.' '.$passenger->last_name,
                    'passenger_passport' => $passenger->passport_no ?? '',
                    'package_value' => $packageValue,
                    'total_cost' => $totalCost,
                    'profit' => $packageValue - $totalCost,
                ];
            });
        })->values();

        $this->computeTotals();
    }

    protected function computeTotals(): void
    {
        $rows = $this->activeTab === 'customer' ? $this->filteredCustomers() : $this->filteredPassengers();

        $totals = $rows->reduce(function ($carry, $row) {
            $carry['package_value'] += $row['package_value'];
            $carry['total_cost'] += $row['total_cost'];
            $carry['profit'] += $row['profit'];

            return $carry;
        }, ['package_value' => 0, 'total_cost' => 0, 'profit' => 0]);

        if ($this->activeTab === 'customer') {
            $this->grandTotalCustomer = $totals;
        } else {
            $this->grandTotalPassenger = $totals;
        }
    }

    protected function filteredCustomers(): Collection
    {
        if (! $this->search) {
            return $this->customers;
        }

        $q = strtolower($this->search);

        return $this->customers->filter(function ($row) use ($q) {
            return str_contains(strtolower($row['invoice_id'] ?? ''), $q)
                || str_contains(strtolower($row['customer_name'] ?? ''), $q)
                || str_contains(strtolower($row['customer_passport'] ?? ''), $q)
                || str_contains(strtolower($row['customer_iqama'] ?? ''), $q);
        })->values();
    }

    protected function filteredPassengers(): Collection
    {
        if (! $this->search) {
            return $this->passengers;
        }

        $q = strtolower($this->search);

        return $this->passengers->filter(function ($row) use ($q) {
            return str_contains(strtolower($row['invoice_id'] ?? ''), $q)
                || str_contains(strtolower($row['customer_name'] ?? ''), $q)
                || str_contains(strtolower($row['passenger_name'] ?? ''), $q)
                || str_contains(strtolower($row['passenger_passport'] ?? ''), $q)
                || str_contains(strtolower($row['customer_passport'] ?? ''), $q)
                || str_contains(strtolower($row['customer_iqama'] ?? ''), $q);
        })->values();
    }

    public function getFilteredCustomersProperty(): Collection
    {
        return $this->filteredCustomers();
    }

    public function getFilteredPassengersProperty(): Collection
    {
        return $this->filteredPassengers();
    }

    public function render()
    {
        return view('livewire.report.profit-loss-report-table', [
            'customers' => $this->filteredCustomers(),
            'passengers' => $this->filteredPassengers(),
            'grandTotalCustomer' => $this->grandTotalCustomer,
            'grandTotalPassenger' => $this->grandTotalPassenger,
            'dateFrom' => $this->dateFrom,
            'dateTo' => $this->dateTo,
            'activeTab' => $this->activeTab,
        ]);
    }
}
