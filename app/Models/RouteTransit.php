<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RouteTransit extends Model
{
    protected $fillable = [
        'route_id',
        'transit_city_id',
        'transit_time',
    ];

    protected $casts = [
        'transit_time' => 'integer',
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