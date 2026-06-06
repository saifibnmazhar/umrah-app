<?php

namespace App\Http\Controllers;

use App\Models\FlightDateGap;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FlightDateGapController extends Controller
{
    public function index()
    {
        $gap = FlightDateGap::first();
        
        if (!$gap) {
            return redirect()->route('flight-date-gaps.create');
        }
        
        return redirect()->route('flight-date-gaps.edit', $gap->id);
    }

    public function create()
    {
        $existingGap = FlightDateGap::first();
        
        if ($existingGap) {
            return redirect()->route('flight-date-gaps.edit', $existingGap->id);
        }
        
        return view('flight-date-gap.create');
    }

    public function store(Request $request)
    {
        $existing = FlightDateGap::first();
        
        if ($existing) {
            return $this->update($request, $existing);
        }
        
        $validated = $request->validate([
            'gap' => 'required|integer|min:1|unique:flight_date_gaps,gap',
        ]);

        try {
            FlightDateGap::create($validated);
            return redirect()->route('flight-date-gaps.edit', FlightDateGap::first()->id)
                ->with('success', 'Flight date gap created successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to create flight date gap.')->withInput();
        }
    }

    public function edit(FlightDateGap $flightDateGap)
    {
        return view('flight-date-gap.edit', compact('flightDateGap'));
    }

    public function update(Request $request, FlightDateGap $flightDateGap)
    {
        $validated = $request->validate([
            'gap' => [
                'required',
                'integer',
                'min:0',
                Rule::unique('flight_date_gaps')->ignore($flightDateGap->id),
            ],
        ]);

        try {
            $flightDateGap->update($validated);
            $tab = $request->input('tab', 'flight-date-gap');
            return redirect()->route('settings', ['tab' => $tab])->with('success', 'Flight date gap updated successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to update flight date gap.')->withInput();
        }
    }

    public function destroy(FlightDateGap $flightDateGap)
    {
        try {
            $flightDateGap->delete();
            return redirect()->route('flight-date-gaps.create')
                ->with('success', 'Flight date gap deleted successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to delete flight date gap.');
        }
    }
}