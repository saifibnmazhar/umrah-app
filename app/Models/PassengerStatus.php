<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PassengerStatus extends Model
{
    protected $fillable = [
        'name',
        'description',
    ];

    public function passengers(): HasMany
    {
        return $this->hasMany(Passenger::class);
    }
}