<?php

namespace App\Services;

use App\Models\IssuedTicket;
use App\Models\Payment;
use App\Models\RefundedTicket;
use App\Models\ReIssuedTicket;
use Illuminate\Database\Eloquent\Builder;

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
}
