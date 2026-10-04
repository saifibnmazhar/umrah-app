# Ticket Refund Payments Tab — Plan

## 1. Problem

Confirming payment from `pending-refunds?tab=tickets` (`POST /passengers/{id}/refund-pay-confirm`,
`RefundController@confirm`) creates:

- one `payments` row (`passenger_id` set, `branch_id` = refund payment branch, `amount` = full `refund_payable`)
- one `vouchers` row (type `Ticket Refund - Payment` via `VoucherService`, linked `payment/invoice/booking`)

The paid record then disappears from pending-refunds (`PROCESSING` only) and has nowhere to be viewed.
A new tab is needed on the Cancelled Bookings page to list that history.

Additionally, the refund payment workflow state (`refund_payment_status`, `refund_payment_branch_id`)
currently lives on the `passengers` table. This mixes passenger data with workflow state. These columns
have been rolled back from the database and need to be replaced with a dedicated `refund_payment_requests`
table.

## 2. Goal

**Part A:** Create a `refund_payment_requests` table to own the refund payment workflow state,
removing `refund_payment_status` and `refund_payment_branch_id` from `passengers`.
Keep `refund_payable` on `passengers` (it's a financial balance).

**Part B:** Add a third tab **Ticket Refund Payments** on the Cancelled Bookings page, placed after
Cancelled Passengers, mirroring the existing two tabs:

- Index table with **View + Print** action buttons (no voucher column in index).
- View (show) page per payment.
- Print voucher page per payment, same style as the other two tabs.
- Same currency sync (SAR/BDT toggle) as the other two tabs.

---

## Part A: Refund Payment Requests Table

### A.1 Enum — `RefundPaymentRequestStatus`

**File:** `app/Enums/RefundPaymentRequestStatus.php` (new, replaces `RefundPaymentStatus.php`)

MySQL `enum` column + PHP backed enum (matching existing pattern like `gender`, `passenger_type`):

```php
enum RefundPaymentRequestStatus: string
{
    case PROCESSING = 'processing';
    case PAID       = 'paid';
    case REVERTED   = 'reverted';
}
```

State machine:

```
(branch assignment) ──> PROCESSING ──confirm()──> PAID
                            │
                         revert()
                            │
                            v
                         REVERTED
```

Each branch assignment creates a **new** request record. Reverted is terminal.
To re-process after revert, assign a branch again (creates a new request).

### A.2 Schema — `refund_payment_requests` table

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `passenger_id` | bigint FK → passengers | |
| `booking_id` | bigint FK → bookings | Denormalized for query convenience |
| `branch_id` | bigint FK → branches, nullable | Branch assigned to process the payment |
| `status` | **enum('processing','paid','reverted')** | MySQL native enum, cast to PHP enum |
| `refund_payable_snapshot` | decimal(14,6) | Snapshot of `passenger.refund_payable` at request creation |
| `assigned_by` | bigint FK → users, nullable | Who assigned the branch |
| `confirmed_by` | bigint FK → users, nullable | Who confirmed the payment |
| `payment_id` | bigint FK → payments, nullable | Link to created Payment after confirmation |
| `voucher_id` | bigint FK → vouchers, nullable | Link to created Voucher after confirmation |
| `remarks` | text, nullable | |
| `assigned_at` | timestamp, nullable | When branch was assigned |
| `confirmed_at` | timestamp, nullable | When payment was confirmed |
| `reverted_at` | timestamp, nullable | When reverted |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

### A.3 Model — `RefundPaymentRequest`

**File:** `app/Models/RefundPaymentRequest.php` (new)

- `$fillable`: `passenger_id`, `booking_id`, `branch_id`, `status`, `refund_payable_snapshot`,
  `assigned_by`, `confirmed_by`, `payment_id`, `voucher_id`, `remarks`,
  `assigned_at`, `confirmed_at`, `reverted_at`
- `$casts`: `status` → `RefundPaymentRequestStatus::class`,
  `refund_payable_snapshot` → `'decimal:6'`, timestamps
- Relationships: `passenger()`, `booking()`, `branch()`, `assignedBy()`,
  `confirmedBy()`, `payment()`, `voucher()`

### A.4 Migration

**File:** `database/migrations/2026_09_10_000001_refund_payment_requests_and_clean_passengers.php`

Single migration, three steps:

1. **Create `refund_payment_requests` table** (schema from A.2)
2. **Drop old columns from `passengers`** (idempotent — only if they exist):
   - `refund_payment_branch_id` (drop foreign key first)
   - `refund_payment_status`
3. **No data migration** — production columns were already rolled back.

Down migration: drop `refund_payment_requests`, re-add old columns to `passengers`.

### A.5 Delete old files

| File | Reason |
|---|---|
| `database/migrations/2026_09_03_000002_add_refund_payment_fields_to_passengers_table.php` | Replaced by new migration |
| `app/Enums/RefundPaymentStatus.php` | Replaced by `RefundPaymentRequestStatus` |

### A.6 `Passenger` model changes

- Remove `refund_payment_branch_id`, `refund_payment_status` from `$fillable`
- Remove `refund_payment_status => RefundPaymentStatus::class` from `$casts`
- Remove `refundPaymentBranch()` relationship
- Add:
  ```php
  public function latestRefundPaymentRequest(): HasOne
  {
      return $this->hasOne(RefundPaymentRequest::class)->latestOfMany();
  }
  ```
- Keep: `refund_payable`, `refundPayablePayments()`, `verifyRefundPayable()`,
  `increaseRefundPayable()`, `decreaseRefundPayable()`, `assertRefundPayableInSync()`

### A.7 `RefundController` changes

**`assignBranch()`** — Create new `RefundPaymentRequest`:
```php
RefundPaymentRequest::create([
    'passenger_id'           => $passenger->id,
    'booking_id'             => $passenger->booking_id,
    'branch_id'              => $validated['branch_id'],
    'status'                 => RefundPaymentRequestStatus::PROCESSING,
    'refund_payable_snapshot' => $passenger->refund_payable,
    'assigned_by'            => auth()->id(),
    'assigned_at'            => now(),
]);
```
Guard: abort if a `PROCESSING` request already exists for this passenger.

**`confirm()`** — Find `PROCESSING` request, use its `branch_id` for Payment/Voucher:
```php
$refundRequest->update([
    'status'       => RefundPaymentRequestStatus::PAID,
    'payment_id'   => $payment->id,
    'voucher_id'   => $voucher->id,
    'confirmed_by' => auth()->id(),
    'confirmed_at' => now(),
]);
```

**`revert()`** — Find `PROCESSING` request:
```php
$refundRequest->update([
    'status'      => RefundPaymentRequestStatus::REVERTED,
    'reverted_at' => now(),
]);
```

Replace `RefundPaymentStatus` import with `RefundPaymentRequestStatus`.

### A.8 `BookingCancellationViewController::pendingRefunds()` changes

Replace the Passenger query (lines 111-126) with:
```php
$ticketRefundQuery = RefundPaymentRequest::with([
        'passenger.booking.customer',
        'passenger.booking.invoice',
        'branch',
        'assignedBy',
    ])
    ->where('status', RefundPaymentRequestStatus::PROCESSING)
    ->whereHas('passenger', fn ($q) => $q->where('refund_payable', '>', 0));

if (auth()->user()->branch_id) {
    $ticketRefundQuery->where('branch_id', auth()->user()->branch_id);
} elseif ($request->filled('branch_id')) {
    $ticketRefundQuery->where('branch_id', $request->branch_id);
}

$ticketRefunds = $ticketRefundQuery->latest()->paginate(20);
```

Replace `RefundPaymentStatus` import with `RefundPaymentRequestStatus`.

### A.9 `pending-refunds/index.blade.php` — Ticket Refunds tab

Iterates `RefundPaymentRequest` records. Field mapping:

| Old (Passenger) | New (RefundPaymentRequest) |
|---|---|
| `$tp->booking?->invoice_id` | `$tp->passenger?->booking?->invoice_id` |
| `$tp->booking?->customer?->name` | `$tp->passenger?->booking?->customer?->name` |
| `$tp->first_name . ' ' . $tp->last_name` | `$tp->passenger?->first_name . ' ' . $tp->passenger?->last_name` |
| `$tp->booking?->bookingBranch?->name` | `$tp->passenger?->booking?->bookingBranch?->name` |
| `$tp->refundPaymentBranch?->name` | `$tp->branch?->name` |
| `$tp->refund_payable` | `$tp->passenger?->refund_payable` |
| Form action with `$tp->id` (passenger ID) | Same route with `$tp->passenger?->id` |
| Confirm modal `openConfirmRefundModal({{ $tp->id }}, ...)` | `openConfirmRefundModal({{ $tp->passenger?->id }}, ...)` |

Revert confirmation message: "Status will return to pending." → "This action cannot be undone."

### A.10 `bookings/index.blade.php` — 3 lines

**Line 171** — Data passed to Alpine:
```php
// Old:
'refund_payment_status' => $p->refund_payment_status?->value ?? null,
// New:
'refund_payment_request_status' => $p->latestRefundPaymentRequest?->status?->value ?? null,
```

**Line 1590** — "Pay Refund" button visibility:
```html
<!-- Old: shows when null or 'pending' -->
x-if="...?.refund_payable > 0 && (!...?.refund_payment_status || ...?.refund_payment_status === 'pending')"

<!-- New: shows when no active processing request (null, paid, or reverted) -->
x-if="...?.refund_payable > 0 && (!...?.refund_payment_request_status || ...?.refund_payment_request_status === 'paid' || ...?.refund_payment_request_status === 'reverted')"
```

**Line 7469** — After branch assignment:
```javascript
// Old:
this.passengersTicketData[idx].refund_payment_status = 'processing';
// New:
this.passengersTicketData[idx].refund_payment_request_status = 'processing';
```

### A.11 Tests — `tests/Feature/RefundPaymentTest.php`

- Replace `refund_payment_branch_id`/`refund_payment_status` in passengers schema with
  `refund_payment_requests` table schema
- Update all assertions to use `RefundPaymentRequest` model instead of passenger columns
- Update imports: `RefundPaymentStatus` → `RefundPaymentRequestStatus`

---

## Part B: Ticket Refund Payments Tab

### B.1 Data source

Query `Payment` records that have a voucher of transaction type `Ticket Refund - Payment`:

```php
Payment::with(['booking.customer', 'booking.invoice', 'passenger', 'branch', 'user', 'voucher.transactionType', 'voucher.branch'])
    ->whereHas('voucher.transactionType', fn ($q) => $q->where('name', 'Ticket Refund - Payment'));
```

Why `Payment` (not `Passenger where status=PAID`): the payment+voucher rows are the source of truth
for each confirm event (amount, method, branch, paid-by, date, voucher no).

### B.2 Routes — `routes/booking-cancellation.php`

After the cancelled-passenger routes, same middleware
(`role:Super Admin,Co Admin,Branch Manager,Fingerprint Admin`):

| Method | URI | Action | Name |
|---|---|---|---|
| GET | `/ticket-refund-payments` | `CancelledRecordController@ticketRefundIndex` | `ticket-refund-payments.index` |
| GET | `/ticket-refund-payments/{payment}` | `ticketRefundShow` | `ticket-refund-payments.show` |
| GET | `/ticket-refund-payments/{payment}/print` | `ticketRefundPrint` | `ticket-refund-payments.print` |
| GET | `/api/ticket-refund-payments` | `ticketRefundIndexData` | `api.ticket-refund-payments.data` |

### B.3 Controller — `app/Http/Controllers/CancelledRecordController.php`

Mirror the existing `bookingIndex/passengerIndex` pattern:

- `ticketRefundIndex()` → `view('cancelled-bookings.index', ['tab' => 'ticket-refunds', ...branches])`
- `buildTicketRefundQuery(Request)` → §B.1 query + branch scoping on `payments.branch_id`
  (`applyBranchFilter` + `ensureFingerprintAdminHasBranch`), `branch_id` filter, `search` filter
  (booking `invoice_id` / customer `name` / passenger `first_name,last_name`), `latest()->paginate(20)`
- `ticketRefundIndexData()` → map via `paginatedResponse()`:
  `id, invoice_id, customer, passenger (full name), payment_branch, refund_amount (float),
  payment_method, status ('paid'), paid_by (user name), date (Y-m-d), show_route, print_route`
- `ticketRefundShow(Payment $payment)` → branch check on `payment.branch_id` (new
  `ensurePaymentBranchAccess` helper; existing `ensureBranchAccess` only handles
  `cancellation_branch_id`), eager-load §B.1 relations
- `ticketRefundPrint(Payment $payment)` → same loads + `$currencyRate =
  CurrencyRateService::getRateForDate(now())?->rate ?? 0` for the view

New private helpers:
```php
private function ensurePaymentBranchAccess(Payment $payment): void
{
    $this->ensureFingerprintAdminHasBranch();
    if (auth()->user()->branch_id
        && auth()->user()->branch_id !== $payment->branch_id) {
        abort(403);
    }
}
```

### B.4 Index view — `resources/views/cancelled-bookings/index.blade.php`

- Extend `$indexRoute/$apiRoute` ternaries to 3-way:
  ```php
  $indexRoute = match($tab) {
      'passengers' => 'cancelled-passengers.index',
      'ticket-refunds' => 'ticket-refund-payments.index',
      default => 'cancelled-bookings.index',
  };
  $apiRoute = match($tab) {
      'passengers' => 'api.cancelled-passengers.data',
      'ticket-refunds' => 'api.ticket-refund-payments.data',
      default => 'api.cancelled-bookings.data',
  };
  ```
- Add tab link after Cancelled Passengers:
  `route('ticket-refund-payments.index')`, active when `$tab === 'ticket-refunds'`,
  label `Ticket Refund Payments`.
- Add `<template x-if="tab === 'ticket-refunds'">` table reusing Alpine `cancelledIndex()`
  (no JS changes). Columns — **no voucher column**:

```
Invoice ID | Customer | Passenger | Payment Branch | Refund Amount | Method | Status | Paid By* | Date | Actions (View + Print)
```

`*Paid By` gated by `canSeeInitiatedBy` (Super Admin / Co Admin), like `Cancelled By` / `Initiated By`.

### B.5 Show view — `resources/views/ticket-refund-payments/show.blade.php` (new)

Clone of `cancelled-passengers/show.blade.php`, variable `$payment`:

```
← Back to Cancelled Bookings                              [Print Refund Voucher]
Ticket Refund Payment Details  [Paid]
Passenger: <name> | Invoice: <invoice_id>

Passenger Information              Refund Payment Summary
- Name / Passport / DOB / Mobile   - Refund Amount (@currency)
- Booking Invoice / Customer       - Method / Voucher No / Payment Branch / Paid By / Date

Financial Transactions: [Refund Payment] Amount / Method / Voucher
```

### B.6 Print view — `resources/views/ticket-refund-payments/print-voucher.blade.php` (new)

Clone of `cancelled-passengers/print-voucher.blade.php` (same standalone CSS):

```
[Back] [Print Voucher]
BIN MISHAL GLOBAL SERVICES LTD.
REFUND VOUCHER (ticket refund payment)
Voucher No / Date / Invoice No / Passenger
Customer (name, mobile, iqama/passport) | Payment Branch / Paid By / Date
Financial Summary table (Refund Amount highlighted green)
Payment Details (Method / Transaction ID)
Received By (If Cash: Name/Passport/Iqama/Mobile | If Bank: Bank/Beneficiary/IBAN)
Prepared By / Authorized By signatures + disclaimer
popstate back → ticket-refund-payments.index
```

### B.7 Currency sync (same as other 2 tabs)

1. **Index:** `fmt()` → `Alpine.store('currency').format(amount, 2)` (`app.js` store, mode in
   `localStorage`, rate from `window.__currencyRate`). New table uses `fmt(row.refund_amount)`.
2. **Show:** `@currency($payment->amount, 2)` → `span.currency-display[data-sar]`, re-rendered by
   `convertAll()` on toggle (`AppServiceProvider@currency`, `resources/js/app.js`).
3. **Print:** `<script>window.__currencyRate = {{ (float) ($currencyRate ?? 0) }}</script>`
   (`$currencyRate` from controller) + `@currency()` spans — identical to both existing print blades.

Note: `confirm` writes `bdt_amount = 0`, and existing cancelled show/print blades don't pass the 4th
`@currency` BDT param — the new pages also convert live from SAR via rate.

### B.8 Tests (TDD-first per AGENTS.md)

New `tests/Feature/TicketRefundPaymentsTabTest.php` (`RefreshDatabase`, SQLite in-memory):

- index renders `Ticket Refund Payments` tab for authorized role
- API returns paid `Ticket Refund - Payment` record with `show_route`/`print_route`
- non-matching transaction types excluded
- branch scoping (branch user sees only own `branch_id`; others 403/empty)
- show + print return 200
- pagination works correctly (20 per page)
- search by invoice_id, customer name, passenger name
- branch filter via `branch_id` query parameter
- non-existent payment returns 404
- payment without a voucher is excluded
- different roles: Branch Manager sees only own branch, Super Admin sees all
- currency rate is passed to print view

---

## 3. Verify

```bash
php artisan test --filter=RefundPayment
php artisan test --filter=TicketRefund
php artisan test
vendor/bin/pint
docker compose config --quiet
docker compose -f docker-compose.prod.yml config --quiet
```

## 4. Files changed

### Part A (Architectural)

| # | File | Action |
|---|---|---|
| 1 | `database/migrations/2026_09_03_000002_add_refund_payment_fields_to_passengers_table.php` | Delete |
| 2 | `database/migrations/2026_09_10_000001_refund_payment_requests_and_clean_passengers.php` | New |
| 3 | `app/Enums/RefundPaymentStatus.php` | Delete |
| 4 | `app/Enums/RefundPaymentRequestStatus.php` | New |
| 5 | `app/Models/RefundPaymentRequest.php` | New |
| 6 | `app/Models/Passenger.php` | Modify (remove 2 fillable, 1 cast, 1 relationship; add 1 relationship) |
| 7 | `app/Http/Controllers/RefundController.php` | Modify (rewrite 3 methods) |
| 8 | `app/Http/Controllers/BookingCancellationViewController.php` | Modify (rewrite pendingRefunds query) |
| 9 | `resources/views/pending-refunds/index.blade.php` | Modify (ticket refunds tab field mapping) |
| 10 | `resources/views/bookings/index.blade.php` | Modify (3 lines: data, button visibility, status assignment) |
| 11 | `tests/Feature/RefundPaymentTest.php` | Modify (replace schema + assertions) |

### Part B (Tab)

| # | File | Action |
|---|---|---|
| 12 | `routes/booking-cancellation.php` | Modify (+4 routes) |
| 13 | `app/Http/Controllers/CancelledRecordController.php` | Modify (+4 methods, +2 helpers) |
| 14 | `resources/views/cancelled-bookings/index.blade.php` | Modify (+tab link +table +3-way ternary) |
| 15 | `resources/views/ticket-refund-payments/show.blade.php` | New |
| 16 | `resources/views/ticket-refund-payments/print-voucher.blade.php` | New |
| 17 | `tests/Feature/TicketRefundPaymentsTabTest.php` | New |

### Files NOT changed (refund_payable stays on passengers)

| File | Reason |
|---|---|
| `app/Services/CancellationService.php` | Bulk-zeroes `refund_payable` |
| `app/Services/PassengerCancellationService.php` | Reads/writes `refund_payable` |
| `app/Http/Controllers/ReIssueController.php` | Reads `refund_payable` |
| `app/Http/Controllers/TicketIssueController.php` | Reads `refund_payable` |
| `app/Http/Controllers/TicketRequestController.php` | Reads `refund_payable` |
| `app/Console/Commands/VerifyRefundPayableCommand.php` | Uses `refund_payable` |
| `resources/views/refunds/confirmation.blade.php` | Reads `refund_payable` |
| `resources/views/re-issues/confirmation.blade.php` | Reads `refund_payable` |
| 8 test files using `refund_payable` | Column stays |

## 5. Implementation order

1. Delete old migration file + old enum
2. Create migration, new enum, new model
3. Update `Passenger` model
4. Rewrite `RefundController` (assignBranch, confirm, revert)
5. Rewrite `BookingCancellationViewController::pendingRefunds()`
6. Update `pending-refunds/index.blade.php`
7. Update `bookings/index.blade.php` (3 lines)
8. Update `RefundPaymentTest.php`
9. Run `php artisan test --filter=RefundPayment`
10. Implement Part B (routes, controller, views, tests)
11. Run full test suite + Pint
