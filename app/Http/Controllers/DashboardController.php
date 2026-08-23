<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Package;
use App\Models\TicketRequest;
use App\Queries\BranchWiseReportQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View|RedirectResponse
    {
        if (! auth()->user()) {
            return redirect()->route('login');
        }

        $branchId = auth()->user()->branch_id;
        $dateFrom = now()->subDays(30);
        $dateTo = now();

        $packages = Package::where('is_active', true)
            ->with(['ticketFare.route.fromCity', 'ticketFare.route.toCity', 'ticketFare.route.returnCity', 'ticketFare.airline', 'ticketFare.airlineClass'])
            ->orderBy('id', 'desc')
            ->get();

        $query = new BranchWiseReportQuery($dateFrom, $dateTo, $branchId);
        $summary = $query->summary();

        $pendingReIssueRequests = TicketRequest::whereIn('status', ['pending', 'processed', 'rejected'])
            ->where('request_type', 're_issue')
            ->when($branchId, fn ($q) => $q->whereHas('booking', fn ($b) => $b->where('booking_branch_id', $branchId)))
            ->with(['booking.customer', 'booking.bookingBranch', 'passenger', 'issuedTicket'])
            ->orderByRaw("FIELD(status, 'pending', 'processed', 'rejected')")
            ->get()
            ->groupBy('booking_id')
            ->map(fn ($rows, $bookingId) => [
                'booking_id' => $bookingId,
                'invoice_no' => $rows->first()->booking?->invoice_id ?? $bookingId,
                'customer_name' => $rows->first()->booking?->customer?->name ?? '-',
                'branch' => $rows->first()->booking?->bookingBranch?->name ?? '-',
                'passenger_count' => $rows->pluck('passenger_id')->unique()->count(),
                'requested_at' => $rows->min('requested_at'),
                'status' => $rows->first()->status,
            ])
            ->values();

        $pendingAdditionalRequests = TicketRequest::whereIn('status', ['pending', 'processed', 'rejected'])
            ->where('request_type', 'additional')
            ->when($branchId, fn ($q) => $q->whereHas('booking', fn ($b) => $b->where('booking_branch_id', $branchId)))
            ->with(['booking.customer', 'booking.bookingBranch', 'passenger'])
            ->orderByRaw("FIELD(status, 'pending', 'processed', 'rejected')")
            ->get()
            ->groupBy('booking_id')
            ->map(fn ($rows, $bookingId) => [
                'booking_id' => $bookingId,
                'invoice_no' => $rows->first()->booking?->invoice_id ?? $bookingId,
                'customer_name' => $rows->first()->booking?->customer?->name ?? '-',
                'branch' => $rows->first()->booking?->bookingBranch?->name ?? '-',
                'passenger_count' => $rows->pluck('passenger_id')->unique()->count(),
                'requested_at' => $rows->min('requested_at'),
                'status' => $rows->first()->status,
            ])
            ->values();

        $pendingRefundRequests = TicketRequest::whereIn('status', ['pending', 'processed', 'rejected'])
            ->where('request_type', 'refund')
            ->when($branchId, fn ($q) => $q->whereHas('booking', fn ($b) => $b->where('booking_branch_id', $branchId)))
            ->with(['booking.customer', 'booking.bookingBranch', 'passenger'])
            ->orderByRaw("FIELD(status, 'pending', 'processed', 'rejected')")
            ->get()
            ->groupBy('booking_id')
            ->map(fn ($rows, $bookingId) => [
                'booking_id' => $bookingId,
                'invoice_no' => $rows->first()->booking?->invoice_id ?? $bookingId,
                'customer_name' => $rows->first()->booking?->customer?->name ?? '-',
                'branch' => $rows->first()->booking?->bookingBranch?->name ?? '-',
                'passenger_count' => $rows->pluck('passenger_id')->unique()->count(),
                'requested_at' => $rows->min('requested_at'),
                'status' => $rows->first()->status,
            ])
            ->values();

        $totals = [
            'invoiceTotalAmount' => $summary['invoiceTotalAmount'],
            'invoiceTotalAmountBdt' => $summary['invoiceTotalAmountBdt'],
            'totalInitialPayment' => $summary['totalInitialPayment'],
            'totalInitialPaymentBdt' => $summary['totalInitialPaymentBdt'],
            'initialPaymentCash' => $summary['initialPaymentCash'],
            'initialPaymentCashBdt' => $summary['initialPaymentCashBdt'],
            'initialPaymentBank' => $summary['initialPaymentBank'],
            'initialPaymentBankBdt' => $summary['initialPaymentBankBdt'],
            'totalCashPayment' => $summary['totalCashPayment'],
            'totalCashPaymentBdt' => $summary['totalCashPaymentBdt'],
            'totalBankPayment' => $summary['totalBankPayment'],
            'totalBankPaymentBdt' => $summary['totalBankPaymentBdt'],
            'totalDue' => $summary['totalDue'],
            'totalDueBdt' => $summary['totalDueBdt'],
            'totalDueCollection' => $summary['totalDueCollection'],
            'totalDueCollectionBdt' => $summary['totalDueCollectionBdt'],
            'dueCollectionCash' => $summary['dueCollectionCash'],
            'dueCollectionCashBdt' => $summary['dueCollectionCashBdt'],
            'dueCollectionBank' => $summary['dueCollectionBank'],
            'dueCollectionBankBdt' => $summary['dueCollectionBankBdt'],
            'totalProfit' => $summary['totalProfit'],
            'totalProfitBdt' => $summary['totalProfitBdt'],
            'totalReceiving' => $summary['totalReceiving'],
            'totalReceivingBdt' => $summary['totalReceivingBdt'],
            'receivingCash' => $summary['receivingCash'],
            'receivingCashBdt' => $summary['receivingCashBdt'],
            'receivingBank' => $summary['receivingBank'],
            'receivingBankBdt' => $summary['receivingBankBdt'],
            'totalRefund' => $summary['totalRefund'],
            'totalRefundBdt' => $summary['totalRefundBdt'],
            'totalTicketRefund' => $summary['totalTicketRefund'],
            'totalTicketRefundBdt' => $summary['totalTicketRefundBdt'],
            'totalFingerprintProfit' => $summary['totalFingerprintProfit'],
            'totalFingerprintProfitBdt' => $summary['totalFingerprintProfitBdt'],
            'totalServiceChargeDeduction' => $summary['totalServiceChargeDeduction'],
            'totalServiceChargeDeductionBdt' => $summary['totalServiceChargeDeductionBdt'],
        ];

        $stats = [
            'visaSubmitted' => $summary['visaSubmitted'],
            'visaIssued' => $summary['visaIssued'],
            'visaPending' => $summary['visaPending'],
            'inboundTicket' => $summary['inboundTicket'],
            'outboundTicket' => $summary['outboundTicket'],
            'pendingTicket' => $summary['pendingTicket'],
            'totalInvoice' => $summary['invoiceCount'],
            'totalDue' => $summary['totalDue'],
            'totalPassengers' => $summary['totalPassengers'],
            'totalReceived' => 86,
            'departureDone' => 50,
            'departureStay' => 30,
        ];

        $showSummaryCards = $this->canViewSummaryCards();
        $showPackages = true;
        $showRequests = $this->canViewTicketRequests();
        $showProfitCards = $this->canViewProfitCards();

        $bookingBranches = Booking::whereNotNull('booking_branch_id')
            ->join('branches', 'branches.id', '=', 'bookings.booking_branch_id')
            ->pluck('branches.name', 'bookings.id')
            ->toArray();

        return view('dashboard.index', compact(
            'packages',
            'stats',
            'totals',
            'showSummaryCards',
            'showPackages',
            'showRequests',
            'showProfitCards',
            'bookingBranches',
            'pendingReIssueRequests',
            'pendingAdditionalRequests',
            'pendingRefundRequests'
        ));
    }
}
