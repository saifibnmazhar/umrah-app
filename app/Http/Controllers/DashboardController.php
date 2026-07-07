<?php

namespace App\Http\Controllers;

use App\Enums\FingerprintStatus;
use App\Enums\PaymentMethod;
use App\Enums\TicketStatus;
use App\Enums\VisaStatus;
use App\Models\FingerprintDetail;
use App\Models\FingerprintDetailLog;
use App\Models\Invoice;
use App\Models\IssuedTicket;
use App\Models\IssuedTicketLog;
use App\Models\Package;
use App\Models\Passenger;
use App\Models\Payment;
use App\Models\VisaSubmission;
use App\Models\VisaUpdateLog;
use App\Models\Voucher;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View|RedirectResponse
    {
        if (!auth()->user()) {
            return redirect()->route('login');
        }

        $branchId = auth()->user()->branch_id;
        $branchScope = fn($query) => $query
            ->where('booking_branch_id', $branchId);

        $packages = Package::where('is_active', true)
            ->with(['ticketFare.route.fromCity', 'ticketFare.route.toCity', 'ticketFare.route.returnCity', 'ticketFare.airline', 'ticketFare.airlineClass'])
            ->orderBy('id', 'desc')
            ->get();

        $visaSubmitted = VisaUpdateLog::where('new_values->status', 'submitted')
            ->where('created_at', '>=', now()->subDays(30))
            ->when($branchId, fn($q) => $q->whereHas('visaSubmission.passenger.booking', $branchScope))
            ->count();
        $visaIssued = VisaUpdateLog::where('new_values->status', 'issued')
            ->where('created_at', '>=', now()->subDays(30))
            ->when($branchId, fn($q) => $q->whereHas('visaSubmission.passenger.booking', $branchScope))
            ->count();
        $visaPending = VisaSubmission::where('status', VisaStatus::PENDING)
            ->where('created_at', '>=', now()->subDays(30))
            ->when($branchId, fn($q) => $q->whereHas('passenger.booking', $branchScope))
            ->count();

        $fingerprintApproved = FingerprintDetailLog::where('new_values->status', FingerprintStatus::APPROVED->value)
            ->where('created_at', '>=', now()->subDays(30))
            ->when($branchId, fn($q) => $q->whereHas('fingerprintDetail.passenger.booking', $branchScope))
            ->count();
        $fingerprintDone = FingerprintDetailLog::where('new_values->status', FingerprintStatus::DONE->value)
            ->where('created_at', '>=', now()->subDays(30))
            ->when($branchId, fn($q) => $q->whereHas('fingerprintDetail.passenger.booking', $branchScope))
            ->count();
        $fingerprintProcessing = FingerprintDetailLog::where('new_values->status', FingerprintStatus::PROCESSING->value)
            ->where('created_at', '>=', now()->subDays(30))
            ->when($branchId, fn($q) => $q->whereHas('fingerprintDetail.passenger.booking', $branchScope))
            ->count();

        $invoiceCount = Invoice::where('created_at', '>=', now()->subDays(30))
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))->count();
        $invoiceTotalAmount = Invoice::where('created_at', '>=', now()->subDays(30))
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))->sum('total_amount');

        $inboundTicket = IssuedTicketLog::whereIn('new_data->status', [TicketStatus::ISSUED->value, TicketStatus::RE_ISSUED->value])
            ->where('created_at', '>=', now()->subDays(30))
            ->whereHas('issuedTicket', fn($q) => $q->whereNotNull('inbound_date'))
            ->when($branchId, fn($q) => $q->whereHas('issuedTicket.booking', $branchScope))
            ->count();

        $outboundTicket = IssuedTicketLog::whereIn('new_data->status', [TicketStatus::ISSUED->value, TicketStatus::RE_ISSUED->value])
            ->where('created_at', '>=', now()->subDays(30))
            ->whereHas('issuedTicket', fn($q) => $q->whereNotNull('outbound_date'))
            ->when($branchId, fn($q) => $q->whereHas('issuedTicket.booking', $branchScope))
            ->count();

        $pendingTicket = IssuedTicket::where('created_at', '>=', now()->subDays(30))
            ->when($branchId, fn($q) => $q->whereHas('booking', $branchScope))
            ->where('status', TicketStatus::PENDING)
            ->count();

        $totalDue = Invoice::where('created_at', '>=', now()->subDays(30))
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))->sum('balance');

        $totalDueCollection = Voucher::where('created_at', '>=', now()->subDays(30))
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->whereHas('transactionType', function ($query) {
                $query->where('name', 'Due Collection');
            })->sum('amount');

        $totalPassengers = Passenger::where('created_at', '>=', now()->subDays(30))
            ->when($branchId, fn($q) => $q->whereHas('booking', $branchScope))->count();

        $totalCashPayment = Payment::where('created_at', '>=', now()->subDays(30))
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->where('payment_method', PaymentMethod::CASH)
            ->whereHas('vouchers.transactionType', fn($q) => $q->whereIn('name', ['Initial Payment', 'Due Collection']))
            ->sum('amount');

        $totalBankPayment = Payment::where('created_at', '>=', now()->subDays(30))
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->where('payment_method', PaymentMethod::BANK)
            ->whereHas('vouchers.transactionType', fn($q) => $q->whereIn('name', ['Initial Payment', 'Due Collection']))
            ->sum('amount');

        return view('dashboard.index', compact('packages', 'visaSubmitted', 'visaIssued', 'visaPending', 'fingerprintApproved', 'fingerprintDone', 'fingerprintProcessing', 'invoiceCount', 'invoiceTotalAmount', 'inboundTicket', 'outboundTicket', 'pendingTicket', 'totalDue', 'totalDueCollection', 'totalPassengers', 'totalCashPayment', 'totalBankPayment'));
    }
}
