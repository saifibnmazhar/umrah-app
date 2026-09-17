# Open Edit Access for In-Use Packages & Ticket Fares

## Goal

Allow editing of packages and ticket fares that are already in use by existing bookings, while preserving historical passenger `package_value` and profit calculations at their original booking-time prices.

## Constraints

1. **Package edit**: Only `service_charge` is editable on locked packages. No fare reference changes (`ticket_fare_id`, `ticket_fare_inbound_id`, `ticket_fare_outbound_id`), no `is_double_ticket` toggle.
2. **Ticket fare edit**: Only `selling_fare` and `offer_price` are editable when the fare is used by packages. Other fields (airline, route, class, net_fare, child/infant percentages) remain locked.
3. **Historic profit**: Passenger profit must always be calculated using the prices that were in effect at booking/issuance time, not current prices.

---

## Part 1: Remove Locks & Restrict Editable Fields

### 1a. `app/Http/Controllers/PackageController.php`

**`edit()` (line 211-213):** Remove the `isLocked()` redirect. Allow editing even when package has bookings.

```php
// REMOVE:
if ($package->isLocked()) {
    return redirect()->route('packages.index')->with('error', 'This package cannot be edited because it has existing bookings.');
}
```

**`update()` (line 225-227):** Remove the `isLocked()` redirect. Add conditional validation — when locked, only allow `package_name` and `service_charge`:

```php
// REMOVE:
if ($package->isLocked()) {
    return redirect()->route('packages.index')->with('error', 'This package cannot be edited because it has existing bookings.');
}

// ADD conditional validation:
$isLocked = $package->isLocked();

$rules = [
    'package_name' => 'required|string|max:255',
];

if (!$isLocked) {
    // Full editing allowed
    $rules['is_double_ticket'] = 'nullable|boolean';
    $rules['regular_price'] = 'required|numeric|min:0';
    $rules['offer_price'] = 'nullable|numeric|min:0';
    $rules['service_charge'] = 'nullable|numeric|min:0';
    // ... fare reference rules ...
} else {
    // Locked: only service_charge
    $rules['service_charge'] = 'nullable|numeric|min:0';
}
```

**`destroy()` (line 296-299):** Keep locked — no change.

### 1b. `app/Http/Controllers/TicketFareController.php`

**`update()` (line 182-192):** Remove the `$hasPackages` early-return. Add conditional validation — when in use, only allow `selling_fare` and `offer_price`:

```php
$hasPackages = $ticketFare->packages()->exists();

// REMOVE the early return:
// if ($hasPackages) {
//     $validated = $request->validate([...]);
//     $ticketFare->update([...]);
//     return redirect(...);
// }

if ($hasPackages) {
    $validated = $request->validate([
        'selling_fare' => 'required|numeric|min:0',
        'offer_price' => 'nullable|numeric|min:0',
    ]);
    $ticketFare->update($validated);
    return redirect()->route('fare.admin', ['tab' => 'fares', 'page' => $request->page])->with('success', 'Ticket fare updated successfully.');
}

// Full validation for non-locked fares (existing logic)...
```

### 1c. `resources/views/ticket-fares/edit.blade.php`

- Remove `$locked` variable (line 17) and the `@php $locked = $hasPackages; @endphp` block.
- Remove all `{{ $locked ? ... : '' }}` conditional disabling throughout the form.
- Add a new `@php $inUse = $hasPackages; @endphp` variable.
- When `$inUse` is true, make all fields except `selling_fare` and `offer_price` display as readonly/disabled.
- Update the submit button text: `{{ $inUse ? 'Update Fare Price' : 'Update Ticket Fare' }}`.

---

## Part 2: Snapshot Historic Prices for Profit Calculation

### Problem

`ProfitCalculationService` currently reads **live** prices from `TicketFare` and `Package`:

| Method | Reads from | Used in |
|--------|-----------|---------|
| `getPackageTicketSellingFare()` (line 746) | `package->ticketFare->selling_fare` / `offer_price` | `calculateTicketProfit()` |
| `calculateServiceCharge()` (line 655) | `package->service_charge` | Profit breakdown |
| `fareSellingPrice()` (line 762) | `fare->selling_fare`, `fare->offer_price` | `calculateAdditionalTicketProfit()` |

If these live values change, profit would recalculate using new prices for old bookings.

### Solution: Snapshot columns on Passenger and IssuedTicket

### 2a. Migration: `add_historical_price_snapshots_to_passengers_table`

```php
Schema::table('passengers', function (Blueprint $table) {
    $table->decimal('fare_selling_fare', 14, 6)->nullable()->after('ticket_fare_outbound_id');
    $table->decimal('booked_service_charge', 14, 6)->nullable()->after('fare_selling_fare');
});
```

- `fare_selling_fare`: Effective fare at booking time (with child/infant multiplier applied). Computed from `package->ticketFare->selling_fare` × passenger type percentage.
- `booked_service_charge`: Package's `service_charge` at booking time.

### 2b. Migration: `add_fare_selling_fare_to_issued_tickets_table`

```php
Schema::table('issued_tickets', function (Blueprint $table) {
    $table->decimal('fare_selling_fare', 14, 6)->nullable()->after('offer_price');
});
```

- `fare_selling_fare`: Fare's selling price at issuance time (with child/infant multiplier applied). Used for additional ticket profit.

### 2c. Backfill Migration

Populate snapshots for all existing passengers and issued tickets:

```php
// Passengers: compute from current fare references
Passenger::with('booking.package', 'ticketFare', 'ticketFareInbound', 'ticketFareOutbound')
    ->chunkById(100, function ($passengers) {
        foreach ($passengers as $passenger) {
            $fareSellingFare = computeEffectiveFare($passenger);
            $bookedServiceCharge = $passenger->booking->package->service_charge ?? null;
            $passenger->updateQuietly([
                'fare_selling_fare' => $fareSellingFare,
                'booked_service_charge' => $bookedServiceCharge,
            ]);
        }
    });

// IssuedTickets: compute from fare reference
DB::statement('UPDATE issued_tickets it
    JOIN passengers p ON p.id = it.passenger_id
    JOIN ticket_fares tf ON tf.id = it.ticket_fare_id
    SET it.fare_selling_fare = CASE
        WHEN tf.ticket_type = "offer" THEN COALESCE(tf.offer_price, tf.selling_fare)
        ELSE tf.selling_fare
    END * CASE
        WHEN p.passenger_type = "child" THEN tf.child_fare_percentage / 100
        WHEN p.passenger_type = "infant" THEN tf.infant_fare_percentage / 100
        ELSE 1
    END
    WHERE it.fare_selling_fare IS NULL');
```

### 2d. `app/Services/BookingService.php` — `recalculateBookingTotal()`

After computing `package_value`, also set snapshot values (only when null to preserve on subsequent recalculations):

```php
public function recalculateBookingTotal(Booking $booking): float
{
    foreach ($booking->passengers as $passenger) {
        $passenger->package_value = $this->calculatePackageValue($passenger);

        // Set historical snapshots only if not already set
        if (is_null($passenger->fare_selling_fare)) {
            $passenger->fare_selling_fare = $this->computeEffectiveFare($passenger);
        }
        if (is_null($passenger->booked_service_charge)) {
            $passenger->booked_service_charge = $passenger->booking->package->service_charge ?? 0;
        }

        $passenger->save();
    }
    // ... rest of method unchanged ...
}
```

Extract fare computation into a new `computeEffectiveFare(Passenger)` method:

```php
private function computeEffectiveFare(Passenger $passenger): float
{
    $package = $passenger->booking->package;
    if (!$package) return 0.0;

    $passengerType = strtolower($passenger->passenger_type instanceof \BackedEnum
        ? $passenger->passenger_type->value
        : $passenger->passenger_type);

    if ($package->is_double_ticket) {
        $inboundFare = $package->ticketFareInbound;
        $outboundFare = $package->ticketFareOutbound;
        $inboundAmount = $inboundFare ? (float) $inboundFare->selling_fare : 0;
        $outboundAmount = $outboundFare ? (float) $outboundFare->selling_fare : 0;
        $baseFare = $inboundAmount + $outboundAmount;
    } else {
        $ticketFare = $passenger->ticketFare;
        if (!$ticketFare) return 0.0;
        $baseFare = $ticketFare->ticket_type === TicketType::OFFER
            ? (float) ($ticketFare->offer_price ?? $ticketFare->selling_fare)
            : (float) $ticketFare->selling_fare;
    }

    return match ($passengerType) {
        'child' => $baseFare * ((float) ($inboundFare->child_fare_percentage ?? 70)) / 100,
        'infant' => $baseFare * ((float) ($inboundFare->infant_fare_percentage ?? 30)) / 100,
        default => $baseFare,
    };
}
```

### 2e. `app/Http/Controllers/TicketIssueController.php` — `issue()`

When issuing a ticket, also set `$ticket->fare_selling_fare`:

```php
// After issuing the ticket:
$fareSellingFare = app(BookingService::class)->computeEffectiveFare($passenger);
$ticket->update(['fare_selling_fare' => $fareSellingFare]);
```

### 2f. `app/Services/ProfitCalculationService.php` — 3 changes

**`getPackageTicketSellingFare()` (line 746):** Use snapshot if available:

```php
private function getPackageTicketSellingFare(Passenger $passenger): float
{
    // Use historical snapshot if available
    if (!is_null($passenger->fare_selling_fare)) {
        return (float) $passenger->fare_selling_fare;
    }

    // Fallback for un-backfilled data
    $package = $passenger->booking->package;
    if (!$package) return 0.0;

    if ($package->is_double_ticket) {
        return $this->fareSellingPrice($package->ticketFareInbound, $passenger)
            + $this->fareSellingPrice($package->ticketFareOutbound, $passenger);
    }

    return $this->fareSellingPrice($package->ticketFare, $passenger);
}
```

**`calculateServiceCharge()` (line 655):** Use snapshot if available:

```php
private function calculateServiceCharge(Passenger $passenger): float
{
    if (!$this->isVisaProfitEffective($passenger) || !$this->isTicketProfitEffective($passenger)) {
        return 0.0;
    }

    // Use historical snapshot if available
    if (!is_null($passenger->booked_service_charge)) {
        return (float) $passenger->booked_service_charge;
    }

    // Fallback for un-backfilled data
    return (float) ($passenger->booking->package->service_charge ?? 0);
}
```

**`calculateAdditionalTicketProfit()` (line 412):** Use snapshot if available:

```php
private function calculateAdditionalTicketProfit(Passenger $passenger): float
{
    return (float) $passenger->allIssuedTickets
        ->filter(fn ($t) => $t->issue_type === 'additional'
            && in_array($t->status, ['issued', 're-issued', 'refunded'], true))
        ->sum(function ($t) use ($passenger) {
            $sellingFare = !is_null($t->fare_selling_fare)
                ? (float) $t->fare_selling_fare
                : $this->fareSellingPrice($t->ticketFare, $passenger);
            return $sellingFare - (float) ($t->net_fare ?? 0);
        });
}
```

---

## Part 3: Update Logs

### 3a. New Model: `app/Models/PackageUpdateLog.php`

Follows the standard `PassengerUpdateLog` pattern:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PackageUpdateLog extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'package_id',
        'user_id',
        'action',
        'old_values',
        'new_values',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
    ];

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

### 3b. New Model: `app/Models/TicketFareUpdateLog.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketFareUpdateLog extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'ticket_fare_id',
        'user_id',
        'action',
        'old_values',
        'new_values',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
    ];

    public function ticketFare(): BelongsTo
    {
        return $this->belongsTo(TicketFare::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

### 3c. Migration: `create_package_update_logs_table`

```php
Schema::create('package_update_logs', function (Blueprint $table) {
    $table->id();
    $table->foreignId('package_id')->constrained('packages')->nullOnDelete();
    $table->foreignId('user_id')->constrained('users');
    $table->string('action');
    $table->json('old_values')->nullable();
    $table->json('new_values')->nullable();
    $table->timestamp('created_at');
    $table->index('package_id');
});
```

### 3d. Migration: `create_ticket_fare_update_logs_table`

```php
Schema::create('ticket_fare_update_logs', function (Blueprint $table) {
    $table->id();
    $table->foreignId('ticket_fare_id')->constrained('ticket_fares')->nullOnDelete();
    $table->foreignId('user_id')->constrained('users');
    $table->string('action');
    $table->json('old_values')->nullable();
    $table->json('new_values')->nullable();
    $table->timestamp('created_at');
    $table->index('ticket_fare_id');
});
```

### 3e. `app/Observers/PackageObserver.php` — extend with logging

```php
<?php

namespace App\Observers;

use App\Enums\CancelledBookingStatus;
use App\Models\Package;
use App\Models\PackageUpdateLog;
use App\Services\ProfitCalculationService;
use Illuminate\Support\Facades\Auth;

class PackageObserver
{
    private const PROFIT_FIELDS = [
        'service_charge',
        'ticket_fare_id',
        'ticket_fare_inbound_id',
        'ticket_fare_outbound_id',
        'is_double_ticket',
    ];

    public function created(Package $package): void
    {
        $user = Auth::user();
        if (!$user) return;

        PackageUpdateLog::create([
            'package_id' => $package->id,
            'user_id' => $user->id,
            'action' => 'created',
            'old_values' => null,
            'new_values' => $package->attributesToArray(),
        ]);
    }

    public function updated(Package $package): void
    {
        if ($package->wasChanged(self::PROFIT_FIELDS)) {
            $package->bookings()
                ->whereDoesntHave('cancelledBooking', fn ($q) => $q->where('status', CancelledBookingStatus::CANCELLED->value))
                ->chunkById(100, function ($bookings): void {
                    $service = app(ProfitCalculationService::class);
                    foreach ($bookings as $booking) {
                        $service->recalculateBookingProfit($booking);
                    }
                });
        }

        $dirty = $package->getDirty();
        if (empty($dirty)) return;

        $user = Auth::user();
        if (!$user) return;

        $original = $package->getOriginal();
        $oldValues = [];
        $newValues = [];
        foreach ($dirty as $key => $newValue) {
            $oldValues[$key] = $original[$key] ?? null;
            $newValues[$key] = $newValue;
        }

        PackageUpdateLog::create([
            'package_id' => $package->id,
            'user_id' => $user->id,
            'action' => 'updated',
            'old_values' => $oldValues,
            'new_values' => $newValues,
        ]);
    }

    public function deleting(Package $package): void
    {
        $user = Auth::user();
        if (!$user) return;

        $oldValues = collect($package->attributesToArray())
            ->except(['created_at', 'updated_at'])
            ->toArray();

        PackageUpdateLog::create([
            'package_id' => $package->id,
            'user_id' => $user->id,
            'action' => 'deleted',
            'old_values' => $oldValues,
            'new_values' => null,
        ]);
    }
}
```

### 3f. New Observer: `app/Observers/TicketFareObserver.php`

```php
<?php

namespace App\Observers;

use App\Models\TicketFare;
use App\Models\TicketFareUpdateLog;
use Illuminate\Support\Facades\Auth;

class TicketFareObserver
{
    public function created(TicketFare $ticketFare): void
    {
        $user = Auth::user();
        if (!$user) return;

        TicketFareUpdateLog::create([
            'ticket_fare_id' => $ticketFare->id,
            'user_id' => $user->id,
            'action' => 'created',
            'old_values' => null,
            'new_values' => $ticketFare->attributesToArray(),
        ]);
    }

    public function updated(TicketFare $ticketFare): void
    {
        $dirty = $ticketFare->getDirty();
        if (empty($dirty)) return;

        $user = Auth::user();
        if (!$user) return;

        $original = $ticketFare->getOriginal();
        $oldValues = [];
        $newValues = [];
        foreach ($dirty as $key => $newValue) {
            $oldValues[$key] = $original[$key] ?? null;
            $newValues[$key] = $newValue;
        }

        TicketFareUpdateLog::create([
            'ticket_fare_id' => $ticketFare->id,
            'user_id' => $user->id,
            'action' => 'updated',
            'old_values' => $oldValues,
            'new_values' => $newValues,
        ]);
    }

    public function deleting(TicketFare $ticketFare): void
    {
        $user = Auth::user();
        if (!$user) return;

        $oldValues = collect($ticketFare->attributesToArray())
            ->except(['created_at', 'updated_at'])
            ->toArray();

        TicketFareUpdateLog::create([
            'ticket_fare_id' => $ticketFare->id,
            'user_id' => $user->id,
            'action' => 'deleted',
            'old_values' => $oldValues,
            'new_values' => null,
        ]);
    }
}
```

### 3g. `app/Models/Package.php` — add relationship

```php
public function updateLogs(): HasMany
{
    return $this->hasMany(PackageUpdateLog::class);
}
```

### 3h. `app/Models/TicketFare.php` — add relationship

```php
public function updateLogs(): HasMany
{
    return $this->hasMany(TicketFareUpdateLog::class);
}
```

### 3i. `app/Providers/AppServiceProvider.php` — register observer

```php
use App\Models\TicketFare;
use App\Observers\TicketFareObserver;

// In boot():
TicketFare::observe(TicketFareObserver::class);
```

---

## Complete File Change List

| File | Action |
|------|--------|
| `app/Http/Controllers/PackageController.php` | Modify — remove lock checks, conditional validation |
| `app/Http/Controllers/TicketFareController.php` | Modify — remove $hasPackages early-return, conditional validation |
| `resources/views/ticket-fares/edit.blade.php` | Modify — remove $locked, conditional readonly for in-use fares |
| `app/Models/PackageUpdateLog.php` | Create |
| `app/Models/TicketFareUpdateLog.php` | Create |
| `database/migrations/xxxx_create_package_update_logs_table.php` | Create |
| `database/migrations/xxxx_create_ticket_fare_update_logs_table.php` | Create |
| `database/migrations/xxxx_add_historical_price_snapshots_to_passengers_table.php` | Create |
| `database/migrations/xxxx_add_fare_selling_fare_to_issued_tickets_table.php` | Create |
| `database/migrations/xxxx_backfill_historical_prices.php` | Create |
| `app/Observers/TicketFareObserver.php` | Create |
| `app/Observers/PackageObserver.php` | Modify — add logging (created/updated/deleting) |
| `app/Models/Package.php` | Modify — add `updateLogs()` |
| `app/Models/TicketFare.php` | Modify — add `updateLogs()` |
| `app/Providers/AppServiceProvider.php` | Modify — register TicketFareObserver |
| `app/Services/BookingService.php` | Modify — snapshot values in `recalculateBookingTotal()`, add `computeEffectiveFare()` |
| `app/Services/ProfitCalculationService.php` | Modify — read from snapshots in 3 methods |
| `app/Http/Controllers/TicketIssueController.php` | Modify — snapshot fare_selling_fare on issuance |

## Execution Order

1. Migrations (schema + backfill)
2. Models (PackageUpdateLog, TicketFareUpdateLog)
3. Observers (TicketFareObserver, update PackageObserver)
4. Relationships (Package, TicketFare, AppServiceProvider)
5. Profit calculation changes (ProfitCalculationService)
6. Snapshot population (BookingService, TicketIssueController)
7. Controller changes (PackageController, TicketFareController)
8. View changes (ticket-fares/edit.blade.php)
