<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Enums\IqamaType;

class Customer extends Model
{
    protected $fillable = [
        'name',
        'iqama_type',
        'passport_no',
        'iqama_no',
        'mobile_no',
        'ref_iqama_no',
        'ref_mobile_no',
        'ref_iqama_doc',
        'address',
    ];

    protected $casts = [
        'iqama_type' => IqamaType::class,
    ];

    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'owner');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }
}
