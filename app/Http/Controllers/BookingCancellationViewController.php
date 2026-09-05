<?php

namespace App\Http\Controllers;

use App\Enums\CancelledBookingStatus;
use App\Enums\RefundPaymentStatus;
use App\Models\Booking;
use App\Models\Branch;
use App\Models\CancelledBooking;
use App\Models\CancelledPassenger;
use App\Models\Passenger as PassengerModel;
use App\Services\CostTrackingService;
use Illuminate\Http\Request;

class BookingCancellationViewController extends Controller
{
    public function initiate(Booking $booking)
    {
        $costSummary = app(CostTrackingService::class)->getBookingCostSummary($booking);
        $invoice = $booking->invoice;

        return response()->json([
            'total_amount' => (float) ($invoice?->total_amount ?? 0),
            'total_paid' => (float) ($invoice?->paid_amount ?? 0),
            'balance' => (float) ($invoice?->balance ?? 0),
            'costs' => [
                'fingerprint_cost' => $costSummary['fingerprint_cost'],
                'visa_cost' => $costSummary['visa_cost'],
                'ticket_cost' => $costSummary['ticket_cost'],
                'total_cost' => $costSummary['total_cost'],
            ],
            'passenger_costs' => $costSummary['passengers'],
            'service_charge' => 0,
            'potential_refund' => (float) (($invoice?->paid_amount ?? 0) - $costSummary['total_cost']),
            'currency_rate_id' => $booking->currency_rate_id,
            'booking_branch_id' => $booking->booking_branch_id,
            'booking_branch_name' => $booking->bookingBranch?->name,
            'booking_location' => $booking->bookingBranch?->location,
        ]);
    }

    public function confirm(CancelledBooking $cancelledBooking)
    {
        $this->ensureBranchAccess($cancelledBooking);

        $cancelledBooking->load([
            'booking.customer',
            'booking.invoice',
            'booking.passengers.visaSubmission',
            'booking.passengers.latestIssuedTicket',
            'booking.passengers.fingerprintDetail',
            'booking.fingerprint',
            'booking.bookingBranch',
            'user',
            'cancellationBranch',
        ]);

        $costSummary = app(CostTrackingService::class)->getBookingCostSummary($cancelledBooking->booking);
        $branches = Branch::select('id', 'name', 'location')->orderBy('name')->get();

        return view('cancelled-bookings.confirm', compact('cancelledBooking', 'costSummary', 'branches'));
    }

    public function pendingRefunds(Request $request)
    {
        $this->ensureFingerprintAdminHasBranch();

        $tab = $request->get('tab', 'bookings');

        $query = CancelledBooking::with([
            'booking.customer',
            'booking.bookingBranch',
            'user',
            'cancellationBranch',
        ])->where('status', CancelledBookingStatus::PROCESSING);

        $query->when(auth()->user()->branch_id, fn ($q) => $q->where('cancellation_branch_id', auth()->user()->branch_id)
        );

        if ($request->filled('branch_id')) {
            $query->where('cancellation_branch_id', $request->branch_id);
        }

        $cancelledBookings = $query->latest()->paginate(20)->withQueryString();

        $passengerQuery = CancelledPassenger::with([
            'booking.customer',
            'booking.bookingBranch',
            'passenger',
            'user',
            'cancellationBranch',
        ])->where('status', CancelledBookingStatus::PROCESSING);

        $passengerQuery->when(auth()->user()->branch_id, fn ($q) => $q->where('cancellation_branch_id', auth()->user()->branch_id));

        if ($request->filled('branch_id')) {
            $passengerQuery->where('cancellation_branch_id', $request->branch_id);
        }

        $cancelledPassengers = $passengerQuery->latest()->paginate(20)->withQueryString();

        $ticketRefundQuery = PassengerModel::with([
            'booking.customer',
            'booking.bookingBranch',
            'refundPaymentBranch',
        ])->where('refund_payment_status', RefundPaymentStatus::PROCESSING)
            ->where('refund_payable', '>', 0);

        if (auth()->user()->branch_id) {
            $ticketRefundQuery->where('refund_payment_branch_id', auth()->user()->branch_id);
        }

        if ($request->filled('branch_id')) {
            $ticketRefundQuery->where('refund_payment_branch_id', $request->branch_id);
        }

        $ticketRefunds = $ticketRefundQuery->latest()->paginate(20)->withQueryString();

        $branches = Branch::select('id', 'name')->orderBy('name')->get();

        return view('pending-refunds.index', compact(
            'cancelledBookings', 'cancelledPassengers', 'ticketRefunds', 'branches', 'tab'
        ));
    }

    public function report()
    {
        $branches = Branch::select('id', 'name')->orderBy('name')->get();

        return view('reports.booking-cancellation', compact('branches'));
    }

    private function ensureBranchAccess(CancelledBooking $cancelledBooking): void
    {
        $this->ensureFingerprintAdminHasBranch();

        if (auth()->user()->branch_id
            && auth()->user()->branch_id !== $cancelledBooking->cancellation_branch_id) {
            abort(403);
        }
    }

    private function ensureFingerprintAdminHasBranch(): void
    {
        if (auth()->user()->roles->pluck('name')->intersect(['Fingerprint Admin'])->isNotEmpty()
            && ! auth()->user()->branch_id) {
            abort(403);
        }
    }
}
