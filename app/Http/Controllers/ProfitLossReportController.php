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

        $customers = $bookings->map(function (Booking $booking) use ($costService) {
            $costSummary = $costService->getBookingCostSummary($booking);
            $totalCost = $costSummary['total_cost'];
            $totalAmount = (float) $booking->invoice->total_amount;

            return [
                'invoice_id'        => $booking->invoice_id,
                'customer_name'     => $booking->customer->name ?? '',
                'customer_passport' => $booking->customer->passport_no ?? '',
                'customer_iqama'    => $booking->customer->iqama_no ?? '',
                'mobile'            => $booking->customer->mobile_no ?? '',
                'pax_qty'           => $booking->pax_qty,
                'package_value'     => $totalAmount,
                'total_cost'        => $totalCost,
                'profit'            => $totalAmount - $totalCost,
            ];
        })->values();

        $passengers = $bookings->flatMap(function (Booking $booking) use ($costService) {
            $passengerCosts = $costService->getPassengerCosts($booking);

            return $booking->passengers->map(function ($passenger) use ($booking, $passengerCosts) {
                $cost = $passengerCosts->firstWhere('passenger_id', $passenger->id);
                $totalCost = $cost['total_cost'] ?? 0;
                $packageValue = (float) $passenger->package_value;

                return [
                    'invoice_id'        => $booking->invoice_id,
                    'customer_name'     => $booking->customer->name ?? '',
                    'customer_passport' => $booking->customer->passport_no ?? '',
                    'customer_iqama'    => $booking->customer->iqama_no ?? '',
                    'mobile'            => $passenger->mobile_no,
                    'passenger_name'    => $passenger->first_name . ' ' . $passenger->last_name,
                    'passenger_passport'=> $passenger->passport_no ?? '',
                    'package_value'     => $packageValue,
                    'total_cost'        => $totalCost,
                    'profit'            => $packageValue - $totalCost,
                ];
            });
        })->values();

        return response()->json([
            'customers'  => $customers,
            'passengers' => $passengers,
        ]);
    }

    public function print(Request $request)
    {
        $dateFrom = $request->date_from;
        $dateTo = $request->date_to;
        $type = $request->get('type', 'customer');
        $currency = $request->get('currency', 'SAR');

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

        $customers = $bookings->map(function (Booking $booking) use ($costService) {
            $costSummary = $costService->getBookingCostSummary($booking);
            $totalCost = $costSummary['total_cost'];
            $totalAmount = (float) $booking->invoice->total_amount;

            return [
                'invoice_id'        => $booking->invoice_id,
                'customer_name'     => $booking->customer->name ?? '',
                'customer_passport' => $booking->customer->passport_no ?? '',
                'customer_iqama'    => $booking->customer->iqama_no ?? '',
                'mobile'            => $booking->customer->mobile_no ?? '',
                'pax_qty'           => $booking->pax_qty,
                'package_value'     => $totalAmount,
                'total_cost'        => $totalCost,
                'profit'            => $totalAmount - $totalCost,
            ];
        })->values();

        $passengers = $bookings->flatMap(function (Booking $booking) use ($costService) {
            $passengerCosts = $costService->getPassengerCosts($booking);

            return $booking->passengers->map(function ($passenger) use ($booking, $passengerCosts) {
                $cost = $passengerCosts->firstWhere('passenger_id', $passenger->id);
                $totalCost = $cost['total_cost'] ?? 0;
                $packageValue = (float) $passenger->package_value;

                return [
                    'invoice_id'        => $booking->invoice_id,
                    'customer_name'     => $booking->customer->name ?? '',
                    'customer_passport' => $booking->customer->passport_no ?? '',
                    'customer_iqama'    => $booking->customer->iqama_no ?? '',
                    'mobile'            => $passenger->mobile_no,
                    'passenger_name'    => $passenger->first_name . ' ' . $passenger->last_name,
                    'passenger_passport'=> $passenger->passport_no ?? '',
                    'package_value'     => $packageValue,
                    'total_cost'        => $totalCost,
                    'profit'            => $packageValue - $totalCost,
                ];
            });
        })->values();

        return view('reports.profit-loss-print', compact('type', 'currency', 'customers', 'passengers', 'dateFrom', 'dateTo'));
    }
}
