<?php

namespace App\Http\Controllers;

use App\Models\CityCode;
use Illuminate\Http\Request;

class CityCodeController extends Controller
{
    public function index()
    {
        $cityCodes = CityCode::orderBy('city_name')->paginate(10)->withQueryString();

        return view('city-codes.index', compact('cityCodes'));
    }

    public function create()
    {
        return view('city-codes.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'city_name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:city_codes,code',
            'country' => 'required|string|max:255',
        ]);

        try {
            $cityCode = CityCode::create($validated);
            if ($request->wantsJson()) {
                return response()->json(['success' => true, 'city' => $cityCode], 201);
            }

            return redirect()->route('city-codes.index')->with('success', 'City code created successfully.');
        } catch (\Exception $e) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'Failed to create city code.'], 500);
            }

            return redirect()->back()->with('error', 'Failed to create city code.')->withInput();
        }
    }

    public function edit(CityCode $cityCode)
    {
        return view('city-codes.edit', compact('cityCode'));
    }

    public function update(Request $request, CityCode $cityCode)
    {
        $validated = $request->validate([
            'city_name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:city_codes,code,'.$cityCode->id,
            'country' => 'required|string|max:255',
        ]);

        try {
            $cityCode->update($validated);

            return redirect()->route('city-codes.index')->with('success', 'City code updated successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to update city code.')->withInput();
        }
    }

    public function destroy(CityCode $cityCode)
    {
        try {
            $cityCode->delete();

            return redirect()->route('city-codes.index')->with('success', 'City code deleted successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to delete city code.');
        }
    }
}
