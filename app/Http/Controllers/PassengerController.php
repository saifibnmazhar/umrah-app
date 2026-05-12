<?php

namespace App\Http\Controllers;

use App\Models\Passenger;
use App\Models\Booking;
use App\Enums\PassengerType;
use Illuminate\Http\Request;
use Carbon\Carbon;

class PassengerController extends Controller
{
    public function show(Passenger $passenger)
    {
        $passenger->load(['booking', 'booking.customer', 'status']);
        return view('passengers.show', compact('passenger'));
    }

    public function update(Request $request, Passenger $passenger)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'passport_no' => 'required|string|max:50',
            'date_of_birth' => 'required|date|before:today',
            'mobile_no' => 'nullable|string|max:20',
            'passport_expiry' => 'nullable|date',
            'service_required' => 'nullable|in:All,Visa Only,Ticket Only',
            'stay_duration' => 'nullable|integer|min:1',
            'flight_date_from' => 'nullable|date',
            'flight_date_to' => 'nullable|date',
            'address' => 'nullable|string|max:500',
            'passenger_type' => 'nullable|in:adult,child,infant',
            'gender' => 'nullable|in:male,female',
            'ticket_fare_id' => 'nullable|exists:ticket_fares,id',
        ]);

        try {
            $passenger->update($validated);
            return response()->json([
                'success' => true,
                'message' => 'Passenger updated successfully',
                'passenger' => $passenger->fresh()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update passenger'
            ], 500);
        }
    }

    public function destroy(Passenger $passenger)
    {
        try {
            $booking = $passenger->booking;
            $passenger->delete();
            
            if ($booking) {
                $booking->update(['pax_qty' => $booking->passengers()->count()]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Passenger deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete passenger'
            ], 500);
        }
    }

    public function calculateAge(Request $request)
    {
        $dateOfBirth = $request->input('date_of_birth');
        
        if (!$dateOfBirth) {
            return response()->json([
                'age_in_months' => null,
                'passenger_type' => null
            ]);
        }

        $dob = Carbon::parse($dateOfBirth);
        $ageInMonths = $dob->diffInMonths(Carbon::now());

        $passengerType = match(true) {
            $ageInMonths < 24 => PassengerType::INFANT,
            $ageInMonths < 144 => PassengerType::CHILD,
            default => PassengerType::ADULT,
        };

        return response()->json([
            'age_in_months' => $ageInMonths,
            'passenger_type' => $passengerType->value,
            'date_of_birth' => $dateOfBirth,
        ]);
    }

    public function search(Request $request)
    {
        $query = $request->input('q');
        
        if (!$query || strlen($query) < 2) {
            return response()->json([]);
        }

        $passengers = Passenger::where('passport_no', 'like', "%{$query}%")
            ->orWhere('first_name', 'like', "%{$query}%")
            ->orWhere('last_name', 'like', "%{$query}%")
            ->orWhere('mobile_no', 'like', "%{$query}%")
            ->with(['booking', 'booking.customer'])
            ->limit(20)
            ->get();

        return response()->json($passengers);
    }
}