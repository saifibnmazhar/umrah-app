<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FingerprintCostLog extends Model
{
    protected $fillable = [
        'fingerprint_id',
        'cost',
        'cost_updated_by',
    ];

    protected $casts = [
        'cost' => 'decimal:6',
    ];

    public function fingerprint(): BelongsTo
    {
        return $this->belongsTo(Fingerprint::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cost_updated_by');
    }
}
