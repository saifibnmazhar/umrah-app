<?php

namespace App\Http\Controllers;

use App\Enums\TicketType;
use App\Exceptions\DatabaseErrorHumanizer;
use App\Models\District;
use App\Models\FlightDateGap;
use App\Models\Package;
use App\Models\StayDurationLimit;
use App\Models\TicketFare;
use App\Models\VisaSellingPrice;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function index()
    {
        $divisions = District::distinct()->pluck('division')->sort();
        $districts = District::orderBy('division')->orderBy('name')->get();

        $flightDateGap = FlightDateGap::first();

        $packagesQuery = Package::with([
            'ticketFare',
            'ticketFare.route.fromCity',
            'ticketFare.route.toCity',
            'ticketFare.route.returnCity',
            'ticketFare.route.multiSegments.fromCity',
            'ticketFare.route.multiSegments.toCity',
            'ticketFare.airline',
            'ticketFareInbound',
            'ticketFareInbound.route.fromCity',
            'ticketFareInbound.route.toCity',
            'ticketFareInbound.airline',
            'ticketFareOutbound',
            'ticketFareOutbound.route.fromCity',
            'ticketFareOutbound.route.toCity',
            'ticketFareOutbound.airline',
            'visaSellingPrice',
        ])->withCount('bookings');

        if (request()->has('status') && request('status') === 'inactive') {
            $packagesQuery->where('is_active', false);
        } elseif (request()->has('status') && request('status') === 'all') {
            // no filter
        } else {
            $packagesQuery->where('is_active', true);
        }

        $packages = $packagesQuery->orderBy('id', 'desc')->paginate(10)->withQueryString();

        $ticketFares = TicketFare::with([
            'airline',
            'route.fromCity',
            'route.toCity',
            'route.returnCity',
            'route.multiSegments.fromCity',
            'route.multiSegments.toCity',
            'airlineClass.travelClass',
        ])
            ->where('is_active', true)
            ->orderBy('id', 'desc')
            ->get()
            ->map(function ($fare) {
                $routeDisplay = '-';
                if ($fare->route) {
                    $route = $fare->route;
                    if ($route->route_type->value === 'multi_city' && $route->multiSegments && $route->multiSegments->count() > 0) {
                        $segments = $route->multiSegments->map(fn ($s) => ($s->fromCity?->code ?? '?').' - '.($s->toCity?->code ?? '?')
                        );
                        $routeDisplay = $segments->implode(', ');
                    } elseif ($route->route_type->value === 'round') {
                        $routeDisplay = ($route->fromCity?->code ?? '?')
                            .' - '.($route->toCity?->code ?? '?')
                            .' - '.($route->returnCity?->code ?? '?');
                    } else {
                        $routeDisplay = ($route->fromCity?->code ?? '?')
                            .' - '.($route->toCity?->code ?? '?');
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

        $inboundFares = TicketFare::with([
            'airline',
            'route.fromCity',
            'route.toCity',
        ])->where('is_active', true)
            ->whereHas('route', fn ($q) => $q->where('route_type', 'oneway_inbound'))
            ->orderBy('id', 'desc')
            ->get()
            ->map(fn ($fare) => [
                'id' => $fare->id,
                'route' => ($fare->route->fromCity?->code ?? '?').' - '.($fare->route->toCity?->code ?? '?'),
                'selling_fare' => $fare->selling_fare,
                'offer_price' => $fare->offer_price,
                'airline' => $fare->airline->name ?? '-',
                'ticket_type' => $fare->ticket_type?->value ?? 'regular',
            ]);

        $outboundFares = TicketFare::with([
            'airline',
            'route.fromCity',
            'route.toCity',
        ])->where('is_active', true)
            ->whereHas('route', fn ($q) => $q->where('route_type', 'oneway_outbound'))
            ->orderBy('id', 'desc')
            ->get()
            ->map(fn ($fare) => [
                'id' => $fare->id,
                'route' => ($fare->route->fromCity?->code ?? '?').' - '.($fare->route->toCity?->code ?? '?'),
                'selling_fare' => $fare->selling_fare,
                'offer_price' => $fare->offer_price,
                'airline' => $fare->airline->name ?? '-',
                'ticket_type' => $fare->ticket_type?->value ?? 'regular',
            ]);

        $usedFareIds = Package::pluck('ticket_fare_id')->toArray();

        $latestVisa = VisaSellingPrice::latest()->first();

        $stayDurationLimit = StayDurationLimit::getOrCreate();

        return view('settings.index', compact(
            'divisions',
            'districts',
            'flightDateGap',
            'packages',
            'ticketFares',
            'inboundFares',
            'outboundFares',
            'latestVisa',
            'usedFareIds',
            'stayDurationLimit'
        ));
    }

    public function updateFlightDateGap(Request $request)
    {
        $validated = $request->validate([
            'gap' => 'required|integer|min:1',
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
            $rules['ticket_fare_id'] = ['required', 'integer', 'exists:ticket_fares,id'];
            $rules['ticket_fare_inbound_id'] = ['nullable', 'integer', 'exists:ticket_fares,id'];
            $rules['ticket_fare_outbound_id'] = ['nullable', 'integer', 'exists:ticket_fares,id'];
        }

        $validated = $request->validate($rules);

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

        try {
            $validated['user_id'] = auth()->id() ?? 1;
            $validated['visa_selling_price_id'] = VisaSellingPrice::latest()->first()?->id;
            Package::create($validated);
            $tab = $request->input('tab', 'package-configuration');

            return redirect()->route('settings', ['tab' => $tab])->with('success', 'Package created successfully.');
        } catch (\Exception $e) {
            $message = $e instanceof QueryException
                ? DatabaseErrorHumanizer::humanize($e)
                : 'Failed to create package.';

            return redirect()->back()->with('error', $message)->withInput();
        }
    }

    public function updatePackage(Request $request, Package $package)
    {
        if ($package->isLocked()) {
            return redirect()->back()->with('error', 'This package cannot be edited because it has existing bookings.');
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
            $rules['ticket_fare_id'] = ['required', 'integer', 'exists:ticket_fares,id'];
            $rules['ticket_fare_inbound_id'] = ['nullable', 'integer', 'exists:ticket_fares,id'];
            $rules['ticket_fare_outbound_id'] = ['nullable', 'integer', 'exists:ticket_fares,id'];
        }

        $validated = $request->validate($rules);

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

        try {
            $validated['visa_selling_price_id'] = VisaSellingPrice::latest()->first()?->id;
            $package->update($validated);
            $tab = $request->input('tab', 'package-configuration');

            return redirect()->route('settings', ['tab' => $tab])->with('success', 'Package updated successfully.');
        } catch (\Exception $e) {
            $message = $e instanceof QueryException
                ? DatabaseErrorHumanizer::humanize($e)
                : 'Failed to update package.';

            return redirect()->back()->with('error', $message)->withInput();
        }
    }

    public function showPackage(Package $package)
    {
        $package->load([
            'ticketFare.route.fromCity',
            'ticketFare.route.toCity',
            'ticketFare.route.returnCity',
            'ticketFare.route.multiSegments.fromCity',
            'ticketFare.route.multiSegments.toCity',
            'ticketFare.route.transits.transitCity',
            'ticketFare.airline',
            'ticketFare.airlineClass',
            'ticketFare.groupTicket',
            'ticketFareInbound.route.fromCity',
            'ticketFareInbound.route.toCity',
            'ticketFareInbound.route.transits.transitCity',
            'ticketFareInbound.airline',
            'ticketFareInbound.airlineClass',
            'ticketFareInbound.groupTicket',
            'ticketFareOutbound.route.fromCity',
            'ticketFareOutbound.route.toCity',
            'ticketFareOutbound.route.transits.transitCity',
            'ticketFareOutbound.airline',
            'ticketFareOutbound.airlineClass',
            'ticketFareOutbound.groupTicket',
        ]);

        return view('package-configurations.show', compact('package'));
    }

    public function updateStayDurationLimit(Request $request)
    {
        $validated = $request->validate([
            'min_days' => 'required|integer|min:1',
            'max_days' => 'required|integer|min:2|gte:min_days',
        ]);

        $stayDurationLimit = StayDurationLimit::getOrCreate();
        $stayDurationLimit->update($validated);

        $tab = $request->input('tab', 'stay-duration-limit');

        return redirect()->route('settings', ['tab' => $tab])->with('success', 'Stay duration limit updated successfully');
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
