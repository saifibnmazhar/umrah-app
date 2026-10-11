# Plan: Immutable passenger `package_value` + historic price recalculation

**Status: PLAN ONLY — DO NOT IMPLEMENT until explicitly approved.**

**Revision 2** — incorporates loophole resolutions **L1–L8** and the uniform anchor
decision (see Decision log §end). Limitations (B\*) / inconsistencies (I\*) review
still pending; sections marked **⚠ pending** carry recommended defaults subject to
that round. All line references below were re-verified against the working tree
(verification addendum V1–V13 folded in).

## Problem

`passengers.package_value` is presented as a stored, invoice-relevant value, but it is
**re-derived from LIVE prices on nearly every booking/passenger mutation**:

- `BookingService::calculatePackageValue()` (`app/Services/BookingService.php:73-127`)
  reads **live** `ticket_fares.selling_fare/offer_price/child%/infant%`, **live**
  `packages.visaSellingPrice.selling_price`, **live** `packages.service_charge`.
- `BookingService::recalculateBookingTotal()` (`:129-154`) unconditionally overwrites
  **every** passenger's `package_value`, then `bookings.total_value`.
- `BookingService::syncFinancials()` (`:156-191`) pushes the delta into
  `discount_amount` and `invoices.total_amount`.

Invoked from: booking edit (`BookingController.php:2181`), fingerprint location change
(`:2268`), passenger add/remove (`:2467, 2501`), **any** passenger edit
(`PassengerController.php:636, 693`), manual commands (`SyncBookingFinancials.php:22`,
`SyncPassengerTicketFare.php:41-46`, `SyncPassengerTicketFareV2.php:79-83`), and the
unused endpoint (`BookingController.php:2877-2892`, `routes/web.php:185` — `PATCH`).

**Net symptom:** edit a Package or a TicketFare, then touch *anything* on an old
booking → its stored `package_value`, `bookings.total_value` and
`invoices.total_amount` silently change to the new prices. Edit vectors on locked
packages (`PackageController.php:217-249`): `service_charge` and
`visa_selling_price_id` are directly editable (the `use_current_visa` block
`:224-247` also recomputes `regular_price`/`offer_price`; `package_name` is editable
but harmless); `selling_fare` is editable on in-use fares and cascades into
`packages.regular_price` via `TicketFareController::cascadeFarePriceToPackages()`
(`:434-506`).

`PackageObserver::updated` (`:35-46`) also writes passenger rows (profit columns) on
`service_charge` changes — snapshot-based, value-neutral for `package_value`; stays
as-is.

## Desired behavior (confirmed)

1. Package updates and ticket fare updates have **no impact** on stored
   `passengers.package_value` / `bookings.total_value` / `invoices.total_amount`.
   *(Scoped: profit-column recalculation via `PackageObserver` continues unchanged.)*
2. Recalculation happens **only** on: booking store, package swap, passenger add,
   `passenger_type` / `extra_charge` / `service_required` change, and the repair
   commands. `ticket_fare_id`/`inbound`/`outbound` never change outside a package
   swap (enforced — see D2).
3. Recalculation uses **historic** prices/fares (log replay), never live rows.
4. If a package/fare column was never updated after creation, the current price is
   taken (natural fallback of log replay: no history ⇒ live value).

## Anchor rule (uniform — replaces the old per-trigger anchor matrix)

```
anchor(passenger) = max( passenger.created_at,
                         lastSwapAt )      // last package_id change of the booking

lastSwapAt = max( booking_update_logs.created_at
                  where action = 'updated'
                    and old_values contains 'package_id' )   // PHP-side key filter
                ?? null                                       // never swapped / pre-2026-07-14
```

- **Same rule for every trigger and every command.** At swap/add moments the call
  site passes `now()`, which equals the derived value at that instant (the swap's
  `booking_update_logs` row is written by `BookingObserver` at `:2056`, before
  `syncFinancials` runs at `:2181`).
- **Derived from immutable facts** (creation time + append-only log rows) ⇒ command
  re-runs are idempotent by construction. No anchor column is stored.
  **D2 amended:** type / extra_charge / service_required changes on a **swapped**
  booking replay at the **swap moment** (what the admin saw; D1 persists through
  later re-valuations), not at `created_at`. The old swap-then-type-change edge
  (value reverting toward creation-era prices) is eliminated.
- Filter `action = 'updated'` is mandatory: a booking's `created` log row also
  carries `package_id` in `new_values` and must not masquerade as a swap.
- **Fallback:** swaps before 2026-07-14 (`booking_update_logs` inception) are
  unlogged → `lastSwapAt = null` → anchor = `created_at` (accepted; mostly exact
  because in-use prices were structurally frozen 2026-05-27 → 2026-09-22; `--dry-run`
  quantifies residue).

## Design decisions (confirmed)

| # | Decision | Detail |
|---|---|---|
| D1 | **Uniform anchor rule** | `max(created_at, lastSwapAt)` as above; no `package_value_anchor_at` column |
| D2 | **Fare-FK invariant enforced (L1)** | Remove `ticket_fare_id`/`ticket_fare_inbound_id`/`ticket_fare_outbound_id` from `PassengerController::update` validation rules (:596-598) — unvalidated keys are ignored by `$passenger->update($validated)`. FKs then change only via: passenger creation (`BookingController` `store` :1513-1523 and `addPassenger` :2363-2373), package swap (:2071-2093), and the V1/V2 repair commands |
| D3 | **Fingerprint charge = district-live (L5)** | `refreshBookingTotals` keeps `getFingerprintCharge($booking->district_id, $location)` (`BookingService.php:250-259`). The frozen-`fingerprint_charge_id`-FK read and its null-fallback are **not** adopted (column is NOT NULL since creation; fallback dead code). District edits reprice on next sync — intended |
| D4 | **Cancelled rows frozen (L3)** | Repair command skips bookings with an active `cancelled_bookings` row (mirror `PackageObserver.php:37-46` filter via the `cancelledBooking` hasOne `latestOfMany`, `Booking.php:119-122`) and skips `is_cancelled` passengers during **recomputation**; `recalculatePassengerValues` also skips `is_cancelled` passengers on every trigger. Re-sum set unchanged |
| D5 | **Repair via command (L2/L3)** | `bookings:sync-financials` becomes the historic repair tool (recompute + totals + invoice delta); `--dry-run` first |
| D6 | **V1/V2 kept, both halves** | FK re-pointing retained as self-healing toward D2; `package_value` recomputed at the derived anchor |
| D7 | **Endpoint deleted (L7)** | `routes/web.php:185` (PATCH) + `BookingController::recalculatePassengerValue` (:2877-2892) removed |
| D8 | **History gaps hardened (L2/L3 dependency)** | Observers + cascade log-writer always log; nullable `user_id` |
| D9 | **Recalc logging (L8)** | `action='recalculated'`, only for authenticated triggers; no logs from commands |

---

## Phase 1 — Historic calculator

### 1a. `Package::valueAt($column, $at)` (new)

Mirror `TicketFare::valueAt()` (`app/Models/TicketFare.php:126-145`) over
`package_update_logs` (migration `2026_09_22_000002`; relation already exists at
`Package.php:59-62`).

- **Must honor `action='fare_updated'`** — written manually after the fare→package
  cascade (`TicketFareController.php:496-503`, after `updateQuietly()` at `:494`
  suppresses observers). The existing replay engine only understands
  `created`/`updated`.
- Seed: first `created` event → `new_values[$column]`; else first event's
  `old_values[$column]` (**any action type**, so a leading `fare_updated` row seeds
  correctly); **fallback = live column value** (rule 4).
- Skip rows with null `created_at` (nullable on package/ticket_fare logs;
  `booking_update_logs.created_at` is NOT nullable, `2026_07_14_000001:18`).
- Extract a shared pure helper `App\Support\LogReplay::valueFromEvents()` used by
  both `TicketFare` and `Package`. Keep `TicketFare::percentageAtFromLogs()`
  (:162-191) signature/behavior backward-compatible
  (`tests/Unit/TicketFareReplayTest.php` exists).
  - **⚠ pending I3:** recommended shape — the internal helper returns
    `string|int|float` (needed for `ticket_type` and FK columns);
    `TicketFare::valueAt()`/`percentageAtFromLogs()` stay `float` wrappers for
    existing callers (`PassengerController.php:955-956`).

### 1b. Anchor derivation helper (new)

`Passenger::historicAnchor(): CarbonInterface` (or a `BookingService` method).
Note: `Passenger` already has an `updateLogs` relation (:433-436); `Booking` has
**none** — query `BookingUpdateLog` directly:

```php
$lastSwapAt = BookingUpdateLog::where('booking_id', $this->booking_id)
    ->where('action', 'updated')
    ->get()
    ->filter(fn ($l) => is_array($l->old_values)
        && array_key_exists('package_id', $l->old_values))
    ->max(fn ($l) => $l->created_at);          // filter in PHP for JSON portability

return ($lastSwapAt && $lastSwapAt->gt($this->created_at))
    ? $lastSwapAt
    : $this->created_at;
```

Memoized per booking during batch operations (Phase 2).

### 1c. `BookingService::calculatePackageValue(Passenger $p, CarbonInterface $at)`

Replace every live read with a historic lookup anchored at `$at` (the uniform
anchor):

| Component | Was (live) | Becomes (historic) |
|---|---|---|
| Ticket (single) | `$ticketFare->selling_fare/offer_price`, live child/infant % | `valueAt('selling_fare'\|'offer_price'\|'ticket_type'\|child%/infant%, $at)` composed exactly as today (`offer_price ?? selling_fare` when type == offer at `$at`) |
| Ticket (double) | `$package->ticketFareInbound/Outbound` live | replay against the **passenger's** `ticket_fare_inbound_id`/`ticket_fare_outbound_id` fares; preserve current semantics exactly: combined base, percentages from the **inbound** fare with `?? 70` / `?? 30` defaults (`BookingService.php:101-102`) |
| Visa | `$package->visaSellingPrice->selling_price` (:118) | `Package::valueAt('visa_selling_price_id', $at)` → immutable `visa_selling_prices` row → `selling_price`. **⚠ pending B4:** recommended — unify on this forward replay (column lives on the same log table) instead of porting the reverse-walk `BackfillVisaSubmissionPrices::resolvePriceAt()` (`app/Console/Commands/BackfillVisaSubmissionPrices.php:147-177`); the reverse walk returns `null` when a post-`$at` row has no `old_values` entry (a `created` row qualifies), a case forward replay handles cleanly |
| Service charge | `$package->service_charge` (:119/:123) | `Package::valueAt('service_charge', $at)` |
| Extra charge | `$passenger->extra_charge` | unchanged (current — direct passenger field) |

Notes:

- `$package->is_double_ticket` stays a package-level read — safe: in-use packages
  are locked (`Package::isLocked()`, `Package.php:73-76`); the flag cannot change
  while the booking exists.
- `ticket_fares.effective_from/to` are not price-validity ranges (only used by
  `ExpireTicketFares` to flip `is_active`).
- Creation/add/swap call sites pass `$at = now()` ⇒ numerically unchanged at those
  moments ("behavior at booking creation is numerically unchanged").

---

## Phase 2 — Service split, triggers, commands

### 2a. New `BookingService` shape (replaces `recalculateBookingTotal` + monolithic `syncFinancials`)

None of the following methods exist yet (verified — no name collisions;
`calculateDiscount` exists and is reused as-is):

1. **`recalculatePassengerValues(Booking $b, CarbonInterface $at, ?Passenger $only = null)`**
   - Historic `calculatePackageValue` for all passengers of the booking, or only
     `$only`.
   - Skips `is_cancelled` passengers (D4).
   - Writes via `updateQuietly(['package_value' => …])` (suppresses
     `PassengerObserver`; its only side effect — the `is_cancelled`→profit branch
     at `PassengerObserver.php:36-38` — cannot trigger on a `package_value`-only
     save). Precedent for `updateQuietly`: `TicketFareController.php:494`,
     `ProfitCalculationService.php:57`.
   - **Recalc logging (D9):** when `Auth::user()` exists, write
     `PassengerUpdateLog` with `action='recalculated'`, `user_id`, and
     `old_values`/`new_values` = `['package_value' => …]`. No log when
     unauthenticated (commands) — deliberate.
   - Ends with `$booking->unsetRelation('passengers')`.
2. **`refreshBookingTotals(Booking $b): float`**
   - `$passengerTotal = (float) Passenger::where('booking_id', $b->id)->sum('package_value')`
     — **aggregate query, not the relation collection** (immune to stale relation
     caches).
   - Fingerprint charge: `getFingerprintCharge($b->district_id, normalized_location)`
     (D3 — same helper as today; signature `(districtId, ?string $location = null)`;
     office/null-location ⇒ 0).
   - `total_value = passengerTotal + fingerprintCharge`; save via `updateQuietly`
     (booking financial fields audited through `invoice_update_logs` +
     `recalculated` rows; avoids a `booking_update_logs` row per sync). No
     `package_value` writes.
3. **`applyDiscount(Booking $b)`** — existing :163-177 logic (normal save kept:
   dirty `discount_amount`/`discount_value`/`is_cancelled` trigger
   `BookingObserver` profit recalc per :33-35).
4. **`applyInvoiceDelta(Booking $b, float $oldTotal, float $oldDiscount, ?string $reason)`**
   — existing :179-190 delta logic via `InvoiceService::updateTotals`
   (`app/Services/InvoiceService.php:92`).
5. **`applyFinancials(Booking $b, ?CarbonInterface $recomputeAt = null, ?string $reason = null, ?Passenger $only = null)`**
   — the single orchestrator; every call site uses it:

```php
DB::transaction(function () use ($b, $recomputeAt, $reason, $only) {
    $oldTotal    = (float) $b->total_value;      // captured BEFORE any write
    $oldDiscount = (float) $b->discount_amount;

    if ($recomputeAt !== null) {
        $this->recalculatePassengerValues($b, $recomputeAt, $only);
    }

    $this->refreshBookingTotals($b);
    $this->applyDiscount($b);
    $this->applyInvoiceDelta($b, $oldTotal, $oldDiscount, $reason);
});
```

Atomic (single transaction per call), ordering fixed inside the service, pairing
cannot be forgotten, and stale-relation split-brain is impossible.

**Log memoization (L4):** `applyFinancials` eager-loads `updateLogs` once for the
booking's package and each involved fare (`relationLoaded` already honored by
`TicketFare::valueAt`; same pattern for `Package::valueAt`) and passes parsed event
arrays into the calculator — an all-passenger recompute drops from ~40-70 replay
queries to ~3-4.

### 2b. Trigger → action matrix (anchor collapsed to one rule)

| Event | Recompute scope | `$at` passed | Then |
|---|---|---|---|
| Booking store | all passengers | `now()` | `applyFinancials` not used — see 2c store note |
| Package swap (`wasChanged('package_id')`) | all passengers | `now()` | `applyFinancials($b, $now, 'booking_updated')` |
| Passenger add | new passenger only | `now()` | `applyFinancials($b, $now, 'passenger_added')` |
| Passenger remove | none | — | `applyFinancials($b, null, 'passenger_removed')` |
| `passenger_type` change | that passenger | `historicAnchor()` | `applyFinancials($b, $anchor, 'passenger_updated')` |
| `extra_charge` change | that passenger | `historicAnchor()` | same (+ existing profit recalc) |
| `service_required` change (incl. visa_only exit) | that passenger | `historicAnchor()` | same |
| Generic booking edit (no swap) | none | — | `applyFinancials($b, null, 'booking_updated')` |
| Fingerprint location change | none | — | `applyFinancials($b, null, 'fingerprint_location_updated')` |
| Other passenger field edits | none | — | `applyFinancials($b, null, 'passenger_updated')` |

### 2c. Call-site changes

| Site | New behavior |
|---|---|
| `BookingController::update` :2181 | if `wasChanged('package_id')` → `applyFinancials($b, now(), 'booking_updated')`; else `applyFinancials($b, null, 'booking_updated')` (profit block :2176-2178 stays) |
| `BookingController::store` :1640 | replace `recalculateBookingTotal` with `recalculatePassengerValues($b, now())` **inside the existing manual transaction** (`DB::beginTransaction()` :1418 → `DB::commit()` :1692); keep the bespoke `createInvoiceForBooking` (:1642) + discount block (:1644-1656, `audit_reason='booking_created'`) — `applyFinancials` is **not** used here (no pre-existing invoice to delta) |
| `addPassenger` :2467 | `applyFinancials($b, now(), 'passenger_added')` — **others untouched**; JSON at :2471-2477 already returns `display_total` = new passenger's `package_value` (:2475) — post-`applyFinancials` it is the stored value ✓ |
| `removePassenger` :2501 | `applyFinancials($b, null, 'passenger_removed')`; JSON at :2503-2507 currently returns only `success`/`message`/`invoice` — Phase 4 adds `total_value` + refreshed per-passenger values |
| `updateFingerprintLocation` :2268 | `applyFinancials($b, null, 'fingerprint_location_updated')` |
| `PassengerController::update` :636 | **D2:** drop :596-598 fare-FK rules. If `passenger_type`/`extra_charge`/`service_required` changed → `applyFinancials($b, $passenger->historicAnchor(), 'passenger_updated')`; else `applyFinancials($b, null, 'passenger_updated')`. Existing `recalculateFareSnapshots` (:612) / `handleServiceRequiredChangeFromVisaOnly` (:619) ordering unchanged (run before financials) |
| `handleServiceRequiredChangeFromVisaOnly` :1018-1086 | covered by the `service_required` branch above (method itself writes only `issued_tickets`) |
| `PassengerController::destroy` :693 | `applyFinancials($b, null, 'passenger_removed')` |
| `recalculatePassengerValue` endpoint :2877-2892 + `routes/web.php:185` (PATCH) | **DELETE (D7).** Optional dead-route regression test following the `TicketFareLockHardeningTest:140-152` pattern |
| `PackageObserver::updated` :35-46 | **unchanged** — profit only |
| `recalculateFareSnapshots` :960-1016 | **unchanged** — historic at `issued_tickets.created_at` (plan 14) |
| Package-swap snapshot block :2058-2170 | **unchanged** — re-points FKs/snapshots from live values, correct at anchor `now()` |

### 2d. Commands (D5/D6)

- **`bookings:sync-financials [--dry-run]`** (`SyncBookingFinancials.php`, 31
  lines, currently `chunk(100)` with no cancellation exclusion and no options):
  - Iterate `Booking::has('passengers')->whereDoesntHave('cancelledBooking', …CANCELLED…)`
    (mirror `PackageObserver.php:39`) in chunks; `DB::transaction` per booking.
  - Per non-cancelled passenger: `$at = $p->historicAnchor()`; recompute via the
    historic calculator; write `package_value` (+ `recalculated` log only if
    authenticated — commands normally aren't).
  - Then `refreshBookingTotals` + `applyDiscount` + `applyInvoiceDelta` with
    `reason = 'historic_backfill'` (every backfill-caused invoice movement greppable
    in `invoice_update_logs`).
  - `--dry-run`: print per-booking table — old→new `package_value` per passenger,
    old→new `total_value`, old→new `invoice.total_amount`, plus a grand summary.
    **Run and review before the real run at deploy** (replaces Phase 5's old
    query 2, which measured the wrong quantity).
  - Idempotent: second run is a no-op (anchor derived from immutable facts).
- **`SyncPassengerTicketFare` / `SyncPassengerTicketFareV2` (D6):** keep both
  halves — FK re-pointing unchanged (self-healing toward D2);
  `calculatePackageValue` calls (:41-46 / :79-83) become
  `recalculatePassengerValues` scoped to that passenger at
  `$p->historicAnchor()`. Document V1's null-package-fare skip (:32-34; double-
  ticket packages are skipped implicitly because their fare ids live on the
  inbound/outbound columns, leaving `ticket_fare_id` null) and V2's `if ($changed)`
  gate (:73) as known, intentional scope limits.

---

## Phase 3 — Observer logging hardening (D8) — load-bearing for replay

Without complete logs, every replay silently degrades to the live-value fallback.

- **Migrations (4):** make `user_id` nullable + `nullOnDelete` on
  `package_update_logs`, `ticket_fare_update_logs`, `booking_update_logs`,
  `passenger_update_logs`. Precedent: `invoice_update_logs`
  (`2026_08_13_000006:15`). (`invoice_update_logs` already nullable — not touched.)
  *(`nullOnDelete` also allows user-account deletion without FK-restrict errors.)*
- **Observers:** `TicketFareObserver`, `PackageObserver`, `BookingObserver`,
  `PassengerObserver` — remove the `$user = Auth::user(); if (! $user) return;`
  guard **before log writes only**; log with `$user?->id` (null = system/CLI).
  Side-effect branches keep their existing behavior.
- **Cascade manual writer:** `TicketFareController.php:496` — remove the
  `if (auth()->user())` guard around the `fare_updated` `PackageUpdateLog::create`
  (:497-503; sole production writer of `fare_updated`; replay depends on it).
- **Programmatic financial writes stay quiet:** `package_value` via
  `updateQuietly` (2a.1); `bookings.total_value` via `updateQuietly` (2a.2).
  Entity edits continue to log normally.
- **⚠ pending I1:** `SettingsController.php:233` (`$validated['user_id'] =
  auth()->id() ?? 1`) sets a **non-fillable, non-existent** `packages.user_id` —
  the value is silently discarded and never affected logging. No-op either way;
  drop the planned change or delete the line during the I round.

---

## Phase 4 — Frontend (rewritten per L6)

**Rule:** client-side live-price math is allowed **only where the anchor is
`now()`** — i.e. the **creation flow**. Edit/show flows display **stored** values
only.

**Verified component map (`resources/js/booking.js`, 5135 lines):**
legacy `bookingApp` (:4-754, never mounted — 0 hits in views; dead) /
`createBookingApp` (:755-2617) / `editBookingApp` (:2619-~3995) /
`showBookingApp` (:3997-5135). `calculatePackageValue` has 3 definitions:
create :1272-1332, edit :3219+, show :4835-4893.

**Keep (creation flow, `createBookingApp`):**

- `calculatePackageValue` (:1272-1332, reads JS state `allPackages`/`allTickets`
  seeded from `window.__bookingServerData`), `onPackageChange` (:1349-1410),
  `onTicketChange` (:2052-2060), `removePassenger` (:2083-2090) pre-save previews,
  `passengerPackageValues` map (:888), and `openPaymentModal` (:2180-2207) payment
  math — all anchored at `now()` = live-by-design.
- Edit-page `onPackageChange` (:3296-3323) is also a **pre-save swap preview**
  (anchor will be `now()` at save) — **keep**.

**Replace in `editBookingApp` (post-save / stored displays):**

- `passengerPackageValues` map (:2716) + totals getters (:2722-2760): the getters
  read the **map**, and the map is **never seeded from server data on edit init**
  (empty until a local recalc runs; `discountedTotal` also reads
  `bookingData.discount_value` at :2731, not `discount_amount`). Fix: **seed the
  map from server-rendered `passenger.package_value` in `init()`; update only from
  server responses**; read `discount_amount` for totals.
- `openPaymentModal` (:3566-3589): currently reads `#bookingPackage`
  `dataset.packageValue` — that selector and `data-package-value` **exist in no
  Blade view** (0 repo hits), so `packageValue` is always `0` and the shown Total
  ≈ fingerprint charge only (latent bug). Rewrite: compute Total/Due from the
  seeded map / stored `grandTotalValue` and `discount_amount`.
- Post-save local recompute calls that overwrite stored truth:
  `recalculateAllPassengerValues()` (:3281) after `removePassenger` (:3481-3482;
  function spans :3452-3499, DELETE fetch :3469) and inside
  `recalculateCurrentPassenger` (:3288) — restrict to the pre-save preview paths
  listed above; post-delete totals must come from the enriched server response.
- `getPassengerFare()` display copy (:3844-3906, used by
  **`bookings/edit.blade.php:198`** and `create.blade.php:207`): on the edit page
  display stored `package_value`. Pre-save modal previews of an unsaved edit may
  approximate; the reload after save renders the authoritative value.
  (`passengers/edit.blade.php` uses its own inline `editPassengerApp()` — **out of
  scope**.)
- `editBookingApp.savePassenger` (:3524-3549) is **unbound in any view** (the
  shared passenger-modal partial is only included by `create.blade.php:317`) —
  dead code, **no action**.

**Show page (`showBookingApp`): no changes required.**

- `openPaymentModal` (:4895-4920, bound at `show.blade.php:250`) already scrapes
  **stored** server-rendered DOM (`#financialTotalValue` / `#financialTotalPaid` /
  `#financialDue`).
- `recalculateCurrentPassenger` (:4830-4834), the `calculatePackageValue` clone
  (:4835-4893) and `passengerPackageValues` (:4049) are **dead — nothing on the
  show page triggers them** (no fare inputs, no bindings). Optional dead-code
  cleanup only, out of scope.

**JSON responses (only true no-reload paths need enrichment):**

- `removePassenger` (:2503-2507): add `total_value` and refreshed per-passenger
  values.
- `addPassenger` (:2471-2477): already returns `display_total` — the
  post-`applyFinancials` stored value.
- Booking edit (native form POST → redirect) and passenger edit (JSON →
  `window.location.href`) full-reload — server-rendered stored values are already
  authoritative; no response changes needed for those two.

**Notes:**

- `bookings/index.blade.php:631` renders Alpine `p.package_value` from the
  server-provided passenger list (`bookingIndexApp`) — stored, no change.
- `bookings/show.blade.php:134` — `$passenger->ticketFare?->fare` is always null
  (no `fare` column on `ticket_fares`, no accessor on `TicketFare`) ⇒ dead
  expression, renders `0`; **⚠ pending I-round** optional one-line cleanup.
- Legacy `bookingApp` (:4-754) is dead code; excluded from scope.

---

## Phase 5 — Validation (read-only; before/after rollout)

Primary instrument: **`bookings:sync-financials --dry-run`** (Phase 2d) —
per-passenger old→new values, totals and invoice deltas with a grand summary.
Review before the real run; capture output for the PR.

Supporting SQL (PR description):

1. Fares/packages with `updated_at > '2026-09-22'` but **zero** rows in
   `ticket_fare_update_logs` / `package_update_logs` → unlogged-edit record count.
2. Bookings created **before 2026-07-14** (anchor-derivation fallback exposure
   count).
3. Count of distinct `action` values in `package_update_logs` — expect only
   `created|updated|deleted|fare_updated` (replay covers all but `deleted`).

*(Old query 2 — `booking_service_charge != packages.service_charge` — removed:
under the new regime that divergence is by design (frozen at swap), not drift.)*

---

## Phase 6 — Tests (TDD — write failing first)

1. **Unit** `tests/Unit/PackageValueReplayTest.php` — `Package::valueAt`: seed
   from `created`; seed from first `old_values` **of any action type** (incl.
   leading `fare_updated`); no-logs ⇒ live value; `updated` walk; `fare_updated`
   applied; null `created_at` rows skipped; anchor inclusivity. **⚠ pending I3:**
   `ticket_type` string-replay case.
2. **Unit** `tests/Unit/HistoricAnchorTest.php` — anchor rule: never swapped →
   `created_at`; swap after creation → swap log time; passenger added after swap →
   `created_at` (= add time); booking `created`-action logs ignored; pre-log-era →
   `created_at`.
3. **Feature** `tests/Feature/PassengerPackageValueImmutabilityTest.php` — fare
   `selling_fare`/`offer_price` update, then booking edit / passenger name edit /
   fingerprint location change → `package_value`, `total_value`,
   `invoices.total_amount` **unchanged**; same for package `service_charge` /
   `visa_selling_price_id` / `regular_price` updates.
4. **Feature** `tests/Feature/PackageSwapRecalculationTest.php` — swap recalcs all
   at `now()`, applies invoice delta (extends `BookingPackageChangeVisaSyncTest`
   patterns).
5. **Feature** `tests/Feature/PassengerTypeChangeHistoricRecalcTest.php` —
   (a) never-swapped: booking created at T, fare raised after T, adult→child →
   fare **as-of T**; other passengers untouched; totals re-summed. **(b, new —
   locks the D2 amendment):** swapped at T1 (fare already changed before T1), type
   change at T3 → ticket leg uses fare **@T1** — not the creation-era value, not
   live.
6. **Feature** `tests/Feature/PassengerEditValueScopesTest.php` — extra_charge /
   service_required change recomputes **only that passenger** at
   `historicAnchor()`; passenger add computes only the new one; remove re-sums
   only; `is_cancelled` passenger skipped; fingerprint charge resolves from current
   `district_id` (D3).
7. **Feature** `tests/Feature/SyncBookingFinancialsHistoricTest.php` — seeded
   drift → `--dry-run` reports without writing; real run fixes drift with invoice
   `reason='historic_backfill'`; **swapped passenger keeps swap-time pricing**
   (regression for the worked 4,000→4,500 example); second run is a no-op;
   cancelled bookings/passengers untouched.
8. **Feature** `tests/Feature/SyncPassengerTicketFareHistoricTest.php` — V1/V2
   recompute at `historicAnchor()`; FK re-pointing still works; V1 null-fare skip
   and V2 `if ($changed)` gate documented behavior.
9. **Feature** `tests/Feature/RecalculationAuditLogTest.php` — authenticated
   trigger → `passenger_update_logs` row with `action='recalculated'`; command run
   → **no** row; `package_value` writes do not produce observer `updated` rows.
10. **Feature** endpoint-deletion test (optional; 404/405 pattern from
    `TicketFareLockHardeningTest:140-152`).
11. **Update existing:**
    - `SyncPassengerTicketFareV2Test.php:177-187` — new signature/semantics;
      replace the self-referential expectation (:185 computes "expected" via the
      same service function; partial mitigation at :187) with a hardcoded /
      fixture-derived value.
    - `PassengerExtraChargeTotalsTest.php` — **verified: no changes needed.** All
      package_value/total assertions are tied to `extra_charge` changes,
      `passenger_type` changes, or creation/add-passenger scenarios (the cited
      ranges :223-243 and :298-319 are keep-cases); no generic-edit
      recomputation assertion exists. *(I5 finding for the I round.)*
    - `TicketFareInUseEditableFieldsTest` / `TicketFareLockHardeningTest` —
      verified: zero `package_value` assertions; no changes expected.

---

## Verification (per AGENTS.md commit checklist)

```bash
php artisan test                 # full suite
vendor/bin/pint                  # format
npm run build                    # frontend build
docker compose -f docker-compose.yml config --quiet
docker compose -f docker-compose.prod.yml config --quiet
```

## Implementation order (suite green at every commit)

1. **Phase 1** (calculator + anchor helper) + tests 1-2 — foundation.
2. **Phase 3** (observer/cascade hardening + 4 migrations) + test 9 — replay
   correctness depends on complete logs going forward; land before the service
   uses it.
3. **Phase 2** (service split, call sites, commands, endpoint deletion) + tests
   3-8 and 10-11 — **update old-behavior tests in the same commits** as the
   behavior changes they cover (never leave the suite red between commits).
4. **Phase 4** (frontend).
5. **Phase 5**: `--dry-run` against production data → review report → attach to
   PR; real run is the post-deploy step.
6. Full checklist → commits (Conventional Commits, one logical change each).

## Caveats (accepted)

- **Pre-log fallbacks.** Anchor derivation covers swaps from 2026-07-14; earlier
  swaps fall back to `created_at`. Replay falls back to live values for any column
  with no log rows. Both are mostly exact for the locked-record era (in-use price
  edits were structurally impossible 2026-05-27 → 2026-09-22, the same commit
  range that shipped the log tables) with two known residue windows:
  2026-05-08→05-27 (no locks yet) and writes suppressed by the old auth guards
  until Phase 3 ships. `--dry-run` quantifies.
- **⚠ pending B3:** live-fallback is per-*column*; one historically unlogged edit
  makes that column permanently resolve to live for that record.
- **Heterogeneous eras:** after a passenger add, the new passenger carries `now()`
  prices while existing ones keep their anchor-era prices — correct per design;
  note in the PR description (⚠ pending B6).
- **`PackageObserver` profit writes** on `service_charge` continue
  (snapshot-based; never touch `package_value`).
- **Out of scope:** `ProfitCalculationService`, cancellation/refund flows (read
  stored `package_value`; now *more* stable), `issued_tickets` snapshot logic on
  swap (live-at-swap == correct), reports (read stored values).

## Files expected to change

**App:** `BookingService` (split per 2a), `BookingController`, `PassengerController`
(+ D2 rule removal), `Package` (`valueAt`), `TicketFare` (helper extraction),
`Support/LogReplay` (new), `SyncBookingFinancials`, `SyncPassengerTicketFare`,
`SyncPassengerTicketFareV2`, observers ×4, `TicketFareController` (cascade log
guard), `Passenger`/`Booking` (`historicAnchor` + relations), optional
`SettingsController` (⚠ I1).

**Deleted:** `routes/web.php:185` + `BookingController::recalculatePassengerValue`.

**Migrations (4):** nullable `user_id` (+ `nullOnDelete`) on
`package_update_logs`, `ticket_fare_update_logs`, `booking_update_logs`,
`passenger_update_logs`. **No anchor column.**

**Frontend:** `resources/js/booking.js` (per Phase 4 map),
`resources/views/bookings/edit.blade.php` only if the stored-display swaps
require it.

**Tests:** 9 new files + 2 updated (Phase 6).

## Decision log (Revision 2)

| Ref | Decision |
|---|---|
| L1 | Fare-FK invariant **enforced** by removing the three validation rules (D2); trigger matrix unchanged |
| L2 | `bookings:sync-financials` = historic repair command; V1/V2 kept both halves, historic-anchored |
| L3 | Backfill = enhanced command; `--dry-run`; cancelled bookings/passengers excluded from recomputation (D4); per-booking transactions; `reason='historic_backfill'` |
| L4 | Single transactional `applyFinancials()`; aggregate-sum `refreshBookingTotals`; `unsetRelation`; **per-booking log memoization** |
| L5 | Fingerprint charge stays district-live; frozen-FK design deleted (D3) |
| L6 | Live client math only in creation flow / pre-save swap preview; edit/show displays read stored; JSON work limited to no-reload paths |
| L7 | Unused recalculate endpoint **deleted** (D7) |
| L8 | `updateQuietly` + `action='recalculated'` only when authenticated; no command logs (D9) |
| Anchor | Uniform `max(created_at, lastSwapAt)`; derived from `booking_update_logs`; **no column**; D2 amended (swap-time pricing persists through later re-valuations; old swap-then-type edge eliminated) |

## Open for the B/I round

| Ref | Topic | Recommended default in this revision |
|---|---|---|
| I1 | `SettingsController:233` dead-code line | Leave as no-op or delete during I round (does not affect logging) |
| I3 | Replay helper return typing | Mixed-return internal helper; `float` wrappers preserved on `TicketFare` public API |
| B4 | Visa FK resolution | Unify on forward replay via `Package::valueAt('visa_selling_price_id')` |
| B3 / B6 | Caveat wording | Per-column live-fallback; heterogeneous eras note in PR |
| (resolved by verification) | I5 test-citation audit | `PassengerExtraChargeTotalsTest` needs **no** changes (verified); I10 line refs confirmed correct |
