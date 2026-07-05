<?php

namespace App\Http\Controllers;

use App\Models\IssuedTicket;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class PendingOutboundReportController extends Controller
{
    public function index(): View
    {
        return view('reports.pending-outbound');
    }

    public function data(Request $request): JsonResponse
    {
        $query = $this->buildQuery($request);
        $perPage = 25;
        $tickets = $query->paginate($perPage);
        $items = $this->mapItems($tickets->items());

        $allQuery = $this->buildQuery($request);
        $allItems = $this->mapItems($allQuery->get()->all());
        $summary = $this->computeSummary($allItems);

        return response()->json([
            'data' => $items,
            'summary' => $summary,
            'pagination' => [
                'current_page' => $tickets->currentPage(),
                'last_page' => $tickets->lastPage(),
                'per_page' => $tickets->perPage(),
                'total' => $tickets->total(),
            ],
        ]);
    }

    protected function buildQuery(Request $request)
    {
        $query = IssuedTicket::with([
                'passenger.booking.customer',
                'passenger.ticketFare.route',
            ])
            ->where('issue_type', 'pending_outbound');

        if ($search = $request->search) {
            $query->where(function ($q) use ($search) {
                $q->whereHas('passenger', function ($pq) use ($search) {
                    $pq->where('first_name', 'like', "%{$search}%")
                       ->orWhere('last_name', 'like', "%{$search}%")
                       ->orWhere('mobile_no', 'like', "%{$search}%")
                       ->orWhere('passport_no', 'like', "%{$search}%");
                })->orWhereHas('passenger.booking', function ($bq) use ($search) {
                    $bq->where('invoice_id', 'like', "%{$search}%");
                })->orWhereHas('passenger.booking.customer', function ($cq) use ($search) {
                    $cq->where('name', 'like', "%{$search}%");
                });
            });
        }

        if ($request->booking_date_from) {
            $query->whereHas('passenger.booking', fn($q) => $q->whereDate('created_at', '>=', $request->booking_date_from));
        }
        if ($request->booking_date_to) {
            $query->whereHas('passenger.booking', fn($q) => $q->whereDate('created_at', '<=', $request->booking_date_to));
        }

        if ($request->flight_date_from) {
            $query->whereHas('passenger', fn($q) => $q->whereDate('flight_date_from', '>=', $request->flight_date_from));
        }
        if ($request->flight_date_to) {
            $query->whereHas('passenger', fn($q) => $q->whereDate('flight_date_to', '<=', $request->flight_date_to));
        }

        if ($request->status && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        $query->orderBy('created_at', 'desc');

        return $query;
    }

    protected function mapItems(array $tickets): array
    {
        return collect($tickets)->map(function ($ticket) {
            $passenger = $ticket->passenger;
            $booking = $passenger?->booking;
            $customer = $booking?->customer;
            return [
                'id' => $ticket->id,
                'booking_date' => $booking?->created_at?->format('d-M-Y') ?? '-',
                'invoice' => $booking?->invoice_id ?? '-',
                'customer_name' => $customer?->name ?? '-',
                'mobile' => $passenger?->mobile_no ?? '-',
                'passenger_name' => $passenger ? trim(($passenger->first_name ?? '') . ' ' . ($passenger->last_name ?? '')) : '-',
                'passport' => $passenger?->passport_no ?? '-',
                'status' => $ticket->status ?? 'pending',
                'flight_date' => $passenger?->flight_date_display ?? '-',
                'actual_flight_date' => $passenger?->actual_flight_date?->format('d-M-Y') ?? '-',
            ];
        })->toArray();
    }

    protected function computeSummary(array $items): array
    {
        $uniqueInvoices = collect($items)->pluck('invoice')->unique();
        return [
            'total_records' => count($items),
            'total_invoices' => $uniqueInvoices->count(),
            'pending' => collect($items)->where('status', 'pending')->count(),
            'issued' => collect($items)->whereIn('status', ['issued', 're-issued'])->count(),
        ];
    }
}
