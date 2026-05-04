<?php

namespace App\Http\Controllers;

use App\Models\District;
use Illuminate\Http\Request;

class DistrictController extends Controller
{
    public function index()
    {
        $districts = District::orderBy('name')->paginate(10);
        return view('districts.index', compact('districts'));
    }

    public function create()
    {
        return view('districts.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'division' => 'required|string|max:255',
        ]);

        try {
            District::create($validated);
            return redirect()->route('districts.index')->with('success', 'District created successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to create district.')->withInput();
        }
    }

    public function edit(District $district)
    {
        return view('districts.edit', compact('district'));
    }

    public function update(Request $request, District $district)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'division' => 'required|string|max:255',
        ]);

        try {
            $district->update($validated);
            return redirect()->route('districts.index')->with('success', 'District updated successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to update district.')->withInput();
        }
    }

    public function destroy(District $district)
    {
        try {
            $district->delete();
            return redirect()->route('districts.index')->with('success', 'District deleted successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to delete district.');
        }
    }
}