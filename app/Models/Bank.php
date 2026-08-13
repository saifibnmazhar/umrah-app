<?php

namespace App\Models;

use App\Enums\Currency;
use App\Enums\Location;
use Illuminate\Database\Eloquent\Model;

class Bank extends Model
{
    protected $fillable = ['name', 'description', 'currency', 'location'];

    protected $casts = [
        'currency' => Currency::class,
        'location' => Location::class,
    ];
}
