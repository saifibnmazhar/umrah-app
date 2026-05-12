# Fingerprint Workflow Implementation Plan

## Overview

This document provides a complete implementation plan for the Fingerprint Workflow system in the BM Umrah booking application. The system manages fingerprint registration tasks for Umrah pilgrims with separate Admin and Staff interfaces.

---

## Current State Analysis

### Already Implemented (Migrations & Models)

| Table | Purpose | Key Fields |
|-------|---------|------------|
| `fingerprints` | One per booking | booking_id, deadline, cost, assigned_staff_id |
| `fingerprint_details` | One per passenger per fingerprint | fingerprint_id, passenger_id, status (none/processing/approved/cancelled) |
| `rescheduled_fingerprints` | Hold/reschedule records | fingerprint_detail_id, reason, next_date, occurrence, remarks |

### Existing Models
- `App\Models\Fingerprint` - belongsTo: Booking, User (assignedStaff); hasMany: FingerprintDetails
- `App\Models\FingerprintDetail` - belongsTo: Fingerprint, Passenger; hasMany: RescheduledFingerprints
- `App\Models\RescheduledFingerprint` - belongsTo: FingerprintDetail

### Existing Enums
- `App\Enums\FingerprintStatus`: none, processing, approved, cancelled
- `App\Enums\RescheduleReason`: rescheduled_by_client, rescheduled_by_bmt, nfc_problem, others

### Routes (Placeholder)
- `GET /fingerprints/admin` - points to placeholder view
- `GET /fingerprints/staff` - points to placeholder view

---

## Missing Pieces Identified

1. **Remarks field** - `rescheduled_fingerprints` table needs `remarks` column
2. **Controller & API** - No dynamic data endpoints
3. **Blade views** - Current views are placeholders with static HTML
4. **JavaScript** - No AJAX data loading and interactivity
5. **Partially Approved logic** - Computed display state for specific passengers

---

## Implementation Phases

### Phase 1: Database - Add Remarks Field (Pre-requisite)

#### Step 1.1: Add remarks column to rescheduled_fingerprints table

**Migration File:** `database/migrations/YYYY_MM_DD_add_remarks_to_rescheduled_fingerprints_table.php`

```php
Schema::table('rescheduled_fingerprints', function (Blueprint $table) {
    $table->text('remarks')->nullable()->after('other_reason');
});
```

**Update Model:** `app/Models/RescheduledFingerprint.php`

Add `'remarks'` to `$fillable` array.

**Dependencies:** None

**Priority:** HIGH - Required before Phase 6

---

### Phase 2: Backend - Controller & API Endpoints

#### Step 2.1: Create FingerprintController

**File:** `app/Http/Controllers/FingerprintController.php` (NEW)

```php
<?php

namespace App\Http\Controllers;

use App\Models\Fingerprint;
use App\Models\FingerprintDetail;
use App\Models\RescheduledFingerprint;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class FingerprintController extends Controller
{
    /**
     * Get all fingerprint tasks for Admin view
     * GET /api/fingerprints/admin
     */
    public function adminIndex(Request $request): JsonResponse
    {
        $query = Fingerprint::with([
            'booking.customer',
            'booking.district',
            'booking.passengers',
            'fingerprintDetails.passenger',
            'assignedStaff'
        ])->orderBy('created_at', 'desc');

        // Apply filters if needed
        if ($request->has('division') && $request->division) {
            $query->whereHas('booking.district', function ($q) use ($request) {
                $q->where('division', $request->division);
            });
        }

        if ($request->has('district') && $request->district) {
            $query->where('booking.district_id', $request->district);
        }

        $fingerprints = $query->get();

        $data = $fingerprints->map(function ($fingerprint) {
            $booking = $fingerprint->booking;
            $passengers = $booking->passengers;

            return $passengers->map(function ($passenger) use ($fingerprint, $booking, $passengers) {
                $detail = $fingerprint->fingerprintDetails()
                    ->where('passenger_id', $passenger->id)
                    ->first();

                // Compute Partially Approved display logic
                $statusDisplay = $this->computePartiallyApprovedStatus($detail, $passengers);

                return [
                    'fingerprint_id' => $fingerprint->id,
                    'fingerprint_detail_id' => $detail?->id,
                    'invoice_id' => $booking->invoice_id,
                    'booking_date' => $booking->created_at->format('Y-m-d'),
                    'customer_name' => $booking->customer->name,
                    'pax_qty' => $booking->pax_qty,
                    'customer_mobile' => $booking->customer->mobile_no,
                    'passenger_mobile' => $passenger->mobile_no,
                    'district' => $booking->district->name ?? '-',
                    'deadline' => $fingerprint->deadline?->format('Y-m-d'),
                    'cost' => $fingerprint->cost,
                    'assigned_staff_id' => $fingerprint->assigned_staff_id,
                    'assigned_staff_name' => $fingerprint->assignedStaff->name ?? null,
                    'passenger_name' => $passenger->first_name . ' ' . $passenger->last_name,
                    'fingerprint_status' => $detail?->status?->value ?? 'none',
                    'fingerprint_status_display' => $statusDisplay,
                    'flight_date_from' => $passenger->flight_date_from?->format('Y-m-d'),
                    'flight_date_to' => $passenger->flight_date_to?->format('Y-m-d'),
                ];
            });
        })->flatten();

        return response()->json(['data' => $data]);
    }

    /**
     * Get fingerprint tasks for Staff view
     * GET /api/fingerprints/staff
     */
    public function staffIndex(Request $request): JsonResponse
    {
        // Get all fingerprints (staff can see all - filter by assigned_staff_id on frontend if needed)
        $query = Fingerprint::with([
            'booking.customer',
            'booking.district',
            'booking.passengers',
            'fingerprintDetails.passenger'
        ])->orderBy('created_at', 'desc');

        $fingerprints = $query->get();

        $data = $fingerprints->map(function ($fingerprint) {
            $booking = $fingerprint->booking;
            $passengers = $booking->passengers;

            return $passengers->map(function ($passenger) use ($fingerprint, $booking, $passengers) {
                $detail = $fingerprint->fingerprintDetails()
                    ->where('passenger_id', $passenger->id)
                    ->first();

                // Compute Partially Approved display logic
                $statusDisplay = $this->computePartiallyApprovedStatus($detail, $passengers);

                return [
                    'fingerprint_id' => $fingerprint->id,
                    'fingerprint_detail_id' => $detail?->id,
                    'invoice_id' => $booking->invoice_id,
                    'customer_name' => $booking->customer->name,
                    'pax_qty' => $booking->pax_qty,
                    'customer_mobile' => $booking->customer->mobile_no,
                    'passenger_mobile' => $passenger->mobile_no,
                    'office' => $booking->fingerprint_office ?? '-',
                    'district' => $booking->district->name ?? '-',
                    'deadline' => $fingerprint->deadline?->format('Y-m-d'),
                    'passenger_name' => $passenger->first_name . ' ' . $passenger->last_name,
                    'cost' => $fingerprint->cost,
                    'fingerprint_status' => $detail?->status?->value ?? 'none',
                    'fingerprint_status_display' => $statusDisplay,
                ];
            });
        })->flatten();

        return response()->json(['data' => $data]);
    }

    /**
     * Compute Partially Approved display status
     * Only shown for passengers who have 'approved' status but not all passengers are approved
     */
    private function computePartiallyApprovedStatus($detail, $passengers): string
    {
        if (!$detail || $detail->status->value !== 'approved') {
            return $detail?->status?->value ?? 'none';
        }

        // Check if ALL passengers in this booking have 'approved' status
        $fingerprint = $detail->fingerprint;
        $allDetails = $fingerprint->fingerprintDetails;

        $allApproved = $allDetails->every(function ($d) {
            return $d->status->value === 'approved';
        });

        if ($allApproved) {
            return 'approved';
        }

        // Not all approved - show "Partially Approved" for approved passengers
        return 'Partially Approved';
    }

    /**
     * Assign staff to fingerprint task
     * PUT /api/fingerprints/{fingerprint}/staff
     */
    public function assignStaff(Request $request, Fingerprint $fingerprint): JsonResponse
    {
        $validated = $request->validate([
            'assigned_staff_id' => 'required|exists:users,id',
        ]);

        $fingerprint->update(['assigned_staff_id' => $validated['assigned_staff_id']]);

        return response()->json([
            'success' => true,
            'message' => 'Staff assigned successfully'
        ]);
    }

    /**
     * Update fingerprint cost
     * PUT /api/fingerprints/{fingerprint}/cost
     */
    public function updateCost(Request $request, Fingerprint $fingerprint): JsonResponse
    {
        $validated = $request->validate([
            'cost' => 'required|numeric|min:0',
        ]);

        $fingerprint->update(['cost' => $validated['cost']]);

        return response()->json([
            'success' => true,
            'message' => 'Cost updated successfully'
        ]);
    }

    /**
     * Update fingerprint status for a passenger
     * PUT /api/fingerprints/detail/{fingerprintDetail}/status
     */
    public function updateStatus(Request $request, FingerprintDetail $fingerprintDetail): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:none,processing,approved,cancelled',
        ]);

        $fingerprintDetail->update(['status' => $validated['status']]);

        return response()->json([
            'success' => true,
            'message' => 'Status updated successfully'
        ]);
    }

    /**
     * Create hold/reschedule record
     * POST /api/fingerprints/detail/{fingerprintDetail}/hold
     */
    public function hold(Request $request, FingerprintDetail $fingerprintDetail): JsonResponse
    {
        $validated = $request->validate([
            'reason' => 'required|in:rescheduled_by_client,rescheduled_by_bmt,nfc_problem,others',
            'other_reason' => 'nullable|string|max:500',
            'next_date' => 'required|date|after_or_equal:today',
            'remarks' => 'nullable|string|max:1000',
        ]);

        // Get occurrence count
        $occurrence = $fingerprintDetail->rescheduledFingerprints()->count() + 1;

        // Create reschedule record
        RescheduledFingerprint::create([
            'fingerprint_detail_id' => $fingerprintDetail->id,
            'reason' => $validated['reason'],
            'other_reason' => $validated['other_reason'] ?? null,
            'next_date' => $validated['next_date'],
            'occurrence' => $occurrence,
            'remarks' => $validated['remarks'] ?? null,
        ]);

        // Update status to processing
        $fingerprintDetail->update(['status' => 'processing']);

        return response()->json([
            'success' => true,
            'message' => 'Hold created successfully'
        ]);
    }

    /**
     * Get staff list for dropdown
     * GET /api/fingerprints/staff-list
     */
    public function staffList(): JsonResponse
    {
        $users = \App\Models\User::select('id', 'name')->orderBy('name')->get();
        return response()->json(['data' => $users]);
    }
}
```

**Dependencies:** Phase 1 (for remarks field)

**Validation:**
- `assignStaff`: assigned_staff_id must exist in users table
- `updateCost`: cost >= 0
- `updateStatus`: status must be valid enum value
- `hold`: reason required, next_date must be today or future

**Priority:** HIGH

---

#### Step 2.2: Update Routes

**File:** `routes/web.php`

Replace or add API routes:

```php
// Fingerprint API Routes
Route::prefix('api/fingerprints')->group(function () {
    Route::get('/admin', [FingerprintController::class, 'adminIndex']);
    Route::get('/staff', [FingerprintController::class, 'staffIndex']);
    Route::get('/staff-list', [FingerprintController::class, 'staffList']);
    Route::put('/{fingerprint}/staff', [FingerprintController::class, 'assignStaff']);
    Route::put('/{fingerprint}/cost', [FingerprintController::class, 'updateCost']);
    Route::put('/detail/{fingerprintDetail}/status', [FingerprintController::class, 'updateStatus']);
    Route::post('/detail/{fingerprintDetail}/hold', [FingerprintController::class, 'hold']);
});
```

**Dependencies:** Step 2.1

**Priority:** HIGH

---

### Phase 3: Admin Page Implementation

#### Step 3.1: Update Admin Blade View

**File:** `resources/views/fingerprints/admin.blade.php`

Replace content with:

```blade
@extends('layouts.app')

@section('title', 'Fingerprint Admin')

@section('content')
<div class="max-w-7xl mx-auto pt-6" x-data="fingerprintAdmin()">
    <h1 class="text-2xl font-bold text-slate-800 mb-6">Fingerprint Admin</h1>

    <div class="bg-white rounded-lg shadow-md mb-6">
        <div class="p-4 border-b border-slate-200">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Division</label>
                    <select x-model="filters.division" @change="loadData()" class="w-full px-3 py-2 border border-slate-300 rounded-md">
                        <option value="">All Divisions</option>
                        @foreach($divisions ?? [] as $division)
                        <option value="{{ $division }}">{{ $division }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">District</label>
                    <select x-model="filters.district" @change="loadData()" class="w-full px-3 py-2 border border-slate-300 rounded-md">
                        <option value="">All Districts</option>
                        @foreach($districts ?? [] as $district)
                        <option value="{{ $district->id }}">{{ $district->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex items-end">
                    <button @click="loadData()" class="px-4 py-2 bg-slate-700 text-white rounded-md hover:bg-slate-800">
                        Filter
                    </button>
                </div>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead class="bg-slate-100 text-slate-700 uppercase text-xs">
                    <tr>
                        <th class="px-3 py-3">Invoice ID</th>
                        <th class="px-3 py-3">Booking Date</th>
                        <th class="px-3 py-3">Customer Name</th>
                        <th class="px-3 py-3">PAX</th>
                        <th class="px-3 py-3">Mobile</th>
                        <th class="px-3 py-3">District</th>
                        <th class="px-3 py-3">Deadline</th>
                        <th class="px-3 py-3 text-right">Cost (SAR)</th>
                        <th class="px-3 py-3">Assign Staff</th>
                        <th class="px-3 py-3">Passenger</th>
                        <th class="px-3 py-3">Fingerprint Status</th>
                        <th class="px-3 py-3">Required Flight</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200" id="tableBody">
                    <template x-if="loading">
                        <tr>
                            <td colspan="12" class="px-3 py-8 text-center text-slate-500">Loading...</td>
                        </tr>
                    </template>
                    <template x-if="!loading && data.length === 0">
                        <tr>
                            <td colspan="12" class="px-3 py-8 text-center text-slate-500">No fingerprint tasks found</td>
                        </tr>
                    </template>
                    <template x-for="(row, index) in data" :key="row.fingerprint_detail_id">
                        <tr class="hover:bg-slate-50"
                            :class="index % 2 === 0 ? 'border-l-4 border-l-blue-500' : 'border-l-4 border-l-orange-500'"
                            :style="index === data.length - 1 || (index < data.length - 1 && data[index + 1]?.invoice_id !== row.invoice_id) ? 'border-bottom: 2px solid #94a3b8;' : ''">
                            <td class="px-3 py-2 font-medium text-slate-800" x-text="row.invoice_id"></td>
                            <td class="px-3 py-2 text-slate-600" x-text="row.booking_date"></td>
                            <td class="px-3 py-2 text-slate-600" x-text="row.customer_name"></td>
                            <td class="px-3 py-2 text-slate-600" x-text="row.pax_qty"></td>
                            <td class="px-3 py-2 text-slate-600 whitespace-pre-line" x-text="row.customer_mobile + '\n' + row.passenger_mobile"></td>
                            <td class="px-3 py-2 text-slate-600" x-text="row.district"></td>
                            <td class="px-3 py-2 text-slate-600" x-text="row.deadline"></td>
                            <td class="px-3 py-2 text-right text-slate-800 font-medium" x-text="row.cost + ' SAR'"></td>
                            <td class="px-3 py-2">
                                <select @change="assignStaff(row.fingerprint_id, $event.target.value)"
                                        class="text-xs border border-slate-300 rounded px-2 py-1 bg-white">
                                    <option value="">Select Staff</option>
                                    <template x-for="staff in staffList" :key="staff.id">
                                        <option :value="staff.id" :selected="staff.id == row.assigned_staff_id" x-text="staff.name"></option>
                                    </template>
                                </select>
                            </td>
                            <td class="px-3 py-2 text-slate-600" x-text="row.passenger_name"></td>
                            <td class="px-3 py-2">
                                <span class="px-2 py-1 rounded-full text-xs font-medium"
                                      :class="getStatusClass(row.fingerprint_status_display)"
                                      x-text="row.fingerprint_status_display"></span>
                            </td>
                            <td class="px-3 py-2 text-slate-600" x-text="row.flight_date_from + ' - ' + row.flight_date_to"></td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
```

**Columns Mapping:**

| # | Column | Data Source | Notes |
|---|--------|-------------|-------|
| 1 | Invoice ID | `row.invoice_id` | - |
| 2 | Booking Date | `row.booking_date` | Format: Y-m-d |
| 3 | Customer Name | `row.customer_name` | - |
| 4 | PAX Qty | `row.pax_qty` | - |
| 5 | Mobile | `row.customer_mobile` + `\n` + `row.passenger_mobile` | Two lines |
| 6 | District | `row.district` | - |
| 7 | Fingerprint Deadline | `row.deadline` | - |
| 8 | Fingerprint Cost | `row.cost` | Display as "X SAR" |
| 9 | Assign Staff | Dropdown | Load users, on change call API |
| 10 | Passenger | `row.passenger_name` | first_name + last_name |
| 11 | Fingerprint Status | `row.fingerprint_status_display` | Read-only, computed |
| 12 | Required Flight | `row.flight_date_from` + " - " + `row.flight_date_to` | Date range |

**UI Behavior:**
- Staff dropdown loads users from `/api/fingerprints/staff-list`
- On staff change → call `PUT /api/fingerprints/{id}/staff`
- Status displayed as colored badge (read-only)
- Alternating blue/orange left border per invoice
- Last row of each invoice group has thicker bottom border

**Dependencies:** Phase 2

**Priority:** HIGH

---

### Phase 4: Staff Page Implementation

#### Step 4.1: Update Staff Blade View

**File:** `resources/views/fingerprints/staff.blade.php`

Replace content with:

```blade
@extends('layouts.app')

@section('title', 'Fingerprint Staff')

@section('content')
<div class="max-w-7xl mx-auto pt-6" x-data="fingerprintStaff()">
    <h1 class="text-2xl font-bold text-slate-800 mb-6">Fingerprint Staff</h1>

    <div class="bg-white rounded-lg shadow-md">
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead class="bg-slate-100 text-slate-700 uppercase text-xs">
                    <tr>
                        <th class="px-3 py-3">Invoice ID</th>
                        <th class="px-3 py-3">Customer Name</th>
                        <th class="px-3 py-3">PAX</th>
                        <th class="px-3 py-3">Mobile</th>
                        <th class="px-3 py-3">Office</th>
                        <th class="px-3 py-3">District</th>
                        <th class="px-3 py-3">Deadline</th>
                        <th class="px-3 py-3">Passenger</th>
                        <th class="px-3 py-3 text-right">Cost (SAR)</th>
                        <th class="px-3 py-3">Fingerprint Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200" id="tableBody">
                    <template x-if="loading">
                        <tr>
                            <td colspan="10" class="px-3 py-8 text-center text-slate-500">Loading...</td>
                        </tr>
                    </template>
                    <template x-if="!loading && data.length === 0">
                        <tr>
                            <td colspan="10" class="px-3 py-8 text-center text-slate-500">No fingerprint tasks found</td>
                        </tr>
                    </template>
                    <template x-for="(row, index) in data" :key="row.fingerprint_detail_id">
                        <tr class="hover:bg-slate-50"
                            :class="index % 2 !== 0 ? 'bg-slate-50' : 'bg-white'"
                            :style="index === data.length - 1 || (index < data.length - 1 && data[index + 1]?.invoice_id !== row.invoice_id) ? 'border-bottom: 2px solid #94a3b8;' : ''">
                            <td class="px-3 py-2 font-medium text-slate-800" x-text="row.invoice_id"></td>
                            <td class="px-3 py-2 text-slate-600" x-text="row.customer_name"></td>
                            <td class="px-3 py-2 text-slate-600" x-text="row.pax_qty"></td>
                            <td class="px-3 py-2 text-slate-600 whitespace-pre-line" x-text="row.customer_mobile + '\n' + row.passenger_mobile"></td>
                            <td class="px-3 py-2 text-slate-600" x-text="row.office"></td>
                            <td class="px-3 py-2 text-slate-600" x-text="row.district"></td>
                            <td class="px-3 py-2 text-slate-600" x-text="row.deadline"></td>
                            <td class="px-3 py-2 text-slate-600" x-text="row.passenger_name"></td>
                            <td class="px-3 py-2">
                                <input type="number"
                                       :value="row.cost"
                                       @change="updateCost(row.fingerprint_id, $event.target.value)"
                                       class="w-20 text-right text-sm border border-slate-300 rounded px-2 py-1"
                                       min="0">
                            </td>
                            <td class="px-3 py-2">
                                <select @change="handleStatusChange(row.fingerprint_detail_id, $event.target.value)"
                                        class="text-xs border border-slate-300 rounded px-2 py-1 bg-white">
                                    <option value="none" :selected="row.fingerprint_status === 'none'">none</option>
                                    <option value="processing" :selected="row.fingerprint_status === 'processing'">processing</option>
                                    <option value="approved" :selected="row.fingerprint_status === 'approved'">approved</option>
                                    <option value="cancelled" :selected="row.fingerprint_status === 'cancelled'">cancelled</option>
                                    <option value="hold">Hold and ask for next fingerprint date?</option>
                                </select>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Hold Modal -->
    <div x-show="showHoldModal"
         class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4"
         style="display: none;">
        <div class="bg-white rounded-xl shadow-2xl max-w-md w-full">
            <div class="flex justify-between items-center px-6 py-4 border-b border-slate-200 bg-slate-50 rounded-t-xl">
                <h3 class="text-lg font-bold text-slate-800">Hold by BMT/Client</h3>
                <button @click="hideHoldModal()" class="text-slate-500 hover:text-slate-700">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <div class="p-6 space-y-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Reason</label>
                    <select x-model="holdForm.reason" class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 bg-white">
                        <option value="">Select Reason</option>
                        <option value="rescheduled_by_client">Reschedule by Client</option>
                        <option value="rescheduled_by_bmt">Reschedule by BMT</option>
                        <option value="nfc_problem">NFC Problem</option>
                        <option value="others">Others</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Next Finger Date</label>
                    <input type="date" x-model="holdForm.next_date" class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Remarks</label>
                    <input type="text" x-model="holdForm.remarks" class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2" placeholder="Enter remarks">
                </div>
            </div>
            <div class="flex justify-end gap-3 px-6 py-4 border-t border-slate-200 bg-slate-50 rounded-b-xl">
                <button @click="hideHoldModal()" class="px-4 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-300 rounded-lg hover:bg-slate-50">Cancel</button>
                <button @click="saveHold()" class="px-4 py-2 text-sm font-medium text-white bg-slate-700 rounded-lg hover:bg-slate-800">Save</button>
            </div>
        </div>
    </div>
</div>
@endsection
```

**Columns Mapping:**

| # | Column | Data Source | Notes |
|---|--------|-------------|-------|
| 1 | Invoice ID | `row.invoice_id` | - |
| 2 | Customer Name | `row.customer_name` | - |
| 3 | PAX Qty | `row.pax_qty` | - |
| 4 | Mobile | `row.customer_mobile` + `\n` + `row.passenger_mobile` | Two lines |
| 5 | Office | `row.office` | fingerprint_office |
| 6 | District | `row.district` | - |
| 7 | Deadline | `row.deadline` | - |
| 8 | Passenger | `row.passenger_name` | - |
| 9 | Fingerprint Cost | `row.cost` | Editable input |
| 10 | Fingerprint Status | Dropdown | Options + Hold trigger |

**Dropdown Options:**
- none
- processing
- approved
- cancelled
- **"Hold and ask for next fingerprint date?"** (triggers hold modal)

**Hold Modal Behavior:**
- When "Hold and ask..." selected → set `showHoldModal = true` and store current `fingerprint_detail_id`
- On Save: call `POST /api/fingerprints/detail/{id}/hold`
- Set status to 'processing' after hold created

**Status Display Logic:**
- Show computed `fingerprint_status_display` value (includes Partially Approved)

**Dependencies:** Phase 2

**Priority:** HIGH

---

### Phase 5: JavaScript Implementation

#### Step 5.1: Create Fingerprint JavaScript Module

**File:** `resources/js/fingerprint.js` (NEW)

```javascript
function fingerprintAdmin() {
    return {
        data: [],
        loading: true,
        staffList: [],
        filters: {
            division: '',
            district: '',
        },

        async init() {
            await this.loadStaffList();
            await this.loadData();
        },

        async loadStaffList() {
            try {
                const response = await fetch('/api/fingerprints/staff-list');
                const result = await response.json();
                this.staffList = result.data || [];
            } catch (error) {
                console.error('Failed to load staff list:', error);
                this.staffList = [];
            }
        },

        async loadData() {
            this.loading = true;
            try {
                const params = new URLSearchParams(this.filters);
                const response = await fetch(`/api/fingerprints/admin?${params}`);
                const result = await response.json();
                this.data = result.data || [];
            } catch (error) {
                console.error('Failed to load fingerprint data:', error);
                this.data = [];
                this.showToast('Failed to load data', 'error');
            } finally {
                this.loading = false;
            }
        },

        async assignStaff(fingerprintId, staffId) {
            try {
                const response = await fetch(`/api/fingerprints/${fingerprintId}/staff`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({ assigned_staff_id: staffId }),
                });
                const result = await response.json();
                if (result.success) {
                    this.showToast('Staff assigned successfully');
                    // Update local data
                    const row = this.data.find(r => r.fingerprint_id === fingerprintId);
                    if (row) {
                        row.assigned_staff_id = staffId;
                        const staff = this.staffList.find(s => s.id == staffId);
                        row.assigned_staff_name = staff?.name;
                    }
                }
            } catch (error) {
                console.error('Failed to assign staff:', error);
                this.showToast('Failed to assign staff', 'error');
                await this.loadData();
            }
        },

        getStatusClass(status) {
            const classes = {
                'none': 'bg-gray-100 text-gray-800',
                'processing': 'bg-yellow-100 text-yellow-800',
                'approved': 'bg-green-100 text-green-800',
                'Partially Approved': 'bg-green-100 text-green-800',
                'cancelled': 'bg-red-100 text-red-800',
            };
            return classes[status] || 'bg-gray-100 text-gray-800';
        },

        showToast(message, type = 'success') {
            // Implement toast notification
            const toast = document.createElement('div');
            toast.className = `fixed top-4 right-4 px-4 py-2 rounded shadow-lg ${type === 'error' ? 'bg-red-500 text-white' : 'bg-green-500 text-white'}`;
            toast.textContent = message;
            document.body.appendChild(toast);
            setTimeout(() => toast.remove(), 3000);
        },
    };
}

function fingerprintStaff() {
    return {
        data: [],
        loading: true,
        showHoldModal: false,
        currentFingerprintDetailId: null,
        holdForm: {
            reason: '',
            next_date: '',
            remarks: '',
        },

        async init() {
            await this.loadData();
        },

        async loadData() {
            this.loading = true;
            try {
                const response = await fetch('/api/fingerprints/staff');
                const result = await response.json();
                this.data = result.data || [];
            } catch (error) {
                console.error('Failed to load fingerprint data:', error);
                this.data = [];
                this.showToast('Failed to load data', 'error');
            } finally {
                this.loading = false;
            }
        },

        handleStatusChange(fingerprintDetailId, value) {
            if (value === 'hold') {
                this.currentFingerprintDetailId = fingerprintDetailId;
                this.showHoldModal = true;
                // Reset form
                this.holdForm = {
                    reason: '',
                    next_date: '',
                    remarks: '',
                };
            } else {
                this.updateStatus(fingerprintDetailId, value);
            }
        },

        async updateStatus(fingerprintDetailId, status) {
            try {
                const response = await fetch(`/api/fingerprints/detail/${fingerprintDetailId}/status`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({ status }),
                });
                const result = await response.json();
                if (result.success) {
                    this.showToast('Status updated successfully');
                    // Update local data
                    const row = this.data.find(r => r.fingerprint_detail_id === fingerprintDetailId);
                    if (row) {
                        row.fingerprint_status = status;
                        // Recompute display
                        row.fingerprint_status_display = this.computeStatusDisplay(status, row.invoice_id);
                    }
                }
            } catch (error) {
                console.error('Failed to update status:', error);
                this.showToast('Failed to update status', 'error');
                await this.loadData();
            }
        },

        async updateCost(fingerprintId, cost) {
            try {
                const response = await fetch(`/api/fingerprints/${fingerprintId}/cost`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({ cost: parseFloat(cost) }),
                });
                const result = await response.json();
                if (result.success) {
                    this.showToast('Cost updated successfully');
                    // Update local data
                    this.data.forEach(row => {
                        if (row.fingerprint_id === fingerprintId) {
                            row.cost = parseFloat(cost);
                        }
                    });
                }
            } catch (error) {
                console.error('Failed to update cost:', error);
                this.showToast('Failed to update cost', 'error');
                await this.loadData();
            }
        },

        hideHoldModal() {
            this.showHoldModal = false;
            this.currentFingerprintDetailId = null;
        },

        async saveHold() {
            if (!this.holdForm.reason) {
                this.showToast('Please select a reason', 'error');
                return;
            }
            if (!this.holdForm.next_date) {
                this.showToast('Please select next finger date', 'error');
                return;
            }

            try {
                const response = await fetch(`/api/fingerprints/detail/${this.currentFingerprintDetailId}/hold`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify(this.holdForm),
                });
                const result = await response.json();
                if (result.success) {
                    this.showToast('Hold created successfully');
                    this.hideHoldModal();
                    await this.loadData();
                }
            } catch (error) {
                console.error('Failed to save hold:', error);
                this.showToast('Failed to save hold', 'error');
            }
        },

        computeStatusDisplay(status, invoiceId) {
            if (status !== 'approved') {
                return status;
            }

            // Check if all passengers in this invoice have approved status
            const invoiceRows = this.data.filter(r => r.invoice_id === invoiceId);
            const allApproved = invoiceRows.every(r => r.fingerprint_status === 'approved');

            if (allApproved) {
                return 'approved';
            }

            return 'Partially Approved';
        },

        getStatusClass(status) {
            const classes = {
                'none': 'bg-gray-100 text-gray-800',
                'processing': 'bg-yellow-100 text-yellow-800',
                'approved': 'bg-green-100 text-green-800',
                'Partially Approved': 'bg-green-100 text-green-800',
                'cancelled': 'bg-red-100 text-red-800',
            };
            return classes[status] || 'bg-gray-100 text-gray-800';
        },

        showToast(message, type = 'success') {
            const toast = document.createElement('div');
            toast.className = `fixed top-4 right-4 px-4 py-2 rounded shadow-lg ${type === 'error' ? 'bg-red-500 text-white' : 'bg-green-500 text-white'}`;
            toast.textContent = message;
            document.body.appendChild(toast);
            setTimeout(() => toast.remove(), 3000);
        },
    };
}
```

**Update `resources/js/app.js`:**

```javascript
import './fingerprint';
```

**Dependencies:** Phase 2, Phase 3, Phase 4

**Priority:** HIGH

---

### Phase 6: Edge Cases & Error Handling

#### Step 6.1: Handle Edge Cases

1. **No fingerprints exist** - Show "No fingerprint tasks found" message
2. **API errors** - Show toast with error message, reload data
3. **Network timeout** - Retry once, then show error
4. **Empty staff assignment** - Display empty option in dropdown
5. **Office fingerprint** - Display cost normally (no special handling needed)

#### Step 6.2: Add Data Refresh (Optional Enhancement)

Add auto-refresh every 30 seconds:

```javascript
// Add to both fingerprintAdmin() and fingerprintStaff() init()
setInterval(() => this.loadData(), 30000);
```

---

## Implementation Order (Recommended)

1. **Phase 1** - Add remarks field (Step 1.1)
2. **Phase 2** - Controller & API routes (Steps 2.1-2.2)
3. **Phase 3** - Admin blade view (Step 3.1)
4. **Phase 4** - Staff blade view (Step 4.1)
5. **Phase 5** - JavaScript functionality (Step 5.1)
6. **Phase 6** - Edge cases & testing

**Rationale:** This order minimizes refactoring because:
- Database schema changes must be done first
- Backend API must exist before frontend can consume it
- Views reference data from API

---

## Summary

### New Files to Create
| File | Purpose |
|------|---------|
| `database/migrations/YYYY_MM_DD_add_remarks_to_rescheduled_fingerprints_table.php` | Add remarks column |
| `app/Http/Controllers/FingerprintController.php` | All API endpoints |
| `resources/js/fingerprint.js` | JavaScript functionality |

### Modified Files
| File | Changes |
|------|---------|
| `app/Models/RescheduledFingerprint.php` | Add remarks to fillable |
| `routes/web.php` | Add API routes |
| `resources/views/fingerprints/admin.blade.php` | Full implementation |
| `resources/views/fingerprints/staff.blade.php` | Full implementation |
| `resources/js/app.js` | Import fingerprint module |

### Authorization
- Both pages accessible to any authenticated user (no role-based restriction)

### Key Features
- Mobile column shows customer mobile on top, passenger mobile on bottom
- Partially Approved displayed only for passengers with 'approved' status where not all passengers are approved
- Hold modal only on Staff page, saves to rescheduled_fingerprints table and sets status to 'processing'
- Staff dropdown on Admin page loads users from database

---

*Plan created based on analysis of:*
- `database/migrations/2026_05_12_000002_create_fingerprints_table.php`
- `database/migrations/2026_05_12_000003_create_fingerprint_details_table.php`
- `database/migrations/2026_05_12_000004_create_rescheduled_fingerprints_table.php`
- `app/Models/Fingerprint.php`, `FingerprintDetail.php`, `RescheduledFingerprint.php`
- `app/Enums/FingerprintStatus.php`, `RescheduleReason.php`
- `ui-references/fingerprint-admin.html`, `fingerprint-admin.js`
- `ui-references/fingerprint-staff.html`, `fingerprint-staff.js`