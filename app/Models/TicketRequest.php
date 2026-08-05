<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketRequest extends Model
{
    protected $fillable = [
        'user_id',
        'request_branch_id',
        'booking_id',
        'passenger_id',
        'issued_ticket_id',
        'request_type',
        'status',
        'ticket_option',
        'probable_date_up',
        'probable_date_down',
        'visa_expiry_date',
        'remark',
        'requested_at',
        'processed_at',
        'rejected_at',
        'result_re_issued_ticket_id',
        'result_refunded_ticket_id',
        'result_issued_ticket_id',
    ];

    protected $casts = [
        'requested_at' => 'datetime',
        'processed_at' => 'datetime',
        'rejected_at' => 'datetime',
        'probable_date_up' => 'date',
        'probable_date_down' => 'date',
        'visa_expiry_date' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function requestBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'request_branch_id');
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function passenger(): BelongsTo
    {
        return $this->belongsTo(Passenger::class);
    }

    public function issuedTicket(): BelongsTo
    {
        return $this->belongsTo(IssuedTicket::class);
    }

    public function resultReIssuedTicket(): BelongsTo
    {
        return $this->belongsTo(ReIssuedTicket::class, 'result_re_issued_ticket_id');
    }

    public function resultRefundedTicket(): BelongsTo
    {
        return $this->belongsTo(RefundedTicket::class, 'result_refunded_ticket_id');
    }

    public function resultIssuedTicket(): BelongsTo
    {
        return $this->belongsTo(IssuedTicket::class, 'result_issued_ticket_id');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }
}
