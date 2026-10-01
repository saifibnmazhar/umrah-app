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

    public function scopeNotSupersededByVoid($query): void
    {
        $query->whereNotExists(function ($sub) {
            $sub->selectRaw('1')
                ->from('issued_ticket_logs as void_logs')
                ->whereColumn('void_logs.issued_ticket_id', 'issued_ticket_logs.issued_ticket_id')
                ->where('void_logs.action', 'void')
                ->whereColumn('void_logs.id', '>', 'issued_ticket_logs.id');
        });
    }
}
