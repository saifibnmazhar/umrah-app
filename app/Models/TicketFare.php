<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Enums\TicketType;

class TicketFare extends Model
{
    protected $fillable = [
        'airline_id',
        'airline_classes_id',
        'route_id',
        'route_type',
        'ticket_type',
        'effective_from',
        'effective_to',
        'net_fare',
        'selling_fare',
        'offer_price',
        'child_fare_percentage',
        'infant_fare_percentage',
        'with_meal',
        'user_id',
    ];

    protected $casts = [
        'ticket_type' => TicketType::class,
        'effective_from' => 'date',
        'effective_to' => 'date',
        'net_fare' => 'decimal:2',
        'selling_fare' => 'decimal:2',
        'offer_price' => 'decimal:2',
        'child_fare_percentage' => 'decimal:2',
        'infant_fare_percentage' => 'decimal:2',
        'with_meal' => 'boolean',
    ];

    public function airline(): BelongsTo
    {
        return $this->belongsTo(Airline::class);
    }

    public function airlineClass(): BelongsTo
    {
        return $this->belongsTo(AirlineClass::class, 'airline_classes_id');
    }

    public function route(): BelongsTo
    {
        return $this->belongsTo(Route::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function groupTicket(): HasOne
    {
        return $this->hasOne(GroupTicket::class);
    }

    public function baggageAllowances(): HasMany
    {
        return $this->hasMany(BaggageAllowance::class);
    }
}