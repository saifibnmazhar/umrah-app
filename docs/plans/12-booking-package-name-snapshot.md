# Plan: `bookings.package_name` Snapshot Column

Implements a `package_name` snapshot on the `bookings` table so a booking keeps
the package name it was booked under even after an in-use package is renamed
(`package_name` is now editable on locked packages per the fare-snapshot plan).

## Decisions

- **`package_id` is required.** Create and edit cannot succeed without
  selecting a package. `store` validates
  `'package_id' => 'required|exists:packages,id'`. `update` enforces the same
  rule on the admin path; non-admins keep the existing gate (their
  `package_id` is `unset` before validation applies, so they can never change
  it). Create/edit package `<select>`s also get `required` (UI is cosmetic —
  server validation is authoritative).
- **Snapshot refresh policy**: `package_name` is set at booking creation and
  refreshed **only** when the booking's `package_id` changes, in the **same
  write** as the `package_id` change (single `update()` → single
  `BookingUpdateLog` row). Renaming an in-use package does NOT rename existing
  bookings. Since `package_id` is required, there is no reachable
  "clear package → null snapshot" path; code still resolves defensively from
  the validated id.
- **Display rule — stored value wins**: every history display (booking screens,
  invoice print, passenger show, reports/exports, serializers, and the
  `edit` inactive-package dropdown option label) reads
  `bookings.package_name` first with a live `package.package_name` fallback
  (fallback covers only pre-existing `NULL`-package rows). Package
  selectors/filters/selection-preview summary spans stay live (they drive
  selection, not history).

---

## Files & Changes

### 0. Pre-step — audit legacy `NULL`-package rows (read-only)

Run before migrating (the old validation was `nullable` even though the
original DDL was `NOT NULL`, so strays may exist):

```sql
SELECT COUNT(*) FROM bookings WHERE package_id IS NULL;
```

- These rows keep `package_name = NULL` after backfill and render via the
  `'N/A'` / `'-'` fallback. No `NOT NULL` migration in this plan unless the
  audit shows the column itself is currently nullable — verify schema first.

### 1. Migration — `database/migrations/2026_09_23_000001_add_package_name_to_bookings_and_backfill.php` (new)

```php
Schema::table('bookings', function (Blueprint $table) {
    $table->string('package_name')->nullable()->after('package_id');
});
```

Standalone backfill (NOT copied from
`2026_09_22_000001_add_booking_service_charge_and_backfill.php` — that file is
a conditional per-passenger Eloquent backfill with `Model::withoutEvents`,
not a join-update; do not treat it as the pattern):

```php
DB::transaction(function () {
    DB::table('bookings')
        ->join('packages', 'bookings.package_id', '=', 'packages.id')
        ->update(['bookings.package_name' => DB::raw('packages.package_name')]);
});
```

- Nullable column accommodates pre-existing `NULL`-package rows.
- Plain `DB::table` update → no `BookingObserver` log rows.
- Bookings with `package_id = NULL` intentionally stay NULL.
- FK is `restrictOnDelete`, so referenced packages always exist (orphans only
  possible in legacy/pre-FK data and stay NULL).
- Post-backfill check: `COUNT(*) WHERE package_id IS NOT NULL AND
  package_name IS NULL` must be 0.
- `after('package_id')` is MySQL-only (ignored elsewhere) — harmless.
- Timestamp `2026_09_23_000001` is collision-free (latest is `2026_09_22_*`).
- `down()`: drop the column.

### 2. `app/Models/Booking.php`

Add `'package_name'` to `$fillable`.

### 3. `app/Http/Controllers/BookingController.php` — `store()` (~lines 1269/1365)

1. Validation: `'package_id' => 'nullable|exists:packages,id'` →
   `'required|exists:packages,id'`.
2. After `$validated = $validator->validated()`, resolve once (no `filled()`
   check needed — required):

```php
$package = Package::findOrFail($validated['package_id']);

$booking = Booking::create([
    // ...existing fields...
    'package_id' => $package->id,
    'package_name' => $package->package_name,
]);
```

- `findOrFail` (not `whereKey()->value()`) so the validation→create race
  returns 404 instead of a silent `null` snapshot + FK exception.
- No new import needed — `use App\Models\Package;` already exists (line 24).
- Single create → one `BookingUpdateLog` row; no post-create update.

### 4. `BookingController::update()` — single-write snapshot refresh

Delete the two-write pattern (`$booking->update($validated)` then a second
`$booking->update(['package_name' => ...])` inside the `wasChanged` block —
that produced two `updated` events / two `BookingUpdateLog` rows). Replace
with snapshot-merged-before-write:

```php
$validated['discount_type'] = ...; // unchanged
if (! $this->isAdminRole()) {
    unset($validated['booking_branch_id']);
    unset($validated['package_id']);
    // ...unchanged unsets...
}

// NEW: resolve snapshot before the single write (admin path only,
// since non-admins just had package_id unset).
if (array_key_exists('package_id', $validated)) {
    // $validated['package_id'] already passed required|exists for admins
    $package = Package::findOrFail($validated['package_id']);
    $validated['package_name'] = $package->package_name;
}

$booking->update($validated);

if ($booking->wasChanged('package_id')) {
    $package ??= Package::with(['ticketFare', 'ticketFareInbound', 'ticketFareOutbound'])
        ->find($booking->package_id);
    // ...existing passenger fare / service-charge sync, unchanged...
}
```

- Validation: `'package_id' => 'nullable|exists:packages,id'` (~line 1939) →
  `'required|exists:packages,id'` for callers that can set it (admins). The
  non-admin `unset` gate runs first, so non-admins are unaffected and still
  can never hit the package-change path.
- One write → one `BookingUpdateLog` row containing both `package_id` and
  `package_name`.
- No profit side-effect: `BookingObserver` only recalculates profit on
  `discount_amount` / `discount_value` / `is_cancelled`, not `package_name`.

### 5. `app/Services/BookingService.php` — `processBookingWithPassengers()` (~line 264)

Not out of scope: it is the only other creation path and would otherwise mint
`NULL`-snapshot rows. Set the snapshot there too:

```php
$package = Package::findOrFail($data['package_id']);

$booking = Booking::create([
    // ...existing fields...
    'package_id' => $package->id,
    'package_name' => $package->package_name,
    // ...
]);
```

Throws when `package_id` is missing (package is required).

### 6. Views / serializers / reports — snapshot first, live fallback

| File | Line | Change |
|------|------|--------|
| `resources/views/bookings/index.blade.php` | 256 | `{{ $booking->package_name ?? $booking->package?->package_name ?? 'N/A' }}` (also fixes latent null-package error — current code is `$booking->package->package_name`) |
| `resources/views/bookings/index.blade.php` | 630 | `p.booking?.package_name \|\| p.booking?.package?.package_name \|\| '—'` |
| `resources/views/bookings/invoice-print.blade.php` | 186 | `{{ $booking->package_name ?? $booking->package?->package_name ?? 'Package' }}` |
| `resources/views/passengers/show.blade.php` | 100 | `{{ $passenger->booking?->package_name ?? $passenger->booking?->package?->package_name ?? '-' }}` |
| `resources/views/bookings/edit.blade.php` | 142 | `{{ $booking->package_name ?? $booking->package?->package_name ?? 'N/A' }}` |

**Serializers** — `BookingController` ~line 489, add to the `'booking' => [...]`
array so the pagination JS template has the snapshot (keep the live key as
fallback):

```php
'booking' => [
    // ...existing keys...
    'package_name' => $p->booking?->package_name,
    'package' => [ 'package_name' => $p->booking?->package?->package_name ],
],
```

Apply the same snapshot-first pattern to any other booking/invoice export
serializer found during implementation (inventory via grep for
`booking.package` / `package_name` in controllers + views).

**Reports/exports** — display the snapshot, not the live value:
`BranchWiseReportController` (`booking.package.*` eager loads), dashboard,
profit-loss, user-wise-sales, and any Excel/PDF exports switch every package
*name* display to snapshot-first. Eager loads of
`booking.package.ticketFare*` used for *profit math* stay as-is — only the
name display changes.

**`BookingController@edit` inactive-package push (~1841-1858)** — the dropdown
option label must show the stored value, not the renamed live one:

```php
'package_name' => $booking->package_name ?? $currentPackage->package_name,
```

**Left as live (unchanged, plus `required`):** package selector dropdowns
(create ~122, edit ~134), filters (index filter 502-505, `packagesList`
distinct query at index ~5-7), and selection-preview summary spans (create
259, edit 223 — they preview the *selection*, not history). Add `required` to
the create/edit package `<select>`s.

### 7. Test — `tests/Feature/BookingPackageNameSnapshotTest.php` (new)

Mirrors `BookingTicketFareSyncTest` conventions (RefreshDatabase, acting as
admin; `phpunit.xml` uses MySQL `umrah_test`, so the join-update backfill runs
for real). Cases:

1. `store` without `package_id` → validation failure (required).
2. `store` with `package_id` snapshots `package_name` from the selected
   package.
3. Renaming the package leaves existing `booking.package_name` untouched.
4. Admin `update()` with a different `package_id` refreshes
   `booking.package_name` in the same write — assert exactly one
   `BookingUpdateLog` row containing both keys.
5. **Real backfill test** (does NOT rely on "backfill runs implicitly on the
   fresh test DB" — a fresh migrate touches zero rows): create package +
   booking, force `bookings.package_name = NULL` via `DB::table()->update()`
   to simulate a legacy row, re-run the migration's join-update statement,
   assert the snapshot is repopulated.
6. `BookingService::processBookingWithPassengers()` with `package_id` sets the
   snapshot.
7. Non-admin `update()` cannot change `package_id`/`package_name` (existing
   gate, regression-guarded).

---

## Out of scope

- `ui-references/DATABASE_DESIGN.md` — already stale/outdated, not updated.

## Execution order

1. Audit legacy `NULL`-`package_id` rows (§0)
2. Migration (§1)
3. `Booking` model (`$fillable`, §2)
4. `BookingController@store()` (§3)
5. `BookingController@update()` (§4)
6. `BookingService` (§5)
7. Serializers + views + reports/exports + edit-dropdown label + `required` selects (§6)
8. Feature test (§7) → `php artisan test` + `vendor/bin/pint`
