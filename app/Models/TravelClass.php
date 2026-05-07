<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class TravelClass extends Model
{
    protected $table = 'classes';

    protected $fillable = ['name'];

    public function airlines(): BelongsToMany
    {
        return $this->belongsToMany(Airline::class, 'airline_classes');
    }
}