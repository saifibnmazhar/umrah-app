<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class IssuedTicket extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'passenger_id', 'booking_id', 'user_id',
        'ticket_agent_id', 'ticket_fare_id', 'group_ticket_id',
        'ticket_number', 'pnr',
        'issued_date', 'inbound_date', 'outbound_date',
        'selling_fare', 'net_fare', 'offer_price',
        'is_refundable', 'is_exchangeable',
        'baggage_inbound', 'baggage_outbound',
        'outbound_pending',
        'issue_type', 'status',
    ];

    protected $casts = [
        'issued_date' => 'date',
        'inbound_date' => 'date',
        'outbound_date' => 'date',
        'selling_fare' => 'decimal:6',
        'net_fare' => 'decimal:6',
        'offer_price' => 'decimal:6',
        'is_refundable' => 'boolean',
        'is_exchangeable' => 'boolean',
        'outbound_pending' => 'boolean',
    ];

    public function passenger(): BelongsTo
    {
        return $this->belongsTo(Passenger::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
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

    public function logs(): HasMany
    {
        return $this->hasMany(IssuedTicketLog::class);
    }

    public function reIssuedTickets(): HasMany
    {
        return $this->hasMany(ReIssuedTicket::class, 'issued_ticket_id');
    }

    public function latestReIssuedTicket(): HasOne
    {
        return $this->hasOne(ReIssuedTicket::class, 'issued_ticket_id')->latestOfMany('id');
    }

    public function refundedTickets(): HasMany
    {
        return $this->hasMany(RefundedTicket::class, 'issued_ticket_id');
    }

    public function latestRefundedTicket(): HasOne
    {
        return $this->hasOne(RefundedTicket::class, 'issued_ticket_id')->latestOfMany('id');
    }

    public function logAction(string $action, ?array $oldData, ?array $newData): void
    {
        $this->logs()->create([
            'user_id' => auth()->id(),
            'action' => $action,
            'old_data' => $oldData,
            'new_data' => $newData,
        ]);
    }
}
