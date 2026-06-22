<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use App\Enums\FingerprintLocation;
use App\Enums\DiscountType;

class Booking extends Model
{
    protected $fillable = [
        'user_id',
        'customer_id',
        'fingerprint_branch_id',
        'district_id',
        'package_id',
        'fingerprint_charge_id',
        'booking_branch_id',
        'invoice_id',
        'date_gap_id',
        'fingerprint_location',
        'pax_qty',
        'discount_type',
        'discount_value',
        'discount_amount',
        'total_value',
        'remarks',
        'currency_rate_id',
    ];

    protected $casts = [
        'fingerprint_location' => FingerprintLocation::class,
        'discount_type' => DiscountType::class,
        'discount_value' => 'decimal:6',
        'discount_amount' => 'decimal:6',
        'total_value' => 'decimal:6',
        'pax_qty' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function fingerprintBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'fingerprint_branch_id');
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function fingerprintCharge(): BelongsTo
    {
        return $this->belongsTo(FingerprintCharge::class);
    }

    public function bookingBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'booking_branch_id');
    }

    public function dateGap(): BelongsTo
    {
        return $this->belongsTo(FlightDateGap::class, 'date_gap_id');
    }

    public function passengers(): HasMany
    {
        return $this->hasMany(Passenger::class);
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'owner');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function fingerprint(): HasOne
    {
        return $this->hasOne(Fingerprint::class);
    }

    public function currencyRate(): BelongsTo
    {
        return $this->belongsTo(CurrencyRate::class);
    }
}