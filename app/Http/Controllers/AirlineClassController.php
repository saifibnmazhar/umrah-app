<?php

namespace App\Http\Controllers;

use App\Models\Airline;
use App\Models\AirlineClass;
use App\Models\TravelClass;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AirlineClassController extends Controller
{
    public function index()
    {
        $airlineClasses = AirlineClass::with(['airline', 'travelClass'])
            ->orderBy('id')
            ->paginate(10)
            ->withQueryString();
        return view('airline-classes.index', compact('airlineClasses'));
    }

    public function create()
    {
        $airlines = Airline::orderBy('name')->get();
        $travelClasses = TravelClass::orderBy('name')->get();
        return view('airline-classes.create', compact('airlines', 'travelClasses'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'airline_id' => 'required|integer|exists:airlines,id',
            'class_id' => [
                'required',
                'integer',
                'exists:classes,id',
                Rule::unique('airline_classes')->where(function ($query) use ($request) {
                    return $query->where('airline_id', $request->airline_id)
                        ->where('class_id', $request->class_id);
                }),
            ],
        ]);

        try {
            AirlineClass::create($validated);
            return redirect()->route('airline-classes.index')->with('success', 'Airline class created successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to create airline class.')->withInput();
        }
    }

    public function edit(AirlineClass $airlineClass)
    {
        $airlines = Airline::orderBy('name')->get();
        $travelClasses = TravelClass::orderBy('name')->get();
        return view('airline-classes.edit', compact('airlineClass', 'airlines', 'travelClasses'));
    }

    public function update(Request $request, AirlineClass $airlineClass)
    {
        $validated = $request->validate([
            'airline_id' => 'required|integer|exists:airlines,id',
            'class_id' => [
                'required',
                'integer',
                'exists:classes,id',
                Rule::unique('airline_classes')->where(function ($query) use ($request) {
                    return $query->where('airline_id', $request->airline_id)
                        ->where('class_id', $request->class_id);
                })->ignore($airlineClass->id),
            ],
        ]);

        try {
            $airlineClass->update($validated);
            return redirect()->route('airline-classes.index')->with('success', 'Airline class updated successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to update airline class.')->withInput();
        }
    }

    public function destroy(AirlineClass $airlineClass)
    {
        try {
            $airlineClass->delete();
            return redirect()->route('airline-classes.index')->with('success', 'Airline class deleted successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to delete airline class.');
        }
    }
}