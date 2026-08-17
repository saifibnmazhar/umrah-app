<?php

namespace App\Http\Controllers;

use App\Models\CancelledSubmission;
use App\Models\Payment;
use App\Models\VisaAgent;
use App\Models\VisaSubmission;
use App\Services\CurrencyRateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
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

        $search = $request->search;
        $dateFrom = $request->date_from;
        $dateTo = $request->date_to;

        $agents = VisaAgent::query()
            ->when($search, fn ($q) => $q->where('name', 'like', "%{$search}%"))
            ->when($request->visa_agent_id, fn ($q) => $q->where('id', $request->visa_agent_id))
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

        $rows = $this->buildCombinedRows($visaAgent, $request->date_from, $request->date_to);
        $stats = $this->buildAgentRow($visaAgent, $request->date_from, $request->date_to);
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
        $rows = $this->buildCombinedRows($visaAgent);
        $currencyRate = (float) (app(CurrencyRateService::class)->getRateForDate(now())?->rate ?? 0);

        return view('reports.visa-agent-print', compact('visaAgent', 'rows', 'currencyRate'));
    }

    protected function buildCombinedRows(VisaAgent $visaAgent, ?string $dateFrom = null, ?string $dateTo = null): Collection
    {
        $agentId = $visaAgent->id;
        $rows = collect();

        $submittedSubmissions = VisaSubmission::where('visa_agent_id', $agentId)
            ->where('status', 'submitted')
            ->with('passenger.booking')
            ->get();

        foreach ($submittedSubmissions as $submission) {
            $submissionLog = $submission->logs()
                ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(new_values, '$.status')) = 'submitted'")
                ->latest()
                ->first();

            $newValues = $submissionLog ? $submissionLog->new_values : [];
            $estimatedCost = (float) ($newValues['net_visa_cost'] ?? $submission->net_visa_cost ?? 0);

            $rowDate = $submissionLog ? $submissionLog->created_at : $submission->created_at;
            $rows->push([
                'date' => $rowDate->format('d-M-Y'),
                'sort_date' => $rowDate->format('Y-m-d'),
                'invoice_id' => $submission->passenger->booking->invoice_id ?? '-',
                'passenger_name' => trim(($submission->passenger->first_name ?? '').' '.($submission->passenger->last_name ?? '')),
                'passport_no' => $submission->passenger->passport_no ?? '-',
                'status' => 'Submitted',
                'estimated_cost' => $estimatedCost,
                'payable' => 0,
                'paid' => 0,
                'balance' => 0,
                'cancellation_fee' => 0,
            ]);
        }

        $issuedSubmissions = VisaSubmission::where('visa_agent_id', $agentId)
            ->where('status', 'issued')
            ->with('passenger.booking')
            ->get();

        foreach ($issuedSubmissions as $submission) {
            $issueLog = $submission->logs()
                ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(new_values, '$.status')) = 'issued'")
                ->latest()
                ->first();

            $rowDate = $issueLog ? $issueLog->created_at : $submission->updated_at;
            $rows->push([
                'date' => $rowDate->format('d-M-Y'),
                'sort_date' => $rowDate->format('Y-m-d'),
                'invoice_id' => $submission->passenger->booking->invoice_id ?? '-',
                'passenger_name' => trim(($submission->passenger->first_name ?? '').' '.($submission->passenger->last_name ?? '')),
                'passport_no' => $submission->passenger->passport_no ?? '-',
                'status' => 'Issued',
                'estimated_cost' => 0,
                'payable' => (float) ($submission->net_visa_cost ?? 0) + (float) ($submission->additional_cost ?? 0),
                'paid' => 0,
                'balance' => 0,
                'cancellation_fee' => 0,
            ]);
        }

        $cancelledSubmissions = CancelledSubmission::where('visa_agent_id', $agentId)
            ->with('visaSubmission.passenger.booking')
            ->get();

        foreach ($cancelledSubmissions as $cs) {
            $invoiceId = '-';
            $passengerName = '-';
            $passportNo = '-';

            if ($cs->visaSubmission && $cs->visaSubmission->passenger) {
                $passenger = $cs->visaSubmission->passenger;
                $invoiceId = $passenger->booking->invoice_id ?? '-';
                $passengerName = trim(($passenger->first_name ?? '').' '.($passenger->last_name ?? ''));
                $passportNo = $passenger->passport_no ?? '-';
            }

            $rows->push([
                'date' => $cs->created_at->format('d-M-Y'),
                'sort_date' => $cs->created_at->format('Y-m-d'),
                'invoice_id' => $invoiceId,
                'passenger_name' => $passengerName,
                'passport_no' => $passportNo,
                'status' => 'Cancelled',
                'estimated_cost' => 0,
                'payable' => 0,
                'paid' => 0,
                'balance' => 0,
                'cancellation_fee' => (float) ($cs->cancellation_fee ?? 0),
            ]);
        }

        $payments = Payment::where('visa_agent_id', $agentId)
            ->whereHas('voucher.transactionType', fn ($q) => $q->where('name', 'Visa Agent Payment'))
            ->get();

        foreach ($payments as $payment) {
            $rows->push([
                'date' => $payment->payment_date->format('d-M-Y'),
                'sort_date' => $payment->payment_date->format('Y-m-d'),
                'invoice_id' => null,
                'passenger_name' => null,
                'passport_no' => null,
                'status' => 'Payment',
                'estimated_cost' => 0,
                'payable' => 0,
                'paid' => (float) ($payment->amount ?? 0),
                'balance' => 0,
                'cancellation_fee' => 0,
            ]);
        }

        $rows = $rows->sortBy('sort_date')->values();

        if ($dateFrom || $dateTo) {
            $rows = $rows->filter(function ($item) use ($dateFrom, $dateTo) {
                $itemDate = Carbon::parse($item['date']);
                if ($dateFrom && $itemDate->lt(Carbon::parse($dateFrom))) {
                    return false;
                }
                if ($dateTo && $itemDate->gt(Carbon::parse($dateTo))) {
                    return false;
                }

                return true;
            })->values();
        }

        $runningPayable = 0;
        $runningPaid = 0;
        $runningFee = 0;
        $rows = $rows->map(function ($item) use (&$runningPayable, &$runningPaid, &$runningFee) {
            $runningPayable += $item['payable'];
            $runningPaid += $item['paid'];
            $runningFee += $item['cancellation_fee'];
            $item['balance'] = $runningPaid - $runningPayable - $runningFee;

            return $item;
        });

        $rows = $rows->map(function ($item) {
            return collect($item)->forget('sort_date')->all();
        });

        return $rows;
    }

    public function logs(VisaAgent $visaAgent): JsonResponse
    {
        $agentId = $visaAgent->id;

        $issuedSubmissions = VisaSubmission::where('visa_agent_id', $agentId)
            ->where('status', 'issued')
            ->with(['logs' => fn ($q) => $q
                ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(new_values, '$.status')) = 'issued'")
                ->latest('created_at')
                ->limit(1),
            ])
            ->get();

        $transactions = collect();

        foreach ($issuedSubmissions as $submission) {
            $issueLog = $submission->logs->first();
            $transactions->push([
                'date' => $issueLog ? $issueLog->created_at->format('d-M-Y') : $submission->updated_at->format('d-M-Y'),
                'status' => 'issued',
                'payable' => (float) ($submission->net_visa_cost ?? 0) + (float) ($submission->additional_cost ?? 0),
                'paid' => 0,
                'balance' => 0,
                'cancellationFee' => 0,
            ]);
        }

        $cancelledSubmissions = CancelledSubmission::where('visa_agent_id', $agentId)->get();

        foreach ($cancelledSubmissions as $cs) {
            $transactions->push([
                'date' => $cs->created_at->format('d-M-Y'),
                'status' => 'cancelled',
                'payable' => 0,
                'paid' => 0,
                'balance' => 0,
                'cancellationFee' => (float) ($cs->cancellation_fee ?? 0),
            ]);
        }

        $payments = Payment::where('visa_agent_id', $agentId)
            ->whereHas('voucher.transactionType', fn ($q) => $q->where('name', 'Visa Agent Payment'))
            ->get();

        foreach ($payments as $payment) {
            $transactions->push([
                'date' => $payment->payment_date->format('d-M-Y'),
                'status' => 'payment',
                'payable' => 0,
                'paid' => (float) ($payment->amount ?? 0),
                'balance' => 0,
                'cancellationFee' => 0,
            ]);
        }

        $transactions = $transactions->sortBy('date')->values();

        $transactions = $transactions->reject(fn ($item) => $item['payable'] == 0 && $item['paid'] == 0 && $item['cancellationFee'] == 0
        )->values();

        $runningPayable = 0;
        $runningPaid = 0;
        $runningFee = 0;
        $transactions = $transactions->map(function ($item) use (&$runningPayable, &$runningPaid, &$runningFee) {
            $runningPayable += $item['payable'];
            $runningPaid += $item['paid'];
            $runningFee += $item['cancellationFee'];
            $item['balance'] = $runningPaid - $runningPayable - $runningFee;

            return $item;
        });

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
            ->with('passenger.booking')
            ->get()
            ->map(function ($submission) {
                $submissionLog = $submission->logs()
                    ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(new_values, '$.status')) = 'submitted'")
                    ->latest()
                    ->first();

                return [
                    'invoice_id' => $submission->passenger->booking->invoice_id ?? '-',
                    'passenger_name' => trim(($submission->passenger->first_name ?? '').' '.($submission->passenger->last_name ?? '')),
                    'passport_no' => $submission->passenger->passport_no ?? '-',
                    'submission_date' => $submissionLog ? $submissionLog->created_at->format('d-M-Y') : '-',
                ];
            });

        return response()->json(['data' => $submissions]);
    }

    public function issued(VisaAgent $visaAgent): JsonResponse
    {
        $submissions = VisaSubmission::where('visa_agent_id', $visaAgent->id)
            ->where('status', 'issued')
            ->with('passenger.booking')
            ->get()
            ->map(function ($submission) {
                $issueLog = $submission->logs()
                    ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(new_values, '$.status')) = 'issued'")
                    ->latest()
                    ->first();

                return [
                    'invoice_id' => $submission->passenger->booking->invoice_id ?? '-',
                    'passenger_name' => trim(($submission->passenger->first_name ?? '').' '.($submission->passenger->last_name ?? '')),
                    'passport_no' => $submission->passenger->passport_no ?? '-',
                    'issue_date' => $issueLog ? $issueLog->created_at->format('d-M-Y') : '-',
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
                if ($dateFrom) {
                    $q->whereDate('created_at', '>=', $dateFrom);
                }
                if ($dateTo) {
                    $q->whereDate('created_at', '<=', $dateTo);
                }
            });
        $totalSubmitted = $submittedQuery->count();

        // --- Total Issued + Payable: issued submissions in date range ---
        $issuedQuery = VisaSubmission::where('visa_agent_id', $agentId)
            ->where('status', 'issued');

        if ($dateFrom || $dateTo) {
            $issuedQuery->where(function ($q) use ($dateFrom, $dateTo) {
                $q->whereHas('logs', function ($logQ) use ($dateFrom, $dateTo) {
                    $logQ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(new_values, '$.status')) = 'issued'");
                    if ($dateFrom) {
                        $logQ->whereDate('created_at', '>=', $dateFrom);
                    }
                    if ($dateTo) {
                        $logQ->whereDate('created_at', '<=', $dateTo);
                    }
                })->orWhere(function ($subQ) use ($dateFrom, $dateTo) {
                    $subQ->whereDoesntHave('logs', fn ($lh) => $lh->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(new_values, '$.status')) = 'issued'"));
                    if ($dateFrom) {
                        $subQ->whereDate('visa_submissions.updated_at', '>=', $dateFrom);
                    }
                    if ($dateTo) {
                        $subQ->whereDate('visa_submissions.updated_at', '<=', $dateTo);
                    }
                });
            });
        }

        $totalIssued = $issuedQuery->count();
        $payable = (float) $issuedQuery->sum(DB::raw('COALESCE(net_visa_cost, 0) + COALESCE(additional_cost, 0)'));

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
            ->when($dateFrom, fn ($q) => $q->whereDate('created_at', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->whereDate('created_at', '<=', $dateTo));
        $cancellationFee = (float) $cancelledQuery->sum('cancellation_fee');

        // --- Paid: Visa Agent Payment type payments ---
        $paidQuery = Payment::where('visa_agent_id', $agentId)
            ->whereHas('voucher.transactionType', fn ($q) => $q->where('name', 'Visa Agent Payment'))
            ->when($dateFrom, fn ($q) => $q->whereDate('payment_date', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->whereDate('payment_date', '<=', $dateTo));
        $paid = (float) $paidQuery->sum('amount');

        $balance = $paid - $payable - $cancellationFee;

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
