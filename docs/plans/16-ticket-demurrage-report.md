# Ticket Demurrage Report — Plan

## 1. Problem

Re-issue and refund flows already record `payment_by ∈ customer | airline | employee | company`
on `re_issued_tickets` and `refunded_tickets` (migration `2026_08_20_000001_add_company_to_payment_by_enums.php`).
When set to `employee` or `company`, **no payment/voucher/invoice row is created** — the value only
exists as a column consumed by cost reports (`ProfitCalculationService`, `CostTrackingService`).

There is currently **no "Ticket Demurrage Report"** anywhere in the codebase (0 matches in code,
views, migrations, git history). There is also no way for Super Admin / Co Admin to move part of an
employee-borne re-issue/refund cost onto the company.

## 2. Goal

Build a new **Ticket Demurrage Report** with two tabs:

- **Employee tab** — re-issues/refunds where `payment_by = employee`, grouped by employee
  (`user_id` on the record), with a per-employee drill-down to individual records.
  Super Admin / Co Admin can **reduce** the amount on an individual record; the reduction
  moves to the Company tab (company pays it).
- **Company tab** — records where `payment_by = company` **plus every active reduction**
  taken from employees.

The reduction is **demurrage-report only**: Profit/Loss, `CostTrackingService` and dashboards
remain unchanged.

## 3. Decisions (confirmed)

| Question | Decision |
|---|---|
| Refund amount used as "total cost" | `refund_compensation` (= `net_fare − iata_refunded_amount`) |
| Re-issue amount used as "total cost" | `total_cost` |
| Employee identity | `user_id` on `re_issued_tickets` / `refunded_tickets` (record creator) |
| Reduction mechanism | **Per-record adjustment** with audit trail (amount, who, when, reason), revertible |
| Financial impact outside the report | **None** — demurrage report only |
| Report view access | `Super Admin`, `Co Admin`, `Ticket Admin` |
| Reduce / revert access | `Super Admin`, `Co Admin` only |
| Employee tab layout | Grouped per employee, **expand → drill-down** of individual records; cap enforced **per record** |
| Export | **Print only** (dedicated print route + print blade, per-tab print button) |

## 4. Business Rules

1. Employee tab sources only records with `payment_by = employee`:
   - re-issue amount = `total_cost`, date = `re_issue_date`
   - refund amount = `refund_compensation`, date = `refund_date`
2. Company tab sources:
   - records with `payment_by = company` (same amount rules)
   - every **active** (non-reverted) reduction row
3. A reduction may only target a record with `payment_by = employee`.
4. Cap: `SUM(active reductions on record) + new reduction ≤ current record amount`
   (re-issue `total_cost` / refund `refund_compensation`).
5. Reductions are never hard-deleted; revert sets `reverted_at` / `reverted_by`.
6. Editing a re-issue (`TicketIssueController::edit`) must reject a new `total_cost` that is below
   the sum of active reductions on that record (422).
7. Report rows: employee net = original − reduced; company total = company records + Σ reductions.

## 5. Data Model

### Migration — `database/migrations/2026_XX_create_demurrage_adjustments_table.php`

```
demurrage_adjustments
  id
  re_issued_ticket_id   FK -> re_issued_tickets, nullable, restrictOnDelete
  refunded_ticket_id    FK -> refunded_tickets,  nullable, restrictOnDelete
  reduction_amount      decimal(14,6)
  reason                string nullable
  adjusted_by           FK -> users, restrictOnDelete
  reverted_at           timestamp nullable
  reverted_by           FK -> users nullable
  timestamps
  index(re_issued_ticket_id)
  index(refunded_ticket_id)
```

Exactly one of `re_issued_ticket_id` / `refunded_ticket_id` must be non-null (validated in the
controller; optional DB check constraint if supported).

### Model — `app/Models/DemurrageAdjustment.php` (new)

- `$fillable`: `re_issued_ticket_id`, `refunded_ticket_id`, `reduction_amount`, `reason`,
  `adjusted_by`, `reverted_at`, `reverted_by`
- Relations: `reIssuedTicket()`, `refundedTicket()`, `adjustedBy()`, `revertedBy()`
- `scopeActive($q)`: `whereNull('reverted_at')`

## 6. Backend

### Controller — `app/Http/Controllers/TicketDemurrageReportController.php` (new)

Mirrors `PendingOutboundReportController` (`index()` view + `data()` JSON).

- **`index(Request $request)`** — filter lookups (employees having demurrage records, date range),
  returns `view('reports.ticket-demurrage', [...])`.
- **`data(Request $request)`** — JSON `{ employee: {...}, company: {...}, summary: {...} }`,
  tab via `?tab=employee|company`:
  - **Employee source**: `ReIssuedTicket::where('payment_by', 'employee')` ∪
    `RefundedTicket::where('payment_by', 'employee')`, active adjustments joined per record →
    `original_amount`, `reduced_amount`, `net_amount`, `adjusted_by`, `reason`;
    grouped per employee: `original_total`, `reduced_total`, `net_total`, record counts.
    Drill-down: `?expand={user_id}` returns that employee's individual records.
  - **Company source**: same tables `where('payment_by', 'company')` ∪ active
    `demurrage_adjustments` rows (amount + source record + whose employee + reduced-by + reason).
  - **Summary**: employee net total, company total (company records + reductions),
    total reductions.
  - Filters: `date_from` / `date_to` (`re_issue_date` / `refund_date`), `user_id`, search
    (PNR / ticket number), pagination.
- **`adjust(Request $request)`** — `POST`:
  - Validation: `re_issued_ticket_id` XOR `refunded_ticket_id`,
    `reduction_amount => required|numeric|min:0.01`, `reason => nullable|string`.
  - Guard: target record `payment_by = employee` (else 422).
  - Cap: `sum(active reductions) + amount ≤ current record amount` (else 422).
  - Defense-in-depth:
    `abort_if(!auth()->user()->roles()->whereIn('name', ['Super Admin', 'Co Admin'])->exists(), 403)`.
- **`revert(Request $request)`** — `DELETE`:
  sets `reverted_at = now()`, `reverted_by = auth()->id()`; Super/Co Admin only.
- **`print(Request $request)`** — tab-aware (`?tab=employee|company`), reuses the same query
  builders, returns `view('reports.ticket-demurrage-print', [...])`.

## 7. Routes — `routes/web.php` (new block next to ~L364)

```php
Route::get('/reports/ticket-demurrage', [TicketDemurrageReportController::class, 'index'])
    ->name('report.ticket-demurrage')
    ->middleware('role:Super Admin,Co Admin,Ticket Admin');

Route::get('/api/reports/ticket-demurrage', [TicketDemurrageReportController::class, 'data'])
    ->name('api.reports.ticket-demurrage')
    ->middleware('role:Super Admin,Co Admin,Ticket Admin');

Route::get('/reports/ticket-demurrage/print', [TicketDemurrageReportController::class, 'print'])
    ->name('report.ticket-demurrage.print')
    ->middleware('role:Super Admin,Co Admin,Ticket Admin');

Route::post('/api/reports/ticket-demurrage/adjustments', [TicketDemurrageReportController::class, 'adjust'])
    ->name('api.reports.ticket-demurrage.adjust')
    ->middleware(['role:Super Admin,Co Admin', 'throttle:30,1']);

Route::delete('/api/reports/ticket-demurrage/adjustments/{adjustment}', [TicketDemurrageReportController::class, 'revert'])
    ->name('api.reports.ticket-demurrage.revert')
    ->middleware('role:Super Admin,Co Admin');
```

## 8. Views

### `resources/views/reports/ticket-demurrage.blade.php` (new)

Structure copied from `resources/views/reports/due.blade.php` (cleanest 2-tab report):

- `@extends('layouts.app')`, root `x-data="ticketDemurrageReport()"`.
- Filter bar (date range, employee select, search) — layout like `due.blade.php:11–56`.
- Tab bar with `.tab-btn` CSS copied from `due.blade.php:98–113`:
  - Buttons: **Employee** / **Company** (`activeTab` state), per-tab **Print** button
    (links to `report.ticket-demurrage.print?tab=...` + current filters).
- **Employee panel**:
  - Summary cards: Original / Reduced / Net.
  - Grouped table rows: Employee, #records, Original total, Reduced total, Net total,
    expand toggle.
  - **Drill-down** (expanded row): individual records — date, type (re-issue/refund),
    PNR/ticket, booking, original amount, reduced amount, net, reduced-by/reason, action.
  - **Reduce button per record**, rendered only for Super/Co Admin:
    `auth()->user()->roles->whereIn('name', ['Super Admin', 'Co Admin'])->isNotEmpty()`.
    Modal: current amount, reduction input (client-side max = remaining cap), reason,
    confirm → `fetch POST /api/reports/ticket-demurrage/adjustments`, reload data.
  - Active adjustment badge + **Revert** link (Super/Co Admin only).
- **Company panel**:
  - Summary cards: Company records / Received from employees / Grand total.
  - Table: date, type, PNR/ticket, booking, source (*company record* | *reduction from
    {employee}*), reduced-by, reason, amount.
- `@push('scripts')` → `function ticketDemurrageReport()` with `activeTab`, `loadData()`
  (`fetch '/api/reports/ticket-demurrage?tab=...'`), pagination, expand/drill-down state,
  reduce modal state — pattern: `due.blade.php:470–527`.

### `resources/views/reports/ticket-demurrage-print.blade.php` (new)

Standalone print layout with `@media print` CSS — pattern:
`resources/views/reports/due-print-customers.blade.php`.
Renders the selected tab (headers, filters applied, table, totals).

## 9. Navigation — `resources/views/partials/nav.blade.php`

- Near L7–10 add:
  `$canAccessDemurrageReport = auth()->user()->roles->whereIn('name', ['Super Admin', 'Co Admin', 'Ticket Admin'])->isNotEmpty();`
- Add "Ticket Demurrage" link in the desktop Reports dropdown (~L40–43) and the mobile list
  (~L150–153), guarded by `$canAccessDemurrageReport`.

## 10. Edit Guard — `app/Http/Controllers/TicketIssueController.php`

In `edit()` financial block (L224–410), before persisting a new `total_cost`: if
`SUM(active reductions on this re-issue) > new total_cost` → 422
*"Existing demurrage reduction exceeds the new cost — update the adjustment first."*

## 11. TDD — `tests/Feature/TicketDemurrageReportTest.php` (write FIRST)

1. `test_report_page_requires_ticket_admin_roles` — 200 for Super/Co/Ticket Admin,
   403 for Ticket Staff / Branch Staff.
2. `test_employee_tab_groups_by_user_with_correct_amounts` — re-issue → `total_cost`,
   refund → `refund_compensation`, grouped by `user_id`.
3. `test_employee_drill_down_returns_individual_records` — `?expand={user_id}`.
4. `test_company_tab_lists_company_paid_records`.
5. `test_reduce_requires_super_or_co_admin` — `adjust()` as Ticket Admin → 403.
6. `test_reduction_moves_amount_to_company_tab` — reduce 30 of 100 → employee net 70,
   company grand total +30, summaries consistent.
7. `test_reduction_capped_at_record_cost` — over-cap → 422, nothing persisted.
8. `test_reduction_requires_employee_payment_by` — target a `payment_by=company` record → 422.
9. `test_revert_restores_amounts_and_keeps_audit_trail` — revert → employee net back to 100,
   company total drops 30, row retains `reverted_at`.
10. `test_reissue_edit_rejects_cost_below_active_adjustment`.
11. `test_print_route_renders_both_tabs_with_totals`.

Run: `php artisan test --filter=TicketDemurrageReportTest`

## 12. Files Summary

| Action | File |
|---|---|
| + | `database/migrations/2026_XX_create_demurrage_adjustments_table.php` |
| + | `app/Models/DemurrageAdjustment.php` |
| + | `app/Http/Controllers/TicketDemurrageReportController.php` |
| + | `resources/views/reports/ticket-demurrage.blade.php` |
| + | `resources/views/reports/ticket-demurrage-print.blade.php` |
| + | `tests/Feature/TicketDemurrageReportTest.php` |
| ~ | `routes/web.php` (5 routes near L364) |
| ~ | `resources/views/partials/nav.blade.php` (flag + 2 links) |
| ~ | `app/Http/Controllers/TicketIssueController.php` (cost-vs-adjustment guard) |

**Not touched:** `ProfitCalculationService`, `CostTrackingService`, profit-loss / branch-wise /
dashboard reports, re-issue & refund creation flow, `ui-references/`.

## 13. Verification Checklist

```bash
php artisan test                    # full suite (new + regression)
vendor/bin/pint                     # format
npm run build                       # frontend
docker compose config --quiet
docker compose -f docker-compose.prod.yml config --quiet
```

Commit message (Conventional Commits): `feat: add ticket demurrage report with employee/company tabs and admin reductions`

No push/deploy without explicit user permission (AGENTS.md §6).

---

## 14. Plan Review — Inconsistencies & Loopholes (codebase-verified)

Findings from analyzing this plan against the actual migrations, models, controllers,
views, routes, and tests. Grouped as: factual errors, logic loopholes, underspecifications,
and reconciliation caveats. **Resolve group B before implementation.**

### A. Factual / reference errors (fix in this plan)

1. **No `booking_id` column exists** on `re_issued_tickets` or `refunded_tickets`
   (confirmed: not in `2026_07_27_000002/000003` creates nor any later migration).
   §8 drill-down/company tables and the print blade promise a "booking" column —
   booking must be resolved via `issued_ticket_id → issued_tickets → passengers → booking_id`.
   → **Fix:** specify this join explicitly and eager-load it (`issuedTicket.passenger`)
   to avoid N+1 across grouped rows.
2. **`due.blade.php:11–56` filter-bar reference is wrong** — those lines are CSS
   (`.filter-btn`, `.date-input`, `.table-header`, …). Actual filter bar HTML is at
   `due.blade.php:184–195`. Also, due's 2 tabs live **inside a modal**
   (`due.blade.php:273–378`), not at page level — "cleanest 2-tab report" is a
   misleading structural template. → **Fix:** update line refs; keep the tab CSS
   (`:98–113`) and JS pattern (`:470–527`) which are correct, but build tabs
   page-level (don't copy the modal nesting).
3. **§1 overstates**: "`refund_compensation` … consumed by cost reports
   (`ProfitCalculationService`, `CostTrackingService`)" — **false**: both services
   have 0 matches for `refund_compensation`; they only consume `total_cost` /
   `payment_by` (`ProfitCalculationService:424–432`, `CostTrackingService:65–66`).
   → **Fix:** reword: the column exists but is consumed by *neither* service; this
   report is its first consumer.
4. **Migration filename is a placeholder** (`2026_XX_create_demurrage_adjustments_table.php`)
   → use a real timestamp at creation time.

### B. Logic loopholes (must be addressed in business rules)

5. **`payment_by` is editable** in `TicketIssueController::edit()`
   (validated `:208`, persisted `:318`). Rule 6 only guards `total_cost` vs active
   reductions — nothing guards flipping `payment_by` from `employee` → `company`
   (or `customer`/`airline`) while active reductions exist:
   - → `company`: record appears in company tab at **full amount** *and* its
     reductions are also counted → **double count**.
   - → `customer`/`airline`: record leaves the employee tab but its **active
     reductions remain as phantom company rows** with no employee source.
   - Cap (Rule 4) becomes stale either way.
   → **Fix (new Rule 8):** `edit()` must reject a `payment_by` change away from
   `employee` (422) when `SUM(active reductions) > 0` — force revert first.
   → **Add test:** `test_reissue_edit_rejects_payment_by_change_with_active_adjustment`.
6. **Soft deletes break report consistency**: both tables use `SoftDeletes`, and
   observers already anticipate deletion (`ReIssuedTicketObserver:32,37`,
   `RefundedTicketObserver:22,27`). The `restrictOnDelete` FK only blocks *hard*
   deletes — it does nothing for soft deletes. If a record with active reductions
   is soft-deleted, it disappears from the employee tab (default scope) while its
   active reductions **keep counting in the company tab** → phantom company total.
   → **Fix (new Rule 9):** on `deleted` event of a re-issue/refund with active
   adjustments, auto-revert them (`reverted_at = now()`, `reverted_by = null` or a
   system marker) — or alternatively exclude adjustments whose parent is trashed
   from the company tab. Prefer the observer auto-revert (audit trail preserved).
   → **Add test:** `test_soft_deleting_record_reverts_its_active_adjustments`.
7. **Cap race condition**: `adjust()` does sum-then-insert with no transaction /
   row lock — two concurrent requests can exceed the cap; `throttle:30,1` does not
   guarantee correctness. → **Fix:** wrap `adjust()` in `DB::transaction()` with
   `lockForUpdate()` on the parent record (or on existing active adjustments)
   before summing.
8. **`payment_by` is nullable** (enum made nullable by
   `2026_08_20_000001`). NULL-payment records appear in **neither** tab, silently —
   report totals will not cover all re-issue/refund records.
   → **Decide:** explicitly exclude (document it) or surface a "unassigned" bucket.
9. **`refund_compensation` can be ≤ 0**: it is `net_fare − iata_refund`
   (`RefundController.php:103`, no clamp visible) → employee tab could show
   negative "cost" rows. Cap (Rule 4) already blocks reductions on them, but the
   summary math will look odd. → **Decide:** exclude rows with amount ≤ 0 from the
   report (and document), or display them flagged.

### C. Underspecified / ambiguous (clarify before coding)

10. **§6 API shape is contradictory**: returns `{ employee, company, summary }`
    *and* says "tab via `?tab=employee|company`". → **Fix:** return only the active
    tab's payload + `summary` (+ `pagination`), or always return all — pick one.
11. **Pagination semantics unclear**: grouped-by-employee rows + `?expand={user_id}`
    drill-down + search + full-set summary — paginate *groups* or *records*?
    → **Fix:** mirror the PendingOutbound pattern explicitly: full-set `summary`
    + 25/page pagination on the grouped rows; drill-down returns that employee's
    records (own pagination or capped list).
12. **Which date filters company-tab reduction rows?** Source record's
    `re_issue_date`/`refund_date`, or the adjustment's `created_at`?
    → **Fix:** use the **source record's date** (keeps employee/company tabs
    consistent under the same filter) and state it.
13. **Rule 7 wording contradicts Rule 2**: "company total = company records +
    Σ reductions" must read Σ **active** reductions (revert must drop it from the
    total — test 9 already expects this).
14. **`revert()` has no reason field** (adjust has one) and no throttle; test list
    misses: cap across **multiple** adjustments (sum of two), and
    `Ticket Admin → revert → 403`. → **Add** both tests; consider a nullable
    `revert_reason`.

### D. Reconciliation caveat (decision already made — document it)

15. **Report totals will not reconcile with Profit/Loss**: `ProfitCalculationService`
    counts only company-paid re-issue `total_cost` (`:431–432`, `:509–511`) — never
    `refund_compensation`, never employee-borne records. The company tab includes
    refund `refund_compensation` **plus** moved reductions, so its total has **no
    counterpart in cost reports**. This matches the confirmed decision
    ("financial impact outside the report: none"), but users will compare numbers.
    → **Fix:** add a footnote on the report page and print:
    *"Demurrage totals are report-only and are not included in Profit/Loss."*

### Verified-correct (no change needed)

- Roles match `RoleSeeder` (12 roles incl. `Super Admin`, `Co Admin`,
  `Ticket Admin`, `Ticket Staff`, `Branch Staff`); `role:` alias registered at
  `bootstrap/app.php:35` → `CheckRole` (403 on mismatch).
- Route location (~L364, next to pending-outbound `:363–364`) and nav refs
  (`nav.blade.php:7–10`, `:40–43`, `:150–153`) match the plan.
- `user_id` = creator at all 4 creation sites (`ReIssueController:123`,
  `RefundController:97`, `TicketRequestController:229/450`).
- No hard-delete path exists for re-issue/refund tickets today (0 matches repo-wide).
- `.tab-btn` CSS ref (`due.blade.php:98–113`) and JS pattern (`:470–527`) are accurate.
- `total_cost` persisted only at edit (`TicketIssueController:321`) and creation
  (`ReIssueController:154`, `TicketRequestController:278`) → Rule 6 guard placement
  is correct; refunds have **no** edit path, so no equivalent guard needed there.
- `refund_compensation` formula in §3 (`net_fare − iata_refunded_amount`) matches
  `RefundController.php:103`.

### Plan amendments checklist

- [ ] A1: booking resolution join + eager-load spec (§8, print)
- [ ] A2: fix `due.blade.php` line refs; page-level tabs
- [ ] A3: reword §1 `refund_compensation` claim
- [ ] A4: real migration filename
- [ ] B5: **Rule 8** — guard `payment_by` change with active reductions (+ test)
- [ ] B6: **Rule 9** — auto-revert adjustments on soft delete (+ test)
- [ ] B7: transaction + lock in `adjust()`
- [ ] B8: decide handling of NULL `payment_by`
- [ ] B9: decide handling of amount ≤ 0
- [ ] C10: settle `data()` payload shape
- [ ] C11: settle pagination semantics
- [ ] C12: reduction-row date filter = source record date
- [ ] C13: Rule 7 → Σ **active** reductions
- [ ] C14: revert reason + throttle; 2 extra tests
- [ ] D15: report-only footnote (page + print)
