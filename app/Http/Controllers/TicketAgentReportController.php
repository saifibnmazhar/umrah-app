<?php

namespace App\Http\Controllers;

use App\Models\IssuedTicket;
use App\Models\TicketAgent;
use App\Services\TicketAgentLedger;
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

        $agents = TicketAgent::query()
            ->when($agentId, fn ($q) => $q->where('id', $agentId))
            ->get();
        $agentIds = $agents->pluck('id')->all();

        if (empty($agentIds)) {
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

        // Same ledger math as the ticket statement (issue-date semantics):
        // charges = ticket net_fare + re-issue total_cost,
        // credits = payments + IATA refunds, balance starts from pre-range opening.
        $fromStr = $dateFrom->toDateString();
        $toStr = $dateTo->toDateString();

        $fares = TicketAgentLedger::sumByAgent(TicketAgentLedger::tickets('issue', $fromStr, $toStr, 'period', null, null, $agentIds), 'net_fare');
        $costs = TicketAgentLedger::sumByAgent(TicketAgentLedger::reissues('issue', $fromStr, $toStr, 'period', null, null, $agentIds), 'total_cost');
        $iatas = TicketAgentLedger::sumByAgent(TicketAgentLedger::refunds('issue', $fromStr, $toStr, 'period', null, null, $agentIds), 'iata_refunded_amount');
        $pays = TicketAgentLedger::sumByAgent(TicketAgentLedger::payments($fromStr, $toStr, 'period', null, null, $agentIds), 'amount');

        $openFares = TicketAgentLedger::sumByAgent(TicketAgentLedger::tickets('issue', $fromStr, $toStr, 'opening', null, null, $agentIds), 'net_fare');
        $openCosts = TicketAgentLedger::sumByAgent(TicketAgentLedger::reissues('issue', $fromStr, $toStr, 'opening', null, null, $agentIds), 'total_cost');
        $openIatas = TicketAgentLedger::sumByAgent(TicketAgentLedger::refunds('issue', $fromStr, $toStr, 'opening', null, null, $agentIds), 'iata_refunded_amount');
        $openPays = TicketAgentLedger::sumByAgent(TicketAgentLedger::payments($fromStr, $toStr, 'opening', null, null, $agentIds), 'amount');

        $dailyFares = TicketAgentLedger::dailySumsByAgent(TicketAgentLedger::tickets('issue', $fromStr, $toStr, 'period', null, null, $agentIds), 'issued_date', 'net_fare');
        $dailyCosts = TicketAgentLedger::dailySumsByAgent(TicketAgentLedger::reissues('issue', $fromStr, $toStr, 'period', null, null, $agentIds), 'COALESCE(re_issue_date, created_at)', 'total_cost');
        $dailyIatas = TicketAgentLedger::dailySumsByAgent(TicketAgentLedger::refunds('issue', $fromStr, $toStr, 'period', null, null, $agentIds), 'COALESCE(refund_date, created_at)', 'iata_refunded_amount');
        $dailyPays = TicketAgentLedger::dailySumsByAgent(TicketAgentLedger::payments($fromStr, $toStr, 'period', null, null, $agentIds), 'payment_date', 'amount');

        $refundCounts = IssuedTicket::whereIn('ticket_agent_id', $agentIds)
            ->where('status', 'refunded')
            ->whereBetween('issued_date', [$dateFrom, $dateTo])
            ->groupBy('ticket_agent_id')
            ->selectRaw('ticket_agent_id, COUNT(*) as count')
            ->pluck('count', 'ticket_agent_id');

        $reissueCounts = IssuedTicket::whereIn('ticket_agent_id', $agentIds)
            ->where('status', 're-issued')
            ->whereBetween('issued_date', [$dateFrom, $dateTo])
            ->groupBy('ticket_agent_id')
            ->selectRaw('ticket_agent_id, COUNT(*) as count')
            ->pluck('count', 'ticket_agent_id');

        $data = $agents->map(function ($agent) use ($fares, $costs, $iatas, $pays, $openFares, $openCosts, $openIatas, $openPays, $refundCounts, $reissueCounts, $dailyFares, $dailyCosts, $dailyIatas, $dailyPays) {
            $id = $agent->id;
            $payable = (float) ($fares[$id] ?? 0) + (float) ($costs[$id] ?? 0);
            $paid = (float) ($pays[$id] ?? 0) + (float) ($iatas[$id] ?? 0);
            $opening = (float) ($openPays[$id] ?? 0) + (float) ($openIatas[$id] ?? 0)
                - (float) ($openFares[$id] ?? 0) - (float) ($openCosts[$id] ?? 0);
            $balance = $opening + $paid - $payable;

            $transactions = $this->dailyTransactions($id, $dailyFares, $dailyCosts, $dailyIatas, $dailyPays);

            return [
                'id' => $id,
                'name' => $agent->name,
                'payable' => $payable,
                'paid' => $paid,
                'due' => $balance,
                'refundedTickets' => (int) ($refundCounts[$id] ?? 0),
                'reissueTickets' => (int) ($reissueCounts[$id] ?? 0),
                'totalRefundAmount' => (float) ($iatas[$id] ?? 0),
                'totalReissueCost' => (float) ($costs[$id] ?? 0),
                'transactions' => $transactions,
                'reissueTransactions' => [],
                'refundTransactions' => [],
            ];
        });

        return response()->json([
            'data' => $data,
            'summary' => [
                'totalAgents' => $agents->count(),
                'agentsWithDue' => $data->filter(fn ($a) => $a['due'] < 0)->count(),
                'totalPayable' => $data->sum('payable'),
                'totalPaid' => $data->sum('paid'),
                'totalDue' => $data->sum('due'),
                'totalRefundedTickets' => $data->sum('refundedTickets'),
                'totalReissueTickets' => $data->sum('reissueTickets'),
                'totalRefundAmount' => $data->sum('totalRefundAmount'),
                'totalReissueCost' => $data->sum('totalReissueCost'),
            ],
        ]);
    }

    private function dailyTransactions(int $agentId, array $dailyFares, array $dailyCosts, array $dailyIatas, array $dailyPays): array
    {
        $fares = $dailyFares[$agentId] ?? [];
        $costs = $dailyCosts[$agentId] ?? [];
        $iatas = $dailyIatas[$agentId] ?? [];
        $pays = $dailyPays[$agentId] ?? [];

        return collect(array_keys($fares))
            ->merge(array_keys($costs))
            ->merge(array_keys($iatas))
            ->merge(array_keys($pays))
            ->unique()
            ->sort()
            ->values()
            ->map(fn ($date) => [
                'date' => Carbon::parse($date)->format('d-M-Y'),
                'payable' => (float) ($fares[$date] ?? 0) + (float) ($costs[$date] ?? 0),
                'paid' => (float) ($pays[$date] ?? 0) + (float) ($iatas[$date] ?? 0),
            ])
            ->all();
    }
}
