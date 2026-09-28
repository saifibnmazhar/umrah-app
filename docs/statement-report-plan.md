# Plan: Rebuild `/reports/statement` (Ticket Statement)

> Target: match `docs/Sales & Customer Statement Format.xlsx`.
> Route: `http://127.0.0.1:8000/reports/statement` (currently a static placeholder).

---

## 1. Excel Spec (decoded)

**Header — 2 rows** (rows 3 & 4 in the sheet):

| Col | Row 1 | Row 2 | Merge |
|-----|-------|-------|-------|
| A | Issue Date | Category (Ticket/Re-issue/Payment/Refund) | stacked |
| B | Ticket No | Invoice No | stacked |
| C | PAX Name | Customer Name | stacked |
| D | PNR | Passport | stacked |
| E | Sector | Carrier \| Class \| Pay | stacked |
| F | Flight Date | Return Date | stacked |
| G | Customer Amount | `SAR` label | `rowspan=2` |
| H | Agent Fare (Net) | `SAR` label | `rowspan=2` |
| I | MARKUP | `SAR` label | `rowspan=2` |
| J | Customer Refund | `SAR` label | `rowspan=2` |
| K | IATA Refund | `SAR` label | `rowspan=2` |
| L | Payment to IATA | `SAR` label | `rowspan=2` |
| M | Balance Agent | `SAR` label | `rowspan=2` |
| N | IATA Agent | Ticket Staff | stacked |

**Data — each record renders as 2 stacked `<tr>` rows:**

- **Row 1 (primary):** Issue Date, Ticket No, PAX Name, PNR, Sector, Flight Date, money columns (G–M incl. running Balance Agent), IATA Agent.
- **Row 2 (secondary):** Category, Invoice No, Customer Name, Passport, Carrier|Class|Pay, Return Date, `SAR` labels under G–M, Ticket Staff.

**Footer — Ticket Statement Summary box (bottom-left of last page):**

- Opening B/L, Total Ticket's, Total Sale Amount, Total Cus RFND
- Closing B/L, Total Agent Fare, Total MarkUP, Total Agent RFND

**Filters:** SEARCH BOX (PNR/TICKET NUM/PASSPORT/INVOICE), Filter Agents, Filter Travel Dates (From/To), Filter Issue Dates (From/To).

---

## 2. Scope Decisions (confirmed)

- **Full:** layout + live data (controller, JSON API, running balance, summary footer). No PDF/Excel export this iteration.
- **Balance logic:** `+ issued_tickets.net_fare` per Ticket, `+ re_issued_tickets.total_cost` per Re-issue, `− refunded_tickets.iata_refunded_amount` per Refund, `− payments.amount` per Payment. Opening B/L = same sums over all history **before** `issue_from`.
- **Row sources:** Ticket + Payment + Refund + **Re-issue** (4 categories).
- **Access:** `role:Super Admin,Co Admin,Ticket Admin` on both page and API routes.

---

## 3. Implementation Steps

### 3.1 Routes — `routes/web.php:332`

```php
// Replace closure:
Route::get('/reports/statement', [StatementController::class, 'index'])
    ->name('report.statement')
    ->middleware('role:Super Admin,Co Admin,Ticket Admin');

Route::get('/api/reports/statement', [StatementController::class, 'data'])
    ->name('api.reports.statement')
    ->middleware('role:Super Admin,Co Admin,Ticket Admin');
```

Add `use App\Http\Controllers\StatementController;`.

### 3.2 New controller — `app/Http/Controllers/StatementController.php`

Pattern sources: `TicketAgentReportController` (index/data JSON), `VisaAgentReportController:246-254` (running balance), `PendingOutboundReportController` (query building + filters).

**`index()`** — load `TicketAgent::orderBy('name')->get()` → `view('reports.statement')`.

**`data(Request $request)`** — filters:

| Param | Applies to | Notes |
|---|---|---|
| `search` | all rows | matches ticket_number, pnr, passport_no, invoice (booking.invoice_id), pax/customer name |
| `agent_id` | all rows | `ticket_agent_id`; required per-agent balance grouping |
| `issue_from` / `issue_to` | date column of each source | default: start of current month → today |
| `travel_from` / `travel_to` | outbound/inbound dates of ticket-like rows | payment rows not travel-filtered |

**Source queries (merged, sorted by date asc):**

| Category | Source | Date col | Balance | Row values |
|---|---|---|---|---|
| Ticket | `issued_tickets` | `issued_date` | **+ `net_fare`** | Customer Amt = `selling_fare`, Agent Fare = `net_fare`, MARKUP = selling − net |
| Re-issue | `re_issued_tickets` | `re_issue_date` | **+ `total_cost`** | Customer Amt = `total_customer_payment`, Agent Fare = `total_cost`, MARKUP = diff |
| Refund | `refunded_tickets` | `refund_date` | **− `iata_refunded_amount`** | Customer Refund = `refund_to_customer`, IATA Refund = `iata_refunded_amount` |
| Payment | `payments` (`ticket_agent_id` NOT NULL) | `payment_date` | **− `amount`** | Payment to IATA = `amount` |

**Balance rules:**

- Opening B/L = Σ(same deltas) over rows with `date < issue_from` for the selected agent(s).
- Running balance accumulates row-by-row (date asc) in the period.
- Closing B/L = Opening + Σ period deltas.
- If agent filter = All, running balance is computed **per agent independently** (grouped by `ticket_agent_id`).

**Row payload shape (one entry = one logical record = 2 `<tr>`s):**

```json
{
  "date": "01-Mar-26",
  "category": "Ticket | Re-issue | Payment | Refund",
  "ticket_no": "779-9461466422",
  "invoice_no": "INV #04499",
  "pax_name": "Md Kamal",
  "customer_name": "ISLAM/MD ROBIUL MR",
  "pnr": "JJUKMH",
  "passport": "A1234567",
  "sector": "RUH-DAC",
  "carrier_class_pay": "BS | Eco | Cash",
  "flight_date": "10-Apr-26",
  "return_date": "-",
  "customer_amount": 2820.00,
  "agent_fare": 2700.00,
  "markup": 120.00,
  "customer_refund": null,
  "iata_refund": null,
  "payment_to_iata": null,
  "balance": 2820.00,
  "agent_name": "FLYBURJ",
  "staff_name": "Shahadat"
}
```

**Column mapping for Row 2 data:**

- Invoice No → `booking.invoice_id` (via `issued_ticket->booking`, `refund->issuedTicket->booking`, `payment->booking/invoice`).
- Customer Name → `booking.customer->name`.
- Passport → `passenger->passport_no`.
- Sector → `passenger->route_display` accessor.
- Carrier|Class|Pay → ticket fare airline + class + linked `payments.payment_method` (`cash`/`bank`), fallback `-`.
- Flight Date → outbound; Return Date → inbound (`inbound_date` / `passenger->flight_date_to`).
- Ticket Staff → `issued_tickets.user_id → users.name`.

**Response JSON:** `{ "rows": [...], "summary": {...}, "agents": [...] }`.

**Summary keys:** `opening_balance`, `total_tickets`, `total_sale_amount`, `total_customer_refund`, `closing_balance`, `total_agent_fare`, `total_markup`, `total_agent_refund`, `total_reissue_cost`, `total_paid`.

### 3.3 Rewrite view — `resources/views/reports/statement.blade.php`

- Alpine `x-data` with `filters`, `rows`, `summary`, `loading`; `init()` calls `loadData()`; `loadData()` builds `URLSearchParams` and fetches `/api/reports/statement` (pattern: `pending-outbound.blade.php:605-631`).
- **Filters row:** SEARCH BOX input, Filter Agents select, Filter Travel Dates From/To, Filter Issue Dates From/To, Search button (with `@input.debounce.300ms`).
- **`<thead>` = 2 `<tr>`:**
  - Row 1: `th` for A–F + N (stacked), `rowspan="2"` for G–M.
  - Row 2: `th` for Category, Invoice No, Customer Name, Passport, Carrier|Class|Pay, Return Date, Ticket Staff.
  - Grouped-header reference: `reports/fingerprint/index.blade.php:129-161`.
- **Body:** `<template x-for>` renders **two `<tr>` per record** (primary then secondary, one after another); category-colored rows (`table-row-ticket`, `table-row-pymt`, `table-row-refund`, `table-row-reissue` — style like `ui-references/statement.html`); second row shows `SAR` labels under money columns.
- **Column hide/show toggles** (Alpine booleans) for Customer Amount, MARKUP, Customer Refund — Excel "Add/Hide Options" (J23–J26).
- **Footer:** Ticket Statement Summary box (8 fields, bottom-left) + loading/empty states.
- No export buttons this iteration.

### 3.4 TDD tests — `tests/Feature/StatementReportTest.php` (write first)

Use `RefreshDatabase`; fixture helpers patterned on `ReportQueryOptimizationTest::createBookingWithPassengers()` and `ProfitLossReportBranchFilterTest`.

1. `test_api_returns_all_four_categories` — fixtures for ticket/reissue/refund/payment; assert row categories + payload shape.
2. `test_running_balance_and_opening_closing` — Opening B/L from pre-period history; running Balance Agent per row; Closing = Opening + deltas.
3. `test_issue_date_filter` — rows outside range excluded.
4. `test_travel_date_filter` — ticket rows filtered by outbound/inbound; payments unaffected.
5. `test_search_filter` — by ticket no / PNR / passport / invoice.
6. `test_agent_filter` — per-agent balance isolation when agent selected.
7. `test_role_middleware` — 403 for unauthorized role, 200 for Super Admin / Ticket Admin.
8. `test_view_renders_two_row_layout` — render view; assert stacked header (`Issue Date` + `Category`) and two-row record structure.

### 3.5 Verify (commit checklist)

```bash
php artisan test
vendor/bin/pint
npm run build
docker compose -f docker-compose.yml config --quiet
docker compose -f docker-compose.prod.yml config --quiet
```

---

## 4. Assumptions

1. Re-issue row: `Agent Fare (Net) = total_cost` (drives balance), `Customer Amount = total_customer_payment`.
2. Payment rows = `payments` where `ticket_agent_id` is set (payments made *to* the IATA agent).
3. Travel-date filter applies to ticket/reissue/refund rows only; payment rows settle regardless.
4. Currency label stays `SAR` as in the Excel (no multi-currency handling).
5. Default issue-date range = current month.
6. No pagination initially — date-bounded query, all rows returned (default range is one month).

---

## 5. Files Touched

| File | Change |
|---|---|
| `routes/web.php` | Replace closure route; add API route + role middleware |
| `app/Http/Controllers/StatementController.php` | **New** — index/data |
| `resources/views/reports/statement.blade.php` | Full rewrite — 2-row header, stacked rows, filters, footer |
| `tests/Feature/StatementReportTest.php` | **New** — feature tests |

Reference only (do not modify): `docs/Sales & Customer Statement Format.xlsx`, `ui-references/statement.html`.
