<?php

namespace App\Services;

use App\Models\IssuedTicket;
use App\Models\Payment;
use App\Models\RefundedTicket;
use App\Models\ReIssuedTicket;
use App\Models\Route;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Single source of truth for ticket-agent ledger math.
 *
 * Balance = Paid − Payable where every event moves the ledger by its delta:
 * Ticket −net_fare · Re-issue −total_cost · Refund +iata_refunded_amount · Payment +amount.
 * Negative balance = due from the agent.
 *
 * Used by StatementController (row-level ledger) and TicketAgentReportController
 * (per-agent aggregates). Keep the delta rules here so both reports agree.
 */
final class TicketAgentLedger
{
    public const TICKET_STATUSES = ['issued', 're-issued', 'refunded'];

    // ---------------- delta rules ----------------

    public static function ticketDelta(IssuedTicket $ticket): float
    {
        return -1 * (float) $ticket->net_fare;
    }

    public static function reissueDelta(ReIssuedTicket $reissue): float
    {
        return -1 * (float) $reissue->total_cost;
    }

    public static function refundDelta(RefundedTicket $refund): float
    {
        return (float) $refund->iata_refunded_amount;
    }

    public static function paymentDelta(Payment $payment): float
    {
        return (float) $payment->amount;
    }

    // ---------------- filtered query builders ----------------
    //
    // $mode is 'period' (date between from/to) or 'opening' (date before from).
    // Callers add ->with() / ->get() / aggregates on top.

    public static function tickets(string $dateType, string $from, string $to, string $mode, ?string $search = null, $agentId = null, array $agentIds = []): Builder
    {
        $query = IssuedTicket::query()->whereIn('status', self::TICKET_STATUSES);
        $query = self::applyAgents($query, $agentIds, $agentId);
        $query = self::applyTicketDate($query, $dateType, $from, $to, $mode);
        if ($search) {
            $query = self::applyTicketSearch($query, $search);
        }

        return $query;
    }

    public static function reissues(string $dateType, string $from, string $to, string $mode, ?string $search = null, $agentId = null, array $agentIds = []): Builder
    {
        $query = ReIssuedTicket::query();
        $query = self::applyAgents($query, $agentIds, $agentId);
        $query = self::applyReissueDate($query, $dateType, $from, $to, $mode);
        if ($search) {
            $query = self::applyReissueSearch($query, $search);
        }

        return $query;
    }

    public static function refunds(string $dateType, string $from, string $to, string $mode, ?string $search = null, $agentId = null, array $agentIds = []): Builder
    {
        $query = RefundedTicket::query();
        $query = self::applyAgents($query, $agentIds, $agentId);
        $query = self::applyRefundDate($query, $dateType, $from, $to, $mode);
        if ($search) {
            $query = self::applyRefundSearch($query, $search);
        }

        return $query;
    }

    public static function payments(string $from, string $to, string $mode, ?string $search = null, $agentId = null, array $agentIds = []): Builder
    {
        $query = Payment::query()->whereNotNull('ticket_agent_id');
        $query = self::applyAgents($query, $agentIds, $agentId);
        $query = self::applyDate($query, 'payment_date', $from, $to, $mode);
        if ($search) {
            $query = self::applyPaymentSearch($query, $search);
        }

        return $query;
    }

    public static function applyAgents(Builder $query, array $agentIds = [], $agentId = null): Builder
    {
        if ($agentId) {
            return $query->where('ticket_agent_id', $agentId);
        }
        if (! empty($agentIds)) {
            return $query->whereIn('ticket_agent_id', $agentIds);
        }

        return $query;
    }

    public static function applyDate(Builder $query, string $column, string $from, string $to, string $mode): Builder
    {
        if ($mode === 'opening') {
            return $query->whereDate($column, '<', $from);
        }

        return $query->whereDate($column, '>=', $from)->whereDate($column, '<=', $to);
    }

    public static function applyTicketDate(Builder $query, string $dateType, string $from, string $to, string $mode): Builder
    {
        $column = match ($dateType) {
            'flight' => 'inbound_date',
            'return' => 'outbound_date',
            default => 'issued_date',
        };

        return self::applyDate($query, $column, $from, $to, $mode);
    }

    public static function applyReissueDate(Builder $query, string $dateType, string $from, string $to, string $mode): Builder
    {
        if ($dateType === 'issue') {
            if ($mode === 'opening') {
                return $query->whereRaw('COALESCE(re_issue_date, DATE(created_at)) < ?', [$from]);
            }

            return $query->whereRaw('COALESCE(re_issue_date, DATE(created_at)) >= ?', [$from])
                ->whereRaw('COALESCE(re_issue_date, DATE(created_at)) <= ?', [$to]);
        }
        if ($dateType === 'flight') {
            return self::applyDate($query, 'inbound_date', $from, $to, $mode);
        }

        return self::applyDate($query, 'outbound_date', $from, $to, $mode);
    }

    public static function applyRefundDate(Builder $query, string $dateType, string $from, string $to, string $mode): Builder
    {
        if ($dateType === 'issue') {
            if ($mode === 'opening') {
                return $query->whereRaw('COALESCE(refund_date, DATE(created_at)) < ?', [$from]);
            }

            return $query->whereRaw('COALESCE(refund_date, DATE(created_at)) >= ?', [$from])
                ->whereRaw('COALESCE(refund_date, DATE(created_at)) <= ?', [$to]);
        }
        if ($dateType === 'flight') {
            return self::applyDate($query, 'inbound_date', $from, $to, $mode);
        }

        return self::applyDate($query, 'outbound_date', $from, $to, $mode);
    }

    public static function applyTicketSearch(Builder $query, string $search): Builder
    {
        return $query->where(function ($q) use ($search) {
            $q->where('ticket_number', 'like', "%{$search}%")
                ->orWhere('pnr', 'like', "%{$search}%")
                ->orWhereHas('passenger', function ($qq) use ($search) {
                    $qq->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('passport_no', 'like', "%{$search}%");
                })
                ->orWhereHas('booking', function ($qq) use ($search) {
                    $qq->where('invoice_id', 'like', "%{$search}%");
                })
                ->orWhereHas('booking.customer', function ($qq) use ($search) {
                    $qq->where('name', 'like', "%{$search}%");
                });
        });
    }

    public static function applyReissueSearch(Builder $query, string $search): Builder
    {
        return $query->where(function ($q) use ($search) {
            $q->where('ticket_number', 'like', "%{$search}%")
                ->orWhere('pnr', 'like', "%{$search}%")
                ->orWhereHas('issuedTicket.passenger', function ($qq) use ($search) {
                    $qq->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('passport_no', 'like', "%{$search}%");
                })
                ->orWhereHas('issuedTicket.booking', function ($qq) use ($search) {
                    $qq->where('invoice_id', 'like', "%{$search}%");
                })
                ->orWhereHas('issuedTicket.booking.customer', function ($qq) use ($search) {
                    $qq->where('name', 'like', "%{$search}%");
                });
        });
    }

    public static function applyRefundSearch(Builder $query, string $search): Builder
    {
        return $query->where(function ($q) use ($search) {
            $q->where('ticket_number', 'like', "%{$search}%")
                ->orWhere('pnr', 'like', "%{$search}%")
                ->orWhereHas('issuedTicket.passenger', function ($qq) use ($search) {
                    $qq->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('passport_no', 'like', "%{$search}%");
                })
                ->orWhereHas('issuedTicket.booking', function ($qq) use ($search) {
                    $qq->where('invoice_id', 'like', "%{$search}%");
                })
                ->orWhereHas('issuedTicket.booking.customer', function ($qq) use ($search) {
                    $qq->where('name', 'like', "%{$search}%");
                });
        });
    }

    public static function applyPaymentSearch(Builder $query, string $search): Builder
    {
        return $query->where(function ($q) use ($search) {
            $q->whereHas('vouchers', function ($qq) use ($search) {
                $qq->where('voucher_id', 'like', "%{$search}%");
            })
                ->orWhereHas('booking', function ($qq) use ($search) {
                    $qq->where('invoice_id', 'like', "%{$search}%");
                });
        });
    }

    // ---------------- display helpers ----------------

    /**
     * Sector text for a fare route, using the same convention as
     * Passenger::formatRouteDisplay so fare-based and fallback values match.
     */
    public static function routeDisplay(?Route $route): string
    {
        if (! $route) {
            return '-';
        }

        $routeType = $route->route_type instanceof \BackedEnum ? $route->route_type->value : (string) $route->route_type;

        if ($routeType === 'multi_city') {
            if ($route->multiSegments && $route->multiSegments->count() > 0) {
                return $route->multiSegments
                    ->map(fn ($s) => ($s->fromCity?->code ?? '?').'-'.($s->toCity?->code ?? '?'))
                    ->implode(', ');
            }

            return '-';
        }

        $from = $route->fromCity?->code ?? '-';
        $to = $route->toCity?->code ?? '-';
        $return = $route->returnCity?->code ?? '';

        if ($routeType === 'round' && $return) {
            return "{$from}-{$to}-{$return}";
        }

        return "{$from}-{$to}";
    }

    // ---------------- aggregates ----------------

    /**
     * @return array<int, float> sums keyed by ticket_agent_id.
     */
    public static function sumByAgent(Builder $query, string $column): array
    {
        return $query->groupBy('ticket_agent_id')
            ->selectRaw('ticket_agent_id, SUM('.$column.') as total')
            ->pluck('total', 'ticket_agent_id')
            ->map(fn ($v) => (float) $v)
            ->all();
    }

    /**
     * @return array<int, array<string, float>> sums keyed by ticket_agent_id then date (Y-m-d).
     */
    public static function dailySumsByAgent(Builder $query, string $dateSql, string $column): array
    {
        $rows = $query->selectRaw('ticket_agent_id, DATE('.$dateSql.') as d, SUM('.$column.') as total')
            ->groupBy('ticket_agent_id')
            ->groupByRaw('DATE('.$dateSql.')')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $out[$row->ticket_agent_id][$row->d] = (float) $row->total;
        }

        return $out;
    }

    // ---------------- statement SQL pagination ----------------
    //
    // One UNION ALL over the four event tables carries every numeric the
    // report needs; page rows, summary, openings and counts all derive from
    // it, so filters can never drift between them. cat_order encodes the PHP
    // strcmp category order (Payment < Re-issue < Refund < Ticket) as integers
    // to stay byte-order identical on every database collation. Deltas use
    // COALESCE so NULL amounts behave exactly like the PHP (float) casts.

    public const STATEMENT_PER_PAGE = 50;

    /**
     * Effective event-date SQL expression per table alias and date type.
     */
    private static function eventDateExpr(string $alias, string $event, string $dateType): string
    {
        if ($dateType === 'flight') {
            return "{$alias}.inbound_date";
        }
        if ($dateType === 'return') {
            return "{$alias}.outbound_date";
        }

        return match ($event) {
            'reissue' => "COALESCE({$alias}.re_issue_date, DATE({$alias}.created_at))",
            'refund' => "COALESCE({$alias}.refund_date, DATE({$alias}.created_at))",
            'payment' => "{$alias}.payment_date",
            default => "{$alias}.issued_date",
        };
    }

    /**
     * Offer-aware pay expression mirroring offerAwarePay(): the row's fare
     * decides offer-vs-regular, the row carries the amounts.
     */
    private static function offerPayExpr(string $alias): string
    {
        return "CASE WHEN (SELECT tf.ticket_type FROM ticket_fares tf WHERE tf.id = {$alias}.ticket_fare_id) = 'offer' "
            ."AND COALESCE({$alias}.offer_price, 0) > 0 THEN {$alias}.offer_price "
            .'ELSE COALESCE('."{$alias}.selling_fare, 0) END";
    }

    private static function applyStatementScope($query, string $agentColumn, string $dateExpr, string $from, string $to, string $mode, $agentId): void
    {
        if ($mode === 'opening') {
            $query->whereRaw("({$dateExpr}) < ?", [$from]);
        } else {
            $query->whereRaw("({$dateExpr}) >= ? AND ({$dateExpr}) <= ?", [$from, $to]);
        }
        if ($agentId) {
            $query->where($agentColumn, $agentId);
        }
    }

    private static function statementTicketBranch(string $dateType, string $from, string $to, string $mode, ?string $search, $agentId)
    {
        $dateExpr = self::eventDateExpr('t', 'ticket', $dateType);
        $payExpr = self::offerPayExpr('t');
        $query = DB::table('issued_tickets AS t')
            ->selectRaw('COALESCE(t.ticket_agent_id, 0) AS agent_id')
            ->selectRaw("{$dateExpr} AS sort_date")
            ->selectRaw("4 AS cat_order, 'Ticket' AS kind, t.id AS sort_id, t.id AS row_id")
            ->selectRaw('-COALESCE(t.net_fare, 0) AS delta')
            ->selectRaw("{$payExpr} AS pay")
            ->selectRaw('COALESCE(t.net_fare, 0) AS fare')
            ->selectRaw("ROUND(({$payExpr}) - COALESCE(t.net_fare, 0), 2) AS markup")
            ->selectRaw('NULL AS cust_refund, NULL AS iata, NULL AS paid_amt')
            ->whereIn('t.status', self::TICKET_STATUSES)
            ->whereNull('t.deleted_at');
        self::applyStatementScope($query, 't.ticket_agent_id', $dateExpr, $from, $to, $mode, $agentId);
        if ($search) {
            $like = "%{$search}%";
            $query->whereRaw(
                '(t.ticket_number LIKE ? OR t.pnr LIKE ? '
                .'OR EXISTS (SELECT 1 FROM passengers p WHERE p.id = t.passenger_id AND (p.first_name LIKE ? OR p.last_name LIKE ? OR p.passport_no LIKE ?)) '
                .'OR EXISTS (SELECT 1 FROM bookings b WHERE b.id = t.booking_id AND b.invoice_id LIKE ?) '
                .'OR EXISTS (SELECT 1 FROM bookings b2 JOIN customers c ON c.id = b2.customer_id WHERE b2.id = t.booking_id AND c.name LIKE ?))',
                [$like, $like, $like, $like, $like, $like, $like]
            );
        }

        return $query;
    }

    private static function statementReissueBranch(string $dateType, string $from, string $to, string $mode, ?string $search, $agentId)
    {
        $dateExpr = self::eventDateExpr('r', 'reissue', $dateType);
        $payExpr = self::offerPayExpr('r');
        $query = DB::table('re_issued_tickets AS r')
            ->selectRaw('COALESCE(r.ticket_agent_id, 0) AS agent_id')
            ->selectRaw("{$dateExpr} AS sort_date")
            ->selectRaw("2 AS cat_order, 'Re-issue' AS kind, r.id AS sort_id, r.id AS row_id")
            ->selectRaw('-COALESCE(r.total_cost, 0) AS delta')
            ->selectRaw("{$payExpr} AS pay")
            ->selectRaw('COALESCE(r.total_cost, 0) AS fare')
            ->selectRaw('COALESCE(r.service_charge, 0) AS markup')
            ->selectRaw('NULL AS cust_refund, NULL AS iata, NULL AS paid_amt')
            ->whereNull('r.deleted_at');
        self::applyStatementScope($query, 'r.ticket_agent_id', $dateExpr, $from, $to, $mode, $agentId);
        if ($search) {
            $like = "%{$search}%";
            $query->whereRaw(
                '(r.ticket_number LIKE ? OR r.pnr LIKE ? '
                .'OR EXISTS (SELECT 1 FROM issued_tickets it JOIN passengers p ON p.id = it.passenger_id WHERE it.id = r.issued_ticket_id AND (p.first_name LIKE ? OR p.last_name LIKE ? OR p.passport_no LIKE ?)) '
                .'OR EXISTS (SELECT 1 FROM issued_tickets it JOIN bookings b ON b.id = it.booking_id WHERE it.id = r.issued_ticket_id AND b.invoice_id LIKE ?) '
                .'OR EXISTS (SELECT 1 FROM issued_tickets it JOIN bookings b2 ON b2.id = it.booking_id JOIN customers c ON c.id = b2.customer_id WHERE it.id = r.issued_ticket_id AND c.name LIKE ?))',
                [$like, $like, $like, $like, $like, $like, $like]
            );
        }

        return $query;
    }

    private static function statementRefundBranch(string $dateType, string $from, string $to, string $mode, ?string $search, $agentId)
    {
        $dateExpr = self::eventDateExpr('f', 'refund', $dateType);
        $payExpr = self::offerPayExpr('f');
        $query = DB::table('refunded_tickets AS f')
            ->selectRaw('COALESCE(f.ticket_agent_id, 0) AS agent_id')
            ->selectRaw("{$dateExpr} AS sort_date")
            ->selectRaw("3 AS cat_order, 'Refund' AS kind, f.id AS sort_id, f.id AS row_id")
            ->selectRaw('COALESCE(f.iata_refunded_amount, 0) AS delta')
            ->selectRaw("{$payExpr} AS pay")
            ->selectRaw('NULL AS fare')
            ->selectRaw('COALESCE(f.service_charge, 0) AS markup')
            ->selectRaw('COALESCE(f.refund_to_customer, 0) AS cust_refund')
            ->selectRaw('COALESCE(f.iata_refunded_amount, 0) AS iata')
            ->selectRaw('NULL AS paid_amt')
            ->whereNull('f.deleted_at');
        self::applyStatementScope($query, 'f.ticket_agent_id', $dateExpr, $from, $to, $mode, $agentId);
        if ($search) {
            $like = "%{$search}%";
            $query->whereRaw(
                '(f.ticket_number LIKE ? OR f.pnr LIKE ? '
                .'OR EXISTS (SELECT 1 FROM issued_tickets it JOIN passengers p ON p.id = it.passenger_id WHERE it.id = f.issued_ticket_id AND (p.first_name LIKE ? OR p.last_name LIKE ? OR p.passport_no LIKE ?)) '
                .'OR EXISTS (SELECT 1 FROM issued_tickets it JOIN bookings b ON b.id = it.booking_id WHERE it.id = f.issued_ticket_id AND b.invoice_id LIKE ?) '
                .'OR EXISTS (SELECT 1 FROM issued_tickets it JOIN bookings b2 ON b2.id = it.booking_id JOIN customers c ON c.id = b2.customer_id WHERE it.id = f.issued_ticket_id AND c.name LIKE ?))',
                [$like, $like, $like, $like, $like, $like, $like]
            );
        }

        return $query;
    }

    private static function statementPaymentBranch(string $from, string $to, string $mode, ?string $search, $agentId)
    {
        $query = DB::table('payments AS p')
            ->selectRaw('COALESCE(p.ticket_agent_id, 0) AS agent_id')
            ->selectRaw('p.payment_date AS sort_date')
            ->selectRaw("1 AS cat_order, 'Payment' AS kind, p.id AS sort_id, p.id AS row_id")
            ->selectRaw('COALESCE(p.amount, 0) AS delta')
            ->selectRaw('NULL AS pay, NULL AS fare, NULL AS markup, NULL AS cust_refund, NULL AS iata')
            ->selectRaw('COALESCE(p.amount, 0) AS paid_amt')
            ->whereNotNull('p.ticket_agent_id');
        self::applyStatementScope($query, 'p.ticket_agent_id', 'p.payment_date', $from, $to, $mode, $agentId);
        if ($search) {
            $like = "%{$search}%";
            $query->whereRaw(
                '(EXISTS (SELECT 1 FROM vouchers v WHERE v.payment_id = p.id AND v.voucher_id LIKE ?) '
                .'OR EXISTS (SELECT 1 FROM bookings b WHERE b.id = p.booking_id AND b.invoice_id LIKE ?))',
                [$like, $like]
            );
        }

        return $query;
    }

    /**
     * Period or opening event union. Column order is positional and identical
     * across branches: agent_id, sort_date, cat_order, kind, sort_id, row_id,
     * delta, pay, fare, markup, cust_refund, iata, paid_amt.
     */
    public static function statementEvents(string $dateType, string $from, string $to, string $mode, ?string $search = null, $agentId = null)
    {
        $union = self::statementTicketBranch($dateType, $from, $to, $mode, $search, $agentId)
            ->unionAll(self::statementReissueBranch($dateType, $from, $to, $mode, $search, $agentId))
            ->unionAll(self::statementRefundBranch($dateType, $from, $to, $mode, $search, $agentId));
        if ($dateType === 'issue') {
            $union->unionAll(self::statementPaymentBranch($from, $to, $mode, $search, $agentId));
        }

        return $union;
    }

    /**
     * Global report order: agent-major (as the old sections), then the
     * chronological ledger order inside each agent. Unknown agents sort by
     * the same fallback label the API exposes.
     */
    private const ORDER = 'agent_name, agent_id, sort_date, cat_order, sort_id';

    private const AGENT_LABEL = "COALESCE(ta.name, 'Unknown agent')";

    /**
     * Same order for use inside window definitions, where SELECT aliases are
     * invisible to MySQL — the label expression is inlined instead.
     */
    private const ORDER_WINDOW = "COALESCE(ta.name, 'Unknown agent'), agent_id, sort_date, cat_order, sort_id";

    /**
     * One page of events with per-agent running balances and global neighbour
     * flags. Windows evaluate over the full filtered set before the page
     * filter applies, so balances and flags stay exact on every page — even
     * when an oversized agent is sliced across pages.
     */
    public static function statementPage(string $dateType, string $from, string $to, ?string $search, $agentId, array $agentIds, int $offset, int $limit)
    {
        $inner = DB::query()->fromSub(self::statementEvents($dateType, $from, $to, 'period', $search, $agentId), 'u')
            ->leftJoin('ticket_agents AS ta', 'ta.id', '=', 'u.agent_id')
            ->selectRaw('u.*')
            ->selectRaw(self::AGENT_LABEL.' AS agent_name')
            ->selectRaw('SUM(delta) OVER (PARTITION BY agent_id ORDER BY sort_date, cat_order, sort_id ROWS UNBOUNDED PRECEDING) AS running')
            ->selectRaw('LAG(agent_id) OVER (ORDER BY '.self::ORDER_WINDOW.') AS prev_agent')
            ->selectRaw('LEAD(agent_id) OVER (ORDER BY '.self::ORDER_WINDOW.') AS next_agent');

        return DB::query()->fromSub($inner, 'w')
            ->whereIn('agent_id', $agentIds)
            ->orderByRaw(self::ORDER)
            ->offset(max(0, $offset))
            ->limit($limit)
            ->get();
    }

    /**
     * Per-agent row counts in report order, driving whole-agent page assembly.
     * Ordered by grouped expressions only — MySQL ONLY_FULL_GROUP_BY rejects
     * ordering grouped rows by non-aggregated event columns. Intra-agent row
     * order comes from statementPage(), which keeps the full ordering.
     *
     * @return array<int, object> with agent_id and n, ordered as displayed.
     */
    public static function statementAgentRowCounts(string $dateType, string $from, string $to, ?string $search, $agentId): array
    {
        return DB::query()->fromSub(self::statementEvents($dateType, $from, $to, 'period', $search, $agentId), 'u')
            ->leftJoin('ticket_agents AS ta', 'ta.id', '=', 'u.agent_id')
            ->selectRaw('u.agent_id')
            ->selectRaw(self::AGENT_LABEL.' AS agent_name')
            ->selectRaw('COUNT(*) AS n')
            ->groupBy('u.agent_id')
            ->groupByRaw(self::AGENT_LABEL)
            ->orderByRaw('agent_name, agent_id')
            ->get()
            ->all();
    }

    public static function statementCount(string $dateType, string $from, string $to, ?string $search, $agentId): int
    {
        return (int) DB::query()->fromSub(self::statementEvents($dateType, $from, $to, 'period', $search, $agentId), 'u')->count();
    }

    /**
     * Whole-range summary from a single aggregate pass over the period union.
     */
    public static function statementSummary(string $dateType, string $from, string $to, ?string $search, $agentId)
    {
        return DB::query()->fromSub(self::statementEvents($dateType, $from, $to, 'period', $search, $agentId), 'u')
            ->selectRaw("ROUND(SUM(CASE WHEN kind = 'Ticket' THEN pay ELSE 0 END), 2) AS total_sale_amount")
            ->selectRaw("ROUND(SUM(CASE WHEN kind = 'Ticket' THEN fare ELSE 0 END), 2) AS total_agent_fare")
            ->selectRaw("ROUND(SUM(CASE WHEN kind IN ('Ticket', 'Re-issue', 'Refund') THEN markup ELSE 0 END), 2) AS total_markup")
            ->selectRaw('ROUND(SUM(COALESCE(cust_refund, 0)), 2) AS total_customer_refund')
            ->selectRaw('ROUND(SUM(COALESCE(iata, 0)), 2) AS total_agent_refund')
            ->selectRaw("ROUND(SUM(CASE WHEN kind = 'Re-issue' THEN fare ELSE 0 END), 2) AS total_reissue_cost")
            ->selectRaw('ROUND(SUM(COALESCE(paid_amt, 0)), 2) AS total_paid')
            ->selectRaw("SUM(CASE WHEN kind = 'Ticket' THEN 1 ELSE 0 END) AS total_tickets")
            ->first();
    }

    /**
     * Per-agent period charges/credits driving each agent's closing balance.
     *
     * @return array<int, object> keyed by agent_id with charges/credits.
     */
    public static function statementAgentPeriod(string $dateType, string $from, string $to, ?string $search, $agentId): array
    {
        return DB::query()->fromSub(self::statementEvents($dateType, $from, $to, 'period', $search, $agentId), 'u')
            ->selectRaw('agent_id')
            ->selectRaw('SUM(COALESCE(fare, 0)) AS charges')
            ->selectRaw('SUM(COALESCE(paid_amt, 0)) + SUM(COALESCE(iata, 0)) AS credits')
            ->selectRaw('COUNT(*) AS row_count')
            ->groupBy('agent_id')
            ->get()
            ->keyBy('agent_id')
            ->all();
    }

    /**
     * Per-agent pre-range opening deltas.
     *
     * @return array<int, float> keyed by agent_id.
     */
    public static function statementAgentOpening(string $dateType, string $from, string $to, ?string $search, $agentId): array
    {
        return DB::query()->fromSub(self::statementEvents($dateType, $from, $to, 'opening', $search, $agentId), 'u')
            ->selectRaw('agent_id, SUM(delta) AS opening')
            ->groupBy('agent_id')
            ->get()
            ->mapWithKeys(fn ($row) => [$row->agent_id => (float) $row->opening])
            ->all();
    }
}
