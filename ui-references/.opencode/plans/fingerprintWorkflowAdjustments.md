# Fingerprint Workflow Adjustments

## Overview

Two changes to the fingerprint status workflow:

1. **30-minute time limit** — Fingerprint Admin and Fingerprint Staff cannot change status from "Approved" to another status after 30 minutes. Super Admin and Co Admin are always exempt.
2. **Approval confirmation** — When all passengers' fingerprints reach "Done", auto-approval is removed. A confirmation dialog asks the user to confirm or defer ("Later").

---

## Requirement 1: 30-Minute Time Limit on Approved Status Changes

### How the 30 minutes is determined

The `FingerprintDetail` model has an existing `approvedLog()` relationship (`app/Models/FingerprintDetail.php:43-48`) that returns the latest `FingerprintDetailLog` where `action = 'status_updated'` and `new_values->status = 'approved'`. The `FingerprintDetailLog` stores `created_at` (no `updated_at`), so `$detail->approvedLog->created_at` gives the exact timestamp when the status was set to approved. The 30-minute check is `$approvedLog->created_at->diffInMinutes(now()) > 30`.

### 1a. Enforce time limit in `updateStatus()`

**File:** `app/Http/Controllers/FingerprintController.php` — `updateStatus()` method (line 422)

Insert after the home-location cost check (after line 454) and before the status transition logic (line 456):

```php
if ($fingerprintDetail->status === FingerprintStatus::APPROVED
    && ($user->hasRole('Fingerprint Admin') || $user->hasRole('Fingerprint Staff'))
) {
    $approvedLog = $fingerprintDetail->approvedLog;
    if ($approvedLog && $approvedLog->created_at->diffInMinutes(now()) > 30) {
        return response()->json([
            'success' => false,
            'message' => 'Cannot change status from Approved after 30 minutes. Only Super Admin or Co Admin can do this.',
        ], 422);
    }
}
```

### 1b. Enforce time limit in `hold()`

**File:** `app/Http/Controllers/FingerprintController.php` — `hold()` method (line 498)

Insert after the passenger hold/cancel validation (after line 518) and before the `$validated` block:

```php
if ($fingerprintDetail->status === FingerprintStatus::APPROVED
    && ($user->hasRole('Fingerprint Admin') || $user->hasRole('Fingerprint Staff'))
) {
    $approvedLog = $fingerprintDetail->approvedLog;
    if ($approvedLog && $approvedLog->created_at->diffInMinutes(now()) > 30) {
        return response()->json([
            'success' => false,
            'message' => 'Cannot hold an Approved fingerprint after 30 minutes. Only Super Admin or Co Admin can do this.',
        ], 422);
    }
}
```

### 1c. Pass `approved_at` to view data

**File:** `app/Http/Controllers/FingerprintController.php`

In both `adminIndex()` (add to row data array, around line 124) and `staffIndex()` (add to row data array, around line 276):

```php
'approved_at' => $detail?->approvedLog?->created_at?->toISOString(),
```

### 1d. Frontend (Admin) — Disable dropdown when window expired

**File:** `resources/views/fingerprints/admin.blade.php`

1. Add a helper function in the Alpine `fingerprintAdmin()` component:

```javascript
isApprovedWindowExpired(row) {
    if (row.fingerprint_status !== 'approved' || !row.approved_at) return false;
    const approvedTime = new Date(row.approved_at);
    const now = new Date();
    return (now - approvedTime) > (30 * 60 * 1000);
},
```

2. On the status `<select>` (line 150), add `&& !isApprovedWindowExpired(row)` to the `x-if` condition so the dropdown is hidden when the window is expired. The read-only `<span>` fallback (line 159) will display instead.

### 1e. Frontend (Staff) — Same disable logic

**File:** `resources/views/fingerprints/staff.blade.php`

1. Add the same `isApprovedWindowExpired(row)` function in the `fingerprintStaff()` component.

2. On the status `<select>` (line 107), add `&& !isApprovedWindowExpired(row)` to the `x-show` condition. When the window is expired, the read-only `<span>` at line 116 displays instead.

### 1f. Tests

**File:** `tests/Feature/FingerprintStatusTimeLimitTest.php` (new)

Test cases:
- Fingerprint Admin **can** change status from Approved within 30 minutes
- Fingerprint Admin **cannot** change status from Approved after 30 minutes (422)
- Fingerprint Staff **cannot** change status from Approved after 30 minutes (422)
- Super Admin **can** change status from Approved after 30 minutes
- Co Admin **can** change status from Approved after 30 minutes
- `hold()` blocked for Fingerprint Admin/Staff after 30 minutes
- `hold()` allowed for Super/Co Admin after 30 minutes

---

## Requirement 2: Confirmation Dialog for Done → Approved Auto-Approval

### 2a. Remove auto-approval, return `all_done` flag

**File:** `app/Http/Controllers/FingerprintController.php` — `updateStatus()` (lines 478-486)

Delete the auto-approval block:

```php
// DELETE these lines:
if ($fingerprintDetail->fingerprint->fingerprintDetails()
    ->where('status', '!=', FingerprintStatus::DONE)
    ->doesntExist()
) {
    $fingerprintDetail->fingerprint->fingerprintDetails()
        ->where('status', FingerprintStatus::DONE)
        ->get()
        ->each(fn (FingerprintDetail $d) => $d->update(['status' => FingerprintStatus::APPROVED]));
}
```

Replace the return statement (lines 488-491) with:

```php
$allDone = $fingerprintDetail->fingerprint->fingerprintDetails()
    ->where('status', '!=', FingerprintStatus::DONE)
    ->doesntExist();

return response()->json([
    'success' => true,
    'message' => 'Status updated successfully',
    'all_done' => $allDone,
]);
```

### 2b. Allow direct "approved" selection when other details are "done"

**File:** `app/Http/Controllers/FingerprintController.php` — `updateStatus()` (lines 465-472)

Currently, selecting "Approved" when other details are "Done" forces the detail back to "Done". Replace with direct approval:

```php
// BEFORE:
if ($validated['status'] === 'approved') {
    $hasDone = $fingerprintDetail->fingerprint->fingerprintDetails()
        ->where('id', '!=', $fingerprintDetail->id)
        ->where('status', FingerprintStatus::DONE)
        ->exists();
    $targetStatus = $hasDone ? FingerprintStatus::DONE : FingerprintStatus::APPROVED;
    $fingerprintDetail->update(['status' => $targetStatus]);
}

// AFTER:
$fingerprintDetail->update(['status' => FingerprintStatus::APPROVED]);
```

This enables manual individual approval for the "Later" path.

### 2c. New `approveAll()` method

**File:** `app/Http/Controllers/FingerprintController.php` (new method)

```php
/**
 * Batch-approve all fingerprint details for a fingerprint
 * POST /api/fingerprints/{fingerprint}/approve-all
 */
public function approveAll(Fingerprint $fingerprint): JsonResponse
{
    $user = auth()->user();
    if (! $user->hasRole('Super Admin') && ! $user->hasRole('Co Admin') && ! $user->hasRole('Fingerprint Admin') && $fingerprint->assigned_staff_id !== $user->id) {
        abort(403);
    }

    if ($fingerprint->booking->is_cancelled) {
        return response()->json([
            'success' => false,
            'message' => 'Cannot approve for a cancelled booking',
        ], 422);
    }

    $fingerprint->fingerprintDetails()
        ->where('status', FingerprintStatus::DONE)
        ->each(fn (FingerprintDetail $d) => $d->update(['status' => FingerprintStatus::APPROVED]));

    return response()->json([
        'success' => true,
        'message' => 'All fingerprints approved successfully',
    ]);
}
```

### 2d. Register route

**File:** `routes/web.php` (near existing fingerprint API routes, around line 262)

```php
Route::post('/fingerprints/{fingerprint}/approve-all', [FingerprintController::class, 'approveAll']);
```

### 2e. Frontend (Admin) — Confirmation modal

**File:** `resources/views/fingerprints/admin.blade.php`

1. **Add Alpine state variables** (around line 261):

```javascript
showApprovalConfirmModal: false,
pendingApprovalFingerprintId: null,
```

2. **Modify `updateStatus()`** (line 486-510) — after receiving the response, check for `all_done`:

```javascript
async updateStatus(fingerprintDetailId, status) {
    // ... existing fetch logic ...
    const result = await response.json();
    if (result.success) {
        window.showToast('Status updated successfully', 'success');
        if (result.all_done) {
            const row = this.data.find(r => r.fingerprint_detail_id === fingerprintDetailId);
            this.pendingApprovalFingerprintId = row?.fingerprint_id;
            this.showApprovalConfirmModal = true;
        }
        await this.loadData();
    } else {
        window.showToast(result.message || 'Failed to update status', 'error');
    }
}
```

3. **Add confirmation modal HTML** (after the hold modal, before `@endsection`):

```html
<div x-show="showApprovalConfirmModal" x-cloak
     class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-2xl max-w-md w-full">
        <div class="flex justify-between items-center px-6 py-4 border-b border-slate-200 bg-slate-50 rounded-t-xl">
            <h3 class="text-lg font-bold text-slate-800">Approve All Fingerprints?</h3>
            <button @click="showApprovalConfirmModal = false" class="text-slate-500 hover:text-slate-700">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        <div class="p-6">
            <p class="text-slate-600">All passengers' fingerprints are marked as Done. Do you want to approve all now?</p>
        </div>
        <div class="flex justify-end gap-3 px-6 py-4 border-t border-slate-200 bg-slate-50 rounded-b-xl">
            <button @click="showApprovalConfirmModal = false"
                    class="px-4 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-300 rounded-lg hover:bg-slate-50">Later</button>
            <button @click="confirmApproveAll()"
                    class="px-4 py-2 text-sm font-medium text-white bg-green-600 rounded-lg hover:bg-green-700">Confirm Approve</button>
        </div>
    </div>
</div>
```

4. **Add `confirmApproveAll()` method:**

```javascript
async confirmApproveAll() {
    try {
        const response = await fetch(`/api/fingerprints/${this.pendingApprovalFingerprintId}/approve-all`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            },
        });
        const result = await response.json();
        if (result.success) {
            window.showToast('All fingerprints approved successfully', 'success');
            this.showApprovalConfirmModal = false;
            this.pendingApprovalFingerprintId = null;
            await this.loadData();
        } else {
            window.showToast(result.message || 'Failed to approve', 'error');
        }
    } catch (error) {
        console.error('Failed to approve all:', error);
        window.showToast('Failed to approve all fingerprints', 'error');
    }
},
```

### 2f. Frontend (Staff) — Same confirmation modal

**File:** `resources/views/fingerprints/staff.blade.php`

Apply the same pattern as admin:

1. Add `showApprovalConfirmModal` and `pendingApprovalFingerprintId` state variables.
2. Modify `updateStatus()` to check for `all_done` and show the modal.
3. Add the same confirmation modal HTML (using the staff view's existing `showToast()` method instead of `window.showToast()`).
4. Add `confirmApproveAll()` method using the staff view's existing fetch pattern.

### 2g. Tests

**File:** `tests/Feature/FingerprintApprovalConfirmationTest.php` (new)

Test cases:
- When all passengers reach "done" status, response includes `all_done: true`
- When not all passengers are "done", `all_done` is `false` or absent
- Auto-approval no longer happens automatically
- `POST /api/fingerprints/{fingerprint}/approve-all` batch-approves all "done" details
- `approve-all` returns 403 for unauthorized users
- `approve-all` returns 422 for cancelled bookings
- Direct "approved" selection is allowed even when other details are "done"

---

## Summary of All Changes

| # | File | Change Type | Description |
|---|------|-------------|-------------|
| 1 | `app/Http/Controllers/FingerprintController.php` | Modify | Add 30-min check in `updateStatus()` and `hold()`, add `approved_at` to API responses, remove auto-approval, return `all_done` flag, add `approveAll()` method, simplify "approved" selection |
| 2 | `routes/web.php` | Modify | Add `POST /fingerprints/{fingerprint}/approve-all` route |
| 3 | `resources/views/fingerprints/admin.blade.php` | Modify | Add approval confirmation modal, handle `all_done`, disable dropdown when 30-min window expired |
| 4 | `resources/views/fingerprints/staff.blade.php` | Modify | Same as admin view |
| 5 | `tests/Feature/FingerprintStatusTimeLimitTest.php` | New | Tests for 30-minute enforcement |
| 6 | `tests/Feature/FingerprintApprovalConfirmationTest.php` | New | Tests for approval confirmation flow |
