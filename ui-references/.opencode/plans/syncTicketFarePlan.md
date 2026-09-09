# Sync Passenger Ticket Fare to Package — Fix Plan

## Problem

Passenger `ticket_fare_id` can mismatch the package's `ticket_fare_id` (e.g. booking 1416 / package 92 has fare **89**, passenger 2028 got fare **47**). Root causes:

1. **Create-form package change bug:** `createBookingApp.onPackageChange()` (`resources/js/booking.js:1368-1370`) only recalculates totals; it does NOT re-sync `ticket_fare_id` on already-added passengers. The edit form (`booking.js:3218-3245`) DOES. Result: a passenger added under package A keeps A's fare when the package is switched to B, and the stale value is submitted via the hidden input (`resources/views/bookings/create.blade.php:220`).
2. **Backend trusts the frontend value:** `BookingController::store()` (1050-1060) and `addPassenger()` (1739-1749) use `$passengerData['ticket_fare_id'] ?? $booking->package?->ticket_fare_id` — the frontend value wins even though the create/edit UI's ticket select is permanently `disabled` (`partials/passenger-form-modal.blade.php:113`, `passengers/edit.blade.php:147`).

Duration of mismatch: the pending `issued_ticket` inherits the passenger's fare, and `package_value`/`total_value`/invoice/profit are all computed from the wrong fare.

## Step 1 — Frontend: re-sync fares on package change (create form)

**File:** `resources/js/booking.js` — `createBookingApp.onPackageChange()` (~:1368)

Rewrite to match edit-form behavior, backend-consistent:

```js
onPackageChange() {
    const pkg = this.bookingData.package_id
        ? this.allPackages.find(p => String(p.id) === String(this.bookingData.package_id))
        : null;
    if (pkg) {
        this.passengers.forEach(p => {
            if ((p.service_required || '') === 'visa_only') return; // backend skips visa_only
            if (pkg.is_double_ticket) {
                p.ticket_fare_id = null;
                p.ticket_fare_inbound_id = pkg.ticket_fare_inbound_id ? String(pkg.ticket_fare_inbound_id) : '';
                p.ticket_fare_outbound_id = pkg.ticket_fare_outbound_id ? String(pkg.ticket_fare_outbound_id) : '';
                // populate inbound/outbound route_type/flight_type/route/airline/class from package fares
            } else if (pkg.ticket_fare_id) {
                p.ticket_fare_id = String(pkg.ticket_fare_id);
                p.ticket_fare_inbound_id = '';
                p.ticket_fare_outbound_id = '';
                // populate route/airline/class from package fare (mirror edit app ~3223-3239)
            }
        });
    }
    this.recalculateAllPassengerValues();
},
```

Behavior mirrors `store()`: double-ticket → null single + set inbound/outbound; single-ticket → set `ticket_fare_id`, null inbound/outbound; `visa_only` → untouched.

**Verify:** no JS test infra exists → `npm run build` + manual (add passenger, switch package, submit, check DB).

## Step 2 — Backend: derive fares from the package only

**File:** `app/Http/Controllers/BookingController.php`

Remove the frontend fallbacks in both places; ignore submitted values:

- `store()` (1050-1060):
```php
'ticket_fare_id' => $isDoubleTicket
    ? null
    : (($passengerData['service_required'] ?? '') === 'visa_only'
        ? null
        : $booking->package?->ticket_fare_id),
'ticket_fare_inbound_id' => $isDoubleTicket ? $booking->package?->ticket_fare_inbound_id : null,
'ticket_fare_outbound_id' => $isDoubleTicket ? $booking->package?->ticket_fare_outbound_id : null,
```
- `addPassenger()` (1739-1749): same pattern.

**Tests (TDD):** new `tests/Feature/BookingTicketFareSyncTest.php` (RefreshDatabase):
- store: package fare 89 + `passengers[0][ticket_fare_id]=47` → passenger **and** created `issued_ticket` assert 89
- addPassenger: same
- `visa_only` → `ticket_fare_id` null, no fare on issued_ticket
- double-ticket package → single `ticket_fare_id` null, inbound/outbound synced

## Step 3 — Data repair: extend `passengers:sync-ticket-fare-v2`

**File:** `app/Console/Commands/SyncPassengerTicketFareV2.php`

Existing `SyncPassengerTicketFareV2` already satisfies the core requirement: skips `visa_only` (incl. NULL), matches single `ticket_fare_id`, matches double-ticket inbound/outbound + nulls single fare, recalculates `package_value`. **Gaps to close:**

1. **Strict single-ticket match** (line 64-65): remove the `$packageFareId !== null` guard so a passenger's stale value is nulled when the package fare is null.
2. **Sync `issued_tickets`** after each passenger update — only where:
   `it.issue_type IS NULL AND it.status = 'pending'` (soft-deleted rows excluded via default scope):
   `IssuedTicket::where('passenger_id', $id)->whereNull('issue_type')->where('status', 'pending')->update(['ticket_fare_id' => $passenger->ticket_fare_id]);`

Keep existing visa_only filtering + `package_value` recalc. **Do not touch** V1 `passengers:sync-ticket-fare` (superseded, double-ticket unsafe).

**Tests (TDD):** new `tests/Feature/SyncPassengerTicketFareV2Test.php`:
- mismatched single-ticket (47→89 case) → passenger + pending issued_ticket synced, `package_value` recalculated
- double-ticket + stale single fare → nulled, inbound/outbound synced
- visa_only → untouched
- issued_ticket with `issue_type` set or status ≠ pending → not modified

## Post-run (for booking 1416)

```bash
php artisan passengers:sync-ticket-fare-v2
php artisan bookings:sync-financials   # recomputes passenger package_value + booking total_value + discount + invoice
php artisan profit:backfill             # recomputes passenger/booking profit (run AFTER sync-financials)
```

> Note: `sync-financials` recomputes `package_value` itself, so the ad-hoc SQL value update is optional. To scope to 1416 only, use `php artisan tinker` + `BookingService::syncFinancials(Booking::find(1416))`.

## Commit checklist
- `vendor/bin/pint`
- `php artisan test`
- `npm run build`
- One commit per logical change (Step 1, Step 2, Step 3, tests)