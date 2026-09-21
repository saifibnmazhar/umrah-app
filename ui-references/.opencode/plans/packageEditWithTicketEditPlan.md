# Open Edit Access for In-Use Packages & Ticket Fares + Fare Snapshot System

## Goal

Allow editing of packages and ticket fares already in use by existing bookings. Preserve historical fare values via snapshots on `issued_tickets.selling_fare`/`offer_price` for profit calculations. Control fare field visibility across all forms.

## Constraints

1. **Package edit**: Only `service_charge` is editable on locked packages. No fare reference changes, no `is_double_ticket` toggle.
2. **Ticket fare edit**: When in use by packages, only `selling_fare`, `offer_price`, `effective_from`, `effective_to` are editable. Other fields remain locked.
3. **Historic profit**: Uses snapshotted `selling_fare`/`offer_price` from `issued_tickets` (not live TicketFare values).
4. **`ticket_fares.net_fare`**: Always 0 — hidden from create/edit forms. Net fare entered by agents at issuance time.
5. **`issued_tickets.selling_fare`/`offer_price`**: Historical snapshot taken at passenger creation, adjusted for passenger_type.

---

## Part 1: Remove Locks & Restrict Editable Fields

### 1a. `app/Http/Controllers/PackageController.php`

**`edit()` (line 211-213):** Remove the `isLocked()` redirect.

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

**`update()` (line 182-192):** Remove the `$hasPackages` early-return. Conditional validation:

```php
$hasPackages = $ticketFare->packages()->exists();

if ($hasPackages) {
    $validated = $request->validate([
        'selling_fare' => 'required|numeric|min:0',
        'offer_price' => 'nullable|numeric|min:0',
        'effective_from' => 'required|date',
        'effective_to' => 'required|date|after_or_equal:effective_from',
    ]);
    $ticketFare->update($validated);
    return redirect()->route('fare.admin', ['tab' => 'fares', 'page' => $request->page])->with('success', 'Ticket fare updated successfully.');
}

// Full validation for non-locked fares...
```

**`store()` (line 65-150):** Remove `net_fare` from validation. Force to 0 in create:

```php
'net_fare' => 0,
```

### 1c. `resources/views/ticket-fares/edit.blade.php`

- Replace `$locked` with `$inUse` for in-use fares
- When `$inUse`: all fields except `selling_fare`, `offer_price`, `effective_from`, `effective_to` are readonly/disabled
- **Hide `net_fare` field entirely** (see Part 7c)

---

## Part 2: Fare Snapshot System

### Design

The existing `issued_tickets.selling_fare` and `issued_tickets.offer_price` columns become the **historical snapshot** of fare values at passenger creation time. The child/infant passenger_type percentage is applied at snapshot time, so the stored values are the final adjusted amounts.

- **Source of snapshot**: `ticket_fares.selling_fare`/`offer_price` via the passenger's `ticket_fare_id`, `ticket_fare_inbound_id`, or `ticket_fare_outbound_id`
- **Snapshot timing**: Passenger creation, package change, passenger_type change
- **Profit calculation reads**: `issued_tickets.selling_fare`/`offer_price` (NOT `ticket_fares`)
- **`ticket_fares.selling_fare`/`offer_price`**: Always editable by admins (rate card)

### 2a. Migration: Add `booking_service_charge` to passengers

```php
Schema::table('passengers', function (Blueprint $table) {
    $table->decimal('booking_service_charge', 14, 6)->default(0)->after('service_charge');
});
```

Add to `Passenger::$fillable`.

### 2b. Backfill Migration

**`passengers.booking_service_charge`:**

```php
DB::table('passengers')
    ->join('bookings', 'passengers.booking_id', '=', 'bookings.id')
    ->join('packages', 'bookings.package_id', '=', 'packages.id')
    ->where('passengers.is_cancelled', false)
    ->update(['passengers.booking_service_charge' => DB::raw('packages.service_charge')]);
```

**`issued_tickets.selling_fare`/`offer_price`** — three cases:

**Case A — Single-ticket, no pending outbound:**
```php
$passengers = Passenger::whereNotNull('ticket_fare_id')
    ->whereNull('ticket_fare_inbound_id')
    ->whereNull('ticket_fare_outbound_id')
    ->whereDoesntHave('issuedTickets', fn($q) => $q->where('issue_type', 'pending_outbound'))
    ->with('ticketFare', 'issuedTickets')
    ->cursor();

foreach ($passengers as $p) {
    $fare = $p->ticketFare;
    if (!$fare) continue;
    $sellingFare = (float) ($fare->selling_fare ?? 0);
    $offerPrice = ($fare->ticket_type === 'offer') ? (float) ($fare->offer_price ?? 0) : 0;
    [$sellingFare, $offerPrice] = $this->adjustFaresForType($sellingFare, $offerPrice, $p, $fare);
    $p->issuedTickets()->whereNull('issue_type')
        ->orWhere('issue_type', 'regular')
        ->update(['selling_fare' => $sellingFare, 'offer_price' => $offerPrice]);
}
```

**Case B — Single-ticket, has pending outbound:**
```php
// Same as A, plus zero out pending_outbound ticket
$pendingOutbound = $p->issuedTickets()->where('issue_type', 'pending_outbound')->first();
if ($pendingOutbound) {
    $pendingOutbound->update(['selling_fare' => 0, 'offer_price' => 0]);
}
```

**Case C — Double-ticket:**
```php
$passengers = Passenger::whereNull('ticket_fare_id')
    ->whereNotNull('ticket_fare_inbound_id')
    ->whereNotNull('ticket_fare_outbound_id')
    ->with('ticketFareInbound', 'ticketFareOutbound', 'issuedTickets')
    ->cursor();

foreach ($passengers as $p) {
    $inboundFare = $p->ticketFareInbound;
    $outboundFare = $p->ticketFareOutbound;

    // Backfill NULL/regular ticket from inbound fare
    $inSelling = $inboundFare ? (float) ($inboundFare->selling_fare ?? 0) : 0;
    $inOffer = ($inboundFare && $inboundFare->ticket_type === 'offer') ? (float) ($inboundFare->offer_price ?? 0) : 0;
    [$inSelling, $inOffer] = $this->adjustFaresForType($inSelling, $inOffer, $p, $inboundFare);
    $p->issuedTickets()->whereNull('issue_type')
        ->orWhere('issue_type', 'regular')
        ->update(['selling_fare' => $inSelling, 'offer_price' => $inOffer]);

    // Create or backfill outbound ticket from outbound fare
    $outSelling = $outboundFare ? (float) ($outboundFare->selling_fare ?? 0) : 0;
    $outOffer = ($outboundFare && $outboundFare->ticket_type === 'offer') ? (float) ($outboundFare->offer_price ?? 0) : 0;
    [$outSelling, $outOffer] = $this->adjustFaresForType($outSelling, $outOffer, $p, $outboundFare);

    $outboundTicket = $p->issuedTickets()->where('issue_type', 'pending_outbound')->first();
    if ($outboundTicket) {
        $outboundTicket->update(['selling_fare' => $outSelling, 'offer_price' => $outOffer]);
    } else {
        IssuedTicket::create([
            'passenger_id' => $p->id,
            'booking_id' => $p->booking_id,
            'user_id' => 1,
            'status' => 'pending',
            'issue_type' => 'pending_outbound',
            'selling_fare' => $outSelling,
            'offer_price' => $outOffer,
        ]);
    }
}
```

**Helper:**
```php
private function adjustFaresForType(float $sellingFare, float $offerPrice, Passenger $p, ?TicketFare $fare): array
{
    if (!$fare) return [$sellingFare, $offerPrice];
    $pct = match($p->passenger_type) {
        'child' => (float) ($fare->child_fare_percentage ?? 70),
        'infant' => (float) ($fare->infant_fare_percentage ?? 30),
        default => 100,
    };
    if ($pct != 100) {
        $sellingFare = round($sellingFare * $pct / 100, 6);
        $offerPrice = round($offerPrice * $pct / 100, 6);
    }
    return [$sellingFare, $offerPrice];
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

## Part 4: Fare Snapshot at Passenger Creation & Package Change

### 4a. `BookingController::store()` — Create issued tickets with fare snapshot

**Current (line 1434-1440):** Creates one IssuedTicket with `selling_fare=0, offer_price=0`.

**New — single-ticket passengers:**

```php
$fare = $booking->package?->ticketFare;
$sellingFare = 0;
$offerPrice = 0;
if ($fare && $passengerType !== 'visa_only') {
    $sellingFare = (float) ($fare->selling_fare ?? 0);
    $offerPrice = ($fare->ticket_type === 'offer') ? (float) ($fare->offer_price ?? 0) : 0;
    [$sellingFare, $offerPrice] = $this->adjustFaresForType($sellingFare, $offerPrice, $passenger, $fare);
}
Passenger::create([
    // ... fields ...
    'booking_service_charge' => $booking->package->service_charge ?? 0,
]);
IssuedTicket::create([
    'booking_id' => $booking->id,
    'passenger_id' => $passenger->id,
    'ticket_fare_id' => $passenger->ticket_fare_id,
    'user_id' => auth()->id(),
    'status' => 'pending',
    'selling_fare' => $sellingFare,
    'offer_price' => $offerPrice,
]);
```

**New — double-ticket passengers (create two tickets):**

```php
$inboundFare = $booking->package?->ticketFareInbound;
$outboundFare = $booking->package?->ticketFareOutbound;

// Inbound (NULL/regular) ticket
$inSelling = $inboundFare ? (float) ($inboundFare->selling_fare ?? 0) : 0;
$inOffer = ($inboundFare && $inboundFare->ticket_type === 'offer') ? (float) ($inboundFare->offer_price ?? 0) : 0;
[$inSelling, $inOffer] = $this->adjustFaresForType($inSelling, $inOffer, $passenger, $inboundFare);

Passenger::create([
    // ...
    'booking_service_charge' => $booking->package->service_charge ?? 0,
]);
IssuedTicket::create([
    'booking_id' => $booking->id,
    'passenger_id' => $passenger->id,
    'ticket_fare_id' => null,
    'user_id' => auth()->id(),
    'status' => 'pending',
    'selling_fare' => $inSelling,
    'offer_price' => $inOffer,
]);

// Outbound (pending_outbound) ticket
$outSelling = $outboundFare ? (float) ($outboundFare->selling_fare ?? 0) : 0;
$outOffer = ($outboundFare && $outboundFare->ticket_type === 'offer') ? (float) ($outboundFare->offer_price ?? 0) : 0;
[$outSelling, $outOffer] = $this->adjustFaresForType($outSelling, $outOffer, $passenger, $outboundFare);

IssuedTicket::create([
    'booking_id' => $booking->id,
    'passenger_id' => $passenger->id,
    'ticket_fare_id' => null,
    'user_id' => auth()->id(),
    'status' => 'pending',
    'issue_type' => 'pending_outbound',
    'selling_fare' => $outSelling,
    'offer_price' => $outOffer,
]);
```

### 4b. `BookingController::addPassenger()` — Same pattern as `store()`

### 4c. `BookingController::update()` — Package change handling (lines 1892-1919)

After updating passenger fare IDs, iterate over affected passengers and update snapshots:

```php
foreach ($booking->passengers()->where(...)->get() as $passenger) {
    $passenger->update(['booking_service_charge' => $package->service_charge ?? 0]);

    $regularTicket = $passenger->issuedTickets()
        ->where(fn($q) => $q->whereNull('issue_type')->orWhere('issue_type', 'regular'))
        ->first();
    $outboundTicket = $passenger->issuedTickets()
        ->where('issue_type', 'pending_outbound')->first();

    if ($package->is_double_ticket) {
        // Update regular ticket from inbound fare
        $inboundFare = $passenger->ticketFareInbound;
        if ($regularTicket && $inboundFare) {
            [$s, $o] = $this->adjustFaresForType(
                (float) ($inboundFare->selling_fare ?? 0),
                ($inboundFare->ticket_type === 'offer') ? (float) ($inboundFare->offer_price ?? 0) : 0,
                $passenger, $inboundFare
            );
            $regularTicket->update(['selling_fare' => $s, 'offer_price' => $o]);
        }

        // Create or update outbound ticket
        $outboundFare = $passenger->ticketFareOutbound;
        [$os, $oo] = $outboundFare
            ? $this->adjustFaresForType(
                (float) ($outboundFare->selling_fare ?? 0),
                ($outboundFare->ticket_type === 'offer') ? (float) ($outboundFare->offer_price ?? 0) : 0,
                $passenger, $outboundFare
            )
            : [0, 0];

        if ($outboundTicket) {
            $outboundTicket->update(['selling_fare' => $os, 'offer_price' => $oo]);
        } else {
            IssuedTicket::create([
                'passenger_id' => $passenger->id,
                'booking_id' => $booking->id,
                'user_id' => auth()->id(),
                'status' => 'pending',
                'issue_type' => 'pending_outbound',
                'selling_fare' => $os,
                'offer_price' => $oo,
            ]);
        }
    } else {
        // Single-ticket: update regular ticket from new fare
        $newFare = $passenger->ticketFare;
        if ($regularTicket && $newFare) {
            [$s, $o] = $this->adjustFaresForType(
                (float) ($newFare->selling_fare ?? 0),
                ($newFare->ticket_type === 'offer') ? (float) ($newFare->offer_price ?? 0) : 0,
                $passenger, $newFare
            );
            $regularTicket->update([
                'ticket_fare_id' => $passenger->ticket_fare_id,
                'selling_fare' => $s,
                'offer_price' => $o,
            ]);
        }

        // Delete outbound ticket if transitioning double->single
        if ($outboundTicket) {
            $outboundTicket->delete();
        }
    }
}
```

---

## Part 5: Passenger Type Change — Recalculate Snapshots

### `PassengerController::update()`

When `passenger_type` changes, recalculate all related issued tickets' selling_fare/offer_price:

```php
if (array_key_exists('passenger_type', $validated) && $validated['passenger_type'] !== $passenger->passenger_type) {
    $passenger->update(['passenger_type' => $validated['passenger_type']]);
    $this->recalculateFareSnapshots($passenger);
    $this->bookingService->syncFinancials($passenger->booking, 'passenger_type_changed');
} else {
    $passenger->update($validated);
}
```

**Helper `recalculateFareSnapshots(Passenger $passenger)`:**

```php
private function recalculateFareSnapshots(Passenger $passenger): void
{
    // a. Regular ticket — use ticket_fare_id or ticket_fare_inbound_id
    $regularTicket = $passenger->issuedTickets()
        ->where(fn($q) => $q->whereNull('issue_type')->orWhere('issue_type', 'regular'))
        ->first();
    if ($regularTicket) {
        $fare = $passenger->ticketFare ?? $passenger->ticketFareInbound;
        if ($fare) {
            [$s, $o] = $this->adjustFaresForType(
                (float) ($fare->selling_fare ?? 0),
                ($fare->ticket_type === 'offer') ? (float) ($fare->offer_price ?? 0) : 0,
                $passenger, $fare
            );
            $regularTicket->update(['selling_fare' => $s, 'offer_price' => $o]);
        }
    }

    // b. pending_outbound ticket — use ticket_fare_outbound_id
    $outboundTicket = $passenger->issuedTickets()->where('issue_type', 'pending_outbound')->first();
    if ($outboundTicket) {
        $outFare = $passenger->ticketFareOutbound;
        if ($outFare) {
            [$s, $o] = $this->adjustFaresForType(
                (float) ($outFare->selling_fare ?? 0),
                ($outFare->ticket_type === 'offer') ? (float) ($outFare->offer_price ?? 0) : 0,
                $passenger, $outFare
            );
            $outboundTicket->update(['selling_fare' => $s, 'offer_price' => $o]);
        }
    }

    // c. Additional tickets — each uses its own ticket_fare
    $additionalTickets = $passenger->issuedTickets()->where('issue_type', 'additional')->get();
    foreach ($additionalTickets as $ticket) {
        $fare = $ticket->ticketFare;
        if ($fare) {
            [$s, $o] = $this->adjustFaresForType(
                (float) ($fare->selling_fare ?? 0),
                ($fare->ticket_type === 'offer') ? (float) ($fare->offer_price ?? 0) : 0,
                $passenger, $fare
            );
            $ticket->update(['selling_fare' => $s, 'offer_price' => $o]);
        }
    }
}
```

---

## Part 6: Controller Changes — Issue / Re-Issue / Additional

### 6a. `TicketIssueController::issue()` and `edit()`

- **Remove** `selling_fare`, `offer_price` from `$request->validate()` entirely
- **Keep** `net_fare` as `nullable|numeric|min:0`
- In update data, explicitly preserve snapshot:
  ```php
  'selling_fare' => $issuedTicket->selling_fare,
  'offer_price' => $issuedTicket->offer_price,
  ```
- **Pending outbound creation**: Set `selling_fare=0`, `offer_price=0`

### 6b. `ReIssueController::store()`

- **Remove** `selling_fare`, `offer_price` from validation
- **Keep** `net_fare` as `nullable|numeric|min:0`
- ReIssuedTicket snapshot: `'selling_fare' => $issuedTicket->selling_fare, 'offer_price' => $issuedTicket->offer_price`

### 6c. `TicketRequestController::processReIssue()`

Same as ReIssueController.

### 6d. `TicketRequestController::processAdditional()`

- **Remove** `selling_fare`, `offer_price` from validation
- **Add** `net_fare` as `nullable|numeric|min:0` (editable from form)
- Selling_fare/offer_price: computed server-side from selected TicketFare, adjusted for passenger_type
- Net_fare: taken from `$validated['net_fare']`

### 6e. Refund controllers — No changes

---

## Part 7: Blade View Changes

### 7a. `bookings/index.blade.php` — Issue/Edit Modal (lines 1885-1944)

- **selling_fare**: Readonly display showing snapshot value. Not submitted.
- **offer_price**: Readonly display showing snapshot value. Not submitted. Only shown when `ticket_type === 'offer'`.
- **net_fare**: **Editable** (SAR + BDT). Agent enters the airline's cost.

### 7b. `bookings/index.blade.php` — Re-Issue Modal (lines 2310-2370)

- **selling_fare**: Readonly display. Not submitted.
- **offer_price**: Readonly display. Not submitted.
- **net_fare**: **Readonly**. Not submitted. (fare_difference always 0)

### 7c. `bookings/index.blade.php` — Inline newTicketFareForm (lines 1813-1821)

- **net_fare**: Hidden, default 0
- **selling_fare**: Visible, editable
- **offer_price**: Visible, editable

### 7d. `ticket-fares/create.blade.php` (lines 124-157)

- **net_fare**: Hidden, default 0
- **selling_fare**: Visible, editable
- **offer_price**: Visible, editable

### 7e. `ticket-fares/edit.blade.php` (lines 184-243)

Same as create.

### 7f. `re-issues/confirmation.blade.php` (lines 158-190)

- **selling_fare**: Readonly display. Not submitted.
- **offer_price**: Readonly display. Not submitted.
- **net_fare**: Readonly. Not submitted.

### 7g. `tickets/add-confirmation.blade.php` (lines 158-194)

- **selling_fare**: Readonly display. Not submitted.
- **offer_price**: Readonly display. Not submitted.
- **net_fare**: **Editable** — agent enters the cost.

---

## Summary: Field Visibility

| Form | selling_fare | offer_price | net_fare |
|------|-------------|-------------|----------|
| **TicketFare Create/Edit** | Visible, editable | Visible, editable | **Hidden, default 0** |
| **Inline TicketFare Create** | Visible, editable | Visible, editable | **Hidden, default 0** |
| **Issue/Edit Modal** | Readonly display (snapshot) | Readonly display (snapshot) | **Editable** |
| **Re-Issue Modal** | Readonly display (snapshot) | Readonly display (snapshot) | **Readonly** |
| **Re-Issue Confirmation** | Readonly display | Readonly display | Readonly |
| **Additional Ticket Confirmation** | Readonly display | Readonly display | **Editable** |
| **Refund Modal** | Readonly (from source) | Readonly (from source) | Readonly (from source) |

---

## Complete File Change List

| File | Action |
|------|--------|
| `app/Http/Controllers/PackageController.php` | Modify — remove lock checks, conditional validation |
| `app/Http/Controllers/TicketFareController.php` | Modify — remove $hasPackages early-return, hide net_fare |
| `app/Http/Controllers/BookingController.php` | Modify — fare snapshot at creation, package change, double-ticket creation |
| `app/Http/Controllers/PassengerController.php` | Modify — recalculate snapshots on type change |
| `app/Http/Controllers/TicketIssueController.php` | Modify — remove selling_fare/offer_price from validation |
| `app/Http/Controllers/ReIssueController.php` | Modify — remove selling_fare/offer_price from validation |
| `app/Http/Controllers/TicketRequestController.php` | Modify — processReIssue + processAdditional |
| `app/Models/PackageUpdateLog.php` | Create |
| `app/Models/TicketFareUpdateLog.php` | Create |
| `app/Models/Passenger.php` | Modify — add `booking_service_charge` to $fillable |
| `app/Models/Package.php` | Modify — add `updateLogs()` |
| `app/Models/TicketFare.php` | Modify — add `updateLogs()` |
| `app/Observers/PackageObserver.php` | Modify — add logging |
| `app/Observers/TicketFareObserver.php` | Create |
| `app/Providers/AppServiceProvider.php` | Modify — register TicketFareObserver |
| `app/Services/ProfitCalculationService.php` | Modify — read from issued_tickets snapshots |
| `database/migrations/xxxx_add_booking_service_charge_and_backfill.php` | Create |
| `database/migrations/xxxx_create_package_update_logs_table.php` | Create |
| `database/migrations/xxxx_create_ticket_fare_update_logs_table.php` | Create |
| `resources/views/bookings/index.blade.php` | Modify — fare field visibility in issue/reissue modals |
| `resources/views/ticket-fares/create.blade.php` | Modify — hide net_fare |
| `resources/views/ticket-fares/edit.blade.php` | Modify — $inUse, hide net_fare |
| `resources/views/re-issues/confirmation.blade.php` | Modify — hide selling_fare/offer_price |
| `resources/views/tickets/add-confirmation.blade.php` | Modify — hide selling_fare/offer_price, editable net_fare |

## Execution Order

1. Migrations (booking_service_charge column + backfill + log tables)
2. Models (PackageUpdateLog, TicketFareUpdateLog, Passenger update)
3. Observers (TicketFareObserver, update PackageObserver)
4. Relationships (Package, TicketFare, AppServiceProvider)
5. Passenger creation snapshots (BookingController store/addPassenger)
6. Package change snapshots (BookingController update)
7. Passenger type change snapshots (PassengerController update)
8. Profit calculation changes (ProfitCalculationService)
9. Controller changes (TicketIssueController, ReIssueController, TicketRequestController, TicketFareController)
10. View changes (all Blade files)
11. Test (`php artisan test`, `vendor/bin/pint`)
