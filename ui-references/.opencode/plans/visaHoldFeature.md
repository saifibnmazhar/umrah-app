# Visa Hold Feature Implementation Plan

> **Status:** Pending implementation
> **Created:** 2026-08-30
> **Reference:** Ticket Hold feature (`is_ticket_held` on passengers table)

---

## Overview

Add a "visa hold" capability that mirrors the existing ticket hold feature.
When visa is held, all visa operations (submit, issue, edit, cancel, resubmit) are
blocked. Only Super Admin, Co Admin, and Visa Admin can hold/unhold visas.
Visa Staff cannot hold/unhold (button hidden entirely in UI).

---

## Step 1: Migration

**New file:** `database/migrations/2026_08_30_000001_add_is_visa_held_to_passengers_table.php`

Add 3 columns to the `passengers` table (same denormalized pattern as ticket hold
migration `2026_07_02_000001`):

| Column | Type | Default | Notes |
|---|---|---|---|
| `is_visa_held` | `boolean` | `false` | Placed after `ticket_held_at` |
| `visa_held_by` | `foreignId (users)` | `null` | Nullable, nullOnDelete |
| `visa_held_at` | `timestamp` | `null` | When hold was applied |

**Down migration:** Drop all 3 columns.

---

## Step 2: Passenger Model

**File:** `app/Models/Passenger.php`

### 2a. Fillable (add after `ticket_held_at`, line ~42)

```php
'is_visa_held',
'visa_held_by',
'visa_held_at',
```

### 2b. Casts (add after `ticket_held_at` cast, line ~65)

```php
'is_visa_held' => 'boolean',
'visa_held_at' => 'datetime',
```

### 2c. New helper method (add after `isOnCancel()` at line ~411)

```php
public function isVisaOnHold(): bool
{
    return (bool) $this->is_visa_held;
}
```

---

## Step 3: PassengerController

**File:** `app/Http/Controllers/PassengerController.php`

Add `toggleVisaHold` method after `toggleTicketHold` (after line ~721).
Identical structure — toggle the boolean, record user + timestamp on hold,
null them on release, return JSON.

```php
public function toggleVisaHold(Passenger $passenger)
{
    $this->ensureBranchAccess($passenger);

    if ($passenger->is_visa_held) {
        $passenger->update([
            'is_visa_held' => false,
            'visa_held_by' => null,
            'visa_held_at' => null,
        ]);
        $message = 'Visa hold released';
    } else {
        $passenger->update([
            'is_visa_held' => true,
            'visa_held_by' => auth()->id(),
            'visa_held_at' => now(),
        ]);
        $message = 'Visa hold applied';
    }

    return response()->json([
        'success' => true,
        'message' => $message,
        'is_visa_held' => $passenger->fresh()->is_visa_held,
    ]);
}
```

---

## Step 4: Route

**File:** `routes/web.php` (add after `toggle-ticket-hold` route, line ~139)

```php
Route::patch('/passengers/{passenger}/toggle-visa-hold', [PassengerController::class, 'toggleVisaHold'])
    ->name('passengers.toggle-visa-hold')
    ->middleware('role:Super Admin,Co Admin,Visa Admin');
```

**Key:** Visa Staff excluded — both server-side (middleware) and client-side
(button hidden via `$canEditVisa` which already checks `Super Admin, Co Admin, Visa Admin`).

---

## Step 5: VisaSubmissionController Guard

**File:** `app/Http/Controllers/VisaSubmissionController.php`

Update the guard condition in all 5 methods to include `isVisaOnHold()`:

**Methods:** `submit` (line 19), `issue` (line 63), `edit` (line 106),
`cancel` (line 155), `reSubmit` (line 207)

```php
// Before:
if ($passenger->isOnHold() || $passenger->isOnCancel() || $passenger->is_cancelled) {

// After:
if ($passenger->isOnHold() || $passenger->isVisaOnHold() || $passenger->isOnCancel() || $passenger->is_cancelled) {
```

---

## Step 6: Bookings Index Blade (UI)

**File:** `resources/views/bookings/index.blade.php`

### 6a. Data mapping — `$passengersVisaData` (~line 26)

Add `is_visa_held` to the visa data array:

```php
'is_visa_held' => (bool)($p->is_visa_held ?? false),
```

### 6b. Alpine state

Add near `isTogglingTicketHold`:

```php
isTogglingVisaHold: {},
```

### 6c. Visa column buttons (~lines 1243-1267)

**Hide visa action buttons when visa is on hold.**
Add `&& !passengersVisaData[{{ $loop->index }}]?.is_visa_held` to each
`x-show` condition on the Submit, Issue, Edit, Cancel, and Re-Submit buttons.

**Add Hold/Unhold button** — visible only when `$canEditVisa` is true (hidden
from Visa Staff entirely). Place it inside the visa `<td>` cell, before the
existing action buttons. Use the same dropdown pattern as the ticket hold button
or a standalone inline button:

```html
@if($canEditVisa)
<template x-if="!passengersTicketData[{{ $loop->index }}]?.is_cancelled">
    <button @click="toggleVisaHold({{ $loop->index }})"
        :disabled="isTogglingVisaHold[{{ $loop->index }}]"
        class="px-2 py-1 text-xs font-medium rounded transition"
        :class="passengersVisaData[{{ $loop->index }}]?.is_visa_held ? 'text-yellow-600 bg-yellow-100 hover:bg-yellow-200' : 'text-orange-600 bg-orange-100 hover:bg-orange-200'"
        x-text="passengersVisaData[{{ $loop->index }}]?.is_visa_held ? 'Unhold' : 'Hold'">
    </button>
</template>
@endif
```

### 6d. Status badge (~lines 1286-1298)

When `is_visa_held` is true, show a "Visa Hold" badge. Modify the status badge
section to prepend or replace with a hold badge:

```html
<template x-if="passengersVisaData[{{ $loop->index }}]?.is_visa_held">
    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-700">
        Visa Hold
    </span>
</template>
```

Place this before the existing visa status badge, and wrap the existing badge
in a `x-if="!passengersVisaData[{{ $loop->index }}]?.is_visa_held"` condition.

### 6e. JavaScript toggle function

Add near `toggleTicketHold` (after line ~4456):

```javascript
toggleVisaHold(index) {
    const row = this.passengersVisaData[index];
    if (!row) return;

    this.isTogglingVisaHold[index] = true;

    fetch(`/passengers/${row.id}/toggle-visa-hold`, {
        method: 'PATCH',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
            'Accept': 'application/json',
        },
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            this.passengersVisaData[index].is_visa_held = data.is_visa_held;
        }
    })
    .finally(() => {
        this.isTogglingVisaHold[index] = false;
    });
},
```

---

## Step 7: Tests

**New file:** `tests/Feature/VisaHoldTest.php`

### Test cases:

1. **`test_visa_hold_can_be_applied`** — Authenticated Visa Admin can toggle visa hold on a passenger. Assert `is_visa_held` becomes `true`, `visa_held_by` is set, `visa_held_at` is set.

2. **`test_visa_hold_can_be_released`** — Toggle off. Assert `is_visa_held` becomes `false`, `visa_held_by` and `visa_held_at` are null.

3. **`test_visa_submit_blocked_when_visa_held`** — Create passenger with `is_visa_held = true`, attempt visa submit. Assert 422 response.

4. **`test_visa_issue_blocked_when_visa_held`** — Same pattern, assert blocked.

5. **`test_visa_edit_blocked_when_visa_held`** — Same pattern, assert blocked.

6. **`test_visa_cancel_blocked_when_visa_held`** — Same pattern, assert blocked.

7. **`test_visa_resubmit_blocked_when_visa_held`** — Same pattern, assert blocked.

8. **`test_visa_staff_cannot_toggle_visa_hold`** — Visa Staff user gets 403 on the toggle endpoint.

9. **`test_visa_admin_can_toggle_visa_hold`** — Visa Admin user can toggle successfully.

---

## Files Modified

| File | Action |
|---|---|
| `database/migrations/2026_08_30_000001_add_is_visa_held_to_passengers_table.php` | **Create** |
| `app/Models/Passenger.php` | Edit (fillable, casts, method) |
| `app/Http/Controllers/PassengerController.php` | Edit (add method) |
| `routes/web.php` | Edit (add route) |
| `app/Http/Controllers/VisaSubmissionController.php` | Edit (guard update) |
| `resources/views/bookings/index.blade.php` | Edit (UI + JS) |
| `tests/Feature/VisaHoldTest.php` | **Create** |

---

## Design Decisions

1. **Denormalized columns on `passengers`** — No separate table, matches ticket hold pattern. Simple toggle with no expiry.
2. **Independent of `isOnHold()`** — `isOnHold()` checks the passenger *status name* (Hold). `is_visa_held` is a separate boolean flag. Both block visa operations.
3. **No audit log** — Matches ticket hold. Just overwrites `visa_held_by`/`visa_held_at`. The `visa_update_log` table can be used later if needed.
4. **Roles** — `Super Admin, Co Admin, Visa Admin` only. Visa Staff excluded from both route middleware and UI button visibility.
5. **UI visibility** — Button hidden entirely (not disabled) for Visa Staff, using existing `$canEditVisa` flag.
