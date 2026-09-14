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
