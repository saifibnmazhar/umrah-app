# Passenger Index: Status Filter & Markup Tooltip Fix

**Branch:** `passengerIndex/uiUpdates`

---

## Issue 1: Current Status Filter Returns Empty Results

### Root Cause

The `passenger_status_id` column is **NULL for all computed statuses**. The
`Passenger::syncComputedStatus()` method (line 451) actively nullifies it for
non-manual statuses. Only manual statuses (Hold, Cancel, Delivered, Ticket Refund
Done, Departure Done) store their ID in `passenger_status_id`.

Current filter in `BookingPassengerQuery::applyPassengerStatus()` (line 297):

```php
$this->query->where('passenger_status_id', $request->input('passenger_status'));
```

This only works for manual statuses. Computed statuses always return empty results
because no rows have `passenger_status_id` set for them.

The previous fix attempt broke manual statuses because it replaced the simple
`where('passenger_status_id', ...)` with computed-status SQL logic for ALL
statuses, but manual statuses have no relationship conditions to match — they are
purely stored in the `passenger_status_id` column.

### Computed Status Priority Chain

From `Passenger::getComputedStatusAttribute()` (line 376-412):

```
1. isTicketIssued AND isVisaIssued           → "Ticket Issued"
2. isVisaCancelled                           → "Processing"
3. isTicketIssued AND NOT isVisaIssued       → "Ticket Issued before Visa"
4. isVisaIssued (ticket NOT issued)          → "Visa Issued"
5. isVisaSubmitted (ticket+visa NOT issued)  → "Visa Submitted"
6. isFingerprintApproved (nothing above)     → "Fingerprint Done"
7. none match                                → null ("None")
```

Where:
- `isTicketIssued` = `passengers.ticket_status IN ('issued','re-issued')` OR
  `latestIssuedTicket.status IN ('issued','re-issued')` OR
  `pendingOutboundTicket.status IN ('issued','re-issued')`
- `isVisaIssued` = `visaSubmission.status = 'issued'`
- `isVisaSubmitted` = `visaSubmission.status = 'submitted'`
- `isVisaCancelled` = `visaSubmission.status = 'cancelled'`
- `isFingerprintApproved` = `fingerprintDetail.status = 'approved'`

### Fix: Two-Branch Approach in `applyPassengerStatus()`

**File:** `app/Queries/BookingPassengerQuery.php` (lines 295-302)

Replace the current method with:

```php
protected function applyPassengerStatus(Request $request): static
{
    if (! $request->filled('passenger_status')) {
        return $this;
    }

    $statusId = $request->input('passenger_status');
    $status = PassengerStatus::find($statusId);

    if (! $status) {
        return $this;
    }

    // BRANCH 1: Manual statuses — stored directly in passenger_status_id
    if (in_array($status->name, Passenger::MANUAL_STATUSES)) {
        $this->query->where('passenger_status_id', $statusId);
        return $this;
    }

    // BRANCH 2: Computed statuses — replicate getComputedStatusAttribute in SQL
    $this->applyComputedStatusFilter($status->name);
    return $this;
}
```

**Add three new private methods:**

```php
private function applyComputedStatusFilter(string $statusName): void
{
    $this->query->where(function ($query) use ($statusName) {
        match ($statusName) {
            'Ticket Issued' => $query
                ->where(fn ($q) => $this->whereTicketIssued($q))
                ->whereHas('visaSubmission', fn ($q) => $q->where('status', VisaStatus::ISSUED->value)),

            'Processing' => $query
                ->whereHas('visaSubmission', fn ($q) => $q->where('status', VisaStatus::CANCELLED->value)),

            'Ticket Issued before Visa' => $query
                ->where(fn ($q) => $this->whereTicketIssued($q))
                ->where(fn ($q) => $q->whereDoesntHave('visaSubmission', fn ($q) => $q->where('status', VisaStatus::ISSUED->value)))
                ->where(fn ($q) => $q->whereDoesntHave('visaSubmission', fn ($q) => $q->where('status', VisaStatus::CANCELLED->value))),

            'Visa Issued' => $query
                ->where(fn ($q) => $this->whereTicketNotIssued($q))
                ->whereHas('visaSubmission', fn ($q) => $q->where('status', VisaStatus::ISSUED->value)),

            'Visa Submitted' => $query
                ->where(fn ($q) => $this->whereTicketNotIssued($q))
                ->where(fn ($q) => $q->whereDoesntHave('visaSubmission', fn ($q) => $q->where('status', VisaStatus::ISSUED->value)))
                ->whereHas('visaSubmission', fn ($q) => $q->where('status', VisaStatus::SUBMITTED->value)),

            'Fingerprint Done' => $query
                ->where(fn ($q) => $this->whereTicketNotIssued($q))
                ->where(fn ($q) => $q->whereDoesntHave('visaSubmission', fn ($q) => $q->whereIn('status', [
                    VisaStatus::SUBMITTED->value,
                    VisaStatus::ISSUED->value,
                ])))
                ->whereHas('fingerprintDetail', fn ($q) => $q->where('status', FingerprintStatus::APPROVED->value)),

            default => null,
        };
    });
}

private function whereTicketIssued($query): void
{
    $query->where(fn ($q) => $q
        ->whereIn('passengers.ticket_status', ['issued', 're-issued'])
        ->orWhereHas('latestIssuedTicket', fn ($iq) => $iq->whereIn('status', ['issued', 're-issued']))
        ->orWhereHas('allIssuedTickets', fn ($iq) => $iq->where('issue_type', 'pending_outbound')->whereIn('status', ['issued', 're-issued']))
    );
}

private function whereTicketNotIssued($query): void
{
    $query->where(fn ($q) => $q
        ->whereNotIn('passengers.ticket_status', ['issued', 're-issued'])
        ->whereDoesntHave('latestIssuedTicket', fn ($iq) => $iq->whereIn('status', ['issued', 're-issued']))
        ->whereDoesntHave('allIssuedTickets', fn ($iq) => $iq->where('issue_type', 'pending_outbound')->whereIn('status', ['issued', 're-issued']))
    );
}
```

**New imports to add at the top of `BookingPassengerQuery.php`:**

```php
use App\Enums\FingerprintStatus;
use App\Enums\VisaStatus;
```

### Why This Is Safe

- **Manual statuses:** Handled by Branch 1 with the existing `where('passenger_status_id', $id)`. Returns early before any computed-status logic runs. Identical to the current working behavior.
- **Computed statuses:** Handled by Branch 2 with SQL that replicates the exact priority chain from `getComputedStatusAttribute()`. Only runs for non-manual status names.
- **No overlap:** `Passenger::MANUAL_STATUSES` is `['Hold', 'Cancel', 'Delivered', 'Ticket Refund Done', 'Departure Done']`. The computed status names (`Processing`, `Fingerprint Done`, `Visa Submitted`, `Visa Issued`, `Ticket Issued`, `Ticket Issued before Visa`) are disjoint from this list.

---

## Issue 2: Markup Tooltip Not Showing Profit Breakdown

### Root Cause

The profit breakdown data is nested inside `ticket_data` in the API response.
In `BookingController::computeTicketData()` (line 706):

```php
'profit_breakdown' => $profitService->getPassengerProfitBreakdown($p),
```

So the JSON structure is:

```json
{
  "profit": 100,
  "visa_data": { ... },
  "ticket_data": {
    "profit_breakdown": {
      "visa_profit": 50,
      "ticket_profit": 30,
      ...
    }
  }
}
```

But the Blade tooltip (lines 617-625) references `p.profit_breakdown` at the
**top level**, where it does not exist. All values fall through to `0`.

Line 617 also references `p.visa_data?.visa?.profit` which does not exist —
the `visa` object in `computeVisaData()` has no `profit` field.

### Fix: Correct Property Path in Tooltip

**File:** `resources/views/bookings/index.blade.php` (lines 617-625)

Change every `p.profit_breakdown` to `p.ticket_data?.profit_breakdown` and
remove the dead `p.visa_data?.visa?.profit` fallback.

**Before (lines 617-625):**

```html
<div class="flex justify-between"><span>Visa Profit</span><span x-text="$currency(p.visa_data?.visa?.profit ?? p.profit_breakdown?.visa_profit ?? 0, 2, p.pass_booking_rate)"></span></div>
<div class="flex justify-between"><span>Ticket Profit</span><span x-text="$currency(p.profit_breakdown?.ticket_profit ?? 0, 2, p.pass_booking_rate)"></span></div>
<div class="flex justify-between"><span>Additional Ticket</span><span x-text="$currency(p.profit_breakdown?.additional_ticket_profit ?? 0, 2, p.pass_booking_rate)"></span></div>
<div class="flex justify-between"><span>Re-Issue Profit</span><span x-text="$currency(p.profit_breakdown?.re_issue_profit ?? 0, 2, p.pass_booking_rate)"></span></div>
<div class="flex justify-between"><span>Refund Profit</span><span x-text="$currency(p.profit_breakdown?.refund_profit ?? 0, 2, p.pass_booking_rate)"></span></div>
<div class="flex justify-between text-red-300"><span>Re-Issue Cost</span><span x-text="'-' + $currency(p.profit_breakdown?.re_issue_cost ?? 0, 2, p.pass_booking_rate)"></span></div>
<div class="flex justify-between"><span>Service Charge</span><span x-text="$currency(p.profit_breakdown?.service_charge ?? 0, 2, p.pass_booking_rate)"></span></div>
<div class="border-t border-slate-600 my-1 pt-1 flex justify-between font-semibold">
    <span>Total</span><span x-text="$currency(p.profit_breakdown?.total ?? 0, 2, p.pass_booking_rate)"></span>
</div>
```

**After:**

```html
<div class="flex justify-between"><span>Visa Profit</span><span x-text="$currency(p.ticket_data?.profit_breakdown?.visa_profit ?? 0, 2, p.pass_booking_rate)"></span></div>
<div class="flex justify-between"><span>Ticket Profit</span><span x-text="$currency(p.ticket_data?.profit_breakdown?.ticket_profit ?? 0, 2, p.pass_booking_rate)"></span></div>
<div class="flex justify-between"><span>Additional Ticket</span><span x-text="$currency(p.ticket_data?.profit_breakdown?.additional_ticket_profit ?? 0, 2, p.pass_booking_rate)"></span></div>
<div class="flex justify-between"><span>Re-Issue Profit</span><span x-text="$currency(p.ticket_data?.profit_breakdown?.re_issue_profit ?? 0, 2, p.pass_booking_rate)"></span></div>
<div class="flex justify-between"><span>Refund Profit</span><span x-text="$currency(p.ticket_data?.profit_breakdown?.refund_profit ?? 0, 2, p.pass_booking_rate)"></span></div>
<div class="flex justify-between text-red-300"><span>Re-Issue Cost</span><span x-text="'-' + $currency(p.ticket_data?.profit_breakdown?.re_issue_cost ?? 0, 2, p.pass_booking_rate)"></span></div>
<div class="flex justify-between"><span>Service Charge</span><span x-text="$currency(p.ticket_data?.profit_breakdown?.service_charge ?? 0, 2, p.pass_booking_rate)"></span></div>
<div class="border-t border-slate-600 my-1 pt-1 flex justify-between font-semibold">
    <span>Total</span><span x-text="$currency(p.ticket_data?.profit_breakdown?.total ?? 0, 2, p.pass_booking_rate)"></span>
</div>
```

### Performance Impact: None

The data is already loaded in the same `passengersList` JS object from the same
AJAX response. This is a property path correction only. The tooltip is only
rendered on hover (`@mouseenter`/`@mouseleave`). Zero extra data fetching, zero
extra computation.

---

## Files to Modify

| File | Lines | Change |
|------|-------|--------|
| `app/Queries/BookingPassengerQuery.php` | 295-302 | Rewrite `applyPassengerStatus()` with two-branch logic |
| `app/Queries/BookingPassengerQuery.php` | (new) | Add `applyComputedStatusFilter()`, `whereTicketIssued()`, `whereTicketNotIssued()` private methods |
| `app/Queries/BookingPassengerQuery.php` | 1-9 | Add `use App\Enums\FingerprintStatus` and `use App\Enums\VisaStatus` imports |
| `resources/views/bookings/index.blade.php` | 617-625 | Fix `p.profit_breakdown` to `p.ticket_data?.profit_breakdown`, remove dead `p.visa_data?.visa?.profit` fallback |

## Tests

| Test file | What it verifies |
|-----------|-----------------|
| `tests/Feature/PassengerStatusFilterTest.php` (new) | Filter returns correct results for manual statuses (Hold, Cancel) AND computed statuses (Visa Submitted, Ticket Issued, Processing, etc.) |

## Verification Steps

1. `php artisan test tests/Feature/PassengerStatusFilterTest.php` — new tests pass
2. `vendor/bin/pint` — code style clean
3. `php artisan test` — full suite passes
4. Manual: select each status from the Current Status dropdown on the passenger tab and confirm correct filtering
5. Manual: hover the Markup column and confirm tooltip shows profit breakdown values

---

## Issue 3: Passenger Index Loses Scroll Position After Row Update

### Root Cause

When a passenger row is updated (status change, visa submit/issue, ticket
confirm, remarks update, refund, re-issue, cancel), the page refreshes via
`reloadView()` or `location.reload()`. The scroll save/restore mechanism is
broken because the `x-ref="tableScroll"` attribute is never defined on any
element.

**Broken scroll-save code:**

| Location | Code | Problem |
|----------|------|---------|
| `reloadView()` (line 6844) | `this.$refs.tableScroll?.scrollTop ?? 0` | Ref doesn't exist, always saves `0` |
| `init()` (line 2980) | `this.$refs.tableScroll.scrollTop = ...` | Ref doesn't exist, restore never executes |
| `updatePassengerStatus()` (line 6958) | `document.querySelector('[x-ref="tableScroll"]')` | Selector returns `null`, saves `0` |
| `updateFingerprintLocation()` (line 6996) | `document.querySelector('[x-ref="tableScroll"]')` | Selector returns `null`, saves `0` |

**Handlers that bypass `reloadView()` entirely (zero scroll save):**

| Handler | Line | Refresh mechanism |
|---------|------|-------------------|
| `handleRefundSubmit()` | 5646 | `setTimeout(() => location.reload(), 800)` |
| `handleReIssueSubmit()` | 5828 | `setTimeout(() => location.reload(), 800)` |
| `handleTicketFareSubmit()` re-issue path | 6048 | `setTimeout(() => location.reload(), 600)` |
| `submitCancelPassenger()` | 6909 | `window.location.reload()` |

**All handlers using `reloadView()` (broken scroll save):**

| Handler | Line |
|---------|------|
| `handleVisaSubmit()` | 3749 |
| `handleVisaIssue()` | 3834 |
| `handleVisaResubmit()` | 3947 |
| `handleVisaCancel()` | 3999 |
| `handleVisaEdit()` | 4137 |
| `toggleTicketHold()` | 4373 |
| `confirmTickets()` | 4690 |
| `updateRemarks()` | 4735 |
| `handleTicketFareSubmit()` normal path | 6201 |
| `handleCancelSubmit()` | 6835 |

**The scrollable container** is at line 526:
```html
<div class="overflow-auto flex-1 min-h-0">
```
It has no `x-ref`. The sticky header inside it (line 528) has
`sticky top-0 z-10` which confirms this is the scroll container.

### Fix: Convert Action Handlers to AJAX Refresh

Replace full page reloads with AJAX data refresh via `loadPassengerData()`.
This eliminates the scroll loss entirely (no reload = no scroll reset) and
is significantly more performant (~100-300ms vs ~1-3s for full reload).

#### Step 1: Add scroll save/restore to `loadPassengerData()`

**File:** `resources/views/bookings/index.blade.php` (line 3376)

Add scroll position preservation around the data fetch:

```javascript
async loadPassengerData() {
    // Save scroll position before re-render
    const scrollContainer = this.$refs.tableScroll;
    const savedScroll = scrollContainer ? scrollContainer.scrollTop : 0;

    this.passengersLoading = true;
    try {
        // ... existing fetch + params logic (unchanged) ...
        this.passengersList = json.data;
        // ... rest of existing logic (unchanged) ...
    } catch (e) {
        console.error('Failed to load passenger data', e);
    } finally {
        this.passengersLoading = false;

        // Restore scroll position after Alpine re-renders the x-for
        this.$nextTick(() => {
            if (scrollContainer) {
                scrollContainer.scrollTop = savedScroll;
            }
        });
    }
},
```

#### Step 2: Add `x-ref="tableScroll"` to the scrollable container

**File:** `resources/views/bookings/index.blade.php` (line 526)

```html
<!-- Before -->
<div class="overflow-auto flex-1 min-h-0">

<!-- After -->
<div x-ref="tableScroll" class="overflow-auto flex-1 min-h-0">
```

#### Step 3: Convert standalone functions to AJAX

**`updatePassengerStatus()`** (lines 6946-6968):

Replace `window.location.reload()` with `loadPassengerData()`:

```javascript
function updatePassengerStatus(passengerId, statusId, selectEl) {
    fetch(`/passengers/${passengerId}/status`, {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json',
        },
        body: JSON.stringify({ status: statusId }),
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Access Alpine component and refresh via AJAX
            const component = Alpine.$data(selectEl.closest('[x-data]'));
            if (component && typeof component.loadPassengerData === 'function') {
                component.loadPassengerData();
            }
        } else {
            alert(data.message || 'Failed to update status');
        }
    })
    .catch(error => {
        console.error('Error updating status:', error);
        alert('An error occurred while updating status');
    });
}
```

**`updateFingerprintLocation()`** (lines 6970-7008):

Same pattern — replace `window.location.reload()` with AJAX refresh:

```javascript
// After successful fetch:
const component = Alpine.$data(element.closest('[x-data]'));
if (component && typeof component.loadPassengerData === 'function') {
    component.loadPassengerData();
}
```

#### Step 4: Convert `reloadView()` callers to `loadPassengerData()`

For each handler that currently calls `this.reloadView()`, replace with:

```javascript
// Before:
this.reloadView();

// After:
this.loadPassengerData();
```

Handlers to convert (10 total):

| Handler | Line |
|---------|------|
| `handleVisaSubmit()` | 3749 |
| `handleVisaIssue()` | 3834 |
| `handleVisaResubmit()` | 3947 |
| `handleVisaCancel()` | 3999 |
| `handleVisaEdit()` | 4137 |
| `toggleTicketHold()` | 4373 |
| `confirmTickets()` | 4690 |
| `updateRemarks()` | 4735 |
| `handleTicketFareSubmit()` normal path | 6201 |
| `handleCancelSubmit()` | 6835 |

#### Step 5: Convert `location.reload()` callers to `loadPassengerData()`

These handlers bypass `reloadView()` and call `location.reload()` directly:

**`handleRefundSubmit()`** (line 5646):
```javascript
// Before:
setTimeout(() => location.reload(), 800);

// After:
this.loadPassengerData();
```

**`handleReIssueSubmit()`** (line 5828):
```javascript
// Before:
setTimeout(() => location.reload(), 800);

// After:
this.loadPassengerData();
```

**`handleTicketFareSubmit()` re-issue path** (line 6048):
```javascript
// Before:
setTimeout(() => location.reload(), 600);

// After:
this.loadPassengerData();
```

**`submitCancelPassenger()`** (line 6909):
```javascript
// Before:
window.location.reload();

// After:
this.loadPassengerData();
```

#### Step 6: Clean up dead scroll code

After converting all handlers to AJAX:

1. **Remove `reloadView()` method** (lines 6844-6847) — no longer called
2. **Remove scroll restore in `init()`** (lines 2980-2988) — no page reloads to restore from
3. **Remove broken `sessionStorage` saves** in standalone functions — replaced by AJAX

### Why This Is Better Than Fix 1 (x-ref Only)

| | Fix 1 (x-ref only) | Fix 3 (AJAX conversion) |
|---|---|---|
| Scroll | Saved/restored (if ref works) | Never lost |
| Speed | ~1-3s (full page reload) | ~100-300ms (AJAX only) |
| Network | Re-downloads entire HTML | Single small JSON response |
| DOM | Full teardown + rebuild | Only table rows re-render |
| UX | Visible page flicker | Seamless update |

### Files to Modify

| File | Change |
|------|--------|
| `resources/views/bookings/index.blade.php` line 526 | Add `x-ref="tableScroll"` |
| `resources/views/bookings/index.blade.php` lines 3376-3429 | Add scroll save/restore to `loadPassengerData()` |
| `resources/views/bookings/index.blade.php` lines 3749, 3834, 3947, 3999, 4137, 4373, 4690, 4735, 6201, 6835 | Replace `this.reloadView()` with `this.loadPassengerData()` |
| `resources/views/bookings/index.blade.php` lines 5646, 5828, 6048, 6909 | Replace `location.reload()` / `window.location.reload()` with `this.loadPassengerData()` |
| `resources/views/bookings/index.blade.php` lines 6946-6968 | Convert `updatePassengerStatus()` to AJAX refresh |
| `resources/views/bookings/index.blade.php` lines 6970-7008 | Convert `updateFingerprintLocation()` to AJAX refresh |
| `resources/views/bookings/index.blade.php` lines 6844-6847 | Remove `reloadView()` method |
| `resources/views/bookings/index.blade.php` lines 2980-2988 | Remove scroll restore from `init()` |

### Verification Steps

1. Manual: update a passenger status (e.g., set to "Hold") — table should refresh in place without scrolling to top
2. Manual: submit/issue a visa — table should refresh in place
3. Manual: confirm tickets — table should refresh in place
4. Manual: update remarks — table should refresh in place
5. Manual: refund/re-issue a ticket — table should refresh in place
6. Manual: cancel a passenger — table should refresh in place
7. Manual: scroll to page 3, perform an update — confirm still on page 3 at the same scroll position
8. Manual: use filters — confirm scroll resets to top (expected behavior for filter changes)

---

## Issue 4: Computed Status Filter Mismatches Displayed Status

### Symptom

After a computed status changes to another computed status (e.g., "Visa Submitted" → "Visa Issued"), or from computed to manual (e.g., "Visa Issued" → "Delivered"):

- Filtering by the **old** status still shows the passenger
- Filtering by the **new/current** status does **not** show the passenger

Both symptoms can happen simultaneously.

### Root Cause

**Two separate bugs, both in `BookingPassengerQuery.php`:**

#### Bug A: Stale `passengers.ticket_status` column (computed→computed issue)

The `passengers.ticket_status` enum column is set to `'issued'` when a ticket is
created (`TicketIssueController:89`, `TicketRequestController:564`), but **never
reset** when a ticket is refunded or deleted. No code sets it back to `'pending'`
or `null`.

The SQL `whereTicketIssued()` and `whereTicketNotIssued()` methods check this
stale column:

```php
// whereTicketIssued — OR logic, column alone can make it true
->whereIn('passengers.ticket_status', ['issued', 're-issued'])
->orWhereHas('latestIssuedTicket', ...)

// whereTicketNotIssued — AND logic, column alone can make it false
->whereNotIn('passengers.ticket_status', ['issued', 're-issued'])
->whereDoesntHave('latestIssuedTicket', ...)
```

But the **display** (`computeTicketData:698`) computes `ticket_status` from the
`allIssuedTickets` relationship, NOT the column:

```php
'ticket_status' => $p->allIssuedTickets
    ->filter(fn ($t) => is_null($t->issue_type) || $t->issue_type === 'regular')
    ->sortByDesc('id')
    ->first()?->status ?? null,
```

And the JS `getComputedStatusName()` uses this computed value (line 3571):
```javascript
const ticketStatus = row.ticket_status; // from computeTicketData, not column
```

**Concrete scenario:**

1. Passenger: visa submitted, ticket issued → `passengers.ticket_status = 'issued'`
2. Ticket is refunded → IssuedTicket status becomes 'refunded' (or deleted)
3. `passengers.ticket_status` is still `'issued'` (stale, never reset)
4. Display: `computeTicketData` → no active issued ticket → `ticket_status = null` → not issued → computes "Visa Submitted"
5. SQL "Visa Submitted" filter: `whereTicketNotIssued` → checks column `'issued'` → FAILS → passenger NOT shown
6. SQL "Ticket Issued" filter: `whereTicketIssued` → checks column `'issued'` → PASSES → passenger IS shown

**Result:** Display shows "Visa Submitted" but filter shows "Ticket Issued".

#### Bug B: No `whereNull('passenger_status_id')` guard (computed→manual issue)

`applyComputedStatusFilter()` does not exclude passengers whose
`passenger_status_id` points to a manual status. A passenger manually set to
"Delivered" but with visa=issued would match BOTH the "Delivered" filter AND the
computed "Visa Issued" filter.

**Concrete scenario:**

1. Passenger: visa issued, no ticket → computed status "Visa Issued"
2. Admin manually sets status to "Delivered" → `passenger_status_id = <Delivered ID>`
3. Display: `isManualStatus()` returns true → shows "Delivered"
4. SQL "Visa Issued" filter: `applyComputedStatusFilter` checks relationships → visa IS issued → MATCHES
5. SQL "Delivered" filter: `where('passenger_status_id', $deliveredId)` → MATCHES

**Result:** Passenger appears in both "Delivered" AND "Visa Issued" filters, but display shows "Delivered".

### Additional Minor Bug: "Fingerprint Done" Filter Too Broad

The PHP priority chain returns "Processing" for cancelled visas (condition 2)
before reaching "Fingerprint Done" (condition 6). So a passenger with a
cancelled visa + approved fingerprint would display "Processing", NOT "Fingerprint
Done".

But the SQL "Fingerprint Done" filter doesn't exclude cancelled visas:

```php
'Fingerprint Done' => $query
    ->where(fn ($q) => $this->whereTicketNotIssued($q))
    ->where(fn ($q) => $q->whereDoesntHave('visaSubmission', fn ($q) => $q->whereIn('status', [
        VisaStatus::SUBMITTED->value,
        VisaStatus::ISSUED->value,
        // ← Missing: VisaStatus::CANCELLED->value
    ])))
    ->whereHas('fingerprintDetail', fn ($q) => $q->where('status', FingerprintStatus::APPROVED->value)),
```

If the latest visa is 'cancelled', `whereDoesntHave visa IN (submitted, issued)`
passes (cancelled is not in the list). The passenger matches "Fingerprint Done"
in SQL but displays "Processing".

### Fix

#### Step 1: Remove stale column from `whereTicketIssued()` and `whereTicketNotIssued()`

**File:** `app/Queries/BookingPassengerQuery.php` (lines 361-377)

Remove the `passengers.ticket_status` column checks. Use only relationship-based
checks to match the display logic (`computeTicketData` + `getComputedStatusName`).

```php
private function whereTicketIssued($query): void
{
    $query->where(fn ($q) => $q
        ->orWhereHas('latestIssuedTicket', fn ($iq) => $iq->whereIn('status', ['issued', 're-issued']))
        ->orWhereHas('allIssuedTickets', fn ($iq) => $iq->where('issue_type', 'pending_outbound')->whereIn('status', ['issued', 're-issued']))
    );
}

private function whereTicketNotIssued($query): void
{
    $query->where(fn ($q) => $q
        ->whereDoesntHave('latestIssuedTicket', fn ($iq) => $iq->whereIn('status', ['issued', 're-issued']))
        ->whereDoesntHave('allIssuedTickets', fn ($iq) => $iq->where('issue_type', 'pending_outbound')->whereIn('status', ['issued', 're-issued']))
    );
}
```

**Why safe:** The `latestIssuedTicket` relationship uses `ofMany(['id' => 'MAX'])`
with the same `issue_type = null OR 'regular'` filter that `computeTicketData()`
uses. The `allIssuedTickets` with `pending_outbound` matches
`computePendingOutboundTicket()`. Both sides check the same data via
relationships.

#### Step 2: Add `whereNull('passenger_status_id')` to `applyComputedStatusFilter()`

**File:** `app/Queries/BookingPassengerQuery.php` (line 325)

Add the guard at the top of the method:

```php
private function applyComputedStatusFilter(string $statusName): void
{
    $this->query->whereNull('passenger_status_id')
        ->where(function ($query) use ($statusName) {
            match ($statusName) {
                // ... existing cases unchanged
            };
        });
}
```

**Why safe:** Computed statuses always have `passenger_status_id = NULL` (enforced
by `syncComputedStatus()`). Adding `whereNull` simply ensures the SQL filter
matches the display logic: if `passenger_status_id` points to a manual status,
`getDisplayStatusAttribute()` returns the manual name, not the computed value.

#### Step 3: Add missing cancelled visa exclusion to "Fingerprint Done"

**File:** `app/Queries/BookingPassengerQuery.php` (line 350)

Add `VisaStatus::CANCELLED->value` to the `whereIn` list:

```php
'Fingerprint Done' => $query
    ->where(fn ($q) => $this->whereTicketNotIssued($q))
    ->where(fn ($q) => $q->whereDoesntHave('visaSubmission', fn ($q) => $q->whereIn('status', [
        VisaStatus::SUBMITTED->value,
        VisaStatus::ISSUED->value,
        VisaStatus::CANCELLED->value,  // ← ADD THIS
    ])))
    ->whereHas('fingerprintDetail', fn ($q) => $q->where('status', FingerprintStatus::APPROVED->value)),
```

**Why safe:** The PHP priority chain handles cancelled visas at condition 2
(`$isVisaCancelled → "Processing"`) before reaching condition 6 (`"Fingerprint
Done"`). The SQL must mirror this priority. A cancelled visa means the passenger
is in "Processing", not "Fingerprint Done".

### Files to Modify

| File | Lines | Change |
|------|-------|--------|
| `app/Queries/BookingPassengerQuery.php` | 325 | Add `whereNull('passenger_status_id')` to `applyComputedStatusFilter()` |
| `app/Queries/BookingPassengerQuery.php` | 327-358 | Add `VisaStatus::CANCELLED->value` to "Fingerprint Done" `whereIn` |
| `app/Queries/BookingPassengerQuery.php` | 361-377 | Remove `passengers.ticket_status` column checks from `whereTicketIssued()` and `whereTicketNotIssued()` |

### Tests to Add/Update

| Test | Scenario |
|------|----------|
| `test_computed_ticket_issued_excludes_refunded_ticket` | Passenger with refunded ticket (stale column) → filter "Ticket Issued" should NOT match |
| `test_computed_visa_submitted_excludes_refunded_ticket` | Passenger with refunded ticket (stale column) + visa submitted → filter "Visa Submitted" SHOULD match |
| `test_computed_filter_excludes_manual_status_override` | Passenger with manual status "Delivered" + visa issued → filter "Visa Issued" should NOT match |
| `test_fingerprint_done_excludes_cancelled_visa` | Passenger with cancelled visa + approved fingerprint → filter "Fingerprint Done" should NOT match |
| `test_fingerprint_done_includes_approved_without_visa` | Passenger with approved fingerprint, no visa → filter "Fingerprint Done" SHOULD match |

### Verification Steps

1. `php artisan test tests/Feature/PassengerStatusFilterTest.php` — all tests pass
2. `vendor/bin/pint` — code style clean
3. Manual test: Issue a ticket, then refund it. Confirm "Visa Submitted" filter shows the passenger and "Ticket Issued" filter does not.
4. Manual test: Set a passenger to "Delivered" manually. Confirm they do NOT appear in any computed status filter.
5. Manual test: Approve fingerprint for a passenger with cancelled visa. Confirm they appear in "Processing" not "Fingerprint Done".
6. Manual test: All original status filter tests from Issue 1 still pass.
