<?php

namespace App\Models;

use App\Enums\TicketType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

class TicketFare extends Model
{
    protected $fillable = [
        'airline_id',
        'airline_classes_id',
        'route_id',
        'route_type',
        'ticket_type',
        'effective_from',
        'effective_to',
        'net_fare',
        'selling_fare',
        'offer_price',
        'child_fare_percentage',
        'infant_fare_percentage',
        'with_meal',
        'user_id',
        'is_active',
    ];

    protected $casts = [
        'ticket_type' => TicketType::class,
        'effective_from' => 'date',
        'effective_to' => 'date',
        'net_fare' => 'decimal:6',
        'selling_fare' => 'decimal:6',
        'offer_price' => 'decimal:6',
        'child_fare_percentage' => 'decimal:2',
        'infant_fare_percentage' => 'decimal:2',
        'with_meal' => 'boolean',
        'is_active' => 'boolean',
    ];

    protected $appends = ['is_locked'];

    public function airline(): BelongsTo
    {
        return $this->belongsTo(Airline::class);
    }

    public function airlineClass(): BelongsTo
    {
        return $this->belongsTo(AirlineClass::class, 'airline_classes_id');
    }

    public function route(): BelongsTo
    {
        return $this->belongsTo(Route::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function groupTicket(): HasOne
    {
        return $this->hasOne(GroupTicket::class);
    }

    public function baggageAllowances(): HasMany
    {
        return $this->hasMany(BaggageAllowance::class);
    }

    public function packages(): HasMany
    {
        return $this->hasMany(Package::class, 'ticket_fare_id');
    }

    public function passengers(): HasMany
    {
        return $this->hasMany(Passenger::class, 'ticket_fare_id');
    }

    public function issuedTickets(): HasMany
    {
        return $this->hasMany(IssuedTicket::class, 'ticket_fare_id');
    }

    public function updateLogs(): HasMany
    {
        return $this->hasMany(TicketFareUpdateLog::class);
    }

    /**
     * Value of any fare column (child/infant percentage, selling_fare,
     * offer_price, …) that was in effect at the given moment, reconstructed by
     * replaying ticket_fare_update_logs.
     *
     * Falls back to the live row value when there is no log history for the
     * column. That fallback is exact for percentages and in-use selling/offer
     * prices: those columns were uneditable before the update-logs table
     * existed, so for fares with no recorded edit, live equals historic.
     */
    public function valueAt(string $column, \DateTimeInterface|string $at): float
    {
        $logs = $this->relationLoaded('updateLogs')
            ? $this->updateLogs
            : $this->updateLogs()->orderBy('created_at')->get();

        $events = $logs
            ->filter(fn ($log) => $log->created_at)
            ->sortBy('created_at')
            ->values()
            ->map(fn ($log) => [
                'action' => $log->action,
                'old_values' => $log->old_values ?? [],
                'new_values' => $log->new_values ?? [],
                'created_at' => $log->created_at->toDateTimeString(),
            ])
            ->all();

        return static::percentageAtFromLogs($events, $column, $at, (float) $this->{$column});
    }

    public function percentageAt(string $column, string $at): float
    {
        return $this->valueAt($column, $at);
    }

    /**
     * Pure log-replay used by valueAt(): seed the value from the start of
     * recorded history (the created-event snapshot, else the value just before
     * the first recorded change), then apply each update up to and including $at.
     *
     * Note: uses array_key_exists() — never isset()/empty() — because 0 and
     * "70.00" are legitimate percentage values.
     *
     * @param  array<int, array{action: string, old_values: array, new_values: array, created_at: string}>  $events  chronological
     */
    public static function percentageAtFromLogs(array $events, string $column, \DateTimeInterface|string $at, float $fallback): float
    {
        $anchor = Carbon::parse($at);

        // --- STEP 1: seed — value in effect at the start of recorded history ---
        $seed = null;
        foreach ($events as $event) {
            if ($event['action'] === 'created' && array_key_exists($column, $event['new_values'])) {
                $seed = (float) $event['new_values'][$column];
                break;
            }
            if ($event['action'] === 'updated' && array_key_exists($column, $event['old_values'])) {
                $seed = (float) $event['old_values'][$column];
                break;
            }
        }

        // --- STEP 2: forward walk — apply changes up to and including $at ---
        $value = $seed ?? $fallback;
        foreach ($events as $event) {
            if (Carbon::parse($event['created_at'])->gt($anchor)) {
                break;
            }
            if ($event['action'] === 'updated' && array_key_exists($column, $event['new_values'])) {
                $value = (float) $event['new_values'][$column];
            }
        }

        return $value;
    }

    public function getIsLockedAttribute(): bool
    {
        return ($this->packages_count ?? 0) > 0 || ($this->passengers_count ?? 0) > 0;
    }

    public function isLocked(): bool
    {
        return $this->packages()->exists()
            || Package::where('ticket_fare_inbound_id', $this->id)->exists()
            || Package::where('ticket_fare_outbound_id', $this->id)->exists()
            || $this->passengers()->exists()
            || Passenger::where('ticket_fare_inbound_id', $this->id)->exists()
            || Passenger::where('ticket_fare_outbound_id', $this->id)->exists();
    }

    protected static function booted()
    {
        static::deleting(function (TicketFare $ticketFare) {
            $ticketFare->baggageAllowances()->delete();
            $ticketFare->groupTicket()?->delete();
        });
    }
}
