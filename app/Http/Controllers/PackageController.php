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
        $packages = Package::with(['ticketFare.route.fromCity', 'ticketFare.route.toCity', 'ticketFare.groupTicket', 'ticketFare.ticketType', 'visaSellingPrice'])
            ->orderBy('id')
            ->paginate(10);

        $ticketFares = TicketFare::with(['route.fromCity', 'route.toCity', 'ticketType', 'groupTicket'])
            ->orderBy('id')
            ->get()
            ->map(fn($f) => [
                'id' => $f->id,
                'ticket_type' => $f->ticket_type?->value,
                'selling_fare' => $f->selling_fare,
                'offer_price' => $f->offer_price,
                'seats' => $f->groupTicket?->ticket_qty,
                'route' => ($f->route?->fromCity?->code ?? '?') . ' → ' . ($f->route?->toCity?->code ?? '?'),
            ]);

        $latestVisa = VisaSellingPrice::latest()->first();

        return view('packages.index', compact('packages', 'ticketFares', 'latestVisa'));
    }

    public function create()
    {
        $ticketFares = TicketFare::with(['route.fromCity', 'route.toCity', 'ticketType', 'groupTicket'])
            ->orderBy('id')
            ->get()
            ->map(fn($f) => [
                'id' => $f->id,
                'ticket_type' => $f->ticket_type?->value,
                'selling_fare' => $f->selling_fare,
                'offer_price' => $f->offer_price,
                'seats' => $f->groupTicket?->ticket_qty,
                'route' => ($f->route?->fromCity?->code ?? '?') . ' → ' . ($f->route?->toCity?->code ?? '?'),
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
        $ticketFares = TicketFare::with(['route.fromCity', 'route.toCity', 'ticketType', 'groupTicket'])
            ->orderBy('id')
            ->get()
            ->map(fn($f) => [
                'id' => $f->id,
                'ticket_type' => $f->ticket_type?->value,
                'selling_fare' => $f->selling_fare,
                'offer_price' => $f->offer_price,
                'seats' => $f->groupTicket?->ticket_qty,
                'route' => ($f->route?->fromCity?->code ?? '?') . ' → ' . ($f->route?->toCity?->code ?? '?'),
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