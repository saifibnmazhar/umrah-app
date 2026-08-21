# Architecture

> Part of the [Development Handbook](README.md) · **Mode:** Explanation

This document describes how Umrah App is structured, what each layer does,
and how requests flow through the system.

## Tech Stack

| Layer              | Technology                                  |
|--------------------|---------------------------------------------|
| Backend            | Laravel 12, PHP 8.4                         |
| Database (prod)    | MySQL 8.0                                   |
| Cache (prod)       | Redis 7                                     |
| Database (test)    | SQLite (in-memory)                          |
| Frontend           | Blade templates, Vite 7, Tailwind CSS v4, Alpine.js |
| Testing            | PHPUnit 11                                  |
| Container          | Docker (multi-stage: Node 22 + PHP 8.4-fpm-alpine) |
| CI/CD              | GitHub Actions → ghcr.io + Watchtower       |
| PDF Generation     | setasign/FPDF + setasign/FPDI               |

## Domain Overview

Umrah App is a management system for a **umrah travel agency**. The core
workflow is:

```
Branch → Booking → Passenger(s) → [Visa | Ticket | Fingerprint] → Invoice → Payment → Voucher
```

- **Branches** represent physical office locations. Each user belongs to a branch.
- **Bookings** are the central entity — they link passengers to a package/route.
- **Passengers** are the travelers. They may have visas, tickets, and fingerprints.
- **Invoices/Payments/Vouchers** track the financial lifecycle (transaction types
  control debit/credit direction).
- **Reports** aggregate data across branches, users, date ranges.

## Directory Structure

```
app/
├── Console/              # Artisan commands (data migration, backfill, sync)
├── Concerns/             # Reusable traits (HandlesBranchAccess, FiltersBookingStatus)
├── Enums/                # 18 PHP backed enums (DiscountType, Gender, etc.)
├── Exceptions/           # Custom exception handlers
├── Http/
│   ├── Controllers/      # 30+ resource + report controllers
│   ├── Middleware/       # CheckRole (RBAC), CheckActive (login state)
│   ├── Requests/         # Form Request validation classes
│   └── ...
├── Livewire/             # Livewire components (dashboard, list tables)
├── Models/               # ~50 Eloquent models
├── Observers/            # 5 model observers (audit logging)
├── Providers/            # AppServiceProvider (observer registration, Blade directives)
├── Queries/              # Query repository classes (BookingIndexQuery, etc.)
├── Services/             # 7 service classes (business logic)
├── Support/              # DateFormatter, DiagnosticLogger
└── Traits/               # ConvertsDocumentsToPdf (FPDF/FPDI)

database/
├── migrations/           # ~108 migration files
├── factories/            # UserFactory (extend for new models)
└── seeders/              # 5 seeders (roles, users, transaction types, etc.)

resources/
├── views/                # ~40 Blade view directories
│   ├── components/         # 13 reusable Blade components
│   ├── layouts/           # app.blade.php (main layout)
│   ├── partials/          # nav.blade.php, form modals
│   └── ...                # domain-specific views (bookings, visas, reports, etc.)
├── css/app.css           # Tailwind CSS entry
└── js/                   # app.js, booking.js, bootstrap.js, utils/

routes/
└── web.php               # All routes (~60) — no API routes

config/                   # Standard Laravel config files
```

## Key Patterns

### Model-View-Controller (MVC)

Standard Laravel MVC:
- **Routes** → `routes/web.php` maps URLs to controllers
- **Controllers** → `app/Http/Controllers/` handle requests, call services, return views
- **Models** → `app/Models/` are Eloquent ORM entities
- **Views** → `resources/views/` are Blade templates

### Service Layer

Business logic lives in **service classes** (`app/Services/`), injected into
controllers via constructor dependency injection:

```php
// app/Http/Controllers/BookingController.php
public function __construct(
    private BookingService $bookingService,
) {}
```

Services:
- `BookingService` — booking creation, passenger type calculation, discounts
- `InvoiceService` — invoice generation, financial calculations
- `PaymentService` — payment processing, receiver bank handling
- `VoucherService` — voucher creation, transaction type handling
- `CancellationService` — booking/visa cancellation logic
- `CostTrackingService` — cost/labor tracking
- `CurrencyRateService` — currency rate management
- `DateFormatter` — per-user timezone display (in `app/Support/`)

### Query Repositories

Complex or repeated database queries are encapsulated in **query repository
classes** (`app/Queries/`). Controllers (including Livewire data endpoints)
delegate to these classes instead of building inline query-builder chains.

| Query class | For |
|-------------|-----|
| `BookingIndexQuery` | Booking index page + Livewire BookingIndexTable data endpoint |
| `PassengerIndexQuery` | Passenger index Livewire table data endpoint |
| `FingerprintReportQuery` | Fingerprint report filtering + eager loading |
| `TicketAgentReportQuery` | Ticket agent report data (payable, paid, refunds, reissues) |
| `VisaAgentReportQuery` | Visa agent report data (submitted, issued, cancelled, payments) |

Constructor takes a `Request`, applies filters (search, date ranges, status,
branch scoping), and exposes `getQuery()`, `paginate()`, and `getSummary()`.

### Concerns (Reusable Traits)

Shared cross-cutting logic lives in `app/Concerns/`, used by multiple controllers:

| Trait | Purpose |
|-------|---------|
| `HandlesBranchAccess` | Role checks (isAdmin, isBranchScoped), branch-scoped access enforcement (ensureBranchAccess, ensurePassengerBranchAccess), edit window (12h), payment/agent permission checks |
| `FiltersBookingStatus` | Reusable `bookingStatus` / `bookingStatusViaBooking` query scopes for active/cancellation_processing/cancelled states |

### Livewire List Tables (BaseListTable)

Reactive list/table views use Livewire. `app/Livewire/BaseListTable.php` is an
abstract base class providing search reset and a shared `applySearch()` helper.
Concrete list tables extend it:

| Component | For |
|-----------|-----|
| `BookingIndexTable` | Booking index Livewire table |
| `PassengerIndexTable` | Passenger index Livewire table |
| `PackageListTable` | Package list |
| `BranchListTable` | Branch list |
| `PaymentListTable` | Payment list |
| `UserListTable` | User list |
| `TicketFareTable` | Ticket fare list |
| `TicketFareIndexTable` | Ticket fare index |
| `FingerprintChargeTable` | Fingerprint charge list |
| `FingerprintChargeList` | Fingerprint charge list (report) |

### Model Observers (Audit Logging)

Five observers are registered in `AppServiceProvider::boot()`:

| Model            | Observer              | Purpose |
|------------------|-----------------------|---------|
| Booking          | BookingObserver       | Logs booking updates (dirty fields) |
| Passenger        | PassengerObserver     | Logs passenger changes |
| FingerprintDetail| FingerprintDetailObserver | Tracks fingerprint actions |
| VisaSubmission   | VisaSubmissionObserver | Logs visa status changes |
| IssuedTicket     | IssuedTicketObserver   | Logs issued ticket actions |

To add a new observer: create the class in `app/Observers/`, register it in
`AppServiceProvider::boot()`:
```php
MyModel::observe(MyObserver::class);
```

### Enums

The project uses **PHP 8.1+ backed enums** for domain values. All enums are in
`app/Enums/`. See [Domain Reference](08-domain-reference.md) for the full list.

Usage in models:
```php
protected $casts = [
    'discount_type' => DiscountType::class,
    'is_cancelled' => 'boolean',
];
```

Usage in views:
```blade
@if($booking->discount_type === \App\Enums\DiscountType::FIXED_AMOUNT)
```

### Form Requests

Validation is handled by **Form Request classes** (not inline validation):

| Request              | For |
|----------------------|-----|
| StoreBookingRequest  | Creating bookings |
| UpdateBookingRequest | Editing bookings |
| StorePassengerRequest| Creating passengers |

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
secondary):

```blade
@currency($booking->total_value, 2, $currencyRate?->rate, $booking->bdt_amount)
@endcurrency
```

### Role-Based Access Control

12 roles enforced via `CheckRole` middleware. Routes are protected like:

```php
Route::middleware('auth')->group(function () {
    Route::resource('bookings', BookingController::class)
        ->middleware('role:Super Admin,Co Admin');
});
```

See [Domain Reference](08-domain-reference.md) for the full role matrix.

## Request Lifecycle

1. **Incoming HTTP request** → `routes/web.php`
2. **Middleware** (`auth`, `role:...`, `active`) applied
3. **Controller method** receives request, calls Form Request for validation
4. **Service layer** (if needed) handles business logic
5. **Query repository** (`app/Queries/`) or **model** interacts with database
6. **Livewire** components render reactive tables/views (where applicable)
7. **Blade view** rendered and returned to user
8. **Observers** fire on model events (creating, updating, etc.)

---

## Navigation

Previous: [Getting Started](01-getting-started.md) ·
Next: [Development Environment](03-dev-environment.md) ·
Full index: [README](README.md)
