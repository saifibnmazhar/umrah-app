<?php

namespace App\Models;

use App\Enums\Currency;
use Illuminate\Database\Eloquent\Model;

class Bank extends Model
{
    protected $fillable = ['name', 'description', 'currency'];

    protected $casts = [
        'currency' => Currency::class,
    ];
}