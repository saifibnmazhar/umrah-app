# Ticket Demurrage Report — Plan (Revised)

> This revision folds in the complete self-review (old §14) *and* the resolutions for every
> loophole / inconsistency / limitation that was found. Each finding is now documented in
> **§14 Findings & Resolutions Register** with its problem *and* its resolution stated in
> detail, and every resolution is embedded in the plan sections §1–§13.

---

## 1. Problem

Re-issue and refund flows already record `payment_by ∈ customer | airline | employee | company`
on `re_issued_tickets` and `refunded_tickets` (enum extended by
`database/migrations/2026_08_20_000001_add_company_to_payment_by_enums.php`).

When set to `employee` or `company`, **no payment / voucher / invoice row is created** — the
value only exists as a column on the record.

Two corrections to the original problem statement (finding A3):

- `refund_compensation` is consumed by **neither** `ProfitCalculationService` **nor**
  `CostTrackingService` (0 matches in both). Those services only consume `total_cost` /
  `payment_by` (`ProfitCalculationService:424–432`, `CostTrackingService:65–66`).
  This report is `refund_compensation`'s **first consumer**.
- `payment_by` is **nullable** (made nullable by `2026_08_20_000001`), so some records have no
  payer at all.

There is currently **no "Ticket Demurrage Report"** anywhere in the codebase (0 matches in
code, views, migrations, git history), and no way for Super Admin / Co Admin to move an
employee-borne re-issue/refund cost onto the company **with an audit trail**.

---

## 2. Goal

Build a new **Ticket Demurrage Report** with two tabs:

- **Employee tab** — current `payment_by = employee` re-issues/refunds, grouped by the
  **ticket issuer** (the employee who actually bore the ticket, resolved via
  `issued_tickets.user_id` — see §3), with a per-issuer drill-down to individual records.
  Super Admin / Co Admin can **Exempt** an individual record: the whole record's cost moves
  to the company (`payment_by` flips to `company`) and an audit row is written.
- **Company tab** — records where `payment_by = company` (including those just exempted),
  annotated with the exemption (who, when, reason) and revertible.

Exemption is **all-or-nothing per record** — there is no partial reduction. Exempt / Revert is
a **bare `payment_by` flip** plus one audit row; it never touches `payment_option`,
`refund_adjustment_amount`, Payment rows, Voucher rows, or `refund_payable`.

Export is **print only**.

---

## 3. Decisions (confirmed)

| Question | Decision |
|---|---|
| Refund amount used as "total cost" | `refund_compensation` (= `net_fare − iata_refunded_amount`, `RefundController:103`); no clamp exists, values can be ≤ 0 (see §4.2) |
| Re-issue amount used as "total cost" | `total_cost` (`ReIssueController:148–154`, already net of `refund_adjustment`) |
| **Employee identity** | **Ticket issuer**, not the record's creator: `issued_tickets.user_id` via `re_issued_tickets.issued_ticket_id` / `refunded_tickets.issued_ticket_id`, exposed by `IssuedTicket::issuer()` (`app/Models/IssuedTicket.php:49–52`). Re-stamped to `auth()->id()` at `pending → issued` (`TicketIssueController::issue():76`, logged via `logAction`) and **immutable afterwards** (`edit()` has no `user_id` rule, so `collect($validated)` at `:441` can never carry it). The record's **own** `user_id` is only the executor audit stamp (set to `auth()->id()` at all 4 creation sites) — it is **not** used for grouping. Filter parameter is named **`issuer_id`**. |
| Exemption mechanism | **All-or-nothing** per record, implemented as a bare `payment_by` flip `employee ↔ company` + exactly one audit row in `demurrage_adjustments` (reason, adjusted_by, reverted_at/by). **No amount column** — the amount is always the record's own `total_cost` / `refund_compensation`. |
| Partial reduction | **Not supported** (removed with its cap rule) |
| Fields exempt/revert never touch | `payment_option`, `refund_adjustment_amount`, `Payment`, `Voucher`, `refund_payable` |
| Financial impact outside the report | **Re-issues: exemption DOES change Profit/Loss** — `ReIssuedTicketObserver::$profitFields` includes `payment_by`, and `ProfitCalculationService:424–432 / :509–511` counts company-paid `total_cost`. **Refunds: none** (`refund_compensation` is consumed by no cost service). This corrects the original "no impact at all" claim (finding L7). |
| Report view access | `Super Admin`, `Co Admin`, `Ticket Admin` |
| Exempt access | `Super Admin`, `Co Admin` only |
| Revert access | `Super Admin`, `Co Admin` only |
| Employee tab layout | Grouped per issuer → **expand → drill-down** of individual records (paginated) |
| Export | **Print only** (print route + print blade, per-tab print button) |
| Exempt modal | **Reason only** — no amount input |

---

## 4. Business Rules

### 4.1 Sources, amounts and dates

1. **Employee tab** sources only records with `payment_by = employee`:
   - re-issue: amount = `total_cost`, date = `re_issue_date`
   - refund: amount = `refund_compensation`, date = `refund_date`
2. **Company tab** sources only records with `payment_by = company` (the same amount/date
   rules apply). Exempted records are company records — that is *why* they appear here.
3. Rows are grouped / filtered by **issuer** (`issued_tickets.user_id`), never by the record's
   own `user_id`.

### 4.2 Exclusions (each surfaced, never silent)

| Excluded | Rule |
|---|---|
| `payment_by IS NULL` | Excluded from both tabs and all totals; surfaced as an **"Unassigned" count** card ("records without a payer — not in totals"). |
| amount ≤ 0 (`total_cost` / `refund_compensation`) | Excluded from totals; **footnote with count**; `exempt()` on such a record returns **422**. |
| orphaned record (`issued_ticket_id IS NULL`) | Can exist because `re_issued_tickets.issued_ticket_id` is nullable `nullOnDelete` and `BookingController::destroy` force-deletes (`:2282`, `:2291`). Excluded; **footnote with count**. |

Also:

- **No `booking_id` column exists** on `re_issued_tickets` / `refunded_tickets` (finding A1).
  Booking is resolved by the join
  `issued_ticket_id → issued_tickets → passengers → booking_id`. This join is required anyway
  for issuer grouping, so it adds no new join.
- Eager-load `issuedTicket.passenger` and `issuedTicket.issuer` to avoid N+1 across grouped
  rows and drill-downs.
- **Nullable source dates** (finding N8): a record whose `re_issue_date` / `refund_date` is
  NULL is excluded from **date-filtered** results and reported in a footnote count; it is
  included when no date filter is applied.

### 4.3 Company tab definition (double-count fix)

- Company totals = **Σ amount of records with `payment_by = company` only**.
- Exemption rows are **never added on top**: an exempted record *is* now a
  `payment_by = company` record, so its amount is already inside the total. The adjustment row
  is an **annotation** (source, exempted-by, reason, reversal state) — not a second amount.
- Summary card **"Received from employees"** = Σ amounts of company records that carry an
  **active** adjustment. It is a **subset of the company grand total, not additive**; the UI
  labels it as such.
- Grand total card = Σ all company records.

### 4.4 Predicates (one vocabulary, used everywhere — finding L4)

| Name | Definition | Used by |
|---|---|---|
| **Active** adjustment | `reverted_at IS NULL AND deleted_at IS NULL` | Company-tab annotation, revert availability, edit gate `company → employee` |
| **Blocking** adjustment | `deleted_at IS NULL` (any non-soft-deleted row, reverted or not) | Edit gate `employee → company` |
| **Audit** read | with trashed (`withTrashed()`) | Audit/history reads only |

- `softDeletes()` **must be added** to `demurrage_adjustments` (it was missing in the original
  §5).
- **Raw joins must state `deleted_at IS NULL` explicitly** — Eloquent soft-delete scopes do not
  apply to `DB::table` joins.
- **One active adjustment per parent** is enforced **in code** under `lockForUpdate()`
  (see §6, `exempt()`). A plain `unique(parent_id, reverted_at)` index is **invalid** because
  SQL treats `NULL`s as distinct, so it would not prevent two active rows.

### 4.5 Exempt / Revert

1. `exempt(record)` — Super/Co only:
   - target must have `payment_by = employee` (else 422);
   - amount must be > 0 (else 422);
   - no **active** adjustment may already exist on it (else 422);
   - within `DB::transaction()` + `lockForUpdate()` on the parent: flip the parent's
     `payment_by` to `company`, then insert the audit row (`reason nullable`,
     `adjusted_by = auth()->id()`).
   - Never touches `payment_option`, `refund_adjustment_amount`, Payment, Voucher,
     `refund_payable`.
   - **Works on records settled with `refund_adjustment`**: `total_cost` is already net of the
     refund adjustment (`ReIssueController:148–154`), so there is no double count.
2. `revert(adjustment)` — Super/Co only:
   - sets `reverted_at = now()`, `reverted_by = auth()->id()` and flips the parent's
     `payment_by` back to `employee`, in the same transaction under lock;
   - the row is **retained** (audit trail preserved);
   - allowed even when the parent is a `refund_adjustment` settlement record.
3. **Accepted asymmetry:** while an active adjustment exists, the *only* way back to
   `employee` is reverting on this report — the edit gate blocks `company → employee` in that
   state (§4.6). This prevents stranded exempted records while keeping the sanctioned path.

### 4.6 `payment_by` edit-gate matrix (`TicketIssueController::edit()`)

Applies only when the submitted value actually **differs** from the stored one.

| Change (from → to) | Requirement |
|---|---|
| employee / customer / airline → **company** | editor ∈ {Super Admin, Co Admin, Ticket Admin} **AND** `re_issued_tickets.created_at ≤ 24h`. Both hops of an `employee → customer → company` detour must pass, so the detour is closed. |
| employee → **company** (additional) | **Rejected (422)** if any **Blocking** adjustment row exists → message points the user to the report. |
| **company** → employee | **Rejected (422)** if an **Active** adjustment exists — revert on the report first. |
| **company** → customer / airline | Allowed; the active adjustment is **soft-deleted in the same transaction** (the record leaves the exemption scope). |
| other changes (customer ↔ airline, … → employee) | Allowed as today. |
| **creation-time** `payment_by = company` | **Stays open to all four ticket roles** (incl. Ticket Staff) — no employee has been charged yet, no history is evaded. Not subject to the 24h/admin gate. |

- **All these checks run BEFORE `DB::beginTransaction()` at `:222`**: the `catch` at
  `:500–505` would otherwise convert an `abort(422)` into a **500** (finding N2).
- The report's `exempt()` action (Super/Co) is the sanctioned **anytime** path; the 24h window
  applies only to edit-sourced flips.
- The old "Rule 6" guard (`total_cost` vs. active reductions) is **deleted** — with
  all-or-nothing exemption there is no partial amount to violate.

### 4.7 Report-only note (D15)

A footnote on the page **and** the print view:

> *"Demurrage totals are report-only. Exempting a re-issue moves its cost to company-paid in
> Profit/Loss (the profit observer recalculates automatically). Refund amounts appear in no
> cost report. Amounts are shown in each record's stored currency — no conversion is
> applied."*

---

## 5. Data Model

### Migration — `database/migrations/2026_XX_create_demurrage_adjustments_table.php`

Use a **real timestamp** at creation time (finding A4).

```
demurrage_adjustments
  id
  re_issued_ticket_id   FK -> re_issued_tickets, nullable, restrictOnDelete
  refunded_ticket_id    FK -> refunded_tickets,  nullable, restrictOnDelete
  reason                string nullable
  adjusted_by           FK -> users, restrictOnDelete
  reverted_at           timestamp nullable
  reverted_by           FK -> users, nullable, restrictOnDelete
  timestamps
  softDeletes           (deleted_at)          <-- added per §4.4
  index(re_issued_ticket_id)
  index(refunded_ticket_id)
```

- **No `reduction_amount` column** (all-or-nothing exemption).
- Exactly one of `re_issued_ticket_id` / `refunded_ticket_id` must be non-null (validated in
  the controller; optional DB `CHECK` constraint where supported).
- **No unique index for "one active row"** — see §4.4; enforced in code under lock.
- `reverted_by` is `restrictOnDelete` for audit consistency (see finding N9); it is nullable
  until a revert happens.

### Model — `app/Models/DemurrageAdjustment.php` (new)

- `use SoftDeletes;`
- `$fillable`: `re_issued_ticket_id`, `refunded_ticket_id`, `reason`, `adjusted_by`,
  `reverted_at`, `reverted_by`
- Relations: `reIssuedTicket()`, `refundedTicket()`, `adjustedBy()`, `revertedBy()`
- `parentRecord()` helper returning whichever of the two relations is set
- `scopeActive($q)`: `whereNull('reverted_at')` (soft-delete scope handles `deleted_at`)

---

## 6. Backend

### Controller — `app/Http/Controllers/TicketDemurrageReportController.php` (new)

Mirrors `PendingOutboundReportController` (`index()` view + `data()` JSON).

**`index(Request $request)`** — filter lookups (issuers having demurrage records, date range),
returns `view('reports.ticket-demurrage', [...])`.

**`data(Request $request)`** — `?tab=employee|company`, returns **only the active tab's
payload** plus the always-full-set `summary`, `pagination`, and footnote counters (finding C10):

```json
{
  "tab": "employee|company",
  "rows": [...],
  "summary": { ... },
  "pagination": { "current_page": 1, "per_page": 25, "total": 0 },
  "footnotes": {
    "unassigned_count": 0,
    "non_positive_count": 0,
    "orphan_count": 0,
    "null_date_count": 0
  }
}
```

- **Employee source**: `ReIssuedTicket::where('payment_by','employee')` ∪
  `RefundedTicket::where('payment_by','employee')`, grouped by **issuer**; each group returns
  `issuer_id`, `issuer_name`, `records_count`, `total_amount`; drill-down
  `?expand={issuer_id}` returns that issuer's individual records (own pagination).
- **Company source**: `where('payment_by','company')`, LEFT JOIN **active** adjustment
  (`deleted_at IS NULL AND reverted_at IS NULL`, stated explicitly in the join) for the
  `source` annotation (`originally company` vs `exempted from {issuer}`), `exempted_by`,
  `reason`, and revert availability.
- **Summary**: employee total + count, company grand total + count, "received from employees"
  (subset card, §4.3), exempted count.
- **Filters**: `date_from` / `date_to` applied to the **source record's date**
  (`re_issue_date` / `refund_date`) for **both tabs** (finding C12); `issuer_id`; search
  (PNR / ticket number); pagination.
- **Aggregates** are computed with aggregate/`groupBy` queries — not `->get()->sum()`
  (finding N12).
- **Pagination semantics** (finding C11): `summary` is always full-set; grouped employee rows
  25/page; drill-down 25/page; company rows 25/page.

**`exempt(Request $request)`** — `POST` (route named `...exempt`; replaces the old "adjust"):

- Validation: `re_issued_ticket_id` **XOR** `refunded_ticket_id`, `reason => nullable|string`.
  **No amount field is accepted.**
- Guards (422s, evaluated **before** the transaction): target `payment_by = employee`; amount
  > 0; no active adjustment yet.
- Transaction + `lockForUpdate()` on the parent → flip `payment_by` to `company` + insert the
  audit row.
- Defense-in-depth:
  `abort_if(!auth()->user()->roles()->whereIn('name', ['Super Admin', 'Co Admin'])->exists(), 403)`.

**`revert(Request $request)`** — `DELETE` with implicit route-model binding
`DemurrageAdjustment $adjustment` (finding N4; 404 when missing/soft-deleted):

- Super/Co only; transaction + lock; set `reverted_at` / `reverted_by`; flip the parent back to
  `employee` if it is currently `company`; keep the row.

**`print(Request $request)`** — tab-aware (`?tab=employee|company`), reuses the same query
builders, returns `view('reports.ticket-demurrage-print', [...])`.

---

## 7. Routes — `routes/web.php` (new block next to ~L364)

The existing stub `report.reissue-refund` (`routes/web.php:359`) is left untouched; the new
routes use distinct names and paths — verify no collision (finding N6).

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

Route::post('/api/reports/ticket-demurrage/adjustments', [TicketDemurrageReportController::class, 'exempt'])
    ->name('api.reports.ticket-demurrage.exempt')
    ->middleware(['role:Super Admin,Co Admin', 'throttle:30,1']);

Route::delete('/api/reports/ticket-demurrage/adjustments/{adjustment}', [TicketDemurrageReportController::class, 'revert'])
    ->name('api.reports.ticket-demurrage.revert')
    ->middleware(['role:Super Admin,Co Admin', 'throttle:30,1']);
```

---

## 8. Views

### `resources/views/reports/ticket-demurrage.blade.php` (new)

- `@extends('layouts.app')`, root `x-data="ticketDemurrageReport()"`.
- **Filter bar**: HTML layout reference is `due.blade.php:184–195` (the original `:11–56`
  reference was CSS, not markup — finding A2).
- **Tabs are page-level** (Employee / Company) — CSS copied from `due.blade.php:98–113`, JS
  pattern from `:470–527`. **Do not copy** due's 2-tab modal nesting (`:273–378`).
- **Employee panel**: summary cards (Employee total, records count, exempted count); grouped
  rows (Issuer, #records, total, expand toggle).
  - **Drill-down**: date, type (re-issue/refund), PNR/ticket, booking, amount, **Exempt**
    action.
  - **Exempt button** rendered only for Super/Co Admin
    (`auth()->user()->roles->whereIn('name', ['Super Admin','Co Admin'])->isNotEmpty()`).
    Modal = **reason field only** (no amount input), confirm →
    `POST /api/reports/ticket-demurrage/adjustments`, reload.
- **Company panel**: summary cards (Company records total, "of which received from employees"
  — subset, not additive, Grand total). Table: date, type, PNR/ticket, booking, amount,
  source (*originally company* | *exempted from {issuer}*), exempted-by, reason,
  **Revert** (Super/Co; only when the row has an active adjustment).
- **Footnotes**: report-only / P&L note (§4.7), non-positive count, orphan count, unassigned
  count, currency note.
- `@push('scripts')` → `function ticketDemurrageReport()` with `activeTab`, `loadData()`
  (`fetch '/api/reports/ticket-demurrage?tab=...'`), pagination, expand/drill-down, exempt
  modal, revert — pattern: `due.blade.php:470–527`.

### `resources/views/reports/ticket-demurrage-print.blade.php` (new)

Standalone print layout with `@media print` CSS — pattern:
`resources/views/reports/due-print-customers.blade.php`. Renders the selected tab (filters
applied, table, totals, footnotes).

---

## 9. Navigation — `resources/views/partials/nav.blade.php`

- Near L7–10 add **once** (finding N5):
  `$canAccessDemurrageReport = auth()->user()->roles->whereIn('name', ['Super Admin', 'Co Admin', 'Ticket Admin'])->isNotEmpty();`
- Add a "Ticket Demurrage" link in the desktop Reports dropdown (~L40–43) and the mobile list
  (~L150–153), both guarded by `$canAccessDemurrageReport`.

---

## 10. Edit Guard — `app/Http/Controllers/TicketIssueController.php`

- Implement the **§4.6 gate matrix** for `payment_by` changes in `edit()`, **before**
  `DB::beginTransaction()` at `:222` (the `catch` at `:500–505` would otherwise turn 422s into
  500s — finding N2).
- Landing on `company` (employee/customer/airline) → admin tier
  {Super Admin, Co Admin, Ticket Admin} + `created_at ≤ 24h`.
- `employee → company` additionally 422s when a **Blocking** adjustment exists.
- `company → employee` 422s when an **Active** adjustment exists.
- `company → customer/airline` soft-deletes the active adjustment in the same transaction.
- **Delete the old Rule-6 guard** (`total_cost` vs. active reductions) — no longer applicable.

---

## 11. TDD — `tests/Feature/TicketDemurrageReportTest.php` (write FIRST)

Access & routes

1. `test_report_page_requires_ticket_admin_roles` — 200 for Super/Co/Ticket Admin, 403 for
   Ticket Staff / Branch Staff.
2. `test_exempt_requires_super_or_co_admin` — Ticket Admin → 403.
3. `test_revert_requires_super_or_co_admin` — Ticket Admin → 403.
4. `test_print_route_renders_both_tabs_with_totals`.

Employee tab

5. `test_employee_tab_groups_by_issuer_not_record_user_id` — issuer (`issued_tickets.user_id`)
   differs from the record's own `user_id`; grouping follows the issuer.
6. `test_issuer_identity_is_restamped_on_issue_and_immutable_via_edit`.
7. `test_employee_tab_amounts_use_total_cost_and_refund_compensation`.
8. `test_employee_drill_down_returns_individual_records_paginated` (`?expand={issuer_id}`).

Company tab

9. `test_company_tab_lists_only_payment_by_company_records`.
10. `test_company_tab_annotates_exempted_records_and_subset_card` — "received from employees"
    is a non-additive subset.
11. `test_company_total_counts_exempted_record_exactly_once` — 100 → exempt → company total is
    100, not 200 (double-count regression).

Exempt

12. `test_exempt_flips_payment_by_and_creates_audit_row`.
13. `test_exempt_is_all_or_nothing_and_ignores_amount_input`.
14. `test_exempt_requires_employee_payment_by` — company/customer/airline target → 422.
15. `test_exempt_rejects_non_positive_amount`.
16. `test_exempt_rejects_second_active_adjustment`.
17. `test_exempt_never_touches_payment_option_refund_adjustment_or_payment_rows`.
18. `test_exempt_works_on_refund_adjustment_settled_record`.
19. `test_exempt_changes_profit_loss_for_reissues` — observer recalc, cost becomes
    company-borne.
20. `test_exempt_does_not_change_profit_loss_for_refunds`.

Revert

21. `test_revert_flips_back_keeps_audit_row_and_restores_tabs`.
22. `test_revert_on_originally_company_row_is_rejected`.

Exclusions

23. `test_null_payment_by_excluded_and_counted_as_unassigned`.
24. `test_non_positive_amounts_excluded_with_footnote`.
25. `test_orphaned_issued_ticket_rows_excluded_and_counted`.
26. `test_null_source_date_rows_excluded_from_date_filter_with_footnote`.

Edit gate

27. `test_edit_gate_landing_on_company_requires_admin_tier_and_24h_window`.
28. `test_edit_gate_blocks_employee_to_company_when_adjustment_row_exists`.
29. `test_edit_gate_blocks_company_to_employee_when_active_adjustment_exists`.
30. `test_edit_gate_company_to_customer_soft_deletes_active_adjustment_in_transaction`.
31. `test_edit_gate_other_payment_by_changes_unaffected`.
32. `test_edit_gate_returns_422_not_500` — regression for the `:500–505` catch.

Predicates / soft delete

33. `test_soft_deleting_parent_hides_record_and_its_adjustments_from_both_tabs`.
34. `test_raw_join_adjustments_require_deleted_at_is_null`.

Pagination / aggregates

35. `test_summary_is_full_set_while_grouped_rows_paginate_25_per_page`.
36. `test_date_filter_uses_source_record_date_for_company_rows`.

Run: `php artisan test --filter=TicketDemurrageReportTest`

---

## 12. Files Summary

| Action | File |
|---|---|
| + | `database/migrations/2026_XX_create_demurrage_adjustments_table.php` (real timestamp; softDeletes; no amount column) |
| + | `app/Models/DemurrageAdjustment.php` (SoftDeletes) |
| + | `app/Http/Controllers/TicketDemurrageReportController.php` (index / data / exempt / revert / print) |
| + | `resources/views/reports/ticket-demurrage.blade.php` |
| + | `resources/views/reports/ticket-demurrage-print.blade.php` |
| + | `tests/Feature/TicketDemurrageReportTest.php` |
| + | `database/migrations/2026_XX_add_demurrage_report_indexes.php` (optional: `(payment_by, re_issue_date)` / `(payment_by, refund_date)`) |
| ~ | `routes/web.php` (5 routes near L364) |
| ~ | `resources/views/partials/nav.blade.php` (flag + 2 links) |
| ~ | `app/Http/Controllers/TicketIssueController.php` (payment_by gate matrix; old Rule-6 guard removed) |

**Not touched:** `ProfitCalculationService`, `CostTrackingService`, profit-loss / branch-wise /
dashboard reports, re-issue & refund creation flow, `ReIssuedTicketObserver` /
`RefundedTicketObserver` (no auto-revert — see B6/L4), `ui-references/`.

---

## 13. Verification Checklist

```bash
php artisan test                    # full suite (new + regression)
vendor/bin/pint                     # format
npm run build                       # frontend
docker compose config --quiet
docker compose -f docker-compose.prod.yml config --quiet
```

Commit message (Conventional Commits):
`feat: add ticket demurrage report with employee/company tabs and admin exemptions`

No push/deploy without explicit user permission (AGENTS.md §6).

---

# 14. Findings & Resolutions Register

Each entry states the **problem** (with concrete example / evidence) and its **resolution**
(what replaces it, and where it lands in the plan). This register replaces the original §14
self-review.

## A. Factual / reference errors

### A1 — Inconsistency: "booking" column has no backing column

- **Problem:** there is **no `booking_id`** on `re_issued_tickets` or `refunded_tickets`
  (confirmed against `2026_07_27_000002/000003` and all later migrations). §8 tables and the
  print blade promised a "booking" column, which would either crash or require an unstated
  join.
- **Resolution:** booking is resolved explicitly via
  `issued_ticket_id → issued_tickets → passengers → booking_id`, with
  `issuedTicket.passenger` eager-loaded (§4.2). The issuer relation uses the same join chain,
  so no extra join is introduced. **Lands in:** §4.2, §6, §8.

### A2 — Factual error: wrong `due.blade.php` line references, misleading template

- **Problem:** `due.blade.php:11–56` is CSS (`.filter-btn`, `.date-input`, …), not the filter
  bar; the real filter bar is `:184–195`. Due's two tabs live **inside a modal** (`:273–378`),
  so "cleanest 2-tab report" was a misleading structural template.
- **Resolution:** references corrected — filter bar `:184–195`, tab CSS `:98–113`, JS pattern
  `:470–527`; tabs are built **page-level**, and copying the modal nesting `:273–378` is
  explicitly forbidden. **Lands in:** §8.

### A3 — Factual error: §1 overstated `refund_compensation` consumption

- **Problem:** the claim that `refund_compensation` is consumed by
  `ProfitCalculationService` / `CostTrackingService` is false — both have 0 matches; they only
  consume `total_cost` / `payment_by`.
- **Resolution:** §1 reworded — the column is consumed by **neither** service; this report is
  its **first consumer**. This correction is also what makes the refund half of finding L7/D15
  true (refund exemptions do not touch P/L). **Lands in:** §1, §3, §4.7.

### A4 — Limitation: placeholder migration filename

- **Problem:** `2026_XX_create_demurrage_adjustments_table.php` is not a usable filename.
- **Resolution:** a real timestamp is used at creation time (§5), matching the project's
  `YYYY_MM_DD_HHMMSS_descriptive_name.php` convention. **Lands in:** §5, §12.

## B. Logic loopholes

### B5 — Loophole: `payment_by` is editable; the report is evadable

- **Problem:** `edit()` validates `payment_by` (`TicketIssueController:208`) and persists it
  (`:318`). Old Rule 6 guarded only `total_cost`. Concrete evasions:
  (i) an employee record flipped straight to `company` in `edit()` lands in the company tab at
  full amount with **no audit row and no reason**;
  (ii) the two-step detour `employee → customer → company` defeats any single-step guard;
  (iii) flipping to `company` while an adjustment is active **double-counts** the record.
- **Resolution:** the §4.6 gate matrix. Every edit-sourced change **landing on** `company`
  (from employee, customer or airline) requires editor ∈ {Super Admin, Co Admin, Ticket Admin}
  **and** `created_at ≤ 24h` — since both hops of a detour must pass, the detour is closed.
  `employee → company` is additionally rejected while any **Blocking** adjustment row exists
  (422 → "use the report"). `company → employee` is rejected while an **Active** row exists
  (accepted asymmetry: reverting on the report is the only way back, which prevents stranded
  exempted records). `company → customer/airline` soft-deletes the active adjustment in the
  same transaction. Creation-time `company` stays open to all four ticket roles (no employee
  charged yet, no history evaded). All checks run **before** `DB::beginTransaction()` at
  `:222`. **Lands in:** §4.6, §10, tests 27–32.

### B6 — Loophole: soft deletes break report consistency (auto-revert RESCINDED)

- **Problem:** both parent tables use `SoftDeletes`, and `restrictOnDelete` FKs only block
  **hard** deletes. A soft-deleted parent disappears from the employee tab (default scope)
  while its active adjustment could keep phantom-counting in the company tab. The original fix
  (observer auto-revert on `deleted`) mutates financial state implicitly and spreads behaviour
  into observers that already recalculate profit.
- **Resolution (changed):** **do nothing in observers.** Consistency comes from the predicate
  vocabulary (§4.4): the company-tab join requires the **parent to be visible under its default
  scope** *and* `deleted_at IS NULL` on the adjustment, so parent and its adjustments disappear
  together; no observer is touched. `softDeletes()` is added to §5 (it was missing).
  **Lands in:** §4.4, §5, §12 ("not touched" list), test 33.

### B7 — Loophole: cap race condition (now an atomicity rule)

- **Problem:** the original design did sum-then-insert with no transaction or row lock, so two
  concurrent requests could exceed the cap; `throttle:30,1` guarantees nothing.
- **Resolution:** with all-or-nothing exemption there is no cap left to exceed, but the same
  race becomes a **duplicate-active-row** race. `exempt()` (and `revert()`) run inside
  `DB::transaction()` with `lockForUpdate()` on the parent record, and the
  "no active adjustment" assertion happens under that lock. A plain
  `unique(parent_id, reverted_at)` index cannot replace this because SQL treats `NULL`s as
  distinct. **Lands in:** §4.4, §4.5, §6, test 16.

### B8 — Limitation: `payment_by` is nullable → silent gaps

- **Problem:** NULL-payment records appeared in neither tab, quietly, so totals never covered
  all records.
- **Resolution:** NULL `payment_by` is **explicitly excluded** and surfaced as an
  **"Unassigned" count** card, labelled "not in totals" (§4.2). **Lands in:** §4.2, §6, test 23.

### B9 — Limitation: `refund_compensation` can be ≤ 0

- **Problem:** `refund_compensation = net_fare − iata_refunded_amount` has no clamp
  (`RefundController:103`), so employee rows could show negative "cost" and distort totals.
- **Resolution:** rows with amount ≤ 0 are **excluded** from totals, reported via **footnote +
  count**, and `exempt()` on them returns **422**. **Lands in:** §4.2, §6, tests 15/24.

## C. Underspecified / ambiguous

### C10 — Inconsistency: contradictory API shape

- **Problem:** §6 promised both `{ employee, company, summary }` **and** "tab via
  `?tab=...`" — contradictory.
- **Resolution:** `data()` returns **only the active tab's payload** plus the always-full-set
  `summary`, `pagination` and `footnotes` (frozen JSON shape in §6). **Lands in:** §6, §8.

### C11 — Underspecified: pagination semantics

- **Problem:** unclear whether groups or records paginate, given grouped rows + drill-down +
  search + full-set summary.
- **Resolution:** explicit semantics — `summary` always full-set; grouped employee rows
  25/page; drill-down 25/page; company rows 25/page (§6). **Lands in:** §6, test 35.

### C12 — Underspecified: which date filters company-tab rows?

- **Problem:** source record's date, or the adjustment's `created_at`?
- **Resolution:** the **source record's** `re_issue_date` / `refund_date`, for both tabs, so
  employee and company stay consistent under the same filter (§4.1, §6). **Lands in:** §6,
  test 36.

### C13 — Inconsistency: "company total = company records + Σ reductions"

- **Problem:** the old Rule 7 contradicted Rule 2/9 by adding reductions on top of records.
- **Resolution:** that arithmetic is **deleted with partial reductions**. Company totals count
  each `payment_by = company` record **exactly once** (exempted records are already company
  records); active adjustments are annotations, and "received from employees" is a labelled
  subset (§4.3). **Lands in:** §4.3, test 11.

### C14 — Underspecified: revert lacked reason/throttle; tests missing

- **Problem:** `revert()` had no reason field, no throttle, and tests were missing. The
  proposed "cap across two adjustments" test.
- **Resolution:** `throttle:30,1` and Super/Co middleware added to the revert route (§7);
  implicit route-model binding added (N4). A `revert_reason` field is **deliberately not
  added** — revert is a state restoration whose audit value is the retained row plus
  `reverted_at`/`reverted_by`; the exemption `reason` remains the durable explanation.
  The old cap test is obsolete (no cap exists); the equivalent new tests are 16, 21, 22.
  **Lands in:** §5, §7, tests 16/21/22.

## D. Reconciliation caveat

### D15 — Limitation: report totals do not reconcile with Profit/Loss

- **Problem:** `ProfitCalculationService` counts only company-paid re-issue `total_cost`
  (`:431–432`, `:509–511`) — never `refund_compensation`, never employee-borne records. The
  company tab therefore has no direct counterpart in cost reports, and users **will** compare
  numbers.
- **Resolution:** the §4.7 footnote on page and print, *corrected* to state the true behaviour
  (re-issue exemptions **do** change P/L; refunds do not; no currency conversion). **Lands
  in:** §3, §4.7, §8, tests 19/20.

## E. Second-review findings

### N1 — Orphaned records after booking deletion

- **Problem:** `re_issued_tickets.issued_ticket_id` is nullable `nullOnDelete`;
  `BookingController::destroy` force-deletes (`:2282`, `:2291`), leaving records with no issuer
  and no booking — they would break grouping.
- **Resolution:** excluded from both tabs, surfaced as a footnote count (§4.2). **Lands in:**
  §4.2, test 25.

### N2 — Guard errors swallowed into 500s

- **Problem:** `TicketIssueController` wraps work in `try` with `catch` at `:500–505`; an
  `abort(422)` raised inside the transaction block becomes a 500.
- **Resolution:** every new gate check runs **before** `DB::beginTransaction()` at `:222`, and
  the gate raises plain validation/`abort` responses outside that block. **Lands in:** §4.6,
  §10, test 32.

### N3 — Stale plan lists

- **Problem:** §11/§12 had drifted from the resolved design (old test 10, reduction column,
  cap test).
- **Resolution:** §11 (§36 tests) and §12 rewritten in this revision; they are the single
  source of truth for implementation. **Lands in:** §11, §12.

### N4 — `revert()` had no route-model binding

- **Problem:** the original signature relied on client-supplied identifiers and could act on
  soft-deleted or missing rows.
- **Resolution:** implicit binding `DemurrageAdjustment $adjustment` (404 for missing/soft-
  deleted) + role middleware + parent-state check inside the locked transaction. **Lands in:**
  §6, §7.

### N5 — Nav flag duplicated per link

- **Problem:** computing the access flag inline at each link risks divergence and repeated
  role queries.
- **Resolution:** one `$canAccessDemurrageReport` computed near the top of
  `nav.blade.php:7`, reused by both desktop and mobile links. **Lands in:** §9.

### N6 — Pre-existing stub route could collide

- **Problem:** a placeholder route named `report.reissue-refund` already exists
  (`routes/web.php:359`).
- **Resolution:** left untouched; the 5 new routes use distinct names/paths; collision check is
  part of the route step. **Lands in:** §7.

### N7 — *Wording not retained from the earlier conversational review.*

- **Resolution:** any substance it carried is already reflected in §1–§13 (the section
  rewrites were derived from the same review); flagged here for transparency. Re-confirm if the
  original text matters.

### N8 — Nullable source dates

- **Problem:** `re_issue_date` / `refund_date` can be NULL, so date filters could silently drop
  or misplace rows.
- **Resolution:** NULL-date rows are excluded from date-filtered results and reported in a
  footnote count; included when no date filter is applied. **Lands in:** §4.2, test 26.

### N9 — FK delete behaviour vs. user lifecycle

- **Problem:** `issued_tickets.user_id` is `NOT NULL restrictOnDelete`
  (`2026_06_12_100004_create_issued_tickets_table.php:15`); the new audit FKs raise the same
  question for `adjusted_by` / `reverted_by`.
- **Resolution:** both are `restrictOnDelete` so the audit trail can never be orphaned —
  consistent with the existing issuer FK; the consequence (a staff user with audit rows cannot
  be hard-deleted) is documented. `reverted_by` stays nullable until a revert occurs. **Lands
  in:** §5.

### N10 — *Wording not retained from the earlier conversational review.*

- **Resolution:** folded into §1–§13; see N7 note.

### N11 — Currency basis

- **Problem:** amounts summed across records were never stated to be in a single currency.
- **Resolution:** the report sums each record's stored amount with **no conversion**, and the
  §4.7 footnote states this explicitly so mixed-currency data is not mistaken for a converted
  total. **Lands in:** §4.7, §8.

### N12 — Aggregate computation and missing indexes

- **Problem:** the original design computed summaries with `->get()->sum()` (loads every row)
  and had no supporting indexes for `payment_by` + date filtering.
- **Resolution:** summaries use aggregate/`groupBy` queries; an optional
  `(payment_by, re_issue_date)` / `(payment_by, refund_date)` index migration is added to §12.
  **Lands in:** §6, §12.

### N13 — Race with concurrent exempt requests

- **Problem:** paired with B7 — no lock meant two requests could each see "no active
  adjustment".
- **Resolution:** same as B7 — transaction + `lockForUpdate()` on the parent, assertion under
  the lock. **Lands in:** §4.4, §6, test 16.

### N14, N15, N16 — *Wording not retained from the earlier conversational review.*

- **Resolution:** any substance they carried is already reflected in the rewritten sections;
  flagged here for transparency. Re-confirm if the original text matters.

## F. Clarification rulings (integrated)

These are the rulings produced during the review Q&A; each is applied where noted. (A handful
of minor lettered items from the original inventory were not retained verbatim and are covered
by the N7/N10/N14–N16 note above.)

| Ruling | Applied in |
|---|---|
| Employee identity = **ticket issuer** (`issued_tickets.user_id`), re-stamped at issue, immutable via `edit()`; record `user_id` = executor audit only; filter param `issuer_id` | §3, §4.1, §6, tests 5/6 |
| Exemption is **all-or-nothing** — no partial amounts, no `reduction_amount` column, no cap, no Rule 6 | §2, §3, §4.5, §5, §10 |
| Exempt/Revert is a **bare `payment_by` flip**; never touches `payment_option`, `refund_adjustment_amount`, Payment, Voucher, `refund_payable`; allowed on `refund_adjustment` settlements | §2, §3, §4.5, tests 17/18 |
| Three predicates **Active / Blocking / Audit**; `softDeletes()` added; raw joins state `deleted_at IS NULL`; one-active-row enforced in code under lock | §4.4, §5, tests 16/33/34 |
| Edit gate: **any change landing on `company`** requires admin tier {Super, Co, Ticket Admin} + ≤24h; `employee→company` also blocked by any Blocking row; `company→employee` blocked by Active row; `company→customer/airline` soft-deletes active row; **creation-time company open to all ticket roles** | §4.6, §10, tests 27–32 |
| Company tab = `payment_by = company` records **only**; adjustments are annotations; "received from employees" = labelled subset (double-count fix) | §4.3, §6, §8, tests 10/11 |
| Exclusions policy: NULL `payment_by` → unassigned counter; amount ≤ 0 → footnote + exempt 422; orphans → footnote count | §4.2, §6, tests 23–25 |
| P/L correction: re-issue exemption **does** change P/L (observer recalc); refunds do not | §3, §4.7, tests 19/20 |
| Soft delete: **no observer changes** (B6 rescinded); scopes drop parent + adjustments together | §4.4, §12 |
| Test list reworked from scratch (old test 10 deleted; 36 tests) | §11 |
| Layout rulings: Exempt on employee drill-down (reason-only modal), Revert on company row, both Super/Co; view access Super/Co/Ticket Admin; print-only export | §3, §8, §9 |

## Verified-correct (no change needed)

- Roles match `RoleSeeder` (12 roles incl. `Super Admin`, `Co Admin`, `Ticket Admin`,
  `Ticket Staff`, `Branch Staff`); `role:` alias registered at `bootstrap/app.php:35` →
  `CheckRole` (403 on mismatch).
- Route location (~L364, next to pending-outbound `:363–364`) and nav refs
  (`nav.blade.php:7–10`, `:40–43`, `:150–153`) match the plan.
- All 4 creation sites stamp `user_id = auth()->id()` (`ReIssueController:123`,
  `RefundController:97`, `TicketRequestController:229/450`) — consistent with treating it as an
  executor stamp only.
- Only two `'status' => 'issued'` writes exist app-wide (`issue()` + direct creation), so the
  issuer is stable before any demurrage row can exist.
- No hard-delete path exists for re-issue/refund tickets today (0 matches repo-wide).
- `.tab-btn` CSS ref (`due.blade.php:98–113`) and JS pattern (`:470–527`) are accurate.
- `total_cost` is persisted only at edit (`TicketIssueController:321`) and creation
  (`ReIssueController:154`, `TicketRequestController:278`); refunds have **no** edit path, so
  no equivalent guard is needed there.
- `total_cost = rawCost − refund_adjustment` (`ReIssueController:148–154`) confirms exemption
  on settled records cannot double-count.
- `refund_compensation` formula (`net_fare − iata_refunded_amount`) matches
  `RefundController.php:103`.
- Legacy backfill attribution is approximate (`BackfillIssuedTickets:40`,
  `BackfillPendingOutbound:49`, fallback `?? 1`) — pre-existing data quality caveat, not a plan
  defect.

## Amendments checklist

- [x] A1: booking resolution join + eager-load spec (§4.2, §6, §8)
- [x] A2: corrected `due.blade.php` line refs; page-level tabs (§8)
- [x] A3: reworded §1 `refund_compensation` claim (§1, §3)
- [x] A4: real migration filename (§5)
- [x] B5: `payment_by` edit-gate matrix (replaces Rule 6/8) (§4.6, §10)
- [x] B6: auto-revert **rescinded**; predicates + explicit joins instead (§4.4, §5)
- [x] B7: `DB::transaction` + `lockForUpdate` in `exempt()`/`revert()` (§6)
- [x] B8: NULL `payment_by` → "Unassigned" counter (§4.2)
- [x] B9: amount ≤ 0 → excluded + footnote + exempt 422 (§4.2)
- [x] C10: `data()` payload shape frozen (§6)
- [x] C11: pagination semantics set (full-set summary; 25/page rows) (§6)
- [x] C12: reduction/company row date = source record date (§6)
- [x] C13: company total counts each record exactly once (§4.3)
- [x] C14: revert throttle + binding; reason-field decision documented (§5, §7)
- [x] D15: report-only / P&L footnote, corrected (§4.7)
- [x] N1–N6, N8, N9, N11–N13: resolved in §4.2, §4.4, §5, §6, §7, §9
- [ ] N7, N10, N14–N16: wording not retained — re-confirm if needed
