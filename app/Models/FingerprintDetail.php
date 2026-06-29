<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Enums\FingerprintStatus;

class FingerprintDetail extends Model
{
    protected $fillable = [
        'fingerprint_id',
        'passenger_id',
        'status',
    ];

    protected $casts = [
        'status' => FingerprintStatus::class,
    ];

    public function fingerprint(): BelongsTo
    {
        return $this->belongsTo(Fingerprint::class);
    }

    public function passenger(): BelongsTo
    {
        return $this->belongsTo(Passenger::class);
    }

    public function rescheduledFingerprints(): HasMany
    {
        return $this->hasMany(RescheduledFingerprint::class);
    }

    public function fingerprintDetailLogs(): HasMany
    {
        return $this->hasMany(FingerprintDetailLog::class);
    }

    public function approvedLog(): HasOne
    {
        return $this->hasOne(FingerprintDetailLog::class)
            ->where('action', 'status_updated')
            ->where('new_values->status', 'approved')
            ->latest('created_at');
    }
}