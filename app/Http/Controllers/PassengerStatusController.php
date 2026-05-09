<?php

namespace App\Http\Controllers;

use App\Models\PassengerStatus;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PassengerStatusController extends Controller
{
    public function index()
    {
        $passengerStatuses = PassengerStatus::orderBy('name')
            ->paginate(10)
            ->withQueryString();

        return view('passenger-statuses.index', compact('passengerStatuses'));
    }

    public function create()
    {
        return view('passenger-statuses.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:passenger_statuses,name',
            'description' => 'nullable|string|max:1000',
        ]);

        try {
            PassengerStatus::create($validated);
            return redirect()->route('passenger-statuses.index')->with('success', 'Passenger status created successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to create passenger status.')->withInput();
        }
    }

    public function edit(PassengerStatus $passengerStatus)
    {
        return view('passenger-statuses.edit', compact('passengerStatus'));
    }

    public function update(Request $request, PassengerStatus $passengerStatus)
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('passenger_statuses', 'name')->ignore($passengerStatus->id),
            ],
            'description' => 'nullable|string|max:1000',
        ]);

        try {
            $passengerStatus->update($validated);
            return redirect()->route('passenger-statuses.index')->with('success', 'Passenger status updated successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to update passenger status.')->withInput();
        }
    }

    public function destroy(PassengerStatus $passengerStatus)
    {
        try {
            $passengerStatus->delete();
            return redirect()->route('passenger-statuses.index')->with('success', 'Passenger status deleted successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to delete passenger status.');
        }
    }
}