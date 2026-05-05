<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GroupTicket extends Model
{
    protected $fillable = [
        'ticket_fare_id',
        'inbound_date',
        'outbound_date',
        'pnr',
        'ticket_qty',
        'is_refundable',
        'is_exchangable',
    ];

    protected $casts = [
        'inbound_date' => 'date',
        'outbound_date' => 'date',
        'ticket_qty' => 'integer',
        'is_refundable' => 'boolean',
        'is_exchangable' => 'boolean',
    ];

    public function ticketFare(): BelongsTo
    {
        return $this->belongsTo(TicketFare::class);
    }
}