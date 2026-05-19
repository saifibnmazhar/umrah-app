<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use App\Enums\Gender;
use App\Enums\PassengerType;
use App\Enums\ServiceRequired;
use App\Enums\TicketStatus;
use App\Enums\VisaStatus;

class Passenger extends Model
{
    protected $fillable = [
        'booking_id',
        'passenger_status_id',
        'first_name',
        'last_name',
        'passport_no',
        'mobile_no',
        'date_of_birth',
        'gender',
        'passenger_type',
        'passport_expiry',
        'stay_duration',
        'service_required',
        'flight_date_from',
        'flight_date_to',
        'actual_flight_date',
        'ticket_status',
        'visa_status',
        'address',
        'ticket_fare_id',
        'package_value',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'passport_expiry' => 'date',
        'flight_date_from' => 'date',
        'flight_date_to' => 'date',
        'actual_flight_date' => 'date',
        'stay_duration' => 'integer',
        'gender' => Gender::class,
        'passenger_type' => PassengerType::class,
        'service_required' => ServiceRequired::class,
        'ticket_status' => TicketStatus::class,
        'visa_status' => VisaStatus::class,
        'package_value' => 'decimal:2',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(PassengerStatus::class, 'passenger_status_id');
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'owner');
    }

    public function ticketFare(): BelongsTo
    {
        return $this->belongsTo(TicketFare::class);
    }
}