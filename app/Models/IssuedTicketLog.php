<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IssuedTicketLog extends Model
{
    protected $fillable = [
        'issued_ticket_id', 'user_id', 'action', 'old_data', 'new_data',
    ];

    protected $casts = [
        'old_data' => 'array',
        'new_data' => 'array',
    ];

    public function issuedTicket(): BelongsTo
    {
        return $this->belongsTo(IssuedTicket::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
