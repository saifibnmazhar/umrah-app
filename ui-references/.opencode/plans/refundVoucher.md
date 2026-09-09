# Refund Voucher System — Implementation Plan

> Cancelled Bookings & Passengers: Index, Details, and Professional Voucher Print

---

## 1. Requirements

- **Access Control**
  - Super Admin, Co Admin: Full access to all cancelled records
  - Branch Manager, Fingerprint Admin: Only records where `cancellation_branch_id` matches their `branch_id`
- **Standalone index page** (`/cancelled-bookings`) with two tabs: Cancelled Bookings and Cancelled Passengers
- **Separate details pages** for each cancelled booking and passenger (read-only)
- **Professional Refund Voucher** print view (title: "REFUND VOUCHER")
- **Adjustment from Due** prominently shown in passenger cancellation voucher
- **After confirming cancellation** → redirect to voucher print page

---

## 2. Files to Modify

### 2.1 `routes/booking-cancellation.php`

Add 6 new routes:

| Method | URI | Name | Middleware |
|--------|-----|------|-----------|
| GET | `/cancelled-bookings` | `cancelled-bookings.index` | `role:Super Admin,Co Admin,Branch Manager,Fingerprint Admin` |
| GET | `/cancelled-bookings/{cancelledBooking}` | `cancelled-bookings.show` | same |
| GET | `/cancelled-bookings/{cancelledBooking}/print` | `cancelled-bookings.print` | same |
| GET | `/cancelled-passengers` | `cancelled-passengers.index` | same |
| GET | `/cancelled-passengers/{cancelledPassenger}` | `cancelled-passengers.show` | same |
| GET | `/cancelled-passengers/{cancelledPassenger}/print` | `cancelled-passengers.print` | same |

### 2.2 `app/Http/Controllers/BookingCancellationActionController.php`

- `confirmSubmit()` (line 71): Change redirect from:
  ```php
  return redirect()->route('pending-refunds.index')
  ```
  to:
  ```php
  return redirect()->route('cancelled-bookings.print', $cancelledBooking)
  ```

### 2.3 `app/Http/Controllers/PassengerCancellationActionController.php`

- `confirmSubmit()` (line 76): Change redirect from:
  ```php
  return redirect()->route('pending-refunds.index', ['tab' => 'passengers'])
  ```
  to:
  ```php
  return redirect()->route('cancelled-passengers.print', $cancelledPassenger)
  ```

### 2.4 `resources/views/partials/nav.blade.php`

Add `canAccessCancelledBookings` permission check (same roles as `canAccessPendingRefunds`).
Add "Cancelled Bookings" nav link after "Pending Refunds" in both desktop and mobile menus.

---

## 3. Files to Create

### 3.1 `app/Http/Controllers/CancelledRecordController.php`

New controller with 6 public methods + 1 private helper:

#### `bookingIndex(Request)`
- Query `CancelledBooking::with([...])` where `status = CancelledBookingStatus::CANCELLED`
- Branch-restricted users: add `where('cancellation_branch_id', auth()->user()->branch_id)`
- Search: `whereHas('booking', fn($q) => $q->where('invoice_id', 'like', ...))` or customer name
- Eager load: `booking.customer`, `booking.bookingBranch`, `cancellationBranch`, `user`, `confirmedBy`, `refundPayment`, `refundVoucher`, `deductionPayment`, `deductionVoucher`
- Paginate 20, pass to `cancelled-bookings.index` view with `$tab` (default: `bookings`)

#### `bookingShow(CancelledBooking)`
- Eager load: `booking.customer`, `booking.passengers`, `booking.invoice`, `booking.bookingBranch`, `booking.fingerprintBranch`, `booking.fingerprint`, `cancellationBranch`, `user`, `confirmedBy`, `deductionPayment`, `deductionVoucher`, `refundPayment`, `refundVoucher`
- Call `ensureBranchAccess($cancelledBooking)`
- Return `cancelled-bookings.show` view

#### `bookingPrint(CancelledBooking)`
- Same eager loading as `bookingShow`
- Call `ensureBranchAccess($cancelledBooking)`
- Return `cancelled-bookings.print-voucher` view (standalone, no layout)

#### `passengerIndex(Request)`
- Query `CancelledPassenger::with([...])` where `status = CancelledBookingStatus::CANCELLED`
- Branch-restricted filter same as above
- Search by invoice ID, customer name, or passenger name
- Eager load: `booking.customer`, `passenger`, `cancellationBranch`, `user`, `confirmedBy`, `refundPayment`, `refundVoucher`, `adjustmentPayment`, `adjustmentVoucher`, `deductionPayment`, `deductionVoucher`
- Paginate 20, pass to same view with `$tab = 'passengers'`

#### `passengerShow(CancelledPassenger)`
- Eager load: `booking.customer`, `booking.invoice`, `booking.bookingBranch`, `passenger`, `cancellationBranch`, `user`, `confirmedBy`, `deductionPayment`, `deductionVoucher`, `refundPayment`, `refundVoucher`, `adjustmentPayment`, `adjustmentVoucher`
- Call `ensureBranchAccess($cancelledPassenger)`
- Return `cancelled-passengers.show` view

#### `passengerPrint(CancelledPassenger)`
- Same eager loading as `passengerShow`
- Call `ensureBranchAccess($cancelledPassenger)`
- Return `cancelled-passengers.print-voucher` view

#### Private: `ensureBranchAccess($record)`
```php
private function ensureBranchAccess($record): void
{
    $user = auth()->user();
    $isAdmin = $user->roles->pluck('name')
        ->intersect(['Super Admin', 'Co Admin'])->isNotEmpty();
    if (!$isAdmin && $user->branch_id
        && $user->branch_id !== $record->cancellation_branch_id) {
        abort(403);
    }
}
```

---

### 3.2 `resources/views/cancelled-bookings/index.blade.php`

Extends `layouts.app`. Alpine.js `x-data` for tab switching.

**Structure:**
- Page header: "Cancelled Bookings"
- Tab bar: "Cancelled Bookings" | "Cancelled Passengers"
- Branch filter dropdown (Super Admin/Co Admin see all)
- Search input (invoice ID or customer name)

**Bookings Tab Table:**

| Column | Source |
|--------|--------|
| Invoice ID | `$cb->booking->invoice_id` |
| Customer | `$cb->booking->customer->name` |
| PAX QTY | `$cb->booking->pax_qty` |
| Mobile | `$cb->booking->customer->mobile_no` |
| Cancellation Branch | `$cb->cancellationBranch->name` |
| Total Paid | `$cb->total_paid` |
| Service Charge | `$cb->service_charge_deduction` |
| Refund Amount | `$cb->refund_amount` |
| Cancel Date | `$cb->created_at->format('Y-m-d')` |
| Status | Badge (yellow=processing, red=cancelled) |
| Cancelled By | `$cb->user->name` (Super Admin/Co Admin only) |
| Actions | "View" link → `cancelled-bookings.show` |

**Passengers Tab Table:**

| Column | Source |
|--------|--------|
| Invoice ID | `$cp->booking->invoice_id` |
| Customer | `$cp->booking->customer->name` |
| Passenger | `$cp->passenger->first_name . ' ' . $cp->passenger->last_name` |
| Cancellation Branch | `$cp->cancellationBranch->name` |
| Package Value | `$cp->package_value` |
| Refundable | `$cp->refundable_amount` |
| Adjusted | `$cp->balance_adjusted_amount` |
| Refund Amount | `$cp->refund_amount` |
| Status | Badge |
| Initiated By | `$cp->user->name` (Super Admin/Co Admin only) |
| Date | `$cp->created_at->format('Y-m-d')` |
| Actions | "View" link → `cancelled-passengers.show` |

---

### 3.3 `resources/views/cancelled-bookings/show.blade.php`

Extends `layouts.app`. Read-only details page.

**Layout:**

```
[Back to Cancelled Bookings]

Cancelled Booking Details                    [Status Badge]
Invoice: BMxx-xxxx

┌─ Booking Information ─────────────────────┐
│ Invoice ID: xxx    Booking Date: xxx       │
│ Customer: xxx      Mobile: xxx             │
│ Booking Branch: xxx                        │
│ Fingerprint Branch: xxx                    │
│ Sale Representative: xxx                   │
└────────────────────────────────────────────┘

┌─ Passenger List ──────────────────────────┐
│ # | Name | Passport | Package Value        │
│ 1 | xxx  | xxx      | SAR xxx             │
│ 2 | xxx  | xxx      | SAR xxx             │
└────────────────────────────────────────────┘

┌─ Cancellation Summary ────────────────────┐
│ Total Amount:            SAR xxx           │
│ Total Paid:              SAR xxx           │
│ Service Charge Deduction: SAR xxx          │
│ Refund Amount:           SAR xxx           │
│ ─────────────────────────────────────────  │
│ Cancelled By: xxx                          │
│ Cancel Date: xxx                           │
│ Cancellation Branch: xxx                   │
│ Confirmed By: xxx                          │
└────────────────────────────────────────────┘

┌─ Financial Transactions ──────────────────┐
│ Deduction Payment (Service Charge)         │
│   Amount: SAR xxx | Method: xxx | Date: xxx│
│   Voucher: VCH-xxx [Print]                 │
│                                            │
│ Refund Payment                             │
│   Amount: SAR xxx | Method: xxx | Date: xxx│
│   Voucher: VCH-xxx [Print]                 │
└────────────────────────────────────────────┘

[Print Refund Voucher]
```

---

### 3.4 `resources/views/cancelled-passengers/show.blade.php`

Extends `layouts.app`. Read-only details page.

**Layout:**

```
[Back to Cancelled Bookings]

Cancelled Passenger Details                  [Status Badge]
Passenger: xxx xxx | Invoice: BMxx-xxxx

┌─ Passenger Information ───────────────────┐
│ Name: xxx xxx         Passport: xxx        │
│ DOB: xxx              Gender: xxx          │
│ Mobile: xxx                                │
│ Booking Invoice: BMxx-xxxx                 │
│ Customer: xxx                              │
└────────────────────────────────────────────┘

┌─ Cancellation Summary ────────────────────┐
│ Package Value:           SAR xxx           │
│ Additional Tickets:      SAR xxx           │
│ Total Passenger Due:     SAR xxx           │
│ Service Charge Deduction: SAR xxx          │
│ Refundable Amount:       SAR xxx           │
│ ─────────────────────────────────────────  │
│ Adjusted from Due:       SAR xxx  ← BLUE   │
│ Invoice Due (before):    SAR xxx           │
│ ─────────────────────────────────────────  │
│ Refund Amount (Cash):    SAR xxx           │
│                                              │
│ Cancelled By: xxx                           │
│ Cancel Date: xxx                            │
│ Cancellation Branch: xxx                    │
└────────────────────────────────────────────┘

┌─ Financial Transactions ──────────────────┐
│ Deduction Payment (Service Charge)         │
│   Amount: SAR xxx | Method: xxx            │
│   Voucher: VCH-xxx [Print]                 │
│                                            │
│ Adjustment Payment (Due Adjustment)        │
│   Amount: SAR xxx | Method: xxx            │
│   Voucher: VCH-xxx [Print]                 │
│                                            │
│ Refund Payment                             │
│   Amount: SAR xxx | Method: xxx            │
│   Voucher: VCH-xxx [Print]                 │
└────────────────────────────────────────────┘

[Print Refund Voucher]
```

---

### 3.5 `resources/views/cancelled-bookings/print-voucher.blade.php`

Standalone HTML (no `@extends`). Pattern from `payments/print-voucher.blade.php`.

**Voucher Layout:**

```
╔═══════════════════════════════════════════════════════════╗
║          BIN MISHAL GLOBAL SERVICES LTD.                  ║
║                  REFUND VOUCHER                           ║
╠═══════════════════════════════════════════════════════════╣
║ Voucher No: VCH-20260831-0001    Date: 31-Aug-2026       ║
║ Invoice No: BMxx-xxxx                                    ║
╠═══════════════════════════════════════════════════════════╣
║ CANCELLATION INFORMATION                                  ║
║ ─────────────────────────────────────────────────────────║
║ Cancelled By:      xxx          Cancellation Branch: xxx  ║
║ Cancel Date:       xxx          Booking Branch: xxx       ║
║ Confirmed By:      xxx                                    ║
╠═══════════════════════════════════════════════════════════╣
║ FINANCIAL SUMMARY                                         ║
║ ─────────────────────────────────────────────────────────║
║ Total Amount                  │  SAR 10,000.00            ║
║ Total Paid                    │  SAR  8,000.00            ║
║ Service Charge Deduction      │  SAR    500.00            ║
║ ──────────────────────────────┼─────────────────          ║
║ Refund Amount                 │  SAR  7,500.00            ║
╠═══════════════════════════════════════════════════════════╣
║ PAYMENT DETAILS                                           ║
║ ─────────────────────────────────────────────────────────║
║ Payment Method: Cash                                      ║
║ Transaction ID:  xxx                                      ║
╠═══════════════════════════════════════════════════════════╣
║ RECEIVED BY                                               ║
║ ─────────────────────────────────────────────────────────║
║ If Cash:                                                    ║
║   Name: ____________  Passport: ____________               ║
║   Iqama: ___________  Mobile: ______________               ║
║ If Bank:                                                    ║
║   Bank Details: ____________                               ║
║   Beneficiary: ____________                                ║
║   Account/IBAN: __________                                 ║
╠═══════════════════════════════════════════════════════════╣
║                                                           ║
║ Prepared By: xxx            Authorized By: ___________    ║
║                                                           ║
║ This is a system-generated Refund Voucher and does not    ║
║ require physical signatures or company stamp.             ║
╚═══════════════════════════════════════════════════════════╝
```

**Technical:**
- `@page { size: landscape; margin: 3mm; }`
- `@media print` — hide `.no-print` toolbar, remove shadows/borders
- Alpine.js `$store.currency` for SAR/BDT toggle
- `window.print()` button
- Voucher number from `$cancelledBooking->refundVoucher->voucher_id ?? 'N/A'`
- Date from `$cancelledBooking->refundVoucher->payment_date ?? $cancelledBooking->created_at`

---

### 3.6 `resources/views/cancelled-passengers/print-voucher.blade.php`

Same standalone HTML pattern. Key difference: includes Passenger info and Adjustment from Due.

**Voucher Layout:**

```
╔═══════════════════════════════════════════════════════════╗
║          BIN MISHAL GLOBAL SERVICES LTD.                  ║
║                  REFUND VOUCHER                           ║
╠═══════════════════════════════════════════════════════════╣
║ Voucher No: VCH-20260831-0002    Date: 31-Aug-2026       ║
║ Invoice No: BMxx-xxxx   Passenger: xxx xxx                ║
╠═══════════════════════════════════════════════════════════╣
║ CANCELLATION INFORMATION                                  ║
║ ─────────────────────────────────────────────────────────║
║ Cancelled By:      xxx          Cancellation Branch: xxx  ║
║ Cancel Date:       xxx          Booking Branch: xxx       ║
║ Confirmed By:      xxx                                    ║
╠═══════════════════════════════════════════════════════════╣
║ FINANCIAL SUMMARY                                         ║
║ ─────────────────────────────────────────────────────────║
║ Package Value                 │  SAR  5,000.00            ║
║ Additional Tickets            │  SAR    800.00            ║
║ Total Passenger Due           │  SAR  5,800.00            ║
║ Service Charge Deduction      │  SAR    300.00            ║
║ Refundable Amount             │  SAR  5,500.00            ║
║ ──────────────────────────────┼─────────────────          ║
║ Adjusted from Due             │  SAR  2,000.00  ← BLUE   ║
║ ──────────────────────────────┼─────────────────          ║
║ Refund Amount (Cash Payout)   │  SAR  3,500.00            ║
╠═══════════════════════════════════════════════════════════╣
║ PAYMENT DETAILS                                           ║
║ ─────────────────────────────────────────────────────────║
║ [Deduction]  Method: xxx  Amount: SAR xxx  Voucher: VCH-xx║
║ [Adjustment] Method: xxx  Amount: SAR xxx  Voucher: VCH-xx║
║ [Refund]     Method: xxx  Amount: SAR xxx  Voucher: VCH-xx║
╠═══════════════════════════════════════════════════════════╣
║ RECEIVED BY                                               ║
║ [Cash/Bank fields]                                        ║
╠═══════════════════════════════════════════════════════════╣
║ Prepared By: xxx            Authorized By: ___________    ║
║                                                           ║
║ This is a system-generated Refund Voucher...              ║
╚═══════════════════════════════════════════════════════════╝
```

**Adjustment from Due styling:**
- Row highlighted with blue background (`bg-blue-50`)
- Font color: `text-blue-700`
- Label includes "(Credit to Invoice Due)" annotation

---

## 4. Summary of All Changes

| # | File | Action | Description |
|---|------|--------|-------------|
| 1 | `routes/booking-cancellation.php` | Modify | Add 6 new routes |
| 2 | `app/Http/Controllers/CancelledRecordController.php` | Create | 6 methods + branch access helper |
| 3 | `app/Http/Controllers/BookingCancellationActionController.php` | Modify | Redirect `confirmSubmit` to print route |
| 4 | `app/Http/Controllers/PassengerCancellationActionController.php` | Modify | Redirect `confirmSubmit` to print route |
| 5 | `resources/views/cancelled-bookings/index.blade.php` | Create | Dual-tab index (bookings + passengers) |
| 6 | `resources/views/cancelled-bookings/show.blade.php` | Create | Cancelled booking details |
| 7 | `resources/views/cancelled-bookings/print-voucher.blade.php` | Create | Booking refund voucher print |
| 8 | `resources/views/cancelled-passengers/show.blade.php` | Create | Cancelled passenger details |
| 9 | `resources/views/cancelled-passengers/print-voucher.blade.php` | Create | Passenger refund voucher print |
| 10 | `resources/views/partials/nav.blade.php` | Modify | Add "Cancelled Bookings" nav link |

---

## 5. Implementation Order

1. Routes
2. `CancelledRecordController` (all 6 methods)
3. Modify `BookingCancellationActionController` (redirect)
4. Modify `PassengerCancellationActionController` (redirect)
5. `cancelled-bookings/index.blade.php`
6. `cancelled-bookings/show.blade.php`
7. `cancelled-passengers/show.blade.php`
8. `cancelled-bookings/print-voucher.blade.php`
9. `cancelled-passengers/print-voucher.blade.php`
10. Nav update
11. Run `vendor/bin/pint`
12. Run `php artisan test`

---

## 6. Access Control Matrix

| Role | Index | Details | Print Voucher | Notes |
|------|-------|---------|---------------|-------|
| Super Admin | All records | Any | Any | Full access |
| Co Admin | All records | Any | Any | Full access |
| Branch Manager | Branch only | Branch only | Branch only | Filtered by `cancellation_branch_id` |
| Fingerprint Admin | Branch only | Branch only | Branch only | Filtered by `cancellation_branch_id` |

Branch restriction logic:
```php
$user = auth()->user();
$isAdmin = $user->roles->pluck('name')
    ->intersect(['Super Admin', 'Co Admin'])->isNotEmpty();
if (!$isAdmin && $user->branch_id
    && $user->branch_id !== $record->cancellation_branch_id) {
    abort(403);
}
```

---

## 7. Data Flow After Implementation

### Booking Cancellation Flow (Updated)
```
Admin initiates cancellation
  → CancelledBooking created (status: "cancellation processing")
  → Booking marked is_cancelled = true
  → User sees "Pending Refunds" page

Branch Manager confirms cancellation
  → Deduction Payment + Voucher created (service charge)
  → Refund Payment + Voucher created
  → Status → "cancelled"
  → REDIRECT → /cancelled-bookings/{id}/print (Refund Voucher)
```

### Passenger Cancellation Flow (Updated)
```
Admin initiates cancellation
  → CancelledPassenger created (status: "cancellation processing")
  → Passenger marked is_cancelled = true, status = "Hold"
  → User sees "Pending Refunds" page

Branch Manager confirms cancellation
  → Deduction Payment + Voucher created
  → Adjustment Payment + Voucher created (credits invoice due)
  → Refund Payment + Voucher created
  → Status → "cancelled"
  → REDIRECT → /cancelled-passengers/{id}/print (Refund Voucher)
```

### Cancelled Records Index
```
/Cancelled-bookings
  ├── Tab: Cancelled Bookings
  │   └── All CancelledBooking records (status=cancelled)
  │       └── Click "View" → /cancelled-bookings/{id}
  │           └── Click "Print Voucher" → /cancelled-bookings/{id}/print
  │
  └── Tab: Cancelled Passengers
      └── All CancelledPassenger records (status=cancelled)
          └── Click "View" → /cancelled-passengers/{id}
              └── Click "Print Voucher" → /cancelled-passengers/{id}/print
```
