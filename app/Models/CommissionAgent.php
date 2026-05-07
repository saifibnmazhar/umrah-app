<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommissionAgent extends Model
{
    protected $fillable = [
        'visa_agent_id',
        'name',
        'address',
        'contacts',
    ];

    public function visaAgent(): BelongsTo
    {
        return $this->belongsTo(VisaAgent::class);
    }
}
