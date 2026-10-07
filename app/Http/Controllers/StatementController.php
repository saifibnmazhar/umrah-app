<?php

namespace App\Http\Controllers;

use App\Models\IssuedTicket;
use App\Models\Payment;
use App\Models\RefundedTicket;
use App\Models\ReIssuedTicket;
use App\Models\TicketAgent;
use App\Services\TicketAgentLedger;
use Carbon\Carbon;
use Illuminate\Http\Request;

class StatementController extends Controller
{
    public function index()
    {
        $agents = TicketAgent::orderBy('name')->get();

        return view('reports.statement', compact('agents'));
    }

    public function data(Request $request)
    {
        $validated = $request->validate([
            'date_type' => 'nullable|in:issue,flight,return',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
            'search' => 'nullable|string|max:255',
            'agent_id' => 'nullable|exists:ticket_agents,id',
            'page' => 'nullable|integer|min:1',
        ]);

        $dateType = $validated['date_type'] ?? 'issue';
        $dateFrom = isset($validated['date_from']) ? Carbon::parse($validated['date_from'])->startOfDay() : now()->subDays(30)->startOfDay();
        $dateTo = isset($validated['date_to']) ? Carbon::parse($validated['date_to'])->endOfDay() : now()->endOfDay();
        $search = isset($validated['search']) ? trim($validated['search']) : null;
        $search = $search === '' ? null : $search;
        $agentId = $validated['agent_id'] ?? null;

        if ($dateFrom->diffInDays($dateTo) > 366) {
            return response()->json(['message' => 'Date range must not exceed 366 days.'], 422);
        }

        $fromStr = $dateFrom->toDateString();
        $toStr = $dateTo->toDateString();

        // Agent-block pages over the SQL-ordered event union: each page holds
        // whole agents up to the row budget, so an agent's records stay
        // together like the old sections. Only an agent bigger than the
        // budget is sliced across consecutive pages (flags stay exact).
        // Summary and per-agent balances always cover the whole range.
        $perPage = TicketAgentLedger::STATEMENT_PER_PAGE;
        $total = TicketAgentLedger::statementCount($dateType, $fromStr, $toStr, $search, $agentId);
        $blockPages = $this->blockPages($dateType, $fromStr, $toStr, $search, $agentId, $perPage);
        $lastPage = max(1, count($blockPages));
        $page = max(1, (int) ($validated['page'] ?? 1));

        $pageEvents = collect();
        if ($page <= count($blockPages)) {
            $block = $blockPages[$page - 1];
            $blockAgents = array_unique(array_column($block, 'agent'));
            $offset = min(array_column($block, 'offset'));
            $limit = array_sum(array_column($block, 'limit'));
            $pageEvents = TicketAgentLedger::statementPage($dateType, $fromStr, $toStr, $search, $agentId, $blockAgents, $offset, $limit);
        }

        $ids = ['Ticket' => [], 'Re-issue' => [], 'Refund' => [], 'Payment' => []];
        foreach ($pageEvents as $event) {
            $ids[$event->kind][] = $event->row_id;
        }
        $tickets = $this->fetchTicketsByIds($ids['Ticket']);
        $reissues = $this->fetchReissuesByIds($ids['Re-issue']);
        $refunds = $this->fetchRefundsByIds($ids['Refund']);
        $payments = $this->fetchPaymentsByIds($ids['Payment']);

        $openings = TicketAgentLedger::statementAgentOpening($dateType, $fromStr, $toStr, $search, $agentId);
        $periods = TicketAgentLedger::statementAgentPeriod($dateType, $fromStr, $toStr, $search, $agentId);

        $rows = [];
        foreach ($pageEvents as $event) {
            $agent = (int) $event->agent_id;
            $mapped = match ($event->kind) {
                'Ticket' => $this->mapTicketRow($tickets[$event->row_id], (float) $event->delta),
                'Re-issue' => $this->mapReissueRow($reissues[$event->row_id], (float) $event->delta),
                'Refund' => $this->mapRefundRow($refunds[$event->row_id], (float) $event->delta),
                default => $this->mapPaymentRow($payments[$event->row_id], (float) $event->delta),
            };
            $mapped['balance'] = round((float) ($openings[$agent] ?? 0) + (float) $event->running, 2);
            unset($mapped['sort_date'], $mapped['sort_id'], $mapped['delta']);
            $mapped['agent_changed'] = $event->prev_agent === null || (int) $event->prev_agent !== $agent;
            $mapped['agent_ends'] = $event->next_agent === null || (int) $event->next_agent !== $agent;
            $rows[] = $mapped;
        }

        // Every counted agent stays visible: row agents plus opening-only agents.
        $agentIds = array_unique(array_merge(array_keys($openings), array_keys($periods)));
        $names = TicketAgent::whereIn('id', $agentIds)->pluck('name', 'id');
        $agents = [];
        foreach ($agentIds as $id) {
            $opening = round((float) ($openings[$id] ?? 0), 2);
            $charges = (float) ($periods[$id]->charges ?? 0);
            $credits = (float) ($periods[$id]->credits ?? 0);
            $agents[$id] = [
                'agent_id' => $id,
                'agent_name' => $names[$id] ?? 'Unknown agent',
                'opening_balance' => $opening,
                'closing_balance' => round($opening + $credits - $charges, 2),
                'has_rows' => ((int) ($periods[$id]->row_count ?? 0)) > 0,
            ];
        }
        uasort($agents, fn ($a, $b) => strcmp($a['agent_name'], $b['agent_name']));

        $totals = TicketAgentLedger::statementSummary($dateType, $fromStr, $toStr, $search, $agentId);
        $summary = [
            'opening_balance' => round(array_sum(array_column($agents, 'opening_balance')), 2),
            'closing_balance' => round(array_sum(array_column($agents, 'closing_balance')), 2),
            'total_tickets' => (int) ($totals->total_tickets ?? 0),
            'total_sale_amount' => round((float) ($totals->total_sale_amount ?? 0), 2),
            'total_customer_refund' => round((float) ($totals->total_customer_refund ?? 0), 2),
            'total_agent_fare' => round((float) ($totals->total_agent_fare ?? 0), 2),
            'total_markup' => round((float) ($totals->total_markup ?? 0), 2),
            'total_agent_refund' => round((float) ($totals->total_agent_refund ?? 0), 2),
            'total_reissue_cost' => round((float) ($totals->total_reissue_cost ?? 0), 2),
            'total_paid' => round((float) ($totals->total_paid ?? 0), 2),
        ];

        return response()->json([
            'rows' => $rows,
            'agents' => $agents,
            'summary' => $summary,
            'meta' => ['page' => $page, 'per_page' => $perPage, 'total' => $total, 'last_page' => $lastPage],
        ]);
    }

    /**
     * Greedy whole-agent page plan in report order. Each entry is a list of
     * ['agent', 'offset', 'limit'] slices; multi-agent pages use offset 0.
     *
     * @return array<int, array<int, array{agent: int, offset: int, limit: int}>>
     */
    private function blockPages(string $dateType, string $fromStr, string $toStr, ?string $search, $agentId, int $perPage): array
    {
        $pages = [];
        $current = [];
        $used = 0;
        $flush = function () use (&$pages, &$current, &$used): void {
            if ($current !== []) {
                $pages[] = $current;
                $current = [];
                $used = 0;
            }
        };

        foreach (TicketAgentLedger::statementAgentRowCounts($dateType, $fromStr, $toStr, $search, $agentId) as $count) {
            $n = (int) $count->n;
            if ($n > $perPage) {
                $flush();
                for ($off = 0; $off < $n; $off += $perPage) {
                    $pages[] = [['agent' => (int) $count->agent_id, 'offset' => $off, 'limit' => min($perPage, $n - $off)]];
                }

                continue;
            }
            if ($used + $n > $perPage) {
                $flush();
            }
            $current[] = ['agent' => (int) $count->agent_id, 'offset' => 0, 'limit' => $n];
            $used += $n;
        }
        $flush();

        return $pages;
    }

    /**
     * Page-scoped model fetches keyed by id. The SQL union decides *which*
     * rows belong on the page; these hydrate just those rows with the same
     * relations the row mappers need.
     */
    private function fetchTicketsByIds(array $ids)
    {
        if (empty($ids)) {
            return collect();
        }

        return IssuedTicket::whereIn('id', $ids)->with([
            'passenger.booking.customer', 'booking.customer',
            'ticketFare.airline', 'ticketFare.airlineClass.travelClass',
            'ticketFare.route.fromCity', 'ticketFare.route.toCity', 'ticketFare.route.returnCity',
            'ticketFare.route.multiSegments.fromCity', 'ticketFare.route.multiSegments.toCity',
            'ticketAgent', 'issuer',
        ])->get()->keyBy('id');
    }

    private function fetchReissuesByIds(array $ids)
    {
        if (empty($ids)) {
            return collect();
        }

        return ReIssuedTicket::whereIn('id', $ids)->with([
            'issuedTicket.passenger.booking.customer', 'issuedTicket.booking.customer', 'issuedTicket.passenger',
            'ticketFare.airline', 'ticketFare.airlineClass.travelClass',
            'ticketFare.route.fromCity', 'ticketFare.route.toCity', 'ticketFare.route.returnCity',
            'ticketFare.route.multiSegments.fromCity', 'ticketFare.route.multiSegments.toCity',
            'ticketAgent', 'user',
        ])->get()->keyBy('id');
    }

    private function fetchRefundsByIds(array $ids)
    {
        if (empty($ids)) {
            return collect();
        }

        return RefundedTicket::whereIn('id', $ids)->with([
            'issuedTicket.passenger.booking.customer', 'issuedTicket.booking.customer', 'issuedTicket.passenger',
            'ticketFare.airline', 'ticketFare.airlineClass.travelClass',
            'ticketFare.route.fromCity', 'ticketFare.route.toCity', 'ticketFare.route.returnCity',
            'ticketFare.route.multiSegments.fromCity', 'ticketFare.route.multiSegments.toCity',
            'ticketAgent', 'user',
        ])->get()->keyBy('id');
    }

    private function fetchPaymentsByIds(array $ids)
    {
        if (empty($ids)) {
            return collect();
        }

        return Payment::whereIn('id', $ids)->with(['voucher', 'vouchers', 'ticketAgent', 'booking'])->get()->keyBy('id');
    }

    private function offerAwarePay($row): float
    {
        $fare = $row->ticketFare;
        $ticketType = $fare?->ticket_type;
        $ticketTypeValue = $ticketType instanceof \BackedEnum ? $ticketType->value : (string) $ticketType;

        if ($ticketTypeValue === 'offer' && (float) ($row->offer_price ?? 0) > 0) {
            return (float) $row->offer_price;
        }

        return (float) ($row->selling_fare ?? 0);
    }

    private function carrierClassPay($row): string
    {
        $fare = $row->ticketFare;
        $carrier = $fare?->airline?->code ?? $fare?->airline?->name ?? '-';
        $class = $fare?->airlineClass?->travelClass?->name ?? $fare?->airlineClass?->class?->name ?? '-';

        return trim("{$carrier} | {$class}");
    }

    /**
     * Sector from the row's own ticket fare route, falling back to the
     * passenger route display (today's behaviour) when fare/route is missing.
     */
    private function sectorFor($row, $passenger): string
    {
        $route = $row->ticketFare?->route;
        if ($route) {
            return TicketAgentLedger::routeDisplay($route);
        }

        return $passenger?->route_display ?? '-';
    }

    private function formatDay($date): string
    {
        if (! $date) {
            return '-';
        }

        return Carbon::parse($date)->format('d-M-y');
    }

    private function mapTicketRow(IssuedTicket $ticket, float $delta): array
    {
        $pay = $this->offerAwarePay($ticket);
        $passenger = $ticket->passenger;
        $booking = $ticket->booking ?? $passenger?->booking;

        return [
            'date' => $this->formatDay($ticket->issued_date),
            'sort_date' => $ticket->issued_date ? Carbon::parse($ticket->issued_date)->toDateString() : '',
            'sort_id' => $ticket->id,
            'category' => 'Ticket',
            'ticket_no' => $ticket->ticket_number ?? '-',
            'reference_id' => $booking?->invoice_id ?? '-',
            'pax_name' => $passenger ? trim(($passenger->first_name ?? '').' '.($passenger->last_name ?? '')) : '-',
            'customer_name' => $booking?->customer?->name ?? '-',
            'pnr' => $ticket->pnr ?? '-',
            'passport' => $passenger?->passport_no ?? '-',
            'sector' => $this->sectorFor($ticket, $passenger),
            'carrier_class_pay' => $this->carrierClassPay($ticket),
            'flight_date' => $this->formatDay($ticket->inbound_date),
            'return_date' => $this->formatDay($ticket->outbound_date),
            'customer_amount' => $pay,
            'agent_fare' => (float) $ticket->net_fare,
            'markup' => round($pay - (float) $ticket->net_fare, 2),
            'customer_refund' => null,
            'iata_refund' => null,
            'payment_to_iata' => null,
            'balance' => 0,
            'delta' => $delta,
            'agent_id' => $ticket->ticket_agent_id,
            'agent_name' => $ticket->ticketAgent?->name ?? '-',
            'staff_name' => $ticket->issuer?->name ?? '-',
        ];
    }

    private function mapReissueRow(ReIssuedTicket $reissue, float $delta): array
    {
        $pay = $this->offerAwarePay($reissue);
        $source = $reissue->issuedTicket;
        $passenger = $source?->passenger;
        $booking = $source?->booking ?? $passenger?->booking;
        $effectiveDate = $reissue->re_issue_date ?? $reissue->created_at;

        return [
            'date' => $this->formatDay($effectiveDate),
            'sort_date' => $effectiveDate ? Carbon::parse($effectiveDate)->toDateString() : '',
            'sort_id' => $reissue->id,
            'category' => 'Re-issue',
            'ticket_no' => $reissue->ticket_number ?? '-',
            'reference_id' => $booking?->invoice_id ?? '-',
            'pax_name' => $passenger ? trim(($passenger->first_name ?? '').' '.($passenger->last_name ?? '')) : '-',
            'customer_name' => $booking?->customer?->name ?? '-',
            'pnr' => $reissue->pnr ?? '-',
            'passport' => $passenger?->passport_no ?? '-',
            'sector' => $this->sectorFor($reissue, $passenger),
            'carrier_class_pay' => $this->carrierClassPay($reissue),
            'flight_date' => $this->formatDay($reissue->inbound_date),
            'return_date' => $this->formatDay($reissue->outbound_date),
            'customer_amount' => $pay,
            'agent_fare' => (float) $reissue->total_cost,
            'markup' => (float) $reissue->service_charge,
            'customer_refund' => null,
            'iata_refund' => null,
            'payment_to_iata' => null,
            'balance' => 0,
            'delta' => $delta,
            'agent_id' => $reissue->ticket_agent_id,
            'agent_name' => $reissue->ticketAgent?->name ?? '-',
            'staff_name' => $reissue->user?->name ?? '-',
        ];
    }

    private function mapRefundRow(RefundedTicket $refund, float $delta): array
    {
        $pay = $this->offerAwarePay($refund);
        $source = $refund->issuedTicket;
        $passenger = $source?->passenger;
        $booking = $source?->booking ?? $passenger?->booking;
        $effectiveDate = $refund->refund_date ?? $refund->created_at;

        return [
            'date' => $this->formatDay($effectiveDate),
            'sort_date' => $effectiveDate ? Carbon::parse($effectiveDate)->toDateString() : '',
            'sort_id' => $refund->id,
            'category' => 'Refund',
            'ticket_no' => $refund->ticket_number ?? '-',
            'reference_id' => $booking?->invoice_id ?? '-',
            'pax_name' => $passenger ? trim(($passenger->first_name ?? '').' '.($passenger->last_name ?? '')) : '-',
            'customer_name' => $booking?->customer?->name ?? '-',
            'pnr' => $refund->pnr ?? '-',
            'passport' => $passenger?->passport_no ?? '-',
            'sector' => $this->sectorFor($refund, $passenger),
            'carrier_class_pay' => $this->carrierClassPay($refund),
            'flight_date' => $this->formatDay($refund->inbound_date),
            'return_date' => $this->formatDay($refund->outbound_date),
            'customer_amount' => null,
            'agent_fare' => null,
            'markup' => (float) $refund->service_charge,
            'customer_refund' => (float) $refund->refund_to_customer,
            'iata_refund' => (float) $refund->iata_refunded_amount,
            'payment_to_iata' => null,
            'balance' => 0,
            'delta' => $delta,
            'agent_id' => $refund->ticket_agent_id,
            'agent_name' => $refund->ticketAgent?->name ?? '-',
            'staff_name' => $refund->user?->name ?? '-',
        ];
    }

    private function mapPaymentRow(Payment $payment, float $delta): array
    {
        return [
            'date' => $this->formatDay($payment->payment_date),
            'sort_date' => $payment->payment_date ? Carbon::parse($payment->payment_date)->toDateString() : '',
            'sort_id' => $payment->id,
            'category' => 'Payment',
            'ticket_no' => '-',
            'reference_id' => $payment->voucher?->voucher_id ?? '-',
            'pax_name' => '-',
            'customer_name' => '-',
            'pnr' => '-',
            'passport' => '-',
            'sector' => '-',
            'carrier_class_pay' => '-',
            'flight_date' => '-',
            'return_date' => '-',
            'customer_amount' => null,
            'agent_fare' => null,
            'markup' => null,
            'customer_refund' => null,
            'iata_refund' => null,
            'payment_to_iata' => (float) $payment->amount,
            'balance' => 0,
            'delta' => $delta,
            'agent_id' => $payment->ticket_agent_id,
            'agent_name' => $payment->ticketAgent?->name ?? '-',
            'staff_name' => '',
        ];
    }
}
