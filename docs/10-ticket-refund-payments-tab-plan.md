# Ticket Refund Payments Tab — Plan

## 1. Problem

Confirming payment from `pending-refunds?tab=tickets` (`POST /passengers/{id}/refund-pay-confirm`,
`RefundController@confirm:178-264`) already creates the records:

- one `payments` row (`passenger_id` set, `branch_id` = refund payment branch, `amount` = full `refund_payable`)
- one `vouchers` row (type `Ticket Refund - Payment` via `VoucherService`, linked `payment/invoice/booking`)
- `passengers.refund_payment_status` → `PAID`, `refund_payable` → `0`

But the paid record then disappears from pending-refunds (`PROCESSING` only) and has nowhere to be
viewed. A new tab is needed on the Cancelled Bookings page to list that history.

## 2. Goal

Add a third tab **`Ticket Refund Payments`** on the Cancelled Bookings page, placed **after
Cancelled Passengers**, mirroring the existing two tabs:

- Index table with **View + Print** action buttons (no voucher column in index).
- View (show) page per payment.
- Print voucher page per payment, same style as the other two tabs.
- Same currency sync (SAR/BDT toggle) as the other two tabs.

## 3. Data source

Query `Payment` records that have a voucher of transaction type `Ticket Refund - Payment`:

```php
Payment::with(['booking.customer', 'booking.invoice', 'passenger', 'branch', 'user', 'voucher.transactionType', 'voucher.branch'])
    ->whereHas('voucher.transactionType', fn ($q) => $q->where('name', 'Ticket Refund - Payment'));
```

Why `Payment` (not `Passenger where status=PAID`): the payment+voucher rows are the source of truth
for each confirm event (amount, method, branch, paid-by, date, voucher no).

## 4. Changes

### 4.1 Routes — `routes/booking-cancellation.php`

After the cancelled-passenger routes, same middleware
(`role:Super Admin,Co Admin,Branch Manager,Fingerprint Admin`):

| Method | URI | Action | Name |
|---|---|---|---|
| GET | `/ticket-refund-payments` | `CancelledRecordController@ticketRefundIndex` | `ticket-refund-payments.index` |
| GET | `/ticket-refund-payments/{payment}` | `ticketRefundShow` | `ticket-refund-payments.show` |
| GET | `/ticket-refund-payments/{payment}/print` | `ticketRefundPrint` | `ticket-refund-payments.print` |
| GET | `/api/ticket-refund-payments` | `ticketRefundIndexData` | `api.ticket-refund-payments.data` |

### 4.2 Controller — `app/Http/Controllers/CancelledRecordController.php`

Mirror the existing `bookingIndex/passengerIndex` pattern:

- `ticketRefundIndex()` → `view('cancelled-bookings.index', ['tab' => 'ticket-refunds', ...branches])`
- `buildTicketRefundQuery(Request)` → §3 query + branch scoping on `payments.branch_id`
  (`applyBranchFilter` + `ensureFingerprintAdminHasBranch`), `branch_id` filter, `search` filter
  (booking `invoice_id` / customer `name` / passenger `first_name,last_name`), `latest()->paginate(20)`
- `ticketRefundIndexData()` → map via `paginatedResponse()`:
  `id, invoice_id, customer, passenger (full name), payment_branch, refund_amount (float),
  payment_method, status ('paid'), paid_by (user name), date (Y-m-d), show_route, print_route`
- `ticketRefundShow(Payment $payment)` → branch check on `payment.branch_id` (new small helper;
  existing `ensureBranchAccess` only handles `cancellation_branch_id`), eager-load §3 relations
- `ticketRefundPrint(Payment $payment)` → same loads + `$currencyRate =
  CurrencyRateService::getRateForDate(now())?->rate ?? 0` for the view

### 4.3 Index view — `resources/views/cancelled-bookings/index.blade.php`

- Extend `$indexRoute/$apiRoute` ternaries to 3-way (`bookings` / `passengers` / `ticket-refunds`).
- Add tab link after Cancelled Passengers:
  `route('ticket-refund-payments.index')`, active when `$tab === 'ticket-refunds'`,
  label `Ticket Refund Payments`.
- Add `<template x-if="tab === 'ticket-refunds'">` table reusing Alpine `cancelledIndex()`
  (no JS changes). Columns — **no voucher column**:

```
Invoice ID | Customer | Passenger | Payment Branch | Refund Amount | Method | Status | Paid By* | Date | Actions (View + Print)
```

`*Paid By` gated by `canSeeInitiatedBy` (Super Admin / Co Admin), like `Cancelled By` / `Initiated By`.

### 4.4 Show view — `resources/views/ticket-refund-payments/show.blade.php` (new)

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

### 4.5 Print view — `resources/views/ticket-refund-payments/print-voucher.blade.php` (new)

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

## 5. Currency sync (same as other 2 tabs)

1. **Index:** `fmt()` → `Alpine.store('currency').format(amount, 2)` (`app.js` store, mode in
   `localStorage`, rate from `window.__currencyRate`). New table uses `fmt(row.refund_amount)`.
2. **Show:** `@currency($payment->amount, 2)` → `span.currency-display[data-sar]`, re-rendered by
   `convertAll()` on toggle (`AppServiceProvider@currency`, `resources/js/app.js`).
3. **Print:** `<script>window.__currencyRate = {{ (float) ($currencyRate ?? 0) }}</script>`
   (`$currencyRate` from controller) + `@currency()` spans — identical to both existing print blades.

Note: `confirm` writes `bdt_amount = 0`, and existing cancelled show/print blades don't pass the 4th
`@currency` BDT param — the new pages also convert live from SAR via rate.

## 6. Tests (TDD-first per AGENTS.md)

New `tests/Feature/TicketRefundPaymentsTabTest.php` (`RefreshDatabase`, SQLite in-memory):

- index renders `Ticket Refund Payments` tab for authorized role
- API returns paid `Ticket Refund - Payment` record with `show_route`/`print_route`
- non-matching transaction types excluded
- branch scoping (branch user sees only own `branch_id`; others 403/empty)
- show + print return 200

## 7. Verify

```bash
php artisan test --filter=TicketRefund
php artisan test
vendor/bin/pint
docker compose config --quiet
docker compose -f docker-compose.prod.yml config --quiet
```

## 8. Files changed

- `routes/booking-cancellation.php` (+4 routes)
- `app/Http/Controllers/CancelledRecordController.php` (+index/data/show/print/query)
- `resources/views/cancelled-bookings/index.blade.php` (+tab link + table)
- `resources/views/ticket-refund-payments/show.blade.php` (new)
- `resources/views/ticket-refund-payments/print-voucher.blade.php` (new)
- `tests/Feature/TicketRefundPaymentsTabTest.php` (new)
