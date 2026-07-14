<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\CancelledBooking;
use App\Models\Branch;
use App\Enums\CancelledBookingStatus;
use Illuminate\Http\Request;

class BookingCancellationViewController extends Controller
{
    public function initiate(Booking $booking)
    {
        $costSummary = $this->getCostBreakdown($booking);
        $invoice = $booking->invoice;

        return response()->json([
            'total_amount'       => (float) ($invoice?->total_amount ?? 0),
            'total_paid'         => (float) ($invoice?->paid_amount ?? 0),
            'balance'            => (float) ($invoice?->balance ?? 0),
            'costs'              => [
                'fingerprint_cost' => $costSummary['fingerprint_cost'],
                'visa_cost'        => $costSummary['visa_cost'],
                'ticket_cost'      => $costSummary['ticket_cost'],
                'total_cost'       => $costSummary['total_cost'],
            ],
            'passenger_costs'    => $costSummary['passengers'],
            'service_charge'     => 0,
            'potential_refund'   => (float) (($invoice?->paid_amount ?? 0) - $costSummary['total_cost']),
            'currency_rate_id'   => $booking->currency_rate_id,
            'booking_branch_id'  => $booking->booking_branch_id,
            'booking_branch_name'=> $booking->bookingBranch?->name,
            'booking_location'   => $booking->bookingBranch?->location,
        ]);
    }

    public function confirm(CancelledBooking $cancelledBooking)
    {
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

        $costSummary = $this->getCostBreakdown($cancelledBooking->booking);
        $bookingCurrencyRate = $cancelledBooking->booking->currencyRate?->rate ?? 0;
        $branches = Branch::select('id', 'name', 'location')->orderBy('name')->get();

        return view('cancelled-bookings.confirm', compact('cancelledBooking', 'costSummary', 'bookingCurrencyRate', 'branches'));
    }

    public function pendingRefunds(Request $request)
    {
        $query = CancelledBooking::with([
            'booking.customer',
            'booking.bookingBranch',
            'user',
            'cancellationBranch',
        ])->where('status', CancelledBookingStatus::PROCESSING);

        if ($request->filled('branch_id')) {
            $query->where('cancellation_branch_id', $request->branch_id);
        }

        $cancelledBookings = $query->latest()->paginate(20)->withQueryString();
        $branches = Branch::select('id', 'name')->orderBy('name')->get();

        return view('pending-refunds.index', compact('cancelledBookings', 'branches'));
    }

    public function report()
    {
        $branches = Branch::select('id', 'name')->orderBy('name')->get();
        return view('reports.booking-cancellation', compact('branches'));
    }

    private function getCostBreakdown(Booking $booking): array
    {
        $eligibleFingerprintCount = $booking->passengers->filter(fn($p) =>
            in_array($p->fingerprintDetail?->status?->value, ['processing', 'done', 'approved'])
        )->count();

        $fingerprintCost = $booking->fingerprint?->cost ?? 0;
        $perPassengerFpCost = $eligibleFingerprintCount > 0
            ? $fingerprintCost / $eligibleFingerprintCount
            : 0;

        $passengerCosts = $booking->passengers->map(fn($p) => [
            'passenger_id'     => $p->id,
            'passenger_name'   => $p->first_name . ' ' . $p->last_name,
            'fingerprint_cost' => in_array($p->fingerprintDetail?->status?->value, ['processing', 'done', 'approved'])
                ? $perPassengerFpCost : 0,
            'visa_cost'        => ($p->visaSubmission && $p->visaSubmission->status?->value === 'issued')
                ? (float) ($p->visaSubmission->final_cost ?? 0) : 0,
            'ticket_cost'      => ($p->latestIssuedTicket && in_array($p->latestIssuedTicket->status, ['issued', 're-issued']))
                ? (float) ($p->latestIssuedTicket->net_fare ?? 0) : 0,
            'total_cost'       => 0,
        ])->map(fn($item) => array_merge($item, [
            'total_cost' => $item['fingerprint_cost'] + $item['visa_cost'] + $item['ticket_cost'],
        ]));

        return [
            'fingerprint_cost' => $passengerCosts->sum('fingerprint_cost'),
            'visa_cost'        => $passengerCosts->sum('visa_cost'),
            'ticket_cost'      => $passengerCosts->sum('ticket_cost'),
            'total_cost'       => $passengerCosts->sum('total_cost'),
            'passengers'       => $passengerCosts->values()->toArray(),
        ];
    }
}
