<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TicketAgent extends Model
{
    protected $fillable = [
        'name',
        'address',
        'contacts',
    ];
}
