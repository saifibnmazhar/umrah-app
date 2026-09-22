# Open Edit Access for In-Use Packages & Ticket Fares + Fare Snapshot System

## Goal

Allow editing of packages and ticket fares already in use by existing bookings. Preserve historical fare values via snapshots on `issued_tickets.selling_fare`/`offer_price` for profit calculations. Control fare field visibility across all forms.

## Constraints

1. **Package edit**: `package_name` and `service_charge` are editable on locked packages. No fare reference changes, no `is_double_ticket` toggle. Option to update `visa_selling_price_id` to current when editing.
2. **Ticket fare edit**: When in use by packages, only `selling_fare`, `offer_price`, `effective_to` are editable. `child_fare_percentage`, `infant_fare_percentage` remain locked (affect future snapshots).
3. **Historic profit**: Uses snapshotted `selling_fare`/`offer_price` from `issued_tickets` (not live TicketFare values).
4. **`ticket_fares.net_fare`**: Always 0 — hidden from create/edit forms. Net fare entered by agents at issuance time.
5. **`issued_tickets.selling_fare`/`offer_price`**: Historical snapshot taken at passenger creation, adjusted for passenger_type.
6. **Invoice immutability**: Backfilling, editing packages, or editing tickets must NOT change `passengers.package_value`, `bookings.total_value`, or `invoices.total_amount`. Package changes DO recalculate these (prospective).
7. **`service_charge` on passengers**: Populated from `booking_service_charge` when both visa_profit and ticket_profit are effective. Package `service_charge` change has no effect on existing passengers' `booking_service_charge` or `service_charge`. Only booking package change updates `booking_service_charge`.
8. **All issuedTickets are immutable** for `selling_fare`/`offer_price` on ticketFare update. Updates only when passenger_type changes.
9. **Soft-deleted tickets**: Excluded from profit calculation.
10. **Visa selling price on locked packages**: Option to update to current visa selling price when editing.

---

## Part 1: Remove Locks & Restrict Editable Fields

### 1a. `app/Http/Controllers/PackageController.php`

**`edit()` (line 211-213):** Remove the `isLocked()` redirect.

**`update()` (line 225-227):** Remove the `isLocked()` redirect. Add conditional validation — when locked, only allow `package_name` and `service_charge`:

```php
$isLocked = $package->isLocked();

$rules = [
    'package_name' => 'required|string|max:255',
];

if (!$isLocked) {
    $rules['is_double_ticket'] = 'nullable|boolean';
    $rules['regular_price'] = 'required|numeric|min:0';
    $rules['offer_price'] = 'nullable|numeric|min:0';
    $rules['service_charge'] = 'nullable|numeric|min:0';
    // ... fare reference rules ...
} else {
    $rules['service_charge'] = 'nullable|numeric|min:0';
}
```

**Visa selling price handling:** After validation, if `$request->boolean('use_current_visa')`, set `$validated['visa_selling_price_id']` to `VisaSellingPrice::latest('id')->value('id')`. Only when not locked.

**`destroy()` (line 296-299):** Keep locked — no change.

### 1b. `app/Http/Controllers/TicketFareController.php`

**`edit()` (line 171):** Fix lock detection to include all fare usage:
```php
$hasPackages = $ticketFare->packages()->exists()
    || \App\Models\Package::where('ticket_fare_inbound_id', $ticketFare->id)->exists()
    || \App\Models\Package::where('ticket_fare_outbound_id', $ticketFare->id)->exists()
    || $ticketFare->passengers()->exists();
```
Pass this as `$inUse` to the view (rename from `$hasPackages`).

**`update()` (line 182):** Same fix for lock detection. When `$inUse`, allow only `selling_fare`, `offer_price`, `effective_to`:

```php
if ($inUse) {
    $validated = $request->validate([
        'selling_fare' => 'required|numeric|min:0',
        'offer_price' => 'nullable|numeric|min:0',
        'effective_to' => 'required|date|after_or_equal:effective_from',
    ]);
    $ticketFare->update($validated);
    return redirect()->route('fare.admin', ['tab' => 'fares', 'page' => $request->page])->with('success', 'Ticket fare updated successfully.');
}
```

**Non-locked `update()`:** Remove `net_fare` from validation. Force to 0 in update:
```php
$validated['net_fare'] = 0;
```

**`store()` (line 65-150):** Remove `net_fare` from validation. Force to 0 in create:
```php
'net_fare' => 0,
```

**`quickStore()` (line 474-569):** Remove `net_fare` from validation. Force to 0 in create:
```php
'net_fare' => 0,
```

### 1c. `resources/views/ticket-fares/edit.blade.php`

- Replace `$locked` variable with `$inUse` (controller passes `$inUse` instead of `$hasPackages`)
- When `$inUse`: `selling_fare` and `offer_price` are editable, `effective_to` is editable. All other fields (`airline_id`, `airline_classes_id`, `route_id`, `ticket_type`, `net_fare`, `effective_from`, `child_fare_percentage`, `infant_fare_percentage`, `with_meal`, group ticket fields) are readonly/disabled
- **`net_fare` field:** Hidden, submit 0. Remove the SAR/BDT currency widget for net_fare. Keep selling_fare/offer_price currency widget.
- Update submit button: `{{ $inUse ? 'Update Fare Price' : 'Update Ticket Fare' }}`

### 1d. `resources/views/ticket-fares/create.blade.php`

- **`net_fare` field:** Hidden, submit 0. Remove the SAR/BDT currency widget for net_fare.

### 1e. `resources/views/packages/edit.blade.php`

When `$package->isLocked()`:
- `ticket_fare_id`, `ticket_fare_inbound_id`, `ticket_fare_outbound_id` selects: readonly/disabled
- `is_double_ticket` checkbox: disabled
- `regular_price`, `offer_price`: readonly (computed from fare selections)
- `service_charge`: editable
- `use_current_visa` checkbox: visible, allows updating visa_selling_price_id
- Submit button: "Update Package"

---

## Part 2: Fare Snapshot System

### Design

The existing `issued_tickets.selling_fare` and `issued_tickets.offer_price` columns become the **historical snapshot** of fare values at passenger creation time. The child/infant passenger_type percentage is applied at snapshot time, so the stored values are the final adjusted amounts.

- **Source of snapshot**: `ticket_fares.selling_fare`/`offer_price` via the passenger's fare references
- **Snapshot timing**: Passenger creation, package change (fare ID change), passenger_type change, service_required change from visa_only
- **Profit calculation reads**: `issued_tickets.selling_fare`/`offer_price` (NOT `ticket_fares`)
- **`ticket_fares.selling_fare`/`offer_price`**: Always editable by admins (rate card)
- **All issuedTickets are immutable** for selling_fare/offer_price regardless of status

### Snapshot rules by passenger type:

| Passenger ticket config | Regular/null ticket snapshot | Pending outbound snapshot |
|------------------------|---------------------------|------------------------|
| Single ticket (ticket_fare_id set) | From ticket_fare_id | 0 (if created via checkbox) |
| Double ticket (inbound/outbound set) | From ticket_fare_inbound_id | From ticket_fare_outbound_id |

### 2a. Migration: Add `booking_service_charge` to passengers

```php
Schema::table('passengers', function (Blueprint $table) {
    $table->decimal('booking_service_charge', 14, 6)->default(0)->after('service_charge');
});
```

Add `booking_service_charge` to `Passenger::$fillable`.

### 2b. Helper: `adjustFaresForType()`

Add to `BookingController` (or extract to a trait/service):

```php
private function adjustFaresForType(float $sellingFare, float $offerPrice, string $passengerType, ?TicketFare $fare): array
{
    if (!$fare) return [$sellingFare, $offerPrice];
    $pct = match($passengerType) {
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

**Note:** Uses string `'child'`/`'infant'`/`'adult'` — at passenger creation time, `$passenger` doesn't exist yet, so we use the request value or computed `$passengerType` string.

### 2c. Backfill Migration

**`passengers.booking_service_charge`:**
```php
DB::table('passengers')
    ->join('bookings', 'passengers.booking_id', '=', 'bookings.id')
    ->join('packages', 'bookings.package_id', '=', 'packages.id')
    ->where('passengers.is_cancelled', false)
    ->where('passengers.service_required', '!=', 'visa_only')
    ->update(['passengers.booking_service_charge' => DB::raw('packages.service_charge')]);
```

**User ID for outbound tickets:**
```php
$userId = User::where('role', 'ticket_admin')->first()?->id
    ?? User::where('role', 'super_admin')->first()?->id
    ?? 1;
```

**Exclude visa_only passengers** from all fare backfill queries.

**`issued_tickets.selling_fare`/`offer_price`** — three cases:

**Case A — Single-ticket, no pending outbound:**
```php
$passengers = Passenger::whereNotNull('ticket_fare_id')
    ->whereNull('ticket_fare_inbound_id')
    ->whereNull('ticket_fare_outbound_id')
    ->where('service_required', '!=', 'visa_only')
    ->whereDoesntHave('issuedTickets', fn($q) => $q->where('issue_type', 'pending_outbound'))
    ->with('ticketFare', 'issuedTickets')
    ->cursor();

foreach ($passengers as $p) {
    $fare = $p->ticketFare;
    if (!$fare) continue;
    $sellingFare = (float) ($fare->selling_fare ?? 0);
    $offerPrice = ($fare->ticket_type === 'offer') ? (float) ($fare->offer_price ?? 0) : 0;
    [$sellingFare, $offerPrice] = $this->adjustFaresForType($sellingFare, $offerPrice, $p->passenger_type->value, $fare);
    $p->issuedTickets()
        ->where(fn($q) => $q->whereNull('issue_type')->orWhere('issue_type', 'regular'))
        ->updateQuietly(['selling_fare' => $sellingFare, 'offer_price' => $offerPrice]);
}
```

**Case B — Single-ticket, has pending outbound:**
```php
// Same as A for regular ticket, plus zero out pending_outbound
$pendingOutbound = $p->issuedTickets()->where('issue_type', 'pending_outbound')->first();
if ($pendingOutbound) {
    $pendingOutbound->updateQuietly(['selling_fare' => 0, 'offer_price' => 0]);
}
```

**Case C — Double-ticket:**
```php
$passengers = Passenger::whereNull('ticket_fare_id')
    ->whereNotNull('ticket_fare_inbound_id')
    ->whereNotNull('ticket_fare_outbound_id')
    ->where('service_required', '!=', 'visa_only')
    ->with('ticketFareInbound', 'ticketFareOutbound', 'issuedTickets')
    ->cursor();

foreach ($passengers as $p) {
    $inboundFare = $p->ticketFareInbound;
    $outboundFare = $p->ticketFareOutbound;

    // Backfill NULL/regular ticket from inbound fare
    $inSelling = $inboundFare ? (float) ($inboundFare->selling_fare ?? 0) : 0;
    $inOffer = ($inboundFare && $inboundFare->ticket_type === 'offer') ? (float) ($inboundFare->offer_price ?? 0) : 0;
    [$inSelling, $inOffer] = $this->adjustFaresForType($inSelling, $inOffer, $p->passenger_type->value, $inboundFare);
    $p->issuedTickets()
        ->where(fn($q) => $q->whereNull('issue_type')->orWhere('issue_type', 'regular'))
        ->updateQuietly(['selling_fare' => $inSelling, 'offer_price' => $inOffer]);

    // Create or backfill outbound ticket from outbound fare
    $outSelling = $outboundFare ? (float) ($outboundFare->selling_fare ?? 0) : 0;
    $outOffer = ($outboundFare && $outboundFare->ticket_type === 'offer') ? (float) ($outboundFare->offer_price ?? 0) : 0;
    [$outSelling, $outOffer] = $this->adjustFaresForType($outSelling, $outOffer, $p->passenger_type->value, $outboundFare);

    $outboundTicket = $p->issuedTickets()->where('issue_type', 'pending_outbound')->first();
    if ($outboundTicket) {
        $outboundTicket->updateQuietly(['selling_fare' => $outSelling, 'offer_price' => $outOffer]);
    } else {
        IssuedTicket::create([
            'passenger_id' => $p->id,
            'booking_id' => $p->booking_id,
            'user_id' => $userId,
            'status' => 'pending',
            'issue_type' => 'pending_outbound',
            'selling_fare' => $outSelling,
            'offer_price' => $outOffer,
        ]);
    }
}
```

**Critical:** Wrap all in `DB::transaction()`. Use `updateQuietly()` to suppress observer logging. Do NOT call `recalculateBookingTotal()`, `syncBookingFinancials()`, or `recalculateBookingProfit()` after backfill.

---

## Part 3: Update Logs

### 3a. `app/Models/PackageUpdateLog.php` — Create

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

### 3b. `app/Models/TicketFareUpdateLog.php` — Create

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
    // service_charge kept for logging; profit recalc from snapshot is harmless
    // ticket_fare_* and is_double_ticket removed — locked packages can't change them
    private const PROFIT_FIELDS = [
        'service_charge',
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

**New — single-ticket passengers (non-visa_only):**

```php
$passengerType = $this->bookingService->calculatePassengerType($passengerData['date_of_birth']);
$isDoubleTicket = $booking->package?->is_double_ticket;
$serviceRequired = $passengerData['service_required'] ?? 'all';

// ... Passenger::create() with booking_service_charge ...

if ($serviceRequired !== 'visa_only') {
    if ($isDoubleTicket) {
        // Double ticket: create two issued tickets
        $inboundFare = $booking->package?->ticketFareInbound;
        $outboundFare = $booking->package?->ticketFareOutbound;

        // Inbound (NULL/regular) ticket
        $inSelling = $inboundFare ? (float) ($inboundFare->selling_fare ?? 0) : 0;
        $inOffer = ($inboundFare && $inboundFare->ticket_type === 'offer') ? (float) ($inboundFare->offer_price ?? 0) : 0;
        [$inSelling, $inOffer] = $this->adjustFaresForType($inSelling, $inOffer, $passengerType, $inboundFare);

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
        [$outSelling, $outOffer] = $this->adjustFaresForType($outSelling, $outOffer, $passengerType, $outboundFare);

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
    } else {
        // Single ticket
        $fare = $booking->package?->ticketFare;
        $sellingFare = 0;
        $offerPrice = 0;
        if ($fare) {
            $sellingFare = (float) ($fare->selling_fare ?? 0);
            $offerPrice = ($fare->ticket_type === 'offer') ? (float) ($fare->offer_price ?? 0) : 0;
            [$sellingFare, $offerPrice] = $this->adjustFaresForType($sellingFare, $offerPrice, $passengerType, $fare);
        }
        IssuedTicket::create([
            'booking_id' => $booking->id,
            'passenger_id' => $passenger->id,
            'ticket_fare_id' => $passenger->ticket_fare_id,
            'user_id' => auth()->id(),
            'status' => 'pending',
            'selling_fare' => $sellingFare,
            'offer_price' => $offerPrice,
        ]);
    }
}
```

**Passenger::create** must include:
```php
'booking_service_charge' => $booking->package->service_charge ?? 0,
```

### 4b. `BookingController::addPassenger()` (line 2090-2156) — Same pattern as `store()`

### 4c. `BookingController::update()` — Package change handling (lines 1892-1919)

After the existing fare ID update (lines 1895-1917), add snapshot updates:

```php
if ($request->has('package_id') && $booking->wasChanged('package_id')) {
    $package = Package::with(['ticketFare', 'ticketFareInbound', 'ticketFareOutbound'])->find($request->input('package_id'));
    if ($package) {
        // Update fare IDs (existing code lines 1895-1917)
        // ...

        // Update booking_service_charge and snapshots on affected passengers
        $affectedPassengers = $booking->passengers()
            ->where(function ($q) {
                $q->where('service_required', '!=', 'visa_only')
                    ->orWhereNull('service_required');
            })
            ->get();

        foreach ($affectedPassengers as $passenger) {
            $passenger->update(['booking_service_charge' => $package->service_charge ?? 0]);

            $regularTicket = $passenger->issuedTickets()
                ->where(fn($q) => $q->whereNull('issue_type')->orWhere('issue_type', 'regular'))
                ->first();
            $outboundTicket = $passenger->issuedTickets()
                ->where('issue_type', 'pending_outbound')->first();

            if ($package->is_double_ticket) {
                // Double ticket: update snapshots from inbound/outbound fares
                $inboundFare = $passenger->ticketFareInbound;
                if ($regularTicket && $inboundFare) {
                    [$s, $o] = $this->adjustFaresForType(
                        (float) ($inboundFare->selling_fare ?? 0),
                        ($inboundFare->ticket_type === 'offer') ? (float) ($inboundFare->offer_price ?? 0) : 0,
                        $passenger->passenger_type->value, $inboundFare
                    );
                    $regularTicket->update(['selling_fare' => $s, 'offer_price' => $o]);
                }

                // Create or update outbound ticket
                $outboundFare = $passenger->ticketFareOutbound;
                [$os, $oo] = $outboundFare
                    ? $this->adjustFaresForType(
                        (float) ($outboundFare->selling_fare ?? 0),
                        ($outboundFare->ticket_type === 'offer') ? (float) ($outboundFare->offer_price ?? 0) : 0,
                        $passenger->passenger_type->value, $outboundFare
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
                // Single ticket: update snapshot from new fare
                $newFare = $passenger->ticketFare;
                if ($regularTicket && $newFare) {
                    [$s, $o] = $this->adjustFaresForType(
                        (float) ($newFare->selling_fare ?? 0),
                        ($newFare->ticket_type === 'offer') ? (float) ($newFare->offer_price ?? 0) : 0,
                        $passenger->passenger_type->value, $newFare
                    );
                    $regularTicket->update([
                        'ticket_fare_id' => $passenger->ticket_fare_id,
                        'selling_fare' => $s,
                        'offer_price' => $o,
                    ]);
                }

                // Soft-delete outbound ticket if transitioning double→single
                if ($outboundTicket) {
                    $outboundTicket->delete(); // Soft-delete (IssuedTicket uses SoftDeletes)
                    // Leave snapshots as-is on soft-deleted record
                }
            }
        }
    }
}
```

**After package change**, `syncBookingFinancials` (line 1926) recalculates `package_value` from live fares — this is correct (prospective change).

---

## Part 5: Passenger Type Change — Recalculate Snapshots

### 5a. `PassengerController::update()`

Add passenger_type change detection and snapshot recalculation:

```php
$oldPassengerType = $passenger->passenger_type;
$passenger->update($validated);

// If passenger_type changed, recalculate fare snapshots
if (isset($validated['passenger_type']) && $validated['passenger_type'] !== $oldPassengerType?->value) {
    $this->recalculateFareSnapshots($passenger);
}

// If service_required changed from visa_only, create issuedTickets if needed
$oldServiceRequired = $oldServiceRequired ?? $passenger->service_required;
if ($oldServiceRequired?->value === 'visa_only'
    && isset($validated['service_required'])
    && $validated['service_required'] !== 'visa_only') {
    $this->handleServiceRequiredChangeFromVisaOnly($passenger);
}

$booking = $passenger->booking;
if ($booking) {
    $this->bookingService->syncFinancials($booking->fresh(), 'passenger_updated');
}
```

### 5b. `recalculateFareSnapshots(Passenger $passenger)`

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
                $passenger->passenger_type->value, $fare
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
                $passenger->passenger_type->value, $outFare
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
                $passenger->passenger_type->value, $fare
            );
            $ticket->update(['selling_fare' => $s, 'offer_price' => $o]);
        }
    }
}
```

### 5c. `PassengerController::update()` — service_required change from visa_only

When `service_required` changes from `visa_only` to `all` or `ticket_only`, create issuedTickets if they don't exist:

```php
private function handleServiceRequiredChangeFromVisaOnly(Passenger $passenger): void
{
    $existingTickets = $passenger->issuedTickets()->count();
    if ($existingTickets > 0) {
        // Tickets exist — update snapshots to match current fare IDs
        $this->recalculateFareSnapshots($passenger);
        return;
    }

    // No tickets exist — create them following the same logic as BookingController::store()
    $booking = $passenger->booking;
    $package = $booking?->package;
    if (!$package) return;

    $isDoubleTicket = $package->is_double_ticket;
    $passengerType = $passenger->passenger_type->value;

    if ($isDoubleTicket) {
        $inboundFare = $package->ticketFareInbound;
        $outboundFare = $package->ticketFareOutbound;

        $inSelling = $inboundFare ? (float) ($inboundFare->selling_fare ?? 0) : 0;
        $inOffer = ($inboundFare && $inboundFare->ticket_type === 'offer') ? (float) ($inboundFare->offer_price ?? 0) : 0;
        [$inSelling, $inOffer] = $this->adjustFaresForType($inSelling, $inOffer, $passengerType, $inboundFare);

        IssuedTicket::create([
            'booking_id' => $booking->id,
            'passenger_id' => $passenger->id,
            'ticket_fare_id' => null,
            'user_id' => auth()->id(),
            'status' => 'pending',
            'selling_fare' => $inSelling,
            'offer_price' => $inOffer,
        ]);

        $outSelling = $outboundFare ? (float) ($outboundFare->selling_fare ?? 0) : 0;
        $outOffer = ($outboundFare && $outboundFare->ticket_type === 'offer') ? (float) ($outboundFare->offer_price ?? 0) : 0;
        [$outSelling, $outOffer] = $this->adjustFaresForType($outSelling, $outOffer, $passengerType, $outboundFare);

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
    } else {
        $fare = $package->ticketFare;
        $sellingFare = 0;
        $offerPrice = 0;
        if ($fare) {
            $sellingFare = (float) ($fare->selling_fare ?? 0);
            $offerPrice = ($fare->ticket_type === 'offer') ? (float) ($fare->offer_price ?? 0) : 0;
            [$sellingFare, $offerPrice] = $this->adjustFaresForType($sellingFare, $offerPrice, $passengerType, $fare);
        }
        IssuedTicket::create([
            'booking_id' => $booking->id,
            'passenger_id' => $passenger->id,
            'ticket_fare_id' => $passenger->ticket_fare_id,
            'user_id' => auth()->id(),
            'status' => 'pending',
            'selling_fare' => $sellingFare,
            'offer_price' => $offerPrice,
        ]);
    }
}
```

---

## Part 6: Controller Changes — Issue / Re-Issue / Additional

### 6a. `TicketIssueController::issue()` (line 35-57)

- **Remove** `selling_fare`, `offer_price` from `$request->validate()` entirely
- **Keep** `net_fare` as `nullable|numeric|min:0`
- In update data, explicitly preserve snapshot:
  ```php
  $updateData = array_merge($validated, [
      'status' => 'issued',
      'user_id' => auth()->id(),
      'selling_fare' => $issuedTicket->selling_fare,
      'offer_price' => $issuedTicket->offer_price,
  ]);
  ```
- **Pending outbound creation** (line 98-108): Set `selling_fare=0`, `offer_price=0` (single-ticket outbound pending checkbox)

### 6b. `TicketIssueController::edit()` (line 443)

```php
// Remove selling_fare/offer_price from update data to preserve snapshots
$updateData = collect($validated)->except(['selling_fare', 'offer_price'])->toArray();
$updateData['selling_fare'] = $issuedTicket->selling_fare;
$updateData['offer_price'] = $issuedTicket->offer_price;
$issuedTicket->update($updateData);
```

### 6c. `ReIssueController::store()` (line 124-131)

- **Remove** `selling_fare`, `offer_price` from validation
- **Keep** `net_fare` as `nullable|numeric|min:0`
- Change fallback to use snapshot values:
  ```php
  'selling_fare' => $issuedTicket->selling_fare ?? 0,
  'net_fare' => $validated['net_fare'] ?? $issuedTicket->net_fare ?? 0,
  'offer_price' => $issuedTicket->offer_price ?? 0,
  ```

### 6d. `TicketRequestController::processReIssue()` (line 196-198)

Same as ReIssueController — fallback to snapshot:
```php
$sellingFare = (float) ($issuedTicket->selling_fare ?? 0);
$netFare = (float) ($validated['net_fare'] ?? $issuedTicket->net_fare ?? 0);
$offerPrice = (float) ($issuedTicket->offer_price ?? 0);
```

### 6e. `TicketRequestController::processAdditional()` (line 520-579)

- **Remove** `selling_fare`, `offer_price` from validation
- **Add** `net_fare` as `nullable|numeric|min:0` (editable from form, raw value — no child/infant scaling)
- Selling_fare/offer_price: computed server-side from selected TicketFare, adjusted for passenger_type (existing logic at lines 542-554 is correct)
- Net_fare: taken raw from `$validated['net_fare']` — do NOT scale by child/infant %

```php
$validated = $request->validate([
    // ... existing fields ...
    'net_fare' => 'nullable|numeric|min:0',
]);

// Compute selling/offer from selected fare (existing lines 542-554)
$sellingFare = (float) ($selectedFare->selling_fare ?? 0);
$netFare = (float) ($validated['net_fare'] ?? $selectedFare->net_fare ?? 0);
$offerPrice = (float) ($selectedFare->offer_price ?? 0);

// Apply child/infant adjustment to selling/offer only (NOT net_fare)
if ($passengerType === 'child') {
    $sellingFare = round($sellingFare * $childPct / 100, 6);
    $offerPrice = round($offerPrice * $childPct / 100, 6);
} elseif ($passengerType === 'infant') {
    $sellingFare = round($sellingFare * $infantPct / 100, 6);
    $offerPrice = round($offerPrice * $infantPct / 100, 6);
}
```

### 6f. Refund controllers — No changes needed

---

## Part 7: Profit Calculation Changes

### 7a. `app/Services/ProfitCalculationService.php`

**New helper `fareSellingPriceFromTicket(IssuedTicket $ticket)`:**
```php
private function fareSellingPriceFromTicket(IssuedTicket $ticket): float
{
    return ($ticket->offer_price ?? 0) > 0
        ? (float) $ticket->offer_price
        : (float) $ticket->selling_fare;
}
```

**Rewrite `getPackageTicketSellingFare()` (line 746):**
```php
private function getPackageTicketSellingFare(Passenger $passenger): float
{
    $tickets = $this->regularTickets($passenger);
    return $tickets->sum(fn ($t) => $this->fareSellingPriceFromTicket($t));
}
```

**Rewrite `calculateTicketProfit()` (line 400):**
```php
private function calculateTicketProfit(Passenger $passenger): float
{
    $tickets = $this->regularTickets($passenger);
    if ($tickets->isEmpty() || ! $this->isTicketProfitEffective($passenger)) {
        return 0.0;
    }
    return $this->getPackageTicketSellingFare($passenger)
        - (float) $tickets->sum('net_fare');
}
```

**Rewrite `calculateAdditionalTicketProfit()` (line 412):**
```php
private function calculateAdditionalTicketProfit(Passenger $passenger): float
{
    return (float) $passenger->allIssuedTickets
        ->filter(fn ($t) => $t->issue_type === 'additional'
            && in_array($t->status, ['issued', 're-issued', 'refunded'], true))
        ->sum(fn ($t) => $this->fareSellingPriceFromTicket($t) - (float) ($t->net_fare ?? 0));
}
```

**Rewrite `calculateServiceCharge()` (line 655):**
```php
private function calculateServiceCharge(Passenger $passenger): float
{
    if (! $this->isVisaProfitEffective($passenger) || ! $this->isTicketProfitEffective($passenger)) {
        return 0.0;
    }
    return (float) ($passenger->booking_service_charge ?? 0);
}
```

**Update callers of `fareSellingPrice()`:**
- `ticketBreakdown()` (line 598): Change to use `fareSellingPriceFromTicket()`
- `additionalTicketsBreakdownEffective()` (line 303): Same
- `additionalTicketEffectiveValue()` (line 494): Same

**Update `regularTickets()` (line 733) to exclude soft-deleted:**
```php
private function regularTickets(Passenger $passenger)
{
    return $passenger->allIssuedTickets->filter(
        fn ($t) => ($t->issue_type === null
            || in_array($t->issue_type, ['regular', 'pending_outbound'], true))
            && is_null($t->deleted_at) // Exclude soft-deleted
    );
}
```

### 7b. `app/Services/BookingService.php`

**No changes to `calculatePackageValue()`** — it still reads live fares. Used only for new bookings. Existing passengers' `package_value` is preserved.

**`recalculateBookingTotal()`** — no changes. Called by `syncFinancials()` on package change to update `package_value` prospectively.

---

## Part 8: Blade View Changes

### 8a. `bookings/index.blade.php` — Issue/Edit Modal (lines 1885-1944)

- **selling_fare**: Readonly text displaying snapshot value from issuedTicket. Not submitted.
- **offer_price**: Readonly text displaying snapshot value from issuedTicket. Not submitted. Only shown when `ticket_type === 'offer'`.
- **net_fare**: **Editable** (SAR + BDT). Agent enters the airline's cost.
- **JS**: Populate fare fields from issuedTicket data, NOT from ticket fare data.

### 8b. `bookings/index.blade.php` — Re-Issue Modal (lines 2310-2370)

- **selling_fare**: Readonly text. Not submitted.
- **offer_price**: Readonly text. Not submitted.
- **net_fare**: **Readonly**. Not submitted. (fare_difference always 0)
- **JS**: Populate from issuedTicket snapshot data.

### 8c. `bookings/index.blade.php` — Inline newTicketFareForm (lines 1813-1821)

- **net_fare**: Hidden, default 0
- **selling_fare**: Visible, editable
- **offer_price**: Visible, editable

### 8d. `ticket-fares/create.blade.php` (lines 124-157)

- **net_fare**: Hidden, default 0. Remove currency widget for net_fare.
- **selling_fare**: Visible, editableshanto@fedora:~/Desktop/project/techCandle-umrah$ php artisan migrate;

   INFO  Running migrations.  

  2026_09_22_000001_add_booking_service_charge_and_backfill .......................................................................... 597.15ms DONE
  2026_09_22_000002_create_package_update_logs_table .................................................................................. 45.61ms FAIL

   Illuminate\Database\QueryException 

  SQLSTATE[HY000]: General error: 1005 Can't create table `laravel_db`.`package_update_logs` (errno: 150 "Foreign key constraint is incorrectly formed") (Connection: mysql, Host: 127.0.0.1, Port: 3306, Database: laravel_db, SQL: alter table `package_update_logs` add constraint `package_update_logs_package_id_foreign` foreign key (`package_id`) references `packages` (`id`) on delete set null)

  at vendor/laravel/framework/src/Illuminate/Database/Connection.php:838
    834▕             $exceptionType = $this->isUniqueConstraintError($e)
    835▕                 ? UniqueConstraintViolationException::class
    836▕                 : QueryException::class;
    837▕ 
  ➜ 838▕             throw new $exceptionType(
    839▕                 $this->getNameWithReadWriteType(),
    840▕                 $query,
    841▕                 $this->prepareBindings($bindings),
    842▕                 $e,

      +9 vendor frames 

  10  database/migrations/2026_09_22_000002_create_package_update_logs_table.php:11
      Illuminate\Support\Facades\Facade::__callStatic()
      +26 vendor frames 

  37  artisan:16
      Illuminate\Foundation\Application::handleCommand()

shanto@fedora:~/Desktop/project/techCandle-umrah$ 

- **offer_price**: Visible, editable

### 8e. `ticket-fares/edit.blade.php` (lines 184-243)

Same as create. When `$inUse`: net_fare hidden, selling_fare/offer_price editable, effective_to editable, all others readonly.

### 8f. `re-issues/confirmation.blade.php` (lines 158-190)

- **selling_fare**: Readonly text. Not submitted.
- **offer_price**: Readonly text. Not submitted.
- **net_fare**: Readonly. Not submitted.

### 8g. `tickets/add-confirmation.blade.php` (lines 158-194)

- **selling_fare**: Readonly text. Not submitted.
- **offer_price**: Readonly text. Not submitted.
- **net_fare**: **Editable** — agent enters the cost.
- **Ticket fare selector** (line 103): Populate with ALL active ticket fares (from `TicketFare::where('is_active', true)->get()`).

### 8h. `packages/edit.blade.php` (locked state)

When locked:
- Fare selects: readonly/disabled
- `is_double_ticket`: disabled
- `regular_price`, `offer_price`: readonly (computed)
- `service_charge`: editable
- **New:** `use_current_visa` checkbox — when checked, updates `visa_selling_price_id` to latest

---

## Summary: Field Visibility

| Form | selling_fare | offer_price | net_fare |
|------|-------------|-------------|----------|
| **TicketFare Create/Edit** | Visible, editable | Visible, editable | **Hidden, default 0** |
| **Inline TicketFare Create** | Visible, editable | Visible, editable | **Hidden, default 0** |
| **Issue/Edit Modal** | Readonly text (snapshot) | Readonly text (snapshot) | **Editable** |
| **Re-Issue Modal** | Readonly text (snapshot) | Readonly text (snapshot) | **Readonly** |
| **Re-Issue Confirmation** | Readonly text | Readonly text | Readonly |
| **Additional Ticket Confirmation** | Readonly text | Readonly text | **Editable** |
| **Refund Modal** | Readonly (from source) | Readonly (from source) | Readonly (from source) |

---

## Complete File Change List

| File | Action |
|------|--------|
| `app/Http/Controllers/PackageController.php` | Modify — remove lock checks, conditional validation, visa selling price option |
| `app/Http/Controllers/TicketFareController.php` | Modify — fix lock detection, hide net_fare, update store/update/quickStore |
| `app/Http/Controllers/BookingController.php` | Modify — fare snapshot at creation, package change, double-ticket creation |
| `app/Http/Controllers/PassengerController.php` | Modify — passenger_type change recalc, service_required change from visa_only |
| `app/Http/Controllers/TicketIssueController.php` | Modify — remove selling_fare/offer_price from validation, preserve snapshots |
| `app/Http/Controllers/ReIssueController.php` | Modify — remove selling_fare/offer_price, fallback to snapshot |
| `app/Http/Controllers/TicketRequestController.php` | Modify — processReIssue + processAdditional |
| `app/Models/PackageUpdateLog.php` | Create |
| `app/Models/TicketFareUpdateLog.php` | Create |
| `app/Models/Passenger.php` | Modify — add `booking_service_charge` to $fillable |
| `app/Models/Package.php` | Modify — add `updateLogs()` |
| `app/Models/TicketFare.php` | Modify — add `updateLogs()` |
| `app/Observers/PackageObserver.php` | Modify — add logging, comment out profit-relevant fields |
| `app/Observers/TicketFareObserver.php` | Create |
| `app/Providers/AppServiceProvider.php` | Modify — register TicketFareObserver |
| `app/Services/ProfitCalculationService.php` | Modify — read from issued_tickets snapshots, exclude soft-deleted |
| `app/Services/BookingService.php` | No changes (calculatePackageValue stays live-read) |
| `database/migrations/xxxx_add_booking_service_charge_and_backfill.php` | Create |
| `database/migrations/xxxx_create_package_update_logs_table.php` | Create |
| `database/migrations/xxxx_create_ticket_fare_update_logs_table.php` | Create |
| `resources/views/bookings/index.blade.php` | Modify — fare field visibility, populate from issuedTickets |
| `resources/views/ticket-fares/create.blade.php` | Modify — hide net_fare |
| `resources/views/ticket-fares/edit.blade.php` | Modify — $inUse, hide net_fare |
| `resources/views/re-issues/confirmation.blade.php` | Modify — hide selling_fare/offer_price |
| `resources/views/tickets/add-confirmation.blade.php` | Modify — hide selling_fare/offer_price, editable net_fare, ticket selector |
| `resources/views/packages/edit.blade.php` | Modify — locked state readonly, use_current_visa checkbox |

---

## Execution Order

1. **Migrations** — booking_service_charge column, backfill, log tables
2. **Models** — PackageUpdateLog, TicketFareUpdateLog, Passenger $fillable update
3. **Observers** — TicketFareObserver, PackageObserver update
4. **Relationships** — Package, TicketFare, AppServiceProvider
5. **Passenger creation snapshots** — BookingController store/addPassenger + helper
6. **Package change snapshots** — BookingController update
7. **Passenger type change snapshots** — PassengerController update + recalculateFareSnapshots
8. **Service required change** — PassengerController handleServiceRequiredChangeFromVisaOnly
9. **Profit calculation changes** — ProfitCalculationService
10. **Controller changes** — TicketIssueController, ReIssueController, TicketRequestController, TicketFareController
11. **View changes** — all Blade files
12. **Test** — `php artisan test`, `vendor/bin/pint`
