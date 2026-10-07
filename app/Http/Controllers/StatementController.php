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
        ]);

        $dateType = $validated['date_type'] ?? 'issue';
        $dateFrom = isset($validated['date_from']) ? Carbon::parse($validated['date_from'])->startOfDay() : now()->startOfMonth()->startOfDay();
        $dateTo = isset($validated['date_to']) ? Carbon::parse($validated['date_to'])->endOfDay() : now()->endOfDay();
        $search = isset($validated['search']) ? trim($validated['search']) : null;
        $search = $search === '' ? null : $search;
        $agentId = $validated['agent_id'] ?? null;

        if ($dateFrom->diffInDays($dateTo) > 92) {
            return response()->json(['message' => 'Date range must not exceed 92 days.'], 422);
        }

        $fromStr = $dateFrom->toDateString();
        $toStr = $dateTo->toDateString();

        $periodRows = $this->collectRows($dateType, $fromStr, $toStr, 'period', $search, $agentId);
        $openingDeltas = $this->collectRows($dateType, $fromStr, $toStr, 'opening', $search, $agentId, true);

        // Per-agent opening keyed by ticket_agent_id.
        $opening = [];
        foreach ($openingDeltas as $item) {
            $opening[$item['agent_id']] = ($opening[$item['agent_id']] ?? 0) + $item['delta'];
        }

        // Sort date asc → category asc → id asc (stable tie-break).
        usort($periodRows, function ($a, $b) {
            $cmp = strcmp($a['sort_date'], $b['sort_date']);
            if ($cmp !== 0) {
                return $cmp;
            }
            $cmp = strcmp($a['category'], $b['category']);
            if ($cmp !== 0) {
                return $cmp;
            }

            return $a['sort_id'] <=> $b['sort_id'];
        });

        // Per-agent running balances.
        $running = $opening;
        foreach ($periodRows as &$row) {
            $agentId_key = $row['agent_id'];
            $running[$agentId_key] = ($running[$agentId_key] ?? 0) + $row['delta'];
            $row['balance'] = round($running[$agentId_key], 2);
        }
        unset($row);

        // Strip internal keys.
        $rows = array_map(function ($row) {
            unset($row['sort_date'], $row['sort_id'], $row['delta'], $row['agent_id']);

            return $row;
        }, $periodRows);

        // Per-agent balances shared by sections and summary, so both always agree.
        // Covers every counted agent: row agents plus opening-only agents.
        $balances = $this->perAgentBalances($periodRows, $opening, $running);

        $summary = $this->buildSummary($periodRows, $balances);

        $payload = ['rows' => array_values($rows), 'summary' => $summary];

        if (! $agentId) {
            $payload['sections'] = $this->buildSections($periodRows, $balances);
        }

        return response()->json($payload);
    }

    /**
     * Collect row payloads for period (date between) or opening (date < from).
     * When $deltasOnly is true, returns lightweight agent_id+delta pairs.
     */
    private function collectRows(string $dateType, string $from, string $to, string $mode, ?string $search, $agentId, bool $deltasOnly = false): array
    {
        $rows = [];

        foreach ($this->fetchTickets($dateType, $from, $to, $mode, $search, $agentId) as $ticket) {
            $delta = TicketAgentLedger::ticketDelta($ticket);
            if ($deltasOnly) {
                $rows[] = ['agent_id' => $ticket->ticket_agent_id, 'delta' => $delta];

                continue;
            }
            $rows[] = $this->mapTicketRow($ticket, $delta);
        }

        foreach ($this->fetchReissues($dateType, $from, $to, $mode, $search, $agentId) as $reissue) {
            $delta = TicketAgentLedger::reissueDelta($reissue);
            if ($deltasOnly) {
                $rows[] = ['agent_id' => $reissue->ticket_agent_id, 'delta' => $delta];

                continue;
            }
            $rows[] = $this->mapReissueRow($reissue, $delta);
        }

        foreach ($this->fetchRefunds($dateType, $from, $to, $mode, $search, $agentId) as $refund) {
            $delta = TicketAgentLedger::refundDelta($refund);
            if ($deltasOnly) {
                $rows[] = ['agent_id' => $refund->ticket_agent_id, 'delta' => $delta];

                continue;
            }
            $rows[] = $this->mapRefundRow($refund, $delta);
        }

        // Payment rows only under Issue Date type.
        if ($dateType === 'issue') {
            foreach ($this->fetchPayments($from, $to, $mode, $search, $agentId) as $payment) {
                $delta = TicketAgentLedger::paymentDelta($payment);
                if ($deltasOnly) {
                    $rows[] = ['agent_id' => $payment->ticket_agent_id, 'delta' => $delta];

                    continue;
                }
                $rows[] = $this->mapPaymentRow($payment, $delta);
            }
        }

        return $rows;
    }

    private function fetchTickets(string $dateType, string $from, string $to, string $mode, ?string $search, $agentId)
    {
        return TicketAgentLedger::tickets($dateType, $from, $to, $mode, $search, $agentId)->with([
            'passenger.booking.customer', 'booking.customer',
            'ticketFare.airline', 'ticketFare.airlineClass.travelClass',
            'ticketFare.route.fromCity', 'ticketFare.route.toCity', 'ticketFare.route.returnCity',
            'ticketFare.route.multiSegments.fromCity', 'ticketFare.route.multiSegments.toCity',
            'ticketAgent', 'issuer',
        ])->get();
    }

    private function fetchReissues(string $dateType, string $from, string $to, string $mode, ?string $search, $agentId)
    {
        return TicketAgentLedger::reissues($dateType, $from, $to, $mode, $search, $agentId)->with([
            'issuedTicket.passenger.booking.customer', 'issuedTicket.booking.customer', 'issuedTicket.passenger',
            'ticketFare.airline', 'ticketFare.airlineClass.travelClass',
            'ticketFare.route.fromCity', 'ticketFare.route.toCity', 'ticketFare.route.returnCity',
            'ticketFare.route.multiSegments.fromCity', 'ticketFare.route.multiSegments.toCity',
            'ticketAgent', 'user',
        ])->get();
    }

    private function fetchRefunds(string $dateType, string $from, string $to, string $mode, ?string $search, $agentId)
    {
        return TicketAgentLedger::refunds($dateType, $from, $to, $mode, $search, $agentId)->with([
            'issuedTicket.passenger.booking.customer', 'issuedTicket.booking.customer', 'issuedTicket.passenger',
            'ticketFare.airline', 'ticketFare.airlineClass.travelClass',
            'ticketFare.route.fromCity', 'ticketFare.route.toCity', 'ticketFare.route.returnCity',
            'ticketFare.route.multiSegments.fromCity', 'ticketFare.route.multiSegments.toCity',
            'ticketAgent', 'user',
        ])->get();
    }

    private function fetchPayments(string $from, string $to, string $mode, ?string $search, $agentId)
    {
        return TicketAgentLedger::payments($from, $to, $mode, $search, $agentId)->with(['voucher', 'vouchers', 'ticketAgent', 'booking'])->get();
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

    /**
     * Rounded opening/closing per agent over the union of row agents and
     * opening agents, so sections and summary always cover the same agents.
     */
    private function perAgentBalances(array $periodRows, array $opening, array $running): array
    {
        $ids = [];
        foreach ($periodRows as $row) {
            $ids[$row['agent_id']] = true;
        }
        foreach ($opening as $id => $delta) {
            $ids[$id] = true;
        }
        foreach ($running as $id => $delta) {
            $ids[$id] = true;
        }

        $balances = [];
        foreach (array_keys($ids) as $id) {
            $balances[$id] = [
                'opening' => round($opening[$id] ?? 0, 2),
                'closing' => round($running[$id] ?? 0, 2),
            ];
        }

        return $balances;
    }

    private function buildSummary(array $periodRows, array $balances): array
    {
        $openingBalance = round(array_sum(array_column($balances, 'opening')), 2);
        $closingBalance = round(array_sum(array_column($balances, 'closing')), 2);

        $ticketRows = array_filter($periodRows, fn ($r) => $r['category'] === 'Ticket');
        $refundRows = array_filter($periodRows, fn ($r) => $r['category'] === 'Refund');
        $reissueRows = array_filter($periodRows, fn ($r) => $r['category'] === 'Re-issue');
        $paymentRows = array_filter($periodRows, fn ($r) => $r['category'] === 'Payment');

        // Re-fetch lightweight sums from mapped rows to keep formulas explicit.
        $totalSale = 0;
        $totalAgentFare = 0;
        $totalMarkup = 0;
        foreach ($ticketRows as $r) {
            $totalSale += (float) ($r['customer_amount'] ?? 0);
            $totalAgentFare += (float) ($r['agent_fare'] ?? 0);
            $totalMarkup += (float) ($r['markup'] ?? 0);
        }
        foreach ($reissueRows as $r) {
            $totalMarkup += (float) ($r['markup'] ?? 0);
        }
        foreach ($refundRows as $r) {
            $totalMarkup += (float) ($r['markup'] ?? 0);
        }

        return [
            'opening_balance' => $openingBalance,
            'closing_balance' => $closingBalance,
            'total_tickets' => count($ticketRows),
            'total_sale_amount' => round($totalSale, 2),
            'total_customer_refund' => round(array_sum(array_map(fn ($r) => (float) ($r['customer_refund'] ?? 0), $refundRows)), 2),
            'total_agent_fare' => round($totalAgentFare, 2),
            'total_markup' => round($totalMarkup, 2),
            'total_agent_refund' => round(array_sum(array_map(fn ($r) => (float) ($r['iata_refund'] ?? 0), $refundRows)), 2),
            'total_reissue_cost' => round(array_sum(array_map(fn ($r) => (float) ($r['agent_fare'] ?? 0), $reissueRows)), 2),
            'total_paid' => round(array_sum(array_map(fn ($r) => (float) ($r['payment_to_iata'] ?? 0), $paymentRows)), 2),
        ];
    }

    private function buildSections(array $periodRows, array $balances): array
    {
        $grouped = [];
        foreach ($periodRows as $row) {
            $grouped[$row['agent_id']][] = $row;
        }

        $ids = array_unique(array_merge(array_keys($grouped), array_keys($balances)));
        $names = TicketAgent::whereIn('id', $ids)->pluck('name', 'id');

        $sections = [];
        foreach ($ids as $id) {
            $agentRows = array_map(function ($row) {
                unset($row['sort_date'], $row['sort_id'], $row['delta'], $row['agent_id']);

                return $row;
            }, $grouped[$id] ?? []);

            $sections[] = [
                'agent_id' => $id,
                'agent_name' => $names[$id] ?? 'Unknown agent',
                'opening_balance' => $balances[$id]['opening'] ?? 0,
                'closing_balance' => $balances[$id]['closing'] ?? 0,
                'rows' => array_values($agentRows),
            ];
        }

        usort($sections, fn ($a, $b) => strcmp($a['agent_name'], $b['agent_name']));

        return $sections;
    }
}
