<?php

namespace App\Http\Controllers;

use App\Enums\FingerprintStatus;
use App\Enums\PaymentMethod;
use App\Enums\TicketStatus;
use App\Enums\VisaStatus;
use App\Models\CurrencyRate;
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
        $firstRate = (float) (CurrencyRate::orderBy('created_at')->first()?->rate ?? 0);
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

        $fingerprintProfitRow = \App\Models\Fingerprint::query()
            ->leftJoin('bookings', 'fingerprints.booking_id', '=', 'bookings.id')
            ->leftJoin('fingerprint_charges', 'bookings.fingerprint_charge_id', '=', 'fingerprint_charges.id')
            ->leftJoin('invoices', 'bookings.id', '=', 'invoices.booking_id')
            ->leftJoin('currency_rates', 'bookings.currency_rate_id', '=', 'currency_rates.id')
            ->where('invoices.created_at', '>=', now()->subDays(30))
            ->when($branchId, fn($q) => $q->where('bookings.booking_branch_id', $branchId))
            ->selectRaw('
                SUM(COALESCE(fingerprint_charges.fingerprint_charge, 0)) - SUM(COALESCE(fingerprints.cost, 0)) as sar_profit,
                SUM(COALESCE(fingerprint_charges.fingerprint_charge, 0) * COALESCE(currency_rates.rate, ?)) - SUM(COALESCE(fingerprints.cost, 0) * COALESCE(currency_rates.rate, ?)) as bdt_profit
            ', [$firstRate, $firstRate])
            ->first();
        $totalFingerprintProfit = $fingerprintProfitRow->sar_profit ?? 0;
        $totalFingerprintProfitBdt = $fingerprintProfitRow->bdt_profit ?? 0;

        $invoiceCount = Invoice::where('created_at', '>=', now()->subDays(30))
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))->count();
        $invoiceRow = Invoice::where('invoices.created_at', '>=', now()->subDays(30))
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

        $dueCollectionRow = Voucher::where('vouchers.created_at', '>=', now()->subDays(30))
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

        $totalPassengers = Passenger::where('created_at', '>=', now()->subDays(30))
            ->when($branchId, fn($q) => $q->whereHas('booking', $branchScope))->count();

        $initialPaymentRow = Payment::where('payments.created_at', '>=', now()->subDays(30))
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

        $paymentRow = Payment::where('payments.created_at', '>=', now()->subDays(30))
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

        return view('dashboard.index', compact('packages', 'visaSubmitted', 'visaIssued', 'visaPending', 'fingerprintApproved', 'fingerprintDone', 'fingerprintProcessing', 'totalFingerprintProfit', 'totalFingerprintProfitBdt', 'invoiceCount', 'invoiceTotalAmount', 'invoiceTotalAmountBdt', 'inboundTicket', 'outboundTicket', 'pendingTicket', 'totalDue', 'totalDueBdt', 'totalDueCollection', 'totalDueCollectionBdt', 'dueCollectionCash', 'dueCollectionCashBdt', 'dueCollectionBank', 'dueCollectionBankBdt', 'totalPassengers', 'totalInitialPayment', 'totalInitialPaymentBdt', 'initialPaymentCash', 'initialPaymentCashBdt', 'initialPaymentBank', 'initialPaymentBankBdt', 'totalCashPayment', 'totalCashPaymentBdt', 'totalBankPayment', 'totalBankPaymentBdt'));
    }
}
