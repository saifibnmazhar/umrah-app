<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Branch extends Model
{
    protected $fillable = ['name', 'address', 'contacts', 'location', 'fingerprint_operation', 'branch_code'];

    protected $casts = [
        'location' => \App\Enums\Location::class,
        'fingerprint_operation' => 'boolean',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}