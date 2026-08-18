<?php

namespace App\Http\Controllers;

use App\Enums\RouteType;
use App\Enums\TicketType;
use App\Models\Package;
use App\Models\TicketFare;
use App\Models\VisaSellingPrice;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PackageController extends Controller
{
    public function index(Request $request)
    {
        $packagesArray = Package::with([
            'ticketFare',
            'ticketFare.route.fromCity',
            'ticketFare.route.toCity',
            'ticketFare.route.returnCity',
            'ticketFare.route.multiSegments.fromCity',
            'ticketFare.route.multiSegments.toCity',
            'ticketFare.groupTicket',
            'ticketFareInbound',
            'ticketFareInbound.route.fromCity',
            'ticketFareInbound.route.toCity',
            'ticketFareInbound.groupTicket',
            'ticketFareOutbound',
            'ticketFareOutbound.route.fromCity',
            'ticketFareOutbound.route.toCity',
            'ticketFareOutbound.groupTicket',
            'visaSellingPrice',
        ])->withCount('bookings')->orderBy('id')->get()->map(fn ($p) => [
            'id' => $p->id,
            'package_name' => $p->package_name,
            'ticket_fare_id' => $p->ticket_fare_id,
            'regular_price' => $p->regular_price,
            'offer_price' => $p->offer_price,
            'ticket_type' => $p->ticketFare?->ticket_type?->value,
            'selling_fare' => $p->ticketFare?->selling_fare,
            'ticket_offer_price' => $p->ticketFare?->offer_price,
            'ticket_seats' => $p->ticketFare?->groupTicket?->ticket_qty,
            'is_double_ticket' => $p->is_double_ticket,
            'ticket_fare_inbound_id' => $p->ticket_fare_inbound_id,
            'ticket_fare_outbound_id' => $p->ticket_fare_outbound_id,
            'is_locked' => $p->is_locked,
        ]);

        $ticketFares = $this->loadTicketFares();
        $inboundFares = $this->loadTicketFares(RouteType::ONEWAY_INBOUND);
        $outboundFares = $this->loadTicketFares(RouteType::ONEWAY_OUTBOUND);
        $usedFareIds = Package::pluck('ticket_fare_id')->toArray();
        $latestVisa = VisaSellingPrice::latest()->first();

        return view('packages.index', compact('packagesArray', 'ticketFares', 'inboundFares', 'outboundFares', 'latestVisa', 'usedFareIds'));
    }

    private function loadTicketFares(?RouteType $routeType = null)
    {
        $query = TicketFare::with([
            'route.fromCity',
            'route.toCity',
            'route.returnCity',
            'route.multiSegments.fromCity',
            'route.multiSegments.toCity',
            'groupTicket',
        ])->where('is_active', true);

        if ($routeType) {
            $query->whereHas('route', fn ($q) => $q->where('route_type', $routeType->value));
        }

        return $query->orderBy('id')->get()->map(fn ($f) => [
            'id' => $f->id,
            'ticket_type' => $f->ticket_type?->value,
            'selling_fare' => $f->selling_fare,
            'offer_price' => $f->offer_price,
            'seats' => $f->groupTicket?->ticket_qty,
            'route' => $this->buildRouteDisplay($f),
        ]);
    }

    private function buildRouteDisplay($fare)
    {
        $route = $fare->route;

        if ($route->multiSegments && $route->multiSegments->count() > 0) {
            $segments = $route->multiSegments->map(fn ($s) => ($s->fromCity?->code ?? '?').'-'.($s->toCity?->code ?? '?')
            );

            return $segments->implode(', ');
        }

        if ($route->returnCity) {
            $from = $route->fromCity?->code ?? '?';
            $to = $route->toCity?->code ?? '?';
            $return = $route->returnCity?->code ?? '?';

            return "$from - $to - $return";
        }

        return ($route->fromCity?->code ?? '?').' → '.($route->toCity?->code ?? '?');
    }

    public function create()
    {
        $ticketFares = $this->loadTicketFares();
        $inboundFares = $this->loadTicketFares(RouteType::ONEWAY_INBOUND);
        $outboundFares = $this->loadTicketFares(RouteType::ONEWAY_OUTBOUND);
        $latestVisa = VisaSellingPrice::latest()->first();

        return view('packages.edit', compact('ticketFares', 'inboundFares', 'outboundFares', 'latestVisa'));
    }

    public function store(Request $request)
    {
        $isDoubleTicket = $request->boolean('is_double_ticket');

        $rules = [
            'package_name' => 'required|string|max:255',
            'is_double_ticket' => 'nullable|boolean',
            'regular_price' => 'required|numeric|min:0',
            'offer_price' => 'nullable|numeric|min:0',
            'service_charge' => 'nullable|numeric|min:0',
        ];

        if ($isDoubleTicket) {
            $rules['ticket_fare_inbound_id'] = ['required', 'integer', 'exists:ticket_fares,id'];
            $rules['ticket_fare_outbound_id'] = ['required', 'integer', 'exists:ticket_fares,id'];
            $rules['ticket_fare_id'] = ['nullable', 'integer', 'exists:ticket_fares,id'];
        } else {
            $rules['ticket_fare_id'] = [
                'required',
                'integer',
                'exists:ticket_fares,id',
                Rule::unique('packages')->where(function ($query) {
                    return $query->where('ticket_fare_id', request('ticket_fare_id'));
                }),
            ];
            $rules['ticket_fare_inbound_id'] = ['nullable', 'integer', 'exists:ticket_fares,id'];
            $rules['ticket_fare_outbound_id'] = ['nullable', 'integer', 'exists:ticket_fares,id'];
        }

        $validated = $request->validate($rules);

        $latestVisa = VisaSellingPrice::latest()->first();
        $validated['visa_selling_price_id'] = $latestVisa?->id;

        $validated['is_double_ticket'] = $isDoubleTicket;

        if (! $isDoubleTicket) {
            $validated['ticket_fare_inbound_id'] = null;
            $validated['ticket_fare_outbound_id'] = null;
        } else {
            $validated['ticket_fare_id'] = null;
        }

        if (empty($validated['offer_price'])) {
            $validated['offer_price'] = null;
        }

        if (! $isDoubleTicket) {
            $ticketFare = TicketFare::find($validated['ticket_fare_id']);
            if ($ticketFare && $ticketFare->ticket_type === TicketType::OFFER && empty($validated['offer_price'])) {
                $validated['offer_price'] = $validated['regular_price'];
            }
        }

        Package::create($validated);

        return redirect()->route('packages.index')->with('success', 'Package created successfully.');
    }

    public function show(Package $package)
    {
        $package->load([
            'ticketFare.route.fromCity',
            'ticketFare.route.toCity',
            'ticketFare.groupTicket',
            'ticketFareInbound.route.fromCity',
            'ticketFareInbound.route.toCity',
            'ticketFareInbound.groupTicket',
            'ticketFareOutbound.route.fromCity',
            'ticketFareOutbound.route.toCity',
            'ticketFareOutbound.groupTicket',
            'visaSellingPrice',
        ]);
        $package->loadCount('bookings');

        return view('packages.details', compact('package'));
    }

    public function edit(Package $package)
    {
        if ($package->isLocked()) {
            return redirect()->route('packages.index')->with('error', 'This package cannot be edited because it has existing bookings.');
        }

        $ticketFares = $this->loadTicketFares();
        $inboundFares = $this->loadTicketFares(RouteType::ONEWAY_INBOUND);
        $outboundFares = $this->loadTicketFares(RouteType::ONEWAY_OUTBOUND);
        $latestVisa = VisaSellingPrice::latest()->first();

        return view('packages.edit', compact('package', 'ticketFares', 'inboundFares', 'outboundFares', 'latestVisa'));
    }

    public function update(Request $request, Package $package)
    {
        if ($package->isLocked()) {
            return redirect()->route('packages.index')->with('error', 'This package cannot be edited because it has existing bookings.');
        }

        $isDoubleTicket = $request->boolean('is_double_ticket');

        $rules = [
            'package_name' => 'required|string|max:255',
            'is_double_ticket' => 'nullable|boolean',
            'regular_price' => 'required|numeric|min:0',
            'offer_price' => 'nullable|numeric|min:0',
            'service_charge' => 'nullable|numeric|min:0',
        ];

        if ($isDoubleTicket) {
            $rules['ticket_fare_inbound_id'] = ['required', 'integer', 'exists:ticket_fares,id'];
            $rules['ticket_fare_outbound_id'] = ['required', 'integer', 'exists:ticket_fares,id'];
            $rules['ticket_fare_id'] = ['nullable', 'integer', 'exists:ticket_fares,id'];
        } else {
            $rules['ticket_fare_id'] = [
                'required',
                'integer',
                'exists:ticket_fares,id',
                Rule::unique('packages')->where(function ($query) {
                    return $query->where('ticket_fare_id', request('ticket_fare_id'));
                })->ignore($package->id),
            ];
            $rules['ticket_fare_inbound_id'] = ['nullable', 'integer', 'exists:ticket_fares,id'];
            $rules['ticket_fare_outbound_id'] = ['nullable', 'integer', 'exists:ticket_fares,id'];
        }

        $validated = $request->validate($rules);

        $latestVisa = VisaSellingPrice::latest()->first();
        $validated['visa_selling_price_id'] = $latestVisa?->id;

        $validated['is_double_ticket'] = $isDoubleTicket;

        if (! $isDoubleTicket) {
            $validated['ticket_fare_inbound_id'] = null;
            $validated['ticket_fare_outbound_id'] = null;
        } else {
            $validated['ticket_fare_id'] = null;
        }

        if (empty($validated['offer_price'])) {
            $validated['offer_price'] = null;
        }

        if (! $isDoubleTicket) {
            $ticketFare = TicketFare::find($validated['ticket_fare_id']);
            if ($ticketFare && $ticketFare->ticket_type === TicketType::OFFER && empty($validated['offer_price'])) {
                $validated['offer_price'] = $validated['regular_price'];
            }
        }

        $package->update($validated);

        return redirect()->route('packages.index')->with('success', 'Package updated successfully.');
    }

    public function toggleActive(Package $package)
    {
        $package->is_active = ! $package->is_active;
        $package->save();

        $status = $package->is_active ? 'activated' : 'deactivated';

        return back()->with('success', "Package {$status} successfully.");
    }

    public function destroy(Package $package)
    {
        if ($package->isLocked()) {
            return redirect()->route('packages.index')->with('error', 'This package cannot be deleted because it has existing bookings.');
        }

        try {
            $package->delete();

            return redirect()->route('packages.index')->with('success', 'Package deleted successfully.');
        } catch (\Exception $e) {
            return redirect()->route('packages.index')->with('error', 'Cannot delete package. It may be in use by bookings.');
        }
    }
}
