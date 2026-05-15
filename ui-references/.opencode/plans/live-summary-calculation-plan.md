# Live Summary Calculation — Implementation Plan

## Overview

Replace the broken/incorrect total package value calculation logic used across the Create Booking page live summary, backend calculations, and database persistence. The current formula incorrectly uses `package.offer_price * passenger_count` without accounting for passenger type (adult/child/infant), service_required variations, per-passenger ticket fares, visa selling price, or service charge breakdowns.

---

## Current State Analysis

### Existing Calculation (INCORRECT)

**Frontend (`openPaymentModal`, booking.js:1336):**
```
total = (package.offer_price OR regular_price) * passenger_count + fingerprint_charge - discount_value
```
Issues: Uses package-level price × count, ignores passenger type, ignores visa/service charge breakdown.

**Backend (`BookingService.calculateTotal`, BookingService.php:43):**
```php
$packageValue = $package ? ($package->offer_price ?? $package->regular_price) * $passengers->count() : 0;
$fingerprintCharge = $this->getFingerprintCharge(...);
$total = $passengerTotal + $fingerprintCharge;
```
Issues: Identical to frontend — not per-passenger, no type differentiation, no service_required logic.

**Database:** No `package_value` or `total_value` columns exist anywhere.

### Existing Data Architecture

| Table | Column | Type | Purpose |
|-------|--------|------|---------|
| `ticket_fares` | `selling_fare` | decimal(10,2) | Base ticket price |
| `ticket_fares` | `child_fare_percentage` | decimal(5,2) | % of selling_fare for child |
| `ticket_fares` | `infant_fare_percentage` | decimal(5,2) | % of selling_fare for infant |
| `visa_selling_prices` | `selling_price` | decimal(10,2) | Visa price |
| `packages` | `service_charge` | decimal(10,2), nullable | Service charge |
| `packages` | `visa_selling_price_id` | FK → visa_selling_prices | Link to visa price |
| `fingerprints` | `cost` | decimal(10,2) | Fingerprint charge (home only) |
| `passengers` | `ticket_fare_id` | FK → ticket_fares | Per-passenger ticket |
| `passengers` | `passenger_type` | enum(adult/child/infant) | Determines fare multiplier |
| `passengers` | `service_required` | enum(all/ticket_only/visa_only) | Determines included components |
| `bookings` | `fingerprint_location` | enum(home/office) | Determines if charge applies |
| `bookings` | `district_id` | FK | Needed to look up fingerprint charge |

### Formula Reference

```
service_required = 'all':
    package_value = ticket_fare_amount + visa_selling_price + service_charge

service_required = 'ticket_only':
    package_value = ticket_fare_amount + service_charge  (visa EXCLUDED)

service_required = 'visa_only':
    package_value = visa_selling_price + service_charge  (ticket EXCLUDED)

Where ticket_fare_amount (per passenger type):
    adult:  ticket_fare.selling_fare
    child:  ticket_fare.selling_fare * ticket_fare.child_fare_percentage / 100
    infant: ticket_fare.selling_fare * ticket_fare.infant_fare_percentage / 100

total_value (booking level) = SUM(passenger.package_value) + fingerprint_charge

fingerprint_charge applies only when booking.fingerprint_location = 'home'
```

---

## Correct Formula

### Per-Passenger Package Value

```
Package Value = Ticket Amount + Visa Amount + Service Charge Amount
```

| service_required | Ticket | Visa | Service Charge |
|-----------------|--------|------|----------------|
| `all` | ✓ | ✓ | ✓ |
| `ticket_only` | ✓ | ✗ | ✓ |
| `visa_only` | ✗ | ✓ | ✓ |

### Ticket Amount by Passenger Type

| passenger_type | Ticket Amount Formula |
|---------------|----------------------|
| `adult` | selling_fare |
| `child` | selling_fare × child_fare_percentage / 100 |
| `infant` | selling_fare × infant_fare_percentage / 100 |

### Booking Total Value

```
total_value = SUM(passenger.package_value for all passengers) + fingerprint_charge
```

`fingerprint_charge` = `fingerprints.cost` when `fingerprint_location = 'home'`, else 0.

---

## Missing Pieces

1. **Database columns** — `passengers.package_value` and `bookings.total_value` do not exist
2. **Backend calculation** — `BookingService` uses wrong formula, no per-passenger breakdown
3. **Controller persistence** — values not saved to DB on create/update/add/remove passenger
4. **Frontend data** — Alpine does not have fare percentages, visa price, service charge from server
5. **Live summary card** — Total and Value columns are hardcoded to `0 SAR`, never reactive
6. **openPaymentModal** — Broken `#bookingPackage` selector reads wrong data
7. **Frontend formula** — No JavaScript function matching PHP calculation logic
8. **No recalculation trigger** — Ticket change, passenger type change, service_required change do not trigger recalculation

---

## Implementation Phases

### Phase 1: Database Migration

#### Step 1.1: Create migration for value columns

**File:** `database/migrations/2026_05_14_000002_add_value_columns_to_passengers_and_bookings.php`

**Conventions used:**
- `return new class extends Migration` pattern
- `DB::statement('ALTER TABLE ... ADD CONSTRAINT ... CHECK (...)')` for positivity constraint
- Try/catch in `down()` for MariaDB compatibility
- `Schema::hasTable` / `Schema::hasColumn` guards before drop

**up():**
```php
Schema::table('passengers', function (Blueprint $table) {
    $table->decimal('package_value', 12, 2)
        ->unsigned()
        ->nullable()
        ->after('ticket_fare_id');
});

DB::statement(
    'ALTER TABLE passengers ADD CONSTRAINT passengers_package_value_check CHECK (package_value >= 0)'
);

Schema::table('bookings', function (Blueprint $table) {
    $table->decimal('total_value', 14, 2)
        ->unsigned()
        ->nullable()
        ->after('discount_amount');
});

DB::statement(
    'ALTER TABLE bookings ADD CONSTRAINT bookings_total_value_check CHECK (total_value >= 0)'
);
```

**down():**
```php
try {
    DB::statement(
        'ALTER TABLE passengers DROP CHECK IF EXISTS passengers_package_value_check'
    );
} catch (\Exception $e) {
    // MariaDB compatibility
}

try {
    DB::statement(
        'ALTER TABLE bookings DROP CHECK IF EXISTS bookings_total_value_check'
    );
} catch (\Exception $e) {
    // MariaDB compatibility
}

if (Schema::hasTable('passengers') && Schema::hasColumn('passengers', 'package_value')) {
    Schema::table('passengers', function (Blueprint $table) {
        $table->dropColumn('package_value');
    });
}

if (Schema::hasTable('bookings') && Schema::hasColumn('bookings', 'total_value')) {
    Schema::table('bookings', function (Blueprint $table) {
        $table->dropColumn('total_value');
    });
}
```

**Design decisions:**
- `decimal(12,2)` for passengers: max ~99 crore SAR per passenger
- `decimal(14,2)` for bookings: max ~999 crore SAR for large groups
- `unsigned()` + CHECK `>= 0`: double-enforced positivity (Laravel's unsigned on decimal is advisory-only)
- `nullable()`: existing records left without value (per project decision)
- Constraint names follow `table_column_check` pattern used in existing migrations

**Dependencies:** None

**Priority:** HIGH

---

### Phase 2: Model Updates

#### Step 2.1: Update Passenger model

**File:** `app/Models/Passenger.php`

**Changes:**
1. Add `'package_value'` to `$fillable` array
2. Add cast: `'package_value' => 'decimal:2'`

#### Step 2.2: Update Booking model

**File:** `app/Models/Booking.php`

**Changes:**
1. Add `'total_value'` to `$fillable` array
2. Add cast: `'total_value' => 'decimal:2'`

**Dependencies:** Phase 1

**Priority:** HIGH

---

### Phase 3: Backend — Centralized Calculation Service

#### Step 3.1: Add calculatePackageValue method

**File:** `app/Services/BookingService.php`

**New method:**
```php
public function calculatePackageValue(Passenger $passenger): float
{
    $ticketFare = $passenger->ticketFare;
    $booking = $passenger->booking;
    $package = $booking->package;
    $serviceRequired = $passenger->service_required;
    $passengerType = $passenger->passenger_type;

    $ticketAmount = 0;
    $visaAmount = 0;
    $serviceChargeAmount = 0;

    // Ticket fare calculation — depends on passenger type
    if ($ticketFare) {
        $baseFare = (float) $ticketFare->selling_fare;
        $ticketAmount = match ($passengerType) {
            'child'  => $baseFare * ((float) $ticketFare->child_fare_percentage) / 100,
            'infant' => $baseFare * ((float) $ticketFare->infant_fare_percentage) / 100,
            default  => $baseFare,
        };
    }

    // Visa + service charge — excluded only when service_required = 'ticket_only'
    if ($serviceRequired !== 'ticket_only' && $package) {
        $visaAmount = (float) ($package->visaSellingPrice?->selling_price ?? 0);
        $serviceChargeAmount = (float) ($package->service_charge ?? 0);
    }

    // ticket_only: no visa, but YES service charge
    if ($serviceRequired === 'ticket_only' && $package) {
        $serviceChargeAmount = (float) ($package->service_charge ?? 0);
    }

    return $ticketAmount + $visaAmount + $serviceChargeAmount;
}
```

#### Step 3.2: Add recalculateBookingTotal method

**File:** `app/Services/BookingService.php`

**New method:**
```php
public function recalculateBookingTotal(Booking $booking): float
{
    // Recalculate each passenger's package_value
    foreach ($booking->passengers as $passenger) {
        $passenger->package_value = $this->calculatePackageValue($passenger);
        $passenger->saveQuietly(); // avoid firing events for bulk update
    }

    // Total = sum of passenger package_values + fingerprint_charge
    $passengerTotal = (float) $booking->passengers->sum('package_value');
    $fingerprintCharge = $this->getFingerprintCharge(
        $booking->district_id,
        $booking->fingerprint_location ?? 'office'
    );
    $total = $passengerTotal + $fingerprintCharge;

    // Persist
    $booking->total_value = $total;
    $booking->saveQuietly();

    return $total;
}
```

#### Step 3.3: Update calculateTotal method

**File:** `app/Services/BookingService.php`

**Change:** Replace the incorrect inline formula in `calculateTotal()` with:
```php
$passengerTotal = (float) $booking->passengers->sum('package_value');
$fingerprintCharge = $this->getFingerprintCharge(...);
$subtotal = $passengerTotal + $fingerprintCharge;
```

Use `$booking->passengers->sum('package_value')` instead of the old `package.offer_price * count` calculation.

**Dependencies:** Phase 1, Phase 2

**Priority:** HIGH

---

### Phase 4: Controller — Persist and Expand Data

#### Step 4.1: Expand data passed to create booking view

**File:** `app/Http/Controllers/BookingController.php` → `create()` method

**Change 1 — Expand ticket fares JSON:**
When building `$ticketFares`, include fare percentages and service charge:
```php
$ticketFares = TicketFare::with([...relationships...])->get()->map(function ($fare) {
    return [
        // existing fields: id, route, airline, class, price, offer_price, route_type, flight_type, etc.
        'selling_fare' => $fare->selling_fare,
        'child_fare_percentage' => $fare->child_fare_percentage,
        'infant_fare_percentage' => $fare->infant_fare_percentage,
        'service_charge' => $fare->package?->service_charge ?? 0,
    ];
});
```

**Change 2 — Expand packages JSON:**
When building `$packages`, include visa selling price and service charge:
```php
$packages = Package::with(['ticketFare', 'visaSellingPrice'])->get()->map(function ($pkg) {
    return [
        // existing fields: id, package_name, ...
        'visa_selling_price' => $pkg->visaSellingPrice?->selling_price ?? 0,
        'service_charge' => $pkg->service_charge ?? 0,
    ];
});
```

**Why:** Alpine.js needs these values to compute per-passenger package value reactively on the frontend.

#### Step 4.2: Persist values on store

**File:** `app/Http/Controllers/BookingController.php` → `store()` method

**Location:** After the passenger creation loop (approximately line ~198)

```php
// After: foreach ($validated['passengers'] as $passengerData) { Passenger::create(...); }
// After the loop:
$booking->refresh();
$bookingService->recalculateBookingTotal($booking);
```

#### Step 4.3: Persist values on add passenger

**File:** `app/Http/Controllers/BookingController.php` → `addPassenger()` method

**Location:** After passenger creation (approximately line ~300)

```php
$bookingService->recalculateBookingTotal($booking->fresh());
```

#### Step 4.4: Persist values on remove passenger

**File:** `app/Http/Controllers/BookingController.php` → `removePassenger()` method

**Location:** After passenger deletion (approximately line ~318)

```php
$bookingService->recalculateBookingTotal($booking->fresh());
```

#### Step 4.5: Persist values on update

**File:** `app/Http/Controllers/BookingController.php` → `update()` method

**Location:** After booking update

```php
$bookingService->recalculateBookingTotal($booking->fresh());
```

#### Step 4.6: Add recalculate API endpoint

**File:** `app/Http/Controllers/BookingController.php`

**New method:**
```php
public function recalculatePassengerValue(Request $request, Passenger $passenger)
{
    $packageValue = $bookingService->calculatePackageValue($passenger);
    $passenger->update(['package_value' => $packageValue]);
    $bookingService->recalculateBookingTotal($passenger->booking->fresh());

    return response()->json([
        'package_value' => $packageValue,
        'total_value' => $passenger->booking->total_value,
    ]);
}
```

**Dependencies:** Phase 3

**Priority:** HIGH

---

### Phase 5: Routes

#### Step 5.1: Add recalculate route

**File:** `routes/web.php`

**Route:**
```php
Route::patch('/bookings/{booking}/passengers/{passenger}/recalculate',
    [BookingController::class, 'recalculatePassengerValue']
)->name('bookings.passengers.recalculate');
```

**Dependencies:** Phase 4.6

**Priority:** HIGH

---

### Phase 6: Frontend — Blade Template Updates

#### Step 6.1: Package dropdown data attributes

**File:** `resources/views/bookings/create.blade.php` (approximately line 92-98)

**Change:** Add `data-visa-price` and `data-service-charge` to package `<option>` elements:

```html
<option
    value="{{ $package->id }}"
    data-id="{{ $package->id }}"
    data-visa-price="{{ $package->visaSellingPrice?->selling_price ?? 0 }}"
    data-service-charge="{{ $package->service_charge ?? 0 }}"
>
    {{ $package->package_name }}
</option>
```

#### Step 6.2: Ticket fare option data attributes

**File:** `resources/views/bookings/create.blade.php` (approximately line 323-329)

**Change:** Add data attributes to the Alpine-rendered ticket option template:

```html
<option
    :value="String(ticket.id)"
    :data-selling-fare="ticket.selling_fare"
    :data-child-pct="ticket.child_fare_percentage ?? 0"
    :data-infant-pct="ticket.infant_fare_percentage ?? 0"
    :data-service-charge="ticket.service_charge ?? 0"
    x-text="getTicketDisplayText(ticket)"
></option>
```

#### Step 6.3: Make summary card reactive

**File:** `resources/views/bookings/create.blade.php` (lines 203-204)

**Change:** Replace hardcoded `0 SAR` with reactive Alpine expressions:

```html
<!-- Grand Total (passenger values sum + fingerprint) -->
<span class="w-1/6 text-center" x-text="(grandTotalValue ?? 0).toFixed(2) + ' SAR'">0 SAR</span>

<!-- Total Value (before fingerprint) -->
<span class="w-1/6 text-center" x-text="(totalPackageValue ?? 0).toFixed(2) + ' SAR'">0 SAR</span>
```

**Dependencies:** Phase 7 (Alpine methods)

**Priority:** HIGH

---

### Phase 7: Frontend — AlpineJS Reactive Calculations

#### Step 7.1: Add reactive state to createBookingApp

**File:** `resources/js/booking.js` (approximately line 728, inside `createBookingApp()`)

**Add to data():**
```javascript
createBookingApp() {
    return {
        // ... existing state ...

        // NEW: per-passenger package values tracking
        passengerPackageValues: {},

        // Computed getters
        get totalPackageValue() {
            return Object.values(this.passengerPackageValues).reduce(
                (sum, v) => sum + (parseFloat(v) || 0), 0
            );
        },
        get grandTotalValue() {
            return this.totalPackageValue + (parseFloat(this.fingerprintCharge) || 0);
        },
```

#### Step 7.2: Add calculatePackageValue function

**File:** `resources/js/booking.js`

**New function inside createBookingApp:**
```javascript
calculatePackageValue(passenger, selectedPackage) {
    const ticketFareId = passenger.ticket_fare_id;
    const serviceRequired = passenger.service_required || 'all';
    const passengerType = passenger.passenger_type || 'adult';

    // Find selected ticket from allTickets
    const ticket = this.allTickets.find(t => String(t.id) === String(ticketFareId));
    if (!ticket) return 0;

    // Ticket fare amount based on passenger type
    const sellingFare = parseFloat(ticket.selling_fare) || 0;
    let ticketAmount = sellingFare;
    if (passengerType === 'child') {
        const pct = parseFloat(ticket.child_fare_percentage) || 0;
        ticketAmount = sellingFare * pct / 100;
    } else if (passengerType === 'infant') {
        const pct = parseFloat(ticket.infant_fare_percentage) || 0;
        ticketAmount = sellingFare * pct / 100;
    }

    // Visa + service charge
    const visaPrice = parseFloat(selectedPackage?.visa_selling_price) || 0;
    const serviceCharge = parseFloat(selectedPackage?.service_charge) || 0;

    let visaAmount = 0;
    let scAmount = 0;
    if (serviceRequired !== 'ticket_only') {
        visaAmount = visaPrice;
        scAmount = serviceCharge;
    } else {
        // ticket_only: no visa, but YES service charge
        scAmount = serviceCharge;
    }

    return ticketAmount + visaAmount + scAmount;
}
```

**Formula consistency:** This JS function MUST mirror the PHP `calculatePackageValue()` exactly. Any changes to the formula must be applied in both places.

#### Step 7.3: Add recalculation helper methods

**File:** `resources/js/booking.js`

```javascript
recalculateAllPassengerValues() {
    const pkg = this.allPackages.find(p => String(p.id) === String(this.bookingData.package_id));
    this.passengers.forEach((p, index) => {
        this.passengerPackageValues[index] = this.calculatePackageValue(p, pkg);
    });
},

recalculateCurrentPassenger(index) {
    const pkg = this.allPackages.find(p => String(p.id) === String(this.bookingData.package_id));
    this.passengerPackageValues[index] = this.calculatePackageValue(this.passengers[index], pkg);
},
```

#### Step 7.4: Wire reactivity

**File:** `resources/js/booking.js`

Wire recalculation calls into existing trigger methods:

| Trigger | Method | Call |
|---------|--------|------|
| Passenger added | `addPassenger()` | `this.recalculateAllPassengerValues()` |
| Passenger removed | `removePassenger()` | `this.recalculateAllPassengerValues()` |
| Ticket changed | `onTicketChange()` | `this.recalculateCurrentPassenger(this.currentPassengerIndex)` |
| Service required changed | `@change` on service dropdown | `this.recalculateCurrentPassenger(this.currentPassengerIndex)` |
| Passenger type changed (DOB) | `calculatePassengerType()` | `this.recalculateCurrentPassenger(this.currentPassengerIndex)` |
| Package changed | `@change` on package dropdown | `this.recalculateAllPassengerValues()` |
| Initial load | `init()` | `this.recalculateAllPassengerValues()` |

#### Step 7.5: Fix openPaymentModal

**File:** `resources/js/booking.js` (approximately line 1336)

**Change:** Replace broken `#bookingPackage` selector logic with reactive computed values:

```javascript
openPaymentModal() {
    const totalPkgVal = this.totalPackageValue;
    const fpCharge = parseFloat(this.fingerprintCharge) || 0;
    const discount = parseFloat(this.bookingData.discount_value) || 0;
    const discountType = this.bookingData.discount_type;
    const grand = totalPkgVal + fpCharge;
    const discountAmount = discountType === 'percentage'
        ? grand * discount / 100
        : discount;
    const due = grand - discountAmount;

    // Update DOM elements
    if (totalEl) totalEl.textContent = grand.toFixed(2) + ' SAR';
    if (dueEl) dueEl.textContent = due.toFixed(2) + ' SAR';
    if (discountEl) discountEl.textContent = discountAmount.toFixed(2) + ' SAR';
    if (subtotalEl) subtotalEl.textContent = totalPkgVal.toFixed(2) + ' SAR';
}
```

Also fix discount calculation: use percentage or fixed based on `discount_type`, not raw subtraction.

**Dependencies:** Phase 3, Phase 4, Phase 6

**Priority:** HIGH

---

### Phase 8: Edge Cases

All edge cases are handled inside `calculatePackageValue()` (PHP) and `calculatePackageValue()` (JS):

| Edge Case | Handling |
|-----------|----------|
| `ticket_fare_id` is null | Return 0 — no ticket charge |
| `child_fare_percentage` is null/0 | Multiply by 0 = free child fare |
| `infant_fare_percentage` is null/0 | Multiply by 0 = free infant fare |
| `selling_fare` is null | Treat as 0 |
| `visa_selling_price` is null | Treat as 0 |
| `service_charge` is null | Treat as 0 |
| `package` is null | Return ticket amount only |
| `fingerprint_location` = 'office' | Fingerprint charge = 0 |
| `fingerprint_location` = 'home' | Use fingerprints.cost from DB |
| `district_id` not set | Fingerprint charge = 0 |

---

## Implementation Order

```
Phase 1 → Phase 2 → Phase 3 → Phase 4 → Phase 5 → Phase 6 → Phase 7 → Phase 8
```

**Rationale:**
1. Database must exist before models can use the columns
2. Models must be ready before service logic
3. Service logic is the single source of truth for both controller and future use
4. Controller ties persistence to service
5. Routes wires the API
6. Blade provides data attributes for Alpine
7. Alpine wires reactivity
8. Edge cases are already handled inside Phase 3/7

---

## Files Summary

| # | File | Action |
|---|------|--------|
| 1 | `database/migrations/2026_05_14_000002_add_value_columns_to_passengers_and_bookings.php` | **Create** |
| 2 | `app/Models/Passenger.php` | **Edit** — fillable + cast |
| 3 | `app/Models/Booking.php` | **Edit** — fillable + cast |
| 4 | `app/Services/BookingService.php` | **Edit** — 2 new methods, update 1 |
| 5 | `app/Http/Controllers/BookingController.php` | **Edit** — expand view data (Step 4.1) |
| 6 | `app/Http/Controllers/BookingController.php` | **Edit** — persist on store/add/remove/update (Steps 4.2–4.5) |
| 7 | `app/Http/Controllers/BookingController.php` | **Edit** — new recalculate endpoint (Step 4.6) |
| 8 | `routes/web.php` | **Edit** — add recalculate route |
| 9 | `resources/views/bookings/create.blade.php` | **Edit** — data attributes, reactive summary (Steps 6.1–6.3) |
| 10 | `resources/js/booking.js` | **Edit** — Alpine reactive state, methods, wiring (Steps 7.1–7.5) |

**Total: 1 new file, 9 file edits across 4 layers (Database, Models, Backend, Frontend).**

---

## Formula Consistency Matrix

| Layer | File | Method | Formula |
|-------|------|--------|---------|
| Backend | `BookingService.php` | `calculatePackageValue()` | Single source of truth |
| Backend | `BookingService.php` | `recalculateBookingTotal()` | Uses `calculatePackageValue()` |
| Backend | `BookingService.php` | `calculateTotal()` | Uses `sum('package_value')` |
| Controller | `BookingController.php` | `store/add/remove/update` | Calls `recalculateBookingTotal()` |
| Controller | `BookingController.php` | `recalculatePassengerValue()` | API endpoint |
| Frontend | `booking.js` | `calculatePackageValue()` | Must mirror PHP exactly |
| Frontend | `booking.js` | `recalculateAllPassengerValues()` | Uses JS `calculatePackageValue()` |
| Frontend | `booking.js` | computed `totalPackageValue` | Sums `passengerPackageValues` |
| Frontend | `booking.js` | computed `grandTotalValue` | `totalPackageValue + fingerprintCharge` |

---

## Testing Checklist

### Backend Tests
- [ ] Create booking with 1 adult → package_value = selling_fare + visa + service
- [ ] Create booking with 1 child → package_value = (selling_fare × child_pct/100) + visa + service
- [ ] Create booking with 1 infant → package_value = (selling_fare × infant_pct/100) + visa + service
- [ ] Create booking with 3 mixed passengers → each has different value, total = sum
- [ ] `service_required = 'ticket_only'` → visa EXCLUDED, service INCLUDED
- [ ] `service_required = 'visa_only'` → ticket EXCLUDED, visa + service INCLUDED
- [ ] Remove passenger → total_value recalculates
- [ ] `fingerprint_location = 'home'` → charge added to total_value
- [ ] `fingerprint_location = 'office'` → fingerprint charge = 0 in total_value
- [ ] DB: passengers.package_value matches formula result
- [ ] DB: bookings.total_value = sum(package_values) + fingerprint_charge

### Frontend Tests
- [ ] Summary card shows reactive Total and Value (not hardcoded 0)
- [ ] Change DOB (child→adult) → per-passenger value updates
- [ ] Change ticket on passenger → value updates
- [ ] Change service_required → value updates
- [ ] Change package → all passengers recalculate
- [ ] openPaymentModal shows correct total (no broken selector)
- [ ] Percentage discount calculated correctly in payment modal
- [ ] Per-passenger values displayed correctly

### Edge Case Tests
- [ ] Passenger with no ticket_fare_id → package_value = 0 or service-only
- [ ] Ticket fare with null percentages → treated as 0
- [ ] Package with null service_charge → treated as 0
- [ ] Package with no linked visa selling price → treated as 0
- [ ] Booking with no passengers → total_value = fingerprint_charge only

---

## Future Improvement Suggestions

1. **Move calculation logic to domain layer** — Create `app/Domain/BookingCalculator.php` as the canonical calculator, used by both `BookingService` and API controllers. This makes the formula testable in isolation.

2. **Add PassengerType enum** — Currently using string literals ('adult', 'child', 'infant'). A typed enum would provide autocompletion and type safety.

3. **ServiceRequired enum** — Same as above for 'all', 'ticket_only', 'visa_only'.

4. **Event-driven recalculation** — Instead of manually calling `recalculateBookingTotal()` in 4 controller methods, fire a `BookingPassengerChanged` event and listen in a single place.

5. **Package value history** — Store historical package_value snapshots so audit trail exists for pricing disputes.

6. **Currency precision** — Consider using a custom decimal type or `brick/money` library to avoid floating-point issues across all financial calculations.

7. **Backend test suite** — Add unit tests for `calculatePackageValue()` covering all passenger types, all service_required combinations, and all edge cases.

8. **Frontend formula extraction** — Extract the JS `calculatePackageValue()` into a shared module that both the create booking page and the booking index page can import, avoiding duplication.

---

*Plan created based on analysis of:*
- `database/migrations/` — bookings, passengers, ticket_fares, visa_selling_prices, packages, fingerprints
- `app/Models/` — Booking, Passenger, TicketFare, VisaSellingPrice, Package, FingerprintCharge
- `app/Services/BookingService.php` — existing (incorrect) calculation logic
- `app/Http/Controllers/BookingController.php` — create(), store(), update(), addPassenger(), removePassenger(), print()
- `resources/views/bookings/create.blade.php` — live summary card (lines 182-206), ticket dropdown
- `resources/js/booking.js` — createBookingApp (line 727), openPaymentModal (line 1336), passenger methods
- `routes/web.php` — booking routes