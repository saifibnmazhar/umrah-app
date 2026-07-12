<?php

namespace App\Http\Controllers;

use App\Models\VisaAgent;
use App\Models\VisaSubmission;
use App\Models\CancelledSubmission;
use App\Models\VisaUpdateLog;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class VisaAgentReportController extends Controller
{
    public function index(Request $request): View
    {
        $visaAgents = VisaAgent::orderBy('name')->get(['id', 'name']);

        return view('reports.visa-agent', compact('visaAgents'));
    }

    public function data(Request $request): JsonResponse
    {
        $request->validate([
            'search' => 'nullable|string|max:255',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
        ]);

        $search = $request->search;
        $dateFrom = $request->date_from;
        $dateTo = $request->date_to;

        $agents = VisaAgent::query()
            ->when($search, fn($q) => $q->where('name', 'like', "%{$search}%"))
            ->orderBy('name')
            ->get();

        $reportData = [];
        $summaryTotals = [
            'totalPayable' => 0,
            'totalPaid' => 0,
            'totalCancellationFee' => 0,
            'agentsWithDue' => 0,
        ];

        foreach ($agents as $agent) {
            $row = $this->buildAgentRow($agent, $dateFrom, $dateTo);
            $reportData[] = $row;

            $summaryTotals['totalPayable'] += $row['payable'];
            $summaryTotals['totalPaid'] += $row['paid'];
            $summaryTotals['totalCancellationFee'] += $row['cancellationFee'];
            if ($row['balance'] > 0) {
                $summaryTotals['agentsWithDue']++;
            }
        }

        $totalBalance = $summaryTotals['totalPayable'] - $summaryTotals['totalPaid'];

        return response()->json([
            'data' => $reportData,
            'summary' => [
                'totalAgents' => count($reportData),
                'agentsWithDue' => $summaryTotals['agentsWithDue'],
                'totalPayable' => $summaryTotals['totalPayable'],
                'totalPaid' => $summaryTotals['totalPaid'],
                'totalBalance' => $totalBalance,
                'totalBalanceLabel' => number_format(abs($totalBalance), 2) . ' SAR',
                'totalCancellationFee' => $summaryTotals['totalCancellationFee'],
            ],
        ]);
    }

    public function logs(VisaAgent $visaAgent): JsonResponse
    {
        $submissionIds = VisaSubmission::where('visa_agent_id', $visaAgent->id)->pluck('id');

        $logs = VisaUpdateLog::whereIn('visa_submission_id', $submissionIds)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($log) {
                $newValues = $log->new_values ?? [];
                $status = $newValues['status'] ?? null;
                $netVisaCost = (float) ($newValues['net_visa_cost'] ?? 0);

                $cancellationFee = 0;
                if ($status === 'cancelled') {
                    $cs = CancelledSubmission::where('visa_submission_id', $log->visa_submission_id)->first();
                    $cancellationFee = (float) ($cs->cancellation_fee ?? 0);
                }

                return [
                    'date' => $log->created_at->format('d-M-Y'),
                    'status' => $status,
                    'payable' => $netVisaCost,
                    'paid' => 0,
                    'balance' => 0,
                    'cancellationFee' => $cancellationFee,
                ];
            });

        $runningPayable = 0;
        $runningPaid = 0;
        $logs = $logs->reverse()->values()->map(function ($item) use (&$runningPayable, &$runningPaid) {
            $runningPayable += $item['payable'];
            $runningPaid += $item['paid'];
            $item['balance'] = $runningPayable - $runningPaid;
            return $item;
        })->reverse()->values();

        return response()->json([
            'data' => $logs,
            'agent' => [
                'id' => $visaAgent->id,
                'name' => $visaAgent->name,
            ],
        ]);
    }

    public function submissions(VisaAgent $visaAgent): JsonResponse
    {
        $submissions = VisaSubmission::where('visa_agent_id', $visaAgent->id)
            ->where('status', 'submitted')
            ->with('passenger.booking')
            ->get()
            ->map(function ($submission) {
                $submissionLog = $submission->logs()
                    ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(new_values, '$.status')) = 'submitted'")
                    ->latest()
                    ->first();

                return [
                    'invoice_id'      => $submission->passenger->booking->invoice_id ?? '-',
                    'passenger_name'  => trim(($submission->passenger->first_name ?? '') . ' ' . ($submission->passenger->last_name ?? '')),
                    'passport_no'     => $submission->passenger->passport_no ?? '-',
                    'submission_date' => $submissionLog ? $submissionLog->created_at->format('d-M-Y') : '-',
                ];
            });

        return response()->json(['data' => $submissions]);
    }

    protected function buildAgentRow(VisaAgent $agent, ?string $dateFrom, ?string $dateTo): array
    {
        $agentId = $agent->id;

        // --- Total Submitted: submissions with a log where new_values.status = "submitted" in date range ---
        $submittedQuery = VisaSubmission::where('visa_agent_id', $agentId)
            ->whereHas('logs', function ($q) use ($dateFrom, $dateTo) {
                $q->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(new_values, '$.status')) = 'submitted'");
                if ($dateFrom) $q->whereDate('created_at', '>=', $dateFrom);
                if ($dateTo) $q->whereDate('created_at', '<=', $dateTo);
            });
        $totalSubmitted = $submittedQuery->count();

        // --- Total Issued + Payable: submissions with a log where new_values.status = "issued" in date range ---
        $issuedQuery = VisaSubmission::where('visa_agent_id', $agentId)
            ->where('status', 'issued')
            ->whereHas('logs', function ($q) use ($dateFrom, $dateTo) {
                $q->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(new_values, '$.status')) = 'issued'");
                if ($dateFrom) $q->whereDate('created_at', '>=', $dateFrom);
                if ($dateTo) $q->whereDate('created_at', '<=', $dateTo);
            });

        $totalIssued = $issuedQuery->count();
        $payable = (float) $issuedQuery->sum('net_visa_cost');

        // --- Price (Max/Min/Avg): from issued submissions in date range ---
        $price = (object) ['max' => 0, 'min' => 0, 'avg' => 0];
        $priceStats = (clone $issuedQuery)
            ->whereNotNull('visa_selling_price_id')
            ->join('visa_selling_prices', 'visa_submissions.visa_selling_price_id', '=', 'visa_selling_prices.id')
            ->selectRaw('MAX(visa_selling_prices.selling_price) as max_price')
            ->selectRaw('MIN(visa_selling_prices.selling_price) as min_price')
            ->selectRaw('AVG(visa_selling_prices.selling_price) as avg_price')
            ->first();
        if ($priceStats && $priceStats->max_price) {
            $price->max = (float) $priceStats->max_price;
            $price->min = (float) ($priceStats->min_price ?? 0);
            $price->avg = (float) ($priceStats->avg_price ?? 0);
        }

        // --- Cancellation Fee ---
        $cancelledQuery = CancelledSubmission::where('visa_agent_id', $agentId)
            ->when($dateFrom, fn($q) => $q->whereDate('created_at', '>=', $dateFrom))
            ->when($dateTo, fn($q) => $q->whereDate('created_at', '<=', $dateTo));
        $cancellationFee = (float) $cancelledQuery->sum('cancellation_fee');

        // --- Paid: Visa Agent Payment type payments ---
        $paidQuery = Payment::where('visa_agent_id', $agentId)
            ->whereHas('voucher.transactionType', fn($q) => $q->where('name', 'Visa Agent Payment'))
            ->when($dateFrom, fn($q) => $q->whereDate('payment_date', '>=', $dateFrom))
            ->when($dateTo, fn($q) => $q->whereDate('payment_date', '<=', $dateTo));
        $paid = (float) $paidQuery->sum('amount');

        $balance = $payable - $paid;

        return [
            'id' => $agent->id,
            'name' => $agent->name,
            'totalSubmitted' => $totalSubmitted,
            'totalIssued' => $totalIssued,
            'price' => [
                'max' => $price->max,
                'min' => $price->min,
                'avg' => $price->avg,
            ],
            'payable' => $payable,
            'paid' => $paid,
            'balance' => $balance,
            'cancellationFee' => $cancellationFee,
        ];
    }
}
