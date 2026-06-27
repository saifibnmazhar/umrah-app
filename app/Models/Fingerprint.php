<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Fingerprint extends Model
{
    protected $fillable = [
        'booking_id',
        'deadline',
        'cost',
        'assigned_staff_id',
    ];

    protected $casts = [
        'deadline' => 'date',
        'cost' => 'decimal:2',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function assignedStaff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_staff_id');
    }

    public function fingerprintDetails(): HasMany
    {
        return $this->hasMany(FingerprintDetail::class);
    }

    public function costLogs(): HasMany
    {
        return $this->hasMany(FingerprintCostLog::class);
    }

    public function firstCostLog(): HasOne
    {
        return $this->hasOne(FingerprintCostLog::class)->ofMany('created_at', 'min');
    }
}