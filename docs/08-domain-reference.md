# Domain Reference

> Part of the [Development Handbook](README.md) · **Mode:** Reference

This is the definitive reference for the project's domain entities, roles,
enums, services, and UI components.

## Role System

Access control is enforced via `CheckRole` middleware (`app/Http/Middleware/CheckRole.php`).
Routes are protected by passing allowed roles as comma-separated values:

```php
Route::resource('bookings', BookingController::class)
    ->middleware('role:Super Admin,Co Admin');
```

### All 12 Roles

| # | Role | Scope |
|---|------|-------|
| 1 | Super Admin | Full access — all modules, all branches |
| 2 | Co Admin | All modules, all branches |
| 3 | Auditor | Read-only access to reports, invoices, payments |
| 4 | Ticket Admin | Manage fares, routes, airlines, ticket agents |
| 5 | Visa Admin | Manage visa agents, visa submissions, visa rates |
| 6 | Ticket Staff | Issue tickets, manage ticket agents' customers |
| 7 | Visa Staff | Process visa submissions for assigned branches |
| 8 | Fingerprint Admin | Configure fingerprint charges, manage fingerprint staff |
| 9 | Fingerprint Staff | Process passenger fingerprints |
| 10 | Branch Manager | Manage bookings and finances for own branch |
| 11 | Branch Staff | Create bookings, issue documents for own branch |
| 12 | Delivery Staff | Receive passport/document handoffs |

Roles are seeded by `RoleSeeder`. Users get roles via the `user_roles` pivot table
(managed by `UserRole` model).

### Checking Roles in Code

```php
// In middleware: 'role:Super Admin,Co Admin'
// In code:
$user = auth()->user();
if ($user->hasRole('Super Admin')) { ... }

// Check multiple roles:
$user->roles()->whereIn('name', ['Super Admin', 'Co Admin'])->exists();
```

## Key Models

### Core Entities

| Model | Table | Key Fields | Relationships |
|-------|-------|-----------|---------------|
| User | users | name, email, password, branch_id, is_active | roles (hasMany), branch (belongsTo) |
| Branch | branches | name, address, phone, location, fingerprint_operation | users, bookings |
| Booking | bookings | customer_id, package_id, branch_id, pax_qty, total_value, discount_type, discount_value, is_cancelled | customer, passengers, invoice, payments |
| Passenger | passengers | booking_id, name, passport, dob, gender, ticket_fare_id, ticket_status | booking, documents |
| Customer | customers | name, mobile, iqama_type, address | bookings |

### Financial Entities

| Model | Table | Key Fields | Notes |
|-------|-------|-----------|-------|
| Invoice | invoices | invitable_type, invitable_id, total, balance, status | Polymorphic |
| Payment | payments | invoice_id, amount, type (credit/debit), method, reference | Transaction types |
| Voucher | vouchers | payment_id, amount, type, transaction_type_id | Linked to payments |
| CurrencyRate | currency_rates | date, rate_sar_to_bdt | Used for BDT conversion |
| Bank | banks | name, account_number, currency, branch_id | Sender/receiver in payments |

### Reference Data

| Model | Table | Purpose |
|-------|-------|---------|
| Package | packages | Umrah packages (routes, pricing) |
| Route | routes | Flight routes (origin, destination, direction) |
| TicketFare | ticket_fares | Fare rates for routes/classes |
| Airline | airlines | Airlines |
| VisaAgent | visa_agents | Visa processing agents |
| TicketAgent | ticket_agents | Ticket-selling agents |
| District | districts | Geographic districts |
| FlightDateGap | flight_date_gaps | Date gap configuration |

## Enums (18)

All in `app/Enums/`. These are PHP backed enums used in model casts.

### Booking / Invoice Related
| Enum | Description |
|------|-------------|
| `DiscountType` | fixed_amount, percentage |
| `InvoiceStatus` | pending, paid, cancelled |
| `TransactionType` | (credit, debit, etc.) |
| `OwnerType` | (booking, passenger, etc.) |

### Passenger / Visa Related
| Enum | Description |
|------|-------------|
| `Gender` | male, female, other |
| `IqamaType` | (iqama, passport, etc.) |
| `PassengerType` | adult, child, infant |
| `TicketType` | (economy, business, etc.) |
| `TicketStatus` | confirmed, held, rescheduled, cancelled |
| `VisaStatus` | pending, approved, rejected |
| `RescheduleReason` | (missed_flight, schedule_change, etc.) |

### Reporting Related
| Enum | Description |
|------|-------------|
| `Location` | (origin, destination, etc.) |
| `FingerprintLocation` | office, branch |
| `FingerprintStatus` | (pending, done, etc.) |
| `ServiceRequired` | yes, no |
| `RouteDirection` | outbound, inbound |
| `RouteType` | (direct, connecting, etc.) |
| `SegmentDirection` | departure, arrival, transit |
| `TravelDirection` | outbound, inbound |
| `FlightType` | outbound, inbound, transit |
| `PaymentMethod` | cash, bank_transfer, card |

### Using Enums

```php
// Check:
if ($booking->discount_type === DiscountType::FIXED_AMOUNT) { ... }

// Create:
$booking->discount_type = DiscountType::PERCENTAGE;

// In Blade:
@if($booking->discount_type->value === 'fixed_amount')
```

## Services (7)

Business logic lives in service classes with constructor DI.

| Service | Purpose |
|---------|---------|
| `BookingService` | Booking creation, passenger type calculation, discount handling |
| `InvoiceService` | Invoice generation, BDT currency conversion |
| `PaymentService` | Payment processing, receiver bank handling |
| `VoucherService` | Voucher creation, transaction type linking |
| `CancellationService` | Booking and visa cancellation logic |
| `CostTrackingService` | Cost and labor tracking for fingerprints |
| `CurrencyRateService` | Manage exchange rates (SAR ↔ BDT) |

## Blade Components (13)

Reusable UI components in `resources/views/components/`.

| Component | Usage |
|-----------|-------|
| `action-button` | Standard action buttons (edit, delete, view) |
| `data-table` | Paginated, sortable data table |
| `empty-state` | Empty state illustration + message |
| `error-state` | Error display for failed loads |
| `loading-state` | Spinner + loading message |
| `modal` | Modal dialog with Alpine.js show/hide |
| `page-header` | Page title + action buttons |
| `search-input` | Search/filter input field |
| `skeleton` | Skeleton loaders for content |
| `stat-card` | Dashboard summary stat card |
| `status-badge` | Status indicator badge |
| `tab-button` | Tab navigation buttons |
| `toast` | Toast notification component |

## Custom Blade Directives

### `@currency`

Renders multi-currency display (SAR primary, BDT secondary):

```blade
@currency($booking->total_value, 2, $currencyRate?->rate, $booking->bdt_amount)
@endcurrency
```

Renders as: `<span class="currency-display" data-sar="1234.56" data-bdt="14814.72">...`

### Registered in `AppServiceProvider::boot()`

To add a new directive:
```php
Blade::directive('myDirective', function ($expression) {
    return "<?php echo '...' . {$expression} . '...' ?>";
});
```

## Observers (5)

Audit logging for key model changes:

| Model | Observer | What it logs |
|-------|----------|-------------|
| Booking | BookingObserver | Updates — dirty field tracking |
| Passenger | PassengerObserver | Changes to passenger records |
| FingerprintDetail | FingerprintDetailObserver | Fingerprint action logs |
| VisaSubmission | VisaSubmissionObserver | Visa status changes |
| IssuedTicket | IssuedTicketObserver | Issued ticket logs |

---

## Navigation

Previous: [CI/CD](07-ci-cd.md) ·
Full index: [README](README.md)
