<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PassengerUpdateLog extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'passenger_id',
        'user_id',
        'action',
        'old_values',
        'new_values',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
    ];

    public function passenger(): BelongsTo
    {
        return $this->belongsTo(Passenger::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
