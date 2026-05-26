<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VisaSellingPrice extends Model
{
    protected $fillable = [
        'selling_price',
        'user_id',
    ];

    protected $casts = [
        'selling_price' => 'decimal:2',
    ];

    protected $appends = ['is_locked'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function packages(): HasMany
    {
        return $this->hasMany(Package::class, 'visa_selling_price_id');
    }

    public function visaSubmissions(): HasMany
    {
        return $this->hasMany(VisaSubmission::class, 'visa_selling_price_id');
    }

    public function getIsLockedAttribute(): bool
    {
        return ($this->packages_count ?? 0) > 0 || ($this->visa_submissions_count ?? 0) > 0;
    }

    public function isLocked(): bool
    {
        return $this->packages()->exists() || $this->visaSubmissions()->exists();
    }
}
