# Re-Issue Form Change Plan — Hide `fare_difference` / `other_costs`, Derive `service_charge` from `total_customer_payment`

> Status: PLAN (not implemented)
> Date: 2026-09-19

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

## 2. Goal (confirmed with user)

1. Hide `fare_difference` + `other_costs` in **both** forms
   (`re-issues/confirmation.blade.php` + `bookings/index.blade.php` modals).
   Default `0`. Calculation stays the same (those terms are just 0).
2. Customer flow inversion: when `payment_by === 'customer'`, the user inputs
   **`total_customer_payment`**, and **`service_charge` is auto-calculated** as
   `service_charge = total_customer_payment - total_cost` (readonly).
3. Backend validation: make `fare_difference` / `other_costs` optional with default `0`.

## 3. New calculation spec (source of truth)

```text
refundedNetFare = wasRefunded ? latestRefunded.net_fare ?? issued.net_fare : 0
rawCost         = re_issue_charge + 0 + 0 + refundedNetFare
total_cost      = rawCost - refund_adjustment_amount   (round to 6)
```

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

* Keep the existing trigger (invoice impact when `payment_by==customer OR wasRefunded`).
* `payment_by==customer` → customer derivation above.
* `wasRefunded && payment_by!=customer` → `service_charge = 0`, invoice impact `= total_cost`.
  Payment option UI is already forced to `refund_adjustment` in this sub-case
  (`confirmation.blade.php:896-899`).

New server validation: reject `total_customer_payment < total_cost` with 422
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
           $serviceCharge = 0;
           $totalCustomerPayment = $totalCost; // wasRefunded non-customer
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
   `fare_difference`/`other_costs` are legacy (always 0 for new saves).
2. Recalc (`:265-268`) — force 0 for new edits:
   ```php
   $fareDifference = array_key_exists('fare_difference', $validated) ? (float) $validated['fare_difference'] : 0.0;
   $otherCosts = array_key_exists('other_costs', $validated) ? (float) $validated['other_costs'] : 0.0;
   ```
   Then after `$totalCost`, if `$effectivePaymentBy === 'customer'`:
   ```php
   $inputTotal = array_key_exists('total_customer_payment', $validated)
       ? (float) $validated['total_customer_payment']
       : (float) $latestRe->total_customer_payment;
   if ($inputTotal < $totalCost) { DB::rollBack(); return 422 ...; }
   $derivedService = round($inputTotal - $totalCost, 6);
   ```
   Store `'fare_difference' => $fareDifference, 'other_costs' => $otherCosts,
   'service_charge' => $derivedService ?? 0` in the `:288-302` update block.
   Invoice delta logic (`:304-315,380-390`, `'re_issue_edited'`) stays — it diffs old vs new
   `total_customer_payment`, which still works because the new value is the input total.
3. NOTE (release-notes item): forcing 0 wipes legacy non-zero `fare_difference`/`other_costs`
   on any edit. Alternative is preserve via `?? (float) $latestRe->...`. Recommended: force 0
   per the "default 0" requirement.

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
* `syncCurrencyFields()` (`:383-395`) + `syncReadonlyMirrors()` (`:405-425`): drop fare/other rows
  (or keep pointing at hidden inputs); keep total-payment editable pair + service readonly mirror.
  Service BDT mirror derives from SAR service (no direct BDT-service input).

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
  `inputTotalPayment/Bdt` (in addition to existing `:893-899` resets).
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
  const rawCost = (parseFloat(f.re_issue_charge)||0) + 0 + 0 + (parseFloat(f.refunded_net_fare)||0);
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
  Delete/reverse old `:5899` (`f.total_payment = totalCost + service`) — that direction no longer
  exists; `total_payment` is now the input.
* Deprecate `recalcReIssueFareDifference()` (`:5903-5911`) → set `fare_difference=0`,
  `_bdt=''`, call `recalcReIssueTotals()`; remove calls at `:2337,:2344,:6660,:6699`.
* `handleReIssuePaymentByChange()` (`:5918-5927`): on non-customer also clear
  `total_payment/_bdt` + errors.
* Init/reset (`~:4387,5484-5485,5558-5562`) + `populateReIssueEditForm` (`~:5453+`): defaults to 0;
  load stored `total_customer_payment` into `total_payment` input and derive service.
* `handleReIssueSubmit()` validation (`:5959-5976`): drop `fare_difference` required (`:5969`);
  keep `fare_difference`/`other_costs`/`service_charge` error keys as `''`; add
  `payment_by==='customer' && (total_payment==='' || parseFloat(total_payment) < totalCost)` →
  `Total customer payment must be at least total cost`. Payload (`:5980+`, second path `:6174+`,
  refund-adjust guard `:6010`): `fare_difference: 0, other_costs: 0`,
  `service_charge: derived, total_customer_payment: total_payment`.

`resources/views/bookings/show.blade.php:1420` history display: **no change**
(stored legacy values still displayed).

## 7. Tests (TDD per AGENTS.md)

Existing tests referencing these fields:

* `tests/Feature/ProfitCalculationServiceTest.php:439-440,472-473`
  (`fare_difference: 50, other_costs: 25`) — direct model creates testing profit math, keep.
* `tests/Unit/CostTrackingServiceTest.php:347-348,390-391`,
  `tests/Feature/PassengerCancellationServiceTest.php:576-577,619-620` — service-level, keep.
* `tests/Feature/BookingReIssueBdtResetTest.php:61-68` — asserts
  `reIssueForm.fare_difference_bdt / other_costs_bdt` reset strings exist; **must update**
  (fields become hidden/removed).
* `tests/Feature/ReIssuedTicketObserverProfitTest.php`,
  `tests/Feature/ReIssueEditRefundedNonCustomerTest.php`,
  `tests/Feature/ReIssueEditRefundPayableAdjustTest.php`,
  `tests/Feature/ProfitEffectiveDateComponentsTest.php:286-379` — run; update only if asserting
  required-validation of hidden fields.

New tests (write failing first):

1. `test_reissue_create_defaults_fare_difference_and_other_costs_to_zero` — POST without those
   keys → success; stored `fare_difference=0`, `other_costs=0`,
   `total_cost = re_issue_charge + refundedNetFare - refundAdj`. Cover both
   `ReIssueController@store` and `TicketRequestController@processReIssue`.
2. `test_customer_payment_derives_service_charge` —
   `payment_by=customer, re_issue_charge=100, total_customer_payment=150` →
   stored `service_charge=50`, `total_cost=100`, invoice `+=150`.
3. `test_customer_payment_below_cost_rejected` — `total_customer_payment < total_cost` → 422.
4. `test_non_customer_forces_zero_service_and_payment` — `payment_by=company` →
   `service_charge=0`, payment col `0`, no invoice change.
5. `test_edit_reissue_recomputes_service_from_total_payment` —
   `TicketIssueController@update` with new `total_customer_payment` → re-derived service,
   invoice delta = new − old total.
6. Manual QA checklist (no JS unit harness in repo): customer flow SAR + BDT, non-customer flow,
   refund_adjustment flow, wasRefunded flow, edit flow with legacy non-zero record.

Verify per commit checklist: `php artisan test`, `vendor/bin/pint`, `npm run build`,
`docker compose config --quiet` (+ prod file), conventional commits, one logical change per commit.

## 8. Risks & open decisions

* Edit of legacy records with non-zero fare/other zeroes them — intended per "default 0",
  but flag in release notes.
* Pre-existing customer rows keep stored `service_charge`; only new saves derive it.
  Profit reports mix both until data ages out — math semantics identical, acceptable.
* BDT mode: total-payment is the conversion source; service BDT mirror derives from SAR service.
  Never allow direct BDT-service input (avoid double-source).
* Rounding: `round(...,6)` server-side, `Math.round(x*1e6)/1e6` in JS (existing convention).
* `wasRefunded` non-customer invoice path preserved as `totalCost`; confirm with product if that
  sub-case should also require a total-payment input.

## 9. Implementation order

1. Backend: `ReIssueController` + `TicketRequestController` + tests 1–4.
2. Backend edit: `TicketIssueController` + test 5.
3. Frontend: `confirmation.blade.php` (§5).
4. Frontend: `index.blade.php` (§6) + `BookingReIssueBdtResetTest` update.
5. Full suite + pint + build + docker config; commit per AGENTS.md.
