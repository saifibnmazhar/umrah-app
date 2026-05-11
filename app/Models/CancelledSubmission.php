<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CancelledSubmission extends Model
{
    protected $fillable = [
        'visa_submission_id',
        'cancellation_fee',
    ];

    protected $casts = [
        'cancellation_fee' => 'decimal:2',
    ];

    public function visaSubmission(): BelongsTo
    {
        return $this->belongsTo(VisaSubmission::class);
    }
}