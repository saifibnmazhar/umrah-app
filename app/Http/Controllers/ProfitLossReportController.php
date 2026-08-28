<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Services\ProfitCalculationService;
use Illuminate\Http\Request;

class ProfitLossReportController extends Controller
{
    private function bookingsQuery(Request $request)
    {
        $query = Booking::with([
            'customer',
            'invoice',
            'fingerprint',
            'package.ticketFare',
            'package.ticketFareInbound',
            'package.ticketFareOutbound',
            'passengers.visaSubmission.cancelledSubmissions',
            'passengers.allIssuedTickets.ticketFare',
            'passengers.allIssuedTickets.reIssuedTickets',
            'passengers.allIssuedTickets.refundedTickets',
        ])
            ->where('is_cancelled', false)
            ->whereHas('invoice');

        if ($request->date_from) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->date_to) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        return $query->get();
    }

    private function mapCustomers($bookings): array
    {
        return $bookings->map(fn (Booking $booking) => [
            'invoice_id' => $booking->invoice_id,
            'customer_name' => $booking->customer->name ?? '',
            'customer_passport' => $booking->customer->passport_no ?? '',
            'customer_iqama' => $booking->customer->iqama_no ?? '',
            'mobile' => $booking->customer->mobile_no ?? '',
            'pax_qty' => $booking->pax_qty,
            'package_value' => (float) ($booking->invoice->total_amount ?? 0),
            'fingerprint_profit' => (float) ($booking->fingerprint?->profit ?? 0),
            'passenger_profit_total' => (float) $booking->passengers->sum('profit'),
            'discount' => (float) ($booking->discount_amount ?? 0),
            'total_profit' => (float) ($booking->profit ?? 0),
        ])->values()->toArray();
    }

    private function mapPassengers($bookings, ProfitCalculationService $profitService): array
    {
        return $bookings->flatMap(fn (Booking $booking) => $booking->passengers->map(fn ($passenger) => [
            'invoice_id' => $booking->invoice_id,
            'customer_name' => $booking->customer->name ?? '',
            'customer_passport' => $booking->customer->passport_no ?? '',
            'customer_iqama' => $booking->customer->iqama_no ?? '',
            'mobile' => $passenger->mobile_no,
            'passenger_name' => trim($passenger->first_name.' '.$passenger->last_name),
            'passenger_passport' => $passenger->passport_no ?? '',
            'package_value' => (float) ($passenger->package_value ?? 0),
            'total_profit' => (float) ($passenger->profit ?? 0),
            'breakdown' => $profitService->getPassengerProfitBreakdown($passenger),
        ]))->values()->toArray();
    }

    public function data(Request $request)
    {
        $bookings = $this->bookingsQuery($request);

        return response()->json([
            'customers' => $this->mapCustomers($bookings),
            'passengers' => $this->mapPassengers($bookings, app(ProfitCalculationService::class)),
        ]);
    }

    public function print(Request $request)
    {
        $type = $request->get('type', 'customer');
        $currency = $request->get('currency', 'SAR');
        $dateFrom = $request->date_from;
        $dateTo = $request->date_to;

        $bookings = $this->bookingsQuery($request);

        $customers = collect($this->mapCustomers($bookings));
        $passengers = collect($this->mapPassengers($bookings, app(ProfitCalculationService::class)));

        return view('reports.profit-loss-print', compact(
            'type', 'currency', 'customers', 'passengers', 'dateFrom', 'dateTo'
        ));
    }
}
