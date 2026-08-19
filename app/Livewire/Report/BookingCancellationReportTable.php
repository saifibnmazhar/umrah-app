<?php

namespace App\Livewire\Report;

use App\Http\Controllers\BookingCancellationActionController;
use App\Models\Branch;
use Illuminate\Http\Request;
use Livewire\Component;

class BookingCancellationReportTable extends Component
{
    public ?string $search = null;

    public ?string $dateFrom = null;

    public ?string $dateTo = null;

    public ?string $branchId = null;

    public ?string $status = null;

    public int $perPage = 15;

    public $data = [];

    public $summary = [
        'total_paid' => 0,
        'total_deduction' => 0,
        'total_refund' => 0,
    ];

    public $pagination = [
        'current_page' => 1,
        'last_page' => 1,
        'per_page' => 15,
        'total' => 0,
    ];

    public $branches = [];

    public bool $loading = false;

    public function boot()
    {
        $this->branches = Branch::orderBy('name')->get(['id', 'name'])->keyBy('id')->toArray();
        $this->loadData();
    }

    protected function loadData(): void
    {
        $this->loading = true;

        $params = array_filter([
            'search' => $this->search,
            'date_from' => $this->dateFrom,
            'date_to' => $this->dateTo,
            'branch_id' => $this->branchId,
            'status' => $this->status,
            'per_page' => $this->perPage,
        ], fn ($v) => $v !== null && $v !== '');

        $request = Request::create('/api/reports/booking-cancellation', 'GET', $params);
        $request->query->set('page', $this->pagination['current_page']);

        $response = app(BookingCancellationActionController::class)->reportData($request);
        $payload = $response->getData(true);

        $this->data = $payload['data'] ?? [];
        $this->summary = $payload['summary'] ?? $this->summary;
        $this->pagination = [
            'current_page' => $payload['pagination']['current_page'] ?? 1,
            'last_page' => $payload['pagination']['last_page'] ?? 1,
            'per_page' => $payload['pagination']['per_page'] ?? 15,
            'total' => $payload['pagination']['total'] ?? 0,
        ];

        $this->loading = false;
    }

    public function updatedSearch(): void
    {
        $this->loadData();
    }

    public function updatedDateFrom(): void
    {
        $this->loadData();
    }

    public function updatedDateTo(): void
    {
        $this->loadData();
    }

    public function updatedBranchId(): void
    {
        $this->loadData();
    }

    public function updatedStatus(): void
    {
        $this->loadData();
    }

    public function resetFilters(): void
    {
        $this->search = null;
        $this->dateFrom = null;
        $this->dateTo = null;
        $this->branchId = null;
        $this->status = null;
        $this->loadData();
    }

    public function goToPage(int $page): void
    {
        $this->pagination['current_page'] = $page;
        $this->loadData();
    }

    public function render()
    {
        return view('livewire.report.booking-cancellation-report-table');
    }
}
