# Plan: Immutable passenger `package_value` + historic price recalculation

**Status: PLAN ONLY — DO NOT IMPLEMENT until explicitly approved.**

## Problem

`passengers.package_value` is presented as a stored, invoice-relevant value, but it is
**re-derived from LIVE prices on nearly every booking/passenger mutation**:

- `BookingService::calculatePackageValue()` (`app/Services/BookingService.php:73-127`) reads
  **live** `ticket_fares.selling_fare/offer_price/child%/infant%`, **live**
  `packages.visaSellingPrice.selling_price`, **live** `packages.service_charge`.
- `BookingService::recalculateBookingTotal()` (`app/Services/BookingService.php:129-154`)
  unconditionally overwrites **every** passenger's `package_value`, then
  `bookings.total_value`.
- `BookingService::syncFinancials()` (`app/Services/BookingService.php:156-191`) pushes the
  delta into `discount_amount` and `invoices.total_amount`.

It is invoked from:

| Trigger | Location |
|---|---|
| Any booking edit (incl. discount-only, remark-only) | `BookingController.php:2181` |
| Fingerprint location change | `BookingController.php:2268` |
| Passenger add / remove | `BookingController.php:2467, 2501` |
| Any passenger edit (name, address, …) — recomputes **all** passengers | `PassengerController.php:636, 693` |
| Manual commands | `SyncBookingFinancials.php:22`, `SyncPassengerTicketFare.php:41-46`, `SyncPassengerTicketFareV2.php:79-84` |
| Unused-but-reachable endpoint | `BookingController.php:2877-2892` (`routes/web.php:185`) |

**Net symptom:** edit a Package (`service_charge`, `visa_selling_price_id`) or a TicketFare
(`selling_fare`, `offer_price` — which also cascades into `packages.regular_price` via
`TicketFareController::cascadeFarePriceToPackages()` :434-506), then touch *anything* on an
old booking → its stored `package_value`, `bookings.total_value` and
`invoices.total_amount` silently change to the new prices.

Secondary write path: `PackageObserver::updated` (`app/Observers/PackageObserver.php:35-46`)
reacts to a `service_charge` change by writing **passenger rows** (profit columns via
`ProfitCalculationService`) — snapshot-based and usually value-neutral; out of scope here.

## Desired behavior (confirmed)

1. Package updates and ticket fare updates must have **no impact** on stored
   `passengers.package_value` / `bookings.total_value` / `invoices.total_amount`.
2. Values are recalculated **only** on: booking package swap, passenger type change —
   plus the structurally unavoidable set below.
3. Recalculation must use **historic** prices/fares (log replay), never live rows.
4. **If a package/fare was never updated after creation, the current price is taken**
   (natural fallback of log replay: no history ⇒ live value).

## Design decisions (confirmed)

| # | Decision | Detail |
|---|---|---|
| D1 | **Anchor: package swap** | `now()` — the new package's prices as of the swap moment (what the admin sees on screen) |
| D2 | **Anchor: type / extra_charge / service_required change** | `passenger.created_at` — historic replay back to the passenger's original booking date, so later price edits never affect the recalc |
| D3 | **Anchor: creation / passenger add** | `now()` (== creation time) |
| D4 | **Other required triggers** | passenger add (compute **only** the new one), passenger remove (re-sum only), `extra_charge` edit (only that passenger), `service_required` change (only that passenger), fingerprint charge change (frozen FK drives the total) |
| D5 | **History gaps** | Harden observers to always log (nullable `user_id`); accept pre-2026-09-22 fallback; ship validation queries |

---

## Phase 1 — Historic calculator

### 1a. `Package::valueAt($column, $at)` (new)

Mirror `TicketFare::valueAt()` (`app/Models/TicketFare.php:126-145`) over
`package_update_logs` (migration `2026_09_22_000002`).

- **Must honor `action='fare_updated'`** — written manually after the fare→package cascade
  (`TicketFareController.php:494-504`, after `updateQuietly()` which suppresses observers).
  The existing replay engine only understands `created`/`updated`.
- Seed: first `created` event → `new_values[$column]`; else first event's
  `old_values[$column]`; **fallback = live column value** (implements decision rule 4:
  never updated ⇒ current price).
- Skip rows with null `created_at` (column is nullable).
- Extract a shared pure helper (e.g. `App\Support\LogReplay::valueFromEvents()`) used by
  both `TicketFare` and `Package`. Keep `TicketFare::percentageAtFromLogs()`
  (`app/Models/TicketFare.php:162-191`) signature/behavior backward-compatible — covered by
  `tests/Unit/TicketFareReplayTest.php`.

### 1b. `BookingService::calculatePackageValue(Passenger $p, CarbonInterface $at)`

Replace every live read with a historic lookup anchored at `$at`:

| Component | Was (live) | Becomes (historic) |
|---|---|---|
| Ticket (single) | `$ticketFare->selling_fare/offer_price`, live child/infant % | `TicketFare::valueAt('selling_fare'\|'offer_price'\|'ticket_type', $at)` + `valueAt('child_fare_percentage'\|'infant_fare_percentage', $at)` |
| Ticket (double) | `$package->ticketFareInbound/Outbound` live | same replay against the **passenger's** `ticket_fare_inbound_id` / `ticket_fare_outbound_id` fares (kept in sync on swap at `BookingController.php:2071-2093`) |
| Visa | `$package->visaSellingPrice->selling_price` (line 118) | replay `visa_selling_price_id` at `$at` → immutable `visa_selling_prices` row → `selling_price`. Reuse/adapt `BackfillVisaSubmissionPrices::resolvePriceAt()` (`app/Console/Commands/BackfillVisaSubmissionPrices.php:147-177`) — reverse-walk `package_update_logs` rows after `$at`, substituting `old_values`; no log ⇒ current FK |
| Service charge | `$package->service_charge` (lines 119/123) | `Package::valueAt('service_charge', $at)` |
| Extra charge | `$passenger->extra_charge` | unchanged (current — it is a direct passenger edit) |

Notes:

- `$package->is_double_ticket` stays a package-level read: safe because in-use packages are
  locked (`Package::isLocked()`, `app/Models/Package.php:64-76`) — the flag cannot change
  while the booking exists.
- `ticket_fares.effective_from/to` are **not** price-validity ranges (only used by
  `ExpireTicketFares` to flip `is_active`) — they play no role here.
- Creation-time call (`$at = now()`) replays to now ⇒ equals today's prices ⇒ behavior at
  booking creation is numerically unchanged.

---

## Phase 2 — Split `BookingService::syncFinancials`

Replace the monolith (`app/Services/BookingService.php:156-191`) with three methods:

1. **`recalculatePassengerValues(Booking $b, ?Passenger $only, CarbonInterface $at)`**
   — writes `package_value` via the historic calculator for all passengers of the booking,
   or only `$only`.
2. **`refreshBookingTotals(Booking $b)`**
   — `total_value = Σ stored package_value + fingerprintCharge`, where the fingerprint
   charge reads the **frozen** `bookings.fingerprint_charge_id` FK
   (`$booking->fingerprintCharge?->fingerprint_charge`, 0 when `location === 'office'`),
   falling back to the live district lookup (`BookingService.php:250-259`) only when the FK
   is null (old rows). No `package_value` writes.
3. **`syncFinancials($booking, $reason)`**
   — `refreshBookingTotals` + existing discount recompute (lines 163-177) + existing
   invoice delta sync (lines 179-190). **Never touches `package_value`** — safe to call on
   every mutation.

### Trigger → action matrix

| Event | Recompute scope | Anchor | Then |
|---|---|---|---|
| Booking store | all passengers | `now()` | `refreshBookingTotals` + discount + invoice |
| Package swap (`wasChanged('package_id')`) | all passengers | `now()` | same |
| Passenger add | new passenger only | `now()` | same |
| Passenger remove | none | — | `syncFinancials` (re-sum) |
| `passenger_type` change | that passenger | `passenger.created_at` | same |
| `extra_charge` change | that passenger | `passenger.created_at` | same (+ existing profit recalc) |
| `service_required` change (incl. visa_only exit) | that passenger | `passenger.created_at` | same |
| Generic booking edit (no swap) | none | — | `syncFinancials` only |
| Fingerprint location change | none | — | `syncFinancials` only |
| Other passenger field edits | none | — | `syncFinancials` only |

### Call-site changes

| Site | New behavior |
|---|---|
| `BookingController::update` :2181 | `if ($booking->wasChanged('package_id'))` → `recalculatePassengerValues($booking, null, now())` **then** `syncBookingFinancials(...)`; else `syncBookingFinancials(...)` only (profit recalc block at :2176-2178 stays) |
| `BookingController::store` :1640 | `recalculatePassengerValues(all, now())` + totals (unchanged semantics) |
| `BookingController::addPassenger` :2467 | `recalculatePassengerValues(new passenger, now())` + `syncFinancials` — **others untouched** |
| `BookingController::removePassenger` :2501 | `syncFinancials` only |
| `BookingController::updateFingerprintLocation` :2268 | `syncFinancials` only (charge from FK + location rule) |
| `PassengerController::update` :636 | If `passenger_type` / `extra_charge` / `service_required` changed → `recalculatePassengerValues(that passenger, $passenger->created_at)`; then `syncFinancials`. Otherwise re-sum only |
| `PassengerController::handleServiceRequiredChangeFromVisaOnly` :1018-1087 | same single-passenger historic recalc |
| `PassengerController::destroy` :693 | `syncFinancials` only |
| `recalculatePassengerValue` endpoint `BookingController.php:2877-2892` | keep, anchor = `passenger.created_at` |
| `PackageObserver::updated` :35-46 | **unchanged** — recalcs *profit* only (snapshot-based), never `package_value` |
| `recalculateFareSnapshots` (`PassengerController.php:960-1016`) | **unchanged** — already historic-anchored at `issued_tickets.created_at` (plan 14) |
| Package-swap snapshot re-derivation `BookingController.php:2058-2170` | **unchanged** — re-points fare FKs, `booking_service_charge`, `visa_submissions`, `issued_tickets` from live values, which at anchor `now()` is correct by definition |

### Commands aligned (so operators cannot bypass)

- `bookings:sync-financials` (`app/Console/Commands/SyncBookingFinancials.php:22`) →
  `refreshBookingTotals` + discount/invoice only; **no `package_value` overwrite**.
- `SyncPassengerTicketFare` / `SyncPassengerTicketFareV2`
  (`app/Console/Commands/SyncPassengerTicketFare.php:41-46`,
  `SyncPassengerTicketFareV2.php:79-84`) → recompute `package_value` via the historic
  calculator at `passenger.created_at`, never live.

---

## Phase 3 — Observer logging hardening (D5)

- **Migrations (4):** make `user_id` nullable (+ `nullOnDelete`) on
  `package_update_logs`, `ticket_fare_update_logs`, `booking_update_logs`,
  `passenger_update_logs`. Precedent: `invoice_update_logs`
  (`2026_08_13_000006_create_invoice_update_logs_table.php:15` uses
  `nullable()->constrained('users')->nullOnDelete()`).
- **Observers:** `TicketFareObserver`, `PackageObserver`, `BookingObserver`,
  `PassengerObserver` — remove the `if (! Auth::user()) return;` guard **before log
  writes**; log with `$user?->id` (null = system/CLI/unauthenticated). Side effects
  (profit recalc etc.) keep their existing guards.
- `SettingsController.php:234` uses `'user_id' => auth()->id() ?? 1` — change to
  `auth()->id()` (null now valid) so package creation logging no longer fabricates user 1.

Without this, history going forward stays incomplete and every replay silently degrades
to the live-value fallback.

---

## Phase 4 — Front-end (`resources/js/booking.js`)

- **Keep** client-side recompute in the package-swap flow (`onPackageChange`
  `:1349-1410`) — matches server semantics (anchor = now).
- **Keep** creation-flow previews (`calculatePackageValue` JS twin — creation anchor = now).
- **Remove/disable** recompute-on-save in edit flows (`savePassenger` `:472-478`,
  `removePassenger` `:2083-2089`, `onTicketChange` `:2052-2060`, and the duplicated blocks
  at `:3281+`, `:3544+`, `:4830+`) — the server response becomes authoritative: extend the
  JSON responses of `PassengerController@update`, `BookingController@update`,
  `addPassenger`, `removePassenger` with refreshed `passenger.package_value`,
  `bookings.total_value` and invoice fields, and render those instead.
- `resources/views/bookings/index.blade.php:631` and `show.blade.php:134` already render
  stored values — no change needed.

---

## Phase 5 — Validation queries (read-only, run before/after rollout)

Deliver as SQL in the PR description:

1. Fares/packages with `updated_at > '2026-09-22'` but **zero** rows in
   `ticket_fare_update_logs` / `package_update_logs` → unlogged edits (measures the
   pre-hardening blind spot).
2. Passengers where `booking_service_charge != packages.service_charge` (non-cancelled,
   `service_required != 'visa_only'`) → current drift magnitude.
3. Bookings created before `2026-09-22` whose package has no log rows → fallback exposure
   count (these will resolve to current prices under the replay).
4. Count of distinct actions in `package_update_logs` — verify only
   `created|updated|deleted|fare_updated` exist (replay must cover all but `deleted`).

---

## Phase 6 — Tests (TDD — write failing first)

1. **Unit** `tests/Unit/PackageValueReplayTest.php`
   — `Package::valueAt`: seed from `created`; seed from first `old_values`; **no-logs ⇒
   live value** (decision rule 4); `updated` walk; **`action='fare_updated'` applied**;
   null `created_at` rows skipped; anchor inclusivity.
2. **Feature** `tests/Feature/PassengerPackageValueImmutabilityTest.php`
   - fare `selling_fare`/`offer_price` update → then booking edit / passenger name edit /
     fingerprint location change → `passengers.package_value`, `bookings.total_value`,
     `invoices.total_amount` **unchanged**;
   - package `service_charge` / `visa_selling_price_id` / `regular_price` update → same
     → unchanged.
3. **Feature** `tests/Feature/PackageSwapRecalculationTest.php`
   — swap recalcs all passengers at `now()` (new package's current prices), applies
   invoice delta; extends the patterns of `BookingPackageChangeVisaSyncTest`.
4. **Feature** `tests/Feature/PassengerTypeChangeHistoricRecalcTest.php`
   — booking created at T, fare price raised after T, adult→child → ticket leg uses the
   **fare as-of T**, not the raised fare; other passengers untouched;
   `bookings.total_value` re-summed from stored values.
5. **Feature** `tests/Feature/PassengerEditValueScopesTest.php`
   — `extra_charge` edit / `service_required` change recompute **only that passenger**
   (anchor `created_at`); passenger add computes only the new one; remove re-sums only;
   fingerprint charge resolves from the booking's FK.
6. **Update existing tests that codify old behavior:**
   - `tests/Feature/PassengerExtraChargeTotalsTest.php:223-243, 298-319` (asserts
     passenger edit recomputes `package_value`/totals — keep only the
     `extra_charge`-changed cases, drop the "any edit recompute" cases);
   - `tests/Feature/SyncPassengerTicketFareV2Test.php:177-187` (command semantics →
     historic anchor);
   - audit `tests/Feature/TicketFareInUseEditableFieldsTest.php` and
     `TicketFareLockHardeningTest.php` (fare→package cascade stays intended; no passenger
     assertions expected).

---

## Verification (per AGENTS.md commit checklist)

```bash
php artisan test                 # full suite
vendor/bin/pint                  # format
npm run build                    # frontend build
docker compose -f docker-compose.yml config --quiet
docker compose -f docker-compose.prod.yml config --quiet
```

## Implementation order

1. Phase 1 (historic calculator) + test 1 — the foundation.
2. Phase 2 (split `syncFinancials`, call-site matrix, commands) + tests 2-5.
3. Phase 6 test updates (make old-behavior tests fail → fix → green).
4. Phase 3 (observer hardening + 4 migrations).
5. Phase 4 (front-end).
6. Phase 5 (validation queries) → run → paste results into PR.
7. Full checklist → commit (Conventional Commits, one logical change per commit).

## Caveats (accepted)

- **Pre-2026-09-22 lookups fall back to current values.** Accepted: in-use fares and
  packages were structurally locked before the log tables existed
  (`docs/plans/11-fare-snapshot-before-after-and-manual-tests.md:9-21`), so live == historic
  for those records in almost all cases. Phase 5 query 1/3 quantifies the residue.
- **Swap-then-type-change edge:** anchor `passenger.created_at` may predate the swapped-in
  fare/package; replay then falls back to those entities' earliest known (creation) values —
  the same accepted edge documented in `docs/plans/14:101-102`.
- **`PackageObserver` passenger writes (profit columns) stay** — they read frozen snapshots
  (`booking_service_charge`, issued-ticket fares) and never touch `package_value`.
- **Out of scope:** `ProfitCalculationService`, cancellation/refund flows (they already read
  stored `package_value` — and become *more* stable under this plan), `issued_tickets`
  snapshot logic on swap (live-at-swap == correct), reports (read stored values only).

## Files expected to change (implementation scope)

**App:** `BookingService`, `BookingController`, `PassengerController`, `Package`,
`TicketFare` (replay helper extraction), `SettingsController` (line 234),
`SyncBookingFinancials`, `SyncPassengerTicketFare`, `SyncPassengerTicketFareV2`,
`PackageObserver`, `TicketFareObserver`, `BookingObserver`, `PassengerObserver`,
`BackfillVisaSubmissionPrices` (extract `resolvePriceAt` into shared service),
new `App\Support\LogReplay`.

**Migrations (5):** 4× nullable `user_id` on the log tables + (if needed) none for value
columns — `package_value`/`total_value` already exist.

**Frontend:** `resources/js/booking.js`, possibly `resources/views/bookings/edit.blade.php`
/ `index.blade.php` for consuming the enriched JSON responses.

**Tests:** 4 new files + 2-3 updated files (Phase 6).
