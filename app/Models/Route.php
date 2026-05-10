<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Enums\RouteType;
use App\Enums\FlightType;

class Route extends Model
{
    protected $fillable = [
        'airline_id',
        'route_type',
        'flight_type',
        'from_city_id',
        'to_city_id',
        'return_city_id',
        'additional_gap',
    ];

    protected $casts = [
        'route_type' => RouteType::class,
        'flight_type' => FlightType::class,
    ];

    public function airline(): BelongsTo
    {
        return $this->belongsTo(Airline::class);
    }

    public function fromCity(): BelongsTo
    {
        return $this->belongsTo(CityCode::class, 'from_city_id');
    }

    public function toCity(): BelongsTo
    {
        return $this->belongsTo(CityCode::class, 'to_city_id');
    }

    public function returnCity(): BelongsTo
    {
        return $this->belongsTo(CityCode::class, 'return_city_id');
    }

    public function multiSegments(): HasMany
    {
        return $this->hasMany(RouteMultiSegment::class);
    }

    public function transits(): HasMany
    {
        return $this->hasMany(RouteTransit::class);
    }
}