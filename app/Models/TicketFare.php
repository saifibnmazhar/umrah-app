<?php

namespace App\Models;

use App\Enums\TicketType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

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
        'is_active',
    ];

    protected $casts = [
        'ticket_type' => TicketType::class,
        'effective_from' => 'date',
        'effective_to' => 'date',
        'net_fare' => 'decimal:6',
        'selling_fare' => 'decimal:6',
        'offer_price' => 'decimal:6',
        'child_fare_percentage' => 'decimal:2',
        'infant_fare_percentage' => 'decimal:2',
        'with_meal' => 'boolean',
        'is_active' => 'boolean',
    ];

    protected $appends = ['is_locked'];

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

    public function packages(): HasMany
    {
        return $this->hasMany(Package::class, 'ticket_fare_id');
    }

    public function passengers(): HasMany
    {
        return $this->hasMany(Passenger::class, 'ticket_fare_id');
    }

    public function issuedTickets(): HasMany
    {
        return $this->hasMany(IssuedTicket::class, 'ticket_fare_id');
    }

    public function getIsLockedAttribute(): bool
    {
        return ($this->packages_count ?? 0) > 0 || ($this->passengers_count ?? 0) > 0;
    }

    public function isLocked(): bool
    {
        return $this->packages()->exists() || $this->passengers()->exists();
    }

    protected static function booted()
    {
        static::deleting(function (TicketFare $ticketFare) {
            $ticketFare->baggageAllowances()->delete();
            $ticketFare->groupTicket()?->delete();
        });
    }
}
