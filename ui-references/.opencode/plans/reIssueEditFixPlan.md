# Re-Issued Ticket Edit — Payment Option Backend Mismatch Fix

## Problem summary

For a **refunded** issued ticket that is re-issued where **Payment By ≠ Customer**
(airline / employee / company), the UI intentionally locks the Payment Option
field to **Refund Adjustment**:

- `handleReIssuePaymentByChange()` in `resources/views/bookings/index.blade.php:5911`
  forces `payment_option = 'refund_adjustment'` when `refunded_ticket` is true and
  `payment_by !== 'customer'`.
- The Payment Option `<select>` is disabled (`:disabled="reIssueForm.payment_by !== 'customer'"`)
  in both the create re-issue form and the re-issue edit form.

The **create** path handles this consistently:

- `app/Http/Controllers/ReIssueController.php::store` (POST `/re-issue`)
  - lines 121-123: keeps `payment_option = $validated['payment_option']` (i.e.
    `refund_adjustment`) when `$wasRefunded` is true, regardless of `payment_by`.

The **edit/update** path is inconsistent:

- `app/Http/Controllers/TicketIssueController.php::edit` (PUT `ticket-edit`)
  - lines 280-282: forces `payment_option = null` whenever
    `array_key_exists('payment_by', $validated) && $validated['payment_by'] !== 'customer'`,
    even when the ticket was refunded and the form locked payment_option to
    `refund_adjustment`.

Because the database stores `null`, reloading the edit form falls back to:

- `resources/views/bookings/index.blade.php::5450` —
  `this.reIssueForm.payment_option = re.payment_option || 'customer_payment';`

so the form **displays and saves "Customer Payment"** for a non-customer, refunded
re-issue — the reported bug.

## Desired behavior (confirmed with user)

**Keep the Payment Option field locked to Refund Adjustment** for a refunded
ticket with non-customer Payment By, and **fix the backend/edit persistence** so
it stores and reflects Refund Adjustment consistently instead of ending up as
Customer Payment / null.

## Changes

### 1. `app/Http/Controllers/TicketIssueController.php` — `edit()` re-issued path

Derive refunded state and make `payment_option` persist `'refund_adjustment'`
for refunded non-customer instead of `null`.

- Add `$wasRefunded = (bool) $issuedTicket->latestRefundedTicket;` near the top
  of the `status === 're-issued'` block, alongside the existing
  `$oldPaymentBy` / `$oldPaymentOption` / `$oldTotalCustomerPayment` captures.
- Change the `payment_option` update (currently lines 280-282) so it resolves to:
  - `payment_by === 'customer'` → validated `payment_option`;
  - else if `$wasRefunded` → `'refund_adjustment'`;
  - else if `payment_by` explicitly present and `!== 'customer'` → `null`;
  - else → keep `$latestRe->payment_option`.
- Change the derived `$newPaymentOption` (currently lines 289-291) to match the
  same rules so the derived financial state and reload both reflect
  `refund_adjustment` for refunded non-customer.
- Keep the refund-adjustment **payment/voucher lifecycle** gated on
  `payment_by === 'customer'` (lines 307, 320) as-is — for non-customer payment
  there is no customer cash/voucher adjustment.

### 2. `resources/views/bookings/index.blade.php` — `populateReIssueEditForm()`

Make the display default consistent for refunded non-customer.

- Change the `payment_option` default (currently line 5450) so that when
  `wasRefunded` is true and `payment_by !== 'customer'` and `re.payment_option`
  is null/empty, it defaults to `'refund_adjustment'` instead of
  `'customer_payment'`.
- When `payment_by === 'customer'`, keep `re.payment_option || 'customer_payment'`.
- This is primarily a defence for any legacy / `null` rows; after the controller
  fix the DB will already hold `refund_adjustment`.

No change to the `:disabled` binding or `handleReIssuePaymentByChange()` — the
lock to Refund Adjustment is intentionally preserved.

## Tests (TDD)

Follow repo conventions (see `tests/Feature/BookingEditPackagePreloadTest.php`:
`RefreshDatabase` + manual `Schema::create` for the minimal tables/models needed).

Add a feature test covering `TicketIssueController::edit` for a re-issued ticket
that was refunded, with `payment_by` set to a non-customer value (e.g. `airline`):

- Send `PUT /bookings/{booking}/passengers/{passenger}/ticket-edit` with
  `payment_by = 'airline'` and `payment_option = 'refund_adjustment'`.
- Assert the stored `ReIssuedTicket::payment_option` is `'refund_adjustment'`
  (not `null`).
- Assert `payment_by` is `'airline'` and `total_customer_payment` is `0`.
- Optionally assert the edit form (`populateReIssueEditForm`) renders/indexes
  Refund Adjustment as the selected/default option for this case.

## Verification

- `vendor/bin/pint`
- `php artisan test`
- Grep to confirm no other path nulls `payment_option` for refunded non-customer.
