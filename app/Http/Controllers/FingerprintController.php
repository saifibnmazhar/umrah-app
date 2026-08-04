<?php

namespace App\Http\Controllers;

use App\Models\Fingerprint;
use App\Models\FingerprintDetail;
use App\Models\RescheduledFingerprint;
use App\Models\User;
use App\Enums\FingerprintLocation;
use App\Enums\FingerprintStatus;
use App\Services\CurrencyRateService;
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
            'booking.cancelledBooking',
            'fingerprintDetails.passenger',
            'fingerprintDetails.rescheduledFingerprints',
            'assignedStaff'
        ])->orderBy('created_at', 'desc');

        if ($request->has('division') && $request->division) {
            $query->whereHas('booking.district', function ($q) use ($request) {
                $q->where('division', $request->division);
            });
        }

        if ($request->has('district') && $request->district) {
            $query->whereHas('booking', function ($q) use ($request) {
                $q->where('district_id', $request->district);
            });
        }

        if ($request->has('fingerprint_location') && $request->fingerprint_location) {
            $query->whereHas('booking', function ($q) use ($request) {
                $q->where('fingerprint_location', $request->fingerprint_location);
            });
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->whereHas('booking', function ($q) use ($search) {
                    $q->where('invoice_id', 'LIKE', "%{$search}%")
                        ->orWhereHas('customer', function ($q) use ($search) {
                            $q->where('name', 'LIKE', "%{$search}%");
                        });
                })->orWhereHas('fingerprintDetails.passenger', function ($q) use ($search) {
                    $q->where('first_name', 'LIKE', "%{$search}%")
                        ->orWhere('last_name', 'LIKE', "%{$search}%")
                        ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$search}%"]);
                });
            });
        }

        if ($request->filled('fingerprint_status')) {
            $query->whereHas('fingerprintDetails', function ($q) use ($request) {
                $q->where('status', $request->input('fingerprint_status'));
            });
        }

        if ($request->filled('deadline_from')) {
            $query->whereDate('deadline', '>=', $request->input('deadline_from'));
        }

        if ($request->filled('deadline_to')) {
            $query->whereDate('deadline', '<=', $request->input('deadline_to'));
        }

        if ($request->filled('flight_date_from')) {
            $query->whereHas('booking.passengers', function ($q) use ($request) {
                $q->whereDate('flight_date_from', '>=', $request->input('flight_date_from'));
            });
        }

        if ($request->filled('flight_date_to')) {
            $query->whereHas('booking.passengers', function ($q) use ($request) {
                $q->whereDate('flight_date_from', '<=', $request->input('flight_date_to'));
            });
        }

        $user = auth()->user();
        if ($user->branch?->fingerprint_operation && !$user->hasRole('Super Admin') && !$user->hasRole('Co Admin')) {
            $query->whereHas('booking', function ($q) use ($user) {
                $q->where('fingerprint_branch_id', $user->branch_id);
            });
        }

        $fingerprints = $query->paginate(10);

        $items = collect($fingerprints->items())
            ->map(function ($fingerprint) {
                $booking = $fingerprint->booking;
                $passengers = $booking->passengers;

                $currencyRateService = app(CurrencyRateService::class);

                return $passengers->map(function ($passenger) use ($fingerprint, $booking, $passengers, $currencyRateService) {
                    $detail = $fingerprint->fingerprintDetails
                        ->where('passenger_id', $passenger->id)
                        ->first();

                    $statusDisplay = $this->computePartiallyApprovedStatus($detail, $passengers);

                    $rescheduleDeadline = $detail?->rescheduledFingerprints
                        ->sortByDesc('created_at')
                        ->first()?->next_date?->format('Y-m-d');

                    $rate = $booking?->currencyRate?->rate
                        ?? $currencyRateService->getRateForDate($booking?->created_at)?->rate
                        ?? $currencyRateService->getFirstRate()?->rate
                        ?? 0;

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
                        'reschedule_deadline' => $rescheduleDeadline,
                        'cost' => $fingerprint->cost,
                        'rate' => $rate,
                        'assigned_staff_id' => $fingerprint->assigned_staff_id,
                        'assigned_staff_name' => $fingerprint->assignedStaff->name ?? null,
                        'booking_branch_id' => $booking->booking_branch_id,
                        'fingerprint_branch_id' => $booking->fingerprint_branch_id,
                        'passenger_name' => $passenger->first_name . ' ' . $passenger->last_name,
                        'fingerprint_status' => $detail?->status?->value ?? 'none',
                        'passenger_status' => $passenger->status?->name ?? null,
                        'fingerprint_status_display' => $statusDisplay,
                        'fingerprint_location' => $booking->fingerprint_location?->value ?? '-',
                        'is_cancelled' => $booking->is_cancelled,
                        'cancellation_status' => $booking->cancelledBooking?->status?->value,
                        'flight_date_from' => $passenger->flight_date_from?->format('Y-m-d'),
                        'flight_date_to' => $passenger->flight_date_to?->format('Y-m-d'),
                        'required_flight_date' => $passenger->flight_date_from && $passenger->flight_date_to
                            ? $passenger->flight_date_from->format('d M Y') . ' → ' . $passenger->flight_date_to->format('d M Y')
                            : ($passenger->flight_date_from?->format('d M Y') ?? $passenger->flight_date_to?->format('d M Y') ?? '-'),
                        'actual_flight_date' => $passenger->actual_flight_date?->format('d M Y') ?? '-',
                    ];
                });
            })->flatten(1);

        return response()->json([
            'data' => $items,
            'pagination' => [
                'current_page' => $fingerprints->currentPage(),
                'last_page'    => $fingerprints->lastPage(),
                'per_page'     => $fingerprints->perPage(),
                'total'        => $fingerprints->total(),
            ],
        ]);
    }

    /**
     * Get fingerprint tasks for Staff view
     * GET /api/fingerprints/staff
     */
    public function staffIndex(Request $request): JsonResponse
    {
        $query = Fingerprint::with([
            'firstCostLog',
            'booking.customer',
            'booking.district',
            'booking.fingerprintBranch',
            'booking.passengers',
            'booking.cancelledBooking',
            'fingerprintDetails.passenger'
        ])->orderBy('created_at', 'desc');

        $user = auth()->user();
        if ($user->hasRole('Fingerprint Staff')) {
            $query->where('assigned_staff_id', $user->id);
        }

        $query->whereHas('booking', function ($q) {
            $q->where('fingerprint_location', FingerprintLocation::HOME);
        });

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->whereHas('booking', function ($q) use ($search) {
                    $q->where('invoice_id', 'LIKE', "%{$search}%")
                        ->orWhereHas('customer', function ($q) use ($search) {
                            $q->where('name', 'LIKE', "%{$search}%");
                        });
                })->orWhereHas('fingerprintDetails.passenger', function ($q) use ($search) {
                    $q->where('first_name', 'LIKE', "%{$search}%")
                        ->orWhere('last_name', 'LIKE', "%{$search}%")
                        ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$search}%"]);
                });
            });
        }

        if ($request->filled('fingerprint_status')) {
            $query->whereHas('fingerprintDetails', function ($q) use ($request) {
                $q->where('status', $request->input('fingerprint_status'));
            });
        }

        if ($request->filled('deadline_from')) {
            $query->whereDate('deadline', '>=', $request->input('deadline_from'));
        }

        if ($request->filled('deadline_to')) {
            $query->whereDate('deadline', '<=', $request->input('deadline_to'));
        }

        if ($request->filled('flight_date_from')) {
            $query->whereHas('booking.passengers', function ($q) use ($request) {
                $q->whereDate('flight_date_from', '>=', $request->input('flight_date_from'));
            });
        }

        if ($request->filled('flight_date_to')) {
            $query->whereHas('booking.passengers', function ($q) use ($request) {
                $q->whereDate('flight_date_from', '<=', $request->input('flight_date_to'));
            });
        }

        $twentyFourHoursAgo = now()->subHours(24);
        $isSuperOrCoAdmin = $user->hasRole('Super Admin') || $user->hasRole('Co Admin');
        $isFingerprintStaffRole = $user->hasRole('Fingerprint Staff');

        $fingerprints = $query->paginate(10);

        $items = collect($fingerprints->items())
            ->map(function ($fingerprint) use ($isSuperOrCoAdmin, $isFingerprintStaffRole, $twentyFourHoursAgo) {
                $booking = $fingerprint->booking;
                if (!$booking) return collect([]);

                $passengers = $booking->passengers;

                $currencyRateService = app(CurrencyRateService::class);

                $firstLog = $fingerprint->firstCostLog;
                $canEditCost = $isSuperOrCoAdmin
                    || ($isFingerprintStaffRole && (
                        !$firstLog
                        || $firstLog->created_at >= $twentyFourHoursAgo
                    ));

                return $passengers->map(function ($passenger) use ($fingerprint, $booking, $passengers, $currencyRateService, $canEditCost) {
                    $detail = $fingerprint->fingerprintDetails()
                        ->where('passenger_id', $passenger->id)
                        ->first();

                    $statusDisplay = $this->computePartiallyApprovedStatus($detail, $passengers);

                    $firstName = $passenger->first_name ?? '';
                    $lastName = $passenger->last_name ?? '';
                    $passengerName = trim($firstName . ' ' . $lastName) ?: '-';

                    $rate = $booking?->currencyRate?->rate
                        ?? $currencyRateService->getRateForDate($booking?->created_at)?->rate
                        ?? $currencyRateService->getFirstRate()?->rate
                        ?? 0;

                    return [
                        'fingerprint_id' => $fingerprint->id,
                        'fingerprint_detail_id' => $detail?->id,
                        'invoice_id' => $booking->invoice_id,
                        'booking_date' => $booking->created_at?->format('Y-m-d'),
                        'customer_name' => $booking->customer?->name ?? '-',
                        'pax_qty' => $passengers->count(),
                        'customer_mobile' => $booking->customer?->mobile_no ?? '',
                        'passenger_mobile' => $passenger->mobile_no ?? '',
                        'fingerprint_branch_name' => $booking->fingerprintBranch?->name ?? '-',
                        'district' => $booking->district?->name ?? '-',
                        'deadline' => $fingerprint->deadline?->format('Y-m-d'),
                        'passenger_name' => $passengerName,
                        'passenger_address' => $passenger->address ?? '-',
                        'cost' => $fingerprint->cost,
                        'rate' => $rate,
                        'can_edit_cost' => $canEditCost,
                        'fingerprint_status' => $detail?->status?->value ?? 'none',
                        'passenger_status' => $passenger->status?->name ?? null,
                        'fingerprint_status_display' => $statusDisplay,
                        'fingerprint_location' => $booking->fingerprint_location?->value ?? '-',
                        'is_cancelled' => $booking->is_cancelled,
                        'cancellation_status' => $booking->cancelledBooking?->status?->value,
                        'flight_date_from' => $passenger->flight_date_from?->format('Y-m-d'),
                        'flight_date_to' => $passenger->flight_date_to?->format('Y-m-d'),
                    ];
                });
            })->flatten(1);

        return response()->json([
            'data' => $items,
            'pagination' => [
                'current_page' => $fingerprints->currentPage(),
                'last_page'    => $fingerprints->lastPage(),
                'per_page'     => $fingerprints->perPage(),
                'total'        => $fingerprints->total(),
            ],
        ]);
    }

    /**
     * Compute Partially Approved display status
     */
    private function computePartiallyApprovedStatus($detail, $passengers): string
    {
        if (!$detail || $detail->status->value !== 'approved') {
            return $detail?->status?->value ?? 'none';
        }

        $fingerprint = $detail->fingerprint;
        $allDetails = $fingerprint->fingerprintDetails;

        $allApproved = $allDetails->every(function ($d) {
            return $d->status->value === 'approved';
        });

        if ($allApproved) {
            return 'approved';
        }

        return 'Partially Approved';
    }

    /**
     * Assign staff to fingerprint task
     * PUT /api/fingerprints/{fingerprint}/staff
     */
    public function assignStaff(Request $request, Fingerprint $fingerprint): JsonResponse
    {
        if ($fingerprint->booking->is_cancelled) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot assign staff for a cancelled booking'
            ], 422);
        }

        $passengers = $fingerprint->booking->passengers;
        if ($passengers->isNotEmpty() && $passengers->every(fn ($p) => $p->isOnHold())) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot update fingerprint for a passenger on Hold'
            ], 422);
        }

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
        if ($fingerprint->booking->is_cancelled) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot update cost for a cancelled booking'
            ], 422);
        }

        $passengers = $fingerprint->booking->passengers;
        if ($passengers->isNotEmpty() && $passengers->every(fn ($p) => $p->isOnHold())) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot update fingerprint for a passenger on Hold'
            ], 422);
        }

        $user = auth()->user();
        if (!$user->hasRole('Super Admin') && !$user->hasRole('Co Admin') && $fingerprint->assigned_staff_id !== $user->id) {
            abort(403);
        }

        $location = $fingerprint->booking->fingerprint_location;
        $minCost = $location === FingerprintLocation::HOME ? 1 : 0;

        $validated = $request->validate([
            'cost' => 'required|numeric|min:' . $minCost,
        ]);

        $fingerprint->update(['cost' => $validated['cost']]);

        $fingerprint->costLogs()->create([
            'cost' => $validated['cost'],
            'cost_updated_by' => auth()->id(),
        ]);

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
        $user = auth()->user();
        $fingerprint = $fingerprintDetail->fingerprint;
        if (!$user->hasRole('Super Admin') && !$user->hasRole('Co Admin') && !$user->hasRole('Fingerprint Admin') && $fingerprint->assigned_staff_id !== $user->id) {
            abort(403);
        }

        if ($fingerprint->booking->is_cancelled) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot update status for a cancelled booking'
            ], 422);
        }

        if ($fingerprintDetail->passenger?->isOnHold()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot update fingerprint for a passenger on Hold'
            ], 422);
        }

        $validated = $request->validate([
            'status' => 'required|in:none,processing,approved,cancelled,done',
        ]);

        $location = $fingerprint->booking->fingerprint_location;
        if ($location === FingerprintLocation::HOME && (is_null($fingerprint->cost) || $fingerprint->cost <= 0)) {
            return response()->json([
                'success' => false,
                'message' => 'Fingerprint cost must be set before updating status'
            ], 422);
        }

        if ($validated['status'] === 'done') {
            $fingerprintDetail->update(['status' => FingerprintStatus::DONE]);

            $fingerprintDetail->fingerprint->fingerprintDetails()
                ->where('id', '!=', $fingerprintDetail->id)
                ->where('status', FingerprintStatus::APPROVED)
                ->get()
                ->each(fn (FingerprintDetail $detail) => $detail->update(['status' => FingerprintStatus::DONE]));
        } else {
            if ($validated['status'] === 'approved') {
                $hasDone = $fingerprintDetail->fingerprint->fingerprintDetails()
                    ->where('id', '!=', $fingerprintDetail->id)
                    ->where('status', FingerprintStatus::DONE)
                    ->exists();

                $targetStatus = $hasDone ? FingerprintStatus::DONE : FingerprintStatus::APPROVED;
                $fingerprintDetail->update(['status' => $targetStatus]);
            } else {
                $fingerprintDetail->update(['status' => $validated['status']]);
            }
        }

        if ($fingerprintDetail->fingerprint->fingerprintDetails()
            ->where('status', '!=', FingerprintStatus::DONE)
            ->doesntExist()
        ) {
            $fingerprintDetail->fingerprint->fingerprintDetails()
                ->where('status', FingerprintStatus::DONE)
                ->get()
                ->each(fn (FingerprintDetail $d) => $d->update(['status' => FingerprintStatus::APPROVED]));
        }

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
        $user = auth()->user();
        $fingerprint = $fingerprintDetail->fingerprint;
        if (!$user->hasRole('Super Admin') && !$user->hasRole('Co Admin') && !$user->hasRole('Fingerprint Admin') && $fingerprint->assigned_staff_id !== $user->id) {
            abort(403);
        }

        if ($fingerprint->booking->is_cancelled) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot hold fingerprint for a cancelled booking'
            ], 422);
        }

        if ($fingerprintDetail->passenger?->isOnHold()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot update fingerprint for a passenger on Hold'
            ], 422);
        }

        $validated = $request->validate([
            'reason' => 'required|in:rescheduled_by_client,rescheduled_by_bmt,nfc_problem,others',
            'other_reason' => 'nullable|string|max:500',
            'next_date' => 'required|date|after_or_equal:today',
            'remarks' => 'nullable|string|max:1000',
        ]);

        $occurrence = $fingerprintDetail->rescheduledFingerprints()->count() + 1;

        RescheduledFingerprint::create([
            'fingerprint_detail_id' => $fingerprintDetail->id,
            'reason' => $validated['reason'],
            'other_reason' => $validated['other_reason'] ?? null,
            'next_date' => $validated['next_date'],
            'occurrence' => $occurrence,
            'remarks' => $validated['remarks'] ?? null,
        ]);

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
    public function staffList(Request $request): JsonResponse
    {
        $user = auth()->user();

        $query = User::select('id', 'name')
            ->whereHas('roles', fn($q) => $q->where('name', 'Fingerprint Staff'));

        if ($request->filled('fingerprint_branch_id')) {
            $query->where('branch_id', (int) $request->fingerprint_branch_id);
        }

        if ($user->branch?->fingerprint_operation && !$user->hasRole('Super Admin') && !$user->hasRole('Co Admin')) {
            $query->where('branch_id', $user->branch_id);
        }

        $users = $query->orderBy('name')->get();

        return response()->json(['data' => $users]);
    }
}