# Server-Side Pagination Plan — Booking / Passenger / Fingerprint Admin / Staff

## 1. Context

Production has ~1500 passengers and growing. Booking index, Fingerprint Admin
(`/fingerprints/admin`), and Fingerprint Staff (`/fingerprints/staff`) load slowly.

Current behavior: backend hydrates the whole dataset (or large dropdown lists),
then the frontend shows a small slice. Every page view re-scans all rows.

Reference pattern already in repo (follow it):
- `VisaReportController@data` + `fetch(/api/reports/visa?...)` in `reports/visa.blade.php:402`
- `FingerprintReportController@data` + `FingerprintReportQuery`
- `ProfitLossReportController@summary` / `@data`

## 2. Root Causes Found

### 2.1 Booking / Passenger index (`BookingController@index`, `bookings/index.blade.php`)

1. `paginate(15)` exists but is defeated:
   - `BookingController.php:591` `(clone $passengers)->count()`
   - `:595` `(clone $passengers)->pluck('booking_id')`
   - `:597-623` `Booking::whereIn($bookingIds)->get()` + PHP loop for totals
   - Result: full 1500-row scan on every page view.
2. Blade runs DB queries (`bookings/index.blade.php:7-111`):
   - `Package::get()`, `Airline::with(travelClasses)->get()`, `TravelClass::all()`
   - `TicketFare::with(8 relations)->get()` + inactive-fares query + large mapped `$ticketFaresList` JSON inlined in HTML.
3. Over-eager loading (`BookingController.php:626-715`): ~80 relations per passenger
   (`allIssuedTickets.*.route.fromCity/toCity/multiSegments`,
   `latestReIssuedTicket`, `latestRefundedTicket`, etc.).
4. No indexes on `passengers` (only FKs). All search is leading-wildcard
   `LIKE "%...%"` on `first_name, last_name, passport_no, mobile_no, invoice_id,
   ticket_number, pnr` plus stacked `whereHas()` subqueries.
5. Per-row PHP work: `CostTrackingService::getPassengerCosts()` per booking,
   `CurrencyRateService::getRateForDate()` per passenger, accessor lazy loads
   (`route_display`, `baggage_display`).

### 2.2 Fingerprint Admin (`FingerprintController@adminIndex:21`)

Already `Fingerprint::with([...])->paginate(10)` + `fetch(/api/fingerprints/admin)`
in `admin.blade.php:354`. Slow because:

1. `paginate(10)` is on fingerprints, then `->flatten(1)` over
   `$booking->passengers` → 10 bookings become 10-40 rows, unpredictable page size.
2. Missing eager loads accessed in map:
   - `$detail?->approvedLog` (`:156`) not in `with()` (`:23-32`)
   - `$passenger->status` (`:144`) not eager loaded
3. Heavy search: `CONCAT(first_name,' ',last_name) LIKE` + leading `%LIKE%`.

### 2.3 Fingerprint Staff (`FingerprintController@staffIndex:175`)

Same as Admin plus hard N+1s:

1. Per-passenger query in map (`:265-267`):
   ```php
   $detail = $fingerprint->fingerprintDetails()->where('passenger_id', $passenger->id)->first();
   ```
   Should use the already-loaded collection.
2. `computePartiallyApprovedStatus()` (`:322-328`) lazy-loads
   `$detail->fingerprint->fingerprintDetails` per row.
3. `approvedLog` and `passenger->status` same missing eager loads as Admin.

## 3. Goal Architecture

```
GET /bookings                 -> shell view only (filters, empty table)
GET /api/bookings/passengers?page=1&per_page=15&search=... -> 15 rows JSON + SQL summary + pagination meta
GET /fingerprints/admin       -> shell view only
GET /api/fingerprints/admin?page=1&per_page=25&search=...  -> 25 passenger-grain rows JSON
GET /fingerprints/staff       -> shell view only
GET /api/fingerprints/staff?page=1&per_page=25&search=...  -> 25 passenger-grain rows JSON
```

Rules:

- No `::get()` over full tables in controller for the main data query.
- Totals via SQL aggregates, never via hydration + PHP loop.
- Dropdown / fare lists stay loaded in PHP/Blade (small reference tables, <100 rows).
- Paginate at passenger grain (`FingerprintDetail`), not booking grain.

## 4. Search & Filter Behavior

### Filter Dropdowns — No Change

All dropdown data continues to load fully in the controller/Blade. These are
small reference tables (<100 rows). The performance problem is the main query
(1500+ passengers), not dropdown data.

| Dropdown | Source | Loaded in |
|----------|--------|-----------|
| Branches | `Branch::get()` | Controller → Blade |
| Packages | `Package::get()` | Controller → Blade |
| Airlines + Travel Classes | `Airline::with('travelClasses')->get()` | Controller → Blade |
| Ticket Fares | `TicketFare::with(8 relations)->get()` | Controller → Blade |
| Visa Agents | `VisaAgent::get()` | Controller → Blade |
| Ticket Agents | `TicketAgent::get()` | Controller → Blade |
| Passenger Statuses | `PassengerStatus::all()` | Controller → Blade |
| Status enums | Hardcoded in Blade | Static |

### Booking/Passenger Index — Switch to AJAX

Current: full page reload on every filter/search change.
After: AJAX `fetch` to `/api/bookings/passengers`, no page reload.

- Search debounce: 1500ms → 400ms
- Any search/filter change resets to `page=1`
- Dropdown data stays embedded in Blade HTML (no change)
- Fingerprint pages already use AJAX — no behavioral change there

### Search Fields

**Booking tab** (`/api/bookings/passengers?search=term`) — `%term%` on:
`invoice_id`, `customer.mobile_no`, `passengers.passport_no` (OR grouped)

**Passenger tab** (`/api/bookings/passengers?search=term`) — `%term%` on:
`first_name`, `last_name`, `mobile_no`, `passport_no`,
`booking.invoice_id`, `issued_tickets.ticket_number`, `issued_tickets.pnr`
(OR grouped)

**Fingerprint Admin/Staff** — `%term%` on:
`booking.invoice_id`, `booking.customer.name`,
`fingerprint_details.passenger.first_name`,
`fingerprint_details.passenger.last_name` (OR grouped)

### CONCAT Cleanup

Replace `orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ...)` with:
`->orWhere('first_name', 'like', "%{$search}%")
  ->orWhere('last_name', 'like', "%{$search}%")`

Same user experience, index-friendly, no raw SQL.

### Response Shape (consistent across all APIs)

```json
{
  "data": [ /* 15 or 25 row objects */ ],
  "summary": { "total": 1523 },
  "pagination": {
    "current_page": 1,
    "last_page": 61,
    "per_page": 15,
    "total": 1523
  }
}
```

## 5. Implementation Steps (TDD-first per AGENTS.md)

### Step 0 — Tests first

Create:

- `tests/Feature/BookingIndexPaginationTest.php`
- `tests/Feature/FingerprintPaginationTest.php`

Assert:

- `GET /api/bookings/passengers` returns 15 items, `pagination.total` matches full count, `summary.total` matches, no full-collection keys.
- `GET /api/fingerprints/admin` and `/staff` return passenger-grain rows, 1 query per relation (use `DB::enableQueryLog` / `assertDatabaseCount` style already used in repo).
- Shell `GET /bookings`, `/fingerprints/admin`, `/fingerprints/staff` return 200.

Run:

```bash
php artisan test --filter=BookingIndexPaginationTest
php artisan test --filter=FingerprintPaginationTest
```

### Step 1 — Indexes (migration, smallest risk, biggest read win)

Migration: `database/migrations/2026_09_10_000001_add_indexes_for_server_side_pagination.php`

Note: FK columns (`booking_id`, `passenger_status_id`, etc.) already have implicit
indexes from InnoDB FK constraints. `invoice_id` on `bookings` already has a
`unique()` constraint. `[fingerprint_id, passenger_id]` on `fingerprint_details`
already has a `unique()` constraint. Only add indexes that don't already exist.

```php
Schema::table('passengers', function ($t) {
    $t->index('passport_no');         // search: LIKE '%term%'
    $t->index('mobile_no');           // search: LIKE '%term%'
    $t->index('flight_date_from');    // filter: date range
});
Schema::table('bookings', function ($t) {
    $t->index('booking_branch_id');     // filter: branch dropdown
    $t->index('fingerprint_branch_id'); // filter: branch dropdown
});
Schema::table('fingerprints', function ($t) {
    $t->index('assigned_staff_id'); // filter: staff assignment
    $t->index('deadline');          // filter: deadline range
});
Schema::table('fingerprint_details', function ($t) {
    $t->index('status'); // filter: fingerprint status
});
Schema::table('issued_tickets', function ($t) {
    $t->index(['passenger_id', 'status']); // composite: ticket status filter
});
```

### Step 2 — Extract `BookingPassengerQuery`

New `app/Queries/BookingPassengerQuery.php`. Move all `when()->whereHas()`
filters from `BookingController@index:283-589` here, mirroring
`FingerprintReportQuery`:

```php
class BookingPassengerQuery
{
    protected $query;

    public function __construct(Request $request)
    {
        $this->query = Passenger::query()->orderBy('created_at', 'desc');
        $this->applyFilters($request);
    }

    public function getQuery()
    {
        return $this->query;
    }

    public function getBaseQueryForAggregates()
    {
        return clone $this->query;
    }
}
```

### Step 3 — Booking API + slim `index()`

`routes/web.php`:

```php
Route::get('/api/bookings/passengers', [BookingController::class, 'passengerData'])
    ->name('api.bookings.passengers');
```

`BookingController.php`:

```php
public function index(Request $request)
{
    return view('bookings.index', [
        'packages' => Package::get(),
        'airlines' => Airline::with('travelClasses')->get(),
        'travelClasses' => TravelClass::all(),
        'ticketFares' => TicketFare::with([...])->get(),
        // ... all existing filter dropdown data stays here
        // NO Passenger query, NO 80-relation with(), NO count/pluck/whereId loop
    ]);
}

public function passengerData(Request $request)
{
    $query = (new BookingPassengerQuery($request))->getQuery();
    $page = $query->with([
        'booking:id,invoice_id,customer_id',
        'booking.customer:id,name',
        'ticketFare:id,airline_id',
        'status:id,name',
        'visaSubmission:id,passenger_id,status',
    ])->paginate(15)->withQueryString();

    return response()->json([
        'data' => $page->items(),
        'summary' => $this->passengerSummary($request),
        'pagination' => [
            'current_page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
            'per_page' => $page->perPage(),
            'total' => $page->total(),
        ],
    ]);
}

protected function passengerSummary(Request $request): array
{
    $base = (new BookingPassengerQuery($request))->getBaseQueryForAggregates();
    $base->getQuery()->orders = [];
    $base->getQuery()->withs = [];
    $row = (clone $base)->selectRaw('COUNT(*) as total')->first();

    return ['total' => (int) $row->total];
}
```

Delete from old `index()`: `(clone $passengers)->count()`, `pluck(booking_id)`,
`whereIn()->get()` loop, 80-relation `with()`. Keep all dropdown queries.

Blade (`bookings/index.blade.php`): keep all PHP blocks for dropdown data
(`Package::get()`, `Airline::get()`, `TravelClass::all()`, `TicketFare::get()`).
Replace page-reload filter logic with Alpine fetch:

```js
// On search input (@input.debounce.400ms) or filter change (@change):
const params = new URLSearchParams({ page, search, visa_status, ... });
const res = await fetch(`/api/bookings/passengers?${params}`);
const { data, summary, pagination } = await res.json();
// Re-render table rows, update pagination controls
```

- Search debounce: 1500ms → 400ms
- Any search/filter change resets to `page=1`

### Step 4 — Fingerprint Admin/Staff fix

Keep URLs and response shape. Change grain to `FingerprintDetail`:

```php
$query = FingerprintDetail::with([
    'fingerprint.booking.customer',
    'fingerprint.booking.district',
    'fingerprint.booking.currencyRate',
    'fingerprint.booking.cancelledBooking',
    'fingerprint.assignedStaff',
    'passenger.status',
    'rescheduledFingerprints',
    'approvedLog',
])->orderBy('created_at', 'desc');
```

Apply existing filters via `whereHas('fingerprint...')` / `whereHas('passenger...')`
instead of `whereHas('booking.passengers...')` + `whereHas('fingerprintDetails...')`.

In map, replace per-row query:

```php
$detail = $fingerprint->fingerprintDetails->firstWhere('passenger_id', $passenger->id);
```

with direct `$detail` model (already paginated). Compute
`Partially Approved` from the eager-loaded sibling collection, no reload:

```php
$allApproved = $siblings->every(fn ($d) => $d->status->value === 'approved');
```

Replace `CONCAT(...) LIKE` with:

```php
$q->where('first_name', 'like', "%{$search}%")
  ->orWhere('last_name', 'like', "%{$search}%");
```

Set `per_page=25` to match `VisaReportController::PER_PAGE` and
`FingerprintReportController::PER_PAGE`. Frontend `fetch` in
`fingerprints/admin.blade.php:354` and `staff.blade.php` only changes
`per_page` handling; pagination meta stays `{current_page, last_page, per_page, total}`.

### Step 5 — Verify

```bash
php artisan test --filter=BookingIndexPaginationTest
php artisan test --filter=FingerprintPaginationTest
php artisan test
vendor/bin/pint
npm run build
docker compose config --quiet
docker compose -f docker-compose.prod.yml config --quiet
```

Manual: open `/bookings`, `/fingerprints/admin`, `/fingerprints/staff` with
network tab — first HTML small, each filter/page triggers one
`/api/...?page=` call returning 15/25 rows.

## 6. Rollout Order

1. Indexes migration.
2. Fingerprint eager-load fix (smallest diff, no API shape change).
3. Fingerprint detail-grain pagination.
4. `BookingPassengerQuery` + `/api/bookings/passengers` + slim Blade.

## 7. Risks / Notes

- Booking ticket-status filters use nested `whereHas('allIssuedTickets')`;
  keep logic identical when moving to query object, only change hydration size.
- `CurrencyRateService::getRateForDate()` is in-request cached only; for API
  rows prefer `$booking->currencyRate->rate ?? $firstRate` like
  `FingerprintController:104,122` to avoid per-row date lookups.
- Do not modify `ui-references/` at runtime; it is reference only.
