<?php

namespace App\Http\Controllers;

use App\Models\District;
use App\Enums\TicketType;
use App\Models\FingerprintCharge;
use App\Models\FlightDateGap;
use App\Models\Package;
use App\Models\TicketFare;
use App\Models\VisaSellingPrice;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function index()
    {
        $fingerprintChargesQuery = FingerprintCharge::with(['district', 'user']);

        if (request()->has('division') && request('division')) {
            $fingerprintChargesQuery->whereHas('district', fn($q) => $q->where('division', request('division')));
        }

        $fingerprintCharges = $fingerprintChargesQuery->orderBy('id')->paginate(10)->withQueryString();
        $districts = District::orderBy('division')->orderBy('name')->get();
        $divisions = District::distinct()->pluck('division')->sort();

        $flightDateGap = FlightDateGap::first();

        $packages = Package::with(['ticketFare', 'ticketFare.route.fromCity', 'ticketFare.route.toCity', 'ticketFare.route.returnCity', 'ticketFare.route.multiSegments.fromCity', 'ticketFare.route.multiSegments.toCity', 'ticketFare.airline', 'visaSellingPrice'])
            ->withCount('bookings')
            ->orderBy('id', 'desc')
            ->paginate(10)
            ->withQueryString();

        $ticketFares = TicketFare::with([
                'airline',
                'route.fromCity',
                'route.toCity',
                'route.returnCity',
                'route.multiSegments.fromCity',
                'route.multiSegments.toCity',
                'airlineClass.travelClass',
            ])
            ->orderBy('id', 'desc')
            ->get()
            ->map(function ($fare) {
                $routeDisplay = '-';
                if ($fare->route) {
                    $route = $fare->route;
                    if ($route->route_type->value === 'multi_city' && $route->multiSegments && $route->multiSegments->count() > 0) {
                        $segments = $route->multiSegments->map(fn($s) =>
                            ($s->fromCity?->code ?? '?') . ' - ' . ($s->toCity?->code ?? '?')
                        );
                        $routeDisplay = $segments->implode(', ');
                    } elseif ($route->route_type->value === 'round') {
                        $routeDisplay = ($route->fromCity?->code ?? '?')
                            . ' - ' . ($route->toCity?->code ?? '?')
                            . ' - ' . ($route->returnCity?->code ?? '?');
                    } else {
                        $routeDisplay = ($route->fromCity?->code ?? '?')
                            . ' - ' . ($route->toCity?->code ?? '?');
                    }
                }
                $type = $fare->ticket_type->value ?? 'regular';
                return [
                    'id' => $fare->id,
                    'route' => $routeDisplay,
                    'ticket_type' => $type,
                    'selling_fare' => $fare->selling_fare,
                    'offer_price' => $fare->offer_price,
                    'airline' => $fare->airline->name ?? '-',
                ];
            });

        $usedFareIds = Package::pluck('ticket_fare_id')->toArray();

        $latestVisa = VisaSellingPrice::latest()->first();

        return view('settings.index', compact(
            'fingerprintCharges', 
            'districts', 
            'divisions', 
            'flightDateGap',
            'packages',
            'ticketFares',
            'latestVisa',
            'usedFareIds'
        ));
    }

    public function updateFlightDateGap(Request $request)
    {
        $validated = $request->validate([
            'gap' => 'required|integer|min:1'
        ]);

        $flightDateGap = FlightDateGap::first();
        
        if ($flightDateGap) {
            $flightDateGap->update(['gap' => $validated['gap']]);
        } else {
            FlightDateGap::create(['gap' => $validated['gap']]);
        }

        $tab = $request->input('tab', 'flight-date-gap');
        return redirect()->route('settings', ['tab' => $tab])->with('success', 'Flight date gap updated successfully');
    }

    public function updateFingerprintCharge(Request $request)
    {
        $tab = $request->input('tab', 'fingerprint-charge');
        return redirect()->route('settings', ['tab' => $tab])->with('success', 'Fingerprint charge settings updated');
    }

    public function storePackage(Request $request)
    {
        $validated = $request->validate([
            'package_name' => 'required|string|max:255',
            'ticket_fare_id' => 'required|exists:ticket_fares,id',
            'regular_price' => 'required|numeric|min:0',
            'offer_price' => 'nullable|numeric|min:0',
            'service_charge' => 'nullable|numeric|min:0',
        ]);

        if (empty($validated['offer_price'])) {
            $validated['offer_price'] = null;
        }

        $ticketFare = TicketFare::find($validated['ticket_fare_id']);
        if ($ticketFare && $ticketFare->ticket_type === TicketType::OFFER && empty($validated['offer_price'])) {
            $validated['offer_price'] = $validated['regular_price'];
        }

        try {
            $validated['user_id'] = auth()->id() ?? 1;
            $validated['visa_selling_price_id'] = VisaSellingPrice::latest()->first()?->id;
            Package::create($validated);
            $tab = $request->input('tab', 'package-configuration');
            return redirect()->route('settings', ['tab' => $tab])->with('success', 'Package created successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to create package: ' . $e->getMessage())->withInput();
        }
    }

    public function updatePackage(Request $request, Package $package)
    {
        if ($package->isLocked()) {
            return redirect()->back()->with('error', 'This package cannot be edited because it has existing bookings.');
        }

        $validated = $request->validate([
            'package_name' => 'required|string|max:255',
            'ticket_fare_id' => 'required|exists:ticket_fares,id',
            'regular_price' => 'required|numeric|min:0',
            'offer_price' => 'nullable|numeric|min:0',
            'service_charge' => 'nullable|numeric|min:0',
        ]);

        if (empty($validated['offer_price'])) {
            $validated['offer_price'] = null;
        }

        $ticketFare = TicketFare::find($validated['ticket_fare_id']);
        if ($ticketFare && $ticketFare->ticket_type === TicketType::OFFER && empty($validated['offer_price'])) {
            $validated['offer_price'] = $validated['regular_price'];
        }

        try {
            $validated['visa_selling_price_id'] = VisaSellingPrice::latest()->first()?->id;
            $package->update($validated);
            $tab = $request->input('tab', 'package-configuration');
            return redirect()->route('settings', ['tab' => $tab])->with('success', 'Package updated successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to update package: ' . $e->getMessage())->withInput();
        }
    }

    public function showPackage(Package $package)
    {
        $package->load(['ticketFare.route.fromCity', 'ticketFare.route.toCity', 'ticketFare.route.returnCity', 'ticketFare.route.multiSegments.fromCity', 'ticketFare.route.multiSegments.toCity', 'ticketFare.route.transits.transitCity', 'ticketFare.airline', 'ticketFare.airlineClass', 'ticketFare.groupTicket']);
        
        return view('package-configurations.show', compact('package'));
    }

    public function destroyPackage(Request $request, Package $package)
    {
        if ($package->isLocked()) {
            return redirect()->back()->with('error', 'This package cannot be deleted because it has existing bookings.');
        }

        try {
            $package->delete();
            $tab = $request->input('tab', 'package-configuration');
            return redirect()->route('settings', ['tab' => $tab])->with('success', 'Package deleted successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to delete package.');
        }
    }
}