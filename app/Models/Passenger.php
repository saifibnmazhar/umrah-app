<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Enums\FingerprintStatus;
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
        'address',
        'ticket_fare_id',
        'package_value',
        'is_ticket_held',
        'ticket_held_by',
        'ticket_held_at',
        'ticket_remarks',
        'ticket_fare_inbound_id',
        'ticket_fare_outbound_id',
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
        'package_value' => 'decimal:6',
        'is_ticket_held' => 'boolean',
        'ticket_held_at' => 'datetime',
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

    public function ticketFareInbound(): BelongsTo
    {
        return $this->belongsTo(TicketFare::class, 'ticket_fare_inbound_id');
    }

    public function ticketFareOutbound(): BelongsTo
    {
        return $this->belongsTo(TicketFare::class, 'ticket_fare_outbound_id');
    }

    public function visaSubmission()
    {
        return $this->hasOne(VisaSubmission::class)->latestOfMany();
    }

    public function fingerprintDetail(): HasOne
    {
        return $this->hasOne(FingerprintDetail::class);
    }

    public function issuedTickets(): HasMany
    {
        return $this->hasMany(IssuedTicket::class);
    }

    public function allIssuedTickets(): HasMany
    {
        return $this->hasMany(IssuedTicket::class);
    }

    public function latestIssuedTicket(): HasOne
    {
        return $this->hasOne(IssuedTicket::class)
            ->ofMany(['id' => 'MAX'], function ($query) {
                $query
                    ->where(function ($q) {
                        $q->whereNull('issue_type')
                          ->orWhere('issue_type', 'regular');
                    });
            });
    }

    public function getRouteDisplayAttribute(): string
    {
        if ($this->ticket_fare_inbound_id) {
            $inboundRoute = $this->formatRouteDisplay($this->ticketFareInbound?->route);
            $outboundRoute = $this->formatRouteDisplay($this->ticketFareOutbound?->route);
            return ($inboundRoute ?: '?') . "\n" . ($outboundRoute ?: '?');
        }

        return $this->formatRouteDisplay($this->ticketFare?->route);
    }

    private function formatRouteDisplay($route): string
    {
        if (!$route) return '-';

        $routeType = $route->route_type?->value;

        if ($routeType === 'multi_city') {
            if ($route->multiSegments && $route->multiSegments->count() > 0) {
                return $route->multiSegments
                    ->map(fn($s) => ($s->fromCity?->code ?? '?') . '-' . ($s->toCity?->code ?? '?'))
                    ->implode(', ');
            }
            return '-';
        }

        $from = $route->fromCity?->code ?? '-';
        $to = $route->toCity?->code ?? '-';
        $return = $route->returnCity?->code ?? '';

        if ($routeType === 'round' && $return) {
            return "{$from}-{$to}-{$return}";
        }

        return "{$from}-{$to}";
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
        if ($this->ticket_fare_inbound_id) {
            $passengerType = $this->passenger_type?->value;
            $inboundAllowances = $this->ticketFareInbound?->baggageAllowances ?? collect();
            $outboundAllowances = $this->ticketFareOutbound?->baggageAllowances ?? collect();

            $inboundBag = $inboundAllowances
                ->filter(fn($a) => ($a->passenger_type?->value ?? $a->passenger_type) === $passengerType)
                ->first()?->allowance;

            $outboundBag = $outboundAllowances
                ->filter(fn($a) => ($a->passenger_type?->value ?? $a->passenger_type) === $passengerType)
                ->first()?->allowance;

            $parts = [];
            if ($inboundBag !== null) $parts[] = "In: {$inboundBag}";
            if ($outboundBag !== null) $parts[] = "Out: {$outboundBag}";
            return empty($parts) ? '-' : implode("\n", $parts);
        }

        $ticketFare = $this->ticketFare;
        if (!$ticketFare) return '-';

        $routeType = $ticketFare->route?->route_type?->value;
        $passengerType = $this->passenger_type?->value;
        $allowances = $ticketFare->baggageAllowances;

        $inboundAllowances = $allowances->filter(fn($a) => $a->travel_direction?->value === 'inbound');
        $outboundAllowances = $allowances->filter(fn($a) => $a->travel_direction?->value === 'outbound');

        $inboundBag = $inboundAllowances
            ->filter(fn($a) => ($a->passenger_type?->value ?? $a->passenger_type) === $passengerType)
            ->first()?->allowance;

        $outboundBag = $outboundAllowances
            ->filter(fn($a) => ($a->passenger_type?->value ?? $a->passenger_type) === $passengerType)
            ->first()?->allowance;

        if ($routeType === 'oneway_inbound') {
            return $inboundBag !== null ? "In: {$inboundBag}" : '-';
        } elseif ($routeType === 'oneway_outbound') {
            return $outboundBag !== null ? "Out: {$outboundBag}" : '-';
        } elseif (in_array($routeType, ['round', 'multi_city'])) {
            $parts = [];
            if ($inboundBag !== null) $parts[] = "In: {$inboundBag}";
            if ($outboundBag !== null) $parts[] = "Out: {$outboundBag}";
            return empty($parts) ? '-' : implode("\n", $parts);
        }

        return '-';
    }

    public function getMealDisplayAttribute(): string
    {
        if ($this->ticket_fare_inbound_id) {
            $inbound = $this->ticketFareInbound?->with_meal === true ? 'Yes' : 'No';
            $outbound = $this->ticketFareOutbound?->with_meal === true ? 'Yes' : 'No';
            return "In: {$inbound}\nOut: {$outbound}";
        }
        return $this->ticketFare?->with_meal === true ? 'Yes' : 'No';
    }

    public function getFlightTypeDisplayAttribute(): string
    {
        return $this->ticketFare?->route?->flight_type?->value ?? '-';
    }

    public function getAirlineDisplayAttribute(): string
    {
        if ($this->ticket_fare_inbound_id) {
            $inbound = $this->ticketFareInbound?->airline?->name ?? '-';
            $outbound = $this->ticketFareOutbound?->airline?->name ?? '-';
            return "In: {$inbound}\nOut: {$outbound}";
        }
        return $this->ticketFare?->airline?->name ?? '-';
    }

    public function getClassDisplayAttribute(): string
    {
        if ($this->ticket_fare_inbound_id) {
            $inbound = $this->ticketFareInbound?->airlineClass?->class?->name ?? '-';
            $outbound = $this->ticketFareOutbound?->airlineClass?->class?->name ?? '-';
            return "In: {$inbound}\nOut: {$outbound}";
        }
        return $this->ticketFare?->airlineClass?->class?->name ?? '-';
    }

    public function getTripDisplayAttribute(): string
    {
        $routeType = $this->ticketFare?->route?->route_type?->value;
        return match ($routeType) {
            'oneway_outbound' => 'Out Bound',
            'oneway_inbound' => 'In Bound',
            'round' => 'Round Trip',
            'multi_city' => 'Multi City',
            default => '-',
        };
    }

    public function getComputedStatusAttribute(): ?string
    {
        $fpStatus = $this->fingerprintDetail?->status?->value;
        $visaStatus = $this->visaSubmission?->status?->value;
        $ticketStatus = $this->ticket_status?->value;
        $issuedTicketStatus = $this->latestIssuedTicket?->status;

        $isFingerprintApproved = $fpStatus === FingerprintStatus::APPROVED->value;
        $isVisaSubmitted = $visaStatus === VisaStatus::SUBMITTED->value;
        $isVisaIssued = $visaStatus === VisaStatus::ISSUED->value;
        $isVisaCancelled = $visaStatus === VisaStatus::CANCELLED->value;
        $isTicketIssued = in_array($ticketStatus, ['issued', 're-issued'])
            || in_array($issuedTicketStatus, ['issued', 're-issued']);

        if ($isTicketIssued && $isVisaIssued) return 'Ticket Issued';
        if ($isVisaCancelled) return 'Processing';
        if ($isTicketIssued && !$isVisaIssued) return 'Ticket Issued before Visa';
        if ($isVisaIssued) return 'Visa Issued';
        if ($isVisaSubmitted) return 'Visa Submitted';
        if ($isFingerprintApproved) return 'Fingerprint Done';

        return null;
    }

    public function updateLogs(): HasMany
    {
        return $this->hasMany(PassengerUpdateLog::class);
    }

    public function syncComputedStatus(): void
    {
        $statusName = $this->computed_status;

        $statusId = null;
        if ($statusName) {
            $statusId = PassengerStatus::firstOrCreate(['name' => $statusName])->id;
        }

        if ($this->passenger_status_id !== $statusId) {
            $this->passenger_status_id = $statusId;
            $this->saveQuietly();
        }
    }
}
