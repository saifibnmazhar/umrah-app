# Plan: Add "Void" button to the Ticket Info modal (Passenger tab → Ticket Panel)

> **Status:** Plan v2 — no implementation yet.
> **Scope:** Passenger index → Ticket Panel column → eye button → Ticket Info modal.
> Today that modal has **Edit**, **Re-Issue**, **Refund**. We add a 4th action: **Void**.
>
> **v2 changes:** additional tickets are now voidable (soft delete + invoice reversal +
> request reopen), companion `pending_outbound` handling reversed (hard delete when
> `pending`), dashboards/filters must erase the issuance event, VISA_ONLY guard added,
> modal closes when no viewable ticket remains, method renamed `voidTicket`, profit
> recalc made conditional, and the test suite expanded to 21 tests.

---

## 1. Requirement

- When a ticket's status is **`issued`**, show a **Void** button in the Ticket Info modal
  (applies to `regular`, `null`, `pending_outbound` **and** `additional` tickets).
- Clicking Void reverts the ticket:
  - **regular / null / pending_outbound** → back to the state captured in the **`issued`**
    row of `issued_ticket_logs` (the pre-issuance snapshot).
  - **additional** → soft-deleted, its fare subtracted back out of the invoice, and its
    source `TicketRequest` reopened to `pending`.
- Void must **erase the issuance event** from dashboards, branch-wise counts and the
  "Ticket Issued between dates" passenger filter.
- Voided ticket profit is **excluded** from ticket profit and ticket profit becomes
  **not effective** (covers single-ticket and double-ticket cases); voided additional
  ticket profit is excluded too.

### Decisions confirmed with the user

| # | Question | Decision |
|---|----------|----------|
| 1 | Restore scope | **Full snapshot** — status, ticket_number, PNR, fare, agent, dates, baggage, issue_type, user_id, etc. — **except `outbound_pending`**, which is recomputed (§3 Branch B) |
| 2 | Companion `pending_outbound` ticket | **REVERSED from v1:** hard-deleted on void of a null/regular ticket — **but only when its status is `pending`**; `awaiting-group` and `issued` companions are kept |
| 3 | Confirmation UI | **Browser `confirm()`** (matches `bookings/index.blade.php:7132`); **after void, close the Ticket Info modal only if no viewable ticket remains** |
| 4 | Audit row for the void itself | **Yes** — new enum value `void` in `issued_ticket_logs.action` (written by **both** branches) |
| 5 | Additional tickets | **Voidable** → soft delete + `updateTotals(-amount, 'additional_ticket_voided')` + source `TicketRequest` reopened to `pending` (`processed_at`/`result_issued_ticket_id` cleared) |
| 6 | Dashboards / filters | Void **erases the issuance event** — a later-`void`ed `issued` log must not be counted |
| 7 | Naming / validation / guards | Controller method **`voidTicket`**, `issued_ticket_id` **validated**, **VISA_ONLY → 403** guard added |
| 8 | Companion kept ⇒ `outbound_pending` | Issued/awaiting-group companion remains → voided ticket `outbound_pending = true`; pending companion hard-deleted → `false` |

---

## 2. Current behaviour (code map)

### Entry points

| What | Location |
|------|----------|
| Passenger tab (inside Bookings index) | `resources/views/bookings/index.blade.php` — tab body `:367`, row template `:588` |
| Ticket Panel column header | `bookings/index.blade.php:575` |
| Eye button (opens Ticket Info modal) | `bookings/index.blade.php:829-838` → `openTicketInfoModal(idx)` `:5222` |
| **Ticket Info modal** | `bookings/index.blade.php:1390-1489` |
| Actions cell + the 3 buttons | `bookings/index.blade.php:1462-1477` |
| `viewableTickets()` filter | `bookings/index.blade.php:5227-5231` → keeps only `issued`, `re-issued`, `refunded` |
| Row data source | `passengersTicketData` ← `loadPassengerData()` `:3383` → `GET /api/bookings/passengers` (route `routes/web.php:210`) |

### Existing buttons → routes → controllers

| Button | JS handler | URL | Controller |
|--------|-----------|-----|-----------|
| Edit | `handleTicketFareSubmit()` `:6103` | `PUT /bookings/{b}/passengers/{p}/ticket-edit` | `TicketIssueController::edit` (`:165`) |
| Re-Issue | `handleReIssueSubmit()` `:5909` | `POST /bookings/{b}/passengers/{p}/re-issue` | `ReIssueController::store` |
| Refund | `handleRefundSubmit()` `:5715` | `POST /bookings/{b}/passengers/{p}/refund` | `RefundController::store` |

Routes live in `routes/web.php:569-598` inside
`Route::middleware('role:Super Admin,Co Admin,Ticket Admin,Ticket Staff')`.

### The issuance log (the "old value / new value" the requirement refers to)

**Model:** `app/Models/IssuedTicketLog.php` — fillable `issued_ticket_id, user_id, action, old_data, new_data`,
casts `old_data`/`new_data` → `array` (relation `IssuedTicket::logs()` `app/Models/IssuedTicket.php:69`,
helper `IssuedTicket::logAction()` `app/Models/IssuedTicket.php:101-109`).

**Table:** `database/migrations/2026_06_12_100005_create_issued_ticket_logs_table.php`

```php
$table->enum('action', ['issued', 'edited', 're-issued', 'refunded']);
$table->json('old_data')->nullable();   // pre-change snapshot
$table->json('new_data')->nullable();   // post-change snapshot (carries status)
// issued_ticket_id FK: cascadeOnDelete
```

Enum extended twice by MySQL-only `DB::statement(... MODIFY COLUMN ...)`:
- `2026_07_26_000001_add_confirmed_group_to_issued_ticket_logs_action.php`
- `2026_09_16_000001_add_reverted_group_to_issued_ticket_logs_action.php`

Current enum: `('issued','edited','re-issued','refunded','confirmed_group','reverted_group')`

### Where the "issued" log is written

`TicketIssueController::issue()` — `app/Http/Controllers/TicketIssueController.php:21`

```php
// :27  VISA_ONLY guard → 403
// :31  hold/cancel guard → 422
// :65  guard — only pending / awaiting-group may be issued
if (! in_array($issuedTicket->status, ['pending', 'awaiting-group'])) { ... }

$oldData = $issuedTicket->toArray();                    // :72  ← OLD VALUE snapshot
$updateData = array_merge($validated, ['status' => 'issued', ...]);
$issuedTicket->update($updateData);                     // :87
$passenger->update(['ticket_status' => 'issued']);       // :89  ← passenger mirror
// :91-109 companion pending_outbound creation / clearPendingOutboundForRoundMulti / clear_double_ticket
$issuedTicket->logAction('issued', $oldData, $issuedTicket->toArray());  // :119 ← LOG
```

**Key insight:** `old_data.status` is whatever the ticket was before issuance
(`pending` or `awaiting-group`), and `old_data` also contains ticket_number, pnr,
ticket_fare_id, net_fare, dates, baggage, issue_type, user_id, etc.

`IssuedTicket` has **no `$appends`**, `issue()` loads **no relations** before `toArray()`,
and every key in `old_data` is in `$fillable` → `update($restore)` is mass-assignment safe.
Date fields are `date`-cast → Carbon parses the serialized ISO strings on write.

### Additional tickets (processAdditional)

`TicketRequestController::processAdditional()` — `app/Http/Controllers/TicketRequestController.php:508-617`

Creates an `IssuedTicket` with `status='issued'`, `issue_type='additional'` and **never
calls `logAction()`**. Side effects void must reverse:
1. `$passenger->update(['ticket_status' => 'issued'])` (`:578`)
2. `$ticketRequest->update(['status'=>'processed','processed_at'=>now(),'result_issued_ticket_id'=>$id])` (`:580-584`)
3. `InvoiceService::updateTotals($invoice, total + $ticketAmount, 'additional_ticket_added')` (`:595`)
   where `$ticketAmount = fare->ticket_type === OFFER ? (offer_price ?: selling_fare) : selling_fare`

`result_issued_ticket_id` FK is nullable + `nullOnDelete`; `additionalTicketsByBooking()`
(`:740`) uses the model → trashed rows automatically excluded from the additional list.

### Status model

- Ticket record = `app/Models/IssuedTicket.php` (soft deletes), `status` is a plain string
  (casts at `:27-37` do **not** cast `status`).
- Enum: `app/Enums/TicketStatus.php` → `pending | issued | re-issued | refunded | awaiting-group`
- DB column: `issued_tickets.status` enum, extended by
  `2026_07_25_000001_add_awaiting_group_and_double_ticket_columns.php`
- Passenger mirror: `passengers.ticket_status` enum **`['pending','issued','re-issued','refunded']`**
  (`database/migrations/2026_05_08_000003_create_passengers_table.php:28`) — **no `awaiting-group` value**,
  not nullable. Cast to `TicketStatus` (`app/Models/Passenger.php:72`), read by
  `getComputedStatusAttribute()` (`Passenger.php:394-431`).
- Only `TicketIssueController::issue()` (`:89`) and `TicketRequestController::processAdditional()`
  (`:578`) ever write `passengers.ticket_status` (Refund/ReIssue never touch it — pre-existing).

### Derived display data

- Passenger row "ticket status" column is **derived**, not stored:
  `BookingController::computeTicketData()` `app/Http/Controllers/BookingController.php:795-798`
  → latest regular ticket's `status`. Payload also carries `id` + `booking_id` (used by the JS handler).
- `all_issued_tickets` payload incl. `has_pending_request`:
  `BookingController::computeAllIssuedTickets()` `:1075-1114` (`:1091`).

### Side effects that matter

- **Profit:** `ProfitCalculationService` counts tickets with status in
  `['issued','re-issued','refunded']` (`:297`, `:417`, `:623`, `:725`).
  - `calculateTicketProfit()` (`:401`) returns 0 unless `isTicketProfitEffective()` (`:716`)
    (`regularTickets` non-empty **and every** status ∈ issued/re-issued/refunded) — so a voided
    leg makes the whole ticket component 0 **and** `determineTicketEffectiveDate()` (`:693`) → null
    (**not effective**). Covers single- and double-ticket cases with no new profit code.
  - `calculateAdditionalTicketProfit()` (`:417`) filters `allIssuedTickets` → soft-deleted
    additional tickets drop out automatically.
  - `IssuedTicketObserver::updated` (`app/Observers/IssuedTicketObserver.php:17-28`) recalcs
    profit only on `net_fare`/`issue_type` change and always calls `syncComputedStatus()`;
    `deleted()` (`:31-34`) **always** recalcs → covers the additional soft-delete branch.
    Observer is registered at `AppServiceProvider.php:57`.
- **Reports read logs** via `new_data LIKE '%"status":"issued"%'` / `new_data->status = 'issued'`:
  - **Already safe (no change):** `ProfitLossReportController:106`, `DashboardController:193`,
    `BranchWiseReportController:329` — all require `issue_type='additional'` +
    `whereIn('it.status', ['issued','re-issued','refunded'])` + `deleted_at IS NULL` → voided
    additional excluded by soft delete; voided regular isn't `additional`.
  - **NEED THE VOID RULE (change):** `DashboardController:325,331`,
    `BranchWiseReportController:122,129` (log counts) and `BookingPassengerQuery:446-454`
    (ticket_issued date filter). These keep counting a voided regular ticket because its
    original `issued` log survives. Soft-deleted additional tickets are already excluded at
    these sites by their `whereHas('issuedTicket(s)')` clauses (global scope) — including the
    additional-branch `void` log whose `new_data.status` is still `issued`.
  - `ProfitCalculationService:451` — only invoked for tickets already taken from
    `allIssuedTickets` (trash-excluded) → safe.
- **Pending requests** disable Re-Issue/Void in the UI (`ticket.has_pending_request`);
  `pendingRequests()` (`IssuedTicket.php:94-99`) already constrains `status='pending'` +
  `request_type in (re_issue, refund)`.
- **`issued_ticket_logs.issued_ticket_id` FK = `cascadeOnDelete`** → force-deleting a
  companion removes its logs too (companions we delete are status `pending` and have no logs anyway).

### Test environment

`phpunit.xml` sets `DB_CONNECTION=mysql` (db `umrah_test`) — **MySQL, not SQLite** (note: this
contradicts AGENTS.md, which is stale — phpunit.xml is authoritative), so the enum
`ALTER TABLE ... MODIFY COLUMN` migrations work in tests.
Test helper pattern to copy: `tests/Feature/PassengerServiceRequiredGatingTest.php:33-135`
(`makeAdmin()`, `seedDeps()`, `makeBooking()`).

---

## 3. Implementation plan

### Step 1 — Migration: allow the `void` action

**New file:** `database/migrations/2026_09_27_000001_add_void_to_issued_ticket_logs_action.php`

Follow `2026_09_16_000001_add_reverted_group_to_issued_ticket_logs_action.php` exactly:

```php
public function up(): void
{
    DB::statement("ALTER TABLE issued_ticket_logs
        MODIFY COLUMN action ENUM('issued','edited','re-issued','refunded','confirmed_group','reverted_group','void')");
}

public function down(): void
{
    DB::statement("ALTER TABLE issued_ticket_logs
        MODIFY COLUMN action ENUM('issued','edited','re-issued','refunded','confirmed_group','reverted_group')");
}
```

> `IssuedTicketLog::$fillable` already contains `action` — no model change needed.
> (Known pattern flaw shared with prior enum migrations: `down()` fails in MySQL strict mode
> once `void` rows exist — accepted, no action.)

---

### Step 2 — Route

**File:** `routes/web.php` — insert inside the role group, right after the
`ticket-edit` route (`:580-581`):

```php
Route::post('/bookings/{booking}/passengers/{passenger}/ticket-void', [TicketIssueController::class, 'voidTicket'])
    ->name('bookings.passengers.ticket-void');
```

Same middleware group as issue/edit/re-issue/refund
(`role:Super Admin,Co Admin,Ticket Admin,Ticket Staff`).

---

### Step 3 — Controller: `TicketIssueController::voidTicket()`

**File:** `app/Http/Controllers/TicketIssueController.php` (add a new method; `issue()` is `:21`, `edit()` `:165`).

New imports: `App\Enums\TicketType`, `App\Models\TicketRequest`, `App\Services\InvoiceService`,
`App\Services\ProfitCalculationService`.

Guards — mirror `issue()` (`:21-67`) exactly, in order:

```
1.  $passenger->booking_id !== $booking->id                → 403
2.  serviceValue($passenger) === VISA_ONLY                 → 403   (NEW — mirrors :27-29)
    ("Ticket service is not required for this passenger (Visa Only)")
3.  $passenger->isOnHold() || isOnCancel() || is_cancelled → 422
    ("Cannot modify ticket for a cancelled passenger")
4.  $request->validate(['issued_ticket_id' => 'required|exists:issued_tickets,id'])   (NEW)
5.  IssuedTicket where id = payload issued_ticket_id AND passenger_id = $passenger->id
    not found                                              → 404
6.  status !== 'issued'                                    → 400
    ("Only issued tickets can be voided.")
7.  $issuedTicket->pendingRequests()->exists()             → 400
    ("This ticket has a pending request. Process or reject it first.")
8.  [Branch B only] latest action='issued' log lookup      → 400 if missing
    ("No issue log found for this ticket; it cannot be voided.")
    (latest('id') so issue → void → issue cycles restore the most recent pre-issue state)
```

Then, **branch on `issue_type`**, all inside `DB::beginTransaction()`:

#### Branch A — `issue_type === 'additional'` (soft delete)

```
$beforeVoid = $issuedTicket->toArray();

// 1. reverse the invoice addition (same amount rule as processAdditional :590-595)
$amount = $issuedTicket->ticketFare?->ticket_type === TicketType::OFFER
    ? (float) ($issuedTicket->offer_price ?: $issuedTicket->selling_fare ?? 0)
    : (float) ($issuedTicket->selling_fare ?? 0);
$invoice = $booking->invoice;                     // or $ticketRequest->booking->invoice equivalent
if ($invoice && $amount > 0) {
    app(InvoiceService::class)->updateTotals(
        $invoice,
        max(0, (float) $invoice->total_amount - $amount),
        'additional_ticket_voided'
    );
}

// 2. reopen the source request
TicketRequest::where('result_issued_ticket_id', $issuedTicket->id)
    ->latest('id')->first()
    ?->update(['status' => 'pending', 'processed_at' => null, 'result_issued_ticket_id' => null]);

// 3. soft delete → observer deleted() recalculates booking profit automatically
$issuedTicket->delete();

// 4. passenger mirror (same logic as Branch B)
$stillIssued = $passenger->allIssuedTickets
    ->where('id', '!=', $issuedTicket->id)
    ->whereIn('status', ['issued', 're-issued'])->isNotEmpty();
if (! $stillIssued) {
    $passenger->update(['ticket_status' => 'pending']);   // enum has no 'awaiting-group', not nullable
}
$passenger->syncComputedStatus();                  // parity with Branch B (observer does it there)

// 5. audit row — new_data.status stays 'issued' but row is trashed;
//    every count site excludes it via whereHas('issuedTicket') global scope
$issuedTicket->logAction('void', $beforeVoid, $issuedTicket->toArray());

DB::commit();
```

> **No issue-log guard** in this branch — additional tickets never get an `issued` log
> (this is why old test #6 was rewritten, see §Step 6).

#### Branch B — `null` / `regular` / `pending_outbound` (snapshot restore)

```
$beforeVoid = $issuedTicket->toArray();
$log = IssuedTicketLog::where('issued_ticket_id', $issuedTicket->id)
          ->where('action', 'issued')->latest('id')->first();      // guard 8 → 400 if null
$restore = collect($log->old_data)
             ->except(['id', 'created_at', 'updated_at', 'deleted_at'])
             ->all();

$isRegularLike = is_null($issuedTicket->issue_type) || $issuedTicket->issue_type === 'regular';

if ($isRegularLike) {
    // D2: hard-delete ONLY status='pending' companions; awaiting-group/issued are kept
    IssuedTicket::where('passenger_id', $passenger->id)
        ->where('issue_type', 'pending_outbound')
        ->where('status', 'pending')
        ->forceDelete();

    // D8: recompute outbound_pending (overrides the snapshot):
    //   issued/awaiting-group companion remains → true; none remain → false
    $restore['outbound_pending'] = IssuedTicket::where('passenger_id', $passenger->id)
        ->where('issue_type', 'pending_outbound')
        ->exists();
}

$issuedTicket->update($restore);
$fareChanged = $issuedTicket->wasChanged(['net_fare', 'issue_type']);   // read immediately after update

// passenger mirror (issue() wrote it at :89)
$stillIssued = $passenger->allIssuedTickets
    ->where('id', '!=', $issuedTicket->id)
    ->whereIn('status', ['issued', 're-issued'])->isNotEmpty();
if (! $stillIssued) {
    $passenger->update(['ticket_status' => 'pending']);
}

$issuedTicket->logAction('void', $beforeVoid, $issuedTicket->toArray());

// minor-4 fix: observer already recalced iff net_fare/issue_type changed;
// status-only changes still affect profit eligibility → recalc manually in that case only
if (! $fareChanged) {
    app(ProfitCalculationService::class)->recalculateBookingProfit($booking);
}

DB::commit();
```

**Explicitly touched companion behaviour (replaces v1 "NOT touched"):**
- `pending_outbound`, status `pending` → **hard delete** (`forceDelete`; cascade FK safe —
  pending companions have no logs; `ticket_requests.issued_ticket_id` FK is `nullOnDelete`).
- `pending_outbound`, status `awaiting-group` or `issued` → **kept**; restored ticket gets
  `outbound_pending = true`.

**Known limitation (document, don't fix):** if `issue()` ran
`clearPendingOutboundForRoundMulti()` and deleted an existing pending_outbound ticket,
that deletion cannot be undone by void.

#### Response (both branches)

```php
return response()->json([
    'success' => true,
    'message' => 'Ticket voided successfully.',
    'issued_ticket' => $issuedTicket->load([...same relations as issue()...]),
]);
// catch → DB::rollBack(), \Log::error(...), 500
```

> `voidTicket` as a method name is legal PHP 7+ (context-sensitive lexer) — verified.

---

### Step 4 — Erase issuance events from dashboards/filters (D6)

**File:** `app/Models/IssuedTicketLog.php` — add a scope:

```php
public function scopeNotSupersededByVoid($query): void
{
    $query->whereNotExists(function ($sub) {
        $sub->selectRaw('1')
            ->from('issued_ticket_logs as void_logs')
            ->whereColumn('void_logs.issued_ticket_id', 'issued_ticket_logs.id')
            ->where('void_logs.action', 'void')
            ->whereColumn('void_logs.id', '>', 'issued_ticket_logs.id');
    });
}
```

**Why this rule (not current-status):** it correctly handles **issue → void → re-issue**
(the original `issued` log is suppressed by the newer `void` log; the second `issued` log
still counts). A plain status check would double-count that case.

Apply at exactly the 3 sites that still count voided *regular* tickets:

| # | File | Change |
|---|------|--------|
| 1 | `app/Http/Controllers/DashboardController.php:325,331` | chain `->notSupersededByVoid()` |
| 2 | `app/Http/Controllers/BranchWiseReportController.php:122,129` | chain `->notSupersededByVoid()` |
| 3 | `app/Queries/BookingPassengerQuery.php:447` | add `->notSupersededByVoid()` inside the `$log` closure |

**Verified — no change needed anywhere else:**
- `ProfitLossReportController:106`, `DashboardController:193`, `BranchWiseReportController:329`
  — additional-only + current-status + `deleted_at IS NULL` filters already exclude voids.
- The 3 changed sites all `whereHas('issuedTicket(s)')` → soft-deleted additional tickets
  (and their `void` log, `new_data.status='issued'`) excluded by the global scope.
- `ProfitCalculationService:451` — called only for non-trashed tickets.

---

### Step 5 — Frontend

**File:** `resources/views/bookings/index.blade.php`

#### 5a. Void button — insert at `:1472` (after the Refund template, inside the Actions cell)

```blade
<template x-if="ticket.status === 'issued'">
    <button type="button"
        @click="handleTicketVoid(ticket)"
        :disabled="ticket.has_pending_request"
        @mouseenter="ticket.has_pending_request && showRequestPendingTooltip($event)"
        @mouseleave="hideRequestPendingTooltip()"
        :class="ticket.has_pending_request ? 'opacity-40 cursor-not-allowed' : 'hover:bg-amber-50'"
        class="px-3 py-1 text-xs font-medium text-amber-600 border border-amber-200 rounded-lg transition">Void</button>
</template>
```

- `ticket.status === 'issued'` covers regular **and** additional tickets (both render in
  `viewableTickets()` `:5227-5231`), never for re-issued / refunded.
- Same `has_pending_request` disabled + tooltip behaviour as Re-Issue / Refund.
- (Hold/Cancel does not visually dim this button — click shows a toast instead; accepted as-is.)

#### 5b. Handler — add next to `handleRefundSubmit()` (`:5715`)

```js
async handleTicketVoid(ticket) {
    if (this.isSubmitting) return;

    const pax = this.passengersTicketData[this.ticketInfoPassengerIndex];
    if (pax?.status === 'Hold' || pax?.status === 'Cancel') {
        this.showToast('Void is not available for passengers with ' + pax.status + ' status.', 'error');
        return;
    }

    if (!confirm('Void this ticket? It will be restored to its state before it was issued.')) return;

    this.isSubmitting = true;
    try {
        const r = await fetch(`/bookings/${pax.booking_id}/passengers/${pax.id}/ticket-void`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
            },
            body: JSON.stringify({ issued_ticket_id: ticket.id })
        });
        const res = await r.json();
        if (res.success) {
            this.showToast('Ticket voided successfully.', 'warning');
            await this.loadPassengerData();   // rebuilds passengersTicketData
            // D3: close ONLY when no viewable ticket remains for this passenger
            if (!this.viewableTickets(this.ticketInfoPassengerIndex).length) {
                this.isTicketInfoModalOpen = false;
            }
        } else {
            this.showToast(res.message || 'Failed to void ticket.', 'error');
        }
    } catch (err) {
        console.error('Void error:', err);
        this.showToast('Failed to void ticket.', 'error');
    } finally {
        this.isSubmitting = false;
    }
}
```

**Why no manual state surgery:** `loadPassengerData()` (`:3383`) re-fetches
`/api/bookings/passengers` and rebuilds `passengersTicketData`. The voided ticket's status
becomes `pending` (or it is trashed), so it drops out of `viewableTickets()` (`:5230`) and
out of the derived row status (`BookingController:795`). Unlike refund (where the row stays
visible as `Refunded`), void removes the row — hence the explicit close-when-empty rule (D3).

---

### Step 6 — Tests (TDD-first)

**New file:** `tests/Feature/TicketVoidTest.php`
Copy the `makeAdmin()` / `seedDeps()` / `makeBooking()` helpers from
`tests/Feature/PassengerServiceRequiredGatingTest.php:33-135`.

| # | Test | Assertion |
|---|------|-----------|
| 1 | `test_void_reverts_ticket_to_pre_issue_state` | create pending ticket (no ticket_number/pnr/fare) → issue it → void → status `pending`, `ticket_number`/`pnr`/`net_fare`/`ticket_fare_id` back to pre-issue values |
| 2 | `test_void_reverts_passenger_ticket_status` | passenger `ticket_status` `issued` → `pending` (when no other issued ticket) |
| 3 | `test_void_keeps_passenger_ticket_status_when_other_ticket_issued` | second ticket still issued → `ticket_status` stays `issued` |
| 4 | `test_void_writes_void_audit_log` | `IssuedTicketLog` row with `action='void'`, `old_data.status='issued'`, `new_data.status='pending'` |
| 5 | `test_void_rejected_when_ticket_not_issued` | pending ticket → `400` |
| 6′ | `test_void_rejected_when_no_issue_log_for_regular_ticket` | **rewritten** — issued **regular** ticket created with no `issued` log → `400` (additional tickets no longer land here) |
| 7 | `test_void_rejected_when_pending_request_exists` | pending `TicketRequest` → `400` |
| 8 | `test_void_rejected_for_hold_or_cancel_passenger` | passenger on Hold → `422` |
| 9 | `test_void_blade_renders_void_button_gated_on_issued_status` | source assertions: contains `handleTicketVoid`, contains `x-if="ticket.status === 'issued'"` for the Void button |
| 10 | `test_void_issue_void_issue_void_cycle_restores_latest_pre_issue_state` | issue (fare A) → void → issue (fare B) → void → state = pre-second-issue (locks `latest('id')` in guard 8) |
| 11 | `test_void_restores_awaiting_group_state` | confirm-group → issue → void → ticket `awaiting-group`, passenger mirror `pending` |
| 12 | `test_void_excludes_ticket_profit_and_effectiveness` | single ticket with `net_fare` → profit > 0, `ticket_profit_effective_at` set → void → passenger ticket profit 0, `ticket_profit_effective_at` null, booking profit updated |
| 13 | `test_void_one_leg_of_double_ticket_makes_ticket_profit_not_effective` | regular + `pending_outbound` both issued → void regular → whole ticket component 0 / not effective |
| 14 | `test_void_regular_hard_deletes_pending_companion` | companion status `pending` → void → `assertDatabaseMissing('issued_tickets', companion)` (hard delete) + restored `outbound_pending=false` |
| 15 | `test_void_regular_keeps_issued_companion_sets_outbound_pending_true` | issued companion survives → restored ticket `outbound_pending=true` |
| 16 | `test_void_additional_soft_deletes_and_excludes_profit` | additional issued → void → row has `deleted_at` (soft), excluded from `all_issued_tickets` payload, additional profit 0 |
| 17 | `test_void_additional_reverses_invoice_and_reopens_request` | invoice `total_amount` back to pre-add value, request `status='pending'`, `processed_at`/`result_issued_ticket_id` null |
| 18 | `test_void_rejected_for_visa_only_passenger` | `service_required=visa_only` → `403` |
| 19 | `test_issue_log_superseded_by_void_not_counted` | logs `[issued, void]` → `notSupersededByVoid()` count 0; logs `[issued, void, issued]` → count 1 |
| 20 | `test_voided_ticket_excluded_from_ticket_issued_filter` | `GET /api/bookings/passengers?status_change_action=ticket_issued` → passenger present before void, absent after |
| 21 | `test_void_booking_passenger_mismatch_403` | ticket belongs to another booking → `403` |

Run order (TDD): write failing test → confirm fail → implement → confirm pass.

---

## 4. Files touched

| # | File | Change |
|---|------|--------|
| 1 | `database/migrations/2026_09_27_000001_add_void_to_issued_ticket_logs_action.php` | **new** |
| 2 | `routes/web.php` | add `bookings.passengers.ticket-void` route → `voidTicket` (~`:581`) |
| 3 | `app/Http/Controllers/TicketIssueController.php` | add `voidTicket()` + imports (`TicketType`, `TicketRequest`, `InvoiceService`, `ProfitCalculationService`) |
| 4 | `app/Models/IssuedTicketLog.php` | add `scopeNotSupersededByVoid()` |
| 5 | `app/Http/Controllers/DashboardController.php` | apply scope at `:325,331` |
| 6 | `app/Http/Controllers/BranchWiseReportController.php` | apply scope at `:122,129` |
| 7 | `app/Queries/BookingPassengerQuery.php` | apply scope at `:447` |
| 8 | `resources/views/bookings/index.blade.php` | Void button (`:1472`) + `handleTicketVoid()` (`~:5783`) |
| 9 | `tests/Feature/TicketVoidTest.php` | **new** (21 tests) |

**No Eloquent model changes** beyond the log scope (`IssuedTicketLog::$fillable` already has
`action`; `IssuedTicket` needs nothing).

---

## 5. Verification

```bash
php artisan test --filter=TicketVoidTest   # new tests
php artisan test                           # full suite
vendor/bin/pint                            # code style
npm run build                              # frontend build
docker compose config --quiet
docker compose -f docker-compose.prod.yml config --quiet
```

---

## 6. Out of scope / follow-ups

- **`awaiting-group` companions are NOT deleted** (user decision: "pending only").
- Issued `pending_outbound` companions are never deleted by voiding the regular ticket —
  void them individually via the same Void button if needed.
- Void is **not** offered for `re-issued` or `refunded` tickets (per requirement).
- Companions already removed by `clearPendingOutboundForRoundMulti()` during `issue()`
  cannot be restored by void (irreversible, documented).
- Invoice reversal for additional tickets uses the fare's **current** `ticket_type` with the
  ticket's stored fares — if the fare row's `ticket_type` changed since issuance the reversed
  amount could differ by the originally-added amount (accepted edge; `audit_reason` records
  every `updateTotals` call).
- AGENTS.md still claims tests run on SQLite — stale; `phpunit.xml` (MySQL `umrah_test`)
  is authoritative. Not fixed here.
