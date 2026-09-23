# Plan: Passenger Extra Charge (`passengers.extra_charge`)

**Status: PLAN ONLY — DO NOT IMPLEMENT until explicitly approved.**

## Goal
Add an `extra_charge` column to the `passengers` table, allow it to be stored via add-passenger forms and updated via the edit-passenger form, fold it into the effective service charge used for profit calculations, and display "Booking Service Charge" and "Extra Charge" on the passenger details page.

## Design decisions (confirmed)
- **Separate columns, formula sum**: `passengers.booking_service_charge` stays the frozen package base; `passengers.extra_charge` is a new column; effective service charge = `booking_service_charge + extra_charge` computed live in `ProfitCalculationService::calculateServiceCharge()` (`app/Services/ProfitCalculationService.php:656`).
- **Display**: passenger details page "Financial Details" section (`resources/views/passengers/show.blade.php` ~240-259), already gated by `$canViewFinancialSection`.
- **Update handling**: lightweight profit recalc only — refresh stored `passengers.service_charge` + `passengers.profit` and `bookings.profit` via `ProfitCalculationService::recalculatePassengerProfit()` + `recalculateBookingProfit()`. Lowering `extra_charge` and recomputing automatically lowers `service_charge`/`profit` ("deducted on update").
- **Access control**: create paths open to anyone who can add a passenger; the edit form's Extra Charge field is **Super Admin / Co Admin only** (same `hasRole` pattern as `BookingController.php:69`).

## Key enabling facts (verified)
- `getPassengerProfitBreakdown()` (`ProfitCalculationService.php:126`) calls `calculateServiceCharge()` (line 134) and its result is written to `passengers.service_charge` by `recalculatePassengerProfit()` via `updateQuietly` (line 53). P&L reports read the **stored** `service_charge`/`profit` columns (raw SQL), so refreshing those columns is what keeps reports accurate.
- `BookingService::syncFinancials()` (`app/Services/BookingService.php:154`) only touches `total_value`/`discount`/`invoice` — it does NOT update `service_charge` or `profit`, so the lightweight recalc is additive and safe.
- `PassengerObserver::updated` (`app/Observers/PassengerObserver.php:29`) already writes a `passenger_update_logs` audit row for any field change — `extra_charge` edits are audited for free.
- `extra_charge` is profit-side only: it must NOT enter `passengers.package_value`, `bookings.total_value`, or invoice totals.

## Steps (implementation order)

### 1. Migration
Create `database/migrations/2026_09_23_000001_add_extra_charge_to_passengers_table.php`:

```php
$table->decimal('extra_charge', 14, 6)->default(0)->after('booking_service_charge');
```

No backfill needed (default 0). Run `php artisan migrate`.

### 2. Model — `app/Models/Passenger.php`
- Add `'extra_charge'` to `$fillable`.
- Add `'extra_charge' => 'decimal:6'` to `$casts` (matches `booking_service_charge` schema).

### 3. Profit calc — `app/Services/ProfitCalculationService.php:656`
Change `calculateServiceCharge()` return to:

```php
return (float) ($passenger->booking_service_charge ?? 0) + (float) ($passenger->extra_charge ?? 0);
```

Keep the existing visa+ticket-effectiveness gate (lines 658-660) unchanged. This single change cascades to `passengers.service_charge`, breakdowns, and booking profit.

**Note/assumption**: `extra_charge` only counts toward service charge when BOTH visa and ticket profit are effective (same rule as the base charge) — consistent, flagged for visibility.

### 4. Create booking — `BookingController@store`
- Validation block (~lines 1277-1291): add `'passengers.*.extra_charge' => 'nullable|numeric|min:0'`.
- `Passenger::create([...])` (~lines 1422-1450): add `'extra_charge' => $passengerData['extra_charge'] ?? 0,`.

### 5. Add passenger — `BookingController@addPassenger`
- Validation (~lines 2231-2247): add `'extra_charge' => 'nullable|numeric|min:0'`.
- Before create: `$validated['extra_charge'] = $validated['extra_charge'] ?? 0;` (mirrors line 2271 for `booking_service_charge`).

### 6. Edit passenger — `PassengerController@update`
- Validation (~line 586): add `'extra_charge' => 'nullable|numeric|min:0'`.
- **Role gate**: if NOT Super/Co Admin and `extra_charge` present in payload → unset it from `$validated` (ignore server-side too), so the field is read-only for others.
- After `$passenger->update($validated)` and the existing passenger_type/visa_only branching:
  - If `extra_charge` changed: run lightweight recalc:
    ```php
    $profitService = app(ProfitCalculationService::class);
    $profitService->recalculatePassengerProfit($passenger->fresh());
    $profitService->recalculateBookingProfit($passenger->fresh()->booking);
    ```
    (refreshes `passengers.service_charge` = base+extra, `passengers.profit`, `bookings.profit`).
  - Keep the existing `syncFinancials()` call **only when other total-affecting fields changed** in the same request (`passenger_type`, `service_required`, `stay_duration`, `flight_date_*`, `ticket_fare*`); skip it when `extra_charge` is the only financial-dirty field (honors "lightweight recalc only"). Judgment call — confirm before implementing.

### 7. Views / JS wiring
- **`resources/views/partials/passenger-form-modal.blade.php`**: add "Extra Charge (SAR)" numeric input bound to `passengerData.extra_charge` (used by both the create page and the show-page Add Passenger modal).
- **`resources/js/booking.js`**:
  - `bookingCreateApp`: initialize `passengerData.extra_charge: 0`.
  - `showBookingApp` `savePassenger()` payload (lines 4266-4307): add `extra_charge: this.passengerData.extra_charge || 0`.
- **`resources/views/bookings/create.blade.php`**: add hidden `passengers[i][extra_charge]` in the per-passenger hidden field loop; show value in the passenger summary row.
- **`resources/views/passengers/edit.blade.php`**: add "Extra Charge (SAR)" input in Service Information (~lines 97-115), rendered only when `@if($canEditExtraCharge)` (server flag = Super/Co Admin); include in `passengerData`, `loadPassengerData()` (~line 383), and `savePassenger()` payload (lines 1139-1156).
- **`resources/views/passengers/show.blade.php`**: inside `@if($canViewFinancialSection)` Financial Details (lines 254-258), add:
  - "Booking Service Charge (SAR)" → `@currency($passenger->booking_service_charge, 2, $rate)`
  - "Extra Charge (SAR)" → `@currency($passenger->extra_charge, 2, $rate)`

### 8. Files touched
| File | Change |
|---|---|
| `database/migrations/2026_09_23_000001_add_extra_charge_to_passengers_table.php` | new migration |
| `app/Models/Passenger.php` | `$fillable`, `$casts` |
| `app/Services/ProfitCalculationService.php:656` | `calculateServiceCharge()` formula |
| `app/Http/Controllers/BookingController.php` | `store()` validation + create; `addPassenger()` validation + create |
| `app/Http/Controllers/PassengerController.php` | `update()` validation, role gate, lightweight recalc |
| `resources/views/partials/passenger-form-modal.blade.php` | Extra Charge input |
| `resources/views/bookings/create.blade.php` | hidden input + summary row |
| `resources/views/passengers/edit.blade.php` | Extra Charge field (admin-gated) + Alpine wiring |
| `resources/views/passengers/show.blade.php` | Financial Details display rows |
| `resources/js/booking.js` | `passengerData.extra_charge` init + both `savePassenger()` payloads |

### 9. Edge cases / notes
- `extra_charge` only contributes to service charge when both visa and ticket profit are effective (inherits `calculateServiceCharge()` gate). Same rule as the base charge.
- `extra_charge` does NOT affect `package_value`, `total_value`, or invoice — profit-side only.
- Audit trail automatic via `PassengerObserver` → `passenger_update_logs`.
- Double-ticket bookings: `extra_charge` is per-passenger, works identically.
- Fare-snapshot interplay: package change in `BookingController@update()` (~line 2012) resets `booking_service_charge` to the new package charge but does **not** touch `extra_charge` — it persists as a manual per-passenger adjustment (assumption).
- `service_charge` column freshness: on `extra_charge` change, the lightweight recalc updates it; on other triggering flows (visa/ticket observers, package observer), `recalculatePassengerProfit` also refreshes it with the new formula automatically.

### 10. Manual test checklist
1. **Create booking** with Extra Charge = 100 on a passenger → verify `passengers.extra_charge = 100`, `service_charge`/`profit` include it when visa+ticket effective.
2. **Add passenger** (show page "+ Add Passenger") with Extra Charge → same verification.
3. **Edit as Super Admin**: raise extra charge 100 → 150 → verify stored `service_charge`/`profit` update; audit row present in `passenger_update_logs`.
4. **Edit as Super Admin**: lower extra charge 150 → 50 → verify `service_charge`/`profit` drop by 100 ("deducted").
5. **Edit as non-admin**: send `extra_charge` in payload → ignored (column unchanged, no 500).
6. **Financial display**: as a financial-section role, open `passengers/show` → "Booking Service Charge" and "Extra Charge" rows show correct values; as a non-financial role, rows hidden (section gated).
7. **Reports**: confirm P&L report `service_charge` reflects base + extra after recalc.
8. **Package change**: change package on a booking → `booking_service_charge` resets to new package charge; `extra_charge` persists untouched.
9. **Visa-only / not-effective**: passenger whose visa/ticket is not yet effective → `service_charge = 0` even with `extra_charge > 0` (inherits gate).
10. **Invoice immutability**: confirm invoice `total_amount` unchanged after `extra_charge` create/edit (profit-side only).
