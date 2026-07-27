<?php

namespace App\Models;

use App\Enums\ReasonOf;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReIssueRefundReason extends Model
{
    protected $fillable = [
        'reason_of',
        'name',
    ];

    protected $casts = [
        'reason_of' => ReasonOf::class,
    ];

    public function reIssuedTickets(): HasMany
    {
        return $this->hasMany(ReIssuedTicket::class, 'reason_id');
    }

    public function refundedTickets(): HasMany
    {
        return $this->hasMany(RefundedTicket::class, 'reason_id');
    }
}
