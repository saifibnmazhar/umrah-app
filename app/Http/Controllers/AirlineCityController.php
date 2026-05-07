<?php

namespace App\Http\Controllers;

use App\Models\Airline;
use App\Models\AirlineCity;
use App\Models\CityCode;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AirlineCityController extends Controller
{
    public function index()
    {
        $airlineCities = AirlineCity::with(['airline', 'cityCode'])
            ->orderBy('id')
            ->paginate(10)
            ->withQueryString();
        return view('airline-cities.index', compact('airlineCities'));
    }

    public function create()
    {
        $airlines = Airline::orderBy('name')->get();
        $cityCodes = CityCode::orderBy('code')->get();
        return view('airline-cities.create', compact('airlines', 'cityCodes'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'airline_id' => 'required|integer|exists:airlines,id',
            'city_code_id' => [
                'required',
                'integer',
                'exists:city_codes,id',
                Rule::unique('airline_cities')->where(function ($query) use ($request) {
                    return $query->where('airline_id', $request->airline_id)
                        ->where('city_code_id', $request->city_code_id);
                }),
            ],
        ]);

        try {
            AirlineCity::create($validated);
            return redirect()->route('airline-cities.index')->with('success', 'Airline city created successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to create airline city.')->withInput();
        }
    }

    public function edit(AirlineCity $airlineCity)
    {
        $airlines = Airline::orderBy('name')->get();
        $cityCodes = CityCode::orderBy('code')->get();
        return view('airline-cities.edit', compact('airlineCity', 'airlines', 'cityCodes'));
    }

    public function update(Request $request, AirlineCity $airlineCity)
    {
        $validated = $request->validate([
            'airline_id' => 'required|integer|exists:airlines,id',
            'city_code_id' => [
                'required',
                'integer',
                'exists:city_codes,id',
                Rule::unique('airline_cities')->where(function ($query) use ($request) {
                    return $query->where('airline_id', $request->airline_id)
                        ->where('city_code_id', $request->city_code_id);
                })->ignore($airlineCity->id),
            ],
        ]);

        try {
            $airlineCity->update($validated);
            return redirect()->route('airline-cities.index')->with('success', 'Airline city updated successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to update airline city.')->withInput();
        }
    }

    public function destroy(AirlineCity $airlineCity)
    {
        try {
            $airlineCity->delete();
            return redirect()->route('airline-cities.index')->with('success', 'Airline city deleted successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to delete airline city.');
        }
    }
}