# Plan: Historic child/infant fare percentages for snapshot recalculation on passenger_type change

**Status: PLAN ONLY — DO NOT IMPLEMENT until explicitly approved.**

## Problem
`PassengerController::recalculateFareSnapshots()` (`app/Http/Controllers/PassengerController.php:941`) re-derives an **existing** issued ticket's `selling_fare`/`offer_price` snapshot when `passenger_type` changes. It calls the local `adjustFaresForType()` (`PassengerController.php:909`), which reads `$fare->child_fare_percentage` / `$fare->infant_fare_percentage` **live** from the `ticket_fares` row.

Child/infant fare percentages were previously locked on in-use fares, so live == historic and the code was correct. Recently, child/infant % edit access was opened for in-use fares. Now a type-change recalc would bake the *updated* percentage into a snapshot that must be frozen at the ticket's creation-time world.

## Design decisions (confirmed)
1. **Anchor timestamp** = each issued ticket's own `created_at` (per-ticket). The snapshot is re-derived "as if the passenger had always been the new type back then" — so we need the fare's percentages as they were at ticket creation time.
2. **Package-change edge accepted**: a ticket row keeps its original `created_at` after being re-bound to a new fare by a package change. The reconstruction then falls back to the **new fare's earliest known %** (its `created`-log value, or its first-change `old_values`, or its live row value — all equal in practice).
3. **visa_only-exit path** (`PassengerController.php:990-996`, calling `recalculateFareSnapshots` at line 993) also recalculates **existing** tickets → uses historic % too. (When no tickets exist there yet, the code falls through to **new** ticket creation at `now()` → live % is correct there.)
4. **Only the percentages are historicized**. The recalc base stays the fare's **current** `selling_fare`/`offer_price` (per the fare-snapshot plan's "recalc on passenger_type change" semantics).
5. All other `adjustFaresForType` call sites create **new** snapshots bound at `now()` → keep live percentages, **unchanged**:
   - `BookingController@store` (lines ~1476/1490/1509) and `@addPassenger` (~2308/2322/2341).
   - `BookingController@update` package-change (~2035/2046/2070): the passenger is bound to a **new fare as of now**, so the new fare's current % applies.

## Background: what the logs contain
`TicketFareObserver` (`app/Observers/TicketFareObserver.php`) writes three kinds of rows into `ticket_fare_update_logs` (migration `2026_09_22_000003_create_ticket_fare_update_logs_table`, `created_at` only, no `updated_at`):
- **`created`** — `new_values` = the fare's **full row** at creation (includes `child_fare_percentage`, `infant_fare_percentage`, `selling_fare`, …). `old_values` = null.
- **`updated`** — one row per update, only the **dirty** columns: `old_values = {changedKey: priorValue}`, `new_values = {changedKey: newValue}`. A column absent from `new_values` did **not** change in that event.
- **`deleted`** — `old_values` = full row, `new_values` = null.

Critical timing fact: the logs table and the child/infant % edit access went live **together**, so there is no unrecorded pct change hiding before the first logged event — which is what makes the recovery below exact.

## How historic percentages are recovered — `TicketFare::percentageAt($column, $at)`

```php
public function percentageAt(string $column, string $at): float
{
    $at   = Carbon::parse($at);
    $logs = $this->updateLogs()->orderBy('created_at')->get();   // chronological

    // --- STEP 1: SEED — value in effect at the *start* of recorded history ---
    $seed = null;
    foreach ($logs as $log) {
        if ($log->action === 'created' && array_key_exists($column, $log->new_values ?? [])) {
            $seed = (float) $log->new_values[$column];
            break;
        }
        if ($log->action === 'updated'
            && array_key_exists($column, $log->old_values ?? [])) {
            $seed = (float) $log->old_values[$column];   // value *before* first recorded change
            break;
        }
    }

    // --- STEP 2: FORWARD WALK — apply changes up to and including $at ---
    $value = $seed ?? (float) $this->{$column};          // no history → live row value
    foreach ($logs as $log) {
        if (! $log->created_at || $log->created_at->gt($at)) break;   // stop past anchor
        if ($log->action === 'updated'
            && array_key_exists($column, $log->new_values ?? [])) {
            $value = (float) $log->new_values[$column];
        }
    }

    return $value;
}
```

### Why each piece works
- **Seed**: the *first* `updated` event that touches the column carries `old_values[$column]` = the value just before the first recorded change. Since no pct change is possible before the logs era, this seed *is* the value in effect from fare creation until the first change. A `created` log's `new_values[$column]` is even more authoritative when present (preferred, checked first).
- **Forward walk**: iterate events in chronological order, stopping once `created_at > $at` (anchor bound is **inclusive**: `<= $at` applies). Each `updated` log only carries keys that actually changed — if the column is absent from `new_values`, it didn't change; if present, the new value becomes the value in effect at the anchor.
- **Fallback (no log history)**: return the fare's **current row value**. This is exact, not approximate: percentages were locked before the logs migration shipped, so for fares with no pct-edit history, live == original.

### Implementation gotchas
1. **`0` and `"70.00"` are valid values** — the percentage allows `min:0`, and the JSON round-trips decimals as strings. Use `array_key_exists(...)` + `(float)` cast; **never** `isset(...)` (misreads `0`) or `!empty()`.
2. **`deleted` events** are ignored in the walk (after a delete the fare's tickets are normally gone; nothing meaningful can be reconstructed past it anyway).
3. Load each fare's logs **once** per `recalculateFareSnapshots` run to avoid N+1 queries (e.g. fetch into a local cache keyed by `ticket_fare_id`).
4. `updateLogs` relation already exists (`TicketFare.php:91-94`). No migration needed.

### Worked examples

**Example A — legacy fare, pct edited after the ticket was created (the core case)**
```
T0 2026-09-01  fare created (pre-observer, no log). child% = 70
T1 2026-09-23  ticket created → issued_tickets.created_at = T1 (child% still 70)
T2 2026-09-24  child% edited 70 → 50
               log: action=updated, old={child_fare_percentage:70}, new={...:50}, created_at=T2
T3 2026-09-25  passenger_type adult→child → recalc, anchor = ticket.created_at = T1
```
- Seed: first `updated` touching the column carries `old=70` → **70**.
- Walk with cutoff T1: only logs ≤ T1 considered → none → value stays **70**.
- Result: snapshot = current fare `selling_fare` × **70%**. The pre-edit 70 governs.
- (If the anchor were T3 instead, the walk would apply the T2 edit → 50 — which is why anchoring at the ticket's `created_at` is essential.)

**Example B — full history (fare created after the observer)**
```
T0 2026-09-22 10:00  fare created. se log: new={child_fare_percentage:70, infant_fare_percentage:30}
T0 +1h                 infant% edited 30 → 35. log: old={infant...:30}, new={...:35}
T0 +2h                 ticket created (infant% quoted at 35)
T0 +3h                 passenger_type → infant, anchor = T0+2h
```
- `infant_fare_percentage` at T0+2h: seed = 30 (`created` log), walk ≤ T0+2h applies 30→35 → **35**.
- `child_fare_percentage` at T0+2h: no `updated` events → seed 70, stays **70**.

**Example C — visa_only exit with pre-existing tickets** — identical mechanics, anchored per-ticket at each ticket's `created_at`.

**Example D — the accepted package-change edge**
A ticket row (`created_at = T1`) is re-bound to a new fare B at T2. A later type change anchors at T1, which predates B's existence. The walk finds no B events ≤ T1, so the result is **B's earliest known %** (its `created`-log value, its first-change `old_values`, or its live row — all equal where the fare's pct was never edited between its own creation and T2). This is the sanctioned, close-enough behavior.

## Steps (implementation order)

### 1. `app/Models/TicketFare.php` — add `percentageAt()`
- Add `percentageAt(string $column, string $at): float` implementing the seed + forward-walk above. Accept `$column` = `'child_fare_percentage' | 'infant_fare_percentage'`.
- Use `Illuminate\Support\Carbon`; reuse the existing `updateLogs()` relation.
- (Optional helper: `childFarePercentageAt($at)` / `infantFarePercentageAt($at)` wrappers for readability.)

### 2. `app/Http/Controllers/PassengerController.php` — wire overrides
- Extend the local `adjustFaresForType()` (line 909) with optional overrides:
  ```php
  private function adjustFaresForType(float $sellingFare, float $offerPrice, string $passengerType,
                                      ?TicketFare $fare, ?float $childPct = null, ?float $infantPct = null): array
  ```
  Use the override when non-null, else fall back to today's live-read (`?? 70` / `?? 30`).
- In `recalculateFareSnapshots()` (lines 941-988), for each of the three blocks (regular ~945-959, pending_outbound ~961-973, additional ~975-987), resolve the bound fare and compute:
  ```php
  $childPct  = $fare->percentageAt('child_fare_percentage', $ticket->created_at);
  $infantPct = $fare->percentageAt('infant_fare_percentage', $ticket->created_at);
  ```
  and pass them as the new 5th/6th args.
- `handleServiceRequiredChangeFromVisaOnly()` (lines 990-…) needs **no** change when tickets exist — it delegates to `recalculateFareSnapshots()`. The new-ticket-creation branches (double-ticket inbound ~1007, outbound ~1025, single ~1039) are `now()`-anchored → keep live pct.

### 3. BookingController duplicate — explicitly out of scope
`BookingController::adjustFaresForType()` (line 85) stays as-is. All its call sites are `now()`-anchored (store/addPassenger new tickets, package-change rebinding to a new fare).

## Files touched
| File | Change |
|---|---|
| `app/Models/TicketFare.php` | add `percentageAt()` (uses existing `updateLogs()` relation) |
| `app/Http/Controllers/PassengerController.php` | `adjustFaresForType()` override params; `recalculateFareSnapshots()` historic-pct threading (3 blocks) |

No migration. No view changes.

## Edge cases / notes
- **Legacy fares with zero logs** → live row value (exact, since pct was locked pre-logs).
- **Fare created after the observer** → `created` log gives an exact creation seed.
- **Fare edited multiple times** → forward walk applies each edit, so the value reflects chained changes (70→50→65 → value-at-T lands on the correct rung).
- **Anchor inclusivity**: a pct edit sharing the exact timestamp with the ticket creation applies (`<=`).
- **Package-change accepted edge**: when the anchor predates the currently-bound fare, result = new fare's earliest known % (see Example D).
- **`adjustFaresForType` in `BookingController`** untouched by design; `ProfitCalculationService::adjustFares` (child/infant × type at line ~773) reads live fare pct for **prospective** display breakdowns — out of scope for this snapshot fix.
- Soft-deleted `issued_tickets` are excluded from profit and are not recalculated on type change (no handling needed).

## Manual test checklist
1. **Core**: adult passenger ticket created under child%=70; edit fare child% to 50; change passenger adult→child → `issued_tickets.selling_fare` = current fare `selling_fare` × 70%. `ticket_fare_update_logs.offer_price` unchanged.
2. **Reverse toggle**: same ticket, type back to adult → snapshot = current fare `selling_fare` (pct 100). Profit follows.
3. **Legacy**: type-change on a pre-logs fare → same snapshot as before this fix (live == historic).
4. **Multiple edits**: 70→50→65 before the type change → recalc uses 70 (at-ticket-creation), not 50/65/later values.
5. **visa_only exit**: visa_only passenger with existing tickets → type switch recalculates with historic %, not the edited current %.
6. **visa_only exit without tickets** → new ticket created at `now()` with live %, unchanged behavior.
7. **Double-ticket**: inbound + outbound recalculated with each respective fare's historic pct anchored at each ticket's `created_at`.
8. **Additional tickets**: `issue_type=additional` ticket recalculated from its own `ticket_fare` anchored at its own `created_at`; profit reports (P&L) match.
9. **Package-change untouched**: change package after pct edit → new tickets use the new fare's current % (no historic lookup interferes).
10. **Zero-percent edit**: edit child% to exactly 0 → `array_key_exists` path detects `0` correctly, snapshot child leg = 0, no fallback to 70.

---

# Amendment: historic base selling_fare / offer_price + lock hardening

**Status: PLAN ONLY — DO NOT IMPLEMENT until explicitly approved.**

## Amendment Part A — Historic base `selling_fare` / `offer_price` for snapshot recalculation

### Problem
The original plan historicized only the child/infant **percentage**. The recalc **base** still comes from the live fare row: `recalculateFareSnapshots()` passes `(float) $fare->selling_fare` and `$fare->offer_price` into `adjustFaresForType()`. Since in-use `selling_fare`/`offer_price` edits are now open (and logged), a passenger-type change would bake post-edit prices into a frozen snapshot. The base must be resolved from `ticket_fare_update_logs` at the same anchor as the percentage — the ticket's `created_at`.

### Exactness rationale (fallback)
Pre-fare-snapshot-plan, in-use fares allowed **`effective_to` only** (docs/11 §1) — selling/offer were locked from ticket creation until the logs table launched (`2026_09_22_000003`, shipped with those edits opening). So for any fare with a ticket: no unlogged selling/offer change exists between the anchor and logging start → seed/live fallbacks are exact, same argument as the percentages. `ticket_type` needs no historicizing (locked on in-use fares → live == historic since binding; keep the live `=== 'offer'` gate).

### Changes
1. **`app/Models/TicketFare.php`** — generalize the replay:
   - Canonical `valueAt(string $column, \DateTimeInterface|string $at): float` — exact body of today's `percentageAt()` (seed from `created`-log `new_values` / first-change `old_values`, forward-walk `updated` events with `created_at <= $at`, fallback to live row value, `array_key_exists` + `(float)`).
   - `percentageAt()` becomes a thin wrapper: `return $this->valueAt($column, $at);`. Static `percentageAtFromLogs()` unchanged (already column-generic).
2. **`app/Http/Controllers/PassengerController.php`** — historic base in `recalculateFareSnapshots()`:
   - New private helper:
     ```php
     private function historicFareBase(TicketFare $fare, mixed $at): array
     {
         return [
             $fare->valueAt('selling_fare', $at ?? now()),
             $this->ticketTypeValue($fare) === 'offer' ? $fare->valueAt('offer_price', $at ?? now()) : 0.0,
         ];
     }
     ```
   - All three blocks (regular / pending_outbound / additional) replace live `$fare->selling_fare` / `$fare->offer_price` args with `[$s0, $o0] = $this->historicFareBase($fare, $ticket->created_at)`, then pass `$s0, $o0` + the existing historic pct overrides to `adjustFaresForType()` (signature unchanged).
   - `null` `offer_price` → `(float) null = 0.0`, matching current `(float) ($fare->offer_price ?? 0)` behavior.
   - `adjustFaresForType()` itself untouched; `BookingController` paths untouched (now()-anchored by design, incl. the accepted package-change edge: anchor predating the new fare's binding → replay returns the new fare's earliest known selling/offer — same accepted semantics as the pct edge).
   - `handleServiceRequiredChangeFromVisaOnly()` existing-ticket branch inherits automatically (delegates to `recalculateFareSnapshots()`); new-ticket branches stay live.

## Amendment Part B — Lock hardening (chosen: Option 1 + passenger-binding check)

### B1. Delete the dead fare-edit endpoint
`FareAdminController::storeFare()` (lines 123-171) and `updateFare()` (lines 173-212) are referenced by **no view or app code** (verified: zero matches for `fare.admin.fare.store|update` or hardcoded `/fares/admin/fare` in `resources/` and `app/`; the admin page's Edit/Create links go to `ticket-fares.edit` / `ticket-fares.create`). They are reachable only by crafted requests, enforce **no in-use lock**, and skip `cascadeFarePriceToPackages()`.
- **`app/Http/Controllers/FareAdminController.php`**: remove `storeFare()` and `updateFare()`; keep `index()`, agent methods, and `destroyFare()` (used + already `isLocked()`-gated). Remove now-unused imports (`DatabaseErrorHumanizer`, `QueryException`).
- **`routes/web.php`**: remove lines 200-201 (`fare.admin.fare.store`, `fare.admin.fare.update`). Keep line 202 (`fare.admin.fare.destroy`).

### B2. Single source of truth for in-use detection
`TicketFare::isLocked()` today = `packages()->exists() || passengers()->exists()` — misses packages referencing the fare as **inbound/outbound** AND passengers bound via **`ticket_fare_inbound_id` / `ticket_fare_outbound_id`** (reachable via `PassengerController::update` validation lines 592-593). `TicketFareController`'s duplicated `$inUse` (edit lines 173-176, update lines 187-190) covers package inbound/outbound but misses the passenger inbound/outbound case.
- **`app/Models/TicketFare.php`** — extend `isLocked()`:
  ```php
  return $this->packages()->exists()
      || Package::where('ticket_fare_inbound_id', $this->id)->exists()
      || Package::where('ticket_fare_outbound_id', $this->id)->exists()
      || $this->passengers()->exists()
      || Passenger::where('ticket_fare_inbound_id', $this->id)->exists()
      || Passenger::where('ticket_fare_outbound_id', $this->id)->exists();
  ```
  (Verify/add `Package`, `Passenger` imports.)
- **`app/Http/Controllers/TicketFareController.php`**: replace both `$inUse` computations (in `edit()` and `update()`) with `$inUse = $ticketFare->isLocked();` — one behavior, no drift.
- **Noted limitation (UI only, no change)**: the `is_locked` **accessor** (`getIsLockedAttribute`) is count-based (`packages_count` / `passengers_count` from `withCount`) and still misses inbound/outbound bindings — the fare-list Delete-button badge may show unlocked in that rare crafted case, but the backend (`destroyFare` → `isLocked()` method) now rejects. Optional follow-up: extend index `withCount`s + accessor for UI parity.

## Files touched (whole amendment)
| File | Change |
|---|---|
| `app/Models/TicketFare.php` | `valueAt()` + `percentageAt` wrapper; extend `isLocked()` (package + passenger inbound/outbound) |
| `app/Http/Controllers/PassengerController.php` | `historicFareBase()` helper; 3 recalc blocks use historic selling/offer |
| `app/Http/Controllers/FareAdminController.php` | remove `storeFare()` / `updateFare()` + unused imports |
| `routes/web.php` | remove lines 200-201; keep destroy route |
| `app/Http/Controllers/TicketFareController.php` | `$inUse` → `$ticketFare->isLocked()` in `edit()` + `update()` |
| `docs/plans/14-historic-child-infant-pct-for-fare-snapshot-recalc.md` | this amendment (appended) |

No migration. No view changes.

## Verification (amendment)
- `php -l` on all 5 edited PHP files; `php artisan route:clear` if route cache is enabled.
- Extend standalone replay check (pure `percentageAtFromLogs`): `selling_fare` chained edits, selling↔offer isolation, null-offer fallback, pre-anchor seed, post-anchor exclusion.
- Manual: (1) type change after in-use selling edit → snapshot = historic base at ticket creation × historic pct; (2) `PUT /fares/admin/fare/{id}` → 404; (3) fare edit via `ticket-fares.edit` on an in-use fare → restricted rules only; (4) crafted passenger rebind to a bare inbound fare → fare now reports in-use (edit restricted, delete blocked); (5) reverse type toggle restores original snapshot.
