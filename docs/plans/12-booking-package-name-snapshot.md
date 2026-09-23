# Plan: `bookings.package_name` Snapshot Column

Implements a `package_name` snapshot on the `bookings` table so a booking keeps
the package name it was booked under even after an in-use package is renamed
(`package_name` is now editable on locked packages per the fare-snapshot plan).

## Decisions
- **Snapshot refresh policy**: `package_name` is set at booking creation and
  refreshed **only** when the booking's `package_id` changes. Renaming an
  in-use package does NOT rename existing bookings.
- **Display**: On-screen package name displays read the snapshot first with a
  live `package.package_name` fallback. Package selectors/filters/summary
  spans stay live (they drive selection, not history).

---

## Files & Changes

### 1. Migration — `database/migrations/2026_09_23_000001_add_package_name_to_bookings_and_backfill.php` (new)

```php
Schema::table('bookings', function (Blueprint $table) {
    $table->string('package_name')->nullable()->after('package_id');
});
```

Backfill (same pattern as `2026_09_22_000001_add_booking_service_charge_and_backfill.php`):

```php
DB::transaction(function () {
    DB::table('bookings')
        ->join('packages', 'bookings.package_id', '=', 'packages.id')
        ->update(['bookings.package_name' => DB::raw('packages.package_name')]);
});
```

- Plain `DB::table` update → no `BookingObserver` log rows.
- Bookings with `package_id = NULL` stay NULL.
- FK is `restrictOnDelete`, so referenced packages always exist.
- `down()`: drop the column.

### 2. `app/Models/Booking.php`

Add `'package_name'` to `$fillable`.

### 3. `app/Http/Controllers/BookingController.php` — `store()` (~line 1365)

Resolve the name once before `Booking::create` and include it in the payload:

```php
$selectedPackageName = filled($validated['package_id'] ?? null)
    ? Package::whereKey($validated['package_id'])->value('package_name')
    : null;

$booking = Booking::create([
    // ...existing fields...
    'package_name' => $selectedPackageName,
]);
```

Avoids a post-create `update` + extra observer log.

### 4. `BookingController::update()` — snapshot refresh only on package change

Inside the existing package-change block (line ~1977,
`if ($request->has('package_id') && $booking->wasChanged('package_id'))`),
after `$package` is resolved:

```php
$booking->update(['package_name' => $package->package_name]);
```

- No profit side-effect: `BookingObserver` only recalculates profit on
  `discount_amount` / `discount_value` / `is_cancelled`, not `package_name`.
- Non-admins never hit this block (their `package_id` is `unset` in `update()`).

### 5. Views — prefer snapshot, keep live fallback

| File | Line | Change |
|------|------|--------|
| `resources/views/bookings/index.blade.php` | 256 | `{{ $booking->package_name ?? $booking->package?->package_name ?? 'N/A' }}` |
| `resources/views/bookings/index.blade.php` | 630 | `p.booking?.package_name \|\| p.booking?.package?.package_name \|\| '—'` |
| `resources/views/bookings/invoice-print.blade.php` | 186 | `{{ $booking->package_name ?? $booking->package?->package_name ?? 'Package' }}` |
| `resources/views/passengers/show.blade.php` | 100 | `{{ $passenger->booking?->package_name ?? $passenger->booking?->package?->package_name ?? '-' }}` |
| `resources/views/bookings/edit.blade.php` | 142 | `{{ $booking->package_name ?? $booking->package?->package_name ?? 'N/A' }}` |

**Serializer addition** — `BookingController` ~line 489, add to the
`'booking' => [...]` array so the pagination JS template has the snapshot:

```php
'booking' => [
    // ...existing keys...
    'package_name' => $p->booking?->package_name,
    'package' => [ 'package_name' => $p->booking?->package?->package_name ],
],
```

**Left as live (unchanged):** package selector dropdowns, filters, and
summary spans (create 128/259, edit 134/223, index filter 502-505).

### 6. Test — `tests/Feature/BookingPackageNameSnapshotTest.php` (new)

Mirrors `BookingTicketFareSyncTest` conventions (RefreshDatabase, acting as
admin). Cases:
1. `store` snapshots `package_name` from the selected package.
2. Renaming the package leaves existing `booking.package_name` untouched.
3. Changing a booking's package via `update()` refreshes `booking.package_name`.
4. Backfill runs implicitly on the fresh test DB (migration).

---

## Out of scope
- `BookingService::processBookingWithPassengers()` — unused (only definition
  found, no callers) → left untouched.
- `ui-references/DATABASE_DESIGN.md` — already stale/outdated, not updated.

## Execution order
1. Migration
2. `Booking` model (`$fillable`)
3. `BookingController@store()`
4. `BookingController@update()`
5. Serializer + 5 view files
6. Feature test → `php artisan test` + `vendor/bin/pint`
