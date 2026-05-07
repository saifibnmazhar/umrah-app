<?php

namespace App\Http\Controllers;

use App\Models\TicketFare;
use App\Models\GroupTicket;
use App\Models\BaggageAllowance;
use App\Models\Airline;
use App\Models\AirlineClass;
use App\Models\Route;
use Illuminate\Http\Request;
use App\Enums\TicketType;
use App\Enums\PassengerType;
use App\Enums\TravelDirection;

class TicketFareController extends Controller
{
    public function index(Request $request)
    {
        $query = TicketFare::with(['airline', 'airlineClass', 'route', 'user', 'groupTicket', 'baggageAllowances']);

        if ($request->has('airline_id') && $request->airline_id) {
            $query->where('airline_id', $request->airline_id);
        }

        if ($request->has('ticket_type') && $request->ticket_type) {
            $query->where('ticket_type', $request->ticket_type);
        }

        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->whereHas('airline', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%");
            });
        }

        $ticketFares = $query->orderBy('id', 'desc')->paginate(15)->withQueryString();

        $airlines = Airline::orderBy('name')->get();

        return view('ticket-fares.index', compact('ticketFares', 'airlines'));
    }

    public function create()
    {
        $airlines = Airline::orderBy('name')->get();
        $airlineClasses = AirlineClass::with('travelClass')->get();
        $routes = Route::with(['airline', 'fromCity', 'toCity', 'returnCity'])->get();

        return view('ticket-fares.create', compact('airlines', 'airlineClasses', 'routes'));
    }

    public function store(Request $request)
    {
        $rules = [
            'airline_id' => 'required|exists:airlines,id',
            'airline_classes_id' => 'required|exists:airline_classes,id',
            'route_id' => 'required|exists:routes,id',
            'route_type' => 'required|in:oneway_inbound,oneway_outbound,round,multi_city',
            'ticket_type' => 'required|in:regular,offer,group',
            'effective_from' => 'required|date',
            'effective_to' => 'required|date|after_or_equal:effective_from',
            'net_fare' => 'required|numeric|min:0',
            'selling_fare' => 'required|numeric|min:0',
            'child_fare_percentage' => 'required|numeric|min:0|max:100',
            'infant_fare_percentage' => 'required|numeric|min:0|max:100',
            'with_meal' => 'nullable|boolean',
        ];

        if ($request->ticket_type === 'offer') {
            $rules['offer_price'] = 'required|numeric|min:0';
        }

        if ($request->ticket_type === 'group') {
            $routeType = $request->route_type;

            if ($routeType === 'oneway_inbound') {
                $rules['inbound_date'] = 'required|date';
            } elseif ($routeType === 'oneway_outbound') {
                $rules['outbound_date'] = 'required|date';
            } else {
                $rules['inbound_date'] = 'required|date';
                $rules['outbound_date'] = 'required|date';
            }

            $rules['pnr'] = 'required|string|max:255';
            $rules['ticket_qty'] = 'required|integer|min:1';
            $rules['is_non_refundable'] = 'nullable|boolean';
            $rules['is_non_exchangable'] = 'nullable|boolean';
        }

        $validated = $request->validate($rules);

        try {
            $ticketFare = TicketFare::create([
                'airline_id' => $validated['airline_id'],
                'airline_classes_id' => $validated['airline_classes_id'],
                'route_id' => $validated['route_id'],
                'ticket_type' => $validated['ticket_type'],
                'effective_from' => $validated['effective_from'],
                'effective_to' => $validated['effective_to'],
                'net_fare' => $validated['net_fare'],
                'selling_fare' => $validated['selling_fare'],
                'offer_price' => $validated['offer_price'] ?? null,
                'child_fare_percentage' => $validated['child_fare_percentage'],
                'infant_fare_percentage' => $validated['infant_fare_percentage'],
                'with_meal' => $request->has('with_meal') ? 1 : 0,
                'user_id' => auth()->id(),
            ]);

            if ($request->ticket_type === 'group') {
                $inboundDate = in_array($validated['route_type'], ['oneway_inbound', 'round', 'multi_city']) 
                    ? ($validated['inbound_date'] ?? null) : null;
                $outboundDate = in_array($validated['route_type'], ['oneway_outbound', 'round', 'multi_city']) 
                    ? ($validated['outbound_date'] ?? null) : null;

                GroupTicket::create([
                    'ticket_fare_id' => $ticketFare->id,
                    'inbound_date' => $inboundDate,
                    'outbound_date' => $outboundDate,
                    'pnr' => $validated['pnr'],
                    'ticket_qty' => $validated['ticket_qty'],
                    'is_refundable' => !$request->has('is_non_refundable'),
                    'is_exchangable' => !$request->has('is_non_exchangable'),
                ]);
            }

            $this->createBaggageAllowances($ticketFare, $request);

            return redirect()->route('fare.admin')->with('success', 'Ticket fare created successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to create ticket fare: ' . $e->getMessage())->withInput();
        }
    }

    public function show(TicketFare $ticketFare)
    {
        $ticketFare->load(['airline', 'airlineClass', 'route', 'user', 'groupTicket', 'baggageAllowances']);

        return view('ticket-fares.show', compact('ticketFare'));
    }

    public function edit(TicketFare $ticketFare)
    {
        $ticketFare->load(['airline', 'airlineClass', 'route', 'groupTicket', 'baggageAllowances']);

        $airlines = Airline::orderBy('name')->get();
        $airlineClasses = AirlineClass::with('travelClass')->get();
        $routes = Route::with(['airline', 'fromCity', 'toCity', 'returnCity'])->get();

        return view('ticket-fares.edit', compact('ticketFare', 'airlines', 'airlineClasses', 'routes'));
    }

    public function update(Request $request, TicketFare $ticketFare)
    {
        $rules = [
            'airline_id' => 'required|exists:airlines,id',
            'airline_classes_id' => 'required|exists:airline_classes,id',
            'route_id' => 'required|exists:routes,id',
            'route_type' => 'required|in:oneway_inbound,oneway_outbound,round,multi_city',
            'ticket_type' => 'required|in:regular,offer,group',
            'effective_from' => 'required|date',
            'effective_to' => 'required|date|after_or_equal:effective_from',
            'net_fare' => 'required|numeric|min:0',
            'selling_fare' => 'required|numeric|min:0',
            'child_fare_percentage' => 'required|numeric|min:0|max:100',
            'infant_fare_percentage' => 'required|numeric|min:0|max:100',
            'with_meal' => 'nullable|boolean',
        ];

        if ($request->ticket_type === 'offer') {
            $rules['offer_price'] = 'required|numeric|min:0';
        }

        if ($request->ticket_type === 'group') {
            $routeType = $request->route_type;

            if ($routeType === 'oneway_inbound') {
                $rules['inbound_date'] = 'required|date';
            } elseif ($routeType === 'oneway_outbound') {
                $rules['outbound_date'] = 'required|date';
            } else {
                $rules['inbound_date'] = 'required|date';
                $rules['outbound_date'] = 'required|date';
            }

            $rules['pnr'] = 'required|string|max:255';
            $rules['ticket_qty'] = 'required|integer|min:1';
            $rules['is_non_refundable'] = 'nullable|boolean';
            $rules['is_non_exchangable'] = 'nullable|boolean';
        }

        $validated = $request->validate($rules);

        try {
            $ticketFare->update([
                'airline_id' => $validated['airline_id'],
                'airline_classes_id' => $validated['airline_classes_id'],
                'route_id' => $validated['route_id'],
                'ticket_type' => $validated['ticket_type'],
                'effective_from' => $validated['effective_from'],
                'effective_to' => $validated['effective_to'],
                'net_fare' => $validated['net_fare'],
                'selling_fare' => $validated['selling_fare'],
                'offer_price' => $validated['offer_price'] ?? null,
                'child_fare_percentage' => $validated['child_fare_percentage'],
                'infant_fare_percentage' => $validated['infant_fare_percentage'],
                'with_meal' => $request->has('with_meal') ? 1 : 0,
            ]);

            if ($request->ticket_type === 'group') {
                $inboundDate = in_array($validated['route_type'], ['oneway_inbound', 'round', 'multi_city']) 
                    ? ($validated['inbound_date'] ?? null) : null;
                $outboundDate = in_array($validated['route_type'], ['oneway_outbound', 'round', 'multi_city']) 
                    ? ($validated['outbound_date'] ?? null) : null;

                $ticketFare->groupTicket()->updateOrCreate(
                    ['ticket_fare_id' => $ticketFare->id],
                    [
                        'inbound_date' => $inboundDate,
                        'outbound_date' => $outboundDate,
                        'pnr' => $validated['pnr'],
                        'ticket_qty' => $validated['ticket_qty'],
                        'is_refundable' => !$request->has('is_non_refundable'),
                        'is_exchangable' => !$request->has('is_non_exchangable'),
                    ]
                );
            } else {
                $ticketFare->groupTicket()->delete();
            }

            $this->updateBaggageAllowances($ticketFare, $request);

            return redirect()->route('fare.admin')->with('success', 'Ticket fare updated successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to update ticket fare: ' . $e->getMessage())->withInput();
        }
    }

    public function destroy(TicketFare $ticketFare)
    {
        try {
            $ticketFare->delete();
            return redirect()->route('fare.admin')->with('success', 'Ticket fare deleted successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to delete ticket fare.');
        }
    }

    private function createBaggageAllowances(TicketFare $ticketFare, Request $request)
    {
        $routeType = $request->input('route_type');

        $allowances = [];

        switch ($routeType) {
            case 'oneway_inbound':
                $allowances = [
                    ['passenger_type' => 'adult', 'travel_direction' => 'inbound', 'allowance' => $request->input('inbound_adult', 30)],
                    ['passenger_type' => 'child', 'travel_direction' => 'inbound', 'allowance' => $request->input('inbound_child', 30)],
                    ['passenger_type' => 'infant', 'travel_direction' => 'inbound', 'allowance' => $request->input('inbound_infant', 0)],
                ];
                break;
            case 'oneway_outbound':
                $allowances = [
                    ['passenger_type' => 'adult', 'travel_direction' => 'outbound', 'allowance' => $request->input('outbound_adult', 50)],
                    ['passenger_type' => 'child', 'travel_direction' => 'outbound', 'allowance' => $request->input('outbound_child', 50)],
                    ['passenger_type' => 'infant', 'travel_direction' => 'outbound', 'allowance' => $request->input('outbound_infant', 0)],
                ];
                break;
            case 'round':
            case 'multi_city':
                $allowances = [
                    ['passenger_type' => 'adult', 'travel_direction' => 'inbound', 'allowance' => $request->input('inbound_adult', 30)],
                    ['passenger_type' => 'child', 'travel_direction' => 'inbound', 'allowance' => $request->input('inbound_child', 30)],
                    ['passenger_type' => 'infant', 'travel_direction' => 'inbound', 'allowance' => $request->input('inbound_infant', 0)],
                    ['passenger_type' => 'adult', 'travel_direction' => 'outbound', 'allowance' => $request->input('outbound_adult', 50)],
                    ['passenger_type' => 'child', 'travel_direction' => 'outbound', 'allowance' => $request->input('outbound_child', 50)],
                    ['passenger_type' => 'infant', 'travel_direction' => 'outbound', 'allowance' => $request->input('outbound_infant', 0)],
                ];
                break;
        }

        foreach ($allowances as $allowance) {
            BaggageAllowance::create([
                'ticket_fare_id' => $ticketFare->id,
                'passenger_type' => $allowance['passenger_type'],
                'travel_direction' => $allowance['travel_direction'],
                'allowance' => $allowance['allowance'],
            ]);
        }
    }

    private function updateBaggageAllowances(TicketFare $ticketFare, Request $request)
    {
        $ticketFare->baggageAllowances()->delete();
        $this->createBaggageAllowances($ticketFare, $request);
    }
}