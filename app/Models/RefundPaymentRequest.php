<?php

namespace App\Models;

use App\Enums\RefundPaymentRequestStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RefundPaymentRequest extends Model
{
    protected $fillable = [
        'passenger_id',
        'booking_id',
        'branch_id',
        'status',
        'refund_payable_snapshot',
        'assigned_by',
        'confirmed_by',
        'payment_id',
        'voucher_id',
        'remarks',
        'assigned_at',
        'confirmed_at',
        'reverted_at',
    ];

    protected $casts = [
        'status' => RefundPaymentRequestStatus::class,
        'refund_payable_snapshot' => 'decimal:6',
        'assigned_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'reverted_at' => 'datetime',
    ];

    public function passenger(): BelongsTo
    {
        return $this->belongsTo(Passenger::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }
}
