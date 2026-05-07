<?php

namespace App\Http\Controllers;

use App\Models\Airline;
use Illuminate\Http\Request;

class AirlineController extends Controller
{
    public function index()
    {
        $airlines = Airline::orderBy('name')->paginate(10)->withQueryString();
        return view('airlines.index', compact('airlines'));
    }

    public function create()
    {
        return view('airlines.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:airlines,name',
            'code' => 'required|string|max:50|unique:airlines,code',
        ]);

        try {
            Airline::create($validated);
            return redirect()->route('airlines.index')->with('success', 'Airline created successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to create airline.')->withInput();
        }
    }

    public function edit(Airline $airline)
    {
        return view('airlines.edit', compact('airline'));
    }

    public function update(Request $request, Airline $airline)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:airlines,name,' . $airline->id,
            'code' => 'required|string|max:50|unique:airlines,code,' . $airline->id,
        ]);

        try {
            $airline->update($validated);
            return redirect()->route('airlines.index')->with('success', 'Airline updated successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to update airline.')->withInput();
        }
    }

    public function destroy(Airline $airline)
    {
        try {
            $airline->delete();
            return redirect()->route('airlines.index')->with('success', 'Airline deleted successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to delete airline.');
        }
    }
}