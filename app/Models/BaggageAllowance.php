<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Enums\PassengerType;
use App\Enums\TravelDirection;

class BaggageAllowance extends Model
{
    protected $fillable = [
        'ticket_fare_id',
        'passenger_type',
        'travel_direction',
        'allowance',
    ];

    protected $casts = [
        'passenger_type' => PassengerType::class,
        'travel_direction' => TravelDirection::class,
    ];

    public function ticketFare(): BelongsTo
    {
        return $this->belongsTo(TicketFare::class);
    }
}