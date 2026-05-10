<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Package extends Model
{
    protected $fillable = [
        'package_name',
        'ticket_fare_id',
        'visa_selling_price_id',
        'regular_price',
        'offer_price',
        'service_charge',
    ];

    protected $casts = [
        'regular_price' => 'decimal:2',
        'offer_price' => 'decimal:2',
        'service_charge' => 'decimal:2',
    ];

    public function ticketFare(): BelongsTo
    {
        return $this->belongsTo(TicketFare::class);
    }

    public function visaSellingPrice(): BelongsTo
    {
        return $this->belongsTo(VisaSellingPrice::class);
    }
}