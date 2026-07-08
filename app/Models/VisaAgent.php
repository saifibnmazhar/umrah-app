<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class VisaAgent extends Model
{
    protected $fillable = [
        'name',
        'address',
        'contacts',
    ];

    public function visaSubmissions(): HasMany
    {
        return $this->hasMany(VisaSubmission::class);
    }

    public function cancelledSubmissions(): HasMany
    {
        return $this->hasMany(CancelledSubmission::class);
    }

    public function commissionAgents(): HasMany
    {
        return $this->hasMany(CommissionAgent::class);
    }

    public function visaAgentCost(): HasOne
    {
        return $this->hasOne(VisaAgentCost::class);
    }
}
