<?php

namespace App\Http\Controllers;

use App\Enums\InvoiceStatus;
use App\Models\Branch;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\CurrencyRateService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DueReportController extends Controller
{
    public function index()
    {
        $branches = Branch::orderBy('name')->get(['id', 'name']);

        return view('reports.due', compact('branches'));
    }

    public function data(Request $request)
    {
        $query = Invoice::select(
            'branches.id',
            'branches.name',
            DB::raw('COALESCE(SUM(invoices.balance), 0) as total_due'),
            DB::raw('COUNT(*) as invoice_count')
        )
            ->join('branches', 'invoices.branch_id', '=', 'branches.id')
            ->where('invoices.balance', '>', 0)
            ->whereNotIn('invoices.status', [
            InvoiceStatus::PAID->value,
            InvoiceStatus::CANCELLED->value,
            InvoiceStatus::REFUNDED->value,
        ])
            ->groupBy('branches.id', 'branches.name')
            ->orderBy('branches.name');

        if ($request->filled('date_from')) {
            $query->whereDate('invoices.created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('invoices.created_at', '<=', $request->date_to);
        }
        if ($request->filled('branch_id')) {
            $query->where('invoices.branch_id', $request->branch_id);
        }

        $branches = $query->get()->map(function ($item) {
            return [
                'id' => (int) $item->id,
                'name' => $item->name,
                'totalDue' => (float) $item->total_due,
            ];
        });

        return response()->json(['branches' => $branches]);
    }

    public function branchDetails(Request $request, $branchId)
    {
        $dateFrom = $request->date_from;
        $dateTo = $request->date_to;

        $customers = Invoice::with(['booking.customer', 'booking.passengers'])
            ->select(
                'invoices.id',
                'invoices.booking_id',
                'invoices.total_amount',
                'invoices.paid_amount',
                'invoices.balance',
                'invoices.created_at'
            )
            ->where('invoices.branch_id', $branchId)
            ->where('invoices.balance', '>', 0)
            ->whereNotIn('invoices.status', [
                InvoiceStatus::PAID->value,
                InvoiceStatus::CANCELLED->value,
                InvoiceStatus::REFUNDED->value,
            ])
            ->when($dateFrom, fn ($q) => $q->whereDate('invoices.created_at', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->whereDate('invoices.created_at', '<=', $dateTo))
            ->orderBy('invoices.created_at', 'desc')
            ->get()
            ->map(function ($invoice) {
                $customer = $invoice->booking?->customer;
                $passengers = $invoice->booking->passengers ?? collect();
                $earliest = $passengers->pluck('actual_flight_date')->filter()->min();

                return [
                    'id' => (int) $invoice->id,
                    'name' => $customer->name ?? 'Unknown',
                    'mobile' => $customer->mobile_no ?? '',
                    'invoiceId' => $invoice->booking->invoice_id ?? 'N/A',
                    'ticketDate' => $earliest ? date('d-M-Y', strtotime($earliest)) : 'N/A',
                    'totalPackage' => (float) $invoice->total_amount,
                    'paid' => (float) $invoice->paid_amount,
                    'due' => (float) $invoice->balance,
                ];
            })->values();

        $totalBranchBalance = Invoice::where('branch_id', $branchId)
            ->where('balance', '>', 0)
            ->whereNotIn('status', [
                InvoiceStatus::PAID->value,
                InvoiceStatus::CANCELLED->value,
                InvoiceStatus::REFUNDED->value,
            ])
            ->when($dateFrom, fn ($q) => $q->whereDate('created_at', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->whereDate('created_at', '<=', $dateTo))
            ->sum('balance');

        $dateWiseRows = Payment::select(
            DB::raw('DATE(payments.payment_date) as date'),
            DB::raw("COALESCE(SUM(CASE WHEN payments.payment_method = 'cash' THEN payments.amount ELSE 0 END), 0) as cash"),
            DB::raw("COALESCE(SUM(CASE WHEN payments.payment_method = 'bank' THEN payments.amount ELSE 0 END), 0) as bank"),
            DB::raw('COALESCE(SUM(payments.amount), 0) as total_collected')
        )
            ->join('invoices', 'payments.invoice_id', '=', 'invoices.id')
            ->where('invoices.branch_id', $branchId)
            ->where('invoices.balance', '>', 0)
            ->whereNotIn('invoices.status', [
            InvoiceStatus::PAID->value,
            InvoiceStatus::CANCELLED->value,
            InvoiceStatus::REFUNDED->value,
        ])
            ->when($dateFrom, fn ($q) => $q->whereDate('invoices.created_at', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->whereDate('invoices.created_at', '<=', $dateTo))
            ->groupBy(DB::raw('DATE(payments.payment_date)'))
            ->orderBy('date')
            ->get();

        $runningDue = (float) $totalBranchBalance;
        $dateWiseData = $dateWiseRows->map(function ($row) use (&$runningDue) {
            $rowDue = $runningDue;
            $runningDue -= (float) $row->total_collected;

            return [
                'date' => date('d-M-Y', strtotime($row->date)),
                'due' => max(0, $rowDue),
                'cash' => (float) $row->cash,
                'bank' => (float) $row->bank,
                'totalCollected' => (float) $row->total_collected,
                'newDue' => max(0, $runningDue),
            ];
        });

        return response()->json([
            'customers' => $customers,
            'dateWiseData' => $dateWiseData,
        ]);
    }

    public function printCustomers(Request $request, $branchId)
    {
        $branch = Branch::findOrFail($branchId);
        $dateFrom = $request->date_from;
        $dateTo = $request->date_to;
        $currency = $request->get('currency', 'SAR');
        $rate = app(CurrencyRateService::class)->getCurrentRateValue();

        $customers = Invoice::with(['booking.customer', 'booking.passengers'])
            ->select(
                'invoices.id',
                'invoices.booking_id',
                'invoices.total_amount',
                'invoices.paid_amount',
                'invoices.balance',
                'invoices.created_at'
            )
            ->where('invoices.branch_id', $branchId)
            ->where('invoices.balance', '>', 0)
            ->whereNotIn('invoices.status', [
                InvoiceStatus::PAID->value,
                InvoiceStatus::CANCELLED->value,
                InvoiceStatus::REFUNDED->value,
            ])
            ->when($dateFrom, fn ($q) => $q->whereDate('invoices.created_at', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->whereDate('invoices.created_at', '<=', $dateTo))
            ->orderBy('invoices.created_at', 'desc')
            ->get()
            ->map(function ($invoice) {
                $customer = $invoice->booking?->customer;
                $passengers = $invoice->booking->passengers ?? collect();
                $earliest = $passengers->pluck('actual_flight_date')->filter()->min();

                return [
                    'name' => $customer->name ?? 'Unknown',
                    'mobile' => $customer->mobile_no ?? '',
                    'invoiceId' => $invoice->booking->invoice_id ?? 'N/A',
                    'ticketDate' => $earliest ? date('d-M-Y', strtotime($earliest)) : 'N/A',
                    'totalPackage' => (float) $invoice->total_amount,
                    'paid' => (float) $invoice->paid_amount,
                    'due' => (float) $invoice->balance,
                ];
            })->values()->toArray();

        return view('reports.due-print-customers', compact('customers', 'branch', 'dateFrom', 'dateTo', 'currency', 'rate'));
    }

    public function printDateWise(Request $request, $branchId)
    {
        $branch = Branch::findOrFail($branchId);
        $dateFrom = $request->date_from;
        $dateTo = $request->date_to;
        $currency = $request->get('currency', 'SAR');
        $rate = app(CurrencyRateService::class)->getCurrentRateValue();

        $totalBranchBalance = Invoice::where('branch_id', $branchId)
            ->where('balance', '>', 0)
            ->whereNotIn('status', [
                InvoiceStatus::PAID->value,
                InvoiceStatus::CANCELLED->value,
                InvoiceStatus::REFUNDED->value,
            ])
            ->when($dateFrom, fn ($q) => $q->whereDate('created_at', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->whereDate('created_at', '<=', $dateTo))
            ->sum('balance');

        $dateWiseRows = Payment::select(
            DB::raw('DATE(payments.payment_date) as date'),
            DB::raw("COALESCE(SUM(CASE WHEN payments.payment_method = 'cash' THEN payments.amount ELSE 0 END), 0) as cash"),
            DB::raw("COALESCE(SUM(CASE WHEN payments.payment_method = 'bank' THEN payments.amount ELSE 0 END), 0) as bank"),
            DB::raw('COALESCE(SUM(payments.amount), 0) as total_collected')
        )
            ->join('invoices', 'payments.invoice_id', '=', 'invoices.id')
            ->where('invoices.branch_id', $branchId)
            ->where('invoices.balance', '>', 0)
            ->whereNotIn('invoices.status', [
            InvoiceStatus::PAID->value,
            InvoiceStatus::CANCELLED->value,
            InvoiceStatus::REFUNDED->value,
        ])
            ->when($dateFrom, fn ($q) => $q->whereDate('invoices.created_at', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->whereDate('invoices.created_at', '<=', $dateTo))
            ->groupBy(DB::raw('DATE(payments.payment_date)'))
            ->orderBy('date')
            ->get();

        $runningDue = (float) $totalBranchBalance;
        $dateWiseData = $dateWiseRows->map(function ($row) use (&$runningDue) {
            $rowDue = $runningDue;
            $runningDue -= (float) $row->total_collected;

            return [
                'date' => date('d-M-Y', strtotime($row->date)),
                'due' => max(0, $rowDue),
                'cash' => (float) $row->cash,
                'bank' => (float) $row->bank,
                'totalCollected' => (float) $row->total_collected,
                'newDue' => max(0, $runningDue),
            ];
        })->toArray();

        return view('reports.due-print-datewise', compact('dateWiseData', 'branch', 'dateFrom', 'dateTo', 'currency', 'rate'));
    }

    public function customerTransactions(Request $request, $invoiceId)
    {
        $invoice = Invoice::with(['booking.customer', 'booking.passengers'])->findOrFail($invoiceId);

        $dateFrom = $request->date_from;
        $dateTo = $request->date_to;

        $payments = Payment::where('invoice_id', $invoiceId)
            ->when($dateFrom, fn ($q) => $q->whereDate('payment_date', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->whereDate('payment_date', '<=', $dateTo))
            ->orderBy('payment_date')
            ->orderBy('created_at')
            ->get();

        $runningDue = (float) $invoice->total_amount;
        $transactions = $payments->map(function ($payment) use (&$runningDue) {
            $dueBefore = $runningDue;
            $runningDue -= (float) $payment->amount;

            return [
                'date' => $payment->payment_date ? date('d-M-Y', strtotime($payment->payment_date)) : '',
                'due' => max(0, $dueBefore),
                'paid' => (float) $payment->amount,
                'method' => ucfirst($payment->payment_method?->value ?? 'cash'),
                'trxId' => $payment->transaction_id ?? '-',
                'newDue' => max(0, $runningDue),
            ];
        });

        $customer = $invoice->booking?->customer;

        $passengers = $invoice->booking->passengers ?? collect();
        $earliest = $passengers->pluck('actual_flight_date')->filter()->min();

        return response()->json([
            'transactions' => $transactions,
            'customer' => [
                'name' => $customer->name ?? 'Unknown',
                'mobile' => $customer->mobile_no ?? '',
                'invoiceId' => $invoice->booking->invoice_id ?? 'N/A',
                'ticketDate' => $earliest ? date('d-M-Y', strtotime($earliest)) : 'N/A',
                'totalPackage' => (float) $invoice->total_amount,
                'paid' => (float) $invoice->paid_amount,
                'due' => (float) $invoice->balance,
            ],
        ]);
    }
}
