<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Document extends Model
{
    protected $fillable = [
        'owner_type',
        'owner_id',
        'file_path',
        'display_name',
    ];

    public function owner(): MorphTo
    {
        return $this->morphTo();
    }
}
