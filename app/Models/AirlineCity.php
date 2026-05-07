<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AirlineCity extends Model
{
    protected $table = 'airline_cities';

    protected $fillable = ['airline_id', 'city_code_id'];

    public function airline(): BelongsTo
    {
        return $this->belongsTo(Airline::class);
    }

    public function cityCode(): BelongsTo
    {
        return $this->belongsTo(CityCode::class, 'city_code_id');
    }
}