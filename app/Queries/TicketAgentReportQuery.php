<?php

namespace App\Queries;

use App\Models\IssuedTicket;
use App\Models\Payment;
use App\Models\TicketAgent;
use App\Support\DateFormatter;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class TicketAgentReportQuery
{
    /**
     * Build the ticket agent report data.
     */
    public function data(?int $agentId = null, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $dateFrom = $dateFrom ? Carbon::parse($dateFrom) : now()->startOfMonth();
        $dateTo = $dateTo ? Carbon::parse($dateTo) : now()->endOfMonth();

        $agents = TicketAgent::query()
            ->when($agentId, fn ($q) => $q->where('id', $agentId))
            ->get();

        $agentIds = $agents->pluck('id');

        if ($agentIds->isEmpty()) {
            return $this->emptyResult();
        }

        $payablePerAgent = $this->payablePerAgent($agentIds, $dateFrom, $dateTo);
        $paidPerAgent = $this->paidPerAgent($agentIds, $dateFrom, $dateTo);
        $refundCounts = $this->refundCounts($agentIds);
        $reissueCounts = $this->reissueCounts($agentIds);
        $dailyTicketsPerAgent = $this->dailyTicketsPerAgent($agentIds, $dateFrom, $dateTo);
        $dailyPaymentsPerAgent = $this->dailyPaymentsPerAgent($agentIds, $dateFrom, $dateTo);

        $data = $agents->map(function ($agent) use ($payablePerAgent, $paidPerAgent, $refundCounts, $reissueCounts, $dailyTicketsPerAgent, $dailyPaymentsPerAgent) {
            return $this->buildAgentRow(
                $agent,
                $payablePerAgent,
                $paidPerAgent,
                $refundCounts,
                $reissueCounts,
                $dailyTicketsPerAgent,
                $dailyPaymentsPerAgent
            );
        });

        $summary = $this->buildSummary($agentIds, $dateFrom, $dateTo, $data);

        return ['data' => $data, 'summary' => $summary];
    }

    protected function emptyResult(): array
    {
        return [
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
        ];
    }

    protected function payablePerAgent($agentIds, $dateFrom, $dateTo): Collection
    {
        return IssuedTicket::whereIn('ticket_agent_id', $agentIds)
            ->whereBetween('issued_date', [$dateFrom, $dateTo])
            ->groupBy('ticket_agent_id')
            ->selectRaw('ticket_agent_id, SUM(net_fare) as total_payable')
            ->pluck('total_payable', 'ticket_agent_id');
    }

    protected function paidPerAgent($agentIds, $dateFrom, $dateTo): Collection
    {
        return Payment::whereIn('ticket_agent_id', $agentIds)
            ->whereBetween('payment_date', [$dateFrom, $dateTo])
            ->groupBy('ticket_agent_id')
            ->selectRaw('ticket_agent_id, SUM(amount) as total_paid')
            ->pluck('total_paid', 'ticket_agent_id');
    }

    protected function refundCounts($agentIds): Collection
    {
        return IssuedTicket::whereIn('ticket_agent_id', $agentIds)
            ->where('status', 'refunded')
            ->groupBy('ticket_agent_id')
            ->selectRaw('ticket_agent_id, COUNT(*) as count')
            ->pluck('count', 'ticket_agent_id');
    }

    protected function reissueCounts($agentIds): Collection
    {
        return IssuedTicket::whereIn('ticket_agent_id', $agentIds)
            ->where('status', 're-issued')
            ->groupBy('ticket_agent_id')
            ->selectRaw('ticket_agent_id, COUNT(*) as count')
            ->pluck('count', 'ticket_agent_id');
    }

    protected function dailyTicketsPerAgent($agentIds, $dateFrom, $dateTo): Collection
    {
        return IssuedTicket::whereIn('ticket_agent_id', $agentIds)
            ->whereBetween('issued_date', [$dateFrom, $dateTo])
            ->groupBy('ticket_agent_id', 'issued_date')
            ->selectRaw('ticket_agent_id, issued_date, SUM(net_fare) as total_payable')
            ->orderBy('ticket_agent_id')
            ->orderBy('issued_date')
            ->get()
            ->groupBy('ticket_agent_id');
    }

    protected function dailyPaymentsPerAgent($agentIds, $dateFrom, $dateTo): Collection
    {
        return Payment::whereIn('ticket_agent_id', $agentIds)
            ->whereBetween('payment_date', [$dateFrom, $dateTo])
            ->groupBy('ticket_agent_id', 'payment_date')
            ->selectRaw('ticket_agent_id, payment_date, SUM(amount) as total_paid')
            ->orderBy('ticket_agent_id')
            ->orderBy('payment_date')
            ->get()
            ->groupBy('ticket_agent_id');
    }

    protected function buildAgentRow(
        TicketAgent $agent,
        Collection $payablePerAgent,
        Collection $paidPerAgent,
        Collection $refundCounts,
        Collection $reissueCounts,
        Collection $dailyTicketsPerAgent,
        Collection $dailyPaymentsPerAgent
    ): array {
        $payable = (float) ($payablePerAgent[$agent->id] ?? 0);
        $paid = (float) ($paidPerAgent[$agent->id] ?? 0);
        $balance = $paid - $payable;

        $agentDailyTickets = $dailyTicketsPerAgent[$agent->id] ?? collect();
        $agentDailyPayments = $dailyPaymentsPerAgent[$agent->id] ?? collect();

        $dailyTickets = $agentDailyTickets->pluck('total_payable', 'issued_date');
        $dailyPayments = $agentDailyPayments->pluck('total_paid', 'payment_date');

        $allDates = collect(array_keys($dailyTickets->toArray()))
            ->merge(array_keys($dailyPayments->toArray()))
            ->unique()
            ->sort()
            ->values();

        $transactions = $allDates->map(function ($date) use ($dailyTickets, $dailyPayments) {
            return [
                'date' => DateFormatter::short(Carbon::parse($date)),
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
    }

    protected function buildSummary($agentIds, $dateFrom, $dateTo, Collection $data): array
    {
        $totalPayableAll = (float) IssuedTicket::whereIn('ticket_agent_id', $agentIds)
            ->whereBetween('issued_date', [$dateFrom, $dateTo])
            ->sum('net_fare');

        $totalPaidAll = (float) Payment::whereIn('ticket_agent_id', $agentIds)
            ->whereBetween('payment_date', [$dateFrom, $dateTo])
            ->sum('amount');

        $totalRefundedTicketsAll = IssuedTicket::whereIn('ticket_agent_id', $agentIds)
            ->where('status', 'refunded')
            ->count();

        $totalReissueTicketsAll = IssuedTicket::whereIn('ticket_agent_id', $agentIds)
            ->where('status', 're-issued')
            ->count();

        $agentsWithDue = $data->filter(fn ($a) => $a['due'] < 0)->count();

        return [
            'totalAgents' => $data->count(),
            'agentsWithDue' => $agentsWithDue,
            'totalPayable' => $totalPayableAll,
            'totalPaid' => $totalPaidAll,
            'totalDue' => $totalPaidAll - $totalPayableAll,
            'totalRefundedTickets' => $totalRefundedTicketsAll,
            'totalReissueTickets' => $totalReissueTicketsAll,
            'totalRefundAmount' => 0,
            'totalReissueCost' => 0,
        ];
    }
}
