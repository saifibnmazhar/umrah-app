# Re-Issue & Refund Adjustment — Implementation Plan

## Scope
Track per-passenger **refund payable** (money owed back to the customer for their refunded tickets) and allow **settling it through re-issue**.

- Refund payable = `Σ refunded_tickets.refund_to_customer` minus ticket-refund payments made.
- A processed refund **creates** refund payable.
- A re-issue may be **settled against** the pending refund payable instead of requiring full fresh payment.
- `Ticket Refund - Payment` payout workflow is **deferred** to a later session (along with separate passenger-cancellation / ticket-refund workflows).

## Business Concept
- Refund payable per passenger starts at `0`.
- Processing a refund (`refund_to_customer`) **increases** the payable.
- Settling a re-issue via refund adjustment **decreases** the payable.
- Refund payouts (`Ticket Refund - Payment`) are out of scope for this session.

## Phase 1 — Schema & Reference Data

1. **Migration A (`payments`):** add nullable foreign keys `passenger_id`, `refunded_ticket_id`, `re_issued_ticket_id`; update `Payment` model (fillable + relations).

2. **Migration B (`re_issued_tickets`):** add
   - `payment_option` enum `customer_payment | refund_adjustment` (nullable)
   - `refund_adjustment_amount` decimal(14,6) default 0 (user-specified, stored)

3. **Migration C (`passengers`):** add `refund_payable` decimal(14,6) default 0 (stored running balance).

4. **Seeder (`TransactionTypeSeeder`):** add two debit transaction types:
   - `Ticket Refund - Payment`
   - `Ticket Refund - Re-issue`

## Phase 2 — Backend: Refund Payable

5. Relations:
   - `Passenger → refundedTickets` (through `issuedTickets`)
   - `Passenger → ticket-refund payments` (via `passenger_id` + voucher transaction type)

6. **Stored running balance** (`passengers.refund_payable`) kept in sync inside the same DB transaction as refund/re-issue processing.
   - **Dynamic aggregation** is used for integrity verification only (recompute and compare) — not for reads.

## Phase 3 — Refund Process (payable only, no payments)

7. When a refund is processed — both `TicketRequestController::processRefund` and `RefundController::store` — **increase** `passenger.refund_payable` by `refund_to_customer`.
   - No `Payment` / `Voucher` is created in this session.
   - The `Ticket Refund - Payment` payout workflow is handled later.

8. Refund confirmation modal (`refunds/confirmation.blade.php`) and passenger-index refund form (`bookings/index.blade.php`): display the passenger's current refund payable (informational).

## Phase 4 — Re-Issue: Settlement

Applies to both re-issue forms:
- Re-issue confirmation page (`re-issues/confirmation.blade.php`)
- Passenger-index re-issue modal (`bookings/index.blade.php`)

Backend: `TicketRequestController::processReIssue` and `ReIssueController::store`.

9. **Computed totals:**
   - `Total Cost = re_issue_charge + fare_difference + other_costs + service_charge`
   - `Total Customer Payment = Total Cost − refund_adjustment_amount`

10. **Payment option control** (`customer_payment | refund_adjustment`) plus a **user-specified adjustment amount** field:
    - Adjustment amount defaults to `min(total_cost, payable)`, editable.
    - Validation: `0 ≤ amount ≤ total_cost` **and** `0 ≤ amount ≤ available refund payable`.

11. **Refund Adjustment** (amount > 0):
    - Persist `payment_option` + `refund_adjustment_amount` on `re_issued_tickets`.
    - Create `Payment` + `Voucher` with `Ticket Refund - Re-issue` (debit), carrying `passenger_id` + `re_issued_ticket_id`.
    - **Decrease** `passenger.refund_payable` by the amount.
    - Invoice unchanged.
    - All within a single DB transaction.

12. **Customer Payment** (currently NOT implemented — must be added this session):
    - Increase `invoice.total_amount` by the re-issue Total Customer Payment.
    - Recompute `invoice.balance` per the new `total_amount`, via `InvoiceService::updateTotals()` (uses `balance = max(0, total_amount − paid_amount)`, status re-evaluated).
    - No `Payment` / `Voucher` this session (payments deferred).
    - Refund payable unchanged.

## Phase 5 — Reporting

13. New summary card **"Total Ticket Refunds"** alongside the existing "Total Refund" card in `DashboardController` and `BranchWiseReportController`: sums vouchers of the two Ticket Refund transaction types.
    - Existing "Total Refund" card stays unchanged (cancellation `Customer Refund` only) — no double counting.

## Confirmed Assumptions
- Balance update reuses `InvoiceService::updateTotals()` with `balance = max(0, total_amount − paid_amount)`.
- Invoice amounts are SAR-based; BDT conversions derive from the SAR total — no extra column needed for the invoice bump.
- Enum column/value naming: `payment_option` enum `customer_payment | refund_adjustment`.
- Balance column: `passengers.refund_payable`.

## Affected Files (reference)
- Migrations: `payments`, `re_issued_tickets`, `passengers`
- Models: `Payment`, `ReIssuedTicket`, `Passenger`
- Controllers: `TicketRequestController`, `ReIssueController`, `RefundController`
- Services: `InvoiceService`, `VoucherService`
- Views: `re-issues/confirmation.blade.php`, `refunds/confirmation.blade.php`, `bookings/index.blade.php`
- Reports: `DashboardController`, `BranchWiseReportController`
- Seeder: `TransactionTypeSeeder`

---

# Detailed Code Change Plan

## 1. Migrations (new files under `database/migrations/`)

1. **`add_ticket_refund_links_to_payments_table`**
   - Add nullable FKs: `passenger_id` → `passengers`, `refunded_ticket_id` → `refunded_tickets`, `re_issued_ticket_id` → `re_issued_tickets` (all `nullOnDelete`).
2. **`add_payment_option_to_re_issued_tickets_table`**
   - `payment_option` enum `customer_payment|refund_adjustment`, nullable, after `payment_by`.
   - `refund_adjustment_amount` decimal(14,6) default 0, after `payment_option`.
3. **`add_refund_payable_to_passengers_table`**
   - `refund_payable` decimal(14,6) default 0.

## 2. Enums & Models

- **New `app/Enums/ReIssuePaymentOption.php`**: `CUSTOMER_PAYMENT = 'customer_payment'`, `REFUND_ADJUSTMENT = 'refund_adjustment'` (mirrors `PaymentMethod`/`InvoiceStatus` enum style).
- **`app/Models/Payment.php`**: add to fillable `passenger_id`, `refunded_ticket_id`, `re_issued_ticket_id`; add `passenger()`, `refundedTicket()`, `reIssuedTicket()` BelongsTo relations.
- **`app/Models/ReIssuedTicket.php`**: add `payment_option`, `refund_adjustment_amount` to fillable; casts `refund_adjustment_amount => decimal:6`, `payment_option => ReIssuePaymentOption::class`.
- **`app/Models/Passenger.php`**:
  - Add `refund_payable` to fillable + cast `decimal:6`.
  - Relations: `refundedTickets()` (`hasManyThrough` RefundedTicket via IssuedTicket), `ticketRefundPayments()` (payments where `passenger_id` + voucher transaction type in the two Ticket Refund types).
  - Helpers: `increaseRefundPayable(float)` (inline increment), `decreaseRefundPayable(float)` (guarded `max(0, ...)`), `verifyRefundPayable()` (dynamic aggregation recompute for QA).

## 3. Seeder

- **`database/seeders/TransactionTypeSeeder.php`**: add via `updateOrCreate`:
  - `Ticket Refund - Payment` (debit)
  - `Ticket Refund - Re-issue` (debit)

## 4. Refund process → increase payable

- **`TicketRequestController::processRefund`**: inside the existing `DB::transaction`, after `RefundedTicket::create`, add `$ticketRequest->passenger?->increaseRefundPayable((float) $validated['customer_refund']);`
- **`RefundController::store`**: inside its transaction, after `RefundedTicket::create`, add `$passenger->increaseRefundPayable((float) $validated['customer_refund']);`

## 5. Re-issue process → settlement + invoice (both controllers)

Shared additions to **`TicketRequestController::processReIssue`** and **`ReIssueController::store`**:

- **Validation:** `'payment_option' => 'required|in:customer_payment,refund_adjustment'`, `'refund_adjustment_amount' => 'required|numeric|min:0'`.
- Inside the existing `DB::transaction`, after `$reIssuedTicket` is created:
  - `$totalCost = re_issue_charge + fare_difference + other_costs + service_charge`.
  - **Refund Adjustment** (`payment_option === 'refund_adjustment'`):
    - Assert `refund_adjustment_amount ≤ $totalCost` and `≤ $passenger->refund_payable` (else throw/rollback with a clear message).
    - Store `payment_option` + `refund_adjustment_amount` on the ReIssuedTicket record.
    - `$passenger->decreaseRefundPayable($amount)`.
    - Create `Payment` (mirror `CancellationService` — direct `Payment::create`, **not** `PaymentService`) with `passenger_id`, `re_issued_ticket_id`, invoice/booking/branch/user/currency_rate, `payment_date = now()`, `payment_method = PaymentMethod::CASH` (default; no capture this session), `amount = $amount`, `bdt_amount = 0`, `remarks`.
    - `app(VoucherService::class)->createVoucher([... transaction_type_id => 'Ticket Refund - Re-issue' type id, payment_id, amount, bdt_amount, invoice/booking/branch ...])`.
    - Invoice unchanged.
  - **Customer Payment** (`payment_option === 'customer_payment'`):
    - Set `payment_option = 'customer_payment'`, `refund_adjustment_amount = 0`.
    - `$invoice = $booking->invoice;` then `app(InvoiceService::class)->updateTotals($invoice, $invoice->total_amount + $totalCost);` (balance = `max(0, newTotal − paid)`, status re-evaluated).
    - No Payment/Voucher; payable unchanged.
  - `$passenger` = `$ticketRequest->passenger` (processReIssue) / route `$passenger` (ReIssueController::store); `$booking` = `$ticketRequest->booking` / route `$booking`.

## 6. Frontend — re-issue confirmation (`resources/views/re-issues/confirmation.blade.php`)

- **HTML:**
  - Add "Refund Payable" read-only display (from `r.passenger.refund_payable`).
  - Add `inputPaymentOption` select (`customer_payment | refund_adjustment`).
  - Add `inputRefundAdjustment` number field, shown only when option = Refund Adjustment.
  - Make `inputTotalCost` and `inputTotalPayment` readonly (auto-calculated).
- **JS:**
  - `processConfirmation()`: reset `inputPaymentOption='customer_payment'`, `inputRefundAdjustment=0`; populate refund payable from `p.refund_payable`; call `updateTotals()`.
  - New `updateTotals()`: `totalCost = re_issue_charge + fare_difference + other_costs + service_charge`; clamp adjustment to `min(value, totalCost, payable)`; `totalPayment = totalCost − adjustment`.
  - Wire `oninput`/`onchange` on charge/difference/other/service inputs, option select, and adjustment input.
  - `confirmProcess()` payload: add `payment_option` + `refund_adjustment_amount`.

## 7. Frontend — refund confirmation (`resources/views/refunds/confirmation.blade.php`)

- Display the passenger's current refund payable (from `r.passenger.refund_payable`) — informational only; no payload change.

## 8. Frontend — passenger index (`resources/views/bookings/index.blade.php`)

- **Data** (`$passengersTicketData`, ~:146): add `'refund_payable' => (float) ($p->refund_payable ?? 0),`.
- **Re-issue modal** (`reIssueForm` state ~:3800, `openReIssueModal` ~:4691, `handleReIssueSubmit` ~:4882):
  - Add state: `payment_option: 'customer_payment'`, `refund_adjustment_amount: 0`, `refund_payable: 0`, `total_cost: 0`, `total_customer_payment: 0`.
  - Display payable + Payment Option + adjustment amount + computed Total Cost / Total Customer Payment (read-only).
  - Reset/populate in `openReIssueModal`; add `computeReIssueTotals()`.
  - Payload adds `payment_option` + `refund_adjustment_amount`.
- **Refund modal** (`refundForm`): display `refund_payable` (read-only); payload unchanged.

## 9. Reporting — "Total Ticket Refunds" card

- **`DashboardController::index`**: add `$totalTicketRefund`/`$totalTicketRefundBdt` — `Voucher` last-30-days, `whereHas('transactionType', name in ['Ticket Refund - Payment', 'Ticket Refund - Re-issue'])`, join bookings/currency_rates, `SUM(amount)` / `SUM(amount * rate)`; include in `compact` (~:293).
- **`resources/views/dashboard/index.blade.php`** (~:291): add "Total Ticket Refunds" card mirroring the "Total Refund" card markup.
- **`BranchWiseReportController::index`** (~:136): same aggregation with date filter + `vouchers.branch_id` branch filter; include in `compact` (~:251).
- **`resources/views/reports/branch-wise.blade.php`** (~:196): add the card.
- Existing "Total Refund" card (Customer Refund only) untouched.

## 10. Verification & Housekeeping

- Run `php artisan migrate` and `php artisan db:seed --class=TransactionTypeSeeder`.
- `Passenger::verifyRefundPayable()` (dynamic aggregation) used as an integrity/QA check.
- No backfill of historical data (defaults to 0).

## Flagged Assumptions / Open Points

- Settlement `Payment.payment_method` defaults to `cash` (no method capture this session).
- The re-issue confirmation's existing `Payment Method` / `Bank` / `Branch` fields remain as-is (out of scope).
- `vouchers` does not gain `passenger_id` / `re_issued_ticket_id` columns (only `payments` does, per requirement).
- New `payment_option` is independent of the existing `payment_by` (customer/airline/employee) column.
