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

        if ($request->has('division') && $request->division) {
            $query->whereHas('booking.district', function ($q) use ($request) {
                $q->where('division', $request->division);
            });
        }

        if ($request->has('district') && $request->district) {
            $query->where('booking.district_id', $request->district);
        }

        $user = auth()->user();
        if ($user->office_id && !$user->hasRole('Super Admin') && !$user->hasRole('Co Admin')) {
            $query->whereHas('booking', function ($q) use ($user) {
                $q->where('office_id', $user->office_id);
            });
        }

        $fingerprints = $query->paginate(10);

        $items = collect($fingerprints->items())
            ->map(function ($fingerprint) {
                $booking = $fingerprint->booking;
                $passengers = $booking->passengers;

                return $passengers->map(function ($passenger) use ($fingerprint, $booking, $passengers) {
                    $detail = $fingerprint->fingerprintDetails()
                        ->where('passenger_id', $passenger->id)
                        ->first();

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
                        'fingerprint_location' => $booking->fingerprint_location?->value ?? '-',
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
            'booking.customer',
            'booking.district',
            'booking.office',
            'booking.passengers',
            'fingerprintDetails.passenger'
        ])->orderBy('created_at', 'desc');

        $user = auth()->user();
        if (!$user->hasRole('Super Admin')) {
            $query->where('assigned_staff_id', $user->id);
        }

        $fingerprints = $query->get();

        $data = $fingerprints->map(function ($fingerprint) {
            $booking = $fingerprint->booking;
            if (!$booking) return collect([]);

            $passengers = $booking->passengers;

            return $passengers->map(function ($passenger) use ($fingerprint, $booking, $passengers) {
                $detail = $fingerprint->fingerprintDetails()
                    ->where('passenger_id', $passenger->id)
                    ->first();

                $statusDisplay = $this->computePartiallyApprovedStatus($detail, $passengers);

                $firstName = $passenger->first_name ?? '';
                $lastName = $passenger->last_name ?? '';
                $passengerName = trim($firstName . ' ' . $lastName) ?: '-';

                return [
                    'fingerprint_id' => $fingerprint->id,
                    'fingerprint_detail_id' => $detail?->id,
                    'invoice_id' => $booking->invoice_id,
                    'booking_date' => $booking->created_at?->format('Y-m-d'),
                    'customer_name' => $booking->customer?->name ?? '-',
                    'pax_qty' => $passengers->count(),
                    'customer_mobile' => $booking->customer?->mobile_no ?? '',
                    'passenger_mobile' => $passenger->mobile_no ?? '',
                    'office' => $booking->office?->name ?? '-',
                    'district' => $booking->district?->name ?? '-',
                    'deadline' => $fingerprint->deadline?->format('Y-m-d'),
                    'passenger_name' => $passengerName,
                    'cost' => $fingerprint->cost,
                    'fingerprint_status' => $detail?->status?->value ?? 'none',
                    'fingerprint_status_display' => $statusDisplay,
                    'fingerprint_location' => $booking->fingerprint_location?->value ?? '-',
                    'flight_date_from' => $passenger->flight_date_from?->format('Y-m-d'),
                    'flight_date_to' => $passenger->flight_date_to?->format('Y-m-d'),
                ];
            });
        })->flatten(1);

        return response()->json(['data' => $data]);
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
        $user = auth()->user();
        if (!$user->hasRole('Super Admin') && $fingerprint->assigned_staff_id !== $user->id) {
            abort(403);
        }

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
        $user = auth()->user();
        $fingerprint = $fingerprintDetail->fingerprint;
        if (!$user->hasRole('Super Admin') && $fingerprint->assigned_staff_id !== $user->id) {
            abort(403);
        }

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
        $user = auth()->user();
        $fingerprint = $fingerprintDetail->fingerprint;
        if (!$user->hasRole('Super Admin') && $fingerprint->assigned_staff_id !== $user->id) {
            abort(403);
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
    public function staffList(): JsonResponse
    {
        $users = \App\Models\User::select('id', 'name')->orderBy('name')->get();
        return response()->json(['data' => $users]);
    }
}