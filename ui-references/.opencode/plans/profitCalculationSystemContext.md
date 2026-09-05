# Profit Calculation System — Full Context

## Overview

The profit calculation system tracks per-passenger and per-booking profit for an umrah travel agency. Profit is "captured" when a visa/ticket is issued, and status changes to `re-issued`/`refunded` do not recalculate captured profit.

---

## Architecture

```
Models (3 with profit column)
├── Booking     → bookings.profit
├── Passenger   → passengers.profit
└── Fingerprint → fingerprints.profit

Service
└── ProfitCalculationService (250 lines, 12 methods)

Observers (4 trigger recalculation)
├── VisaSubmissionObserver   → on visa create/update
├── IssuedTicketObserver     → on ticket create/update/delete
├── ReIssuedTicketObserver   → on re-issue create/update/delete
└── RefundedTicketObserver   → on refund create/update/delete

Backfill Command
└── BackfillProfitData       → recalculates all bookings
```

---

## Profit Formulas

### Passenger Profit (7 components)

```
passenger_profit =
    visa_profit
  + ticket_profit
  + additional_ticket_profit
  + re_issue_profit
  + refund_profit
  + service_charge
  - re_issue_cost
```

### Component Breakdown

| Component | Formula | Trigger |
|-----------|---------|---------|
| **visa_profit** | `selling_price - net_visa_cost - agent_commission - additional_cost - SUM(cancellation_fees)` | Visa status = `issued` |
| **ticket_profit** | `package_selling_fare - SUM(net_fare)` | All regular/pending_outbound tickets = `issued` |
| **additional_ticket_profit** | `SUM(fare_selling_fare - net_fare)` per additional issued ticket | Each additional ticket issued |
| **re_issue_profit** | `SUM(service_charge)` WHERE `payment_by = customer` | Re-issue created |
| **refund_profit** | `SUM(service_charge)` from all refunded tickets | Refund created |
| **re_issue_cost** | `SUM(total_cost)` WHERE `payment_by = company` | Re-issue created |
| **service_charge** | `package.service_charge` | Both visa AND ticket effective |

### Booking Profit

```
booking_profit =
  SUM(passenger.profit)
  + fingerprint.profit
  - discount_amount
```

### Fingerprint Profit

```
fingerprint_profit = fingerprint_charge - fingerprint.cost
```

---

## Effectiveness Rules

### Visa Effectiveness (`isVisaProfitEffective`)
- Returns `true` if `service_required = ticket_only` (no visa expected)
- Otherwise returns `true` if `visa_submission.status = issued`

### Ticket Effectiveness (`isTicketProfitEffective`)
- Returns `true` if `service_required = visa_only` (no ticket expected)
- Otherwise returns `true` if at least one regular ticket exists AND all are `issued`

### Service Charge
- Granted only when **both** visa and ticket are effective
- Exception: `visa_only` or `ticket_only` passengers skip the missing sector

---

## Fare Adjustments

| Passenger Type | Fare Multiplier |
|---------------|-----------------|
| adult | 100% |
| child | `child_fare_percentage` (default 50%) |
| infant | `infant_fare_percentage` (default 20%) |

For offer-type fares, `offer_price` is used instead of `selling_fare`.

---

## Ticket Types in Profit

| `issue_type` | Counted in |
|-------------|------------|
| `null` | ticket_profit (treated as regular) |
| `regular` | ticket_profit |
| `pending_outbound` | ticket_profit |
| `additional` | additional_ticket_profit |

Only one regular and one pending_outbound ticket per passenger (system restriction).

---

## Re-Issue Payment Rules

| `payment_by` | `service_charge` | `total_cost` |
|-------------|------------------|--------------|
| `customer` | → re_issue_profit | — |
| `company` | — | → re_issue_cost |
| `airline` | ignored | ignored |
| `employee` | ignored | ignored |

---

## Observer Triggers

| Observer | Model | Triggers Recalc When |
|----------|-------|---------------------|
| VisaSubmissionObserver | visa_submissions | `status`, `net_visa_cost`, `agent_commission`, `additional_cost`, `visa_selling_price_id` changed |
| IssuedTicketObserver | issued_tickets | `net_fare`, `issue_type` changed (NOT `status`) |
| ReIssuedTicketObserver | re_issued_tickets | `service_charge`, `payment_by`, `re_issue_charge`, `fare_difference`, `other_costs`, `net_fare` changed |
| RefundedTicketObserver | refunded_tickets | `service_charge` changed |

---

## Bug Fixes Applied

### Bug A: Status Change Erases Profit
**Problem:** `IssuedTicketObserver` triggered recalculation on any `wasChanged()`, including `status` changes to `re-issued`/`refunded`, which zeroed out captured ticket profit.

**Fix:** Changed `wasChanged()` to `wasChanged(['net_fare', 'issue_type'])` — only recalculate when actual fare data changes.

### Bug B: Re-Issue Cost Formula
**Problem:** `calculateReIssueCost()` summed `re_issue_charge + fare_difference + other_costs + net_fare` — but `net_fare` is a ticket price, not a re-issue cost.

**Fix:** Added `total_cost` column to `re_issued_tickets`. `calculateReIssueCost()` now uses `SUM(total_cost)`. Controller computes `total_cost = re_issue_charge + fare_difference + other_costs` (no net_fare). Backfill command updates existing records.

### Bug C: Refund Adjustment Restricted to Customer
**Problem:** Refund adjustment only worked when `payment_by = customer`. The validation allowed it for any `payment_by`, but the controller logic overwrote `payment_option` and `refund_adjustment_amount` to null/0 for non-customer.

**Fix:** Removed `payment_by === 'customer'` ternary guards in `ReIssueController` and `TicketRequestController` so refund adjustment works for any `payment_by`.

### Bug D: Re-Issue Profit Scope
**Problem:** `calculateReIssueProfit()` summed service charges for all `payment_by !== company` (customer, airline, employee, null).

**Fix:** Changed to `payment_by === customer` — only customer re-issue service charges count as profit.

---

## Files Involved

| File | Purpose |
|------|---------|
| `app/Services/ProfitCalculationService.php` | Core service (250 lines) |
| `app/Observers/IssuedTicketObserver.php` | Ticket change observer |
| `app/Observers/VisaSubmissionObserver.php` | Visa change observer |
| `app/Observers/ReIssuedTicketObserver.php` | Re-issue change observer |
| `app/Observers/RefundedTicketObserver.php` | Refund change observer |
| `app/Http/Controllers/ReIssueController.php` | Re-issue API (Bug B, C targets) |
| `app/Http/Controllers/TicketRequestController.php` | Ticket request API (Bug C target) |
| `app/Console/Commands/BackfillProfitData.php` | Backfill command |
| `app/Models/ReIssuedTicket.php` | Added `total_cost` field |
| `app/Models/Booking.php` | Has `profit` in fillable/casts |
| `app/Models/Passenger.php` | Has `profit` in fillable/casts |
| `app/Models/Fingerprint.php` | Has `profit` in fillable/casts |
| `tests/Feature/ProfitCalculationServiceTest.php` | 14 PHPUnit tests |
| `database/migrations/*_add_total_cost_to_re_issued_tickets_table.php` | Migration for Bug B |

---

## Test Coverage

14 PHPUnit tests covering:
- Full profit breakdown and booking rollup
- Visa/ticket not issued → zero profit + zero service charge
- Cancellation fees, child/infant fares, offer fares, double tickets
- Customer vs company re-issue profit/cost
- Refund service charge
- Visa-only and ticket-only service charge bypasses
- Discount subtraction
- Backfill consistency

38-case manual test plan covering all of the above plus:
- Bug fix verification (A, B, C, D)
- Status change preservation
- Refund adjustment for all `payment_by` values
- Edge cases (null fields, multiple cancellations, mixed statuses)
