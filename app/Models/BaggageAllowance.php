<?php

namespace App\Models;

use App\Enums\PassengerType;
use App\Enums\TravelDirection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
