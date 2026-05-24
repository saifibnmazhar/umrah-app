<?php

namespace App\Http\Controllers;

use App\Models\Route;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RouteController extends Controller
{
    public function index()
    {
        $routes = Route::with(['airline', 'fromCity', 'toCity', 'returnCity', 'multiSegments', 'transits'])
            ->orderBy('id', 'desc')
            ->paginate(15)
            ->withQueryString();
        
        return view('routes.index', compact('routes'));
    }

    public function create()
    {
        return view('routes.create');
    }

public function store(Request $request)
    {
        $routeType = $request->route_type;
        
        $rules = [
            'airline_id' => 'required|exists:airlines,id',
            'route_type' => 'required|in:oneway_inbound,oneway_outbound,round,multi_city',
            'flight_type' => 'required|in:direct,transit',
            'additional_gap' => 'nullable|integer|min:0',
        ];

        if ($routeType !== 'multi_city') {
            $rules['from_city_id'] = 'required|exists:city_codes,id';
            $rules['to_city_id'] = 'required|exists:city_codes,id';
        }

        if ($routeType === 'round') {
            $rules['return_city_id'] = 'required|exists:city_codes,id';
        }

        $validated = $request->validate($rules);

        try {
            return DB::transaction(function () use ($request, $routeType, $validated) {
                $route = Route::create($validated);

                if ($routeType === 'multi_city' && $request->has('segments')) {
                    foreach ($request->segments as $segment) {
                        $route->multiSegments()->create($segment);
                    }
                }

                if ($validated['flight_type'] === 'transit' && $request->has('transits')) {
                    foreach ($request->transits as $index => $transit) {
                        if (empty($transit['transit_city_id'])) {
                            continue;
                        }
                        $hours = (int) ($transit['transit_hours'] ?? 0);
                        $minutes = (int) ($transit['transit_minutes'] ?? 0);
                        $transit['transit_time'] = ($hours * 60) + $minutes;
                        unset($transit['transit_hours'], $transit['transit_minutes']);

                        if (!isset($transit['route_direction'])) {
                            $transit['route_direction'] = ($index === 0) ? 'inbound' : 'outbound';
                        }

                        $route->transits()->create($transit);
                    }
                }

                return redirect()->route('routes.index')->with('success', 'Route created successfully.');
            });
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to create route.')->withInput();
        }
    }

    public function show(Route $route)
    {
        $route->load(['airline', 'fromCity', 'toCity', 'returnCity', 'multiSegments.fromCity', 'multiSegments.toCity', 'transits.transitCity']);
        
        return view('routes.show', compact('route'));
    }

    public function edit(Route $route)
    {
        $route->load(['multiSegments', 'transits']);
        
        return view('routes.edit', compact('route'));
    }

    public function update(Request $request, Route $route)
    {
        $routeType = $request->route_type;
        
        $rules = [
            'airline_id' => 'required|exists:airlines,id',
            'route_type' => 'required|in:oneway_inbound,oneway_outbound,round,multi_city',
            'flight_type' => 'required|in:direct,transit',
            'additional_gap' => 'nullable|integer|min:0',
        ];

        if ($routeType !== 'multi_city') {
            $rules['from_city_id'] = 'required|exists:city_codes,id';
            $rules['to_city_id'] = 'required|exists:city_codes,id';
        }

        if ($routeType === 'round') {
            $rules['return_city_id'] = 'required|exists:city_codes,id';
        }

        $validated = $request->validate($rules);

        try {
            return DB::transaction(function () use ($request, $routeType, $validated, $route) {
                $route->update($validated);

                $route->multiSegments()->delete();
                $route->transits()->delete();

                if ($routeType === 'multi_city' && $request->has('segments')) {
                    foreach ($request->segments as $segment) {
                        $route->multiSegments()->create($segment);
                    }
                }

                if ($validated['flight_type'] === 'transit' && $request->has('transits')) {
                    foreach ($request->transits as $index => $transit) {
                        if (empty($transit['transit_city_id'])) {
                            continue;
                        }
                        $hours = (int) ($transit['transit_hours'] ?? 0);
                        $minutes = (int) ($transit['transit_minutes'] ?? 0);
                        $transit['transit_time'] = ($hours * 60) + $minutes;
                        unset($transit['transit_hours'], $transit['transit_minutes']);

                        if (!isset($transit['route_direction'])) {
                            $transit['route_direction'] = ($index === 0) ? 'inbound' : 'outbound';
                        }

                        $route->transits()->create($transit);
                    }
                }

                return redirect()->route('routes.index')->with('success', 'Route updated successfully.');
            });
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to update route.')->withInput();
        }
    }

    public function destroy(Route $route)
    {
        try {
            $route->delete();
            return redirect()->route('routes.index')->with('success', 'Route deleted successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to delete route.');
        }
    }
}