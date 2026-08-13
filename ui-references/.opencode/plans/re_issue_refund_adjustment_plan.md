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
