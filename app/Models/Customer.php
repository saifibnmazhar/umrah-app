<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Enums\IqamaType;

class Customer extends Model
{
    protected $casts = [
        'iqama_type' => IqamaType::class,
    ];
}
