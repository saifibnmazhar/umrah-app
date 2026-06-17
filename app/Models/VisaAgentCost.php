<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VisaAgentCost extends Model
{
    protected $fillable = [
        'visa_agent_id',
        'user_id',
        'visa_agent_cost',
    ];

    protected $casts = [
        'visa_agent_cost' => 'decimal:6',
    ];

    public function visaAgent(): BelongsTo
    {
        return $this->belongsTo(VisaAgent::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
