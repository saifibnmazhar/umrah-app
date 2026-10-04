# Plan: Rebuild `/reports/statement` (Ticket Statement)

> Target: match `docs/Sales & Customer Statement Format.xlsx` (with the deviations
> recorded in §10 — Excel's balance example and "Cash" pay cell are explicitly wrong).
> Route: `http://127.0.0.1:8000/reports/statement` (currently a static placeholder).

---

## 1. Excel Spec (decoded) & Deviations

**Header — 2 rows** (rows 3 & 4 in the sheet; A–F & N are two stacked cells,
G–M are single cells merged `G3:G4 … M3:M4` — the `SAR` label lives in each
*data* row 2, not in the header):

| Col | Row 1 | Row 2 |
|-----|-------|-------|
| A | Issue Date | Category (Ticket/Re-issue/Payment/Refund) |
| B | **Reference ID** *(Excel says "Invoice No"; renamed — see §2)* | *(stacked)* |
| C | PAX Name | Customer Name |
| D | PNR | Passport |
| E | Sector | Carrier \| Class \| Pay |
| F | Flight Date | Return Date |
| G | Customer Amount | *(merged, `SAR` shown in data row 2)* |
| H | Agent Fare (Net) | *(merged)* |
| I | MARKUP | *(merged)* |
| J | Customer Refund | *(merged)* |
| K | IATA Refund | *(merged)* |
| L | Payment to IATA | *(merged)* |
| M | Balance Agent | *(merged)* |
| N | IATA Agent | Ticket Staff |

**Data — each record renders as 2 stacked `<tr>` rows:**

- **Row 1 (primary):** Issue Date, Ticket No, PAX Name, PNR, Sector, Flight Date, money columns (G–M incl. running Balance Agent), IATA Agent.
- **Row 2 (secondary):** Category, Reference ID, Customer Name, Passport, Carrier|Class|Pay, Return Date, `SAR` labels under G–M, Ticket Staff.

**Footer — Ticket Statement Summary box** (bottom-left of last page; all
**10** fields rendered — Excel shows 8 labels, we add `Total Re-Issue Cost`
and `Total Paid`, see §5).

**Filters (Excel row 1):** SEARCH BOX (PNR/TICKET NUM/PASSPORT/INVOICE), Filter Agents, Filter Travel Dates (From/To), Filter Issue Dates (From/To).

**Excel "Add/Hide Options" (J23–J26):** toggle Customer Amount, MARKUP, Customer Refund columns.

**Deviations from Excel (confirmed):**

1. Header col B label → **Reference ID** (was "Invoice No").
2. `Carrier | Class | Pay` → **Pay is an amount** (offer-aware fare), not the word "Cash".
3. Balance formula → our own Paid − Payable (Excel's example is wrong, see §10 I7/I8).
4. Footer → 10 fields, not Excel's 8 labels.
5. No export buttons this iteration (Excel has "Export Options pdf/excel" — deferred).

---

## 2. Scope Decisions (confirmed)

- **Full:** layout + live data (controller, JSON API, running balance, summary footer). No PDF/Excel export this iteration.
- **Balance = Paid − Payable** (matches `TicketAgentReportController`; negative = due to agent). Per-row deltas:
  - Ticket: **− `issued_tickets.net_fare`**
  - Re-issue: **− `re_issued_tickets.total_cost`** (used as-is; the paired refund row already accounts for the IATA refund)
  - Refund: **+ `refunded_tickets.iata_refunded_amount`**
  - Payment: **+ `payments.amount`**
- **Row sources:** Ticket + Payment + Refund + Re-issue (4 categories).
- **Access:** `role:Super Admin,Co Admin,Ticket Admin` on both page and API routes.
- **No branch filter** this iteration.
- **Ticket rows:** only `issued_tickets.status IN ('issued', 're-issued', 'refunded')`.

---

## 3. Filters

Single date range + date-type selector:

```
[ Date Type ▾ ]  From [date]  To [date]   [ Agents ▾ ]  [ SEARCH BOX ]  [Search]
```

- **Date Type options:** `Issue Date` (default) · `Flight Date (Inbound Date)` · `Return Date (Outbound Date)`
- **Date column per type:**

| Date Type | Ticket/Re-issue/Refund rows | Payment rows |
|---|---|---|
| Issue Date | `issued_date` / `re_issue_date` / `refund_date` (`COALESCE(date, created_at)` for the latter two) | `payment_date` |
| Flight Date (Inbound) | `inbound_date` — NULL → row excluded | excluded |
| Return Date (Outbound) | `outbound_date` — NULL → row excluded | excluded |

- **Payment rows render only when Date Type = Issue Date.**
- **All filters (search, agent, date) affect balance math.** Opening B/L uses
  identical filters with `date < From`.
- **Range cap: 92 days** → 422 if exceeded. API route also throttled.

| Param | Applies to | Notes |
|---|---|---|
| `date_type` | which date column is used | `issue` (default) / `flight` / `return` |
| `date_from` / `date_to` | selected date column | required; max span 92 days; default: start of current month → today |
| `search` | all rows + math | matches ticket_number, pnr, passport_no, Reference ID (`bookings.invoice_id` or `vouchers.voucher_id`), pax/customer name |
| `agent_id` | all rows + math | `ticket_agent_id` (guaranteed NOT NULL, see §7); empty = All |

---

## 4. Column Mapping

| Column | Source |
|---|---|
| **Ticket No** | `ticket_number` on the row's own table (`issued_tickets` / `re_issued_tickets` / `refunded_tickets`); payment rows → `-` |
| **Reference ID** | Ticket/Re-issue/Refund → `bookings.invoice_id` (via `booking`, or `refund->issuedTicket->booking`, `reissue->issuedTicket->booking`); Payment → `payment->voucher->voucher_id`. Search matches both. |
| **Carrier \| Class \| Pay** | Airline/Class → `ticket_fare_id` **on the row's own table** → `ticket_fares.airline` / `airlineClass`, fallback `-`. **Pay → amount:** `offer_price` if `ticket_fares.ticket_type == 'offer'` else `selling_fare`, read from the row's own table (`group` → `selling_fare`) |
| **Customer Amount** | Same offer-aware amount as Pay |
| **MARKUP (Ticket/Re-issue/Refund rows)** | Ticket: `Pay − net_fare`. Re-issue: `re_issued_tickets.service_charge`. Refund: `refunded_tickets.service_charge` |
| **Flight Date** | `inbound_date` (row's table); payment rows `-` |
| **Return Date** | `outbound_date` (row's table); payment rows `-` |
| **Customer Name** | `booking.customer->name` |
| **PAX Name / Passport / Sector** | ticket → `passenger`; reissue/refund → `issuedTicket->passenger`; Sector = `passenger->route_display` accessor |
| **Ticket Staff** | `user_id` on the row's own table (`issuer()` on IssuedTicket, `user()` on ReIssuedTicket/RefundedTicket); **payment row → empty** |
| **IATA Agent** | `ticket_agent_id` → `ticket_agents.name` (NOT NULL) |

**Re-issue row** = full Ticket-style row, all values from `re_issued_tickets`
joined to booking/passenger via `issued_ticket_id` (required server-side —
nullable in DB only for historical reasons; no null handling needed).

**Payment rows short-circuit:** all ticket/pax/staff detail cells render `-`
without walking booking relations.

---

## 5. Running Balance & Summary

### 5.1 Running balance

1. Merge the four source queries (filters applied — including search).
2. Sort `date asc → category asc → id asc` (stable tie-break).
3. Iterate, keeping per-agent counters keyed by `ticket_agent_id`:
   - `opening[agent]` = Σ deltas of filtered rows with `date < date_from`
   - `running[agent]` starts at `opening[agent]`, += row delta each row
4. Row payload's `balance` = `running[agent]` after that row.
5. `closing_balance` = Opening + Σ period deltas (equals last row's balance per agent).

### 5.2 Display: agent-grouped sections

- **Agent filter = single agent:** one continuous table, rows date-asc.
- **Agent filter = All:** rows grouped by agent (ordered by `TicketAgent.name`):
  1. Section header row (full width): `FLYBURJ — Opening B/L: 15,000.00`
  2. That agent's rows, date-asc, coherent per-agent running balance
  3. Section total row: `FLYBURJ — Closing B/L: 22,000.00`
- Balance column always reads as one continuous sequence inside a section.
- Global footer: `opening_balance` = Σ agent openings; `closing_balance` = Σ agent closings.

### 5.3 Summary footer — all 10 fields

| Key | Formula |
|---|---|
| `opening_balance` | Σ deltas, filtered rows with `date < date_from` |
| `closing_balance` | Opening + Σ period deltas (= Σ agent closings) |
| `total_tickets` | COUNT of Ticket rows (status-filtered, in period) |
| `total_sale_amount` | Σ **Pay** (offer-aware amounts) |
| `total_customer_refund` | Σ `refunded_tickets.refund_to_customer` |
| `total_agent_fare` | Σ `issued_tickets.net_fare` |
| `total_markup` | Σ ticket-row markup (`Pay − net_fare`) + Σ `re_issued_tickets.service_charge` + Σ `refunded_tickets.service_charge` |
| `total_agent_refund` | Σ `refunded_tickets.iata_refunded_amount` |
| `total_reissue_cost` | Σ `re_issued_tickets.total_cost` |
| `total_paid` | Σ `payments.amount` |

All totals computed over period rows with all filters applied.

---

## 6. Implementation Steps

### 6.1 Routes — `routes/web.php:332`

```php
// Replace closure:
Route::get('/reports/statement', [StatementController::class, 'index'])
    ->name('report.statement')
    ->middleware('role:Super Admin,Co Admin,Ticket Admin');

Route::get('/api/reports/statement', [StatementController::class, 'data'])
    ->name('api.reports.statement')
    ->middleware(['role:Super Admin,Co Admin,Ticket Admin', 'throttle:30,1']);
```

Add `use App\Http\Controllers\StatementController;`.

### 6.2 New controller — `app/Http/Controllers/StatementController.php`

Self-contained — **no pattern references** for balance or header markup (see §10 I5).
Verified references kept: `TicketAgentReportController` (index/data JSON,
`paid − payable` sign), `PendingOutboundReportController` (query building + filters).

- **`index()`** — `TicketAgent::orderBy('name')->get()` → `view('reports.statement')`.
- **`data(Request $request)`** — validate params (`date_type`, `date_from/date_to`
  max 92 days, `search`, `agent_id`); run the four source queries per §3/§4;
  apply status filter (`issued`,`re-issued`,`refunded`) on `issued_tickets`;
  `COALESCE(date, created_at)` for `re_issue_date`/`refund_date`;
  model queries only (soft-deleted rows excluded automatically);
  merge → sort (`date, category, id`) → per-agent balances (§5.1) →
  group for display when All (§5.2) → summary (§5.3).
- **Response JSON:** `{ "rows": [...], "sections": [...] (when All), "summary": {...}, "agents": [...] }`.
  `agents` returned from `index()` view data only (not duplicated in `data()`).

**Row payload shape (one entry = one logical record = 2 `<tr>`s):**

```json
{
  "date": "01-Mar-26",
  "category": "Ticket | Re-issue | Payment | Refund",
  "ticket_no": "779-9461466422",
  "reference_id": "INV #04499",
  "pax_name": "Md Kamal",
  "customer_name": "ISLAM/MD ROBIUL MR",
  "pnr": "JJUKMH",
  "passport": "A1234567",
  "sector": "RUH-DAC",
  "carrier_class_pay": "BS | Eco | 2820",
  "flight_date": "10-Apr-26",
  "return_date": "-",
  "customer_amount": 2820.00,
  "agent_fare": 2700.00,
  "markup": 120.00,
  "customer_refund": null,
  "iata_refund": null,
  "payment_to_iata": null,
  "balance": -2700.00,
  "agent_name": "FLYBURJ",
  "staff_name": "Shahadat"
}
```

### 6.3 Write-side fix — L3 (2 small edits)

- `ReIssueController` and `RefundController` store
  `ticket_agent_id = $validated['ticket_agent_id'] ?? $sourceTicket->ticket_agent_id`
  (mirrors `TicketRequestController:233,454`).
- Report-side `COALESCE` **not used** — the NOT NULL constraint (§6.4) is the
  single source of truth; queries filter/group on `ticket_agent_id` directly.

### 6.4 Migration A — `database/migrations/2026_10_01_000001_backfill_and_not_null_ticket_agent_id.php`

1. Backfill `re_issued_tickets` / `refunded_tickets` `ticket_agent_id` from the
   source `issued_tickets.ticket_agent_id` where NULL (pre-check query: warn if
   any source ticket also has NULL).
2. `->change()` both columns to `NOT NULL`.

### 6.5 Migration B — `database/migrations/2026_10_01_000002_add_date_indexes_for_statement_report.php`

No date indexes exist today (verified):

- `issued_tickets`: `issued_date`, `inbound_date`, `outbound_date`, `(ticket_agent_id, issued_date)`
- `re_issued_tickets`: `re_issue_date`, `inbound_date`, `outbound_date`
- `refunded_tickets`: `refund_date`, `inbound_date`, `outbound_date`
- `payments`: `payment_date`, `(ticket_agent_id, payment_date)`

### 6.6 Rewrite view — `resources/views/reports/statement.blade.php`

- Alpine `x-data` with `filters`, `rows`, `summary`, `loading`; `init()` →
  `loadData()` builds `URLSearchParams` and fetches `/api/reports/statement`
  (fetch pattern: `pending-outbound.blade.php:605-631`).
- **Filters row:** Date Type dropdown (3 options), From/To dates, Agents select, SEARCH BOX input, Search button (`@input.debounce.300ms`).
- **`<thead>` = 2 `<tr>` — specified inline, no external reference:**

```html
<tr> <!-- row 1 -->
  <th rowspan="2">Issue Date</th> <th rowspan="2">Ticket No</th>
  <th rowspan="2">PAX Name</th> <th rowspan="2">PNR</th>
  <th rowspan="2">Sector</th> <th rowspan="2">Flight Date</th>
  <th rowspan="2">Customer Amount</th> <th rowspan="2">Agent Fare (Net)</th>
  <th rowspan="2">MARKUP</th> <th rowspan="2">Customer Refund</th>
  <th rowspan="2">IATA Refund</th> <th rowspan="2">Payment to IATA</th>
  <th rowspan="2">Balance Agent</th> <th rowspan="2">IATA Agent</th>
</tr>
<tr> <!-- row 2 -->
  <th>Category</th><th>Reference ID</th><th>Customer Name</th><th>Passport</th>
  <th>Carrier | Class | Pay</th><th>Return Date</th><th>Ticket Staff</th>
</tr>
```

- **Body:** `<template x-for>` renders **two `<tr>` per record** (primary then
  secondary). When Agent = All, insert agent **section header/total rows** (§5.2).
  Category-colored rows: `table-row-ticket`, `table-row-pymt`, `table-row-rfnd`,
  `table-row-reis` (new). Second data row shows `SAR` labels under money columns.
- **Row colors:** inline `<style>` block in this view (pattern:
  `reports/due.blade.php:32`) — do **not** touch `app.css`.
- **Column hide/show toggles** (Alpine booleans) for Customer Amount, MARKUP, Customer Refund.
- **Footer:** Ticket Statement Summary box with all 10 fields (bottom-left) + loading/empty states.
- No export buttons this iteration.

### 6.7 Nav gate — `resources/views/partials/nav.blade.php`

Lines 40 and 150: change `$canAccessTicket` → **`$canAccessTicketReport`**
(Ticket Statement visible only to Super Admin / Co Admin / Ticket Admin —
otherwise Ticket Staff sees the link and gets 403).

---

## 7. TDD Tests — `tests/Feature/StatementReportTest.php` (write first)

Use `RefreshDatabase`; fixture helpers patterned on `ReportQueryOptimizationTest::createBookingWithPassengers()` (private — copy, don't call) and `ProfitLossReportBranchFilterTest` (role attach: `Role::create` + `roles()->attach`).

1. `test_api_returns_all_four_categories` — fixtures for ticket/reissue/refund/payment; assert row categories + payload shape.
2. `test_running_balance_paid_minus_payable` — Paid − Payable signs per §2; opening from pre-period history; `closing_balance == last row balance`.
3. `test_date_type_filters` — Issue/Flight/Return each select the right rows; payment rows absent under Flight/Return; NULL inbound/outbound excluded under those types.
4. `test_date_range_cap` — span > 92 days → 422.
5. `test_search_filter` — ticket no / PNR / passport / `bookings.invoice_id` / `vouchers.voucher_id`.
6. `test_agent_grouped_sections` — All → section headers/closings per agent; single agent → flat table; per-agent balance isolation.
7. `test_role_middleware` — 403 for authenticated unauthorized role; 302 for guest (auth group).
8. `test_status_and_soft_delete_filters` — `pending` tickets excluded; soft-deleted rows excluded.
9. `test_created_at_fallback` — null `re_issue_date`/`refund_date` fall back to `created_at`.
10. `test_offer_aware_pricing` — offer fare → `offer_price`, regular → `selling_fare`; assert MARKUP, `total_sale_amount`, `total_markup`.
11. `test_reissue_row_joins_via_issued_ticket` — reissue row shows booking/passenger data through `issued_ticket_id`.
12. `test_agent_fallback_on_write` — reissue/refund created without explicit agent inherits source ticket's agent; DB rejects NULL (`ticket_agent_id` NOT NULL).
13. `test_summary_formulas` — assert each of the 10 footer fields against fixtures.
14. `test_view_renders_two_row_layout` — stacked header (`Issue Date` + `Category`), **Reference ID** label, 10-field footer, payment row short-circuit (no ticket/staff cells).

---

## 8. Verify (commit checklist)

```bash
php artisan test
vendor/bin/pint
npm run build
docker compose -f docker-compose.yml config --quiet
docker compose -f docker-compose.prod.yml config --quiet
```

---

## 9. Assumptions

1. Re-issue row: `Agent Fare (Net) = total_cost` (drives balance), `Customer Amount = offer-aware selling/offer price`, `MARKUP = service_charge`.
2. Payment rows = `payments` where `ticket_agent_id` is set (payments made *to* the IATA agent); shown only under Date Type = Issue Date.
3. Currency label stays `SAR` (no multi-currency handling).
4. Default date range = current month start → today, capped at 92 days.
5. No pagination — date-bounded query (≤ 92 days), all rows returned.
6. `issued_date` is always populated (client + server-side validation); `re_issue_date`/`refund_date` fall back to `created_at`.
7. `re_issued_tickets.issued_ticket_id` / `refunded_tickets.issued_ticket_id` always set (required server-side).
8. `ticket_agent_id` always set on re-issue/refund (write-side fallback + NOT NULL).
9. Money displayed with 2 decimals (`number_format`); no rounding of stored `decimal(14,6)` values in math.

---

## 10. Decisions Log (audit findings → resolutions)

| Item | Resolution |
|---|---|
| I1 CSS classes | Row colors via inline `<style>` in the view; classes `table-row-ticket`, `table-row-pymt`, `table-row-rfnd`, `table-row-reis` (new) |
| I2 Nav/role mismatch | Nav gate → `$canAccessTicketReport` (§6.7) |
| I3 Payment Invoice No | Header renamed **Reference ID**; payment rows show `vouchers.voucher_id`; search matches it |
| I4 §1 header table | Corrected — G–M are merged cells, `SAR` in data rows |
| I5 Reference drift | Plan no longer depends on `VisaAgentReportController` / fingerprint header; balance algorithm and rowspan markup specified in-plan (§5.1, §6.6) |
| I6 No re-issue sample in Excel | Re-issue row = full issue row + re-issue fields from `re_issued_tickets` (§4) |
| I7/I8 Excel balance example wrong | Excel example ignored; own Paid − Payable formula used (§2) |
| I9/L8 Refund/Re-issue markup | Refund → `refunded_tickets.service_charge`; Re-issue → `re_issued_tickets.service_charge` |
| I10 `issued_date` nullable | Guaranteed by validation; treated as non-null |
| L1 Filters + balance math | All filters affect math (§3) |
| L2 Re-issue after refund | `total_cost` used as-is; refund row accounts for IATA refund |
| L3 NULL agent | Write-side fallback (§6.3) + backfill + NOT NULL (§6.4) |
| L4 Nullable dates | `COALESCE(date, created_at)` (Issue Date type); NULL inbound/outbound excluded under Flight/Return types |
| L5 Soft deletes | Eloquent model queries only |
| L6 Summary formulas | Explicit formulas for all 10 keys (§5.3) |
| L7 Per-agent balance hostile | Agent-grouped sections when All (§5.2) |
| L9 Tie-breaking | Sort `date → category → id` |
| L10/L17 Performance | Throttle (`30,1`), 92-day cap, date indexes (§6.5) |
| L11 Misc gaps | `agents` loaded once in `index()`; 2-decimal display; `agent_id` empty = All; `carrier_class_pay` resolved via L13 |
| L12 Status filter | Only `issued`, `re-issued`, `refunded` |
| L13 Pay semantics | Pay = offer-aware fare amount; airline/class from row's `ticket_fare_id` |
| L14 Travel filter semantics | Date Type selector defines the exact column; NULL handling per §3 |
| L15 Branch filter | None this iteration |
| L16 Footer fields | All 10 rendered |
| L18 Footer formulas | Per §5.3 |
| Smaller 1 | Balance = Paid − Payable (like Ticket Agent Report) |
| Smaller 2 | Staff column empty for payment rows |
| Smaller 3 | Files table updated (§11) |
| Smaller 4 | Payment rows short-circuit (§4) |

---

## 11. Files Touched

| File | Change |
|---|---|
| `routes/web.php` | Replace closure; add API route + role middleware + throttle |
| `app/Http/Controllers/StatementController.php` | **New** — index/data |
| `app/Http/Controllers/ReIssueController.php` | Agent fallback on write (L3) |
| `app/Http/Controllers/RefundController.php` | Agent fallback on write (L3) |
| `database/migrations/2026_10_01_000001_backfill_and_not_null_ticket_agent_id.php` | **New** — backfill + NOT NULL |
| `database/migrations/2026_10_01_000002_add_date_indexes_for_statement_report.php` | **New** — date/agent indexes |
| `resources/views/reports/statement.blade.php` | Full rewrite — 2-row rowspan header, stacked rows, filters, agent sections, 10-field footer, inline row-color styles |
| `resources/views/partials/nav.blade.php` | Gate → `$canAccessTicketReport` |
| `tests/Feature/StatementReportTest.php` | **New** — feature tests |

Reference only (do not modify): `docs/Sales & Customer Statement Format.xlsx`, `ui-references/statement.html`.

---

## 12. Follow-up: NOT NULL Impact & Resolution (post-implementation audit)

### 12.1 Issues found (system-wide investigation)

| # | Issue | Blast radius |
|---|---|---|
| I-A | Migration A (`2026_10_01_000001`) throws mid-`migrate` on MySQL if orphan rows exist (NULL `issued_ticket_id` or NULL source agent); the `logger()->warning` does not prevent the crash | Deploy blocker (production `migrate`) |
| I-B | Reissue/refund on historically agent-less issued tickets → HTTP 500 (`SQLSTATE 23000`) on all 4 child writers (`TicketRequestController:242,463`, `ReIssueController:124`, `RefundController:98`) via NULL-inheriting fallback | Live users (reissue/refund flows) |
| I-C | Requiring agent at `TicketIssueController issue()/edit()` (`:43,:195`) breaks ~10 test payloads in 7 files expecting success without an agent | Tests only (list: `TicketVoidTest:218,521`, `IssuedDateRequiredTest:251,318`, `BookingInactiveFareSourcesTest:403`, `IssueFormIssuedTicketFareSourceTest:467`, `TicketIssueReIssueFareSourceTest:219`, `ReIssueCustomerPaymentDerivationTest:447,479,514`, `ReIssueEditRefundedNonCustomerTest:210`, `ReIssueEditRefundPayableAdjustTest:248`) |
| I-D | Extending NOT NULL to `issued_tickets.ticket_agent_id` would break booking creation (18 agent-less placeholder creates: `BookingController` store/add-passenger/update, `PassengerController` visa-only switch, `TicketIssueController` auto-creates, group-confirm, 2 backfill commands) | Must NOT do — `issued_tickets` stays nullable |

Verified safe (no action): all issue/edit UIs already force agent selection client-side; `processAdditional` already `required` server-side; group-confirm/create-pending/revert untouched; all profit/cost readers never select the agent column; seeders set agents; no jobs/listeners/internal callers; no later migration touches these columns.

### 12.2 Resolution plan

- **R0 — Pre-deploy orphan audit:** count `re/refunded_tickets` rows with NULL agent whose source is NULL/missing; backfill with a real (human-chosen) agent before deploying.
- **R1 — Harden Migration A:** fail fast with orphan IDs + audit queries instead of warn-and-crash.
- **R2 — Require agent at issue:** `TicketIssueController:43,195` `nullable` → `required` (UI already compliant; `:261` fallback kept).
- **R3 — 422 guard in 4 child writers** when resolved (explicit ?? source) agent is NULL (legacy-row safety net).
- **R4 — Update the ~10 test payloads** in the 7 files (fixture-only).
- **R5 — Non-goal:** `issued_tickets.ticket_agent_id` stays nullable.
- **R6 — Verify:** full suite + pint + build; manual 422 checks (issue without agent; reissue of legacy NULL-agent ticket); prod orphan audit = 0.
