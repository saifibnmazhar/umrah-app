# Architecture

> Part of the [Development Handbook](README.md) · **Mode:** Explanation
>
> This document describes how Umrah App is structured, what each layer does,
> and how requests flow through the system. Read this before making changes.

---

## Tech Stack

| Layer               | Technology                                              |
|---------------------|---------------------------------------------------------|
| Backend             | Laravel 12, PHP 8.4                                     |
| Database (prod)     | MySQL 8.0 + Redis 7                                     |
| Database (test)     | MySQL 8.0 (`umrah_test` database) — **not** SQLite      |
| Cache (prod)        | Redis 7 (CACHE_STORE=database for local dev)            |
| Queue (prod)        | Redis 7 (QUEUE_CONNECTION=database for local dev)       |
| Frontend            | Blade templates, Vite 7, Tailwind CSS v4, Alpine.js    |
| Reactive UI         | Livewire 3                                              |
| Testing             | PHPUnit 11                                              |
| Code Style          | Laravel Pint (PSR-12 + Laravel preset)                  |
| Container           | Docker (multi-stage: Node 22 + PHP 8.4-fpm-alpine)      |
| CI/CD               | GitHub Actions → ghcr.io + Watchtower                   |
| PDF Generation      | setasign/FPDF + setasign/FPDI                           |
| Cloudflare          | monicahq/laravel-cloudflare (TrustProxies middleware)   |

> **Note on test database:** Unlike many Laravel projects, the test suite uses
> MySQL (`umrah_test` database) not SQLite in-memory. The `phpunit.xml` file
> configures `DB_CONNECTION=mysql`, `DB_DATABASE=umrah_test`, `DB_USERNAME=test`,
> `DB_PASSWORD=test`. Ensure a MySQL 8.0 server is running with these
> credentials before running `php artisan test`.

---

## Domain Overview

Umrah App is a management system for a **umrah travel agency** (Bin Mishal
Travels). The core workflow is:

```
Branch → Booking → Passenger(s) → [Visa | Ticket | Fingerprint] → Invoice → Payment → Voucher
```

- **Branches** represent physical office locations (KSA and BD). Each user belongs
  to a branch, except global admins (Super Admin, Co Admin) who have no branch.
- **Bookings** are the central entity — they link passengers to a package/route,
  carry the total invoice value, discount, fingerprint configuration, and branch
  assignment. A booking generates exactly one invoice.
- **Passengers** are the travelers. Each passenger may have visa submissions,
  issued tickets, and fingerprint records. Passenger type (adult/child/infant)
  is calculated from date of birth.
- **Packages** bundle a ticket fare + visa selling price + service charge.
  Packages can be "double ticket" (separate inbound/outbound fares).
- **Fingerprints** — a booking has one Fingerprint record (with a deadline and
  cost), which contains FingerprintDetail records (one per passenger). Fingerprint
  details can be rescheduled.
- **Visas** — each passenger has one VisaSubmission (latestOfMany), which tracks
  status (pending/submitted/issued/cancelled), costs (net, additional, final),
  and is linked to a VisaAgent and optionally a CommissionAgent.
- **Tickets** — passengers have IssuedTickets (soft-deleted on re-issue/refund),
  which can be re-issued (ReIssuedTicket) or refunded (RefundedTicket).
- **Invoices** — one per booking. Tracks total_amount, paid_amount, balance,
  status (pending/partial/paid/cancelled/refunded).
- **Payments** — financial transactions (cash or bank) against an invoice or
  for agent payments. Each payment creates a corresponding Voucher.
- **Vouchers** — the ledger entries. Every payment maps to a voucher with a
  transaction_type controlling debit/credit direction.
- **Reports** aggregate data across branches, users, date ranges for profit/loss,
  due reports, visa/ticket/fingerprint reports, and payment receiving.

---

## Directory Structure

```
app/
├── Console/          # Artisan commands (data migration, backfill, sync)
├── Concerns/         # Reusable traits (HandlesBranchAccess, FiltersBookingStatus)
├── Enums/            # 26 PHP backed enums (DiscountType, Gender, etc.)
├── Exceptions/       # Custom exception handlers
├── Http/
│   ├── Controllers/  # 54 controllers (resource + report controllers)
│   ├── Middleware/   # CheckRole (RBAC), CheckActive (login state), EnsureTicketRequestAccess, TrustProxies
│   ├── Requests/     # 3 Form Request validation classes (StoreBookingRequest, UpdateBookingRequest, StorePassengerRequest)
│   └── ...
├── Livewire/         # 30+ Livewire components (dashboard, list tables, reports)
├── Models/           # 56 Eloquent models
├── Observers/        # 6 model observers (audit logging to update log tables)
├── Providers/        # AppServiceProvider (observer registration, Blade directives, HTTPS forcing)
├── Queries/          # 6 query repository classes (BookingIndexQuery, etc.)
├── Services/         # 7 service classes (business logic)
├── Support/          # DateFormatter (per-user timezone), DiagnosticLogger
└── Traits/           # ConvertsDocumentsToPdf (FPDF/FPDI)

database/
├── migrations/       # 123 migration files (~35 core tables + 7 audit log tables)
├── factories/        # 1 factory (UserFactory — extend for new models)
└── seeders/          # 24 seeders (roles, users, transaction types, sample data)

resources/
├── views/            # 166 Blade views across 40+ domain directories
│   ├── components/     # 13 reusable Blade components
│   ├── layouts/       # app.blade.php (main layout)
│   ├── partials/      # nav.blade.php, form modals
│   ├── livewire/      # 25+ Livewire component views
│   └── ...            # domain-specific views (bookings, visas, reports, etc.)
├── css/              # app.css (Tailwind CSS entry)
└── js/               # app.js, booking.js, bootstrap.js, utils/duration.js

routes/
├── web.php           # All routes (~120 routes in one file)
└── booking-cancellation.php  # Sub-routed file included at end of web.php

config/               # Standard Laravel config files (auth, database, mail, queue, etc.)

docker/               # Docker build scripts and entrypoint
.github/              # CI/CD workflows
docs/                 # Development handbook (this directory)
ui-references/        # Original HTML/JS design files (read-only reference)
```

---

## Key Patterns

### Model-View-Controller (MVC) + Service Layer

The application follows a layered architecture:

```
HTTP Request
    ↓
routes/web.php (+ booking-cancellation.php)
    ↓
Middleware: auth → role → active
    ↓
Controller (constructor DI for Services)
    ↓
Form Request (validation) OR inline validation
    ↓
Service → Query Repository / Model (Eloquent)
    ↓
Database (MySQL 8.0)
    ↓
Observers fire on model events → audit log tables
    ↓
Livewire components (reactive tables/dashboard)
    ↓
Blade view → Vite assets (Tailwind + Alpine + Livewire)
    ↓
HTTP Response
```

### Service Layer

Business logic lives in **service classes** (`app/Services/`), injected into
controllers via constructor dependency injection. Services handle financial
calculations, booking creation, payment processing, and cancellations.

```php
// app/Http/Controllers/BookingController.php
public function __construct(
    private BookingService $bookingService,
) {}
```

**Services:**

| Service               | Purpose                                                              |
|-----------------------|----------------------------------------------------------------------|
| `BookingService`      | Booking creation, passenger type calculation (age-based), discount calculation (fixed_amount or percentage), package value calculation (ticket fare + visa price + service charge), booking total recalculation, `syncFinancials()`, `generateInvoiceId()` (branch-prefixed random: `BRNC-XXXX26`) |
| `InvoiceService`      | Invoice creation for bookings, payment status updates (pending/partial/paid/cancelled/refunded), balance calculation, `canAcceptPayment()` (50 SAR overpayment buffer), `updateTotals()` |
| `PaymentService`      | Customer payment creation (DB::transaction), agent payments (ticket/agent/commission), currency conversion via `CurrencyRateService` |
| `VoucherService`      | Voucher number generation (`VCH-YYYYMMDD-XXXX` sequential), voucher creation |
| `CancellationService` | Booking cancellation (refund = totalPaid - totalCost - serviceChargeDeduction), creates `CancelledBooking` record, links deduction/refund Payment+Voucher |
| `CostTrackingService` | Per-passenger cost breakdown (fingerprint cost / passenger count + visa cost + ticket net fare), booking cost summary, profit calculation |
| `CurrencyRateService` | In-request cache keyed by date, three-tier rate resolution (explicit relation → effective on date → first/oldest rate), SAR↔BDT conversion |

### Query Repositories

Complex or repeated database queries are encapsulated in **query repository
classes** (`app/Queries/`). Controllers delegate to these classes instead of
building inline query-builder chains. This eliminates duplication between initial
page renders (controller) and Livewire AJAX data endpoints.

| Query class              | For                                                                 |
|--------------------------|---------------------------------------------------------------------|
| `BookingIndexQuery`      | Booking index page + Livewire `BookingIndexTable` data endpoint     |
| `PassengerIndexQuery`    | Passenger index Livewire table data endpoint                        |
| `FingerprintReportQuery` | Fingerprint report filtering + eager loading                        |
| `TicketAgentReportQuery` | Ticket agent report data (payable, paid, refunds, reissues)         |
| `VisaAgentReportQuery`   | Visa agent report data (submitted, issued, cancelled, payments)     |
| `BranchWiseReportQuery`  | Branch-wise report + dashboard summary + payment history print view |

Constructor takes a `Request`, applies filters (search, date ranges, status,
branch scoping), and exposes `getQuery()`, `paginate()`, and `getSummary()`.

### Concerns (Reusable Traits)

Shared cross-cutting logic lives in `app/Concerns/`, used by all controllers via
the abstract `Controller` base class:

```php
// app/Http/Controllers/Controller.php
abstract class Controller
{
    use FiltersBookingStatus;
    use HandlesBranchAccess;
}
```

| Trait | Purpose |
|-------|---------|
| `HandlesBranchAccess` | Role checks (`isAdmin`, `isBranchScoped`, `isGlobalNonAdmin`), branch-scoped access enforcement (`ensureBranchAccess`, `ensurePassengerBranchAccess`, `ensureCancellationAccess`), edit window (12h, admins exempt), permission checks (`canEditVisa`, `canEditFingerprint`, `canEditTickets`, `canViewSummaryCards`, `canViewProfitCards`, `canViewTicketRequests`, `canFilterByAgent`, `canDeleteBooking`), branch resolution (`resolveBookingBranch`) |
| `FiltersBookingStatus` | Reusable `bookingStatus` / `bookingStatusViaBooking` query scopes for active / cancellation_processing / cancelled states |

### Livewire Components

Reactive list/table views use Livewire. `app/Livewire/BaseListTable.php` is an
abstract base class providing `WithPagination` trait, `resetSearch()`, and a
fluent `applySearch()` helper (supports both simple column names and custom
`[operator, value]` pairs).

**Namespace:** All Livewire components live under `App\Livewire` (not
`App\Http\Livewire`). Subdirectories map to dot notation in `<livewire:...>` tags.

```
app/Livewire/Dashboard/DashboardSummary.php
  → <livewire:dashboard.dashboard-summary />
```

**View location:** `resources/views/livewire/{kebab-path}/{name}.blade.php`

**Component categories:**

| Category         | Components                                                          |
|------------------|-----------------------------------------------------------------------|
| List Tables      | `BookingIndexTable`, `PassengerIndexTable`, `PackageListTable`, `BranchListTable`, `PaymentListTable`, `UserListTable`, `TicketFareTable`, `TicketFareIndexTable`, `FingerprintChargeTable`, `FingerprintChargeList`, `IndexDataTable` |
| Dashboard        | `DashboardSummary`, `DashboardRequestTabs`, `DashboardPackageSlider` |
| Reports          | `ProfitLossReportTable`, `FingerprintReportTable`, `VisaAgentReportTable`, `TicketAgentReportTable`, `BookingCancellationReportTable`, `DueReportTable`, `BranchWiseReportFilters`, `VisaReportTable` |
| Settings         | `FingerprintChargeTable`                                              |
| Booking          | `BookingIndexTable`, `PassengerIndexTable`                            |
| Package          | `PackageListTable`                                                    |
| Payment          | `PaymentListTable`                                                    |
| Fare             | `TicketFareTable`, `TicketFareIndexTable`                             |
| User             | `UserListTable`                                                       |
| Branch           | `BranchListTable`                                                     |
| Fingerprint      | `FingerprintChargeList`                                               |

List tables delegate data fetching to **query repository classes** in
`app/Queries/`. See [Livewire Best Practices](09-livewire.md) for details.

### Model Observers (Audit Logging)

6 observers are registered in `AppServiceProvider::boot()`:

| Model          | Observer              | What it logs                          | Log table             |
|----------------|-----------------------|---------------------------------------|-----------------------|
| Booking        | BookingObserver       | Created/updated (dirty fields), deleted | `booking_update_logs`  |
| Passenger      | PassengerObserver     | Created/updated (dirty fields), deleted | `passenger_update_logs` |
| Invoice        | InvoiceObserver       | Created/updated, deleted (with audit_reason) | `invoice_update_logs`  |
| FingerprintDetail | FingerprintDetailObserver | Fingerprint action logs | `fingerprint_detail_logs` |
| VisaSubmission | VisaSubmissionObserver | Visa status changes                   | `visa_update_logs`     |
| IssuedTicket   | IssuedTicketObserver  | Issued ticket actions                 | `issued_ticket_logs`   |

Plus `fingerprint_cost_logs` (tracks fingerprint cost changes, not via observer).

All log tables share this structure:
- `id`, foreign key to the parent model, `user_id`, `action` (string),
  `old_values` (JSON, nullable), `new_values` (JSON, nullable), `created_at` timestamp
- Log tables are append-only — they use `const UPDATED_AT = null` (no `updated_at`)
- The `InvoiceUpdateLog` adds a `reason` column for audit context

To add a new observer:
```php
// 1. Create the class in app/Observers/
class MyObserver
{
    public function created(MyModel $model): void { ... }
    public function updated(MyModel $model): void { ... }
    public function deleted(MyModel $model): void { ... }
}

// 2. Register in AppServiceProvider::boot():
MyModel::observe(MyObserver::class);
```

### Enums

The project uses **PHP 8.1+ backed enums** for domain values. All 26 enums are in
`app/Enums/`. See [Domain Reference](08-domain-reference.md) for the full list.

Usage in models (via `$casts`):
```php
protected $casts = [
    'discount_type' => DiscountType::class,
    'fingerprint_location' => FingerprintLocation::class,
    'is_cancelled' => 'boolean',
];
```

Usage in Blade:
```blade
@if($booking->discount_type === \App\Enums\DiscountType::FIXED_AMOUNT)
```

### Form Requests

Validation is handled by **Form Request classes** (not inline validation):

| Request              | For             |
|----------------------|-----------------|
| `StoreBookingRequest`  | Creating bookings  |
| `UpdateBookingRequest` | Editing bookings   |
| `StorePassengerRequest`| Creating passengers |

Usage:
```php
public function store(StoreBookingRequest $request)
{
    $validated = $request->validated();
    // ...
}
```

### Blade Components

13 reusable Blade components in `resources/views/components/`. See
[Domain Reference](08-domain-reference.md) for the full list and usage.

### Custom Blade Directives

The `@currency` directive renders multi-currency displays (SAR primary, BDT
secondary). Registered in `AppServiceProvider::boot()`:

```blade
@currency($booking->total_value, 2, $currencyRate?->rate, $booking->bdt_amount)
@endcurrency
```

Renders as:
```html
<span class="currency-display" data-sar="1234.560000" data-dec="2" data-rate="12.00" data-bdt="14814.720000">1,234.56</span>
```

The layout (`layouts/app.blade.php`) also sets two JS globals for client-side
currency formatting:
```javascript
window.__currencyRate  // SAR→BDT rate
window.__stayDurationLimits  // { minDays, maxDays }
```

### Role-Based Access Control

12 roles enforced via `CheckRole` middleware (`app/Http/Middleware/CheckRole.php`).
Routes are protected like:

```php
Route::middleware('auth')->group(function () {
    Route::resource('bookings', BookingController::class)
        ->middleware('role:Super Admin,Co Admin');
});
```

| #  | Role             | Scope                                                      |
|----|------------------|------------------------------------------------------------|
| 1  | Super Admin      | Full access — all modules, all branches                    |
| 2  | Co Admin         | All modules, all branches                                   |
| 3  | Auditor          | Read-only access to reports, invoices, payments            |
| 4  | Ticket Admin     | Manage fares, routes, airlines, ticket agents                 |
| 5  | Visa Admin       | Manage visa agents, visa submissions, visa rates            |
| 6  | Ticket Staff     | Issue tickets, manage ticket agents' customers              |
| 7  | Visa Staff       | Process visa submissions for assigned branches              |
| 8  | Fingerprint Admin| Configure fingerprint charges, manage fingerprint staff     |
| 9  | Fingerprint Staff| Process passenger fingerprints                              |
| 10 | Branch Manager   | Manage bookings and finances for own branch                 |
| 11 | Branch Staff     | Create bookings, issue documents for own branch             |
| 12 | Delivery Staff   | Receive passport/document handoffs                            |

Roles are seeded by `RoleSeeder`. Users get roles via the `user_roles` pivot
table (managed by the `UserRole` model). The `HandlesBranchAccess` trait
provides programmatic role checks with in-request caching.

See [Domain Reference](08-domain-reference.md) for the full role matrix and
permission matrix.

---

## Request Lifecycle

1. **Incoming HTTP request** → `routes/web.php`
2. **Middleware** applied in order: `auth` (user must be logged in), `role:...`
   (CheckRole — user must have one of the allowed roles), `active` (CheckActive),
   optional `ticket-request-branch` (EnsureTicketRequestAccess)
3. **Controller method** receives the request. Constructor DI injects Service
   classes and Query Repository classes. Form Request classes handle validation
   automatically via type-hint.
4. **Service layer** executes business logic:
   - `BookingService` — calculates passenger types, package values, discounts,
     creates invoices, syncs financials
   - `PaymentService` — wraps payment + voucher creation in a DB transaction,
     handles currency conversion
   - `CancellationService` — calculates refund amounts, creates CancelledBooking
     records, handles deduction/refund vouchers
   - `CostTrackingService` — computes per-booking cost summaries for profit reports
5. **Query repository** or **Eloquent model** interacts with the database
6. **Observers** fire on model events (creating, updating, deleting, restored)
   and write to the corresponding audit log tables
7. **Livewire** components render reactive tables/views (where applicable) —
   they delegate to the same query repositories as controllers
8. **Blade view** is rendered and returned to the user
9. **Vite** serves frontend assets (Tailwind CSS + Alpine.js + Livewire)

---

## Database Schema

### Schema Overview

The database has approximately 35 core entity tables plus 7 audit log tables.
Migrations are timestamped and applied in order. The schema is MySQL 8.0 in
production.

### Entity Relationship Diagram

```
                          ┌─────────────┐
                          │   users     │
                          └──────┬──────┘
                    (branch_id) │
                                 │
                    ┌──────────┴──────────┐
                    │     branches         │◄───┐
                    └──────────┬──────────┘    │
                               │               │
         ┌──────────┬──────────┼──────────┬─────┴──────────┐
         │          │          │          │                │
         ▼          ▼          ▼          ▼                ▼
   ┌─────────┐ ┌─────────┐ ┌────────┐ ┌────────┐     ┌──────────────┐
   │ bookings│ │ invoices│ │payments│ │vouchers│     │fingerprints   │
   └────┬────┘ └────┬────┘ └───┬────┘ └───┬────┘     └──────┬───────┘
        │           │          │          │                  │
        │    ┌──────┴──────┐  │   ┌──────┴──────┐           │ (has one)
        │    │customers    │  │   │trans_types  │           │
        │    └─────────────┘  │   └──────┬──────┘           │
        │    ┌──────┬──────┐  │          │                  │
        │    │packages│     │  │          │                  │
        │    └──────┴──────┘  │          │                  │
        │           │         │          │                  │
        │    ┌──────┴──────┐  │          │                  │
        │    │passengers   │  │          │                  │
        │    └──────┬──────┘  │          │                  │
        │           │         │          │                  │
        └───────────┼─────────┘          │                  │
                    │                    │                  │
    ┌───────────────┼───────────┐        │                  │
    │               │           │        │                  │
    ▼               ▼           ▼        │                  │
 ┌───────┐    ┌──────────┐ ┌─────────┐   │                  │
 │visa_  │    │issued_   │ │fprints│   │                  │
 │sub-   │    │tickets  │ │details│   │                  │
 │missions│    └────┬────┘ └────┬────┘   │                  │
 └───────┘         │          │        │                  │
                   │     ┌────┴────┐   │                  │
                   │     │ticket_  │   │                  │
                   │     │requests │   │                  │
                   │     └─────────┘   │                  │
                   │                   │                  │
                   ▼                   ▼                  ▼
            ┌────────────────┐  ┌──────────────┐  ┌──────────────┐
            │re_issued_tickets│  │refunded_     │  │fingerprint_   │
            └────┬───────────┘  │tickets        │  │detail_logs    │
                 │              └───────────────┘  └──────────────┘
                 │
            ┌────┴────┐
            │re_issue_│
            │refund_  │
            │reasons  │
            └─────────┘
```

### Core Tables

#### Users & Access Control

| Table          | Columns                                              | Description                              |
|----------------|------------------------------------------------------|------------------------------------------|
| `users`        | id, name, email, password, branch_id, is_active, email_verified_at, timestamps | System users; `branch_id` is nullable (null = global admin) |
| `roles`        | id, name, timestamps                                 | 12 predefined roles                      |
| `user_roles`   | id, user_id, role_id, timestamps                     | Pivot table (unique: user_id + role_id)  |
| `branches`     | id, name, address, contacts, location, fingerprint_operation, branch_code, timestamps | Physical office locations; `location` enum (KSA/BD), `branch_code` auto-uppercased |
| `districts`    | id, name, division, timestamps                       | Geographic districts for fingerprint charges |
| `booking_conditions` | id, title, description, is_active, sort_order, timestamps | Terms/conditions shown on booking forms |
| `stay_duration_limits` | id, min_days, max_days, timestamps       | Global stay duration config (default 1-85 days) |

#### Bookings & Passengers

| Table                  | Relationship to                | Description                                           |
|------------------------|-------------------------------|-------------------------------------------------------|
| `bookings`             | customer, package, branch     | Central entity; `invoice_id` (unique string), `booking_branch_id`, `fingerprint_branch_id`, `pax_qty`, `discount_type`/`discount_value`/`discount_amount`, `total_value`, `is_cancelled` |
| `passengers`           | booking                       | Travelers; `passenger_type` (adult/child/infant), `ticket_status`, `service_required`, `package_value`, `ticket_fare_id`/`ticket_fare_inbound_id`/`ticket_fare_outbound_id`, `is_ticket_held`, `refund_payable` |
| `passenger_statuses`   | —                             | Status labels (e.g., "Hold", "Cancel", "Visa Submitted") |
| `documents`            | Morph: owner_type/owner_id    | Polymorphic; `owner_type` enum (customer, passenger), `file_path` (512 chars), `display_name` |

#### Packages & Fares

| Table                  | Relationship to                | Description                                           |
|------------------------|-------------------------------|-------------------------------------------------------|
| `packages`             | ticket_fare, visa_selling_price | Bundled fare + visa price + service charge; `is_double_ticket` flag, `ticket_fare_inbound_id`/`ticket_fare_outbound_id` for double tickets |
| `package_configurations`| ticket_fare                   | Package templates (name, prices, status)              |
| `ticket_fares`         | airline, airline_class, route | Fare rates; `ticket_type` (regular/offer/group), `effective_from`/`effective_to`, `net_fare`/`selling_fare`/`offer_price`, child/infant fare percentages, `with_meal` |
| `ticket_fare_id` (on passengers) | —                    | Denormalized reference to the fare used for that passenger |
| `group_tickets`        | ticket_fare                   | Group ticket PNRs with `ticket_qty`, inbound/outbound dates |
| `baggage_allowances`   | ticket_fare                   | Per passenger_type + travel_direction baggage rules (unique: fare + type + direction) |
| `routes`               | airline, city_codes           | `route_type` (oneway_inbound/oneway_outbound/round/multi_city), `flight_type` (direct/transit), from/to/return cities, `additional_gap` |
| `route_multi_segments` | routes, city_codes            | Multi-city route segments (from/to + direction)      |
| `route_transits`       | routes, city_codes            | Transit cities with `transit_time` (minutes)          |
| `airlines`             | —                             | Airline name + code                                    |
| `airline_classes`      | airlines, classes             | Pivot: airline ↔ class                                 |
| `classes` (table)      | —                             | Travel classes (Economy, Business, etc.) — model: `TravelClass` |
| `airline_cities`       | airlines, city_codes          | Pivot: which cities an airline serves                  |
| `city_codes`           | —                             | Airport/city codes (city_name, code, country)         |
| `travel_classes`       | —                             | (Alias table name for `classes`)                       |

#### Fingerprints

| Table                     | Relationship to          | Description                                           |
|---------------------------|--------------------------|-------------------------------------------------------|
| `fingerprint_charges`     | district, user           | Per-district fingerprint cost (unique: district_id)  |
| `fingerprints`            | booking                  | One fingerprint record per booking; `deadline`, `cost`, `assigned_staff_id` (user) |
| `fingerprint_details`     | fingerprint, passenger   | One per passenger per booking's fingerprint; `status` enum (none/processing/approved/cancelled) |
| `fingerprint_detail_logs` | fingerprint_detail, user | Audit log of fingerprint status changes                |
| `fingerprint_cost_logs`   | fingerprint, user        | Audit log of cost changes (`cost`, `cost_updated_by`) |
| `rescheduled_fingerprints`| fingerprint_detail       | Reschedule tracking (reason, next_date, occurrence)   |

#### Visas

| Table                | Relationship to                  | Description                                           |
|----------------------|-----------------------------------|-------------------------------------------------------|
| `visa_agents`        | —                                 | Visa processing agents (name, address, contacts)      |
| `visa_agent_costs`   | visa_agent, user                  | Per-agent visa cost (unique: visa_agent_id)           |
| `visa_selling_prices`| user                              | Selling prices (unique per package); `is_locked` appended |
| `commission_agents`  | visa_agent                        | Commission agents under visa agents                   |
| `visa_submissions`   | passenger, visa_agent, commission_agent, visa_selling_price | `status` enum (pending/submitted/issued/cancelled), `net_visa_cost`, `additional_cost`, `final_cost`, `is_cancelled`, `agent_commission` |
| `visa_update_logs`   | visa_submission, user             | Audit log of visa status changes (new_values→status)  |
| `cancelled_submissions` | visa_submission                | Cancellation records with `cancellation_fee`           |

#### Tickets

| Table                | Relationship to                | Description                                           |
|----------------------|--------------------------------|-------------------------------------------------------|
| `ticket_agents`      | —                              | Ticket-selling agents (name, address, contacts)       |
| `issued_tickets`     | passenger, booking, ticket_fare, group_ticket, user | `ticket_number`, `pnr`, dates, fares, `issue_type` (regular/additional/pending_outbound), `status` (pending/issued/re-issued/refunded); **soft deletes** |
| `issued_ticket_logs` | issued_ticket, user            | Audit log (issued/edited/re-issued/refunded actions)  |
| `re_issued_tickets`  | issued_ticket, user, ticket_fare, ticket_agent, group_ticket, route, reason | Re-issue details: `re_issue_charge`, `fare_difference`, `other_costs`, `service_charge`, `payment_by` (customer/airline/employee), `payment_option` (customer_payment/refund_adjustment), `refund_adjustment_amount`, `total_customer_payment`; **soft deletes** |
| `refunded_tickets`   | issued_ticket, user, ticket_fare, ticket_agent, group_ticket, reason | Refund details: `iata_refunded_amount`, `refund_to_customer`, `service_charge`, `payment_by`; **soft deletes** |
| `ticket_requests`    | booking, passenger, issued_ticket, user | Re-issue/refund/additional ticket requests; `request_type`, `status`, result linkage to re_issued/refunded/issued tickets |
| `re_issue_refund_reasons` | —                          | Reason lookup table (`reason_of` enum: re-issue/refund, `name`, `default_payment_by`) |

#### Financial

| Table             | Relationship to                              | Description                                           |
|-------------------|----------------------------------------------|-------------------------------------------------------|
| `invoices`        | booking, branch, user                        | One per booking; `total_amount`, `paid_amount`, `balance`, `status` (InvoiceStatus enum) |
| `payments`        | invoice, booking, branch, user, banks, agents | Financial transactions; `payment_method` (cash/bank), `amount`, `bdt_amount`, `transaction_id`, `currency_rate_id`, sender/receiver banks, agent IDs |
| `vouchers`        | invoice, booking, payment, branch, user, transaction_type, banks, agents | Ledger entries (1:1 with payments); `voucher_id` (unique, format `VCH-YYYYMMDD-XXXX`), `amount`, `bdt_amount`, `transaction_id` |
| `transaction_types` | —                                         | 9 types controlling debit/credit: Initial Payment (credit), Due Collection (credit), Customer Refund (debit), Ticket Refund - Payment (debit), Ticket Refund - Re-issue (debit), Ticket Agent Payment (debit), Visa Agent Payment (debit), Commission Agent Payment (debit), Service Charge Deduction (credit) |
| `currency_rates`  | user                                         | SAR→BDT exchange rates; `rate` (decimal 10,4); resolved via three-tier logic (explicit relation → effective on date → first/oldest) |
| `banks`           | —                                            | Banks (name, description, currency, location); sender/receiver in payments |
| `flight_date_gaps`| —                                            | Date gap config (gap: integer) for passenger forms (7/10/14/30 days) |
| `refunds` (table)  | —                                            | (Appears in view but the model is `RefundedTicket`)    |

#### Audit Log Tables (7)

All audit log tables share a common structure: `id`, foreign key to parent model,
`user_id`, `action` (string), `old_values` (JSON), `new_values` (JSON),
`created_at`. They are append-only (`const UPDATED_AT = null`).

| Log table                   | Parent model          | Extra columns                    |
|----------------------------|-----------------------|----------------------------------|
| `booking_update_logs`       | Booking               | `booking_invoice_id`             |
| `passenger_update_logs`     | Passenger             | `passport_no`                    |
| `invoice_update_logs`       | Invoice               | `reason`, `booking_invoice_id`    |
| `visa_update_logs`          | VisaSubmission        | —                                |
| `fingerprint_detail_logs`   | FingerprintDetail     | —                                |
| `issued_ticket_logs`        | IssuedTicket          | —                                |
| `fingerprint_cost_logs`     | Fingerprint           | `cost` (decimal), `cost_updated_by` (user_id) |

### Financial Flow

```
Booking created → InvoiceService::createForBooking() → Invoice (status: pending)
    ↓
Customer makes payment → PaymentService::createCustomerPayment()
    ↓
  [DB Transaction]
  1. Payment::create() with invoice_id, booking_id, branch_id, user_id,
     amount (SAR), bdt_amount (SAR × rate), payment_method, bank/agent refs
  2. VoucherService::createVoucher() → Voucher::create() with
     voucher_id (VCH-YYYYMMDD-XXXX), same amounts, transaction_type_id
  3. InvoiceService::updatePaymentStatus() → updates invoice paid_amount,
     balance, status (pending→partial→paid)
    ↓
  Each Payment ↔ Voucher is a 1:1 relationship
  Voucher.transactionType controls debit/credit direction

Agent payments (ticket/agent/commission) → PaymentService::createAgentPayment()
    → Payment (invoice_id=null) + Voucher with agent-specific transaction type
    → No invoice update (these are payouts to agents)

Cancellation → CancellationService::initiateCancellation()
    ↓
  Creates CancelledBooking (status: 'cancellation processing')
  - total_paid = invoice.paid_amount
  - refund_amount = total_paid - total_cost - service_charge_deduction
  Creates Payment + Voucher for the deduction
  Creates Payment + Voucher for the refund (when confirmed)
  Links via deduction_payment_id/voucher_id, refund_payment_id/voucher_id

Currency conversion:
  CurrencyRateService resolves rate via:
    1. Explicit CurrencyRate relation (e.g., booking.currencyRate)
    2. Rate effective on the booking's created_at date
    3. First (oldest) rate on file
  @currency Blade directive: renders SAR amount with data-bdt attribute
```

### Key Model Relationships (Summary)

| Model              | Key Relationships (abbreviated)                                    |
|--------------------|----------------------------------------------------------------------|
| `User`             | belongsTo Branch, belongsToMany Role (via user_roles)                |
| `Branch`           | hasMany User, hasMany Booking (booking_branch_id)                    |
| `Customer`         | hasMany Booking, morphMany Document                                   |
| `Booking`          | belongsTo Customer, Package, Branch×2, FingerprintCharge, District, CurrencyRate; hasMany Passenger, Payment; hasOne Invoice, Fingerprint, CancelledBooking; morphMany Document |
| `Passenger`        | belongsTo Booking, PassengerStatus, TicketFare×3; hasOne VisaSubmission, FingerprintDetail, latestIssuedTicket; hasMany IssuedTicket, morphMany Document; hasManyThrough RefundedTicket |
| `Package`          | belongsTo TicketFare×3, VisaSellingPrice; hasMany Booking             |
| `Invoice`          | belongsTo Booking, Branch, User; hasMany Payment, Voucher              |
| `Payment`          | belongsTo Invoice, Booking, Branch, User, CurrencyRate, Bank×3, TicketAgent, VisaAgent, CommissionAgent, CancelledBooking, Passenger, RefundedTicket, ReIssuedTicket; hasMany Voucher |
| `Voucher`          | belongsTo Invoice, Booking, Payment, Branch, User, CurrencyRate, Bank, TicketAgent, VisaAgent, CommissionAgent, TransactionType, CancelledBooking |
| `VisaSubmission`   | belongsTo Passenger, VisaAgent, CommissionAgent, VisaSellingPrice; hasOne CancelledSubmission; hasMany VisaUpdateLog |
| `IssuedTicket`     | belongsTo Passenger, Booking, User, TicketAgent, TicketFare, GroupTicket; hasMany IssuedTicketLog, ReIssuedTicket, RefundedTicket, TicketRequest; **SoftDeletes** |
| `Fingerprint`      | belongsTo Booking, User; hasMany FingerprintDetail, FingerprintCostLog; hasOne firstCostLog |
| `FingerprintDetail`| belongsTo Fingerprint, Passenger; hasMany FingerprintDetailLog, RescheduledFingerprint |
| `Route`            | belongsTo Airline; belongsTo CityCode×3 (from/to/return); hasMany RouteMultiSegment, RouteTransit, TicketFare |

> See [Domain Reference](08-domain-reference.md) for the complete model list and
> detailed relationship tables.

---

## Frontend Stack

### Blade Templates
- ~166 Blade views across 40+ domain directories (`bookings/`, `visas/`,
  `fingerprints/`, `reports/`, `invoices/`, `payments/`, `settings/`, etc.)
- 13 reusable Blade components in `resources/views/components/`
- Layout: `resources/views/layouts/app.blade.php` — includes `@vite()`,
  `@livewireStyles`, `@livewireScripts`, CSRF token, toast component,
  JS globals for currency rate and stay duration limits
- Navigation: `resources/views/partials/nav.blade.php`
- UI reference: `ui-references/` folder contains original HTML/JS design files —
  **do not modify**, use as visual references only

### Alpine.js
- All interactivity uses Alpine.js (`x-data`, `x-show`, `x-model`, `@click`, `:class`)
- No Vue or React
- Reusable `x-data` logic extracted into named data functions registered via
  `Alpine.data()` in `resources/js/alpine-data.js`
- Always pair `x-show` with `x-cloak` to prevent FOUC
- `@currency` Blade directive for multi-currency display

### Livewire 3
- Reactive dashboard summaries, list tables, and filter forms
- Components live in `app/Livewire/` with view files in
  `resources/views/livewire/`. See [Livewire Best Practices](09-livewire.md).

### Asset Pipeline
- **Vite 7** for asset bundling (configured in `vite.config.js`)
- **Tailwind CSS v4** via `@tailwindcss/vite` plugin
- Custom CSS in `resources/css/app.css` with `@theme` blocks
- Entry points: `resources/css/app.css`, `resources/js/app.js`

---

## Testing Strategy

### Test Setup
- **Framework:** PHPUnit 11 (via Laravel's built-in testing)
- **Test DB:** MySQL 8.0 (`umorah_test` database) — configured in `phpunit.xml`
- **Test cases:** `tests/TestCase.php` (base case), `tests/Feature/*`, `tests/Unit/*`

The `phpunit.xml` configures the test environment:
```xml
<env name="DB_CONNECTION" value="mysql"/>
<env name="DB_DATABASE" value="umrah_test"/>
<env name="DB_USERNAME" value="test"/>
<env name="DB_PASSWORD" value="test"/>
<env name="CACHE_STORE" value="array"/>
<env name="QUEUE_CONNECTION" value="sync"/>
<env name="SESSION_DRIVER" value="array"/>
<env name="LARAVEL_CLOUDFLARE_ENABLED" value="false"/>
```

### Running Tests
```bash
php artisan test              # all tests
php artisan test -v           # verbose
php artisan test --filter=test_method_name   # specific test
vendor/bin/pint               # format code
npm run build                 # build frontend assets
```

### Writing Tests
- Use `RefreshDatabase` for DB-interacting tests
- Use `actingAs($user)` instead of `withoutMiddleware()` (see [Testing](05-testing.md))
- Use `UserFactory::create()` for test users
- For new models, create factories: `php artisan make:factory ModelFactory --model=Model`
- For test-only tables: define schema in `setUp()` via `Schema::create()`
- File uploads: `Storage::fake('public')`, `UploadedFile::fake()`

### Test Coverage
30+ test files (28 Feature + 12 Unit) covering:
- Booking CRUD and financial sync
- Booking cancellation workflow
- Dashboard query optimization
- List table Livewire components (BookingIndexTable, PassengerIndexTable, etc.)
- Query repositories (BookingIndexQuery, VisaAgentReportQuery, TicketAgentReportQuery, etc.)
- Report pages (ProfitLoss, Fingerprint, Visa, Ticket Agent, Due, BranchWise, Payment Receiving)
- Seed data validation
- File upload validation
- Service unit tests (BookingService, CostTrackingService, CurrencyRateService)
- Middleware/trait tests (HandlesBranchAccess, FiltersBookingStatus)
- AppServiceProvider tests (@currency directive, observer registration)

---

## Deployment & CI/CD

### CI
- GitHub Actions workflow: `.github/workflows/build-push.yml`
- Three parallel jobs: `test-php` (PHP 8.4 + MySQL 8.0), `test-js` (Node 22 + npm), `build` (Docker Buildx → ghcr.io)
- On push to `main` or PR → tests run → image built and pushed to `ghcr.io/mostafiz-8bits/umrah-app`

### CD
- **Watchtower** auto-deploys: the prod app container is labeled
  `com.centurylinklabs.watchtower=true` — it auto-pulls and restarts within ~5 minutes of a new image push
- **Manual deploy:** `./deploy-prod.sh` (pulls image, migrates, restarts)
- Production compose: `docker-compose.prod.yml` (app + MySQL 8.0 + Redis 7)

### Docker
- Multi-stage build: Node 22 (assets) → PHP 8.4-fpm-alpine (runtime)
- Local: `docker compose up -d --build` (app at `http://localhost:8080`, MySQL at `127.0.0.1:3306`)
- Prod: `docker compose -f docker-compose.prod.yml up -d`
- Entypoint (`docker/entrypoint.sh`): fixes permissions, caches config/routes/views, runs migrations (unless `MIGRATE=false`)

---

## Navigation

Previous: [Getting Started](01-getting-started.md) ·
Next: [Development Environment](03-dev-environment.md) ·
Full index: [README](README.md)
