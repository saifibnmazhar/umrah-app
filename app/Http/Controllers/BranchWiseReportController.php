<?php

namespace App\Http\Controllers;

use App\Enums\FingerprintStatus;
use App\Enums\PaymentMethod;
use App\Enums\TicketStatus;
use App\Enums\VisaStatus;
use App\Models\Branch;
use App\Models\CurrencyRate;
use App\Models\FingerprintDetail;
use App\Models\FingerprintDetailLog;
use App\Models\Invoice;
use App\Models\IssuedTicket;
use App\Models\IssuedTicketLog;
use App\Models\Booking;
use App\Models\Passenger;
use App\Models\Payment;
use App\Services\CostTrackingService;
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
        $userBranchId = auth()->user()?->branch_id;
        $branchId = $userBranchId ?: $request->branch_id;
        $selectedBranch = $branchId;
        $branches = Branch::orderBy('name')->get(['id', 'name']);
        $firstRate = (float) (CurrencyRate::orderBy('created_at')->first()?->rate ?? 0);

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
        $invoiceRow = Invoice::whereDate('invoices.created_at', '>=', $dateFrom)
            ->whereDate('invoices.created_at', '<=', $dateTo)
            ->when($branchId, fn($q) => $q->where('invoices.branch_id', $branchId))
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

        $dueCollectionRow = Voucher::whereDate('vouchers.created_at', '>=', $dateFrom)
            ->whereDate('vouchers.created_at', '<=', $dateTo)
            ->when($branchId, fn($q) => $q->where('vouchers.branch_id', $branchId))
            ->whereHas('transactionType', fn($q) => $q->where('name', 'Due Collection'))
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

        $refundRow = Voucher::whereDate('vouchers.created_at', '>=', $dateFrom)
            ->whereDate('vouchers.created_at', '<=', $dateTo)
            ->whereHas('transactionType', fn($q) => $q->where('name', 'Customer Refund'))
            ->when($branchId, fn($q) => $q->whereHas('cancelledBooking', fn($cq) => $cq->where('cancellation_branch_id', $branchId)))
            ->leftJoin('bookings', 'vouchers.booking_id', '=', 'bookings.id')
            ->leftJoin('currency_rates', 'bookings.currency_rate_id', '=', 'currency_rates.id')
            ->selectRaw('
                SUM(vouchers.amount) as sar_total,
                SUM(vouchers.amount * COALESCE(currency_rates.rate, ?)) as bdt_total
            ', [$firstRate])
            ->first();
        $totalRefund = $refundRow->sar_total ?? 0;
        $totalRefundBdt = $refundRow->bdt_total ?? 0;

        $initialPaymentRow = Payment::whereDate('payments.created_at', '>=', $dateFrom)
            ->whereDate('payments.created_at', '<=', $dateTo)
            ->when($branchId, fn($q) => $q->where('payments.branch_id', $branchId))
            ->whereHas('vouchers.transactionType', fn($q) => $q->where('name', 'Initial Payment'))
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

        $profitBookings = Booking::with(['invoice', 'fingerprint', 'passengers.visaSubmission', 'passengers.allIssuedTickets'])
            ->where('is_cancelled', false)
            ->whereHas('invoice')
            ->whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo)
            ->when($branchId, fn($q) => $q->where('booking_branch_id', $branchId))
            ->get();
        $costService = app(CostTrackingService::class);
        $totalProfit = $profitBookings->sum(function (Booking $booking) use ($costService) {
            $costSummary = $costService->getBookingCostSummary($booking);
            return (float) $booking->invoice->total_amount - $costSummary['total_cost'];
        });
        $totalProfitBdt = $profitBookings->sum(function (Booking $booking) use ($costService, $firstRate) {
            $costSummary = $costService->getBookingCostSummary($booking);
            $profit = (float) $booking->invoice->total_amount - $costSummary['total_cost'];
            $rate = (float) ($booking->currencyRate?->rate ?? $firstRate);
            return $profit * $rate;
        });

        $totalPassengers = Passenger::whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo)
            ->when($branchId, fn($q) => $q->whereHas('booking', $branchScope))->count();

        $paymentRow = Payment::whereDate('payments.created_at', '>=', $dateFrom)
            ->whereDate('payments.created_at', '<=', $dateTo)
            ->when($branchId, fn($q) => $q->where('payments.branch_id', $branchId))
            ->whereHas('vouchers.transactionType', fn($q) => $q->whereIn('name', ['Initial Payment', 'Due Collection']))
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

        $payments = Payment::whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo)
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->whereHas('vouchers.transactionType', fn($q) => $q->whereIn('name', ['Initial Payment', 'Due Collection']))
            ->with(['branch', 'vouchers.transactionType', 'vouchers.user.branch', 'vouchers.booking', 'vouchers.currencyRate'])
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
                    'bdt_amount' => (float) ($v->bdt_amount ?: 0),
                    'currency_rate' => (float) ($v->currencyRate?->rate ?? $firstRate),
                    'payment_date' => $v->payment_date?->format('d-M-Y') ?? '',
                ];
            }
        }
        $vouchersByDateJson = json_encode($vouchersByDate);

        return view('reports.branch-wise', compact(
            'visaSubmitted', 'visaIssued', 'visaPending',
            'fingerprintApproved', 'fingerprintDone', 'fingerprintProcessing',
            'invoiceCount', 'invoiceTotalAmount', 'invoiceTotalAmountBdt',
            'inboundTicket', 'outboundTicket', 'pendingTicket',
            'totalDue', 'totalDueBdt', 'totalDueCollection', 'totalDueCollectionBdt', 'dueCollectionCash', 'dueCollectionCashBdt', 'dueCollectionBank', 'dueCollectionBankBdt', 'totalInitialPayment', 'totalInitialPaymentBdt', 'initialPaymentCash', 'initialPaymentCashBdt', 'initialPaymentBank', 'initialPaymentBankBdt', 'totalReceiving', 'totalReceivingBdt', 'receivingCash', 'receivingCashBdt', 'receivingBank', 'receivingBankBdt', 'totalPassengers', 'totalProfit', 'totalProfitBdt', 'totalRefund', 'totalRefundBdt',
            'totalCashPayment', 'totalCashPaymentBdt', 'totalBankPayment', 'totalBankPaymentBdt',
            'dateFrom', 'dateTo', 'selectedBranch', 'branches', 'userBranchId',
            'vouchersByDateJson', 'vouchersByDate'
        ));
    }

    public function paymentHistoryPrint(Request $request): View
    {
        $dateFrom = $request->date_from ? Carbon::parse($request->date_from) : now()->subDays(30);
        $dateTo = $request->date_to ? Carbon::parse($request->date_to) : now();
        $branchId = $request->branch_id;
        $currency = $request->get('currency', 'SAR');
        $branch = $branchId ? Branch::find($branchId) : null;
        $firstRate = (float) (CurrencyRate::orderBy('created_at')->first()?->rate ?? 0);

        $payments = Payment::whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo)
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->whereHas('vouchers.transactionType', fn($q) => $q->whereIn('name', ['Initial Payment', 'Due Collection']))
            ->with(['vouchers.transactionType', 'vouchers.user.branch', 'vouchers.booking', 'vouchers.currencyRate'])
            ->get();

        $vouchers = collect();
        foreach ($payments as $payment) {
            foreach ($payment->vouchers as $v) {
                if (!in_array($v->transactionType?->name, ['Initial Payment', 'Due Collection'])) {
                    continue;
                }
                $bdtAmount = (float) ($v->bdt_amount ?: 0);
                $vouchers->push([
                    'invoice_id' => $v->booking?->invoice_id ?? 'N/A',
                    'voucher_no' => $v->voucher_id ?? $v->id,
                    'method' => ucfirst($v->payment_method?->value ?? ''),
                    'transaction_type' => $v->transactionType?->name ?? '',
                    'trx_id' => $v->transaction_id ?? '-',
                    'receive_by' => $v->user?->name ?? '',
                    'receive_at' => $v->user?->branch?->name ?? 'Central',
                    'amount' => (float) $v->amount,
                    'bdt_amount' => $bdtAmount > 0 ? $bdtAmount : (float) $v->amount * $firstRate,
                    'currency_rate' => (float) ($v->currencyRate?->rate ?? $firstRate),
                    'payment_date' => $v->payment_date?->format('d-M-Y') ?? '',
                ]);
            }
        }

        $totalCash = $vouchers->where('method', 'Cash')->sum('amount');
        $totalCashBdt = $vouchers->where('method', 'Cash')->sum('bdt_amount');
        $totalBank = $vouchers->where('method', 'Bank')->sum('amount');
        $totalBankBdt = $vouchers->where('method', 'Bank')->sum('bdt_amount');
        $totalAmount = $vouchers->sum('amount');
        $totalAmountBdt = $vouchers->sum('bdt_amount');

        $dateLabel = $dateFrom->format('d-M-Y') . ' to ' . $dateTo->format('d-M-Y');

        return view('reports.branch-wise.payment-history-print', compact(
            'vouchers', 'totalCash', 'totalCashBdt', 'totalBank', 'totalBankBdt',
            'totalAmount', 'totalAmountBdt', 'currency', 'branch', 'dateLabel', 'dateFrom', 'dateTo'
        ));
    }
}
