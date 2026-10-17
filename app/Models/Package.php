<?php

namespace App\Models;

use App\Support\LogReplay;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Package extends Model
{
    protected $fillable = [
        'package_name',
        'ticket_fare_id',
        'visa_selling_price_id',
        'regular_price',
        'offer_price',
        'service_charge',
        'is_active',
        'ticket_fare_inbound_id',
        'ticket_fare_outbound_id',
        'is_double_ticket',
    ];

    protected $casts = [
        'regular_price' => 'decimal:6',
        'offer_price' => 'decimal:6',
        'service_charge' => 'decimal:6',
        'is_active' => 'boolean',
        'is_double_ticket' => 'boolean',
    ];

    protected $appends = ['is_locked'];

    public function ticketFare(): BelongsTo
    {
        return $this->belongsTo(TicketFare::class);
    }

    public function visaSellingPrice(): BelongsTo
    {
        return $this->belongsTo(VisaSellingPrice::class);
    }

    public function ticketFareInbound(): BelongsTo
    {
        return $this->belongsTo(TicketFare::class, 'ticket_fare_inbound_id');
    }

    public function ticketFareOutbound(): BelongsTo
    {
        return $this->belongsTo(TicketFare::class, 'ticket_fare_outbound_id');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function updateLogs(): HasMany
    {
        return $this->hasMany(PackageUpdateLog::class);
    }

    /**
     * Value of any package column (service_charge, regular_price,
     * visa_selling_price_id, …) that was in effect at the given moment,
     * reconstructed by replaying package_update_logs.
     *
     * Honors the manually-written `fare_updated` rows (cascade from
     * TicketFareController after updateQuietly suppresses observers) both for
     * seeding and during the walk — see App\Support\LogReplay.
     *
     * Falls back to the live row value when there is no log history for the
     * column (exact for this dataset: locked-package price edits became
     * possible in the same commit as the log tables).
     *
     * @return string|int|float|null raw replayed value — int for FK columns
     *                               (visa_selling_price_id), string for
     *                               decimal-cast prices; null only when a
     *                               logged value was null (callers decide via ??)
     */
    public function valueAt(string $column, \DateTimeInterface|string $at): string|int|float|null
    {
        $logs = $this->relationLoaded('updateLogs')
            ? $this->updateLogs
            : $this->updateLogs()->orderBy('created_at')->get();

        return LogReplay::valueFromEvents(
            LogReplay::eventsFromLogs($logs),
            $column,
            $at,
            $this->{$column}
        );
    }

    public function getIsLockedAttribute(): bool
    {
        if (! array_key_exists('bookings_count', $this->attributes)) {
            return $this->isLocked();
        }

        return $this->bookings_count > 0;
    }

    public function isLocked(): bool
    {
        return $this->bookings()->exists();
    }
}
