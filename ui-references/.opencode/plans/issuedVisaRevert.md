# Plan: Visa Revert (Issued → Submitted)

## Background

The visa status flow is `pending → submitted → issued` (with `cancelled` branching from `submitted`). There is currently no way to revert an `issued` visa. This feature adds a "Revert" action that undoes the issuance and restores the visa to `submitted` status.

## What Issuance Changes

When a visa is issued (`VisaSubmissionController::issue`, line 86), these fields are set on `visa_submissions`:

| Field | Set To |
|-------|--------|
| `visa_number` | User-provided visa number |
| `additional_cost` | User-provided additional cost |
| `final_cost` | `currentFinalCost + additionalCost` |
| `status` | `'issued'` |

The observer then triggers profit recalculation (visa profit becomes effective) and passenger status sync (becomes "Visa Issued" or "Ticket Issued").

## What Revert Must Do

Revert the visa from `issued` → `submitted`:

- Clear `visa_number` → `null` (issue-specific field)
- Clear `additional_cost` → `null` (issue-specific field)
- Recalculate `final_cost` = `final_cost - additional_cost`
- Set `status` → `'submitted'`

**Do NOT clear** `visa_agent_id`, `commission_agent_id`, `net_visa_cost`, `agent_commission` — these belong to the submit phase and must be preserved.

The existing `VisaSubmissionObserver` will automatically handle:
- Profit recalculation (visa profit becomes ineffective again)
- Passenger computed status sync (reverts from "Visa Issued" to "Visa Submitted")
- Audit logging

---

## Changes Required

### 1. Backend — `app/Http/VisaSubmissionController.php`

Add a new `revert()` method after `reSubmit()` (around line 248).

**Logic:**

```php
public function revert(Request $request, Booking $booking, Passenger $passenger)
{
    // Standard ownership check
    if ($passenger->booking_id !== $booking->id) {
        return 403 JSON error;
    }

    // Standard cancelled-passenger guard
    if ($passenger->isOnHold() || $passenger->isOnCancel() || $passenger->is_cancelled) {
        return 422 JSON error;
    }

    $visaSubmission = $passenger->visaSubmission;

    if (! $visaSubmission) {
        return 404 JSON error;
    }

    // Only issued visas can be reverted
    if ($visaSubmission->status?->value !== 'issued') {
        return 422 JSON error: 'Visa must be in issued status to revert';
    }

    // Recalculate final_cost without additional_cost
    $additionalCost = (float) ($visaSubmission->additional_cost ?? 0);
    $currentFinalCost = (float) ($visaSubmission->final_cost ?? 0);
    $finalCost = $currentFinalCost - $additionalCost;

    $visaSubmission->update([
        'visa_number' => null,
        'additional_cost' => null,
        'final_cost' => $finalCost ?: null,
        'status' => 'submitted',
    ]);

    return response()->json([
        'success' => true,
        'message' => 'Visa reverted successfully',
        'visa_submission' => $visaSubmission->fresh()->load(['visaAgent', 'commissionAgent', 'visaSellingPrice']),
    ]);
}
```

### 2. Routes — `routes/web.php`

Add a new POST route after the existing `visa-resubmit` route (after line 167):

```php
Route::post('/bookings/{booking}/passengers/{passenger}/visa-revert', [VisaSubmissionController::class, 'revert'])
    ->name('bookings.passengers.visa-revert')
    ->middleware('role:Super Admin,Co Admin,Visa Admin,Visa Staff');
```

### 3. Observer — `app/Observers/VisaSubmissionObserver.php`

Update `determineAction()` (line 71) to recognize the `issued → submitted` transition. Add before the `return 'edited'` fallback:

```php
if ($oldStatus === 'issued' && $newStatus === 'submitted') {
    return 'reverted';
}
```

### 4. Frontend — `resources/views/bookings/index.blade.php`

#### 4a. Add "Revert" Button (near lines 1252-1257)

After the existing "Edit" button block, add a new template with a "Revert" button:

```blade
<template x-if="!passengersTicketData[{{ $loop->index }}]?.is_cancelled && passengersTicketData[{{ $loop->index }}]?.status !== 'Hold' && passengersTicketData[{{ $loop->index }}]?.status !== 'Cancel'">
    <button x-show="passengersVisaData[{{ $loop->index }}]?.visa?.status === 'issued'"
            @click="openVisaRevertModal({{ $loop->index }})"
            class="text-xs bg-red-100 hover:bg-red-200 text-red-600 px-2 py-1 rounded font-medium transition">Revert</button>
</template>
```

#### 4b. Add "Revert" Confirmation Modal (after Visa Cancel Modal)

```html
{{-- Visa Revert Confirmation Modal --}}
<div x-show="visaRevertModalVisible" x-cloak class="fixed inset-0 z-[60] flex items-center justify-center" @keydown.escape="closeVisaRevertModal()">
    <div class="fixed inset-0 bg-black/50" @click="closeVisaRevertModal()"></div>
    <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-md mx-4 p-6 max-h-[90vh] overflow-y-auto">
        <h3 class="text-xl font-semibold text-slate-800 mb-4">Revert Visa</h3>
        <div class="space-y-4">
            <p class="text-sm text-slate-600">Are you sure you want to revert this issued visa?</p>
            <div class="bg-slate-50 rounded-lg p-4 space-y-2">
                <div class="flex justify-between text-sm">
                    <span class="text-slate-500">Visa Number:</span>
                    <span class="font-medium text-slate-700" x-text="passengersVisaData[editingVisaIndex]?.visa?.visa_number || '-'"></span>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-slate-500">Agent:</span>
                    <span class="font-medium text-slate-700" x-text="passengersVisaData[editingVisaIndex]?.visa?.agent || '-'"></span>
                </div>
            </div>
            <p class="text-xs text-red-600">The visa number and additional cost will be cleared. The visa will return to submitted status.</p>
        </div>
        <div class="flex gap-3 mt-6">
            <button type="button" @click="handleVisaRevert()" class="flex-1 px-6 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition font-medium">Revert</button>
            <button type="button" @click="closeVisaRevertModal()" class="flex-1 px-6 py-2 border border-slate-300 text-slate-700 rounded-lg hover:bg-slate-50 transition font-medium">Cancel</button>
        </div>
    </div>
</div>
```

#### 4c. Add Alpine.js State and Methods

In the Alpine.js data object (near the other visa modal states, around line 3590):

```javascript
visaRevertModalVisible: false,
```

Add methods (near the other visa methods):

```javascript
openVisaRevertModal(index) {
    this.editingVisaIndex = index;
    this.visaRevertModalVisible = true;
},

closeVisaRevertModal() {
    this.editingVisaIndex = null;
    this.visaRevertModalVisible = false;
},

handleVisaRevert() {
    if (this.editingVisaIndex === null) return;
    const data = this.passengersVisaData[this.editingVisaIndex];
    if (!data?.visa) return;

    fetch('/bookings/' + data.booking_id + '/passengers/' + data.id + '/visa-revert', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}'
        },
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            const sub = res.visa_submission;
            this.$nextTick(() => {
                if (data.visa) {
                    data.visa.visa_number = null;
                    data.visa.additional_cost = null;
                    data.visa.final_cost = sub.final_cost;
                    data.visa.status = 'submitted';
                }
            });
            this.closeVisaRevertModal();
            this.showToast('Visa reverted successfully');
        } else {
            alert(res.message || 'Revert failed');
        }
    })
    .catch(err => {
        console.error('Visa revert error:', err);
        alert('Failed to revert visa');
    });
},
```

---

## Files Summary

| # | File | Change |
|---|------|--------|
| 1 | `app/Http/Controllers/VisaSubmissionController.php` | Add `revert()` method |
| 2 | `routes/web.php` | Add `visa-revert` POST route |
| 3 | `app/Observers/VisaSubmissionObserver.php` | Add `reverted` action detection |
| 4 | `resources/views/bookings/index.blade.php` | Add Revert button, confirmation modal, Alpine.js methods |
| 5 | `tests/Feature/VisaRevertTest.php` | New test file for revert functionality |

## Testing Plan

### `tests/Feature/VisaRevertTest.php`

| Test | Description |
|------|-------------|
| `test_can_revert_issued_visa` | Revert succeeds: status=submitted, visa_number=null, additional_cost=null, final_cost recalculated |
| `test_revert_clears_visa_number` | Ensures visa_number is nulled out |
| `test_revert_clears_additional_cost` | Ensures additional_cost is nulled out |
| `test_revert_recalculates_final_cost` | final_cost = final_cost - additional_cost |
| `test_revert_preserves_agent_details` | visa_agent_id, commission_agent_id, net_visa_cost, agent_commission unchanged |
| `test_cannot_revert_submitted_visa` | Returns 422 |
| `test_cannot_revert_pending_visa` | Returns 422 |
| `test_cannot_revert_cancelled_visa` | Returns 422 |
| `test_revert_triggers_profit_recalculation` | Profit becomes ineffective after revert |
| `test_revert_syncs_passenger_status` | Passenger computed status reverts |

### Verification Steps

1. `php artisan test tests/Feature/VisaRevertTest.php`
2. `vendor/bin/pint`
3. `php artisan test` (full suite)
