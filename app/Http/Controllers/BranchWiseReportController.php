<?php

namespace App\Http\Controllers;

use App\Enums\FingerprintStatus;
use App\Enums\TicketStatus;
use App\Enums\VisaStatus;
use App\Models\FingerprintDetail;
use App\Models\Invoice;
use App\Models\IssuedTicket;
use App\Models\Passenger;
use App\Models\Voucher;
use Illuminate\View\View;

class BranchWiseReportController extends Controller
{
    public function index(): View
    {
        $visaSubmitted = Passenger::where('created_at', '>=', now()->subDays(30))
            ->whereHas('visaSubmission', fn($q) => $q->where('status', VisaStatus::SUBMITTED))->count();
        $visaIssued = Passenger::where('created_at', '>=', now()->subDays(30))
            ->whereHas('visaSubmission', fn($q) => $q->where('status', VisaStatus::ISSUED))->count();
        $visaPending = Passenger::where('created_at', '>=', now()->subDays(30))
            ->whereDoesntHave('visaSubmission', fn($q) => $q->whereIn('status', [VisaStatus::SUBMITTED, VisaStatus::ISSUED]))->count();

        $fingerprintApproved = FingerprintDetail::where('created_at', '>=', now()->subDays(30))
            ->where('status', FingerprintStatus::APPROVED)->count();
        $fingerprintDone = FingerprintDetail::where('created_at', '>=', now()->subDays(30))
            ->where('status', FingerprintStatus::DONE)->count();
        $fingerprintProcessing = FingerprintDetail::where('created_at', '>=', now()->subDays(30))
            ->whereNotIn('status', [FingerprintStatus::APPROVED, FingerprintStatus::DONE])->count();

        $invoiceCount = Invoice::where('created_at', '>=', now()->subDays(30))->count();
        $invoiceTotalAmount = Invoice::where('created_at', '>=', now()->subDays(30))->sum('total_amount');

        $inboundTicket = IssuedTicket::where('created_at', '>=', now()->subDays(30))
            ->whereIn('status', [TicketStatus::ISSUED, TicketStatus::RE_ISSUED])
            ->whereNotNull('inbound_date')
            ->count();

        $outboundTicket = IssuedTicket::where('created_at', '>=', now()->subDays(30))
            ->whereIn('status', [TicketStatus::ISSUED, TicketStatus::RE_ISSUED])
            ->whereNotNull('outbound_date')
            ->count();

        $pendingTicket = IssuedTicket::where('created_at', '>=', now()->subDays(30))
            ->where('status', TicketStatus::PENDING)
            ->count();

        $totalDue = Invoice::where('created_at', '>=', now()->subDays(30))->sum('balance');

        $totalDueCollection = Voucher::where('created_at', '>=', now()->subDays(30))
            ->whereHas('transactionType', function ($query) {
                $query->where('name', 'Due Collection');
            })->sum('amount');

        $totalPassengers = Passenger::where('created_at', '>=', now()->subDays(30))->count();

        return view('reports.branch-wise', compact('visaSubmitted', 'visaIssued', 'visaPending', 'fingerprintApproved', 'fingerprintDone', 'fingerprintProcessing', 'invoiceCount', 'invoiceTotalAmount', 'inboundTicket', 'outboundTicket', 'pendingTicket', 'totalDue', 'totalDueCollection', 'totalPassengers'));
    }
}
