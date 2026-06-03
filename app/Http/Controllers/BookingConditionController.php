<?php

namespace App\Http\Controllers;

use App\Models\BookingCondition;
use Illuminate\Http\Request;

class BookingConditionController extends Controller
{
    public function index()
    {
        $bookingConditions = BookingCondition::orderBy('sort_order')->orderBy('title')->paginate(10)->withQueryString();
        return view('booking-conditions.index', compact('bookingConditions'));
    }

    public function create()
    {
        return view('booking-conditions.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        try {
            BookingCondition::create($validated);
            return redirect()->route('booking-conditions.index')->with('success', 'Booking condition created successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to create booking condition.')->withInput();
        }
    }

    public function edit(BookingCondition $bookingCondition)
    {
        return view('booking-conditions.edit', compact('bookingCondition'));
    }

    public function update(Request $request, BookingCondition $bookingCondition)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        try {
            $bookingCondition->update($validated);
            return redirect()->route('booking-conditions.index')->with('success', 'Booking condition updated successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to update booking condition.')->withInput();
        }
    }

    public function destroy(BookingCondition $bookingCondition)
    {
        try {
            $bookingCondition->delete();
            return redirect()->route('booking-conditions.index')->with('success', 'Booking condition deleted successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to delete booking condition.');
        }
    }
}
