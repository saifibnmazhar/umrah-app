<?php

namespace App\Http\Controllers;

use App\Models\Office;
use Illuminate\Http\Request;

class OfficeController extends Controller
{
    public function index()
    {
        $offices = Office::orderBy('name')->paginate(10)->withQueryString();
        return view('offices.index', compact('offices'));
    }

    public function create()
    {
        return view('offices.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'required|string|max:255',
            'contacts' => 'required|string|max:255',
        ]);

        try {
            Office::create($validated);
            return redirect()->route('offices.index')->with('success', 'Office created successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to create office.')->withInput();
        }
    }

    public function edit(Office $office)
    {
        return view('offices.edit', compact('office'));
    }

    public function update(Request $request, Office $office)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'required|string|max:255',
            'contacts' => 'required|string|max:255',
        ]);

        try {
            $office->update($validated);
            return redirect()->route('offices.index')->with('success', 'Office updated successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to update office.')->withInput();
        }
    }

    public function destroy(Office $office)
    {
        try {
            $office->delete();
            return redirect()->route('offices.index')->with('success', 'Office deleted successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to delete office.');
        }
    }
}