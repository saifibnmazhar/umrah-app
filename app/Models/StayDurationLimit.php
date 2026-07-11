<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StayDurationLimit extends Model
{
    protected $fillable = [
        'min_days',
        'max_days',
    ];

    protected $casts = [
        'min_days' => 'integer',
        'max_days' => 'integer',
    ];

    public static function getOrCreate(int $min = 1, int $max = 85): self
    {
        return static::first() ?? static::create([
            'min_days' => $min,
            'max_days' => $max,
        ]);
    }
}
