<?php

namespace App\Http\Controllers;

use App\Models\IssuedTicket;
use App\Models\TicketAgent;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class PendingOutboundReportController extends Controller
{
    public function index(): View
    {
        $ticketAgents = TicketAgent::orderBy('name')->get(['id', 'name']);
        $filters = [
            'search' => request('search', ''),
            'booking_date_from' => request('booking_date_from', ''),
            'booking_date_to' => request('booking_date_to', ''),
            'flight_date_from' => request('flight_date_from', ''),
            'flight_date_to' => request('flight_date_to', ''),
            'status' => request('status', ''),
        ];
        return view('reports.pending-outbound', compact('ticketAgents', 'filters'));
    }

    public function data(Request $request): JsonResponse
    {
        try {
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
        } catch (\Exception $e) {
            \Log::error('Pending outbound report error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'data' => [],
                'summary' => ['total_records' => 0, 'total_invoices' => 0, 'pending' => 0, 'issued' => 0],
                'pagination' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 25, 'total' => 0],
                'error' => 'An error occurred while loading the report.',
            ], 500);
        }
    }

    protected function buildQuery(Request $request)
    {
        $query = IssuedTicket::with([
                'passenger.booking.customer',
                'passenger.ticketFare.route.fromCity',
                'passenger.ticketFare.route.toCity',
                'passenger.ticketFare.airline',
                'passenger.ticketFare.airlineClass.class',
                'passenger.issuedTickets' => function ($q) {
                    $q->where('issue_type', 'regular')
                      ->whereIn('status', ['issued', 're-issued']);
                },
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

            $regularTicket = $passenger?->issuedTickets->first();

            $inboundDate = $regularTicket?->inbound_date;
            $visaExpiryDate = $inboundDate ? $inboundDate->copy()->addDays(90)->format('d-M-Y') : null;
            $expectedFlightDate = $inboundDate?->format('d-M-Y') ?? $passenger?->flight_date_display ?? '-';

            $fare = $regularTicket?->ticketFare;
            $routeDisplay = '-';
            if ($fare?->route) {
                $r = $fare->route;
                $from = $r->fromCity?->code ?? '?';
                $to = $r->toCity?->code ?? '?';
                if ($r->route_type?->value === 'round' && $r->returnCity) {
                    $routeDisplay = "{$from}-{$to}-{$r->returnCity->code}";
                } else {
                    $routeDisplay = "{$from}-{$to}";
                }
            }

            return [
                'id' => $ticket->id,
                'booking_id' => $booking?->id,
                'passenger_id' => $passenger?->id,
                'booking_date' => $booking?->created_at?->format('d-M-Y') ?? '-',
                'invoice' => $booking?->invoice_id ?? '-',
                'customer_name' => $customer?->name ?? '-',
                'customer_mobile' => $customer?->mobile_no ?? '-',
                'mobile' => $passenger?->mobile_no ?? '-',
                'passenger_name' => $passenger ? trim(($passenger->first_name ?? '') . ' ' . ($passenger->last_name ?? '')) : '-',
                'passport' => $passenger?->passport_no ?? '-',
                'status' => $ticket->status ?? 'pending',
                'visa_expiry_date' => $visaExpiryDate ?? '-',
                'expected_flight_date' => $expectedFlightDate,
                'actual_flight_date' => $ticket->outbound_date?->format('d-M-Y') ?? '-',
                'passenger_type' => $passenger?->passenger_type?->value ?? 'adult',
                'current_ticket' => [
                    'ticket_number' => $ticket->ticket_number,
                    'pnr' => $ticket->pnr,
                    'outbound_date' => $ticket->outbound_date?->format('Y-m-d'),
                    'issued_date' => $ticket->issued_date?->format('Y-m-d'),
                    'ticket_agent_id' => $ticket->ticket_agent_id,
                    'selling_fare' => (float) $ticket->selling_fare,
                    'net_fare' => (float) $ticket->net_fare,
                    'is_refundable' => $ticket->is_refundable,
                    'is_exchangeable' => $ticket->is_exchangeable,
                    'baggage_outbound' => $ticket->baggage_outbound ?? '',
                ],
                'regular_ticket' => $regularTicket ? [
                    'ticket_agent_id' => $regularTicket->ticket_agent_id,
                    'ticket_fare_id' => $regularTicket->ticket_fare_id,
                    'inbound_date' => $inboundDate?->format('Y-m-d'),
                    'outbound_date' => $regularTicket->outbound_date?->format('Y-m-d'),
                    'selling_fare' => (float) $regularTicket->selling_fare,
                    'net_fare' => (float) $regularTicket->net_fare,
                    'offer_price' => (float) ($regularTicket->offer_price ?? 0),
                    'is_refundable' => $regularTicket->is_refundable,
                    'is_exchangeable' => $regularTicket->is_exchangeable,
                    'baggage_inbound' => $regularTicket->baggage_inbound ?? '',
                    'baggage_outbound' => $regularTicket->baggage_outbound ?? '',
                    'route_display' => $routeDisplay,
                    'airline' => $fare?->airline?->name ?? '',
                    'travel_class' => $fare?->airlineClass?->class?->name ?? '',
                    'flight_type' => $fare?->route?->flight_type?->value ?? '',
                ] : null,
            ];
        })->toArray();
    }

    protected function computeSummary(array $items): array
    {
        $uniqueInvoices = collect($items)->pluck('invoice')->unique();
        $statusCounts = collect($items)->groupBy('status')->map->count();
        return [
            'total_records' => count($items),
            'total_invoices' => $uniqueInvoices->count(),
            'pending' => $statusCounts->get('pending', 0),
            'issued' => ($statusCounts->get('issued', 0) + $statusCounts->get('re-issued', 0)),
        ];
    }
}
