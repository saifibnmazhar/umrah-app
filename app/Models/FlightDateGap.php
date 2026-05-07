<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FlightDateGap extends Model
{
    protected $fillable = [
        'gap',
    ];

    protected $casts = [
        'gap' => 'integer',
    ];

    public static function getDefault(): ?self
    {
        return static::first();
    }

    public static function getOrCreate(int $defaultGap = 30): self
    {
        return static::first() ?? static::create(['gap' => $defaultGap]);
    }
}