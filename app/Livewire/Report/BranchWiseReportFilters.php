<?php

namespace App\Livewire\Report;

use App\Models\Branch;
use App\Queries\BranchWiseReportQuery;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Livewire\Component;

class BranchWiseReportFilters extends Component
{
    public ?string $dateFrom = null;

    public ?string $dateTo = null;

    public ?string $branchId = null;

    public $branches = [];

    public $dailyPayments = [];

    public $vouchersByDate = [];

    public $totalCashPayment = 0;

    public $totalBankPayment = 0;

    public $totalBdOfficeCollection = 0;

    public $totalKsaOfficeCollection = 0;

    public $userBranchId = null;

    public $selectedBranch = null;

    public function updatedDateFrom(): void
    {
        $this->loadReport();
    }

    public function updatedDateTo(): void
    {
        $this->loadReport();
    }

    public function updatedBranchId(): void
    {
        $this->loadReport();
    }

    public function refreshReport(): void
    {
        $this->loadReport();
    }

    public function mount(): void
    {
        $this->loadReport();
    }

    private function loadReport(): void
    {
        $dateFrom = $this->dateFrom ? Carbon::parse($this->dateFrom) : now()->subDays(30);
        $dateTo = $this->dateTo ? Carbon::parse($this->dateTo) : now();
        $this->userBranchId = auth()->user()?->branch_id;
        $branchId = $this->branchId ?: $this->userBranchId;
        $this->selectedBranch = $branchId;

        $this->branches = Branch::orderBy('name')->get(['id', 'name']);

        $query = new BranchWiseReportQuery($dateFrom, $dateTo, $branchId);
        $vouchers = collect($query->paymentHistory($dateFrom, $dateTo, $branchId));

        // Build daily aggregates from the voucher list
        $this->dailyPayments = $vouchers
            ->groupBy('payment_date')
            ->map(function (Collection $dayVouchers, string $date) {
                return [
                    'date' => $date,
                    'cash' => $dayVouchers->where('method', 'Cash')->sum('amount'),
                    'bank' => $dayVouchers->where('method', 'Bank')->sum('amount'),
                    'bd_office' => $dayVouchers->where('receive_branch_location', 'BD')->sum('amount'),
                    'ksa_office' => $dayVouchers->where('receive_branch_location', 'KSA')->sum('amount'),
                ];
            })
            ->sortKeys();

        $this->vouchersByDate = $vouchers
            ->groupBy('payment_date')
            ->map(fn (Collection $dayVouchers) => $dayVouchers->toArray())
            ->toArray();

        $this->totalCashPayment = $vouchers->where('method', 'Cash')->sum('amount');
        $this->totalBankPayment = $vouchers->where('method', 'Bank')->sum('amount');
        $this->totalBdOfficeCollection = $vouchers->where('receive_branch_location', 'BD')->sum('amount');
        $this->totalKsaOfficeCollection = $vouchers->where('receive_branch_location', 'KSA')->sum('amount');
    }

    public function render()
    {
        return view('livewire.report.branch-wise-report-filters', [
            'dailyPayments' => $this->dailyPayments,
            'vouchersByDate' => $this->vouchersByDate,
            'totalCashPayment' => $this->totalCashPayment,
            'totalBankPayment' => $this->totalBankPayment,
            'totalBdOfficeCollection' => $this->totalBdOfficeCollection,
            'totalKsaOfficeCollection' => $this->totalKsaOfficeCollection,
            'branches' => $this->branches,
            'userBranchId' => $this->userBranchId,
            'dateFrom' => $this->dateFrom,
            'dateTo' => $this->dateTo,
        ]);
    }
}
