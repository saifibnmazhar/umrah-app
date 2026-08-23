<?php

namespace App\Http\Controllers;

use App\Models\Bank;
use App\Models\Branch;
use App\Queries\BranchWiseReportQuery;
use App\Support\DateFormatter;
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

        $query = new BranchWiseReportQuery($dateFrom, $dateTo, $branchId);
        $summary = $query->summary();

        $vouchersByDate = $query->paymentHistory($dateFrom, $dateTo, $branchId);
        $vouchersByDateJson = json_encode($vouchersByDate);
        $banksJson = json_encode(Bank::orderBy('name')->get(['id', 'name']));

        return view('reports.branch-wise', [
            // Visa
            'visaSubmitted' => $summary['visaSubmitted'],
            'visaIssued' => $summary['visaIssued'],
            'visaPending' => $summary['visaPending'],
            // Fingerprint
            'fingerprintApproved' => $summary['fingerprintApproved'],
            'fingerprintDone' => $summary['fingerprintDone'],
            'fingerprintProcessing' => $summary['fingerprintProcessing'],
            // Invoices
            'invoiceCount' => $summary['invoiceCount'],
            'invoiceTotalAmount' => $summary['invoiceTotalAmount'],
            'invoiceTotalAmountBdt' => $summary['invoiceTotalAmountBdt'],
            // Tickets
            'inboundTicket' => $summary['inboundTicket'],
            'outboundTicket' => $summary['outboundTicket'],
            'pendingTicket' => $summary['pendingTicket'],
            // Payments
            'totalCashPayment' => $summary['totalCashPayment'],
            'totalCashPaymentBdt' => $summary['totalCashPaymentBdt'],
            'totalBankPayment' => $summary['totalBankPayment'],
            'totalBankPaymentBdt' => $summary['totalBankPaymentBdt'],
            'totalInitialPayment' => $summary['totalInitialPayment'],
            'totalInitialPaymentBdt' => $summary['totalInitialPaymentBdt'],
            'initialPaymentCash' => $summary['initialPaymentCash'],
            'initialPaymentCashBdt' => $summary['initialPaymentCashBdt'],
            'initialPaymentBank' => $summary['initialPaymentBank'],
            'initialPaymentBankBdt' => $summary['initialPaymentBankBdt'],
            'totalDueCollection' => $summary['totalDueCollection'],
            'totalDueCollectionBdt' => $summary['totalDueCollectionBdt'],
            'dueCollectionCash' => $summary['dueCollectionCash'],
            'dueCollectionCashBdt' => $summary['dueCollectionCashBdt'],
            'dueCollectionBank' => $summary['dueCollectionBank'],
            'dueCollectionBankBdt' => $summary['dueCollectionBankBdt'],
            'totalReceiving' => $summary['totalReceiving'],
            'totalReceivingBdt' => $summary['totalReceivingBdt'],
            'receivingCash' => $summary['receivingCash'],
            'receivingCashBdt' => $summary['receivingCashBdt'],
            'receivingBank' => $summary['receivingBank'],
            'receivingBankBdt' => $summary['receivingBankBdt'],
            // Profit
            'totalProfit' => $summary['totalProfit'],
            'totalProfitBdt' => $summary['totalProfitBdt'],
            // Refunds
            'totalRefund' => $summary['totalRefund'],
            'totalRefundBdt' => $summary['totalRefundBdt'],
            'totalTicketRefund' => $summary['totalTicketRefund'],
            'totalTicketRefundBdt' => $summary['totalTicketRefundBdt'],
            // Passengers
            'totalPassengers' => $summary['totalPassengers'],
            // Fingerprint profit + service charge deduction (dashboard extra cards)
            'totalFingerprintProfit' => $summary['totalFingerprintProfit'],
            'totalFingerprintProfitBdt' => $summary['totalFingerprintProfitBdt'],
            'totalServiceChargeDeduction' => $summary['totalServiceChargeDeduction'],
            'totalServiceChargeDeductionBdt' => $summary['totalServiceChargeDeductionBdt'],
            // Due
            'totalDue' => $summary['totalDue'],
            'totalDueBdt' => $summary['totalDueBdt'],
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'selectedBranch' => $selectedBranch,
            'branches' => $branches,
            'userBranchId' => $userBranchId,
            'vouchersByDateJson' => $vouchersByDateJson,
            'vouchersByDate' => $vouchersByDate,
            'banksJson' => $banksJson,
        ]);
    }

    public function paymentHistoryPrint(Request $request): View
    {
        $dateFrom = $request->date_from ? Carbon::parse($request->date_from) : now()->subDays(30);
        $dateTo = $request->date_to ? Carbon::parse($request->date_to) : now();
        $branchId = $request->branch_id;
        $bankId = $request->bank_id;
        $method = $request->method;
        $currency = $request->get('currency', 'SAR');
        $branch = $branchId === 'central'
            ? (object) ['name' => 'Central']
            : ($branchId ? Branch::find($branchId) : null);
        $bankName = $bankId === 'all' ? 'All Banks' : ($bankId ? Bank::find($bankId)?->name : null);

        $query = new BranchWiseReportQuery($dateFrom, $dateTo, $branchId);
        $vouchers = collect($query->paymentHistory($dateFrom, $dateTo, $branchId));

        if ($bankId === 'all') {
            $vouchers = $vouchers->where('method', 'Bank');
        } elseif ($bankId) {
            $vouchers = $vouchers->where('bank_id', (int) $bankId);
        }

        if ($method) {
            $vouchers = $vouchers->where('method', ucfirst(strtolower($method)));
        }

        $methodLabel = $method ? ucfirst(strtolower($method)) : null;
        $totalCash = $vouchers->where('method', 'Cash')->sum('amount');
        $totalCashBdt = $vouchers->where('method', 'Cash')->sum('bdt_amount');
        $totalBank = $vouchers->where('method', 'Bank')->sum('amount');
        $totalBankBdt = $vouchers->where('method', 'Bank')->sum('bdt_amount');
        $totalAmount = $vouchers->sum('amount');
        $totalAmountBdt = $vouchers->sum('bdt_amount');

        $dateLabel = DateFormatter::short($dateFrom).' to '.DateFormatter::short($dateTo);

        return view('reports.branch-wise.payment-history-print', [
            'vouchers' => $vouchers,
            'totalCash' => $totalCash,
            'totalCashBdt' => $totalCashBdt,
            'totalBank' => $totalBank,
            'totalBankBdt' => $totalBankBdt,
            'totalAmount' => $totalAmount,
            'totalAmountBdt' => $totalAmountBdt,
            'currency' => $currency,
            'branch' => $branch,
            'bankName' => $bankName,
            'methodLabel' => $methodLabel,
            'dateLabel' => $dateLabel,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
        ]);
    }
}
