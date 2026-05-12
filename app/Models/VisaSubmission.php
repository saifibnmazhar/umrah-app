<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class VisaSubmission extends Model
{
    protected $fillable = [
        'passenger_id',
        'visa_agent_id',
        'commission_agent_id',
        'agent_commission',
        'visa_selling_price_id',
        'visa_number',
        'is_cancelled',
    ];

    protected $casts = [
        'agent_commission' => 'decimal:2',
        'is_cancelled' => 'boolean',
    ];

    public function passenger(): BelongsTo
    {
        return $this->belongsTo(Passenger::class);
    }

    public function visaAgent(): BelongsTo
    {
        return $this->belongsTo(VisaAgent::class);
    }

    public function commissionAgent(): BelongsTo
    {
        return $this->belongsTo(CommissionAgent::class);
    }

    public function visaSellingPrice(): BelongsTo
    {
        return $this->belongsTo(VisaSellingPrice::class);
    }

    public function cancelledSubmission(): HasOne
    {
        return $this->hasOne(CancelledSubmission::class);
    }
}