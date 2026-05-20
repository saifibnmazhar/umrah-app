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

    public function documents()
    {
        return $this->morphMany(Document::class, 'owner');
    }

    public function ticketFare(): BelongsTo
    {
        return $this->belongsTo(TicketFare::class);
    }

    public function getRouteDisplayAttribute(): string
    {
        $route = $this->ticketFare?->route;
        if (!$route) return '-';

        $from = $route->fromCity?->city_code ?? '';
        $to = $route->toCity?->city_code ?? '';
        $return = $route->returnCity?->city_code ?? '';

        $routeType = $this->ticketFare?->route_type?->value;

        if ($routeType === 'oneway_inbound') {
            return "{$from} → {$to}";
        } elseif ($routeType === 'oneway_outbound') {
            return "{$from} → {$to}";
        } elseif ($routeType === 'round') {
            return "{$from} → {$to} → {$return}";
        } elseif ($routeType === 'multi_city') {
            return "{$from} → {$to} → {$return}";
        }

        return "{$from} → {$to}";
    }

    public function getFlightDateDisplayAttribute(): string
    {
        $from = $this->flight_date_from?->format('d M Y') ?? '-';
        $to = $this->flight_date_to?->format('d M Y') ?? '-';

        if ($from === '-' && $to === '-') return '-';
        if ($from === $to) return $from;

        return "{$from} → {$to}";
    }

    public function getBaggageDisplayAttribute(): string
    {
        $ticketFare = $this->ticketFare;
        if (!$ticketFare) return '-';

        $routeType = $ticketFare->route_type?->value;
        $passengerType = $this->passenger_type?->value;
        $allowances = $ticketFare->baggageAllowances;

        $inbound = $allowances->firstWhere('travel_direction', 'inbound');
        $outbound = $allowances->firstWhere('travel_direction', 'outbound');

        $inboundBag = null;
        if ($inbound && ($inbound->passenger_type?->value ?? $inbound->passenger_type) === $passengerType) {
            $inboundBag = $inbound->allowance;
        }

        $outboundBag = null;
        if ($outbound && ($outbound->passenger_type?->value ?? $outbound->passenger_type) === $passengerType) {
            $outboundBag = $outbound->allowance;
        }

        if ($routeType === 'oneway_inbound') {
            return $inboundBag ? "I:{$inboundBag}" : '-';
        } elseif ($routeType === 'oneway_outbound') {
            return $outboundBag ? "O:{$outboundBag}" : '-';
        } elseif (in_array($routeType, ['round', 'multi_city'])) {
            $parts = [];
            if ($inboundBag) $parts[] = "I:{$inboundBag}";
            if ($outboundBag) $parts[] = "O:{$outboundBag}";
            return empty($parts) ? '-' : implode("\n", $parts);
        }

        return '-';
    }

    public function getMealDisplayAttribute(): string
    {
        return $this->ticketFare?->with_meal === true ? 'Yes' : 'No';
    }

    public function getFlightTypeDisplayAttribute(): string
    {
        return $this->ticketFare?->route?->flight_type?->value ?? '-';
    }
}