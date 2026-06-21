<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Package extends Model
{
    protected $fillable = [
        'package_name',
        'ticket_fare_id',
        'visa_selling_price_id',
        'regular_price',
        'offer_price',
        'service_charge',
        'is_active',
    ];

    protected $casts = [
        'regular_price' => 'decimal:6',
        'offer_price' => 'decimal:6',
        'service_charge' => 'decimal:6',
        'is_active' => 'boolean',
    ];

    protected $appends = ['is_locked'];

    public function ticketFare(): BelongsTo
    {
        return $this->belongsTo(TicketFare::class);
    }

    public function visaSellingPrice(): BelongsTo
    {
        return $this->belongsTo(VisaSellingPrice::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function getIsLockedAttribute(): bool
    {
        return ($this->bookings_count ?? 0) > 0;
    }

    public function isLocked(): bool
    {
        return $this->bookings()->exists();
    }
}