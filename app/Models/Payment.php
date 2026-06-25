<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
    ];

    protected $casts = [
        'payment_date' => 'date',
        'amount' => 'decimal:6',
        'bdt_amount' => 'decimal:6',
        'payment_method' => \App\Enums\PaymentMethod::class,
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
}