# Branch-Office Merge Plan

## Overview

Merge the `offices` table into the `branches` table by adding `location` (KSA/BD) and `fingerprint_operation` (boolean) columns. The `offices` table is dropped. This unifies two structurally identical entities that were split only for country differentiation and fingerprint workflow scoping.

---

## Execution Sequence

| Step | What | Command |
|------|------|---------|
| 1 | **Migration 1** — Add `location`, `fingerprint_operation` to `branches` | `php artisan migrate` |
| 2 | **Migration 2** — Rename `bookings.branch_id`→`booking_branch_id`, `office_id`→`fingerprint_branch_id`; update FKs | (same `migrate`) |
| 3 | **Migration 3** — Drop `users.office_id` column + FK | (same `migrate`) |
| 4 | **Seed data** — Copy offices→branches, update existing booking/user FKs | `php artisan db:seed --class=MergeOfficesIntoBranchesSeeder` |
| 5 | **Migration 4** — Drop `offices` table | `php artisan migrate` |

### Why this order

- Steps 1-3 are pure schema changes (safe, no data loss).
- Step 4 reads from `offices` (still exists), copies data into `branches`, updates FKs on `bookings` and `users`.
- Step 5 drops the now-empty `offices` table.

On an empty database (tests/fresh install): Step 4 is a no-op (zero rows in `offices`).

---

## Phase 1: Enum

**New file:** `app/Enums/Location.php`

```php
<?php

namespace App\Enums;

enum Location: string
{
    case KSA = 'KSA';
    case BD = 'BD';
}
```

---

## Phase 2: Database Migrations

### Migration 1 — `add_location_and_fingerprint_operation_to_branches_table`

```php
Schema::table('branches', function (Blueprint $table) {
    $table->string('location')->default('KSA');
    $table->boolean('fingerprint_operation')->default(false);
});
```

### Migration 2 — `rename_branch_and_office_columns_on_bookings_table`

```php
Schema::table('bookings', function (Blueprint $table) {
    $table->dropForeign(['branch_id']);
    $table->dropForeign(['office_id']);
    $table->dropIndex(['branch_id']);
    $table->dropIndex(['office_id']);

    $table->renameColumn('branch_id', 'booking_branch_id');
    $table->renameColumn('office_id', 'fingerprint_branch_id');

    $table->foreign('booking_branch_id')->references('id')->on('branches')->onUpdate('cascade');
    $table->foreign('fingerprint_branch_id')->references('id')->on('branches')->onUpdate('cascade');
});
```

### Migration 3 — `drop_office_id_from_users_table`

```php
Schema::table('users', function (Blueprint $table) {
    $table->dropForeign(['office_id']);
    $table->dropColumn('office_id');
});
```

### Migration 4 — `drop_offices_table`

```php
Schema::dropIfExists('offices');
```

---

## Phase 3: Seeder (Standalone — not added to DatabaseSeeder)

**New file:** `database/seeders/MergeOfficesIntoBranchesSeeder.php`

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MergeOfficesIntoBranchesSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            // 1. Map old office IDs to new branch IDs
            $officeMap = [];
            $offices = DB::table('offices')->get();

            foreach ($offices as $office) {
                $newBranchId = DB::table('branches')->insertGetId([
                    'name' => $office->name,
                    'address' => $office->address,
                    'contacts' => $office->contacts,
                    'location' => 'BD',
                    'fingerprint_operation' => true,
                    'created_at' => $office->created_at ?? now(),
                    'updated_at' => $office->updated_at ?? now(),
                ]);
                $officeMap[$office->id] = $newBranchId;
            }

            // 2. Update existing KSA branches
            DB::table('branches')
                ->whereNull('location')
                ->orWhere('location', '')
                ->update(['location' => 'KSA', 'fingerprint_operation' => false]);

            // 3. Update bookings.fingerprint_branch_id
            foreach ($officeMap as $oldOfficeId => $newBranchId) {
                DB::table('bookings')
                    ->where('fingerprint_branch_id', $oldOfficeId)
                    ->update(['fingerprint_branch_id' => $newBranchId]);
            }

            // 4. Update users: merge office_id into branch_id
            foreach ($officeMap as $oldOfficeId => $newBranchId) {
                DB::table('users')
                    ->where('office_id', $oldOfficeId)
                    ->whereNull('branch_id')
                    ->update(['branch_id' => $newBranchId]);
            }
        });
    }
}
```

---

## Phase 4: Model Changes

### `app/Models/Branch.php`

```php
protected $fillable = ['name', 'address', 'contacts', 'location', 'fingerprint_operation'];

protected $casts = [
    'location' => \App\Enums\Location::class,
    'fingerprint_operation' => 'boolean',
];
```

### `app/Models/Booking.php`

| Change | Before | After |
|--------|--------|-------|
| `$fillable` | `'branch_id'`, `'office_id'` | `'booking_branch_id'`, `'fingerprint_branch_id'` |
| Relation | `branch(): BelongsTo` | `bookingBranch(): BelongsTo` |
| Relation | `office(): BelongsTo` | `fingerprintBranch(): BelongsTo` |

### `app/Models/User.php`

- Remove `'office_id'` from `$fillable`.
- Remove `office(): BelongsTo` relation.
- Keep single `branch(): BelongsTo` (already exists).

### `app/Models/Office.php`

**DELETE** entire file.

### No changes to:
- `Invoice` (keeps `branch_id` → references `branches.id`)
- `Payment` (keeps `branch_id` → references `branches.id`)
- `Voucher` (keeps `branch_id` → references `branches.id`)
- `Fingerprint` (no direct FK to branch/office)

---

## Phase 5: Controller Changes

### `app/Http/Controllers/BranchController.php`

**`store()` / `update()`:**
```php
$validated = $request->validate([
    'name' => 'required|string|max:255',
    'address' => 'required|string|max:255',
    'contacts' => 'required|string|max:255',
    'location' => 'required|in:KSA,BD',
]);
$validated['fingerprint_operation'] = $validated['location'] === 'BD';
```

### `app/Http/Controllers/BookingController.php`

**`create()`:**
```php
$user = auth()->user();
$userBranch = $user->branch;

$bookingBranches = Branch::all();
$fingerprintBranches = Branch::where('fingerprint_operation', true)->get();

$showBookingBranch = !$userBranch; // visible if user has no branch
$showFingerprintBranch = !$userBranch || !$userBranch->fingerprint_operation;
// If BD branch: both hidden (auto-set)
// If KSA branch: booking_branch hidden, fingerprint_branch visible
// If no branch: both visible
```

**`store()`:**
```php
$user = auth()->user();
$userBranch = $user->branch;

if ($userBranch && $userBranch->fingerprint_operation) {
    // BD branch user: both forced
    $booking->booking_branch_id = $userBranch->id;
    $booking->fingerprint_branch_id = $userBranch->id;
} elseif ($userBranch) {
    // KSA branch user: booking_branch forced
    $booking->booking_branch_id = $userBranch->id;
    $booking->fingerprint_branch_id = $validated['fingerprint_branch_id'];
} else {
    // No branch: both from input
    $booking->booking_branch_id = $validated['booking_branch_id'];
    $booking->fingerprint_branch_id = $validated['fingerprint_branch_id'];
}
```

**`resolveBookingBranch()` → renamed to `resolveBookingBranch()`:**
- If admin role and `booking_branch_id` provided → use it
- Otherwise → use `auth()->user()->branch_id`

**`isBranchScoped()` → use `booking_branch_id`** (unchanged logic, just column rename).

**`ensureBranchAccess()` → use `booking_branch_id`** (unchanged logic).

**`update()` / `updateFingerprintLocation()` / `print()`** → update all `$booking->branch_id` → `$booking->booking_branch_id`, `$booking->office` → `$booking->fingerprintBranch`.

### `app/Http/Controllers/FingerprintController.php`

**`adminIndex()`:**
```php
// Replace office scoping:
if ($user->branch?->fingerprint_operation && !$user->hasRole('Super Admin') && !$user->hasRole('Co Admin')) {
    $query->whereHas('booking', fn($q) => $q->where('fingerprint_branch_id', $user->branch_id));
}
```

**`staffIndex()`:**
- `'booking.office'` → `'booking.fingerprintBranch'`
- `$booking->office?->name` → `$booking->fingerprintBranch?->name`

**`staffList()`:**
```php
$query = User::select('id', 'name')
    ->whereHas('roles', fn($q) => $q->where('name', 'Fingerprint Staff'));

if ($request->filled('fingerprint_branch_id')) {
    $query->where('branch_id', (int) $request->fingerprint_branch_id);
}

if ($user->branch?->fingerprint_operation && !$user->hasRole('Super Admin') && !$user->hasRole('Co Admin')) {
    $query->where('branch_id', $user->branch_id);
}
```

**API response keys:** `booking_branch_id`, `fingerprint_branch_id` (instead of `booking_branch_id`, `booking_office_id`).

### `app/Http/Controllers/FingerprintReportController.php`

**`index()`:**
```php
$fingerprintBranches = Branch::where('fingerprint_operation', true)->orderBy('name')->get(['id', 'name']);
```

**`data()` / `print()` / `details()`:**
- `$query->whereHas('booking', fn($q) => $q->where('fingerprint_branch_id', $officeId))`

**`resolveRescheduledBy()`:**
```php
$fingerprintAdmin = User::whereHas('roles', fn($q) => $q->where('name', 'Fingerprint Admin'))
    ->where('branch_id', $booking->fingerprint_branch_id)
    ->first();
```

**`getOfficeFilter()` → `getFingerprintBranchFilter()`:**
```php
protected function getFingerprintBranchFilter(): ?int
{
    $user = auth()->user();
    if ($user->branch?->fingerprint_operation && !$user->hasRole('Super Admin') && !$user->hasRole('Co Admin')) {
        return $user->branch_id;
    }
    return null;
}
```

### `app/Http/Controllers/FingerprintReportQuery.php`

**Eager loads:**
```php
'booking.fingerprintBranch'
// Removed: 'booking.office.users.roles'
// (not needed — resolveRescheduledBy does its own query)
```

**`applyBranch()` → `applyBookingBranch()`:**
```php
protected function applyBookingBranch(Request $request): static
{
    if ($branchId = $request->filled('booking_branch_id') ? $request->booking_branch_id : null) {
        $this->query->whereHas('booking', fn($q) => $q->where('booking_branch_id', $branchId));
    }
    return $this;
}
```

**`applyOffice()` → `applyFingerprintBranch()`:**
```php
protected function applyFingerprintBranch(Request $request): static
{
    if ($branchId = $request->filled('fingerprint_branch_id') ? $request->fingerprint_branch_id : null) {
        $this->query->whereHas('booking', fn($q) => $q->where('fingerprint_branch_id', $branchId));
    }
    return $this;
}
```

**`applyOfficeFilter()` → `applyFingerprintBranchFilter()`:**
```php
public function applyFingerprintBranchFilter(?int $branchId): static
{
    if ($branchId) {
        $this->query->whereHas('booking', fn($q) => $q->where('fingerprint_branch_id', $branchId));
    }
    return $this;
}
```

### `app/Http/Controllers/UserController.php`

**Validation logic:**
```php
if (Str::contains($roleName, 'fingerprint')) {
    $request->validate(['branch_id' => 'required|exists:branches,id']);
    $branch = Branch::find($request->branch_id);
    if (!$branch || !$branch->fingerprint_operation) {
        return back()->withErrors(['branch_id' => 'Fingerprint roles require a branch with fingerprint operations enabled.']);
    }
} else {
    $request->validate(['branch_id' => 'nullable|exists:branches,id']);
}
```

**`create()` / `edit()`:**
```php
$allBranches = Branch::orderBy('name')->get();
$fingerprintBranches = Branch::where('fingerprint_operation', true)->orderBy('name')->get();
```

**`index()`:**
```php
$users = User::with('branch')->orderBy('name')->paginate(10)->withQueryString();
```

### `app/Http/Controllers/OfficeController.php`

**DELETE** entire file.

---

## Phase 6: Route Changes

### `routes/web.php`

- Remove line: `Route::resource('offices', OfficeController::class)->middleware('role:Super Admin,Co Admin');`

---

## Phase 7: Service Changes

### `app/Services/BookingService.php`

- `generateInvoiceId(int $branchId)` — parameter name stays, caller passes `$booking->booking_branch_id`
- `getFingerprintCharge()` — no change (compares `strtolower($location) === 'office'` against FingerprintLocation enum; column name `fingerprint_location` on bookings is unchanged)
- `processBookingWithPassengers()` — update field name `office_id` → `fingerprint_branch_id`

### `app/Services/PaymentService.php`

No change. Receives `branch_id` from caller (which will pass `$booking->booking_branch_id`).

### `app/Services/InvoiceService.php`

No change. Receives `branch_id` from caller.

---

## Phase 8: View Changes

### `resources/views/partials/nav.blade.php`

**Fingerprint report access:**
```php
$canAccessFingerprintReport = auth()->user()->roles->pluck('name')->intersect(['Super Admin', 'Co Admin', 'Auditor'])->isNotEmpty()
    || (auth()->user()->hasRole('Fingerprint Admin') && auth()->user()->branch?->fingerprint_operation);
```

**Navigation links (desktop + mobile):**
```blade
{{-- Before --}}
<a href="{{ route('branches.index') }}">Branches (KSA)</a>
<a href="{{ route('offices.index') }}">Branches (BD)</a>

{{-- After --}}
<a href="{{ route('branches.index') }}">Branches</a>
```

### `resources/views/branches/index.blade.php`

- Title: "Branches" (was "Branches(KSA)")
- Add `<th>Location</th>` column after Contacts
- Add `<td>{{ $branch->location }}</td>` in row
- Empty state: "No branches found."

### `resources/views/branches/create.blade.php`

- Title / labels: "Branch" (was "Branch(KSA)")
- Add Location select dropdown (KSA / BD)
- Hidden `fingerprint_operation` field auto-set via JS:
  - Location = KSA → `fingerprint_operation = false`
  - Location = BD → `fingerprint_operation = true`

### `resources/views/branches/edit.blade.php`

Same changes as create, with selected value for location.

### `resources/views/bookings/create.blade.php`

**Booking Branch dropdown:**
```blade
<select name="booking_branch_id" x-show="showBookingBranch" ...>
    <option value="">Select Booking Branch</option>
    @foreach($bookingBranches as $branch)
        <option value="{{ $branch->id }}">{{ $branch->name }} ({{ $branch->location }})</option>
    @endforeach
</select>
```

**Fingerprint Branch dropdown:**
```blade
<select name="fingerprint_branch_id" x-show="showFingerprintBranch" ...>
    <option value="">Select Fingerprint Branch</option>
    @foreach($fingerprintBranches as $branch)
        <option value="{{ $branch->id }}">{{ $branch->name }}</option>
    @endforeach
</select>
```

### `resources/views/bookings/edit.blade.php`

Same as create.

### `resources/views/bookings/index.blade.php`

- `$booking->office->name ?? '—'` → `$booking->fingerprintBranch->name ?? '—'`
- Column header: "Branch(BD)" → "Fingerprint Branch"

### `resources/views/bookings/invoice-print.blade.php`

```php
$booking->office?->address ?? '-'  →  $booking->fingerprintBranch?->address ?? '-'
$booking->office->name ?? '-'      →  $booking->fingerprintBranch->name ?? '-'
```

### `resources/views/fingerprints/admin.blade.php`

- `booking_office_id` → `fingerprint_branch_id` in Alpine data
- `loadStaffListsForOffices()` → `loadStaffListsForFingerprintBranches()`
- API URL param: `office_id` → `fingerprint_branch_id`

### `resources/views/fingerprints/staff.blade.php`

- Header: "Branch(BD)" → "Fingerprint Branch"
- `row.office` → `row.fingerprint_branch_name`
- `fingerprint_location` column stays unchanged (Home/Office enum on bookings table — not related to offices table)

### `resources/views/users/create.blade.php`

**Single branch dropdown (replaces dual branch/office sections):**
```blade
<div x-show="roleType !== 'other'" x-cloak>
    <label for="branch_id" class="block text-sm font-medium text-slate-700 mb-1">Branch</label>
    <select name="branch_id" id="branch_id"
        class="block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 sm:text-sm px-3 py-2 border">
        <option value="">-- Central/No Branch --</option>
        <template x-if="roleType === 'fingerprint'">
            @foreach($fingerprintBranches as $branch)
                <option value="{{ $branch->id }}">{{ $branch->name }}</option>
            @endforeach
        </template>
        <template x-if="roleType !== 'fingerprint'">
            @foreach($allBranches as $branch)
                <option value="{{ $branch->id }}">{{ $branch->name }} ({{ $branch->location }})</option>
            @endforeach
        </template>
    </select>
</div>
```

**Alpine.js init update:**
```js
init() {
    // ... existing init ...
    this.$watch('selectedRole', () => {
        const type = this.roleType;
        if (type === 'other' && this.$refs.branchSelect) this.$refs.branchSelect.value = '';
    });
}
```

### `resources/views/users/edit.blade.php`

Same changes as create.

### `resources/views/users/index.blade.php`

- Merge "Branch(KSA)" and "Branch(BD)" columns into single "Branch" column
- `{{ $user->branch?->name ?? '-' }}`

### `resources/views/reports/fingerprint/index.blade.php`

**"Branch" filter → "Booking Branch" filter:**
```blade
<select x-model="filters.booking_branch_id">
    <option value="">All Booking Branches</option>
    @foreach($branches as $branch)
    <option value="{{ $branch->id }}">{{ $branch->name }}</option>
    @endforeach
</select>
```

**"Office" filter → "Fingerprint Branch" filter:**
```blade
<select x-model="filters.fingerprint_branch_id">
    <option value="">All Fingerprint Branches</option>
    @foreach($fingerprintBranches as $branch)
    <option value="{{ $branch->id }}">{{ $branch->name }}</option>
    @endforeach
</select>
```

### `resources/views/reports/fingerprint/print.blade.php`

- `'Office' => request('office_id') ? \App\Models\Office::find(...)` → `'Fingerprint Branch' => request('fingerprint_branch_id') ? \App\Models\Branch::where('fingerprint_operation', true)->find(...)`

---

## Phase 9: Deletions

| File | Reason |
|------|--------|
| `app/Models/Office.php` | Model no longer needed |
| `app/Http/Controllers/OfficeController.php` | CRUD no longer needed |
| `resources/views/offices/index.blade.php` | View no longer needed |
| `resources/views/offices/create.blade.php` | View no longer needed |
| `resources/views/offices/edit.blade.php` | View no longer needed |

---

## Complete File Impact Summary

| # | File | Action |
|---|------|--------|
| 1 | `app/Enums/Location.php` | **NEW** |
| 2 | `database/migrations/xxxx_add_location_fingerprint_operation_to_branches.php` | **NEW** |
| 3 | `database/migrations/xxxx_rename_branch_office_columns_on_bookings.php` | **NEW** |
| 4 | `database/migrations/xxxx_drop_office_id_from_users.php` | **NEW** |
| 5 | `database/migrations/xxxx_drop_offices_table.php` | **NEW** |
| 6 | `database/seeders/MergeOfficesIntoBranchesSeeder.php` | **NEW** |
| 7 | `app/Models/Branch.php` | EDIT |
| 8 | `app/Models/Booking.php` | EDIT |
| 9 | `app/Models/User.php` | EDIT |
| 10 | `app/Models/Office.php` | **DELETE** |
| 11 | `app/Http/Controllers/BranchController.php` | EDIT |
| 12 | `app/Http/Controllers/BookingController.php` | EDIT |
| 13 | `app/Http/Controllers/FingerprintController.php` | EDIT |
| 14 | `app/Http/Controllers/FingerprintReportController.php` | EDIT |
| 15 | `app/Http/Controllers/UserController.php` | EDIT |
| 16 | `app/Http/Controllers/OfficeController.php` | **DELETE** |
| 17 | `app/Queries/FingerprintReportQuery.php` | EDIT |
| 18 | `app/Services/BookingService.php` | EDIT |
| 19 | `routes/web.php` | EDIT |
| 20 | `resources/views/partials/nav.blade.php` | EDIT |
| 21 | `resources/views/branches/index.blade.php` | EDIT |
| 22 | `resources/views/branches/create.blade.php` | EDIT |
| 23 | `resources/views/branches/edit.blade.php` | EDIT |
| 24 | `resources/views/bookings/create.blade.php` | EDIT |
| 25 | `resources/views/bookings/edit.blade.php` | EDIT |
| 26 | `resources/views/bookings/index.blade.php` | EDIT |
| 27 | `resources/views/bookings/invoice-print.blade.php` | EDIT |
| 28 | `resources/views/fingerprints/admin.blade.php` | EDIT |
| 29 | `resources/views/fingerprints/staff.blade.php` | EDIT |
| 30 | `resources/views/users/create.blade.php` | EDIT |
| 31 | `resources/views/users/edit.blade.php` | EDIT |
| 32 | `resources/views/users/index.blade.php` | EDIT |
| 33 | `resources/views/reports/fingerprint/index.blade.php` | EDIT |
| 34 | `resources/views/reports/fingerprint/print.blade.php` | EDIT |
| 35 | `resources/views/offices/index.blade.php` | **DELETE** |
| 36 | `resources/views/offices/create.blade.php` | **DELETE** |
| 37 | `resources/views/offices/edit.blade.php` | **DELETE** |

**Total: 6 new, 23 edit, 8 delete = 37 files**
