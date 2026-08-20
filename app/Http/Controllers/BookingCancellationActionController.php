<?php

namespace App\Http\Controllers;

use App\Enums\PaymentMethod;
use App\Models\Booking;
use App\Models\CancelledBooking;
use App\Services\CancellationService;
use App\Support\DateFormatter;
use Illuminate\Http\Request;

class BookingCancellationActionController extends Controller
{
    public function store(Request $request, Booking $booking)
    {
        $validated = $request->validate([
            'cancellation_branch_id' => 'required|exists:branches,id',
            'service_charge_deduction' => 'nullable|numeric|min:0',
        ]);

        try {
            $service = app(CancellationService::class);
            $cancelledBooking = $service->initiateCancellation($booking, $validated);

            return response()->json([
                'success' => true,
                'message' => 'Cancellation initiated',
                'data' => [
                    'id' => $cancelledBooking->id,
                    'status' => $cancelledBooking->status->value,
                    'refund_amount' => $cancelledBooking->refund_amount,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function revert(CancelledBooking $cancelledBooking)
    {
        $this->ensureCancellationAccess($cancelledBooking);

        try {
            $service = app(CancellationService::class);
            $service->revertCancellation($cancelledBooking);

            return redirect()->route('pending-refunds.index')
                ->with('success', 'Cancellation reverted successfully.');
        } catch (\Exception $e) {
            return redirect()->route('pending-refunds.index')
                ->with('error', $e->getMessage());
        }
    }

    public function confirmSubmit(Request $request, CancelledBooking $cancelledBooking)
    {
        $this->ensureCancellationAccess($cancelledBooking);

        $validated = $request->validate([
            'payment_method' => 'required|in:'.implode(',', array_column(PaymentMethod::cases(), 'value')),
            'refund_amount' => 'required|numeric|min:0',
            'remarks' => 'nullable|string|max:500',
        ]);

        try {
            $service = app(CancellationService::class);
            $service->confirmCancellation($cancelledBooking, $validated);

            return redirect()->route('pending-refunds.index')
                ->with('success', 'Refund processed successfully.');
        } catch (\Exception $e) {
            return redirect()->route('pending-refunds.index')
                ->with('error', $e->getMessage());
        }
    }

    public function reportData(Request $request)
    {
        $validated = $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'branch_id' => 'nullable|exists:branches,id',
            'status' => 'nullable|in:cancellation processing,cancelled',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = CancelledBooking::with(['booking.customer', 'cancellationBranch', 'refundPayment', 'refundVoucher.user'])
            ->orderBy('cancelled_bookings.created_at', 'desc');

        if (! empty($validated['date_from'])) {
            $query->where('cancelled_bookings.created_at', '>=', $validated['date_from']);
        }
        if (! empty($validated['date_to'])) {
            $query->where('cancelled_bookings.created_at', '<=', $validated['date_to'].' 23:59:59');
        }
        if (! empty($validated['branch_id'])) {
            $query->where('cancellation_branch_id', $validated['branch_id']);
        }
        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        $perPage = $validated['per_page'] ?? 15;
        $paginated = $query->paginate($perPage);

        $data = $paginated->getCollection()->map(fn ($cb) => [
            'id' => $cb->id,
            'booking_id' => $cb->booking_id,
            'invoice_id' => $cb->invoice_id,
            'customer_name' => $cb->booking?->customer?->name ?? '-',
            'mobile' => $cb->booking?->customer?->mobile_no ?? '-',
            'booking_branch' => $cb->booking?->bookingBranch?->name ?? '-',
            'cancellation_branch' => $cb->cancellationBranch?->name ?? '-',
            'total_paid' => $cb->total_paid,
            'service_charge_deduction' => $cb->service_charge_deduction,
            'refund_amount' => $cb->refund_amount,
            'method' => $cb->refundPayment?->payment_method?->value ?? '-',
            'remarks' => $cb->refundPayment?->remarks ?? '-',
            'cancelled_at' => DateFormatter::dateTime($cb->created_at),
            'refunded_at' => DateFormatter::dateTime($cb->refundVoucher?->created_at),
            'refunded_by' => $cb->refundVoucher?->user?->name ?? '-',
            'status' => $cb->status->value,
        ]);

        $summary = [
            'total_paid' => (float) $query->clone()->sum('total_paid'),
            'total_deduction' => (float) $query->clone()->sum('service_charge_deduction'),
            'total_refund' => (float) $query->clone()->sum('refund_amount'),
        ];

        return response()->json([
            'data' => $data,
            'summary' => $summary,
            'pagination' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
        ]);
    }

    public function updateRefundAmount(Request $request, CancelledBooking $cancelledBooking)
    {
        $this->ensureCancellationAccess($cancelledBooking);

        $validated = $request->validate([
            'refund_amount' => 'required|numeric|min:0',
        ]);

        $cancelledBooking->update([
            'refund_amount' => $validated['refund_amount'],
        ]);

        return response()->json([
            'success' => true,
            'refund_amount' => $cancelledBooking->refund_amount,
        ]);
    }
}
