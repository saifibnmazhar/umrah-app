<?php

namespace App\Models;

use App\Enums\VisaStatus;
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
        'net_visa_cost',
        'additional_cost',
        'final_cost',
        'remarks',
        'status',
    ];

    protected $casts = [
        'agent_commission' => 'decimal:2',
        'net_visa_cost' => 'decimal:2',
        'additional_cost' => 'decimal:2',
        'final_cost' => 'decimal:2',
        'is_cancelled' => 'boolean',
        'status' => VisaStatus::class,
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