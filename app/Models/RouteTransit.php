<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Enums\RouteDirection;

class RouteTransit extends Model
{
    protected $fillable = [
        'route_id',
        'transit_city_id',
        'transit_time',
        'route_direction',
    ];

    protected $casts = [
        'transit_time' => 'integer',
        'route_direction' => RouteDirection::class,
    ];

    public function route(): BelongsTo
    {
        return $this->belongsTo(Route::class);
    }

    public function transitCity(): BelongsTo
    {
        return $this->belongsTo(CityCode::class, 'transit_city_id');
    }
}