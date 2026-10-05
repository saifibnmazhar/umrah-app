# AUDIT SUMMARY - umrah-app

**Date:** 2026-10-05 | **Scope:** full read-only audit of `C:\Users\cdrmo\projects\umrah-app` (Laravel 12.66.0) | **Verdict:** feature-rich, well-tested domain logic, **but authorization and money-path integrity need work before further role delegation.**

---

## 1. Counts

| Category | HIGH | MEDIUM | LOW | INFO | Total |
|---|---:|---:|---:|---:|---:|
| Authentication / session / deps (SEC) | 1 | 1 | 5 | 1 | **8** |
| Authorization / IDOR (AUTH) | 3 | 11 | 0 | 2 | **16** |
| XSS / CSRF | 0 | 1 | 2 | 4 | **7** |
| Business logic / money / state (BIZ) | 7 | 28 | 8 | 2 | **45** |
| Database / schema integrity (DB) | 0 | 6 | 2 | 1 | **9** |
| Performance (PERF) | 0 | 4 | 2 | 1 | **7** |
| Maintainability (MAINT) | 2 | 8 | 4 | 1 | **15** |
| Testing (TEST) | 0 | 4 | 1 | 1 | **6** |
| **Total** | **13** | **63** | **24** | **13** | **113** |

Confidence: **Confirmed 102 - Likely 6 - Needs Verification 5** (per-finding confidence is in `AUDIT-FINDINGS.md`).
One earlier candidate (**"ticket/refund routes have no role middleware"**) was **withdrawn as a false positive** - those routes sit inside a `role:` group at `routes/web.php:580`.

## 2. Baseline facts

| Metric | Value |
|---|---|
| Routes | 350 (264 auth+role, 53 auth-only, 26 role-only, 7 guest, 0 public-mutating) |
| Code | 198 app PHP files / 22,620 LOC, 60 models, 57 controllers, 153 Blade views / 40,027 LOC, 650 route LOC |
| Database (live dev) | 69 tables, 680 columns, 166 FKs (all indexed), 262 indexes, 149 migrations |
| Data volume | 794 bookings, 1,240 passengers, 3,161 documents, 1,802 payments, 81 ticket fares |
| Tests | **723 passed, 0 failed, 2,833 assertions, 304 s** (115 files, 704 `test*` methods) |
| Dependencies | composer audit: **4 advisories / 3 packages**, npm audit: **2 (1 high, 1 low)** |
| App cache usage | **0** (Redis is configured for prod but nothing calls `Cache::` / `->remember()`) |
| Secrets | none hardcoded in the tracked tree; `.env` present locally (untracked, values not reproduced) |

## 3. Top 10 risks

1. **Double-refund / money-loss races** - refund amount read before `lockForUpdate` (`RefundController.php:206` read, `:222` lock), cancellation confirm has no row lock and writes its guard last (`CancellationService.php:113,117,217`), refund cap is an unlocked `SUM` check (`RefundCapService`). *(BIZ L-01, L-02, S-03, M-13)*
2. **Privilege split on money** - non-admins can put a discount on the invoice (`BookingController.php:1453` vs `:1644`) and any authenticated user can record payments for any branch (`routes/web.php:186`, `storePayment` has no `ensureBranchAccess`). *(AUTH-03, AUTH-02)*
3. **Financial data visible to every role** - `/api/bookings/passengers` returns profit, cost and invoice totals to any logged-in user; `net_fare` (supplier cost) is embedded in page HTML and in two fare APIs. *(AUTH-01, AUTH-04, AUTH-05, AUTH-06)*
4. **Broken money math** - re-issue payments inflate invoice balance (`ReIssueController.php:233`), `updatePayment` recomputes `paid_amount` with a broader formula than `InvoiceService`, and `bdt_amount` is supplied by the client instead of computed. *(BIZ M-05, M-06, M-02)*
5. **No brute-force protection** - `POST /login` (`routes/web.php:69`) has no throttle and the app defines no `RateLimiter`. *(SEC-01)*
6. **Document IDOR / PII** - `GET /documents/{id}/download` performs no ownership or branch check; passenger document downloads skip the branch check. *(AUTH-07, AUTH-08)*
7. **Partial persistence on passenger update** - five money writes with no transaction, catch block returns an error with no rollback and no log (`PassengerController.php:606-685`). *(BIZ T-02)*
8. **Two live 500s** - `transaction-types.store/update` validate against the dropped table name `transaction_type` (proved with SQL error 1146) and `invoices/print` calls an undefined route name. *(DB-03, MAINT-02)*
9. **Destructive deletes without transactions** - `removePassenger` (4 writes), `PassengerController::destroy` and `BookingController::destroy` skip FK-restricted children and leave files behind. *(BIZ T-04, T-05, T-06)*
10. **Maintainability drag** - one 7,507-line view (459 KB), a 3,020-line controller, 103 of 120 `catch` blocks that never log, 13 unused Blade components. *(MAINT-01, MAINT-06, MAINT-08, MAINT-04)*

## 4. Top 10 actions (in order)

| # | Action | Fixes | Verify with |
|---|---|---|---|
| 1 | Add `RateLimiter` to `POST /login` (5/min/IP) | SEC-01 | 6 rapid bad logins -> 429 |
| 2 | Gate financial fields server-side in `passengerData`/`passengerSummary`; drop `net_fare` for non-financial roles | AUTH-01/04/05/06 | JSON test asserting `data.0.profit` absent for Delivery Staff |
| 3 | Add `role:` + `ensureBranchAccess` to `bookings.payment.store`, `bookings.print`, document upload/download | AUTH-02/07/08/09 | `assertForbidden` tests for low-privilege users |
| 4 | Compute the invoice discount only when `isAdminRole()` (reuse the `:1453` gate at `:1644`) | AUTH-03 | non-admin POST discount -> invoice total unchanged |
| 5 | Put refund/cancellation guards inside the transaction, `lockForUpdate` invoice/passenger rows, lock the refund-cap `SUM` | BIZ L-01/L-02/S-03/M-13 | two concurrent confirm requests -> exactly one payout |
| 6 | Compute `bdt_amount` server-side; use one `paid_amount` formula (`InvoiceService`) everywhere | BIZ M-02/M-05/M-06 | reconciliation test: stored = recomputed |
| 7 | Wrap `PassengerController::update` in `DB::transaction` with rollback + `Log::error` | BIZ T-02 | injected failure -> no partial rows |
| 8 | Fix `unique:transaction_type` -> `transaction_types`; decide `invoices.print` (restore resource with `role:` or delete) | DB-03, MAINT-02 | POST transaction type -> 302, not 500 |
| 9 | Add indexes `documents(owner_type,owner_id)`, `payments(created_at)`, `bookings(created_at)`; log in the 103 silent catches | DB-01/02, MAINT-08 | `EXPLAIN` -> `ref`, not `ALL` |
| 10 | Extract the 4,650-line `<script>` from `bookings/index.blade.php`; add authz/IDOR tests | MAINT-01, TEST-01..04 | `php artisan test` stays green |

## 5. What is verified clean

* CSRF: 70 state-changing `fetch` calls all carry a token; no state-changing GET routes; the 23 non-GET forms without `@csrf` are all JS-intercepted.
* Every view-hidden sensitive action has a matching route `role:` or controller `abort(403)`.
* No hardcoded secrets in the tracked tree; the prod sample keeps `APP_DEBUG=false` and `SESSION_SECURE_COOKIE=true`.
* Login regenerates the session; inactive accounts are invalidated by `CheckActive`.
* 166 FKs all indexed, every table has a PRIMARY key, no table is orphaned from the schema vocabulary.
* Report queries in `VisaAgentReportController` and `ProfitLossReportController` eager-load correctly (spot-checked).

## 6. Not audited (out of scope)

Git history secrets (explicitly excluded), production server / Cloudflare config (only repo files read), runtime/load/stress testing, dynamic exploitation (no exploit was run - findings are code + SQL evidence), UI/UX parity with `ui-references/`, accessibility, browser/E2E behaviour, business-requirements correctness, real production data (only the local dev DB was queried).

---
*Full narrative: `AUDIT-REPORT.md` | Master findings: `AUDIT-FINDINGS.md` | Detail: `AUDIT-SECURITY.md`, `AUDIT-DATABASE.md`, `AUDIT-PERFORMANCE.md`, `AUDIT-TESTING.md` | Inventory: `AUDIT-INVENTORY.md`*
