# Re-Issue Form Change Plan — Hide `fare_difference` / `other_costs`, Derive `service_charge` from `total_customer_payment`

> Status: PLAN (not implemented)
> Date: 2026-09-19 (v2 — edit-form legacy preservation + all gaps addressed)

## 1. Background — how it works now

### 1.1 `total_cost` (stored on `re_issued_tickets.total_cost`)

Create paths (identical logic):

* `app/Http/Controllers/ReIssueController.php:147-153` (`store`, direct re-issue from booking page)
* `app/Http/Controllers/TicketRequestController.php:274-280` (`processReIssue`, request approval flow)

```php
$refundedNetFare = $wasRefunded
    ? (float) ($issuedTicket->latestRefundedTicket?->net_fare ?? $issuedTicket->net_fare ?? 0)
    : 0;

$rawCost = (float) $reIssueData['re_issue_charge']
    + (float) $reIssueData['fare_difference']
    + (float) $reIssueData['other_costs']
    + $refundedNetFare;

$totalCost = $rawCost - ($reIssueData['refund_adjustment_amount'] ?? 0);
$reIssuedTicket->update(['total_cost' => round($totalCost, 6)]);
```

Edit path recalculates the same way:

* `app/Http/Controllers/TicketIssueController.php:265-272` (`update` when `issuedTicket->status === 're-issued'`)

```php
$totalCost = (float) $reIssueCharge + (float) $fareDifference + (float) $otherCosts + $refundedNetFare - (float) $refundAdjustment;
```

### 1.2 `total_customer_payment` + `service_charge` today

* `total_customer_payment` column = user input when `payment_by=customer`, else `0`
  (`ReIssueController.php:136-138`, `TicketRequestController.php:263-265`, `TicketIssueController.php:294-296`).
* `service_charge` = user input (always `required|numeric|min:0` on create:
  `ReIssueController.php:59-63`, `TicketRequestController.php:156-160`;
  edit path all `nullable|numeric` in `TicketIssueController.php:201-205`).
* Invoice impact (create): `totalCustomerPayment = totalCost + service_charge`, added to the
  invoice only when `payment_by==customer OR wasRefunded`
  (`ReIssueController.php:164-165`, `TicketRequestController.php:297-298`).
* Edit invoice impact: delta of old vs new `total_customer_payment` with reason
  `re_issue_edited` (`TicketIssueController.php:304-315,380-390`).
* Frontend mirrors it:
  * `resources/views/re-issues/confirmation.blade.php:840-883` `updateTotals()`:
    `totalCost = reIssue + difference + other + refundedNetFare - refundAdj`,
    `totalPayment = totalCost + service` (service editable, total readonly).
  * `resources/views/bookings/index.blade.php:5873-5901` `recalcReIssueTotals()`: same formula
    with Alpine `reIssueForm`.
  * `recalcFareDifference` auto-fills fare difference from net-fare change:
    `confirmation.blade.php:642-648`, `index.blade.php:5903-5911` (called from net-fare inputs
    and `syncFareFields`).

Consumers (no change needed, must keep working):

* `app/Services/ProfitCalculationService.php:420-432,497-511`:
  `re_issue_profit = sum(service_charge) where payment_by=CUSTOMER`;
  `re_issue_cost = sum(total_cost) where payment_by=COMPANY`.
* `app/Services/CostTrackingService.php:53-71`: ticket cost uses
  `latestReIssuedTicket->net_fare`, plus `total_cost` only when `payment_by==company`.
* `resources/views/bookings/show.blade.php:1420` history display computes
  `re_issue_charge + fare_difference + other_costs + ...` for display of stored rows.
* `app/Console/Commands/BackfillProfitData.php:52-65` backfills `total_cost` where 0.

Model/columns already exist — no migration needed:

* `app/Models/ReIssuedTicket.php:15-48` (`re_issue_charge`, `fare_difference`, `other_costs`,
  `service_charge`, `total_cost`, `total_customer_payment` fillable + `decimal:6` casts).

### 1.3 Submit paths (four total)

| # | Path | Method | Route | Controller |
|---|------|--------|-------|------------|
| 1 | `handleReIssueSubmit()` `index.blade.php:5954` | POST | `/bookings/{b}/passengers/{p}/re-issue` | `ReIssueController::store` |
| 2 | `confirmProcess()` `confirmation.blade.php:954` | PUT | `/ticket-requests/{id}/process-reissue` | `TicketRequestController::processReIssue` |
| 3 | `handleTicketFareSubmit()` `index.blade.php:6148` (when `isEditingReIssued`) | PUT | `/bookings/{b}/passengers/{p}/ticket-edit` | `TicketIssueController::edit` |
| 4 | `openReIssueModal()` + `populateReIssueEditForm()` `index.blade.php:5444` (data loading) | — | — | — |

## 2. Goal (confirmed with user)

1. Hide `fare_difference` + `other_costs` in **all forms** (`re-issues/confirmation.blade.php`,
   `bookings/index.blade.php` re-issue modal, `bookings/index.blade.php` ticket-fare modal).
2. **New saves**: these fields default to `0`. The user never inputs them.
3. **Edit saves**: legacy non-zero values are **preserved** from the existing record and continue
   to contribute to `total_cost`. The total_cost calculation formula is unchanged — only the
   source of `fare_difference`/`other_costs` changes (from user input to stored legacy value).
4. Customer flow inversion: when `payment_by === 'customer'`, the user inputs
   **`total_customer_payment`**, and **`service_charge` is auto-calculated** as
   `service_charge = total_customer_payment - total_cost` (readonly).
5. Backend validation: make `fare_difference` / `other_costs` optional with default `0`.

## 3. New calculation spec (source of truth)

**Formula is identical in create and edit paths:**

```text
rawCost         = re_issue_charge + fare_difference + other_costs + refundedNetFare
total_cost      = rawCost - refund_adjustment_amount   (round to 6)
```

Only the **source** of `fare_difference`/`other_costs` differs:

| Field | Create | Edit |
|-------|--------|------|
| `fare_difference` | `0` (no user input) | Existing record's value (preserved) |
| `other_costs` | `0` (no user input) | Existing record's value (preserved) |

Customer (`payment_by === 'customer'`):

```text
INPUT:  total_customer_payment  (required, numeric, must be >= total_cost)
DERIVE: service_charge = total_customer_payment - total_cost  (round 6, >= 0)
STORE:  total_customer_payment = input, service_charge = derived, total_cost as above
INVOICE += total_customer_payment   (= total_cost + service_charge, same semantics as today)
```

Non-customer (`airline|employee|company`, not `wasRefunded`):

```text
service_charge = 0
total_customer_payment column = 0
INVOICE: no change
```

`wasRefunded` edge (ticket was `refunded` before re-issue):

* `payment_by==customer` → customer derivation above (invoice `+= total_customer_payment`).
* `wasRefunded && payment_by!=customer` → `service_charge = 0`, stored
  `total_customer_payment = 0`, **no invoice impact** (revised per user 2026-09-19;
  company bears the cost). The refund-adjustment Payment/Voucher +
  `decreaseRefundPayable` flow still runs in this sub-case. Payment option UI is
  already forced to `refund_adjustment` in this sub-case
  (`confirmation.blade.php:896-899`).

New server validation (both create AND edit): reject `total_customer_payment < total_cost` with 422
(`Total customer payment must be at least total cost.`), preventing negative derived service.

Unchanged validations: `refund_adjustment_amount <= rawCost` and
`<= passenger.refund_payable`.

## 4. Backend changes

### 4.1 `ReIssueController::store` (`app/Http/Controllers/ReIssueController.php:39-74,124-165`)

1. Validation — replace required fare/other/service with:
   ```php
   'fare_difference' => 'nullable|numeric',
   'other_costs' => 'nullable|numeric|min:0',
   'service_charge' => 'nullable|numeric|min:0', // accepted but ignored when customer (derived)
   'total_customer_payment' => 'required_if:payment_by,customer|numeric|min:0',
   ```
2. Defaults in `$reIssueData` (`:124-139`):
   ```php
   'fare_difference' => (float) ($validated['fare_difference'] ?? 0),
   'other_costs' => (float) ($validated['other_costs'] ?? 0),
   'service_charge' => 0, // placeholder, overwritten after derivation for customer path
   ```
3. After `$totalCost` is computed, derive (replaces `:164-165` logic):
   ```php
   if (($validated['payment_by'] ?? null) === 'customer' || $wasRefunded) {
       if (($validated['payment_by'] ?? null) === 'customer') {
           $inputTotal = (float) $validated['total_customer_payment'];
           if ($inputTotal < $totalCost) {
               throw new \InvalidArgumentException('Total customer payment must be at least total cost.');
           }
           $serviceCharge = round($inputTotal - $totalCost, 6);
           $totalCustomerPayment = $inputTotal;
       } else {
           // wasRefunded, non-customer: company bears the cost — no stored
           // customer payment, no invoice impact (existing `> 0` guard skips it).
           $serviceCharge = 0;
           $totalCustomerPayment = 0;
       }
       $reIssuedTicket->update([
           'service_charge' => $serviceCharge,
           'total_customer_payment' => round($totalCustomerPayment, 6),
       ]);
       // ... refund_adjustment payment/voucher + invoice += $totalCustomerPayment (unchanged)
   }
   ```
   Follow the existing create-then-`update` style (`:141` create, `:153` update `total_cost`).

### 4.2 `TicketRequestController::processReIssue` (`:154-185,:230-298`)

Mirror §4.1 exactly (same validation swap, same defaults, same derive-then-invoice).
Payload keys are identical so frontend can share logic.

### 4.3 `TicketIssueController::update` — edit re-issued ticket (`:200-209,:265-311`)

1. Validation already `nullable` — no rule change required; add a comment that
   `fare_difference`/`other_costs` are legacy (preserved from existing record, default 0).
2. Recalc (`:265-268`) — **preserve legacy values** (do NOT force 0):
   ```php
   $fareDifference = array_key_exists('fare_difference', $validated)
       ? (float) $validated['fare_difference']
       : (float) $latestRe->fare_difference;
   $otherCosts = array_key_exists('other_costs', $validated)
       ? (float) $validated['other_costs']
       : (float) $latestRe->other_costs;
   ```
   Then after `$totalCost`, if `$effectivePaymentBy === 'customer'`:
   ```php
   $inputTotal = array_key_exists('total_customer_payment', $validated)
       ? (float) $validated['total_customer_payment']
       : (float) $latestRe->total_customer_payment;
   if ($inputTotal < $totalCost) {
       DB::rollBack();
       return response()->json(['message' => 'Total customer payment must be at least total cost.'], 422);
   }
   $derivedService = round($inputTotal - $totalCost, 6);
   ```
   Store `'fare_difference' => $fareDifference, 'other_costs' => $otherCosts,
   'service_charge' => $derivedService ?? 0` in the `:288-302` update block.
   Invoice delta logic (`:304-315,380-390`, `'re_issue_edited'`) stays — it diffs old vs new
   `total_customer_payment`, which still works because the new value is the input total.
3. Legacy non-zero `fare_difference`/`other_costs` are preserved through the `?? $latestRe->...`
   fallback. New edits (from frontend) send `0` for these fields; when the key is absent from
   the request, the existing value is preserved.

## 5. Frontend — `resources/views/re-issues/confirmation.blade.php` (request-approval modal)

HTML (`~:220-285`):

* Hide `#fieldFareDifferenceSar/Bdt` (`:221-230`) + `#fieldOtherCostsSar/Bdt` (`:232-241`).
  Keep hidden inputs `inputFareDifference=0`, `inputOtherCosts=0` so the `confirmProcess()`
  payload shape and `syncCurrencyFields()` wrapper list don't break — or remove from DOM and
  hardcode 0 in JS (pick one; hidden inputs = smaller diff).
* `#fieldServiceCharge` (`:264-274`): make `inputServiceCharge` / `inputServiceChargeBdt`
  `readonly`, remove `oninput="...updateTotals()"`, apply readonly grey styling like
  `inputTotalCost` (`border-slate-200 bg-slate-50 text-slate-500`).
* `#fieldTotalPayment` (`:275-285`): invert — make `inputTotalPayment` / `inputTotalPaymentBdt`
  **editable** (remove `readonly`, add `oninput`, e.g. `handleTotalPaymentInput(); updateTotals()`).
  Keep visible only when `payment_by==='customer'` (existing `handlePaymentByChange:904-905`).
* `syncCurrencyFields()` (`:383-395`): remove `fieldFareDifferenceSar/Bdt` and
  `fieldOtherCostsSar/Bdt` from the `wrappers` array. Keep all other pairs.
* `syncReadonlyMirrors()` (`:405-425`): remove `inputFareDifference`/`inputFareDifferenceBdtSar`
  and `inputOtherCosts`/`inputOtherCostsBdtSar` from the `pairs` array. Keep all other pairs.

JS:

* `processConfirmation()` reset (`~:763-781`): set hidden fare/other to `0`; clear total-payment
  input (editable, empty) and service (readonly, empty).
* Deprecate `recalcFareDifference()` (`:642-648`): do NOT write fare difference from
  `newNetFare - originalTicketNetFare` anymore; leave at 0. Remove its calls in
  `syncFareFields()` (`:639`) and `inputNetFare oninput` (`:172`). Ticket select still updates
  selling/net/offer display only.
* Rewrite `updateTotals()` (`:840-883`):
  ```js
  var reIssue = parseFloat(inputReIssueCharge) || 0;
  var difference = 0, other = 0; // hidden, default 0
  var rawCost = reIssue + difference + other + currentRefundedNetFare;
  var refundAdj = ...; // unchanged
  var totalCost = rawCost - refundAdj; // -> inputTotalCost (readonly)
  if (isCustomer) {
      var inputTotal = parseFloat(inputTotalPayment) || 0;
      var service = Math.round((inputTotal - totalCost) * 1e6) / 1e6;
      inputServiceCharge = service >= 0 ? service : 0;
      // setCustomValidity on inputTotalPayment if inputTotal > 0 && inputTotal < totalCost
  } else { inputServiceCharge = 0; }
  // BDT mirrors for totalCost, serviceCharge, totalPayment
  ```
* New `handleTotalPaymentInput()`: SAR↔BDT conversion for total payment
  (mirror `handleFieldSarInput/BdtInput`), then `updateTotals()`.
* `handlePaymentByChange()` (`:885-910`): when non-customer also clear
  `inputTotalPayment` (set to `''`) and `inputTotalPaymentBdt` (set to `''`)
  (in addition to existing `:893-899` resets).
* `confirmProcess()` payload (`:957-977`): send `fare_difference: 0, other_costs: 0`,
  `total_customer_payment: parseFloat(inputTotalPayment)||0`,
  `service_charge: parseFloat(inputServiceCharge)||0` (derived; server re-derives as authority).
  Refund-adjustment guard (`:990`) keeps working with 0s.

## 6. Frontend — `resources/views/bookings/index.blade.php` (two Alpine modals, one `reIssueForm`)

Two HTML blocks share one `reIssueForm` object:

* A. Edit-in-ticketFare modal "Re-Issue Details" (`:1989-2162`): fare `:2066-2103`,
  service `:2104-2122`, total `:2145-2155`.
* B. "Re-Issue Ticket Modal" (`:2373-2550`): fare `:2454-2491`, service `:2492-2510`,
  total `:2533-2543`. Refunded-fare + total-cost readonly blocks (`:2511-2532`) unchanged.

For **both** blocks:

* Remove/hide fare-difference + other-costs SAR/BDT divs; keep state keys
  `fare_difference: 0, fare_difference_bdt: '', other_costs: 0, other_costs_bdt: ''`
  (do not delete keys — `recalcReIssueTotals` and submit payload reference them).
* Service charge inputs → `readonly`, remove
  `@input="handleReIssueSarInput('service_charge')..."`, apply readonly classes.
* Total payment (`total_payment` / `total_payment_bdt`) → **editable** with `@input` handlers
  (new `handleReIssueTotalPaymentSar/BdtInput` or reuse of generic with reversed recalc direction),
  `x-show` on `payment_by==='customer'` stays.

Alpine methods (`~:5849-5960`):

* `handleReIssueSarInput/BdtInput` (`:5849-5871`): keep for `re_issue_charge`,
  `refund_adjustment_amount`, new `total_payment`; unbind fare/other/service.
* Rewrite `recalcReIssueTotals()` (`:5873-5901`):
  ```js
  const rawCost = (parseFloat(f.re_issue_charge)||0)
      + (parseFloat(f.fare_difference)||0)   // legacy value preserved from populateReIssueEditForm
      + (parseFloat(f.other_costs)||0)        // legacy value preserved from populateReIssueEditForm
      + (parseFloat(f.refunded_net_fare)||0);
  // adj validation unchanged (:5881-5894)
  const totalCost = rawCost - adj;
  f.total_cost = totalCost; f.total_cost_bdt = ...;
  if (f.payment_by === 'customer') {
      const inputTotal = parseFloat(f.total_payment)||0;
      const svc = Math.round((inputTotal - totalCost)*1e6)/1e6;
      f.service_charge = svc >= 0 ? svc : 0;
      f.service_charge_bdt = rate>0 ? Math.round(f.service_charge*rate) : '';
      f.errors.total_payment = (inputTotal > 0 && inputTotal < totalCost)
          ? 'Total customer payment must be at least total cost.' : '';
      f.total_payment_bdt = ...; // from input, not derived
  } else { f.service_charge = 0; ... }
  ```
  Note: `fare_difference` and `other_costs` are read from the form state. For new re-issues
  they are `0`. For edits of legacy records, `populateReIssueEditForm` loads the stored values
  into the form state, so they contribute to `total_cost` correctly.
* Deprecate `recalcReIssueFareDifference()` (`:5903-5911`) → set `fare_difference=0`,
  `_bdt=''`, call `recalcReIssueTotals()`; remove ALL calls:
  * `:2337` — `@input="handleReIssueSarInput('net_fare'); recalcReIssueFareDifference()"`
  * `:2344` — BDT input equivalent
  * `:6660` — inside `handleReIssueTicketOptionChange()` (ticket selected)
  * `:6699` — inside `handleReIssueTicketOptionChange()` (fallback)
  After removal, `handleReIssueTicketOptionChange()` just updates selling/net/offer display.
* `handleReIssuePaymentByChange()` (`:5918-5927`): on non-customer also clear
  `total_payment` (set to `0`), `total_payment_bdt` (set to `''`), and errors.
* `populateReIssueEditForm()` (`~:5453+`): load legacy `fare_difference`, `other_costs` from
  existing re-issued ticket into form state. Load `total_customer_payment` into `total_payment`.
  ```js
  this.reIssueForm.fare_difference = re.fare_difference || 0;
  this.reIssueForm.other_costs = re.other_costs || 0;
  this.reIssueForm.service_charge = re.service_charge || 0;
  this.reIssueForm.total_payment = re.total_customer_payment || 0;
  // ... BDT mirrors ...
  this.recalcReIssueTotals();
  ```
* `resetReIssueEditFields()` (`~:5478`): reset fare/other to `0`, total_payment to `0`,
  service_charge to `0`. (No change needed — already does this.)
* `openReIssueModal()` (`~:5503`): For new re-issues, reset `fare_difference=0` and
  `other_costs=0`. When `re != null` (editing existing), load legacy values from `re`
  via `populateReIssueEditForm`. The `recalcReIssueTotals()` call at the end uses these
  values correctly.
* `handleReIssueSubmit()` validation (`:5959-5976`): drop `fare_difference` required (`:5969`);
  keep `fare_difference`/`other_costs`/`service_charge` error keys as `''`; add
  `payment_by==='customer' && (total_payment==='' || parseFloat(total_payment) < totalCost)` →
  `Total customer payment must be at least total cost`.
* `handleReIssueSubmit()` payload (`:5980+`): send `fare_difference: form.fare_difference || 0`,
  `other_costs: form.other_costs || 0`,
  `service_charge: derived, total_customer_payment: total_payment`.
* **`handleTicketFareSubmit()` (`:6148-6265`)** — **must apply same changes**:
  * Remove fare_difference required validation (`:6179`)
  * Update error initialization (`:6172-6176`) — keep keys, remove required assertion
  * Update payload (`:6244-6245`) — send `fare_difference: rf.fare_difference || 0`,
    `other_costs: rf.other_costs || 0`
  * Add `total_customer_payment < totalCost` validation when `payment_by === 'customer'`

### 6.1 `resources/views/bookings/show.blade.php:1420` history display

**Must update** to use stored `total_cost` instead of recomputing from components:

```js
// Before (wrong for new records):
const totalCost = (parseFloat(r.re_issue_charge) || 0)
    + (parseFloat(r.fare_difference) || 0)
    + (parseFloat(r.other_costs) || 0);

// After (correct for all records):
const totalCost = parseFloat(r.total_cost) || 0;
```

This ensures:
- Legacy records with non-zero fare/other display correctly (their `total_cost` was computed
  with those values).
- New records with fare/other = 0 display correctly (their `total_cost` was computed
  with the same formula).
- Refunded tickets with refund adjustments display correctly (the old formula ignored
  `refundedNetFare` and `refund_adjustment_amount`).

The profit line (`const profit = customerPayment - totalCost`) remains correct since
`customerPayment` is `total_customer_payment` from the database.

## 7. Tests (TDD per AGENTS.md)

Existing tests referencing these fields:

* `tests/Feature/ProfitCalculationServiceTest.php:439-440,472-473`
  (`fare_difference: 50, other_costs: 25`) — direct model creates testing profit math, keep.
* `tests/Unit/CostTrackingServiceTest.php:347-348,390-391`,
  `tests/Feature/PassengerCancellationServiceTest.php:576-577,619-620` — service-level, keep.
* `tests/Feature/BookingReIssueBdtResetTest.php:61-68` — asserts
  `reIssueForm.fare_difference_bdt / other_costs_bdt` reset strings exist; **must update**
  (remove `fare_difference_bdt` and `other_costs_bdt` assertions, keep `re_issue_charge_bdt`
  and `service_charge_bdt`).
* `tests/Feature/ReIssuedTicketObserverProfitTest.php` — uses `service_charge` for profit;
  keep as-is (service_charge is still stored, just derived).
* `tests/Feature/ReIssueEditRefundedNonCustomerTest.php`,
  `tests/Feature/ReIssueEditRefundPayableAdjustTest.php` — no assertions on target fields, keep.
* `tests/Feature/ProfitEffectiveDateComponentsTest.php:286-379` — uses `service_charge`;
  keep as-is.

New tests (write failing first):

1. `test_reissue_create_defaults_fare_difference_and_other_costs_to_zero` — POST without those
   keys → success; stored `fare_difference=0`, `other_costs=0`,
   `total_cost = re_issue_charge + refundedNetFare - refundAdj`. Cover both
   `ReIssueController@store` and `TicketRequestController@processReIssue`.
2. `test_customer_payment_derives_service_charge` —
   `payment_by=customer, re_issue_charge=100, total_customer_payment=150` →
   stored `service_charge=50`, `total_cost=100`, invoice `+=150`.
3. `test_customer_payment_below_cost_rejected` — `total_customer_payment < total_cost` → 422.
   Cover both create and edit paths.
4. `test_non_customer_forces_zero_service_and_payment` — `payment_by=company` →
   `service_charge=0`, payment col `0`, no invoice change.
5. `test_edit_reissue_preserves_legacy_fare_difference_and_other_costs` —
   Create with `fare_difference=50, other_costs=25`, then edit via
   `TicketIssueController@update` without sending those fields → stored values preserved,
   `total_cost` includes them.
6. `test_edit_reissue_recomputes_service_from_total_payment` —
   `TicketIssueController@update` with new `total_customer_payment` → re-derived service,
   invoice delta = new − old total.
7. Manual QA checklist (no JS unit harness in repo): customer flow SAR + BDT, non-customer flow,
   refund_adjustment flow, wasRefunded flow, edit flow with legacy non-zero record.

Verify per commit checklist: `php artisan test`, `vendor/bin/pint`, `npm run build`,
`docker compose config --quiet` (+ prod file), conventional commits, one logical change per commit.

## 8. Risks & open decisions

* Edit of legacy records with non-zero fare/other: **preserved** (backend fallback to
  `$latestRe->fare_difference`/`$latestRe->other_costs`). Frontend sends 0 for new forms;
  legacy values persist through edits unless explicitly changed.
* Pre-existing customer rows keep stored `service_charge`; only new saves derive it.
  Profit reports mix both until data ages out — math semantics identical, acceptable.
* BDT mode: total-payment is the conversion source; service BDT mirror derives from SAR service.
  Never allow direct BDT-service input (avoid double-source).
* Rounding: `round(...,6)` server-side, `Math.round(x*1e6)/1e6` in JS (existing convention).
* `wasRefunded` non-customer: decided 2026-09-19 — stored `total_customer_payment = 0`,
  no invoice impact; refund-adjustment Payment/Voucher + `refund_payable` consumption unchanged.

## 9. Implementation order

1. Backend: `ReIssueController` + `TicketRequestController` + tests 1–4.
2. Backend edit: `TicketIssueController` (preserve legacy, add 422 guard) + tests 5–6.
3. Frontend: `confirmation.blade.php` (§5).
4. Frontend: `index.blade.php` — re-issue modal + ticket-fare modal (§6) + `show.blade.php` history fix (§6.1).
5. Update `BookingReIssueBdtResetTest` + full suite + pint + build + docker config.
6. Commit per AGENTS.md.
