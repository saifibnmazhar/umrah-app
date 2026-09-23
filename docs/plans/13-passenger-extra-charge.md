# Plan: Passenger Extra Charge (`passengers.extra_charge`)

**Status: PLAN ONLY — DO NOT IMPLEMENT until explicitly approved.**

## Goal
Add an `extra_charge` column to the `passengers` table, allow it to be stored via add-passenger forms and updated via the edit-passenger form, fold it into the effective service charge used for profit calculations, and display "Booking Service Charge" and "Extra Charge" on the passenger details page.

## Design decisions (confirmed)
- **Separate columns, formula sum**: `passengers.booking_service_charge` stays the frozen package base; `passengers.extra_charge` is a new column; effective service charge = `booking_service_charge + extra_charge` computed live in `ProfitCalculationService::calculateServiceCharge()` (`app/Services/ProfitCalculationService.php:656-663`).
- **Display**: passenger details page "Financial Details" section (`resources/views/passengers/show.blade.php:240-272`, gate var defined at `:9`), already gated by `$canViewFinancialSection`. Display invariant: when visa AND ticket profits are both effective, stored `service_charge = booking_service_charge + extra_charge`; before effectiveness the components may display non-zero while effective `service_charge` is `0` (gate, not a bug) — no extra "effective total" row needed.
- **Update handling**: lightweight profit recalc only — refresh stored `passengers.service_charge` + `passengers.profit` and `bookings.profit` via `ProfitCalculationService::recalculateBookingProfit()` (booking-level call only; it already loops all passengers through `recalculatePassengerProfit()`, `ProfitCalculationService.php:70-78`). Lowering `extra_charge` and recomputing automatically lowers `service_charge`/`profit` ("deducted on update").
- **Access control (intentional asymmetry — confirmed)**: create/add paths stay open to anyone who can add a passenger (front-desk staff usually know the charge at entry); the edit form's Extra Charge field is **Super Admin / Co Admin only** (same `hasRole` pattern as `BookingController.php:69`, and same role set as `PassengerController::isFlightDateAdmin()`). Accepted consequence: a non-admin typo at create time can only be corrected by an admin. No stripping on create paths.
- **Recalc rule**: whenever the effectiveness gate is satisfied AND either component (`booking_service_charge` or `extra_charge`) changes, stored `service_charge` + `profit` must refresh (covers both edit-passenger and package-change paths — see steps 6 and 6b).

## Key enabling facts (verified)
- `getPassengerProfitBreakdown()` (`ProfitCalculationService.php:126`) calls `calculateServiceCharge()` (line 134) and its result is written to `passengers.service_charge` by `recalculatePassengerProfit()` via `updateQuietly` (line 53). P&L reports read the **stored** `service_charge`/`profit` columns gated on `service_charge_effective_at` (`DashboardController.php:112-114`, `BranchWiseReportController.php:248-250`), so refreshing those columns is what keeps reports accurate.
- `BookingService::syncFinancials()` (`app/Services/BookingService.php:154-189`) only touches `total_value`/`discount`/`invoice` — it does NOT update `service_charge` or `profit`, so the lightweight recalc is additive and safe. Same for the `BookingController::syncBookingFinancials()` wrapper (`BookingController.php:147-175`).
- `PassengerObserver::updated` (`app/Observers/PassengerObserver.php:29`) already writes a `passenger_update_logs` audit row for any field change — `extra_charge` edits are audited via the main `update()` call. Derived `service_charge`/`profit` writes use `updateQuietly()`/`saveQuietly()` and are intentionally NOT audit-logged (see §9 audit note — no explicit logging added, no observer recursion by design).
- Downstream freshness: `VisaSubmissionObserver`, `IssuedTicketObserver`, `ReIssuedTicketObserver`, `RefundedTicketObserver`, `PackageObserver`, `BookingObserver` all call `recalculateBookingProfit()`, so the new formula propagates on later visa/ticket/package events automatically. EXCEPTION (verified gap, fixed in step 6b): `BookingController@update` package-change loop has no profit recalc afterward, and `PassengerObserver::updated` only recalcs when `is_cancelled` is dirty (`PassengerObserver.php:36-38`).
- `extra_charge` is profit-side only: it must NOT enter `passengers.package_value`, `bookings.total_value`, or invoice totals.

## Steps (implementation order)

### 1. Migration
Create `database/migrations/2026_09_23_000001_add_extra_charge_to_passengers_table.php`:

```php
$table->decimal('extra_charge', 14, 6)->default(0)->after('booking_service_charge');
```

Matches the `booking_service_charge` precedent migration exactly (`2026_09_22_000001_add_booking_service_charge_and_backfill.php:16`). No backfill needed (default 0). Run `php artisan migrate`.

### 2. Model — `app/Models/Passenger.php`
- Add `'extra_charge'` to `$fillable`.
- Add `'extra_charge' => 'decimal:6'` to `$casts` (matches `booking_service_charge` schema).

### 3. Profit calc — `app/Services/ProfitCalculationService.php:656`
Change `calculateServiceCharge()` return to:

```php
return (float) ($passenger->booking_service_charge ?? 0) + (float) ($passenger->extra_charge ?? 0);
```

Keep the existing visa+ticket-effectiveness gate (lines 658-660) unchanged. This single change cascades to `passengers.service_charge`, breakdowns, and booking profit.

**Note/assumption**: `extra_charge` only counts toward service charge when BOTH visa and ticket profit are effective (same rule as the base charge) — confirmed consistent. Single-service passengers follow the existing waivers (`isVisaProfitEffective` returns true for `TICKET_ONLY`, `isTicketProfitEffective` returns true for `VISA_ONLY`, lines 707-726).

### 4. Create booking — `BookingController@store`
- Validation block (lines 1277-1291): add `'passengers.*.extra_charge' => 'nullable|numeric|min:0'`.
- `Passenger::create([...])` (lines 1422-1450): add `'extra_charge' => $passengerData['extra_charge'] ?? 0,`.
- No role stripping here (intentional asymmetry). No profit recalc here (stored `service_charge` stays initial until a later observer fires; gate yields `0` while visa/ticket pending — expected, covered in tests 1-2).

### 5. Add passenger — `BookingController@addPassenger`
- Validation (lines 2231-2247): add `'extra_charge' => 'nullable|numeric|min:0'`.
- Before create: `$validated['extra_charge'] = $validated['extra_charge'] ?? 0;` (mirrors line 2271 for `booking_service_charge`).
- No role stripping here (intentional asymmetry). No profit recalc here (same reason as step 4).

### 6. Edit passenger — `PassengerController@update`
- **Pre-validation role strip** (mirrors the existing flight-date pattern at lines 567-570, reuse `isFlightDateAdmin()` — identical Super/Co Admin role set): if NOT admin → `$request->request->remove('extra_charge');` BEFORE validation, so non-admins sending junk in the field don't get a 422 for a field they can't use.
- Validation (lines 572-594): add `'extra_charge' => 'nullable|numeric|min:0'`.
- **Normalization** (NOT NULL column protection): after validation,
  ```php
  if (array_key_exists('extra_charge', $validated)) {
      $validated['extra_charge'] = ($validated['extra_charge'] === null || $validated['extra_charge'] === '') ? 0 : $validated['extra_charge'];
  }
  ```
- After `$passenger->update($validated)` and the existing passenger_type/visa_only branching, determine recalc scope with `wasChanged()` (not payload-key presence):
  ```php
  $extraOnly = $passenger->wasChanged('extra_charge')
      && ! $passenger->wasChanged(['passenger_type', 'service_required', 'stay_duration', 'flight_date_from', 'flight_date_to', 'ticket_fare_id', 'ticket_fare_inbound_id', 'ticket_fare_outbound_id']);
  if (! $extraOnly) {
      // existing syncFinancials() call — MUST run first: it recomputes discount_amount,
      // which recalculateBookingProfit() consumes (booking profit subtracts discount).
      $this->bookingService->syncFinancials($booking, 'passenger_updated');
  }
  if ($passenger->wasChanged('extra_charge') || ! $extraOnly) {
      // single booking-level call only — it already loops all passengers
      // through recalculatePassengerProfit() internally.
      app(\App\Services\ProfitCalculationService::class)->recalculateBookingProfit($passenger->fresh()->booking);
  }
  ```
  (Refreshes `passengers.service_charge` = base+extra when effective, `passengers.profit`, `bookings.profit`. Combined edits get `sync → profit` in that order; extra-only edits skip `syncFinancials()`, honoring "lightweight recalc only".)

### 6b. Package change — `BookingController@update` (fixes verified freshness gap)
- The package-change loop (lines 2011-2012) resets `booking_service_charge` to the new package charge but currently triggers NO profit recalc (`PassengerObserver::updated` only recalcs on `is_cancelled` dirtiness; `syncBookingFinancials` never touches profit columns).
- After the affected-passenger loop and before `syncBookingFinancials($booking, ...)` (lines 2085-2086), add:
  ```php
  app(\App\Services\ProfitCalculationService::class)->recalculateBookingProfit($booking->fresh());
  ```
- `extra_charge` is NOT touched by the package change — it persists as a manual per-passenger adjustment (assumption, confirmed).

### 7. Views / JS wiring
- **`resources/views/partials/passenger-form-modal.blade.php`**: add "Extra Charge (SAR)" numeric input bound to `passengerData.extra_charge` (used by both the create page and the show-page Add Passenger modal).
- **`resources/js/booking.js`** — all 4 `savePassenger()` definitions considered:
  - Default `passengerData` literal (`:65-90`) AND fresh-reset literal (`:482-506`): initialize `extra_charge: 0` in **both** (otherwise a stale value leaks into the next modal open).
  - `bookingCreateApp.savePassenger` (`:520-544`, local push): add `passengerCopy.extra_charge = parseFloat(this.passengerData.extra_charge) || 0;` coercion; spread carries the rest.
  - Second app `savePassenger` (`:1703-1748`, local push + docs): same coercion.
  - Third app `savePassenger` (`:3505-3529`, push-only, no edit branch — pre-existing, out of scope): same coercion.
  - `showBookingApp.savePassenger` (`:4266-4307`, fetch POST `/bookings/{id}/passengers`): add `extra_charge: parseFloat(this.passengerData.extra_charge) || 0` to the JSON body (`:4293-4307`).
  - `openPassengerModal(index)` edit-copy (`:452-457`): spread carries `extra_charge` automatically once present — no change.
- **`resources/views/bookings/create.blade.php`**: add hidden `passengers[i][extra_charge]` in the per-passenger hidden field loop (`:209-228`); show value in the passenger summary row.
- **`resources/views/passengers/edit.blade.php`**: add "Extra Charge (SAR)" input in Service Information (`:97-119`), rendered only when `@if($canEditExtraCharge)`; add `extra_charge: 0` to `passengerData` init (`:318-351`), load in `loadPassengerData()` (starts `:383`), and include `extra_charge: parseFloat(this.passengerData.extra_charge) || 0` in the PUT `payload` (`:1139-1156`).
- **`resources/views/passengers/show.blade.php`**: inside `@if($canViewFinancialSection)` Financial Details (`:240-272`), add:
  - "Booking Service Charge (SAR)" → `@currency($passenger->booking_service_charge, 2, $rate)`
  - "Extra Charge (SAR)" → `@currency($passenger->extra_charge, 2, $rate)`
- **Controller flag wiring** (`$canEditExtraCharge` does not exist yet — must be added):
  - `PassengerController@show` (returns view at `:290`): compute `$canEditExtraCharge` with the same intersect pattern as existing `$canEditVisa` (`:211-213`) and add to `compact()`.
  - `PassengerController@edit` (returns view at `:391`): same flag + `compact()`.

### 8. Files touched
| File | Change |
|---|---|
| `database/migrations/2026_09_23_000001_add_extra_charge_to_passengers_table.php` | new migration |
| `app/Models/Passenger.php` | `$fillable`, `$casts` |
| `app/Services/ProfitCalculationService.php:656` | `calculateServiceCharge()` formula |
| `app/Http/Controllers/BookingController.php` | `store()` validation + create; `addPassenger()` validation + create; `update()` package-change recalc (step 6b) |
| `app/Http/Controllers/PassengerController.php` | `show()`/`edit()` `$canEditExtraCharge` flag; `update()` pre-validation strip, validation, normalization, `wasChanged`-based recalc ordering |
| `resources/views/partials/passenger-form-modal.blade.php` | Extra Charge input |
| `resources/views/bookings/create.blade.php` | hidden input + summary row |
| `resources/views/passengers/edit.blade.php` | Extra Charge field (admin-gated) + Alpine wiring |
| `resources/views/passengers/show.blade.php` | Financial Details display rows |
| `resources/js/booking.js` | `passengerData.extra_charge` init (2 literals) + coercion in all 4 `savePassenger()` paths + fetch payload |

### 9. Edge cases / notes
- `extra_charge` only contributes to service charge when both visa and ticket profit are effective (inherits `calculateServiceCharge()` gate). Same rule as the base charge. Single-service waivers (`TICKET_ONLY`/`VISA_ONLY`) apply identically.
- `extra_charge` does NOT affect `package_value`, `total_value`, or invoice — profit-side only.
- Audit: `PassengerObserver` logs the user-entered `extra_charge` change via the main `update()` call. Derived `service_charge`/`profit` writes use `updateQuietly()`/`saveQuietly()` and are intentionally NOT audit-logged — this also avoids observer recursion (a plain `update()` inside the recalc would re-fire `updated()`, re-log, and risk an update→observer→recalc→update loop). No explicit logging added: it would cost an extra DB write on every booking-wide recalc (visa/ticket/package events), `Auth::user()` can be null outside request context, and computed-column rows would pollute a user-input audit trail. Traceability = `extra_charge` audit row + `service_charge_effective_at` timestamp.
- Double-ticket bookings: `extra_charge` is per-passenger, works identically.
- Fare-snapshot interplay: package change resets `booking_service_charge` but does NOT touch `extra_charge` — persists as manual adjustment (confirmed). Stored `service_charge` refresh is now guaranteed by the step-6b recalc.
- `service_charge` column freshness: on `extra_charge` change, the step-6 recalc updates it; on other triggering flows (visa/ticket observers, package observer), `recalculateBookingProfit` refreshes it with the new formula automatically.
- Cancelled passengers: `recalculateBookingProfit()` zeroes cancelled passengers' profit — `extra_charge` on a cancelled passenger is inert by design.
- Currency: `extra_charge` is stored in SAR like `booking_service_charge`; `@currency(..., 2, $rate)` display matches existing Financial Details rows (stored precision is 6 decimals).

### 10. Manual test checklist
1. **Create booking** with Extra Charge = 100 → verify `passengers.extra_charge = 100` AND `service_charge = 0` while visa/ticket pending (gate); then drive visa→issued + ticket→issued → verify stored `service_charge = base + 100` and `profit` includes it.
2. **Add passenger** (show page "+ Add Passenger") with Extra Charge → same two-phase verification as (1).
3. **Edit as Super Admin**: raise extra charge 100 → 150 (after effectiveness) → verify stored `service_charge`/`profit` update; audit row present in `passenger_update_logs` for `extra_charge`.
4. **Edit as Super Admin**: lower extra charge 150 → 50 → verify `service_charge`/`profit` drop by 100 ("deducted").
5. **Edit as non-admin**: send `extra_charge` in payload → stripped pre-validation (column unchanged, no 500); sending garbage `extra_charge` as non-admin → no 422 from that field.
6. **Financial display**: as a financial-section role, open `passengers/show` → "Booking Service Charge" and "Extra Charge" rows show correct values; as a non-financial role, rows hidden (section gated).
7. **Reports**: confirm P&L report `service_charge` reflects base + extra after recalc.
8. **Package change**: change package on a booking → `booking_service_charge` resets to new package charge; `extra_charge` persists untouched; stored `service_charge` refreshes (step-6b recalc).
9. **Visa-only / not-effective**: passenger whose visa/ticket is not yet effective → `service_charge = 0` even with `extra_charge > 0` (inherits gate).
10. **Invoice immutability**: confirm invoice `total_amount` unchanged after `extra_charge` create/edit (profit-side only).
11. **Validation**: negative and non-numeric `extra_charge` → 422; null/`''` on edit → stored `0`, no 500.
12. **Combined edit**: change `extra_charge` + `passenger_type` in one PUT → both `syncFinancials` and profit recalc ran in order (invoice totals AND profit correct).
13. **Cancelled passenger**: set `extra_charge` on a cancelled passenger → profit stays `0`.
14. **Single-service**: `visa_only` passenger with visa issued (no ticket) → `extra_charge` counts; `ticket_only` with ticket issued (no visa) → counts.
