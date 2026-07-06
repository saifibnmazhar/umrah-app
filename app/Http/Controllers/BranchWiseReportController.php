<?php

namespace App\Http\Controllers;

use App\Enums\FingerprintStatus;
use App\Enums\PaymentMethod;
use App\Enums\TicketStatus;
use App\Enums\VisaStatus;
use App\Models\Branch;
use App\Models\FingerprintDetail;
use App\Models\Invoice;
use App\Models\IssuedTicket;
use App\Models\Passenger;
use App\Models\Payment;
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

        $visaSubmitted = Passenger::whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo)
            ->when($branchId, fn($q) => $q->whereHas('booking', $branchScope))
            ->whereHas('visaSubmission', fn($q) => $q->where('status', VisaStatus::SUBMITTED))->count();
        $visaIssued = Passenger::whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo)
            ->when($branchId, fn($q) => $q->whereHas('booking', $branchScope))
            ->whereHas('visaSubmission', fn($q) => $q->where('status', VisaStatus::ISSUED))->count();
        $visaPending = Passenger::whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo)
            ->when($branchId, fn($q) => $q->whereHas('booking', $branchScope))
            ->whereDoesntHave('visaSubmission', fn($q) => $q->whereIn('status', [VisaStatus::SUBMITTED, VisaStatus::ISSUED]))->count();

        $fingerprintApproved = FingerprintDetail::whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo)
            ->when($branchId, fn($q) => $q->whereHas('passenger.booking', $branchScope))
            ->where('status', FingerprintStatus::APPROVED)->count();
        $fingerprintDone = FingerprintDetail::whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo)
            ->when($branchId, fn($q) => $q->whereHas('passenger.booking', $branchScope))
            ->where('status', FingerprintStatus::DONE)->count();
        $fingerprintProcessing = FingerprintDetail::whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo)
            ->when($branchId, fn($q) => $q->whereHas('passenger.booking', $branchScope))
            ->whereNotIn('status', [FingerprintStatus::APPROVED, FingerprintStatus::DONE])->count();

        $invoiceCount = Invoice::whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo)
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))->count();
        $invoiceTotalAmount = Invoice::whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo)
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))->sum('total_amount');

        $inboundTicket = IssuedTicket::whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo)
            ->when($branchId, fn($q) => $q->whereHas('booking', $branchScope))
            ->whereIn('status', [TicketStatus::ISSUED, TicketStatus::RE_ISSUED])
            ->whereNotNull('inbound_date')
            ->count();

        $outboundTicket = IssuedTicket::whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo)
            ->when($branchId, fn($q) => $q->whereHas('booking', $branchScope))
            ->whereIn('status', [TicketStatus::ISSUED, TicketStatus::RE_ISSUED])
            ->whereNotNull('outbound_date')
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

        return view('reports.branch-wise', compact(
            'visaSubmitted', 'visaIssued', 'visaPending',
            'fingerprintApproved', 'fingerprintDone', 'fingerprintProcessing',
            'invoiceCount', 'invoiceTotalAmount',
            'inboundTicket', 'outboundTicket', 'pendingTicket',
            'totalDue', 'totalDueCollection', 'totalPassengers',
            'totalCashPayment', 'totalBankPayment',
            'dateFrom', 'dateTo', 'selectedBranch', 'branches', 'userBranchId'
        ));
    }
}
