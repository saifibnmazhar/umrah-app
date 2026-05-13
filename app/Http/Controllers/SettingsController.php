<?php

namespace App\Http\Controllers;

use App\Models\District;
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

        $packages = Package::with(['ticketFare', 'ticketFare.route', 'ticketFare.airline', 'visaSellingPrice'])
            ->orderBy('id', 'desc')
            ->paginate(10)
            ->withQueryString();

        $ticketFares = TicketFare::with(['airline', 'route', 'airlineClass.travelClass'])
            ->orderBy('id', 'desc')
            ->get()
            ->map(function ($fare) {
                $routeDisplay = $fare->route 
                    ? ($fare->route->fromCity->code ?? '-') . '-' . ($fare->route->toCity->code ?? '-')
                    : '-';
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

        $latestVisa = VisaSellingPrice::latest()->first();

        return view('settings.index', compact(
            'fingerprintCharges', 
            'districts', 
            'divisions', 
            'flightDateGap',
            'packages',
            'ticketFares',
            'latestVisa'
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

        return redirect()->route('settings')->with('success', 'Flight date gap updated successfully');
    }

    public function updateFingerprintCharge(Request $request)
    {
        return redirect()->route('settings')->with('success', 'Fingerprint charge settings updated');
    }

    public function storePackage(Request $request)
    {
        $validated = $request->validate([
            'package_name' => 'required|string|max:255',
            'ticket_fare_id' => 'required|exists:ticket_fares,id',
            'regular_price' => 'required|numeric|min:0',
            'offer_price' => 'nullable|numeric|min:0',
        ]);

        try {
            $validated['user_id'] = auth()->id() ?? 1;
            Package::create($validated);
            return redirect()->route('settings')->with('success', 'Package created successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to create package: ' . $e->getMessage())->withInput();
        }
    }

    public function updatePackage(Request $request, Package $package)
    {
        $validated = $request->validate([
            'package_name' => 'required|string|max:255',
            'ticket_fare_id' => 'required|exists:ticket_fares,id',
            'regular_price' => 'required|numeric|min:0',
            'offer_price' => 'nullable|numeric|min:0',
        ]);

        try {
            $package->update($validated);
            return redirect()->route('settings')->with('success', 'Package updated successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to update package: ' . $e->getMessage())->withInput();
        }
    }

    public function showPackage(Package $package)
    {
        $package->load(['ticketFare.route.fromCity', 'ticketFare.route.toCity', 'ticketFare.route.returnCity', 'ticketFare.airline', 'ticketFare.airlineClass']);
        
        return view('package-configurations.show', compact('package'));
    }

    public function destroyPackage(Package $package)
    {
        try {
            $package->delete();
            return redirect()->route('settings')->with('success', 'Package deleted successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to delete package.');
        }
    }
}