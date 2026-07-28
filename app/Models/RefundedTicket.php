<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RefundedTicket extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id', 'ticket_agent_id', 'ticket_fare_id', 'group_ticket_id', 'issued_ticket_id',
        'ticket_number', 'pnr',
        'refund_date', 'inbound_date', 'outbound_date',
        'selling_fare', 'net_fare', 'offer_price',
        'is_refundable', 'is_exchangeable',
        'baggage_inbound', 'baggage_outbound',
        'iata_refunded_amount', 'refund_to_customer', 'service_charge',
        'payment_by',
        'reason_id', 'remarks',
    ];

    protected $casts = [
        'refund_date' => 'date',
        'inbound_date' => 'date',
        'outbound_date' => 'date',
        'selling_fare' => 'decimal:6',
        'net_fare' => 'decimal:6',
        'offer_price' => 'decimal:6',
        'iata_refunded_amount' => 'decimal:6',
        'refund_to_customer' => 'decimal:6',
        'service_charge' => 'decimal:6',
        'is_refundable' => 'boolean',
        'is_exchangeable' => 'boolean',

    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function ticketAgent(): BelongsTo
    {
        return $this->belongsTo(TicketAgent::class);
    }

    public function ticketFare(): BelongsTo
    {
        return $this->belongsTo(TicketFare::class);
    }

    public function groupTicket(): BelongsTo
    {
        return $this->belongsTo(GroupTicket::class);
    }

    public function issuedTicket(): BelongsTo
    {
        return $this->belongsTo(IssuedTicket::class);
    }

    public function reason(): BelongsTo
    {
        return $this->belongsTo(ReIssueRefundReason::class, 'reason_id');
    }
}
