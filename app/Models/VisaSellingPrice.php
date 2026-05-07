<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VisaSellingPrice extends Model
{
    protected $fillable = [
        'selling_price',
        'user_id',
    ];

    protected $casts = [
        'selling_price' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
