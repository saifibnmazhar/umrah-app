<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
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
}
