<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Enums\RescheduleReason;

class RescheduledFingerprint extends Model
{
    protected $table = 'rescheduled_fingerprints';

    protected $fillable = [
        'fingerprint_detail_id',
        'reason',
        'other_reason',
        'next_date',
        'occurrence',
        'remarks',
    ];

    protected $casts = [
        'next_date' => 'date',
        'occurrence' => 'integer',
        'reason' => RescheduleReason::class,
    ];

    public function fingerprintDetail(): BelongsTo
    {
        return $this->belongsTo(FingerprintDetail::class);
    }
}