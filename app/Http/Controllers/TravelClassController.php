<?php

namespace App\Http\Controllers;

use App\Models\TravelClass;
use Illuminate\Http\Request;

class TravelClassController extends Controller
{
    public function index()
    {
        $travelClasses = TravelClass::orderBy('name')->paginate(10)->withQueryString();
        return view('classes.index', compact('travelClasses'));
    }

    public function create()
    {
        return view('classes.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:classes,name',
        ]);

        try {
            TravelClass::create($validated);
            return redirect()->route('classes.index')->with('success', 'Travel class created successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to create travel class.')->withInput();
        }
    }

    public function edit(TravelClass $class)
    {
        return view('classes.edit', compact('class'));
    }

    public function update(Request $request, TravelClass $class)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:classes,name,' . $class->id,
        ]);

        try {
            $class->update($validated);
            return redirect()->route('classes.index')->with('success', 'Travel class updated successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to update travel class.')->withInput();
        }
    }

    public function destroy(TravelClass $class)
    {
        try {
            $class->delete();
            return redirect()->route('classes.index')->with('success', 'Travel class deleted successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to delete travel class.');
        }
    }
}