<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FingerprintCharge extends Model
{
    protected $fillable = [
        'district_id',
        'user_id',
        'fingerprint_charge',
    ];

    protected $casts = [
        'fingerprint_charge' => 'decimal:6',
    ];

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
