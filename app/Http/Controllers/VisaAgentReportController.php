<?php

namespace App\Http\Controllers;

use App\Models\VisaAgent;
use App\Models\VisaSubmission;
use App\Queries\VisaAgentReportQuery;
use App\Services\CurrencyRateService;
use App\Support\DateFormatter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
            'visa_agent_id' => 'nullable|integer|exists:visa_agents,id',
        ]);

        $query = VisaAgent::query()
            ->when($request->search, fn ($q) => $q->where('name', 'like', "%{$request->search}%"))
            ->when($request->visa_agent_id, fn ($q) => $q->where('id', $request->visa_agent_id))
            ->orderBy('name')
            ->get();

        $reportQuery = new VisaAgentReportQuery;
        $reportData = [];
        $summaryTotals = [
            'totalPayable' => 0,
            'totalPaid' => 0,
            'totalCancellationFee' => 0,
            'agentsWithDue' => 0,
        ];

        foreach ($query as $agent) {
            $row = $reportQuery->buildAgentRow($agent, $request->date_from, $request->date_to);
            $reportData[] = $row;
            $summaryTotals['totalPayable'] += $row['payable'];
            $summaryTotals['totalPaid'] += $row['paid'];
            $summaryTotals['totalCancellationFee'] += $row['cancellationFee'];
            if ($row['balance'] < 0) {
                $summaryTotals['agentsWithDue']++;
            }
        }

        $totalBalance = $summaryTotals['totalPaid'] - $summaryTotals['totalPayable'] - $summaryTotals['totalCancellationFee'];

        return response()->json([
            'data' => $reportData,
            'summary' => [
                'totalAgents' => count($reportData),
                'agentsWithDue' => $summaryTotals['agentsWithDue'],
                'totalPayable' => $summaryTotals['totalPayable'],
                'totalPaid' => $summaryTotals['totalPaid'],
                'totalBalance' => $totalBalance,
                'totalBalanceLabel' => number_format(abs($totalBalance), 2).' SAR',
                'totalCancellationFee' => $summaryTotals['totalCancellationFee'],
            ],
        ]);
    }

    public function combined(Request $request, VisaAgent $visaAgent): JsonResponse
    {
        $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
        ]);

        $reportQuery = new VisaAgentReportQuery;
        $rows = $reportQuery->buildCombinedRows($visaAgent, $request->date_from, $request->date_to);
        $stats = $reportQuery->buildAgentRow($visaAgent, $request->date_from, $request->date_to);
        $stats['estimatedCost'] = (float) $rows->sum('estimated_cost');

        return response()->json([
            'data' => $rows,
            'agent' => [
                'id' => $visaAgent->id,
                'name' => $visaAgent->name,
            ],
            'stats' => $stats,
        ]);
    }

    public function printReport(Request $request, VisaAgent $visaAgent): View
    {
        $rows = (new VisaAgentReportQuery)->buildCombinedRows($visaAgent);
        $currencyRate = (float) (app(CurrencyRateService::class)->getRateForDate(now())?->rate ?? 0);

        return view('reports.visa-agent-print', compact('visaAgent', 'rows', 'currencyRate'));
    }

    public function logs(VisaAgent $visaAgent): JsonResponse
    {
        $transactions = (new VisaAgentReportQuery)->transactions($visaAgent);

        return response()->json([
            'data' => $transactions,
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
            ->with([
                'passenger.booking',
                'logs' => fn ($q) => $q
                    ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(new_values, '$.status')) = 'submitted'")
                    ->latest('created_at')
                    ->limit(1),
            ])
            ->get()
            ->map(function ($submission) {
                $submissionLog = $submission->logs->first();

                return [
                    'invoice_id' => $submission->passenger->booking->invoice_id ?? '-',
                    'passenger_name' => trim(($submission->passenger->first_name ?? '').' '.($submission->passenger->last_name ?? '')),
                    'passport_no' => $submission->passenger->passport_no ?? '-',
                    'submission_date' => $submissionLog ? DateFormatter::short($submissionLog->created_at) : '-',
                ];
            });

        return response()->json(['data' => $submissions]);
    }

    public function issued(VisaAgent $visaAgent): JsonResponse
    {
        $submissions = VisaSubmission::where('visa_agent_id', $visaAgent->id)
            ->where('status', 'issued')
            ->with([
                'passenger.booking',
                'logs' => fn ($q) => $q
                    ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(new_values, '$.status')) = 'issued'")
                    ->latest('created_at')
                    ->limit(1),
            ])
            ->get()
            ->map(function ($submission) {
                $issueLog = $submission->logs->first();

                return [
                    'invoice_id' => $submission->passenger->booking->invoice_id ?? '-',
                    'passenger_name' => trim(($submission->passenger->first_name ?? '').' '.($submission->passenger->last_name ?? '')),
                    'passport_no' => $submission->passenger->passport_no ?? '-',
                    'issue_date' => $issueLog ? DateFormatter::short($issueLog->created_at) : '-',
                ];
            });

        return response()->json(['data' => $submissions]);
    }
}
