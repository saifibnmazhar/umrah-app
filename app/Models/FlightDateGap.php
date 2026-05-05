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
}