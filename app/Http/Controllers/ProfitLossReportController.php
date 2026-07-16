<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Services\CostTrackingService;
use Illuminate\Http\Request;

class ProfitLossReportController extends Controller
{
    public function data(Request $request)
    {
        $dateFrom = $request->date_from;
        $dateTo = $request->date_to;

        $query = Booking::with([
            'customer',
            'invoice',
            'fingerprint',
            'passengers.visaSubmission',
            'passengers.latestIssuedTicket',
        ])
            ->where('is_cancelled', false)
            ->whereHas('invoice');

        if ($dateFrom) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        $bookings = $query->get();

        $costService = app(CostTrackingService::class);

        $passengers = $bookings->flatMap(function (Booking $booking) use ($costService) {
            $passengerCosts = $costService->getPassengerCosts($booking);

            return $booking->passengers->map(function ($passenger) use ($booking, $passengerCosts) {
                $cost = $passengerCosts->firstWhere('passenger_id', $passenger->id);
                $totalCost = $cost['total_cost'] ?? 0;
                $packageValue = (float) $passenger->package_value;

                return [
                    'invoice_id'     => $booking->invoice_id,
                    'customer_name'  => $booking->customer->name ?? '',
                    'mobile'         => $passenger->mobile_no,
                    'passenger_name' => $passenger->first_name . ' ' . $passenger->last_name,
                    'package_value'  => $packageValue,
                    'total_cost'     => $totalCost,
                    'profit'         => $packageValue - $totalCost,
                ];
            });
        })->values();

        return response()->json(['passengers' => $passengers]);
    }
}
