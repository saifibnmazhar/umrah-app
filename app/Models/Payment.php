<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Payment extends Model
{
    protected $fillable = [
        'invoice_id',
        'booking_id',
        'branch_id',
        'user_id',
        'currency_rate_id',
        'bank_id',
        'sender_bank_id',
        'other_sender_bank',
        'receiver_bank',
        'ticket_agent_id',
        'visa_agent_id',
        'commission_agent_id',
        'payment_date',
        'payment_method',
        'transaction_id',
        'amount',
        'bdt_amount',
        'notes',
        'remarks',
        'payment_referral',
        'cancelled_booking_id',
        'passenger_id',
        'refunded_ticket_id',
        're_issued_ticket_id',
        'cancelled_passenger_id',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'amount' => 'decimal:6',
        'bdt_amount' => 'decimal:6',
        'payment_method' => PaymentMethod::class,
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function currencyRate(): BelongsTo
    {
        return $this->belongsTo(CurrencyRate::class);
    }

    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
    }

    public function senderBank(): BelongsTo
    {
        return $this->belongsTo(Bank::class, 'sender_bank_id');
    }

    public function ticketAgent(): BelongsTo
    {
        return $this->belongsTo(TicketAgent::class);
    }

    public function visaAgent(): BelongsTo
    {
        return $this->belongsTo(VisaAgent::class);
    }

    public function commissionAgent(): BelongsTo
    {
        return $this->belongsTo(CommissionAgent::class);
    }

    public function vouchers(): HasMany
    {
        return $this->hasMany(Voucher::class);
    }

    public function voucher(): HasOne
    {
        return $this->hasOne(Voucher::class)->latestOfMany();
    }

    public function cancelledBooking(): BelongsTo
    {
        return $this->belongsTo(CancelledBooking::class);
    }

    public function cancelledPassenger(): BelongsTo
    {
        return $this->belongsTo(CancelledPassenger::class);
    }

    public function passenger(): BelongsTo
    {
        return $this->belongsTo(Passenger::class);
    }

    public function refundedTicket(): BelongsTo
    {
        return $this->belongsTo(RefundedTicket::class);
    }

    public function reIssuedTicket(): BelongsTo
    {
        return $this->belongsTo(ReIssuedTicket::class);
    }
}
