<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Airline extends Model
{
    protected $fillable = ['name', 'code'];

    public function cityCodes(): BelongsToMany
    {
        return $this->belongsToMany(CityCode::class, 'airline_cities');
    }

    public function travelClasses(): BelongsToMany
    {
        return $this->belongsToMany(TravelClass::class, 'airline_classes', 'airline_id', 'class_id');
    }
}