<?php

namespace App\Models;

use App\Enums\SegmentDirection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RouteMultiSegment extends Model
{
    protected $fillable = [
        'route_id',
        'from_city_id',
        'to_city_id',
        'segment_direction',
    ];

    protected $casts = [
        'segment_direction' => SegmentDirection::class,
    ];

    public function route(): BelongsTo
    {
        return $this->belongsTo(Route::class);
    }

    public function fromCity(): BelongsTo
    {
        return $this->belongsTo(CityCode::class, 'from_city_id');
    }

    public function toCity(): BelongsTo
    {
        return $this->belongsTo(CityCode::class, 'to_city_id');
    }
}
