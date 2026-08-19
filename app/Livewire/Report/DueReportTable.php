<?php

namespace App\Livewire\Report;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class DueReportTable extends Component
{
    public ?string $dateFrom = null;

    public ?string $dateTo = null;

    public $branches = [];

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

    public function loadData(): void
    {
        $query = Invoice::select(
            'branches.id',
            'branches.name',
            DB::raw('COALESCE(SUM(invoices.balance), 0) as total_due'),
            DB::raw('COUNT(*) as invoice_count')
        )
            ->join('branches', 'invoices.branch_id', '=', 'branches.id')
            ->where('invoices.balance', '>', 0)
            ->whereNotIn('invoices.status', [
                InvoiceStatus::PAID->value,
                InvoiceStatus::CANCELLED->value,
                InvoiceStatus::REFUNDED->value,
            ])
            ->groupBy('branches.id', 'branches.name')
            ->orderBy('branches.name');

        if ($this->dateFrom) {
            $query->whereDate('invoices.created_at', '>=', $this->dateFrom);
        }
        if ($this->dateTo) {
            $query->whereDate('invoices.created_at', '<=', $this->dateTo);
        }

        $this->branches = $query->get()->map(function ($item) {
            return [
                'id' => (int) $item->id,
                'name' => $item->name,
                'totalDue' => (float) $item->total_due,
            ];
        });
    }

    public function render()
    {
        return view('livewire.report.due-report-table', [
            'branches' => $this->branches,
            'dateFrom' => $this->dateFrom,
            'dateTo' => $this->dateTo,
        ]);
    }
}
