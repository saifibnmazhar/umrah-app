<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TransactionType extends Model
{
    protected $fillable = [
        'name',
        'type',
    ];

    protected $casts = [
        'type' => \App\Enums\TransactionType::class,
    ];

    public function vouchers(): HasMany
    {
        return $this->hasMany(Voucher::class);
    }
}