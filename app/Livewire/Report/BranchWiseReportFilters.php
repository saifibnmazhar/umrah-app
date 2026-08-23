<?php

namespace App\Livewire\Report;

use App\Models\Branch;
use App\Models\Payment;
use Carbon\Carbon;
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

        $payments = Payment::whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo)
            ->when($branchId === 'central', fn ($q) => $q->whereHas('vouchers.user', fn ($u) => $u->whereNull('branch_id')))
            ->when($branchId && $branchId !== 'central' && $branchId !== 'all', fn ($q) => $q->whereHas('vouchers.user', fn ($u) => $u->where('branch_id', $branchId)))
            ->whereHas('vouchers.transactionType', fn ($q) => $q->whereIn('name', ['Initial Payment', 'Due Collection']))
            ->with(['branch', 'vouchers.transactionType', 'vouchers.user.branch', 'vouchers.booking', 'vouchers.currencyRate', 'vouchers.bank'])
            ->get();

        $this->dailyPayments = $payments->groupBy(fn ($p) => $p->created_at->format('Y-m-d'))
            ->map(function ($dayPayments, $date) {
                return [
                    'date' => $date,
                    'cash' => $dayPayments->where('payment_method', 'cash')->sum('amount'),
                    'bank' => $dayPayments->where('payment_method', 'bank')->sum('amount'),
                    'bd_office' => $dayPayments->filter(fn ($p) => $p->branch?->location === 'BD')->sum('amount'),
                    'ksa_office' => $dayPayments->filter(fn ($p) => $p->branch?->location === 'KSA')->sum('amount'),
                ];
            })
            ->sortKeys();

        $this->vouchersByDate = [];
        foreach ($payments as $payment) {
            $dateKey = $payment->created_at->format('Y-m-d');
            foreach ($payment->vouchers as $v) {
                if (! in_array($v->transactionType?->name, ['Initial Payment', 'Due Collection'])) {
                    continue;
                }
                $this->vouchersByDate[$dateKey][] = [
                    'invoice_id' => $v->booking?->invoice_id ?? 'N/A',
                    'voucher_no' => $v->voucher_id ?? $v->id,
                    'method' => ucfirst($v->payment_method?->value ?? ''),
                    'transaction_type' => $v->transactionType?->name ?? '',
                    'trx_id' => $v->transaction_id ?? '-',
                    'receive_by' => $v->user?->name ?? '',
                    'receive_at' => $v->user?->branch?->name ?? 'Central',
                    'amount' => (float) $v->amount,
                    'bdt_amount' => (float) ($v->bdt_amount ?: 0),
                    'currency_rate' => (float) ($v->currencyRate?->rate ?? 1),
                    'payment_date' => $v->payment_date?->format('d-M-Y') ?? '',
                    'bank' => $v->bank?->name ?? '-',
                    'bank_id' => $v->bank_id,
                ];
            }
        }

        $this->totalCashPayment = $payments->where('payment_method', 'cash')->sum('amount');
        $this->totalBankPayment = $payments->where('payment_method', 'bank')->sum('amount');
        $this->totalBdOfficeCollection = $payments->filter(fn ($p) => $p->branch?->location === 'BD')->sum('amount');
        $this->totalKsaOfficeCollection = $payments->filter(fn ($p) => $p->branch?->location === 'KSA')->sum('amount');
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
