<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AirlineClass extends Model
{
    protected $table = 'airline_classes';

    protected $fillable = ['airline_id', 'class_id'];

    public function airline(): BelongsTo
    {
        return $this->belongsTo(Airline::class);
    }

    public function travelClass(): BelongsTo
    {
        return $this->belongsTo(TravelClass::class, 'class_id');
    }

    public function class(): BelongsTo
    {
        return $this->belongsTo(TravelClass::class, 'class_id');
    }
}
