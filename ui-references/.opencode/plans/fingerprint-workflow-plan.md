# Fingerprint Workflow Implementation Plan

## Overview

This document provides a complete implementation plan for the Fingerprint Workflow system in the BM Umrah booking application. The system allows admins to manage fingerprint tasks across bookings and passengers, while staff can update fingerprint status and track processing.

---

## Current State Analysis

### Already Implemented
- `fingerprint_charges` table with `district_id`, `user_id`, `fingerprint_charge` (managed via `FingerprintChargeController`)
- `FingerprintCharge` model with relations to `District` and `User`
- `FingerprintLocation` enum (home/office)
- Routes: `/fingerprints/admin` and `/fingerprints/staff` (placeholder views)
- `Booking` model has `fingerprint_location` and `fingerprint_charge_id`
- UI reference files demonstrating intended workflow

### Missing Pieces (Critical)
1. **Passenger fingerprint fields** - No fingerprint status, deadline, cost, or staff assignment fields on Passenger model
2. **Fingerprint status tracking** - No way to track passenger fingerprint progress (pending/processing/done)
3. **Hold/reschedule logic** - No handling for "Hold by BMT/Client" workflow
4. **Staff assignment** - No way to assign staff to fingerprint tasks
5. **API endpoints** - No dynamic data endpoints for admin/staff views
6. **Updated blade views** - Current views are placeholders with static HTML
7. **Booking → Passenger integration** - Fingerprint data not flowing from booking level to passenger level

---

## Implementation Phases

### Phase 1: Database & Models (Foundation)

#### Step 1.1: Create FingerprintStatus Enum
**Files affected:** `app/Enums/FingerprintStatus.php` (new)

```php
enum FingerprintStatus: string
{
    case PENDING = 'pending';
    case PROCESSING = 'processing';
    case DONE = 'done';
    case NFC_PROBLEM = 'nfc_problem';
    case HOLD_BY_BMT = 'hold_by_bmt';
    case HOLD_BY_CLIENT = 'hold_by_client';
    case RESCHEDULE = 'reschedule';
    case CANCEL = 'cancel';
}
```

**Dependencies:** None

**Validation:** Ensure enum values match UI reference options

---

#### Step 1.2: Add Fingerprint Fields to Passengers Table (Migration)
**Files affected:** `database/migrations/YYYY_MM_DD_add_fingerprint_fields_to_passengers_table.php` (new)

Add columns:
- `fingerprint_status` - enum('pending', 'processing', 'done', 'nfc_problem', 'hold_by_bmt', 'hold_by_client', 'reschedule', 'cancel')
- `fingerprint_completed_date` - date (nullable)
- `fingerprint_deadline` - date (nullable)
- `finger_cost` - decimal(10,2) (nullable) - cost incurred by staff
- `assigned_staff_id` - unsignedBigInteger (nullable) foreign to users
- `hold_reason` - string (nullable)
- `hold_next_date` - date (nullable)
- `hold_remarks` - text (nullable)
- `fingerprint_location` - enum('home', 'office') (nullable) - overrides booking-level setting

**Migration考虑:**
- Add nullable columns initially (no data loss)
- Add CHECK constraint: finger_cost >= 0

**Dependencies:** Step 1.1, existing `passengers` table

**Validation:**
- Add foreign key constraint with `restrictOnDelete()` and `onUpdate('cascade')`
- Ensure check constraint validates finger_cost >= 0

---

#### Step 1.3: Update Passenger Model
**Files affected:** `app/Models/Passenger.php`

Add fields to `$fillable`:
```php
protected $fillable = [
    // ... existing fields
    'fingerprint_status',
    'fingerprint_completed_date',
    'fingerprint_deadline',
    'finger_cost',
    'assigned_staff_id',
    'hold_reason',
    'hold_next_date',
    'hold_remarks',
    'fingerprint_location',
];
```

Add casts:
```php
protected $casts = [
    // ... existing casts
    'fingerprint_status' => FingerprintStatus::class,
    'fingerprint_completed_date' => 'date',
    'fingerprint_deadline' => 'date',
    'finger_cost' => 'decimal:2',
    'hold_next_date' => 'date',
];
```

Add relationships:
```php
public function assignedStaff(): BelongsTo
{
    return $this->belongsTo(User::class, 'assigned_staff_id');
}

public function booking(): BelongsTo
{
    return $this->belongsTo(Booking::class);
}
```

**Dependencies:** Step 1.2

---

### Phase 2: Controllers & API Endpoints

#### Step 2.1: Create FingerprintAdminController
**Files affected:** `app/Http/Controllers/FingerprintAdminController.php` (new)

```php
public function index(Request $request)
{
    // Query filters: division, district, status, date_range
    // Eager load: booking.customer, booking.district, assignedStaff
    // Return view('fingerprints.admin', compact('fingerprints', 'filters'))
}

public function updateStaff(Request $request, Passenger $passenger)
{
    // Validate: staff_id required
    // Update assigned_staff_id
    // Return JSON response
}

public function updateStatus(Request $request, Passenger $passenger)
{
    // Validate: fingerprint_status required, valid enum
    // If 'done', set fingerprint_completed_date = now
    // If 'hold_by_*' or 'reschedule', require hold_reason, hold_next_date
    // Return JSON response
}

public function updateFingerprintCost(Request $request, Passenger $passenger)
{
    // Validate: finger_cost >= 0
    // Update finger_cost
    // Return JSON response
}

public function updateFingerprintLocation(Request $request, Passenger $passenger)
{
    // Validate: fingerprint_location (home/office)
    // Update fingerprint_location on passenger
    // Return JSON response
}

public function bulkAssignStaff(Request $request)
{
    // Validate: passenger_ids array, staff_id
    // Batch update assigned_staff_id
    // Return JSON response
}
```

**Dependencies:** Phase 1

**Permissions:** Require 'admin' or 'fingerprint_admin' role

**Validation concerns:**
- Validate passenger belongs to authenticated user's branch (if using branch-based access control)
- Prevent changing status if booking is cancelled/completed

**Business logic:**
- When fingerprint_status changes to 'done', auto-set `fingerprint_completed_date = now()`
- When changing to hold states, require reason and next date
- If booking has fingerprint_location='office', staff assignment should be read-only or automatic

---

#### Step 2.2: Create FingerprintStaffController
**Files affected:** `app/Http/Controllers/FingerprintStaffController.php` (new)

```php
public function index(Request $request)
{
    // Get only passengers assigned to current authenticated staff
    // Filters: status, date_range
    // Return view('fingerprints.staff', compact('fingerprints'))
}

public function updateApprovedVisa(Request $request, Passenger $passenger)
{
    // Validate: approved_visa status
    // If 'Hold & Ask for next Finger date?', trigger hold modal workflow
    // Update passenger record
    // Return JSON response
}

public function updateCost(Request $request, Passenger $passenger)
{
    // Validate: finger_cost >= 0
    // Update cost
    // Return JSON response
}

public function saveHoldDetails(Request $request, Passenger $passenger)
{
    // Validate: hold_reason required, hold_next_date required
    // Update: hold_reason, hold_next_date, hold_remarks
    // Set fingerprint_status = 'processing' (or appropriate)
    // Return JSON response
}

public function getAssignedTasks()
{
    // API for AJAX data loading
    // Return JSON of assigned passengers
}
```

**Dependencies:** Phase 1

**Permissions:** Require authenticated staff user

**Validation concerns:**
- Only allow updating passengers assigned to current staff
- Validate hold workflow requires both reason AND next date

**Business logic:**
- Staff can only see passengers assigned to them
- Staff can update finger_cost but not assigned_staff_id
- Hold workflow: select "Hold & Ask for next Finger date?" triggers modal

---

#### Step 2.3: Update Routes
**Files affected:** `routes/web.php`

Replace placeholder routes with:

```php
// Fingerprint Admin
Route::get('/fingerprints/admin', [FingerprintAdminController::class, 'index'])->name('fingerprint.admin');
Route::put('/fingerprints/admin/{passenger}/staff', [FingerprintAdminController::class, 'updateStaff'])->name('fingerprint.admin.staff');
Route::put('/fingerprints/admin/{passenger}/status', [FingerprintAdminController::class, 'updateStatus'])->name('fingerprint.admin.status');
Route::put('/fingerprints/admin/{passenger}/cost', [FingerprintAdminController::class, 'updateFingerprintCost'])->name('fingerprint.admin.cost');
Route::put('/fingerprints/admin/{passenger}/location', [FingerprintAdminController::class, 'updateFingerprintLocation'])->name('fingerprint.admin.location');
Route::post('/fingerprints/admin/bulk-assign', [FingerprintAdminController::class, 'bulkAssignStaff'])->name('fingerprint.admin.bulk');

// Fingerprint Staff
Route::get('/fingerprints/staff', [FingerprintStaffController::class, 'index'])->name('fingerprint.staff');
Route::put('/fingerprints/staff/{passenger}/visa', [FingerprintStaffController::class, 'updateApprovedVisa'])->name('fingerprint.staff.visa');
Route::put('/fingerprints/staff/{passenger}/cost', [FingerprintStaffController::class, 'updateCost'])->name('fingerprint.staff.cost');
Route::put('/fingerprints/staff/{passenger}/hold', [FingerprintStaffController::class, 'saveHoldDetails'])->name('fingerprint.staff.hold');
```

**API Routes (optional, for AJAX):**
```php
Route::get('/api/fingerprints/tasks', [FingerprintStaffController::class, 'getAssignedTasks'])->name('api.fingerprints.tasks');
```

**Dependencies:** Step 2.1, Step 2.2

---

### Phase 3: Blade Views (UI Implementation)

#### Step 3.1: Update Admin Blade View
**Files affected:** `resources/views/fingerprints/admin.blade.php`

**Based on UI reference:** `ui-references/fingerprint-admin.html`

**Implementation requirements:**
- Use Livewire or Alpine.js for dynamic interactions
- Table columns matching reference:
  - Invoice ID (from booking.invoice_id)
  - Booking Date (from booking.created_at)
  - Customer Name (from booking.customer.name)
  - PAX Qty (from booking.pax_qty)
  - Mobile (booking.customer.mobile + passenger local number if available)
  - District (from booking.district.name)
  - Fingerprint Deadline (from passenger.fingerprint_deadline)
  - Fingerprint Cost (from passenger.finger_cost)
  - Fingerprint Location (from booking.fingerprint_location or passenger.fingerprint_location)
  - Assign Staff (dropdown)
  - Passenger (passenger.name)
  - Fingerprint Status (dropdown)
  - Required Flight Date (from passenger.flight_date_from)
  - Actual Flight Date (from passenger.actual_flight_date)

**UI behaviors:**
- Staff assignment dropdown disabled if fingerprint_location='office'
- Status dropdown triggers AJAX save on change
- Hold modal (same as reference) for rescheduling
- Display empty state if no records

**Filters:**
- Division dropdown
- District dropdown
- Status dropdown
- Date range picker

**Dependencies:** Step 2.1, Step 1.3

---

#### Step 3.2: Update Staff Blade View
**Files affected:** `resources/views/fingerprints/staff.blade.php`

**Based on UI reference:** `ui-references/fingerprint-staff.html`

**Implementation requirements:**
- Table columns matching reference:
  - Invoice ID
  - Customer Name
  - PAX Qty
  - Mobile
  - Office (fingerprint location)
  - District
  - Fingerprint Deadline
  - Passenger Name
  - Cost (SAR) - editable input field
  - Approved Visa dropdown

**UI behaviors:**
- Cost field allows inline editing
- Approved Visa dropdown: "Hold & Ask for next Finger date?" triggers hold modal
- Hold modal includes Reason, Next Finger Date, Remarks fields
- Show only assigned passengers

**Hold modal fields:**
- Reason dropdown: "Reschedule by Client", "Reschedule by BMT", "NFC Problem"
- Next Finger Date (date picker)
- Remarks (text input)

**Dependencies:** Step 2.2, Step 1.3

---

### Phase 4: Booking Integration

#### Step 4.1: Set Fingerprint Deadline on Booking Create
**Files affected:** `app/Http/Controllers/BookingController.php` (update store method)

**Business logic:** When booking is created:
- Calculate fingerprint_deadline based on package/flight_date
- For each passenger, set initial fingerprint_status = 'pending'
- Set fingerprint_deadline = calculated date or package default

**Implementation:**
```php
// In store() method, after creating passengers:
$flightDate = $validated['passengers'][0]['flight_date_from'] ?? now()->addDays(14);
$deadline = $flightDate->subDays(config('umrah.fingerprint_days_before', 14));

foreach ($booking->passengers as $passenger) {
    $passenger->update([
        'fingerprint_status' => 'pending',
        'fingerprint_deadline' => $deadline,
    ]);
}
```

**Dependencies:** Phase 1

---

#### Step 4.2: Create FingerprintDeadline Calculation Service
**Files affected:** `app/Services/FingerprintService.php` (new)

```php
class FingerprintService
{
    public function calculateDeadline(Package $package, Carbon $flightDate): Carbon
    {
        // Default: 14 days before flight
        $daysBefore = $package->fingerprint_days_before ?? 14;
        return $flightDate->subDays($daysBefore);
    }

    public function isOverdue(Passenger $passenger): bool
    {
        if (!$passenger->fingerprint_deadline) return false;
        return now()->gt($passenger->fingerprint_deadline);
    }

    public function getBookingFingerprintSummary(Booking $booking): array
    {
        // Return summary: total_cost, completed_count, pending_count, etc.
    }
}
```

**Dependencies:** Phase 1

---

### Phase 5: Permission & Authorization

#### Step 5.1: Add Role/Permission Check
**Files affected:** `app/Http/Middleware/EnsureUserIsAdmin.php` (new or update existing)

If using role-based auth:

```php
public function handle($request, $next, $role = 'admin')
{
    $user = $request->user();
    
    if (!$user) {
        return redirect('/login');
    }
    
    // Check if user has required role
    // Or check permission guard
    
    return $next($request);
}
```

**Add to routes:**
```php
Route::middleware(['auth', 'role:admin'])->group(function () {
    // Admin routes
});

Route::middleware(['auth', 'role:staff'])->group(function () {
    // Staff routes
});
```

**Dependencies:** Step 2.3

---

### Phase 6: Reports (Optional Enhancement)

#### Step 6.1: Fingerprint Report Updates
**Files affected:** `app/Http/Controllers/FingerprintReportController.php` (new), `resources/views/reports/fingerprint.blade.php`

**Based on:** `ui-references/fingerprint-report.js`

**Features:**
- Filter by: status, date_range, location, division, district
- Export to Excel
- Profit calculation: finger_charge - finger_cost per passenger

**Dependencies:** Phase 1, Step 1.2

---

### Phase 7: Edge Cases & Error Handling

#### Step 7.1: Handle Edge Cases
1. **Passenger deleted after assignment:** Soft delete or prevent deletion if fingerprint in progress
2. **Booking cancelled:** Mark all passengers as cancelled or prevent changes
3. **Staff user deleted:** Clear assigned_staff_id or reassign to admin
4. **Fingerprint location changes after assignment:** Recalculate costs or warn user

**Implementation in models:**
```php
// In Passenger model
public function scopeActive($query)
{
    return $query->whereHas('booking', fn($q) => $q->where('status', '!=', 'cancelled'));
}
```

#### Step 7.2: Add Activity Logging
**Files affected:** `app/Models/FingerprintActivity.php` (new model), migration

Track:
- passenger_id
- user_id (who made change)
- action (status_change, staff_assignment, hold, etc.)
- old_value
- new_value
- created_at

**Dependencies:** Phase 1

---

## Implementation Order (Recommended)

1. **Phase 1** (Steps 1.1-1.4) - Database & Models foundation
2. **Phase 2** (Steps 2.1-2.3) - Controllers & Routes  
3. **Phase 3** (Steps 3.1-3.2) - Blade Views
4. **Phase 4** (Steps 4.1-4.2) - Booking Integration
5. **Phase 5** (Step 5.1) - Permissions
6. **Phase 6** (Step 6.1) - Reports (if needed)
7. **Phase 7** (Steps 7.1-7.2) - Edge Cases

**Rationale:** This order minimizes refactoring because:
- Database schema must exist before any queries
- Controllers depend on models
- Views depend on controllers
- Booking integration builds on all previous phases

---

## Testing Strategy

### Unit Tests
- FingerprintService deadline calculations
- Enum values validation
- Model attribute casting

### Feature Tests
- Admin can assign staff to passenger
- Staff can update fingerprint status
- Hold workflow saves correct data
- Bulk assign works correctly

### Browser Tests (Dusk)
- Admin page loads with correct data
- Staff page shows only assigned passengers
- Hold modal appears and saves correctly
- Status changes persist

### API Tests
- All JSON endpoints return correct responses
- Authorization blocks unauthorized access
- Validation errors returned properly

---

## Key Files to Create/Modify

### New Files
| File | Purpose |
|------|---------|
| `app/Enums/FingerprintStatus.php` | Fingerprint status enum |
| `app/Services/FingerprintService.php` | Business logic |
| `app/Http/Controllers/FingerprintAdminController.php` | Admin page |
| `app/Http/Controllers/FingerprintStaffController.php` | Staff page |
| `app/Models/FingerprintActivity.php` | Activity logging |

### Modified Files
| File | Changes |
|------|--------|
| `app/Models/Passenger.php` | Add fingerprint fields, casts, relationships |
| `app/Http/Controllers/BookingController.php` | Set initial fingerprint data |
| `routes/web.php` | Add new routes |
| `resources/views/fingerprints/admin.blade.php` | Full implementation |
| `resources/views/fingerprints/staff.blade.php` | Full implementation |

### New Migrations
| File | Purpose |
|------|---------|
| `database/migrations/*_add_fingerprint_fields_to_passengers_table.php` | Add passenger fingerprint columns |
| `database/migrations/*_add_fingerprint_activities_table.php` | Activity log (if implementing) |

---

## Summary Checklist

- [ ] Create FingerprintStatus enum
- [ ] Create migration for passenger fingerprint fields
- [ ] Update Passenger model with fingerprint fields
- [ ] Create FingerprintAdminController
- [ ] Create FingerprintStaffController
- [ ] Update routes
- [ ] Implement admin Blade view with full UI
- [ ] Implement staff Blade view with full UI
- [ ] Update BookingController to set initial fingerprint data
- [ ] Add role/permission middleware
- [ ] Add error handling and edge cases
- [ ] Write tests

---

*Plan created based on analysis of:*
- `database/migrations/2026_05_04_150011_create_fingerprint_charges_table.php`
- `app/Models/FingerprintCharge.php`, `Booking.php`, `Passenger.php`
- `app/Http/Controllers/FingerprintChargeController.php`, `BookingController.php`
- `routes/web.php`
- `ui-references/fingerprint-admin.html`, `fingerprint-admin.js`
- `ui-references/fingerprint-staff.html`, `fingerprint-staff.js`
- `app/Enums/FingerprintLocation.php`
