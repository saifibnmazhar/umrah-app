<?php

namespace App\Http\Controllers;

use App\Enums\FingerprintStatus;
use App\Enums\PaymentMethod;
use App\Enums\TicketStatus;
use App\Enums\VisaStatus;
use App\Models\Branch;
use App\Models\FingerprintDetail;
use App\Models\FingerprintDetailLog;
use App\Models\Invoice;
use App\Models\IssuedTicket;
use App\Models\IssuedTicketLog;
use App\Models\Passenger;
use App\Models\Payment;
use App\Models\VisaSubmission;
use App\Models\VisaUpdateLog;
use App\Models\Voucher;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BranchWiseReportController extends Controller
{
    public function index(Request $request): View
    {
        $dateFrom = $request->date_from ? Carbon::parse($request->date_from) : now()->subDays(30);
        $dateTo = $request->date_to ? Carbon::parse($request->date_to) : now();
        $userBranchId = auth()->user()->branch_id;
        $branchId = $userBranchId ?: $request->branch_id;
        $selectedBranch = $branchId;
        $branches = Branch::orderBy('name')->get(['id', 'name']);

        $branchScope = fn($query) => $query
            ->where('booking_branch_id', $branchId);

        $visaSubmitted = VisaUpdateLog::where('new_values->status', 'submitted')
            ->whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo)
            ->when($branchId, fn($q) => $q->whereHas('visaSubmission.passenger.booking', $branchScope))
            ->count();
        $visaIssued = VisaUpdateLog::where('new_values->status', 'issued')
            ->whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo)
            ->when($branchId, fn($q) => $q->whereHas('visaSubmission.passenger.booking', $branchScope))
            ->count();
        $visaPending = VisaSubmission::where('status', VisaStatus::PENDING)
            ->whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo)
            ->when($branchId, fn($q) => $q->whereHas('passenger.booking', $branchScope))
            ->count();

        $fingerprintApproved = FingerprintDetailLog::where('new_values->status', FingerprintStatus::APPROVED->value)
            ->whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo)
            ->when($branchId, fn($q) => $q->whereHas('fingerprintDetail.passenger.booking', $branchScope))
            ->count();
        $fingerprintDone = FingerprintDetailLog::where('new_values->status', FingerprintStatus::DONE->value)
            ->whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo)
            ->when($branchId, fn($q) => $q->whereHas('fingerprintDetail.passenger.booking', $branchScope))
            ->count();
        $fingerprintProcessing = FingerprintDetailLog::where('new_values->status', FingerprintStatus::PROCESSING->value)
            ->whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo)
            ->when($branchId, fn($q) => $q->whereHas('fingerprintDetail.passenger.booking', $branchScope))
            ->count();

        $invoiceCount = Invoice::whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo)
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))->count();
        $invoiceTotalAmount = Invoice::whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo)
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))->sum('total_amount');

        $inboundTicket = IssuedTicketLog::whereIn('new_data->status', [TicketStatus::ISSUED->value, TicketStatus::RE_ISSUED->value])
            ->whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo)
            ->whereHas('issuedTicket', fn($q) => $q->whereNotNull('inbound_date'))
            ->when($branchId, fn($q) => $q->whereHas('issuedTicket.booking', $branchScope))
            ->count();

        $outboundTicket = IssuedTicketLog::whereIn('new_data->status', [TicketStatus::ISSUED->value, TicketStatus::RE_ISSUED->value])
            ->whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo)
            ->whereHas('issuedTicket', fn($q) => $q->whereNotNull('outbound_date'))
            ->when($branchId, fn($q) => $q->whereHas('issuedTicket.booking', $branchScope))
            ->count();

        $pendingTicket = IssuedTicket::whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo)
            ->when($branchId, fn($q) => $q->whereHas('booking', $branchScope))
            ->where('status', TicketStatus::PENDING)
            ->count();

        $totalDue = Invoice::whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo)
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))->sum('balance');

        $totalDueCollection = Voucher::whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo)
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->whereHas('transactionType', function ($query) {
                $query->where('name', 'Due Collection');
            })->sum('amount');

        $totalPassengers = Passenger::whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo)
            ->when($branchId, fn($q) => $q->whereHas('booking', $branchScope))->count();

        $totalCashPayment = Payment::whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo)
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->where('payment_method', PaymentMethod::CASH)
            ->whereHas('vouchers.transactionType', fn($q) => $q->whereIn('name', ['Initial Payment', 'Due Collection']))
            ->sum('amount');

        $totalBankPayment = Payment::whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo)
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->where('payment_method', PaymentMethod::BANK)
            ->whereHas('vouchers.transactionType', fn($q) => $q->whereIn('name', ['Initial Payment', 'Due Collection']))
            ->sum('amount');

        $payments = Payment::whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo)
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->whereHas('vouchers.transactionType', fn($q) => $q->whereIn('name', ['Initial Payment', 'Due Collection']))
            ->with(['branch', 'vouchers.transactionType', 'vouchers.user.branch', 'vouchers.booking'])
            ->get();

        $vouchersByDate = [];
        foreach ($payments as $payment) {
            $dateKey = $payment->created_at->format('Y-m-d');
            foreach ($payment->vouchers as $v) {
                if (!in_array($v->transactionType?->name, ['Initial Payment', 'Due Collection'])) {
                    continue;
                }
                $vouchersByDate[$dateKey][] = [
                    'invoice_id' => $v->booking?->invoice_id ?? 'N/A',
                    'voucher_no' => $v->voucher_id ?? $v->id,
                    'method' => ucfirst($v->payment_method?->value ?? ''),
                    'transaction_type' => $v->transactionType?->name ?? '',
                    'trx_id' => $v->transaction_id ?? '-',
                    'receive_by' => $v->user?->name ?? '',
                    'receive_at' => $v->user?->branch?->name ?? 'Central',
                    'amount' => (float) $v->amount,
                    'payment_date' => $v->payment_date?->format('d-M-Y') ?? '',
                ];
            }
        }
        $vouchersByDateJson = json_encode($vouchersByDate);

        return view('reports.branch-wise', compact(
            'visaSubmitted', 'visaIssued', 'visaPending',
            'fingerprintApproved', 'fingerprintDone', 'fingerprintProcessing',
            'invoiceCount', 'invoiceTotalAmount',
            'inboundTicket', 'outboundTicket', 'pendingTicket',
            'totalDue', 'totalDueCollection', 'totalPassengers',
            'totalCashPayment', 'totalBankPayment',
            'dateFrom', 'dateTo', 'selectedBranch', 'branches', 'userBranchId',
            'vouchersByDateJson', 'vouchersByDate'
        ));
    }
}
