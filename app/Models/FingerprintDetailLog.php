<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FingerprintDetailLog extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'fingerprint_detail_id',
        'user_id',
        'action',
        'old_values',
        'new_values',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
    ];

    public function fingerprintDetail(): BelongsTo
    {
        return $this->belongsTo(FingerprintDetail::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
