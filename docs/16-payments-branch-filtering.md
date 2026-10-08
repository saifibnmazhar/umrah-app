# Payments branch filtering: `payments.branch_id` vs `vouchers.user.branch_id`

Both columns are used to filter payments, on different screens. This note records
which screen filters on which column so the numbers can be reconciled.

## 1. The two columns

- **`payments.branch_id`** — the payment's own branch attribute. Stamped at creation
  in `app/Http/Controllers/BookingController.php:2747-2748`
  (`'branch_id' => auth()->user()->branch_id ?? $booking->booking_branch_id`)
  and copied onto the voucher in `app/Services/PaymentService.php:33-66`
  (`'branch_id' => $processedData['branch_id']` for both records).
  Creator-branch-biased by design: a branch user stamping a payment for another
  branch's booking still records their own branch.
- **`vouchers.user.branch_id`** ("payments.users.branch_id") — the branch of the user
  who received/created the voucher. Reached via `whereHas('vouchers.user', …)` and
  displayed as `receive_at` / `receive_by` (e.g.
  `app/Http/Controllers/BranchWiseReportController.php:463-464`,
  `routes/web.php:452-455,495-500`).

## 2. Per-screen matrix

| Screen | Filter column | Branch source |
|---|---|---|
| `PaymentController@index` (`app/Http/Controllers/PaymentController.php:40-46`, `/payments`) | `payments.branch_id = request branch_id` (`other` = `whereNull`) | Request only, no `auth()` lookup |
| Dashboard (`app/Http/Controllers/DashboardController.php:411-433`) | `payments.branch_id = auth()->user()->branch_id` | User-derived |
| Payment-receiving main (`routes/web.php:377-412`, `report.payment-receiving`) | `payments.branch_id = auth()->user()->branch_id` on all totals + list queries; no `?branch_id=` server-side | User-derived |
| Payment-receiving modal (`resources/views/reports/payment-receiving.blade.php:134-140,247,264`) | Client-side `receive_branch_id == selectedBranch`, where `receive_branch_id = v.user.branch_id` (`routes/web.php:455`) | Request (client), but on the *other* column — mismatched with the server scope above |
| Payment-receiving print (`routes/web.php:473-519`) | Server `payments.branch_id = user.branch` (`:477-478`), then in-memory `receive_branch_id (= v.user.branch_id) == ?branch_id=` (`:515-519`, `central` = null) | Both stacked, on different columns |
| Branch-wise `index` (`app/Http/Controllers/BranchWiseReportController.php:36-37,50-52,216-230,418-446`) | `whereHas('vouchers.user', branch_id = $branchId)` with `$branchId = $userBranchId ?: $request->branch_id` | User-overrides-request, on receiver's branch |
| Branch-wise `paymentHistoryPrint` (`app/Http/Controllers/BranchWiseReportController.php:489-508`) | Same `whereHas('vouchers.user', …)` but `$branchId = $request->branch_id` only | Request only (inconsistent sibling of `index`) |
| Ticket-refund list (`app/Http/Controllers/CancelledRecordController.php:376-404`) | `payments.branch_id = user.branch` (`applyBranchFilter:388`) plus `payments.branch_id = request branch_id` (`:402-404`) | Both stacked, own column |
| Pending-refunds tickets tab (`app/Http/Controllers/BookingCancellationViewController.php:120-124`) | `branch_id = user.branch`, else `?branch_id=` | User-preempts-request |

## 3. Why numbers can disagree

Filtering by `payments.branch_id` follows the creation stamp (creator's branch,
falling back to the booking's branch for central users). Filtering by
`vouchers.user.branch_id` follows who received it — which is what the
branch-wise and payment-receiving tables display as `receive_at`. The same branch
selection can therefore return different totals across `/payments`, the
dashboard, branch-wise, and payment-receiving.

## 4. Recommendation (open)

Standardize on `payments.branch_id`, the payment's own attribute and what
`PaymentController`, the dashboard, and the ticket-refund queries already use.
If adopted, confirm whether the `receive_at` display column changes with it.
