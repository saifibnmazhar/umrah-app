<?php

namespace App\Queries;

use App\Enums\FingerprintStatus;
use App\Enums\PaymentMethod;
use App\Enums\TicketStatus;
use App\Enums\VisaStatus;
use App\Models\Bank;
use App\Models\Booking;
use App\Models\Branch;
use App\Models\FingerprintDetailLog;
use App\Models\Invoice;
use App\Models\IssuedTicket;
use App\Models\IssuedTicketLog;
use App\Models\Passenger;
use App\Models\Payment;
use App\Models\VisaSubmission;
use App\Models\VisaUpdateLog;
use App\Models\Voucher;
use App\Services\CostTrackingService;
use App\Services\CurrencyRateService;
use App\Support\DateFormatter;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Encapsulates the BranchWise report query logic, extracted from
 * BranchWiseReportController. Handles date-range + branch-scoped aggregate
 * queries for the branch-wise report dashboard and payment history print view.
 */
class BranchWiseReportQuery
{
    protected Carbon $dateFrom;

    protected Carbon $dateTo;

    /** @var int|string|null */
    protected $branchId;

    protected int $firstRate;

    public function __construct(Carbon $dateFrom, Carbon $dateTo, $branchId = null)
    {
        $this->dateFrom = $dateFrom;
        $this->dateTo = $dateTo;
        $this->branchId = $branchId;
        $this->firstRate = (int) app(CurrencyRateService::class)->getFirstRateValue();
    }

    /**
     * Resolve the effective branch ID for the current user.
     * - If the user has a branch, use that.
     * - If the user has no branch and the request provides one, use the request value.
     * - 'central' string means no branch scoping (global admin view).
     */
    public static function resolveBranchId($branchId, ?int $userBranchId): mixed
    {
        if ($userBranchId !== null && $userBranchId > 0) {
            return $userBranchId;
        }

        return $branchId;
    }

    /**
     * Apply branch scoping to a query on the bookings table.
     * 'central' => branch_id IS NULL; otherwise where booking_branch_id = $branchId.
     */
    protected function applyBookingBranchScope($query)
    {
        if ($this->branchId === 'central') {
            return $query->whereNull('booking_branch_id');
        }

        return $query->when($this->branchId, fn ($q) => $q->where('booking_branch_id', $this->branchId));
    }

    /**
     * Apply branch scoping to a query via a relation (e.g. whereHas('user')).
     */
    protected function applyUserBranchScope($query, string $relation): void
    {
        if ($this->branchId === 'central') {
            $query->whereHas($relation, fn ($u) => $u->whereNull('branch_id'));
        } else {
            $query->when($this->branchId, fn ($q) => $q->whereHas($relation, fn ($u) => $u->where('branch_id', $this->branchId)));
        }
    }

    /**
     * Apply branch scoping to a query via a direct column.
     */
    protected function applyColumnBranchFilter($query, string $column): void
    {
        if ($this->branchId === 'central') {
            $query->whereNull($column);
        } else {
            $query->when($this->branchId, fn ($q) => $q->where($column, $this->branchId));
        }
    }

    /**
     * Compute the full summary for the branch-wise report index page.
     * Returns an array of all aggregate metrics used by the Blade view.
     */
    public function summary(): array
    {
        // --- Visa stats ---
        $branchScope = fn ($query) => $this->applyBookingBranchScope($query);

        $visaSubmitted = VisaUpdateLog::where('new_values->status', 'submitted')
            ->whereDate('created_at', '>=', $this->dateFrom)
            ->whereDate('created_at', '<=', $this->dateTo)
            ->when($this->branchId, fn ($q) => $q->whereHas('visaSubmission.passenger.booking', $branchScope))
            ->count();

        $visaIssued = VisaUpdateLog::where('new_values->status', 'issued')
            ->whereDate('created_at', '>=', $this->dateFrom)
            ->whereDate('created_at', '<=', $this->dateTo)
            ->when($this->branchId, fn ($q) => $q->whereHas('visaSubmission.passenger.booking', $branchScope))
            ->count();

        $visaPending = VisaSubmission::where('status', VisaStatus::PENDING)
            ->whereDate('created_at', '>=', $this->dateFrom)
            ->whereDate('created_at', '<=', $this->dateTo)
            ->when($this->branchId, fn ($q) => $q->whereHas('passenger.booking', $branchScope))
            ->count();

        // --- Fingerprint stats ---
        $fingerprintApproved = FingerprintDetailLog::where('new_values->status', FingerprintStatus::APPROVED->value)
            ->whereDate('created_at', '>=', $this->dateFrom)
            ->whereDate('created_at', '<=', $this->dateTo)
            ->when($this->branchId, fn ($q) => $q->whereHas('fingerprintDetail.passenger.booking', $branchScope))
            ->count();

        $fingerprintDone = FingerprintDetailLog::where('new_values->status', FingerprintStatus::DONE->value)
            ->whereDate('created_at', '>=', $this->dateFrom)
            ->whereDate('created_at', '<=', $this->dateTo)
            ->when($this->branchId, fn ($q) => $q->whereHas('fingerprintDetail.passenger.booking', $branchScope))
            ->count();

        $fingerprintProcessing = FingerprintDetailLog::where('new_values->status', FingerprintStatus::PROCESSING->value)
            ->whereDate('created_at', '>=', $this->dateFrom)
            ->whereDate('created_at', '<=', $this->dateTo)
            ->when($this->branchId, fn ($q) => $q->whereHas('fingerprintDetail.passenger.booking', $branchScope))
            ->count();

        // --- Invoice stats ---
        $invoiceCount = Invoice::whereDate('created_at', '>=', $this->dateFrom)
            ->whereDate('created_at', '<=', $this->dateTo)
            ->when($this->branchId, function ($q) {
                if ($this->branchId === 'central') {
                    $q->whereNull('branch_id');
                } else {
                    $q->where('branch_id', $this->branchId);
                }
            })
            ->count();

        $invoiceRow = Invoice::whereDate('invoices.created_at', '>=', $this->dateFrom)
            ->whereDate('invoices.created_at', '<=', $this->dateTo)
            ->when($this->branchId === 'central', fn ($q) => $q->whereNull('invoices.branch_id'))
            ->when($this->branchId !== 'central' && $this->branchId, fn ($q) => $q->where('invoices.branch_id', $this->branchId))
            ->leftJoin('bookings', 'invoices.booking_id', '=', 'bookings.id')
            ->leftJoin('currency_rates', 'bookings.currency_rate_id', '=', 'currency_rates.id')
            ->selectRaw(
                'SUM(invoices.total_amount) as sar_total,
                SUM(invoices.total_amount * COALESCE(currency_rates.rate, ?)) as bdt_total,
                SUM(invoices.balance) as due_sar,
                SUM(invoices.balance * COALESCE(currency_rates.rate, ?)) as due_bdt',
                [$this->firstRate, $this->firstRate]
            )
            ->first();

        // --- Ticket stats ---
        $inboundTicket = IssuedTicketLog::whereIn('new_data->status', [TicketStatus::ISSUED->value, TicketStatus::RE_ISSUED->value])
            ->whereDate('created_at', '>=', $this->dateFrom)
            ->whereDate('created_at', '<=', $this->dateTo)
            ->whereHas('issuedTicket', fn ($q) => $q->whereNotNull('inbound_date'))
            ->when($this->branchId, fn ($q) => $q->whereHas('issuedTicket.booking', $branchScope))
            ->count();

        $outboundTicket = IssuedTicketLog::whereIn('new_data->status', [TicketStatus::ISSUED->value, TicketStatus::RE_ISSUED->value])
            ->whereDate('created_at', '>=', $this->dateFrom)
            ->whereDate('created_at', '<=', $this->dateTo)
            ->whereHas('issuedTicket', fn ($q) => $q->whereNotNull('outbound_date'))
            ->when($this->branchId, fn ($q) => $q->whereHas('issuedTicket.booking', $branchScope))
            ->count();

        $pendingTicket = IssuedTicket::whereDate('created_at', '>=', $this->dateFrom)
            ->whereDate('created_at', '<=', $this->dateTo)
            ->when($this->branchId, fn ($q) => $q->whereHas('booking', $branchScope))
            ->where('status', TicketStatus::PENDING)
            ->count();

        // --- Payment aggregates (single query for both types) ---
        $allPaymentTypes = ['Initial Payment', 'Due Collection'];
        $initialPaymentAgg = $this->paymentAggregates(['Initial Payment']);
        $dueCollectionAgg = $this->paymentAggregates(['Due Collection']);
        $allPaymentsAgg = $this->paymentAggregates($allPaymentTypes);

        // --- Refund aggregates ---
        $refundAgg = $this->voucherAggregates(['Customer Refund']);
        $ticketRefundAgg = $this->voucherAggregates(['Ticket Refund - Payment', 'Ticket Refund - Re-issue']);

        // --- Profit (iterates bookings — kept from original controller) ---
        $totalProfit = 0.0;
        $totalProfitBdt = 0.0;
        $profitBookings = Booking::with(['invoice', 'fingerprint', 'currencyRate', 'passengers.visaSubmission', 'passengers.allIssuedTickets'])
            ->where('is_cancelled', false)
            ->whereHas('invoice')
            ->whereDate('created_at', '>=', $this->dateFrom)
            ->whereDate('created_at', '<=', $this->dateTo)
            ->when($this->branchId === 'central', fn ($q) => $q->whereNull('booking_branch_id'))
            ->when($this->branchId !== 'central' && $this->branchId, fn ($q) => $q->where('booking_branch_id', $this->branchId))
            ->get();

        $costService = app(CostTrackingService::class);
        foreach ($profitBookings as $booking) {
            $costSummary = $costService->getBookingCostSummary($booking);
            $profit = (float) $booking->invoice->total_amount - $costSummary['total_cost'];
            $totalProfit += $profit;
            $rate = (float) ($booking->currencyRate?->rate ?? $this->firstRate);
            $totalProfitBdt += $profit * $rate;
        }

        $totalPassengers = Passenger::whereDate('created_at', '>=', $this->dateFrom)
            ->whereDate('created_at', '<=', $this->dateTo)
            ->when($this->branchId, fn ($q) => $q->whereHas('booking', $branchScope))
            ->count();

        return [
            // Visa
            'visaSubmitted' => $visaSubmitted,
            'visaIssued' => $visaIssued,
            'visaPending' => $visaPending,
            // Fingerprint
            'fingerprintApproved' => $fingerprintApproved,
            'fingerprintDone' => $fingerprintDone,
            'fingerprintProcessing' => $fingerprintProcessing,
            // Invoices
            'invoiceCount' => $invoiceCount,
            'invoiceTotalAmount' => $invoiceRow->sar_total ?? 0,
            'invoiceTotalAmountBdt' => $invoiceRow->bdt_total ?? 0,
            'totalDue' => $invoiceRow->due_sar ?? 0,
            'totalDueBdt' => $invoiceRow->due_bdt ?? 0,
            // Tickets
            'inboundTicket' => $inboundTicket,
            'outboundTicket' => $outboundTicket,
            'pendingTicket' => $pendingTicket,
            // Payments (Initial Payment + Due Collection)
            'totalReceiving' => $allPaymentsAgg['sar'],
            'totalReceivingBdt' => $allPaymentsAgg['bdt'],
            'receivingCash' => $allPaymentsAgg['cash'],
            'receivingCashBdt' => $allPaymentsAgg['cashBdt'],
            'receivingBank' => $allPaymentsAgg['bank'],
            'receivingBankBdt' => $allPaymentsAgg['bankBdt'],
            // Due collection split
            'totalInitialPayment' => $initialPaymentAgg['sar'],
            'totalInitialPaymentBdt' => $initialPaymentAgg['bdt'],
            'initialPaymentCash' => $initialPaymentAgg['cash'],
            'initialPaymentCashBdt' => $initialPaymentAgg['cashBdt'],
            'initialPaymentBank' => $initialPaymentAgg['bank'],
            'initialPaymentBankBdt' => $initialPaymentAgg['bankBdt'],
            'totalDueCollection' => $dueCollectionAgg['sar'],
            'totalDueCollectionBdt' => $dueCollectionAgg['bdt'],
            'dueCollectionCash' => $dueCollectionAgg['cash'],
            'dueCollectionCashBdt' => $dueCollectionAgg['cashBdt'],
            'dueCollectionBank' => $dueCollectionAgg['bank'],
            'dueCollectionBankBdt' => $dueCollectionAgg['bankBdt'],
            // Payments by method
            'totalCashPayment' => $allPaymentsAgg['cash'],
            'totalCashPaymentBdt' => $allPaymentsAgg['cashBdt'],
            'totalBankPayment' => $allPaymentsAgg['bank'],
            'totalBankPaymentBdt' => $allPaymentsAgg['bankBdt'],
            // Profit
            'totalProfit' => $totalProfit,
            'totalProfitBdt' => $totalProfitBdt,
            // Refunds
            'totalRefund' => $refundAgg['sar'],
            'totalRefundBdt' => $refundAgg['bdt'],
            'totalTicketRefund' => $ticketRefundAgg['sar'],
            'totalTicketRefundBdt' => $ticketRefundAgg['bdt'],
            // Passengers
            'totalPassengers' => $totalPassengers,
        ];
    }

    /**
     * Compute payment aggregates (sum of payments) for the given transaction
     * type names, broken down by cash/bank. Returns SAR and BDT amounts.
     */
    protected function paymentAggregates(array $transactionTypeNames): array
    {
        $query = Payment::whereDate('payments.created_at', '>=', $this->dateFrom)
            ->whereDate('payments.created_at', '<=', $this->dateTo)
            ->whereHas('vouchers.transactionType', fn ($q) => $q->whereIn('name', $transactionTypeNames))
            ->leftJoin('bookings', 'payments.booking_id', '=', 'bookings.id')
            ->leftJoin('currency_rates', 'bookings.currency_rate_id', '=', 'currency_rates.id');

        $this->applyUserBranchScope($query, 'vouchers.user');

        $row = $query->selectRaw(
            'SUM(payments.amount) as sar_total,
            SUM(payments.amount * COALESCE(currency_rates.rate, ?)) as bdt_total,
            SUM(CASE WHEN payments.payment_method = ? THEN payments.amount ELSE 0 END) as cash_sar,
            SUM(CASE WHEN payments.payment_method = ? THEN payments.amount * COALESCE(currency_rates.rate, ?) ELSE 0 END) as cash_bdt,
            SUM(CASE WHEN payments.payment_method = ? THEN payments.amount ELSE 0 END) as bank_sar,
            SUM(CASE WHEN payments.payment_method = ? THEN payments.amount * COALESCE(currency_rates.rate, ?) ELSE 0 END) as bank_bdt',
            [
                $this->firstRate,
                PaymentMethod::CASH->value, PaymentMethod::CASH->value, $this->firstRate,
                PaymentMethod::BANK->value, PaymentMethod::BANK->value, $this->firstRate,
            ]
        )
            ->first();

        return [
            'sar' => (float) ($row->sar_total ?? 0),
            'bdt' => (float) ($row->bdt_total ?? 0),
            'cash' => (float) ($row->cash_sar ?? 0),
            'cashBdt' => (float) ($row->cash_bdt ?? 0),
            'bank' => (float) ($row->bank_sar ?? 0),
            'bankBdt' => (float) ($row->bank_bdt ?? 0),
        ];
    }

    /**
     * Compute voucher aggregates for the given transaction type names.
     */
    protected function voucherAggregates(array $transactionTypeNames): array
    {
        $query = Voucher::whereDate('vouchers.created_at', '>=', $this->dateFrom)
            ->whereDate('vouchers.created_at', '<=', $this->dateTo)
            ->whereHas('transactionType', fn ($q) => $q->whereIn('name', $transactionTypeNames))
            ->leftJoin('bookings', 'vouchers.booking_id', '=', 'bookings.id')
            ->leftJoin('currency_rates', 'bookings.currency_rate_id', '=', 'currency_rates.id');

        $this->applyUserBranchScope($query, 'user');

        $row = $query->selectRaw(
            'SUM(vouchers.amount) as sar_total,
            SUM(vouchers.amount * COALESCE(currency_rates.rate, ?)) as bdt_total',
            [$this->firstRate]
        )
            ->first();

        return [
            'sar' => (float) ($row->sar_total ?? 0),
            'bdt' => (float) ($row->bdt_total ?? 0),
        ];
    }

    /**
     * Build the payment history vouchers for the print view.
     */
    public function paymentHistory(Carbon $dateFrom, Carbon $dateTo, $branchId, ?string $method = null, ?string $currency = 'SAR'): array
    {
        $query = Payment::whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo)
            ->whereHas('vouchers.transactionType', fn ($q) => $q->whereIn('name', ['Initial Payment', 'Due Collection']))
            ->with(['vouchers.transactionType', 'vouchers.user.branch', 'vouchers.booking', 'vouchers.currencyRate', 'vouchers.bank']);

        if ($branchId === 'central') {
            $query->whereHas('vouchers.user', fn ($u) => $u->whereNull('branch_id'));
        } elseif ($branchId) {
            $query->whereHas('vouchers.user', fn ($u) => $u->where('branch_id', $branchId));
        }

        $payments = $query->get();

        $vouchers = new Collection;
        foreach ($payments as $payment) {
            foreach ($payment->vouchers as $v) {
                if (! in_array($v->transactionType?->name, ['Initial Payment', 'Due Collection'])) {
                    continue;
                }
                $vouchers->push([
                    'invoice_id' => $v->booking?->invoice_id ?? 'N/A',
                    'voucher_no' => $v->voucher_id ?? $v->id,
                    'method' => ucfirst($v->payment_method?->value ?? ''),
                    'transaction_type' => $v->transactionType?->name ?? '',
                    'trx_id' => $v->transaction_id ?? '-',
                    'receive_by' => $v->user?->name ?? '',
                    'receive_at' => $v->user?->branch?->name ?? 'Central',
                    'amount' => (float) $v->amount,
                    'bdt_amount' => (float) ($v->bdt_amount ?: 0),
                    'currency_rate' => (float) ($v->currencyRate?->rate ?? $this->firstRate),
                    'payment_date' => DateFormatter::short($v->payment_date),
                    'bank' => $v->bank?->name ?? '-',
                    'bank_id' => $v->bank_id,
                ]);
            }
        }

        if ($method === 'all' || $method === 'bank') {
            $vouchers = $vouchers->where('method', 'Bank');
        } elseif ($method === 'cash') {
            $vouchers = $vouchers->where('method', 'Cash');
        }

        return $vouchers->values()->toArray();
    }

    /**
     * Get the list of branches for the report filter dropdown.
     */
    public function branches(): Collection
    {
        return Branch::orderBy('name')->get(['id', 'name']);
    }

    /**
     * Get the list of banks for the report filter dropdown.
     */
    public function banks(): Collection
    {
        return Bank::orderBy('name')->get(['id', 'name']);
    }
}
