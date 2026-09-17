# G-Cancel Workflow Plan

> **Status:** Plan Complete — Ready for Implementation
> **Date:** 2026-09-16

---

## 1. Summary

Add a "G-Cancel" workflow that reverts tickets from `awaiting-group` status back to `pending`. This is the reverse of the existing G-Confirm workflow. Each ticket independently shows G-Confirm (if pending) or G-Cancel (if awaiting-group).

---

## 2. State Machine

### Single-ticket package

| Regular Status | Buttons Shown |
|---|---|
| Pending | G-Confirm |
| Awaiting Group | G-Cancel |

### Double-ticket package

| Regular Status | Outbound Status | Buttons Shown |
|---|---|---|
| Pending | Pending | G-Confirm In, G-Confirm Out, G-Confirm Both |
| Awaiting Group | Pending | G-Cancel In, G-Confirm Out |
| Pending | Awaiting Group | G-Confirm In, G-Cancel Out |
| Awaiting Group | Awaiting Group | G-Cancel In, G-Cancel Out, G-Cancel Both |

**Rule:** For a ticket with `pending` status → G-Confirm button. For a ticket with `awaiting-group` status → G-Cancel button.

---

## 3. File Changes

### 3.1 Migration (new)

**File:** `database/migrations/2026_09_16_000001_add_reverted_group_to_issued_ticket_logs_action.php`

Add `reverted_group` to the `issued_ticket_logs.action` ENUM:

```php
DB::statement("ALTER TABLE issued_ticket_logs 
    MODIFY COLUMN action ENUM('issued','edited','re-issued','refunded','confirmed_group','reverted_group')");
```

---

### 3.2 Controller

**File:** `app/Http/Controllers/TicketIssueController.php`

Add `revertGroup(Request $request, Passenger $passenger)` method after `confirmGroup` (~line 660).

#### Validation

Same guards as `confirmGroup`:
- Reject visa-only passengers (403)
- Reject cancelled/on-hold passengers (422)
- `action` required, must be `in`, `out`, or `both`
- `booking_id` required, must exist, must belong to the passenger

#### Logic by action

| Action | What it does |
|---|---|
| `in` | Find regular ticket (`issue_type` null/`'regular'`) with `status = 'awaiting-group'` → set to `'pending'`. Log `reverted_group`. |
| `out` | Find outbound ticket (`issue_type = 'pending_outbound'`) with `status = 'awaiting-group'` → set to `'pending'`. Log `reverted_group`. |
| `both` | Combine `in` + `out` logic. |

**No deletion of outbound tickets.** Auto-created outbound tickets stay; status just reverts to `pending`.

#### Response

```json
{
    "success": true,
    "message": "Tickets reverted successfully.",
    "updated_ids": [1, 2]
}
```

#### Pseudocode

```php
public function revertGroup(Request $request, Passenger $passenger)
{
    // Guards (same as confirmGroup)
    // Validate: action in ['in', 'out', 'both'], booking_id exists
    
    $allTickets = $passenger->allIssuedTickets;
    $regularTicket = $allTickets->first(fn ($t) => is_null($t->issue_type) || $t->issue_type === 'regular');
    $outboundTicket = $allTickets->first(fn ($t) => $t->issue_type === 'pending_outbound');
    
    $updatedIds = [];
    
    DB::beginTransaction();
    
    if ($action === 'in' || $action === 'both') {
        if ($regularTicket && $regularTicket->status === 'awaiting-group') {
            $oldData = $regularTicket->toArray();
            $regularTicket->update(['status' => 'pending']);
            $regularTicket->logAction('reverted_group', $oldData, $regularTicket->toArray());
            $updatedIds[] = $regularTicket->id;
        }
    }
    
    if ($action === 'out' || $action === 'both') {
        if ($outboundTicket && $outboundTicket->status === 'awaiting-group') {
            $oldData = $outboundTicket->toArray();
            $outboundTicket->update(['status' => 'pending']);
            $outboundTicket->logAction('reverted_group', $oldData, $outboundTicket->toArray());
            $updatedIds[] = $outboundTicket->id;
        }
    }
    
    DB::commit();
    
    return response()->json([
        'success' => true,
        'message' => 'Tickets reverted successfully.',
        'updated_ids' => $updatedIds,
    ]);
}
```

---

### 3.3 Route

**File:** `routes/web.php`

Add after line 569 (after the `confirm-group` route):

```php
Route::put('/passengers/{passenger}/revert-group', [TicketIssueController::class, 'revertGroup'])
    ->name('passengers.revert-group');
```

---

### 3.4 View (Blade + JS)

**File:** `resources/views/bookings/index.blade.php`

#### 4a. HTML buttons (~line 792-805)

Replace the existing G-Confirm `<template x-if="rowHasConfirmableTickets(idx)">` block with:

```blade
<template x-if="rowHasConfirmableTickets(idx) || rowHasCancelableTickets(idx)">
    <div>
        {{-- Single-ticket mode --}}
        <template x-if="!showThreeButtonsMode(idx)">
            <span>
                <button x-show="showSingleGConfirm(idx)" @click="confirmTickets(idx, 'all')" class="px-2 py-1 text-xs font-medium text-indigo-600 rounded hover:bg-slate-50 transition">G-Confirm</button>
                <button x-show="showSingleGCancel(idx)" @click="revertTickets(idx, 'all')" class="px-2 py-1 text-xs font-medium text-amber-600 rounded hover:bg-slate-50 transition">G-Cancel</button>
            </span>
        </template>
        {{-- Double-ticket mode --}}
        <template x-if="showThreeButtonsMode(idx)">
            <span>
                <button x-show="showGConfirmIn(idx)" @click="confirmTickets(idx, 'in')" class="px-2 py-1 text-xs font-medium text-indigo-600 rounded hover:bg-slate-50 transition">G-Confirm In</button>
                <button x-show="showGCancelIn(idx)" @click="revertTickets(idx, 'in')" class="px-2 py-1 text-xs font-medium text-amber-600 rounded hover:bg-slate-50 transition">G-Cancel In</button>
                <button x-show="showGConfirmOut(idx)" @click="confirmTickets(idx, 'out')" class="px-2 py-1 text-xs font-medium text-indigo-600 rounded hover:bg-slate-50 transition">G-Confirm Out</button>
                <button x-show="showGCancelOut(idx)" @click="revertTickets(idx, 'out')" class="px-2 py-1 text-xs font-medium text-amber-600 rounded hover:bg-slate-50 transition">G-Cancel Out</button>
                <button x-show="showGConfirmBoth(idx)" @click="confirmTickets(idx, 'both')" class="px-2 py-1 text-xs font-medium text-indigo-600 rounded hover:bg-slate-50 transition">G-Confirm Both</button>
                <button x-show="showGCancelBoth(idx)" @click="revertTickets(idx, 'both')" class="px-2 py-1 text-xs font-medium text-amber-600 rounded hover:bg-slate-50 transition">G-Cancel Both</button>
            </span>
        </template>
    </div>
</template>
```

#### 4b. JS helper functions

Add after the existing `showGConfirmBoth` function (~line 4713):

```javascript
rowHasCancelableTickets(index) {
    const row = this.passengersTicketData[index];
    if (!row || row.is_cancelled) return false;
    return (row.all_issued_tickets || []).some(t => t.status === 'awaiting-group');
},

showSingleGConfirm(index) {
    return this.hasConfirmableRegular(index);
},

showSingleGCancel(index) {
    return this.hasCancelableRegular(index);
},

hasCancelableRegular(index) {
    const row = this.passengersTicketData[index];
    if (!row) return false;
    const regular = (row.all_issued_tickets || []).find(t => !t.issue_type || t.issue_type === 'regular');
    return regular && regular.status === 'awaiting-group';
},

hasCancelableOutbound(index) {
    const row = this.passengersTicketData[index];
    if (!row) return false;
    const outbound = (row.all_issued_tickets || []).find(t => t.issue_type === 'pending_outbound');
    return outbound && outbound.status === 'awaiting-group';
},

showGCancelIn(index) {
    return this.hasCancelableRegular(index);
},

showGCancelOut(index) {
    return this.hasCancelableOutbound(index);
},

showGCancelBoth(index) {
    return this.hasCancelableRegular(index) && this.hasCancelableOutbound(index);
},
```

#### 4c. `revertTickets` JS function

Add after the existing `confirmTickets` function (~line 4754):

```javascript
revertTickets(index, action) {
    const row = this.passengersTicketData[index];
    if (!row) return;

    fetch(`/passengers/${row.id}/revert-group`, {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
        },
        body: JSON.stringify({ action, booking_id: row.booking_id }),
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            (row.all_issued_tickets || []).forEach(t => {
                if (data.updated_ids.includes(t.id)) {
                    t.status = 'pending';
                }
            });
            if (row.latest_issued_ticket && data.updated_ids.includes(row.latest_issued_ticket.id)) {
                row.latest_issued_ticket.status = 'pending';
            }
            if (row.pending_outbound_issued_ticket && data.updated_ids.includes(row.pending_outbound_issued_ticket.id)) {
                row.pending_outbound_issued_ticket.status = 'pending';
            }
            this.showToast(data.message || 'Tickets reverted successfully.');
        } else {
            this.showToast(data.message || 'Failed to revert tickets.', 'error');
        }
    })
    .catch(err => {
        console.error('Revert group error:', err);
        this.showToast('Failed to revert tickets.', 'error');
    });
},
```

#### 4d. Button styling

- G-Confirm buttons: `text-indigo-600` (existing, no change)
- G-Cancel buttons: `text-amber-600` (new)

#### 4e. Status badges

No changes needed. The existing `getTicketStatuses()` already handles `pending` correctly. Reverting from `awaiting-group` to `pending` naturally changes the badge from "Awaiting Group" to "Pending".

---

## 4. Edge Cases

1. **Outbound ticket not deleted**: Auto-created outbound tickets (by G-Confirm) stay in the database when G-Cancel Out is clicked. Status reverts to `pending`.

2. **`outbound_pending` flag**: Not modified by revertGroup. The flag remains as-is since it reflects whether an outbound ticket exists, not its status.

3. **Already issued tickets**: `revertGroup` only allows reverting `awaiting-group` status. Issued/re-issued/refunded tickets are rejected.

4. **No outbound ticket exists**: `out` and `both` actions simply skip if no outbound ticket is found (no error).

---

## 5. Implementation Order

1. Create migration for `reverted_group` action ENUM
2. Add `revertGroup` method to `TicketIssueController`
3. Add route to `routes/web.php`
4. Add JS helper functions to `bookings/index.blade.php`
5. Add `revertTickets` JS function to `bookings/index.blade.php`
6. Update HTML button block in `bookings/index.blade.php`
7. Run `vendor/bin/pint` for code formatting
8. Test the workflow manually

---

## 6. Testing Checklist

- [ ] Single-ticket: G-Confirm → G-Cancel toggle works
- [ ] Double-ticket: G-Confirm In → G-Cancel In, G-Confirm Out both visible
- [ ] Double-ticket: G-Confirm Out → G-Confirm In, G-Cancel Out both visible
- [ ] Double-ticket: G-Confirm Both → G-Cancel In, Out, All visible
- [ ] G-Cancel reverts status to pending
- [ ] Status badge updates correctly after revert
- [ ] Audit log created with `reverted_group` action
- [ ] Auto-created outbound ticket not deleted on G-Cancel Out
- [ ] Revert rejected for issued tickets
- [ ] Revert rejected for cancelled/on-hold passengers
- [ ] `vendor/bin/pint` passes
