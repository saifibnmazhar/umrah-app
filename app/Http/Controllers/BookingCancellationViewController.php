<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\CancelledBooking;
use Illuminate\Http\Request;

class BookingCancellationViewController extends Controller
{
    public function initiate(Booking $booking)
    {
        return response()->json([
            'total_amount' => 0,
            'total_paid' => 0,
            'balance' => 0,
            'costs' => [
                'fingerprint_cost' => 0,
                'visa_cost' => 0,
                'ticket_cost' => 0,
                'total_cost' => 0,
            ],
            'passenger_costs' => [],
            'service_charge' => 0,
            'potential_refund' => 0,
            'currency_rate_id' => null,
            'booking_branch_id' => $booking->booking_branch_id,
            'booking_branch_name' => $booking->bookingBranch?->name,
            'booking_location' => $booking->bookingBranch?->location,
        ]);
    }

    public function confirm(CancelledBooking $cancelledBooking)
    {
        return response()->json([
            'id' => $cancelledBooking->id,
            'status' => $cancelledBooking->status,
        ]);
    }

    public function pendingRefunds()
    {
        return response()->json([
            'data' => [],
            'pagination' => [
                'current_page' => 1,
                'last_page' => 1,
                'per_page' => 15,
                'total' => 0,
            ],
        ]);
    }

    public function report()
    {
        return response()->json([
            'data' => [],
            'pagination' => [
                'current_page' => 1,
                'last_page' => 1,
                'per_page' => 15,
                'total' => 0,
            ],
        ]);
    }
}
