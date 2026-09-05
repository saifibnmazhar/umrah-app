# Profit Calculation System — Test Case Checklist

## Reference Data

| Component | Formula |
|-----------|---------|
| Visa Profit | `selling_price - net_visa_cost - agent_commission - additional_cost - SUM(cancellation_fees)` |
| Ticket Profit | `package_selling_fare - SUM(net_fare)` (only regular/pending_outbound issued tickets) |
| Additional Ticket Profit | `SUM(fare_selling_fare - net_fare)` per additional issued ticket |
| Re-Issue Profit | `SUM(service_charge)` WHERE `payment_by = customer` |
| Re-Issue Cost | `SUM(total_cost)` WHERE `payment_by = company` |
| Refund Profit | `SUM(service_charge)` from all refunded tickets |
| Service Charge | `package.service_charge` (only when both visa & ticket are effective) |
| Fingerprint Profit | `fingerprint_charge - fingerprint.cost` |
| **Passenger Profit** | `visa_profit + ticket_profit + additional_ticket_profit + re_issue_profit + refund_profit + service_charge - re_issue_cost` |
| **Booking Profit** | `SUM(passenger.profit) + fingerprint.profit - discount_amount` |

---

## GROUP 1: Core Profit Calculation

### TC-01: Full effective passenger profit and booking rollup
**Steps:**
1. Create adult passenger (`service_required = 'all'`)
2. Create issued visa (status: `issued`)
3. Create one issued regular ticket (status: `issued`)
4. Calculate passenger and booking profit

**Expected:** visa_profit=850, ticket_profit=3000, service_charge=500, **passenger_total=4350**, fingerprint_profit=200, **booking_total=4550**

**DB check:** `passengers.profit=4350`, `bookings.profit=4550`, `fingerprints.profit=200`

---

### TC-02: Visa not issued zeroes visa profit and service charge
**Steps:**
1. Create adult passenger with all services
2. Set visa status to `submitted`
3. Calculate passenger profit

**Expected:** visa_profit=**0**, service_charge=**0**, total=3000

---

### TC-03: Ticket not issued zeroes ticket profit and service charge
**Steps:**
1. Create adult passenger with all services
2. Set regular ticket status to `pending`
3. Calculate passenger profit

**Expected:** ticket_profit=**0**, service_charge=**0**, total=850

---

### TC-04: Cancellation fees reduce visa profit
**Steps:**
1. Create adult passenger with all services
2. Add 1 cancellation fee of 150 to visa submission
3. Calculate visa profit

**Expected:** visa_profit=**700** (850 - 150)

---

### TC-05: Child and infant passenger type adjusts selling fare
**Steps:**
1. Create child passenger (50% fare)
2. Create infant passenger (20% fare)
3. Calculate ticket profit for each

**Expected:** child ticket_profit=**-12000**, infant ticket_profit=**-21000**

---

### TC-06: Offer price used when package fare is offer type
**Steps:**
1. Update fare: `ticket_type='offer'`, `offer_price=25000`
2. Create adult passenger with all services
3. Calculate ticket profit

**Expected:** ticket_profit=**-2000** (25000 - 27000)

---

### TC-07: Double ticket package sums inbound and outbound fares
**Steps:**
1. Create outbound fare (`selling_fare=8000`, `net_fare=10000`)
2. Update package: `is_double_ticket=true`, set both fare IDs
3. Create adult passenger
4. Calculate ticket profit

**Expected:** ticket_profit=**11000** (30000 + 8000 - 27000)

---

### TC-08: Booking profit subtracts discount
**Steps:**
1. Create booking with `discount_amount=500`
2. Add 2 adult passengers
3. Calculate booking profit

**Expected:** booking_profit=**8400** (4350×2 + 200 - 500)

---

## GROUP 2: Service Required Variations

### TC-09: Visa only — service charge granted without ticket
**Steps:**
1. Create passenger with `service_required='visa_only'`
2. Create issued visa (no ticket)
3. Calculate profit

**Expected:** visa_profit=850, service_charge=**500**, total=**1350**

---

### TC-10: Ticket only — service charge granted without visa
**Steps:**
1. Create passenger with `service_required='ticket_only'`
2. Create issued ticket (no visa)
3. Calculate profit

**Expected:** ticket_profit=3000, service_charge=**500**, total=**3500**

---

### TC-11: Service charge zero when neither effective
**Steps:**
1. Create passenger with `service_required='all'`
2. Set visa status=`submitted`, ticket status=`pending`
3. Calculate profit

**Expected:** service_charge=**0**, total=**0**

---

## GROUP 3: Re-Issue Profit

### TC-12: Customer paid re-issue — service charge counts as profit
**Steps:**
1. Create adult passenger with all services
2. Create re-issued ticket: `payment_by='customer'`, `service_charge=200`
3. Calculate profit

**Expected:** re_issue_profit=**200**, re_issue_cost=0

---

### TC-13: Company paid re-issue — total_cost counts as cost
**Steps:**
1. Create adult passenger with all services
2. Create re-issued ticket: `payment_by='company'`, `re_issue_charge=100`, `fare_difference=50`, `other_costs=25`, `net_fare=26500`, `total_cost=26675`
3. Calculate profit

**Expected:** re_issue_profit=0, re_issue_cost=**26675**, total=**-22325**

---

### TC-14: Airline paid re-issue — counts as neither profit nor cost
**Steps:**
1. Create adult passenger with all services
2. Create re-issued ticket: `payment_by='airline'`, `service_charge=150`
3. Calculate profit

**Expected:** re_issue_profit=**0**, re_issue_cost=**0**

---

### TC-15: Employee paid re-issue — counts as neither profit nor cost
**Steps:**
1. Create adult passenger with all services
2. Create re-issued ticket: `payment_by='employee'`, `service_charge=100`
3. Calculate profit

**Expected:** re_issue_profit=**0**, re_issue_cost=**0**

---

## GROUP 4: Refund Profit

### TC-16: Refund service charge counts as profit
**Steps:**
1. Create adult passenger with all services
2. Create refunded ticket: `service_charge=75`
3. Calculate profit

**Expected:** refund_profit=**75**

---

### TC-17: Multiple refunds across tickets sum service_charge
**Steps:**
1. Create adult passenger with 2 issued tickets
2. Create refunded ticket for each: `service_charge=50` and `service_charge=75`
3. Calculate profit

**Expected:** refund_profit=**125**

---

## GROUP 5: Bug Fix A — Status Change Does Not Erase Profit

### TC-18: Status change to re-issued preserves ticket profit
**Steps:**
1. Create adult passenger with all services
2. Verify ticket_profit=3000
3. Change ticket `status` to `re-issued`
4. Re-calculate profit

**Expected:** ticket_profit still=**3000** (not erased)

---

### TC-19: Status change to refunded preserves ticket profit
**Steps:**
1. Create adult passenger with all services
2. Verify ticket_profit=3000
3. Change ticket `status` to `refunded`
4. Re-calculate profit

**Expected:** ticket_profit still=**3000** (not erased)

---

### TC-20: Net fare change still triggers recalculation
**Steps:**
1. Create adult passenger with all services
2. Verify ticket_profit=3000
3. Change ticket `net_fare` to 25000
4. Re-calculate profit

**Expected:** ticket_profit=**5000** (30000 - 25000, recalculated correctly)

---

## GROUP 6: Bug Fix B — Re-Issue Cost Uses `total_cost`

### TC-21: Company re-issue cost uses total_cost field
**Steps:**
1. Create adult passenger with all services
2. Create re-issued ticket: `payment_by='company'`, `re_issue_charge=100`, `fare_difference=50`, `other_costs=25`, `net_fare=26500`, `total_cost=175` (100+50+25 only, no net_fare)
3. Calculate profit

**Expected:** re_issue_cost=**175** (not 26675)

---

### TC-22: Company re-issue cost with refund adjustment
**Steps:**
1. Create adult passenger with `refund_payable=100`
2. Create re-issued ticket: `payment_by='company'`, raw cost=175, `refund_adjustment_amount=100`, `total_cost=75`
3. Calculate profit

**Expected:** re_issue_cost=**75**

---

## GROUP 7: Bug Fix C — Refund Adjustment for Any `payment_by`

### TC-23: Customer refund adjustment decreases refund_payable
**Steps:**
1. Create adult passenger, set `refund_payable=200`
2. Create re-issued ticket: `payment_by='customer'`, `refund_adjustment_amount=100`
3. Check `passenger.refund_payable`

**Expected:** refund_payable=**100**

---

### TC-24: Airline refund adjustment decreases refund_payable
**Steps:**
1. Create adult passenger, set `refund_payable=200`
2. Create re-issued ticket: `payment_by='airline'`, `refund_adjustment_amount=50`
3. Check `passenger.refund_payable`

**Expected:** refund_payable=**150**

---

### TC-25: Employee refund adjustment decreases refund_payable
**Steps:**
1. Create adult passenger, set `refund_payable=200`
2. Create re-issued ticket: `payment_by='employee'`, `refund_adjustment_amount=75`
3. Check `passenger.refund_payable`

**Expected:** refund_payable=**125**

---

### TC-26: Company refund adjustment decreases refund_payable
**Steps:**
1. Create adult passenger, set `refund_payable=200`
2. Create re-issued ticket: `payment_by='company'`, `refund_adjustment_amount=100`
3. Check `passenger.refund_payable`

**Expected:** refund_payable=**100**

---

## GROUP 8: Backfill

### TC-27: Backfill updates stored values
**Steps:**
1. Create booking with 1 passenger
2. Set `bookings.profit=0` manually
3. Run `backfillAllBookings()`
4. Check DB

**Expected:** `bookings.profit=4550`

---

### TC-28: Backfill populates total_cost for existing re-issues
**Steps:**
1. Create re-issued ticket with `re_issue_charge=100`, `fare_difference=50`, `other_costs=25`, `total_cost=0`
2. Run backfill
3. Check DB

**Expected:** `re_issued_tickets.total_cost=175`

---

## GROUP 9: Multiple Passengers

### TC-29: Multiple passengers contribute to booking profit
**Steps:**
1. Create booking
2. Add 3 adult passengers (each profit=4350)
3. Calculate booking profit

**Expected:** booking_profit=**13250** (4350×3 + 200)

---

## GROUP 10: Edge Cases

### TC-30: Visa with multiple cancellation fees
**Steps:**
1. Create adult passenger with all services
2. Add 2 cancellation fees: 150 and 75
3. Calculate visa profit

**Expected:** visa_profit=**625** (850 - 150 - 75)

---

### TC-31: Null visa fields default to zero
**Steps:**
1. Create adult passenger with all services
2. Set visa: `net_visa_cost=null`, `agent_commission=null`, `additional_cost=null`
3. Calculate visa profit

**Expected:** visa_profit=**2000** (2000 - 0 - 0 - 0)

---

### TC-32: Null fingerprint cost defaults to zero
**Steps:**
1. Create booking with fingerprint: `cost=null`
2. Calculate fingerprint profit

**Expected:** fingerprint_profit=**300** (300 - 0)

---

### TC-33: No fingerprint record
**Steps:**
1. Create booking without fingerprint record
2. Calculate booking profit

**Expected:** fingerprint_profit=0, booking profit = sum of passenger profits only

---

### TC-34: Null discount treated as zero
**Steps:**
1. Create booking with `discount_amount=null`
2. Add 1 passenger
3. Calculate booking profit

**Expected:** booking_profit=**4550** (no discount subtracted)

---

### TC-35: Ticket with null issue_type treated as regular
**Steps:**
1. Create adult passenger
2. Create ticket with `issue_type=null`, `status='issued'`
3. Calculate ticket profit

**Expected:** ticket_profit=**3000**

---

### TC-36: Ticket with pending_outbound treated as regular
**Steps:**
1. Create adult passenger
2. Create ticket with `issue_type='pending_outbound'`, `status='issued'`
3. Calculate ticket profit

**Expected:** ticket_profit=**3000**

---

### TC-37: Additional ticket excluded from regular profit
**Steps:**
1. Create adult passenger with all services (1 regular ticket)
2. Add 1 additional ticket: `issue_type='additional'`, `status='issued'`, `net_fare=25000`
3. Calculate both profits

**Expected:** ticket_profit=**3000**, additional_ticket_profit=**5000**

---

### TC-38: Additional ticket not issued — excluded from additional profit
**Steps:**
1. Create adult passenger with all services
2. Add 1 additional ticket: `issue_type='additional'`, `status='pending'`
3. Calculate additional_ticket_profit

**Expected:** additional_ticket_profit=**0**

---

## Summary

| Group | Test IDs | Count |
|-------|----------|-------|
| Core Profit Calculation | TC-01 to TC-08 | 8 |
| Service Required Variations | TC-09 to TC-11 | 3 |
| Re-Issue Profit | TC-12 to TC-15 | 4 |
| Refund Profit | TC-16 to TC-17 | 2 |
| Bug Fix A | TC-18 to TC-20 | 3 |
| Bug Fix B | TC-21 to TC-22 | 2 |
| Bug Fix C | TC-23 to TC-26 | 4 |
| Backfill | TC-27 to TC-28 | 2 |
| Multiple Passengers | TC-29 | 1 |
| Edge Cases | TC-30 to TC-38 | 9 |
| **Total** | | **38** |
