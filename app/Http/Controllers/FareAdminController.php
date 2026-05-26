<?php

namespace App\Http\Controllers;

use App\Models\TicketAgent;
use App\Models\TicketFare;
use App\Models\Airline;
use App\Models\AirlineClass;
use App\Models\Route;
use Illuminate\Http\Request;

class FareAdminController extends Controller
{
    public function index(Request $request)
    {
        $ticketAgentsQuery = TicketAgent::orderBy('name');
        $ticketAgents = $ticketAgentsQuery->paginate(10)->withQueryString();

        $ticketFaresQuery = TicketFare::with(['airline', 'airlineClass.travelClass', 'route.fromCity', 'route.toCity', 'route.returnCity', 'route.multiSegments.fromCity', 'route.multiSegments.toCity', 'user'])
            ->withCount(['packages', 'passengers']);

        if ($request->has('airline_id') && $request->airline_id) {
            $ticketFaresQuery->where('airline_id', $request->airline_id);
        }

        if ($request->has('ticket_type') && $request->ticket_type) {
            $ticketFaresQuery->where('ticket_type', $request->ticket_type);
        }

        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $ticketFaresQuery->whereHas('airline', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%");
            });
        }

        $ticketFares = $ticketFaresQuery->orderBy('id', 'desc')->paginate(15)->withQueryString();
        
        $routesQuery = Route::with(['airline', 'fromCity', 'toCity', 'returnCity', 'multiSegments.fromCity', 'multiSegments.toCity']);
        
        if ($request->has('route_airline_id') && $request->route_airline_id) {
            $routesQuery->where('airline_id', $request->route_airline_id);
        }
        
        if ($request->has('route_type') && $request->route_type) {
            $routesQuery->where('route_type', $request->route_type);
        }
        
        if ($request->has('flight_type') && $request->flight_type) {
            $routesQuery->where('flight_type', $request->flight_type);
        }
        
        $routes = $routesQuery->orderBy('id', 'desc')->paginate(10)->withQueryString();
        
        $airlines = Airline::orderBy('name')->get();
        $airlineClasses = AirlineClass::with('travelClass')->get();

        return view('fares.admin', compact('ticketAgents', 'ticketFares', 'routes', 'airlines', 'airlineClasses'));
    }

    public function storeAgent(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'nullable|string|max:500',
            'contacts' => 'nullable|string|max:255',
        ]);

        try {
            $validated['user_id'] = auth()->id() ?? 1;
            TicketAgent::create($validated);
            return redirect()->route('fare.admin')->with('success', 'Ticket agent created successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to create ticket agent.')->withInput();
        }
    }

    public function updateAgent(Request $request, TicketAgent $ticketAgent)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'nullable|string|max:500',
            'contacts' => 'nullable|string|max:255',
        ]);

        try {
            $ticketAgent->update($validated);
            return redirect()->route('fare.admin')->with('success', 'Ticket agent updated successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to update ticket agent.')->withInput();
        }
    }

    public function destroyAgent(TicketAgent $ticketAgent)
    {
        try {
            $ticketAgent->delete();
            return redirect()->route('fare.admin')->with('success', 'Ticket agent deleted successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to delete ticket agent.');
        }
    }

    public function storeFare(Request $request)
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

        $validated = $request->validate($rules);

        try {
            TicketFare::create([
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
                'user_id' => auth()->id() ?? 1,
            ]);

            return redirect()->route('fare.admin')->with('success', 'Ticket fare created successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to create ticket fare: ' . $e->getMessage())->withInput();
        }
    }

    public function updateFare(Request $request, TicketFare $ticketFare)
    {
        if ($ticketFare->isLocked()) {
            return redirect()->back()->with('error', 'This ticket fare cannot be edited because it is in use by packages or passengers.');
        }

        $rules = [
            'airline_id' => 'required|exists:airlines,id',
            'airline_classes_id' => 'required|exists:airline_classes,id',
            'route_id' => 'required|exists:routes,id',
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

        $validated = $request->validate($rules);

        try {
            $ticketFare->update(array_merge($validated, [
                'with_meal' => $request->has('with_meal') ? 1 : 0,
            ]));

            return redirect()->route('fare.admin')->with('success', 'Ticket fare updated successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to update ticket fare: ' . $e->getMessage())->withInput();
        }
    }

    public function destroyFare(TicketFare $ticketFare)
    {
        if ($ticketFare->isLocked()) {
            return redirect()->back()->with('error', 'This ticket fare cannot be deleted because it is in use by packages or passengers.');
        }

        try {
            $ticketFare->delete();
            return redirect()->route('fare.admin')->with('success', 'Ticket fare deleted successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to delete ticket fare.');
        }
    }
}