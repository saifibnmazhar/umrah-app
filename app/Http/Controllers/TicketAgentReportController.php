<?php

namespace App\Http\Controllers;

use App\Models\IssuedTicket;
use App\Models\Payment;
use App\Models\TicketAgent;
use Carbon\Carbon;
use Illuminate\Http\Request;

class TicketAgentReportController extends Controller
{
    public function index()
    {
        $agents = TicketAgent::orderBy('name')->get();

        return view('reports.ticket-agent', compact('agents'));
    }

    public function data(Request $request)
    {
        $dateFrom = $request->date('date_from') ?? now()->startOfMonth();
        $dateTo = $request->date('date_to') ?? now()->endOfMonth();
        $agentId = $request->agent_id;

        $agentsQuery = TicketAgent::query()
            ->when($agentId, fn ($q) => $q->where('id', $agentId));

        $agents = $agentsQuery->get();
        $agentIds = $agents->pluck('id');

        if ($agentIds->isEmpty()) {
            return response()->json([
                'data' => [],
                'summary' => [
                    'totalAgents' => 0,
                    'agentsWithDue' => 0,
                    'totalPayable' => 0,
                    'totalPaid' => 0,
                    'totalDue' => 0,
                    'totalRefundedTickets' => 0,
                    'totalReissueTickets' => 0,
                    'totalRefundAmount' => 0,
                    'totalReissueCost' => 0,
                ],
            ]);
        }

        $payablePerAgent = IssuedTicket::whereIn('ticket_agent_id', $agentIds)
            ->whereBetween('issued_date', [$dateFrom, $dateTo])
            ->groupBy('ticket_agent_id')
            ->selectRaw('ticket_agent_id, SUM(net_fare) as total_payable')
            ->pluck('total_payable', 'ticket_agent_id');

        $paidPerAgent = Payment::whereIn('ticket_agent_id', $agentIds)
            ->whereBetween('payment_date', [$dateFrom, $dateTo])
            ->groupBy('ticket_agent_id')
            ->selectRaw('ticket_agent_id, SUM(amount) as total_paid')
            ->pluck('total_paid', 'ticket_agent_id');

        $refundCounts = IssuedTicket::whereIn('ticket_agent_id', $agentIds)
            ->where('status', 'refunded')
            ->groupBy('ticket_agent_id')
            ->selectRaw('ticket_agent_id, COUNT(*) as count')
            ->pluck('count', 'ticket_agent_id');

        $reissueCounts = IssuedTicket::whereIn('ticket_agent_id', $agentIds)
            ->where('status', 're-issued')
            ->groupBy('ticket_agent_id')
            ->selectRaw('ticket_agent_id, COUNT(*) as count')
            ->pluck('count', 'ticket_agent_id');

        $data = $agents->map(function ($agent) use ($payablePerAgent, $paidPerAgent, $refundCounts, $reissueCounts, $dateFrom, $dateTo) {
            $payable = (float) ($payablePerAgent[$agent->id] ?? 0);
            $paid = (float) ($paidPerAgent[$agent->id] ?? 0);
            $balance = $paid - $payable;

            $dailyTickets = IssuedTicket::where('ticket_agent_id', $agent->id)
                ->whereBetween('issued_date', [$dateFrom, $dateTo])
                ->groupBy('issued_date')
                ->selectRaw('issued_date, SUM(net_fare) as total_payable')
                ->orderBy('issued_date')
                ->pluck('total_payable', 'issued_date');

            $dailyPayments = Payment::where('ticket_agent_id', $agent->id)
                ->whereBetween('payment_date', [$dateFrom, $dateTo])
                ->groupBy('payment_date')
                ->selectRaw('payment_date, SUM(amount) as total_paid')
                ->orderBy('payment_date')
                ->pluck('total_paid', 'payment_date');

            $allDates = collect(array_keys($dailyTickets->toArray()))
                ->merge(array_keys($dailyPayments->toArray()))
                ->unique()
                ->sort()
                ->values();

            $transactions = $allDates->map(function ($date) use ($dailyTickets, $dailyPayments) {
                return [
                    'date' => Carbon::parse($date)->format('d-M-Y'),
                    'payable' => (float) ($dailyTickets[$date] ?? 0),
                    'paid' => (float) ($dailyPayments[$date] ?? 0),
                ];
            });

            return [
                'id' => $agent->id,
                'name' => $agent->name,
                'payable' => $payable,
                'paid' => $paid,
                'due' => $balance,
                'refundedTickets' => (int) ($refundCounts[$agent->id] ?? 0),
                'reissueTickets' => (int) ($reissueCounts[$agent->id] ?? 0),
                'totalRefundAmount' => 0,
                'totalReissueCost' => 0,
                'transactions' => $transactions,
                'reissueTransactions' => [],
                'refundTransactions' => [],
            ];
        });

        $summaryQuery = TicketAgent::query()
            ->when($agentId, fn ($q) => $q->where('id', $agentId));

        $summaryAgentIds = $summaryQuery->pluck('id');

        $totalPayableAll = IssuedTicket::whereIn('ticket_agent_id', $summaryAgentIds)
            ->whereBetween('issued_date', [$dateFrom, $dateTo])
            ->sum('net_fare');

        $totalPaidAll = Payment::whereIn('ticket_agent_id', $summaryAgentIds)
            ->whereBetween('payment_date', [$dateFrom, $dateTo])
            ->sum('amount');

        $totalPayableAll = (float) $totalPayableAll;
        $totalPaidAll = (float) $totalPaidAll;

        $totalRefundedTicketsAll = IssuedTicket::whereIn('ticket_agent_id', $summaryAgentIds)
            ->where('status', 'refunded')
            ->count();

        $totalReissueTicketsAll = IssuedTicket::whereIn('ticket_agent_id', $summaryAgentIds)
            ->where('status', 're-issued')
            ->count();

        $agentsWithDue = $data->filter(fn ($a) => $a['due'] < 0)->count();

        return response()->json([
            'data' => $data,
            'summary' => [
                'totalAgents' => $agents->count(),
                'agentsWithDue' => $agentsWithDue,
                'totalPayable' => $totalPayableAll,
                'totalPaid' => $totalPaidAll,
                'totalDue' => $totalPaidAll - $totalPayableAll,
                'totalRefundedTickets' => $totalRefundedTicketsAll,
                'totalReissueTickets' => $totalReissueTicketsAll,
                'totalRefundAmount' => 0,
                'totalReissueCost' => 0,
            ],
        ]);
    }
}
