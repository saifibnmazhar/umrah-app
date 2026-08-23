<?php

namespace App\Models;

use App\Enums\CancelledBookingStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CancelledPassenger extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'booking_id',
        'passenger_id',
        'invoice_id',
        'user_id',
        'package_value',
        'additional_ticket_value',
        'total_passenger_due',
        'visa_cost',
        'ticket_cost',
        'service_charge_deduction',
        'refundable_amount',
        'balance_adjusted_amount',
        'refund_amount',
        'cancellation_branch_id',
        'status',
        'deduction_payment_id',
        'deduction_voucher_id',
        'refund_payment_id',
        'refund_voucher_id',
        'confirmed_by_id',
        'reverted_by_id',
    ];

    protected $casts = [
        'package_value' => 'decimal:6',
        'additional_ticket_value' => 'decimal:6',
        'total_passenger_due' => 'decimal:6',
        'visa_cost' => 'decimal:6',
        'ticket_cost' => 'decimal:6',
        'service_charge_deduction' => 'decimal:6',
        'refundable_amount' => 'decimal:6',
        'balance_adjusted_amount' => 'decimal:6',
        'refund_amount' => 'decimal:6',
        'status' => CancelledBookingStatus::class,
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function passenger(): BelongsTo
    {
        return $this->belongsTo(Passenger::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function cancellationBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'cancellation_branch_id');
    }

    public function deductionPayment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'deduction_payment_id');
    }

    public function deductionVoucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class, 'deduction_voucher_id');
    }

    public function refundPayment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'refund_payment_id');
    }

    public function refundVoucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class, 'refund_voucher_id');
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by_id');
    }

    public function revertedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reverted_by_id');
    }
}
