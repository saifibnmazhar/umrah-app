<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class CityCode extends Model
{
    protected $table = 'city_codes';

    protected $fillable = ['city_name', 'code', 'country'];

    public function airlines(): BelongsToMany
    {
        return $this->belongsToMany(Airline::class, 'airline_cities');
    }
}