# Plan: Add "Void" button to the Ticket Info modal (Passenger tab → Ticket Panel)

> **Status:** Plan only — no implementation yet.
> **Scope:** Passenger index → Ticket Panel column → eye button → Ticket Info modal.
> Today that modal has **Edit**, **Re-Issue**, **Refund**. We add a 4th action: **Void**.

---

## 1. Requirement

- When a ticket's status is **`issued`**, show a **Void** button in the Ticket Info modal.
- Clicking Void reverts the ticket to the state captured in the **`issued`** row of
  `issued_ticket_logs` (the log written at the moment of issuance), i.e. the ticket
  becomes **how it was before it was issued**.

### Decisions confirmed with the user

| # | Question | Decision |
|---|----------|----------|
| 1 | Restore scope | **Full snapshot** — status, ticket_number, PNR, fare, agent, dates, baggage, issue_type, user_id, etc. |
| 2 | Companion `pending_outbound` ticket created at issue time | **Leave it** — do not delete on void |
| 3 | Confirmation UI | **Browser `confirm()`** (matches existing pattern at `bookings/index.blade.php:7132`) |
| 4 | Audit row for the void itself | **Yes** — new enum value `void` in `issued_ticket_logs.action` |

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
| Row data source | `passengersTicketData` ← `loadPassengerData()` `:3383` → `GET /api/bookings/passengers` |

### Existing buttons → routes → controllers

| Button | JS handler | URL | Controller |
|--------|-----------|-----|-----------|
| Edit | `handleTicketFareSubmit()` `:6103` | `PUT /bookings/{b}/passengers/{p}/ticket-edit` | `TicketIssueController::edit` (`:165`) |
| Re-Issue | `handleReIssueSubmit()` `:5909` | `POST /bookings/{b}/passengers/{p}/re-issue` | `ReIssueController::store` |
| Refund | `handleRefundSubmit()` `:5715` | `POST /bookings/{b}/passengers/{p}/refund` | `RefundController::store` |

Routes live in `routes/web.php:569-598` inside
`Route::middleware('role:Super Admin,Co Admin,Ticket Admin,Ticket Staff')`.

### The issuance log (the "old value / new value" the requirement refers to)

**Model:** `app/Models/IssuedTicketLog.php` — fillable `issued_ticket_id, user_id, action, old_data, new_data`
(relation `IssuedTicket::logs()` `app/Models/IssuedTicket.php:71`, helper
`IssuedTicket::logAction()` `app/Models/IssuedTicket.php:101-109`).

**Table:** `database/migrations/2026_06_12_100005_create_issued_ticket_logs_table.php`

```php
$table->enum('action', ['issued', 'edited', 're-issued', 'refunded']);
$table->json('old_data')->nullable();   // pre-change snapshot
$table->json('new_data')->nullable();   // post-change snapshot (carries status)
```

Enum extended twice by MySQL-only `DB::statement(... MODIFY COLUMN ...)`:
- `2026_07_26_000001_add_confirmed_group_to_issued_ticket_logs_action.php`
- `2026_09_16_000001_add_reverted_group_to_issued_ticket_logs_action.php`

Current enum: `('issued','edited','re-issued','refunded','confirmed_group','reverted_group')`

### Where the "issued" log is written

`TicketIssueController::issue()` — `app/Http/Controllers/TicketIssueController.php:21`

```php
// :65  guard — only pending / awaiting-group may be issued
if (! in_array($issuedTicket->status, ['pending', 'awaiting-group'])) { ... }

$oldData = $issuedTicket->toArray();                    // :72  ← OLD VALUE snapshot
$updateData = array_merge($validated, ['status' => 'issued', ...]);
$issuedTicket->update($updateData);                     // :87
$passenger->update(['ticket_status' => 'issued']);       // :89  ← passenger mirror
...
$issuedTicket->logAction('issued', $oldData, $issuedTicket->toArray());  // :119 ← LOG
```

**Key insight:** `old_data.status` is whatever the ticket was before issuance
(`pending` or `awaiting-group`), and `old_data` also contains ticket_number, pnr,
ticket_fare_id, net_fare, dates, baggage, issue_type, user_id, etc.

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

### Derived display data

- Passenger row "ticket status" column is **derived**, not stored:
  `BookingController::computeTicketData()` `app/Http/Controllers/BookingController.php:795-798`
  → latest regular ticket's `status`.
- `all_issued_tickets` payload incl. `has_pending_request`:
  `BookingController::computeAllIssuedTickets()` `:1075-1114` (`:1091`).

### Side effects that matter

- **Profit:** `ProfitCalculationService` counts tickets with status in
  `['issued','re-issued','refunded']` (`:297`, `:417`, `:623`, `:725`).
  `IssuedTicketObserver::updated` (`app/Observers/IssuedTicketObserver.php:17-28`) only
  recalculates on `net_fare` / `issue_type` change and always calls `syncComputedStatus()`.
  → **status-only change would NOT trigger a profit recalc** unless `net_fare` changes.
- **Reports** read logs via `new_data LIKE '%"status":"issued"%'` / `new_data->status = 'issued'`
  (`DashboardController:193,325`, `ProfitLossReportController:106`,
  `BranchWiseReportController:122,329`, `ProfitCalculationService:450`,
  `BookingPassengerQuery:446-454`). A `void` log with `new_data.status = 'pending'`
  is naturally excluded → **no report breakage**.
- **Pending requests** disable Re-Issue/Refund in the UI (`ticket.has_pending_request`).

### Test environment

`phpunit.xml` sets `DB_CONNECTION=mysql` (db `umrah_test`) — **MySQL, not SQLite**, so the
enum `ALTER TABLE ... MODIFY COLUMN` migrations work in tests.
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

---

### Step 2 — Route

**File:** `routes/web.php` — insert inside the role group, right after the
`ticket-edit` route (`:580-581`):

```php
Route::post('/bookings/{booking}/passengers/{passenger}/ticket-void', [TicketIssueController::class, 'void'])
    ->name('bookings.passengers.ticket-void');
```

Same middleware group as issue/edit/re-issue/refund
(`role:Super Admin,Co Admin,Ticket Admin,Ticket Staff`).

---

### Step 3 — Controller: `TicketIssueController::void()`

**File:** `app/Http/Controllers/TicketIssueController.php` (add a new method; `issue()` is `:21`, `edit()` `:165`)

Request body: `issued_ticket_id` (mirrors `issue()`).

```
1.  $passenger->booking_id !== $booking->id                → 403
2.  $passenger->isOnHold() || isOnCancel() || is_cancelled → 422
    ("Cannot modify ticket for a cancelled passenger")
3.  IssuedTicket where id = payload issued_ticket_id AND passenger_id = $passenger->id
    not found                                              → 404
4.  status !== 'issued'                                    → 400
    ("Only issued tickets can be voided.")
5.  $issuedTicket->pendingRequests()->where('status','pending')->exists() → 400
    ("This ticket has a pending request. Process or reject it first.")
6.  $log = IssuedTicketLog::where('issued_ticket_id', $id)
              ->where('action', 'issued')->latest('id')->first();
    missing                                                → 400
    ("No issue log found for this ticket; it cannot be voided.")
    (latest('id') so issue → void → issue cycles restore the most recent pre-issue state)
7.  DB::beginTransaction():
      $beforeVoid  = $issuedTicket->toArray();
      $restore     = collect($log->old_data)
                       ->except(['id', 'created_at', 'updated_at', 'deleted_at'])
                       ->all();                             // FULL SNAPSHOT
      $issuedTicket->update($restore);

      // passenger mirror (issue() wrote it at :89)
      $stillIssued = $passenger->allIssuedTickets
          ->where('id', '!=', $issuedTicket->id)
          ->whereIn('status', ['issued', 're-issued'])->isNotEmpty();
      if (! $stillIssued) {
          $passenger->update(['ticket_status' => 'pending']);
          // enum has no 'awaiting-group' and is NOT nullable → 'pending' is the safe value
      }

      $issuedTicket->logAction('void', $beforeVoid, $issuedTicket->toArray());

      // IssuedTicketObserver only recalcs profit on net_fare/issue_type change
      app(ProfitCalculationService::class)->recalculateBookingProfit($booking);

      DB::commit();
8.  return response()->json([
        'success' => true,
        'message' => 'Ticket voided successfully.',
        'issued_ticket' => $issuedTicket->load([...same relations as issue()...]),
      ]);
    catch → DB::rollBack(), \Log::error(...), 500
```

**Explicitly NOT touched:** the companion `pending_outbound` ticket created during
`issue()` (`TicketIssueController:91-109`) — it stays.

**Known limitation (document, don't fix):** if `issue()` ran
`clearPendingOutboundForRoundMulti()` and deleted an existing pending_outbound ticket,
that deletion cannot be undone by void.

---

### Step 4 — Frontend

**File:** `resources/views/bookings/index.blade.php`

#### 4a. Void button — insert at `:1472` (after the Refund template, inside the Actions cell)

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

- Rendered **only** when `ticket.status === 'issued'` → never for re-issued / refunded
  (matches the requirement "when ticket status is issued, the void button occurs").
- Same `has_pending_request` disabled + tooltip behaviour as Re-Issue / Refund.

#### 4b. Handler — add next to `handleRefundSubmit()` (`:5715`)

```js
handleTicketVoid(ticket) {
    if (this.isSubmitting) return;

    const pax = this.passengersTicketData[this.ticketInfoPassengerIndex];
    if (pax?.status === 'Hold' || pax?.status === 'Cancel') {
        this.showToast('Void is not available for passengers with ' + pax.status + ' status.', 'error');
        return;
    }

    if (!confirm('Void this ticket? It will be restored to its state before it was issued.')) return;

    this.isSubmitting = true;
    fetch(`/bookings/${pax.booking_id}/passengers/${pax.id}/ticket-void`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
        },
        body: JSON.stringify({ issued_ticket_id: ticket.id })
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            this.showToast('Ticket voided successfully.', 'warning');
            this.loadPassengerData();   // rebuilds passengersTicketData
        } else {
            this.showToast(res.message || 'Failed to void ticket.', 'error');
        }
    })
    .catch(err => {
        console.error('Void error:', err);
        this.showToast('Failed to void ticket.', 'error');
    })
    .finally(() => { this.isSubmitting = false; });
}
```

**Why no manual state surgery:** `loadPassengerData()` (`:3383`) re-fetches
`/api/bookings/passengers` and rebuilds `passengersTicketData`. The voided ticket's
status becomes `pending`, so it automatically drops out of `viewableTickets()` (`:5230`)
and out of the derived row status (`BookingController:795`). Ticket Info modal stays open
(consistent with the refund flow at `:5770-5771`).

---

### Step 5 — Tests (TDD-first)

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
| 6 | `test_void_rejected_when_no_issue_log_exists` | issued ticket with no `action='issued'` log (additional-ticket case) → `400` |
| 7 | `test_void_rejected_when_pending_request_exists` | pending `TicketRequest` → `400` |
| 8 | `test_void_rejected_for_hold_or_cancel_passenger` | passenger on Hold → `422` |
| 9 | `test_void_blade_renders_void_button_gated_on_issued_status` | source assertions: contains `handleTicketVoid`, contains `x-if="ticket.status === 'issued'"` for the Void button |

Run order (TDD): write failing test → confirm fail → implement → confirm pass.

---

## 4. Files touched

| # | File | Change |
|---|------|--------|
| 1 | `database/migrations/2026_09_27_000001_add_void_to_issued_ticket_logs_action.php` | **new** |
| 2 | `routes/web.php` | add `bookings.passengers.ticket-void` route (~`:581`) |
| 3 | `app/Http/Controllers/TicketIssueController.php` | add `void()` method + `ProfitCalculationService` import |
| 4 | `resources/views/bookings/index.blade.php` | Void button (`:1472`) + `handleTicketVoid()` (`~:5783`) |
| 5 | `tests/Feature/TicketVoidTest.php` | **new** |

**No model changes** (`IssuedTicketLog::$fillable` already has `action`).

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

- **Gap (pre-existing):** `TicketRequestController::processAdditional()`
  (`app/Http/Controllers/TicketRequestController.php:556-584`) creates an
  `IssuedTicket` with `status='issued'` and **never calls `logAction()`** → such tickets
  cannot be voided (test #6 covers the 400). Fixing that gap is a separate change.
- Void is **not** offered for `re-issued` or `refunded` tickets (per requirement).
- Companion `pending_outbound` ticket is left alone (per decision #2).
