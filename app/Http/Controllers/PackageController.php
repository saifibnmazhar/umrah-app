<?php

namespace App\Http\Controllers;

use App\Enums\TicketType;
use App\Models\Package;
use App\Models\TicketFare;
use App\Models\VisaSellingPrice;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PackageController extends Controller
{
    public function index()
    {
        $packages = Package::with([
            'ticketFare',
            'ticketFare.route.fromCity',
            'ticketFare.route.toCity',
            'ticketFare.route.returnCity',
            'ticketFare.route.multiSegments.fromCity',
            'ticketFare.route.multiSegments.toCity',
            'ticketFare.groupTicket',
            'visaSellingPrice'
        ])->orderBy('id')->paginate(10);

        $packagesArray = $packages->map(fn($p) => [
            'id' => $p->id,
            'package_name' => $p->package_name,
            'ticket_fare_id' => $p->ticket_fare_id,
            'regular_price' => $p->regular_price,
            'offer_price' => $p->offer_price,
            'ticket_type' => $p->ticketFare?->ticket_type?->value,
            'selling_fare' => $p->ticketFare?->selling_fare,
            'ticket_offer_price' => $p->ticketFare?->offer_price,
            'ticket_seats' => $p->ticketFare?->groupTicket?->ticket_qty,
        ]);

        $ticketFares = TicketFare::with([
            'route.fromCity',
            'route.toCity',
            'route.returnCity',
            'route.multiSegments.fromCity',
            'route.multiSegments.toCity',
            'groupTicket'
        ])->orderBy('id')->get()->map(fn($f) => [
            'id' => $f->id,
            'ticket_type' => $f->ticket_type?->value,
            'selling_fare' => $f->selling_fare,
            'offer_price' => $f->offer_price,
            'seats' => $f->groupTicket?->ticket_qty,
            'route' => $this->buildRouteDisplay($f),
        ]);

        $latestVisa = VisaSellingPrice::latest()->first();

        return view('packages.index', compact('packages', 'packagesArray', 'ticketFares', 'latestVisa'));
    }

    private function buildRouteDisplay($fare)
    {
        $route = $fare->route;
        
        if ($route->multiSegments && $route->multiSegments->count() > 0) {
            $segments = $route->multiSegments->map(fn($s) => 
                ($s->fromCity?->code ?? '?') . '-' . ($s->toCity?->code ?? '?')
            );
            return $segments->implode(', ');
        }
        
        if ($route->returnCity) {
            $from = $route->fromCity?->code ?? '?';
            $to = $route->toCity?->code ?? '?';
            $return = $route->returnCity?->code ?? '?';
            return "$from - $to - $return";
        }
        
        return ($route->fromCity?->code ?? '?') . ' → ' . ($route->toCity?->code ?? '?');
    }

    public function create()
    {
        $ticketFares = TicketFare::with([
            'route.fromCity',
            'route.toCity',
            'route.returnCity',
            'route.multiSegments.fromCity',
            'route.multiSegments.toCity',
            'groupTicket'
        ])->orderBy('id')->get()->map(fn($f) => [
            'id' => $f->id,
            'ticket_type' => $f->ticket_type?->value,
            'selling_fare' => $f->selling_fare,
            'offer_price' => $f->offer_price,
            'seats' => $f->groupTicket?->ticket_qty,
            'route' => $this->buildRouteDisplay($f),
        ]);
        $latestVisa = VisaSellingPrice::latest()->first();

        return view('packages.edit', compact('ticketFares', 'latestVisa'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'package_name' => 'required|string|max:255',
            'ticket_fare_id' => [
                'required',
                'integer',
                'exists:ticket_fares,id',
                Rule::unique('packages')->where(function ($query) {
                    return $query->where('ticket_fare_id', request('ticket_fare_id'));
                }),
            ],
            'regular_price' => 'required|numeric|min:0',
            'offer_price' => 'nullable|numeric|min:0',
            'service_charge' => 'nullable|numeric|min:0',
        ]);

        $latestVisa = VisaSellingPrice::latest()->first();
        $validated['visa_selling_price_id'] = $latestVisa?->id;

        $ticketFare = TicketFare::find($validated['ticket_fare_id']);
        if ($ticketFare && $ticketFare->ticket_type === TicketType::OFFER && empty($validated['offer_price'])) {
            $validated['offer_price'] = $validated['regular_price'];
        }

        Package::create($validated);

        return redirect()->route('packages.index')->with('success', 'Package created successfully.');
    }

    public function show(Package $package)
    {
        $package->load(['ticketFare.route.fromCity', 'ticketFare.route.toCity', 'ticketFare.groupTicket', 'visaSellingPrice']);

        return view('packages.details', compact('package'));
    }

    public function edit(Package $package)
    {
        $ticketFares = TicketFare::with([
            'route.fromCity',
            'route.toCity',
            'route.returnCity',
            'route.multiSegments.fromCity',
            'route.multiSegments.toCity',
            'groupTicket'
        ])->orderBy('id')->get()->map(fn($f) => [
            'id' => $f->id,
            'ticket_type' => $f->ticket_type?->value,
            'selling_fare' => $f->selling_fare,
            'offer_price' => $f->offer_price,
            'seats' => $f->groupTicket?->ticket_qty,
            'route' => $this->buildRouteDisplay($f),
        ]);
        $latestVisa = VisaSellingPrice::latest()->first();

        return view('packages.edit', compact('package', 'ticketFares', 'latestVisa'));
    }

    public function update(Request $request, Package $package)
    {
        $validated = $request->validate([
            'package_name' => 'required|string|max:255',
            'ticket_fare_id' => [
                'required',
                'integer',
                'exists:ticket_fares,id',
                Rule::unique('packages')->where(function ($query) {
                    return $query->where('ticket_fare_id', request('ticket_fare_id'));
                })->ignore($package->id),
            ],
            'regular_price' => 'required|numeric|min:0',
            'offer_price' => 'nullable|numeric|min:0',
            'service_charge' => 'nullable|numeric|min:0',
        ]);

        $latestVisa = VisaSellingPrice::latest()->first();
        $validated['visa_selling_price_id'] = $latestVisa?->id;

        $ticketFare = TicketFare::find($validated['ticket_fare_id']);
        if ($ticketFare && $ticketFare->ticket_type === TicketType::OFFER && empty($validated['offer_price'])) {
            $validated['offer_price'] = $validated['regular_price'];
        }

        $package->update($validated);

        return redirect()->route('packages.index')->with('success', 'Package updated successfully.');
    }

    public function destroy(Package $package)
    {
        try {
            $package->delete();
            return redirect()->route('packages.index')->with('success', 'Package deleted successfully.');
        } catch (\Exception $e) {
            return redirect()->route('packages.index')->with('error', 'Cannot delete package. It may be in use by bookings.');
        }
    }
}