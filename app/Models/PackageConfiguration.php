<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PackageConfiguration extends Model
{
    protected $fillable = [
        'package_name',
        'ticket_fare_id',
        'regular_price',
        'offer_price',
        'package_status',
    ];

    protected $casts = [
        'regular_price' => 'decimal:2',
        'offer_price' => 'decimal:2',
    ];

    public function ticketFare()
    {
        return $this->belongsTo(TicketFare::class, 'ticket_fare_id');
    }
}
