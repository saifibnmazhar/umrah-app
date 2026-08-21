# Coding Conventions

> Part of the [Development Handbook](README.md) · **Mode:** Reference

This document defines the coding standards and conventions for Umrah App.
Following these consistently keeps the codebase maintainable and reviewable.

## Code Style

### PSR-12 + Laravel Pint

All PHP code follows **PSR-12** with Laravel-specific conventions, enforced by
**Laravel Pint** (`laravel/pint` in `require-dev`).

**Format before every commit:**
```bash
vendor/bin/pint
```

If Pint is not installed:
```bash
composer install --no-interaction --prefer-dist
```

Pint uses default rules (PSR-12 + Laravel preset). No custom `pint.json` config
file exists — defaults are sufficient.

### .editorconfig

The project enforces editor settings via `.editorconfig`:

| Setting | Value |
|---------|-------|
| Charset | UTF-8 |
| Line endings | LF (Unix) |
| Indent size | 4 spaces |
| Trim trailing whitespace | Yes (except Markdown) |
| Insert final newline | Yes |

**YAML files** use 2-space indentation.

### Frontend (Tailwind CSS v4 + Alpine.js)

- Tailwind CSS v4 via `@tailwindcss/vite` plugin
- Custom CSS in `resources/css/app.css` with `@theme` blocks
- Alpine.js for interactivity (`x-data`, `x-show`, `x-model`, `@click`, `:class`)

## Naming Conventions

### Models
- **PascalCase**, singular (e.g., `Booking`, `Passenger`, `VisaAgent`)
- File: `app/Models/Booking.php`
- Table: `bookings` (auto-derived as plural snake_case)

### Migrations
- `YYYY_MM_DD_HHMMSS_descriptive_name.php`
- Always specify a clear, descriptive name
- Example: `2026_07_14_000003_add_is_cancelled_to_bookings_table.php`

### Controllers
- **PascalCase** + `Controller` suffix (e.g., `BookingController`)
- File: `app/Http/Controllers/BookingController.php`
- Resource controllers follow RESTful naming: `index`, `create`, `store`, `show`, `edit`, `update`, `destroy`
- Report controllers end with `Report` (e.g., `ProfitLossReportController`)

### Routes
- **snake_case** with dot notation (e.g., `booking.index`, `visa.edit`)
- Use `Route::resource()` for CRUD:
  ```php
  Route::resource('bookings', BookingController::class);
  ```
- Custom routes use explicit names:
  ```php
  Route::patch('/bookings/{booking}/cancel', [BookingController::class, 'cancel'])
       ->name('bookings.cancel');
  ```

### Views
- Dot notation matching route structure (e.g., `bookings.edit`, `reports.profit-loss`)
- Directory structure mirrors domain: `bookings/`, `visas/`, `fingerprints/`, `reports/`, etc.
- Components in `resources/views/components/`

### Database Tables
- **plural snake_case** (e.g., `booking_conditions`, `visa_agent_costs`, `flight_date_gaps`)

### Enums
- **PascalCase**, short, descriptive (e.g., `DiscountType`, `InvoiceStatus`, `PaymentMethod`)
- Backed by string or int
- Stored in `app/Enums/`

## Blade Patterns

### Layout and Partials

```blade
@extends('layouts.app')
@section('content')
    @include('partials.nav')
    {{-- Your content --}}
@endsection
```

### Components

Use reusable Blade components for consistent UI:

```blade
<x-data-table :data="$bookings" :headers="$headers">
    @foreach($bookings as $booking)
        <tr>
            <x-status-badge :status="$booking->status" />
            <x-action-button :href="route('bookings.edit', $booking)" />
        </tr>
    @endforeach
</x-data-table>
```

### Vue-less Interactivity

All interactive features use **Alpine.js** (no Vue/React):

```blade
<div x-data="{ show: false }">
    <button @click="show = true">Open Modal</button>
    <div x-show="show" @click.away="show = false">
        Modal content
    </div>
</div>
```

### UI References

The `ui-references/` folder contains original HTML/JS design files. **Do not modify
these files.** Use them as visual references when building new Blade views.

## Key Patterns

### Enum Usage

```php
// In models:
protected $casts = [
    'discount_type' => DiscountType::class,
    'fingerprint_location' => FingerprintLocation::class,
];

// In controllers:
if ($booking->discount_type === DiscountType::FIXED_AMOUNT) { ... }

// In Blade:
@if($booking->discount_type->value === 'fixed_amount')
```

### Service Pattern

### Service Pattern

Business logic lives in service classes, injected via constructor. The `app/Support/`
directory also holds non-service helpers like `DateFormatter` (per-user timezone
display) and `DiagnosticLogger` (file upload diagnostics):

```php
class BookingController extends Controller
{
    public function __construct(
        private BookingService $bookingService,
    ) {}

    public function store(StoreBookingRequest $request)
    {
        $validated = $request->validated();
        $booking = $this->bookingService->create($validated);
        return redirect()->route('bookings.show', $booking);
    }
}
```

### Concerns (Reusable Traits)

Shared cross-cutting logic lives in `app/Concerns/`, used by multiple controllers:

- **`HandlesBranchAccess`** — role checks (`isAdmin`, `isBranchScoped`), branch-scoped
  access enforcement (`ensureBranchAccess`, `ensurePassengerBranchAccess`,
  `ensureCancellationAccess`), edit window (12h), and permission checks
  (`canEditVisa`, `canEditFingerprint`, `canEditTickets`, `canFilterByAgent`).
- **`FiltersBookingStatus`** — reusable `bookingStatus` / `bookingStatusViaBooking`
  query scopes for active / cancellation_processing / cancelled states.

### Query Repository Pattern

Complex or repeated database queries are encapsulated in **query repository classes**
(`app/Queries/`). Controllers delegate to these classes instead of building inline
query-builder chains. This eliminates duplication between initial page renders and
Livewire AJAX data endpoints:

| Query class | For |
|-------------|-----|
| `BookingIndexQuery` | Booking index page + Livewire BookingIndexTable data endpoint |
| `PassengerIndexQuery` | Passenger index Livewire table data endpoint |
| `FingerprintReportQuery` | Fingerprint report filtering + eager loading |
| `TicketAgentReportQuery` | Ticket agent report data (payable, paid, refunds, reissues) |
| `VisaAgentReportQuery` | Visa agent report data (submitted, issued, cancelled, payments) |

Constructor takes a `Request`, applies filters, and exposes `getQuery()`,
`paginate()`, and `getSummary()` methods.

### Form Request Validation

Always use Form Request classes (not inline validation):

| Class | Purpose |
|-------|---------|
| `StoreBookingRequest` | Create booking |
| `UpdateBookingRequest` | Edit booking |
| `StorePassengerRequest` | Create passenger |

### Observer Pattern

Observers are registered in `AppServiceProvider::boot()`:

```php
Booking::observe(BookingObserver::class);
Passenger::observe(PassengerObserver::class);
```

To add a new observer, create the class and register it:
```php
// app/Observers/MyObserver.php
class MyObserver
{
    public function created(MyModel $model): void { ... }
    public function updated(MyModel $model): void { ... }
    public function deleted(MyModel $model): void { ... }
}
```

### Custom Blade Directives

The `@currency` directive handles multi-currency display (SAR + BDT):

```blade
@currency($amount, 2, $currencyRate?->rate, $bdtAmount)
@endcurrency
```

---

## Navigation

Previous: [Development Environment](03-dev-environment.md) ·
Next: [Testing](05-testing.md) ·
Full index: [README](README.md)
