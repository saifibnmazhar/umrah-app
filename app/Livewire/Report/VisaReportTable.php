<?php

namespace App\Livewire\Report;

use App\Models\VisaSubmission;
use App\Services\CurrencyRateService;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class VisaReportTable extends Component
{
    public const PER_PAGE = 25;

    public ?string $search = null;

    public ?string $visaSubmitDateFrom = null;

    public ?string $visaSubmitDateTo = null;

    public ?string $flightDateFrom = null;

    public ?string $flightDateTo = null;

    public string $status = 'all';

    public $data = [];

    public $summary = [
        'total_records' => 0,
        'total_invoices' => 0,
        'total_agent_cost' => 0,
        'pending' => 0,
        'submitted' => 0,
        'issued' => 0,
        'cancelled' => 0,
    ];

    public $currentPage = 1;

    public function boot()
    {
        $this->loadData();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
        $this->loadData();
    }

    public function updatedVisaSubmitDateFrom(): void
    {
        $this->resetPage();
        $this->loadData();
    }

    public function updatedVisaSubmitDateTo(): void
    {
        $this->resetPage();
        $this->loadData();
    }

    public function updatedFlightDateFrom(): void
    {
        $this->resetPage();
        $this->loadData();
    }

    public function updatedFlightDateTo(): void
    {
        $this->resetPage();
        $this->loadData();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
        $this->loadData();
    }

    public function changePage($page): void
    {
        $this->currentPage = max(1, (int) $page);
        $this->loadData();
    }

    public function resetPage(): void
    {
        $this->currentPage = 1;
    }

    public function resetFilters(): void
    {
        $this->search = null;
        $this->visaSubmitDateFrom = null;
        $this->visaSubmitDateTo = null;
        $this->flightDateFrom = null;
        $this->flightDateTo = null;
        $this->status = 'all';
        $this->resetPage();
        $this->loadData();
    }

    protected function loadData(): void
    {
        $query = VisaSubmission::query()
            ->with([
                'passenger.booking.customer',
                'passenger.booking.currencyRate',
                'passenger.booking',
                'visaAgent',
            ]);

        if ($this->search) {
            $query->where(function ($q) {
                $q->whereHas('passenger', function ($pq) {
                    $pq->where('first_name', 'like', "%{$this->search}%")
                        ->orWhere('last_name', 'like', "%{$this->search}%")
                        ->orWhere('mobile_no', 'like', "%{$this->search}%")
                        ->orWhere('passport_no', 'like', "%{$this->search}%");
                })->orWhereHas('passenger.booking', function ($bq) {
                    $bq->where('invoice_id', 'like', "%{$this->search}%");
                })->orWhereHas('passenger.booking.customer', function ($cq) {
                    $cq->where('name', 'like', "%{$this->search}%");
                })->orWhere('visa_number', 'like', "%{$this->search}%");
            });
        }

        if ($this->visaSubmitDateFrom) {
            $query->whereDate('created_at', '>=', $this->visaSubmitDateFrom);
        }

        if ($this->visaSubmitDateTo) {
            $query->whereDate('created_at', '<=', $this->visaSubmitDateTo);
        }

        if ($this->flightDateFrom) {
            $query->whereHas('passenger', fn ($q) => $q->whereDate('flight_date_from', '>=', $this->flightDateFrom));
        }

        if ($this->flightDateTo) {
            $query->whereHas('passenger', fn ($q) => $q->whereDate('flight_date_to', '<=', $this->flightDateTo));
        }

        if ($this->status && $this->status !== 'all') {
            $query->where('status', $this->status);
        }

        $query->orderBy('created_at', 'desc');

        $submissions = $query->paginate(self::PER_PAGE, ['*'], 'page', $this->currentPage);

        $this->data = $this->mapReportData($submissions->items());
        $this->summary = $this->computeSummaryFromQuery();
    }

    protected function mapReportData(array $submissions): array
    {
        $currencyRateService = app(CurrencyRateService::class);
        $result = [];

        foreach ($submissions as $submission) {
            $passenger = $submission->passenger;
            $booking = $passenger?->booking;
            $customer = $booking?->customer;

            $rate = $booking?->currencyRate?->rate
                ?? $currencyRateService->getRateForDate($booking?->created_at)?->rate
                ?? 0;

            $iqama = $customer?->iqama_no;
            $passport = $passenger?->passport_no;

            $result[] = [
                'id' => $submission->id,
                'invoice_no' => $booking?->invoice_id ?? '-',
                'customer_name' => $customer?->name ?? '-',
                'customer_iqama' => $iqama ? "IQAMA: {$iqama}" : 'N/A',
                'pax_name' => $passenger ? trim(($passenger->first_name ?? '').' '.($passenger->last_name ?? '')) : '-',
                'pax_passport' => $passport ? "Passport: {$passport}" : 'N/A',
                'mobile' => $passenger?->mobile_no ?? '-',
                'customer_mobile' => $customer?->mobile_no ?? '-',
                'visa_submit_date' => $submission->created_at?->format('d-M-Y'),
                'visa_status' => $submission->status?->value ?? 'pending',
                'flight_date' => $passenger?->flight_date_display ?? '-',
                'visa_number' => $submission->visa_number ?? '-',
                'visa_agent' => $submission->visaAgent?->name ?? '-',
                'agent_cost' => (float) ($submission->final_cost ?? 0),
                'rate' => $rate,
            ];
        }

        return $result;
    }

    protected function computeSummaryFromQuery(): array
    {
        $base = VisaSubmission::query();

        if ($this->search) {
            $base->where(function ($q) {
                $q->whereHas('passenger', function ($pq) {
                    $pq->where('first_name', 'like', "%{$this->search}%")
                        ->orWhere('last_name', 'like', "%{$this->search}%")
                        ->orWhere('mobile_no', 'like', "%{$this->search}%")
                        ->orWhere('passport_no', 'like', "%{$this->search}%");
                })->orWhereHas('passenger.booking', function ($bq) {
                    $bq->where('invoice_id', 'like', "%{$this->search}%");
                })->orWhereHas('passenger.booking.customer', function ($cq) {
                    $cq->where('name', 'like', "%{$this->search}%");
                })->orWhere('visa_number', 'like', "%{$this->search}%");
            });
        }

        if ($this->visaSubmitDateFrom) {
            $base->whereDate('created_at', '>=', $this->visaSubmitDateFrom);
        }

        if ($this->visaSubmitDateTo) {
            $base->whereDate('created_at', '<=', $this->visaSubmitDateTo);
        }

        if ($this->flightDateFrom) {
            $base->whereHas('passenger', fn ($q) => $q->whereDate('flight_date_from', '>=', $this->flightDateFrom));
        }

        if ($this->flightDateTo) {
            $base->whereHas('passenger', fn ($q) => $q->whereDate('flight_date_to', '<=', $this->flightDateTo));
        }

        if ($this->status && $this->status !== 'all') {
            $base->where('status', $this->status);
        }

        $aggregates = (clone $base)
            ->select(
                DB::raw('SUM(COALESCE(final_cost, 0)) as total_agent_cost'),
                DB::raw('COUNT(*) as total_records'),
                DB::raw("SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending"),
                DB::raw("SUM(CASE WHEN status = 'submitted' THEN 1 ELSE 0 END) as submitted"),
                DB::raw("SUM(CASE WHEN status = 'issued' THEN 1 ELSE 0 END) as issued"),
                DB::raw("SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled")
            )
            ->first();

        $uniqueInvoices = (clone $base)
            ->join('passengers', 'visa_submissions.passenger_id', '=', 'passengers.id')
            ->join('bookings', 'passengers.booking_id', '=', 'bookings.id')
            ->whereNotNull('bookings.invoice_id')
            ->distinct('bookings.invoice_id')
            ->count('bookings.invoice_id');

        return [
            'total_records' => (int) ($aggregates->total_records ?? 0),
            'total_invoices' => $uniqueInvoices,
            'total_agent_cost' => (float) ($aggregates->total_agent_cost ?? 0),
            'pending' => (int) ($aggregates->pending ?? 0),
            'submitted' => (int) ($aggregates->submitted ?? 0),
            'issued' => (int) ($aggregates->issued ?? 0),
            'cancelled' => (int) ($aggregates->cancelled ?? 0),
        ];
    }

    public function getFilteredDataProperty(): array
    {
        return $this->data;
    }

    public function getFilteredSummaryProperty(): array
    {
        return $this->summary;
    }

    public function getPaginationProperty()
    {
        $total = $this->summary['total_records'] ?? 0;
        $perPage = self::PER_PAGE;
        $lastPage = max(1, ceil($total / $perPage));

        return [
            'current_page' => $this->currentPage,
            'last_page' => $lastPage,
            'per_page' => $perPage,
            'total' => $total,
        ];
    }

    public function getPaginationPagesProperty(): array
    {
        $pages = [];
        $start = max(1, $this->currentPage - 2);
        $end = min($this->pagination['last_page'], $this->currentPage + 2);

        for ($i = $start; $i <= $end; $i++) {
            $pages[] = $i;
        }

        return $pages;
    }

    public function render()
    {
        return view('livewire.report.visa-report-table', [
            'data' => $this->data,
            'summary' => $this->summary,
            'currentPage' => $this->currentPage,
            'lastPage' => $this->pagination['last_page'],
            'totalRecords' => $this->pagination['total'],
            'perPage' => self::PER_PAGE,
            'paginationPages' => $this->paginationPages,
        ]);
    }
}
