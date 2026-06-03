<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BookingCondition extends Model
{
    protected $fillable = ['title', 'description', 'is_active', 'sort_order'];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];
}
