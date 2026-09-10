<?php

namespace App\Http\Controllers;

use App\Enums\FingerprintStatus;
use App\Enums\PaymentMethod;
use App\Enums\TicketStatus;
use App\Enums\VisaStatus;
use App\Models\Booking;
use App\Models\CurrencyRate;
use App\Models\FingerprintDetailLog;
use App\Models\Invoice;
use App\Models\IssuedTicket;
use App\Models\IssuedTicketLog;
use App\Models\Package;
use App\Models\Passenger;
use App\Models\Payment;
use App\Models\TicketRequest;
use App\Models\VisaSubmission;
use App\Models\VisaUpdateLog;
use App\Models\Voucher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View|RedirectResponse
    {
        if (! auth()->user()) {
            return redirect()->route('login');
        }

        $branchId = auth()->user()->branch_id;
        $firstRate = (float) (CurrencyRate::orderBy('created_at')->first()?->rate ?? 0);
        $branchScope = fn ($query) => $query
            ->where('booking_branch_id', $branchId);

        $packages = Package::where('is_active', true)
            ->with(['ticketFare.route.fromCity', 'ticketFare.route.toCity', 'ticketFare.route.returnCity', 'ticketFare.airline', 'ticketFare.airlineClass'])
            ->orderBy('id', 'desc')
            ->get();

        $visaSubmitted = VisaUpdateLog::where('new_values->status', 'submitted')
            ->where('created_at', '>=', now()->subDays(30))
            ->when($branchId, fn ($q) => $q->whereHas('visaSubmission.passenger.booking', $branchScope))
            ->count();
        $visaIssued = VisaUpdateLog::where('new_values->status', 'issued')
            ->where('created_at', '>=', now()->subDays(30))
            ->when($branchId, fn ($q) => $q->whereHas('visaSubmission.passenger.booking', $branchScope))
            ->count();
        $visaPending = VisaSubmission::where('status', VisaStatus::PENDING)
            ->where('created_at', '>=', now()->subDays(30))
            ->when($branchId, fn ($q) => $q->whereHas('passenger.booking', $branchScope))
            ->count();

        $fingerprintApproved = FingerprintDetailLog::where('new_values->status', FingerprintStatus::APPROVED->value)
            ->where('created_at', '>=', now()->subDays(30))
            ->when($branchId, fn ($q) => $q->whereHas('fingerprintDetail.passenger.booking', $branchScope))
            ->count();
        $fingerprintDone = FingerprintDetailLog::where('new_values->status', FingerprintStatus::DONE->value)
            ->where('created_at', '>=', now()->subDays(30))
            ->when($branchId, fn ($q) => $q->whereHas('fingerprintDetail.passenger.booking', $branchScope))
            ->count();
        $fingerprintProcessing = FingerprintDetailLog::where('new_values->status', FingerprintStatus::PROCESSING->value)
            ->where('created_at', '>=', now()->subDays(30))
            ->when($branchId, fn ($q) => $q->whereHas('fingerprintDetail.passenger.booking', $branchScope))
            ->count();

        $effectiveDateFrom = now()->subDays(30)->startOfDay()->toDateTimeString();
        $effectiveDateTo = now()->endOfDay()->toDateTimeString();

        $profitRow = DB::table('passengers as p')
            ->join('bookings as b', 'b.id', '=', 'p.booking_id')
            ->leftJoin('currency_rates as cr', 'cr.id', '=', 'b.currency_rate_id')
            ->where('b.is_cancelled', false)
            ->where('p.is_cancelled', false)
            ->whereNotNull('b.invoice_id')
            ->when($branchId, fn ($q) => $q->where('b.booking_branch_id', $branchId))
            ->where(function ($q) use ($effectiveDateFrom, $effectiveDateTo) {
                $q->whereBetween('p.visa_profit_effective_at', [$effectiveDateFrom, $effectiveDateTo])
                    ->orWhereBetween('p.ticket_profit_effective_at', [$effectiveDateFrom, $effectiveDateTo])
                    ->orWhereBetween('p.service_charge_effective_at', [$effectiveDateFrom, $effectiveDateTo]);
            })
            ->selectRaw('
                COALESCE(SUM(
                    CASE WHEN p.visa_profit_effective_at BETWEEN ? AND ? THEN p.visa_profit ELSE 0 END
                    + CASE WHEN p.ticket_profit_effective_at BETWEEN ? AND ? THEN p.ticket_profit ELSE 0 END
                    + CASE WHEN p.service_charge_effective_at BETWEEN ? AND ? THEN p.service_charge ELSE 0 END
                ), 0) as sar_total,
                COALESCE(SUM(
                    CASE WHEN p.visa_profit_effective_at BETWEEN ? AND ? THEN p.visa_profit * COALESCE(cr.rate, ?) ELSE 0 END
                    + CASE WHEN p.ticket_profit_effective_at BETWEEN ? AND ? THEN p.ticket_profit * COALESCE(cr.rate, ?) ELSE 0 END
                    + CASE WHEN p.service_charge_effective_at BETWEEN ? AND ? THEN p.service_charge * COALESCE(cr.rate, ?) ELSE 0 END
                ), 0) as bdt_total
            ', [
                $effectiveDateFrom, $effectiveDateTo,
                $effectiveDateFrom, $effectiveDateTo,
                $effectiveDateFrom, $effectiveDateTo,
                $effectiveDateFrom, $effectiveDateTo, $firstRate,
                $effectiveDateFrom, $effectiveDateTo, $firstRate,
                $effectiveDateFrom, $effectiveDateTo, $firstRate,
            ])
            ->first();

        $reIssueProfitRow = DB::table('re_issued_tickets as rit')
            ->join('issued_tickets as it', 'it.id', '=', 'rit.issued_ticket_id')
            ->join('passengers as p', 'p.id', '=', 'it.passenger_id')
            ->join('bookings as b', 'b.id', '=', 'p.booking_id')
            ->leftJoin('currency_rates as cr', 'cr.id', '=', 'b.currency_rate_id')
            ->where('b.is_cancelled', false)
            ->where('p.is_cancelled', false)
            ->whereNotNull('b.invoice_id')
            ->whereNull('it.deleted_at')
            ->whereNull('rit.deleted_at')
            ->where('rit.payment_by', 'customer')
            ->whereBetween('rit.created_at', [$effectiveDateFrom, $effectiveDateTo])
            ->when($branchId, fn ($q) => $q->where('b.booking_branch_id', $branchId))
            ->selectRaw('COALESCE(SUM(rit.service_charge), 0) as sar, COALESCE(SUM(rit.service_charge * COALESCE(cr.rate, ?)), 0) as bdt', [$firstRate])
            ->first();

        $reIssueCostRow = DB::table('re_issued_tickets as rit')
            ->join('issued_tickets as it', 'it.id', '=', 'rit.issued_ticket_id')
            ->join('passengers as p', 'p.id', '=', 'it.passenger_id')
            ->join('bookings as b', 'b.id', '=', 'p.booking_id')
            ->leftJoin('currency_rates as cr', 'cr.id', '=', 'b.currency_rate_id')
            ->where('b.is_cancelled', false)
            ->where('p.is_cancelled', false)
            ->whereNotNull('b.invoice_id')
            ->whereNull('it.deleted_at')
            ->whereNull('rit.deleted_at')
            ->where('rit.payment_by', 'company')
            ->whereBetween('rit.created_at', [$effectiveDateFrom, $effectiveDateTo])
            ->when($branchId, fn ($q) => $q->where('b.booking_branch_id', $branchId))
            ->selectRaw('COALESCE(SUM(rit.total_cost), 0) as sar, COALESCE(SUM(rit.total_cost * COALESCE(cr.rate, ?)), 0) as bdt', [$firstRate])
            ->first();

        $refundProfitRow = DB::table('refunded_tickets as rft')
            ->join('issued_tickets as it', 'it.id', '=', 'rft.issued_ticket_id')
            ->join('passengers as p', 'p.id', '=', 'it.passenger_id')
            ->join('bookings as b', 'b.id', '=', 'p.booking_id')
            ->leftJoin('currency_rates as cr', 'cr.id', '=', 'b.currency_rate_id')
            ->where('b.is_cancelled', false)
            ->where('p.is_cancelled', false)
            ->whereNotNull('b.invoice_id')
            ->whereNull('it.deleted_at')
            ->whereNull('rft.deleted_at')
            ->whereBetween('rft.created_at', [$effectiveDateFrom, $effectiveDateTo])
            ->when($branchId, fn ($q) => $q->where('b.booking_branch_id', $branchId))
            ->selectRaw('COALESCE(SUM(rft.service_charge), 0) as sar, COALESCE(SUM(rft.service_charge * COALESCE(cr.rate, ?)), 0) as bdt', [$firstRate])
            ->first();

        $totalProfit = (float) ($profitRow->sar_total ?? 0)
            + (float) ($reIssueProfitRow->sar ?? 0)
            + (float) ($refundProfitRow->sar ?? 0)
            - (float) ($reIssueCostRow->sar ?? 0);
        $totalProfitBdt = (float) ($profitRow->bdt_total ?? 0)
            + (float) ($reIssueProfitRow->bdt ?? 0)
            + (float) ($refundProfitRow->bdt ?? 0)
            - (float) ($reIssueCostRow->bdt ?? 0);

        $fingerprintRow = DB::table('fingerprints as fp')
            ->join('bookings as b', 'b.id', '=', 'fp.booking_id')
            ->leftJoin('currency_rates as cr', 'cr.id', '=', 'b.currency_rate_id')
            ->leftJoin(DB::raw('
                (SELECT fingerprint_id, MAX(created_at) as last_cost_at
                 FROM fingerprint_cost_logs GROUP BY fingerprint_id) as fcl
            '), 'fcl.fingerprint_id', '=', 'fp.id')
            ->leftJoin(DB::raw('
                (SELECT fd.fingerprint_id, MIN(fdl.created_at) as status_at
                 FROM fingerprint_detail_logs fdl
                 JOIN fingerprint_details fd ON fd.id = fdl.fingerprint_detail_id
                 WHERE fdl.new_values->>"$.status" IN ("done", "approved")
                 GROUP BY fd.fingerprint_id) as fsl
            '), 'fsl.fingerprint_id', '=', 'fp.id')
            ->where('b.is_cancelled', false)
            ->whereNotNull('b.invoice_id')
            ->where('fp.cost', '>', 0)
            ->where('b.fingerprint_location', 'home')
            ->where(function ($q) use ($effectiveDateFrom, $effectiveDateTo) {
                $q->whereBetween('fcl.last_cost_at', [$effectiveDateFrom, $effectiveDateTo])
                    ->orWhere(function ($q2) use ($effectiveDateFrom, $effectiveDateTo) {
                        $q2->whereNull('fcl.last_cost_at')
                            ->whereBetween('fsl.status_at', [$effectiveDateFrom, $effectiveDateTo]);
                    });
            })
            ->when($branchId, fn ($q) => $q->where('b.booking_branch_id', $branchId))
            ->selectRaw('
                COALESCE(SUM(fp.profit), 0) as sar_total,
                COALESCE(SUM(fp.profit * COALESCE(cr.rate, ?)), 0) as bdt_total
            ', [$firstRate])
            ->first();

        $totalFingerprintProfit = (float) ($fingerprintRow->sar_total ?? 0);
        $totalFingerprintProfitBdt = (float) ($fingerprintRow->bdt_total ?? 0);

        $invoiceCount = Invoice::where('created_at', '>=', now()->subDays(30))
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))->count();
        $invoiceRow = Invoice::where('invoices.created_at', '>=', now()->subDays(30))
            ->when($branchId, fn ($q) => $q->where('invoices.branch_id', $branchId))
            ->leftJoin('bookings', 'invoices.booking_id', '=', 'bookings.id')
            ->leftJoin('currency_rates', 'bookings.currency_rate_id', '=', 'currency_rates.id')
            ->selectRaw('
                SUM(invoices.total_amount) as sar_total,
                SUM(invoices.total_amount * COALESCE(currency_rates.rate, ?)) as bdt_total,
                SUM(invoices.balance) as due_sar,
                SUM(invoices.balance * COALESCE(currency_rates.rate, ?)) as due_bdt
            ', [$firstRate, $firstRate])
            ->first();
        $invoiceTotalAmount = $invoiceRow->sar_total ?? 0;
        $invoiceTotalAmountBdt = $invoiceRow->bdt_total ?? 0;
        $totalDue = $invoiceRow->due_sar ?? 0;
        $totalDueBdt = $invoiceRow->due_bdt ?? 0;

        $inboundTicket = IssuedTicketLog::whereIn('new_data->status', [TicketStatus::ISSUED->value, TicketStatus::RE_ISSUED->value])
            ->where('created_at', '>=', now()->subDays(30))
            ->whereHas('issuedTicket', fn ($q) => $q->whereNotNull('inbound_date'))
            ->when($branchId, fn ($q) => $q->whereHas('issuedTicket.booking', $branchScope))
            ->count();

        $outboundTicket = IssuedTicketLog::whereIn('new_data->status', [TicketStatus::ISSUED->value, TicketStatus::RE_ISSUED->value])
            ->where('created_at', '>=', now()->subDays(30))
            ->whereHas('issuedTicket', fn ($q) => $q->whereNotNull('outbound_date'))
            ->when($branchId, fn ($q) => $q->whereHas('issuedTicket.booking', $branchScope))
            ->count();

        $pendingTicket = IssuedTicket::where('created_at', '>=', now()->subDays(30))
            ->when($branchId, fn ($q) => $q->whereHas('booking', $branchScope))
            ->where('status', TicketStatus::PENDING)
            ->count();

        $dueCollectionRow = Voucher::where('vouchers.created_at', '>=', now()->subDays(30))
            ->when($branchId, fn ($q) => $q->where('vouchers.branch_id', $branchId))
            ->whereHas('transactionType', fn ($q) => $q->where('name', 'Due Collection'))
            ->leftJoin('bookings', 'vouchers.booking_id', '=', 'bookings.id')
            ->leftJoin('currency_rates', 'bookings.currency_rate_id', '=', 'currency_rates.id')
            ->selectRaw('
                SUM(vouchers.amount) as sar_total,
                SUM(vouchers.amount * COALESCE(currency_rates.rate, ?)) as bdt_total,
                SUM(CASE WHEN vouchers.payment_method = ? THEN vouchers.amount ELSE 0 END) as cash_sar,
                SUM(CASE WHEN vouchers.payment_method = ? THEN vouchers.amount * COALESCE(currency_rates.rate, ?) ELSE 0 END) as cash_bdt,
                SUM(CASE WHEN vouchers.payment_method = ? THEN vouchers.amount ELSE 0 END) as bank_sar,
                SUM(CASE WHEN vouchers.payment_method = ? THEN vouchers.amount * COALESCE(currency_rates.rate, ?) ELSE 0 END) as bank_bdt
            ', [$firstRate, PaymentMethod::CASH->value, PaymentMethod::CASH->value, $firstRate, PaymentMethod::BANK->value, PaymentMethod::BANK->value, $firstRate])
            ->first();
        $totalDueCollection = $dueCollectionRow->sar_total ?? 0;
        $totalDueCollectionBdt = $dueCollectionRow->bdt_total ?? 0;
        $dueCollectionCash = $dueCollectionRow->cash_sar ?? 0;
        $dueCollectionCashBdt = $dueCollectionRow->cash_bdt ?? 0;
        $dueCollectionBank = $dueCollectionRow->bank_sar ?? 0;
        $dueCollectionBankBdt = $dueCollectionRow->bank_bdt ?? 0;

        $scDeductionRow = Voucher::where('vouchers.created_at', '>=', now()->subDays(30))
            ->when($branchId, fn ($q) => $q->where('vouchers.branch_id', $branchId))
            ->whereHas('transactionType', fn ($q) => $q->where('name', 'Service Charge Deduction'))
            ->leftJoin('bookings', 'vouchers.booking_id', '=', 'bookings.id')
            ->leftJoin('currency_rates', 'bookings.currency_rate_id', '=', 'currency_rates.id')
            ->selectRaw('
                SUM(vouchers.amount) as sar_total,
                SUM(vouchers.amount * COALESCE(currency_rates.rate, ?)) as bdt_total
            ', [$firstRate])
            ->first();
        $totalServiceChargeDeduction = $scDeductionRow->sar_total ?? 0;
        $totalServiceChargeDeductionBdt = $scDeductionRow->bdt_total ?? 0;

        $refundRow = Voucher::where('vouchers.created_at', '>=', now()->subDays(30))
            ->when($branchId, fn ($q) => $q->where('vouchers.branch_id', $branchId))
            ->whereHas('transactionType', fn ($q) => $q->where('name', 'Customer Refund'))
            ->leftJoin('bookings', 'vouchers.booking_id', '=', 'bookings.id')
            ->leftJoin('currency_rates', 'bookings.currency_rate_id', '=', 'currency_rates.id')
            ->selectRaw('
                SUM(vouchers.amount) as sar_total,
                SUM(vouchers.amount * COALESCE(currency_rates.rate, ?)) as bdt_total
            ', [$firstRate])
            ->first();
        $totalRefund = $refundRow->sar_total ?? 0;
        $totalRefundBdt = $refundRow->bdt_total ?? 0;

        $ticketRefundRow = Voucher::where('vouchers.created_at', '>=', now()->subDays(30))
            ->when($branchId, fn ($q) => $q->where('vouchers.branch_id', $branchId))
            ->whereHas('transactionType', fn ($q) => $q->whereIn('name', ['Ticket Refund - Payment', 'Ticket Refund - Re-issue']))
            ->leftJoin('bookings', 'vouchers.booking_id', '=', 'bookings.id')
            ->leftJoin('currency_rates', 'bookings.currency_rate_id', '=', 'currency_rates.id')
            ->selectRaw('
                SUM(vouchers.amount) as sar_total,
                SUM(vouchers.amount * COALESCE(currency_rates.rate, ?)) as bdt_total
            ', [$firstRate])
            ->first();
        $totalTicketRefund = $ticketRefundRow->sar_total ?? 0;
        $totalTicketRefundBdt = $ticketRefundRow->bdt_total ?? 0;

        $totalPassengers = Passenger::where('created_at', '>=', now()->subDays(30))
            ->when($branchId, fn ($q) => $q->whereHas('booking', $branchScope))->count();

        $initialPaymentRow = Payment::where('payments.created_at', '>=', now()->subDays(30))
            ->when($branchId, fn ($q) => $q->where('payments.branch_id', $branchId))
            ->whereHas('vouchers.transactionType', fn ($q) => $q->where('name', 'Initial Payment'))
            ->leftJoin('bookings', 'payments.booking_id', '=', 'bookings.id')
            ->leftJoin('currency_rates', 'bookings.currency_rate_id', '=', 'currency_rates.id')
            ->selectRaw('
                SUM(payments.amount) as sar_total,
                SUM(payments.amount * COALESCE(currency_rates.rate, ?)) as bdt_total,
                SUM(CASE WHEN payments.payment_method = ? THEN payments.amount ELSE 0 END) as cash_sar,
                SUM(CASE WHEN payments.payment_method = ? THEN payments.amount * COALESCE(currency_rates.rate, ?) ELSE 0 END) as cash_bdt,
                SUM(CASE WHEN payments.payment_method = ? THEN payments.amount ELSE 0 END) as bank_sar,
                SUM(CASE WHEN payments.payment_method = ? THEN payments.amount * COALESCE(currency_rates.rate, ?) ELSE 0 END) as bank_bdt
            ', [$firstRate, PaymentMethod::CASH->value, PaymentMethod::CASH->value, $firstRate, PaymentMethod::BANK->value, PaymentMethod::BANK->value, $firstRate])
            ->first();
        $totalInitialPayment = $initialPaymentRow->sar_total ?? 0;
        $totalInitialPaymentBdt = $initialPaymentRow->bdt_total ?? 0;
        $initialPaymentCash = $initialPaymentRow->cash_sar ?? 0;
        $initialPaymentCashBdt = $initialPaymentRow->cash_bdt ?? 0;
        $initialPaymentBank = $initialPaymentRow->bank_sar ?? 0;
        $initialPaymentBankBdt = $initialPaymentRow->bank_bdt ?? 0;

        $paymentRow = Payment::where('payments.created_at', '>=', now()->subDays(30))
            ->when($branchId, fn ($q) => $q->where('payments.branch_id', $branchId))
            ->whereHas('vouchers.transactionType', fn ($q) => $q->whereIn('name', ['Initial Payment', 'Due Collection']))
            ->leftJoin('bookings', 'payments.booking_id', '=', 'bookings.id')
            ->leftJoin('currency_rates', 'bookings.currency_rate_id', '=', 'currency_rates.id')
            ->selectRaw('
                SUM(CASE WHEN payments.payment_method = ? THEN payments.amount ELSE 0 END) as cash_sar,
                SUM(CASE WHEN payments.payment_method = ? THEN payments.amount * COALESCE(currency_rates.rate, ?) ELSE 0 END) as cash_bdt,
                SUM(CASE WHEN payments.payment_method = ? THEN payments.amount ELSE 0 END) as bank_sar,
                SUM(CASE WHEN payments.payment_method = ? THEN payments.amount * COALESCE(currency_rates.rate, ?) ELSE 0 END) as bank_bdt
            ', [PaymentMethod::CASH->value, PaymentMethod::CASH->value, $firstRate, PaymentMethod::BANK->value, PaymentMethod::BANK->value, $firstRate])
            ->first();
        $totalCashPayment = $paymentRow->cash_sar ?? 0;
        $totalCashPaymentBdt = $paymentRow->cash_bdt ?? 0;
        $totalBankPayment = $paymentRow->bank_sar ?? 0;
        $totalBankPaymentBdt = $paymentRow->bank_bdt ?? 0;

        $totalReceiving = $totalInitialPayment + $totalDueCollection;
        $totalReceivingBdt = $totalInitialPaymentBdt + $totalDueCollectionBdt;
        $receivingCash = $initialPaymentCash + $dueCollectionCash;
        $receivingCashBdt = $initialPaymentCashBdt + $dueCollectionCashBdt;
        $receivingBank = $initialPaymentBank + $dueCollectionBank;
        $receivingBankBdt = $initialPaymentBankBdt + $dueCollectionBankBdt;

        $bookingBranches = Booking::whereNotNull('booking_branch_id')
            ->join('branches', 'branches.id', '=', 'bookings.booking_branch_id')
            ->pluck('branches.name', 'bookings.id')
            ->toArray();

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

        return view('dashboard.index', compact('packages', 'visaSubmitted', 'visaIssued', 'visaPending', 'fingerprintApproved', 'fingerprintDone', 'fingerprintProcessing', 'totalFingerprintProfit', 'totalFingerprintProfitBdt', 'invoiceCount', 'invoiceTotalAmount', 'invoiceTotalAmountBdt', 'inboundTicket', 'outboundTicket', 'pendingTicket', 'totalDue', 'totalDueBdt', 'totalDueCollection', 'totalDueCollectionBdt', 'dueCollectionCash', 'dueCollectionCashBdt', 'dueCollectionBank', 'dueCollectionBankBdt', 'totalPassengers', 'totalInitialPayment', 'totalInitialPaymentBdt', 'initialPaymentCash', 'initialPaymentCashBdt', 'initialPaymentBank', 'initialPaymentBankBdt', 'totalCashPayment', 'totalCashPaymentBdt', 'totalBankPayment', 'totalBankPaymentBdt', 'totalReceiving', 'totalReceivingBdt', 'receivingCash', 'receivingCashBdt', 'receivingBank', 'receivingBankBdt', 'totalProfit', 'totalProfitBdt', 'totalServiceChargeDeduction', 'totalServiceChargeDeductionBdt', 'totalRefund', 'totalRefundBdt', 'totalTicketRefund', 'totalTicketRefundBdt', 'bookingBranches', 'pendingReIssueRequests', 'pendingAdditionalRequests', 'pendingRefundRequests'));
    }
}
