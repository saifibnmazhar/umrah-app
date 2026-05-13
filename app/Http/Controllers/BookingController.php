<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Passenger;
use App\Models\Customer;
use App\Models\District;
use App\Models\Document;
use App\Models\Package;
use App\Models\Office;
use App\Models\FingerprintCharge;
use App\Models\Route;
use App\Models\Airline;
use App\Models\TravelClass;
use App\Models\TicketFare;
use App\Models\VisaSellingPrice;
use App\Enums\PassengerType;
use App\Enums\ServiceRequired;
use App\Enums\FingerprintLocation;
use App\Enums\DiscountType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class BookingController extends Controller
{
    public function index(Request $request)
    {
        $tab = $request->get('tab', 'booking');
        
        $bookings = Booking::with(['customer', 'passengers'])
            ->orderBy('created_at', 'desc')
            ->paginate(10)
            ->withQueryString();

        $passengers = Passenger::with(['booking', 'booking.customer'])
            ->orderBy('created_at', 'desc')
            ->paginate(15)
            ->withQueryString();

        return view('bookings.index', compact('tab', 'bookings', 'passengers'));
    }

    public function create(Request $request)
    {
        $packageId = $request->query('package_id');
        $preSelectedPackageId = null;

        if ($packageId) {
            $package = Package::find($packageId);
            $preSelectedPackageId = $package ? $package->id : null;
        }

        $districts = District::orderBy('name')->get();
        $packages = Package::with('ticketFare')->orderBy('package_name')->get();
        $offices = Office::orderBy('name')->get();

        $ticketFares = TicketFare::with([
            'route.fromCity',
            'route.toCity',
            'route.returnCity',
            'route.multiSegments.fromCity',
            'route.multiSegments.toCity',
            'airline',
            'airlineClass.class',
            'groupTicket'
        ])->get()->map(function ($fare) {
            $routeCode = '';
            $routeType = $fare->route->route_type?->value;

            if ($routeType === 'multi_city') {
                $segments = $fare->route->multiSegments->map(function ($seg) {
                    return $seg->fromCity?->code . '-' . $seg->toCity?->code;
                })->toArray();
                $routeCode = implode(', ', $segments);
            } elseif ($routeType === 'round') {
                $routeCode = $fare->route->fromCity?->code . '-' .
                    $fare->route->toCity?->code . '-' .
                    $fare->route->returnCity?->code;
            } else {
                $routeCode = $fare->route->fromCity?->code . '-' . $fare->route->toCity?->code;
            }

            return [
                'id' => $fare->id,
                'route' => $routeCode,
                'airline' => $fare->airline->name,
                'airline_class' => $fare->airlineClass->class?->name,
                'ticket_type' => $fare->ticket_type->value,
                'selling_fare' => $fare->selling_fare,
                'offer_price' => $fare->offer_price,
                'available_seats' => $fare->groupTicket?->ticket_qty ?? null,
                'route_type' => $routeType,
                'flight_type' => $fare->route->flight_type?->value,
            ];
        });

        return view('bookings.create', compact(
            'districts', 'packages', 'offices', 'preSelectedPackageId', 'ticketFares'
        ));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'district_id' => 'nullable|exists:districts,id',
            'office_id' => 'nullable|exists:offices,id',
            'package_id' => 'nullable|exists:packages,id',
            'fingerprint_location' => 'nullable|in:Office,Home',
            'fingerprint_office' => 'nullable|string|max:255',
            'pax_qty' => 'nullable|integer|min:1',
            'discount_type' => 'nullable|in:fixed,percentage',
            'discount_value' => 'nullable|numeric|min:0',
            'remarks' => 'nullable|string|max:1000',
            'passengers' => 'required|array|min:1',
            'passengers.*.first_name' => 'required|string|max:255',
            'passengers.*.last_name' => 'required|string|max:255',
            'passengers.*.passport_no' => 'required|string|max:50',
            'passengers.*.date_of_birth' => 'required|date|before:today',
            'passengers.*.mobile_no' => 'nullable|string|max:20',
            'passengers.*.passport_expiry' => 'nullable|date',
            'passengers.*.service_required' => 'nullable|in:All,Visa Only,Ticket Only',
            'passengers.*.stay_duration' => 'nullable|integer|min:1',
            'passengers.*.flight_date_from' => 'nullable|date',
            'passengers.*.flight_date_to' => 'nullable|date|after:passengers.*.flight_date_from',
            'passengers.*.address' => 'nullable|string|max:500',
            'passengers.*.gender' => 'nullable|in:male,female',
            'passengers.*.ticket_fare_id' => 'nullable|exists:ticket_fares,id',
            'booking_customer_docs' => 'nullable|array',
            'booking_customer_docs.*' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
        ]);

        try {
            DB::beginTransaction();

            $booking = Booking::create([
                'user_id' => auth()->id(),
                'customer_id' => $validated['customer_id'],
                'district_id' => $validated['district_id'] ?? null,
                'office_id' => $validated['office_id'] ?? null,
                'package_id' => $validated['package_id'] ?? null,
                'fingerprint_location' => $validated['fingerprint_location'] ?? 'Office',
                'fingerprint_office' => $validated['fingerprint_office'] ?? null,
                'pax_qty' => count($validated['passengers']),
                'discount_type' => $validated['discount_type'] ?? null,
                'discount_value' => $validated['discount_value'] ?? 0,
                'remarks' => $validated['remarks'] ?? null,
            ]);

            if ($request->hasFile('booking_customer_docs')) {
                foreach ($request->file('booking_customer_docs') as $file) {
                    $booking->documents()->create([
                        'owner_type' => 'booking',
                        'owner_id' => $booking->id,
                        'file_path' => $file->store('booking-docs', 'public'),
                        'display_name' => $file->getClientOriginalName(),
                    ]);
                }
            }

            foreach ($validated['passengers'] as $passengerData) {
                $dob = Carbon::parse($passengerData['date_of_birth']);
                $ageInMonths = $dob->diffInMonths(Carbon::now());

                $passengerType = match(true) {
                    $ageInMonths < 24 => PassengerType::INFANT,
                    $ageInMonths < 144 => PassengerType::CHILD,
                    default => PassengerType::ADULT,
                };

                Passenger::create([
                    'booking_id' => $booking->id,
                    'first_name' => $passengerData['first_name'],
                    'last_name' => $passengerData['last_name'],
                    'passport_no' => $passengerData['passport_no'],
                    'date_of_birth' => $passengerData['date_of_birth'],
                    'gender' => $passengerData['gender'] ?? null,
                    'passenger_type' => $passengerType->value,
                    'passport_expiry' => $passengerData['passport_expiry'] ?? null,
                    'mobile_no' => $passengerData['mobile_no'] ?? null,
                    'service_required' => $passengerData['service_required'] ?? 'All',
                    'stay_duration' => $passengerData['stay_duration'] ?? 14,
                    'flight_date_from' => $passengerData['flight_date_from'] ?? null,
                    'flight_date_to' => $passengerData['flight_date_to'] ?? null,
                    'address' => $passengerData['address'] ?? null,
                    'ticket_fare_id' => $passengerData['ticket_fare_id'] ?? $booking->package?->ticket_fare_id,
                ]);
            }

            DB::commit();

            return redirect()->route('bookings.index')
                ->with('success', 'Booking created successfully with ' . count($validated['passengers']) . ' passenger(s)');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Failed to create booking: ' . $e->getMessage())->withInput();
        }
    }

    public function show(Booking $booking)
    {
        $booking->load(['customer', 'passengers', 'user', 'district', 'package', 'office']);
        return view('bookings.show', compact('booking'));
    }

    public function edit(Booking $booking)
    {
        $booking->load(['customer', 'passengers']);
        
        $districts = District::orderBy('name')->get();
        $packages = Package::orderBy('package_name')->get();
        $offices = Office::orderBy('name')->get();

        return view('bookings.edit', compact(
            'booking', 'districts', 'packages', 'offices'
        ));
    }

    public function update(Request $request, Booking $booking)
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'district_id' => 'nullable|exists:districts,id',
            'office_id' => 'nullable|exists:offices,id',
            'package_id' => 'nullable|exists:packages,id',
            'fingerprint_location' => 'nullable|in:Office,Home',
            'fingerprint_office' => 'nullable|string|max:255',
            'discount_type' => 'nullable|in:fixed,percentage',
            'discount_value' => 'nullable|numeric|min:0',
            'remarks' => 'nullable|string|max:1000',
        ]);

        try {
            $booking->update($validated);
            return redirect()->route('bookings.show', $booking->id)
                ->with('success', 'Booking updated successfully');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to update booking.')->withInput();
        }
    }

    public function destroy(Booking $booking)
    {
        try {
            $booking->passengers()->delete();
            $booking->delete();
            return redirect()->route('bookings.index')
                ->with('success', 'Booking deleted successfully');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to delete booking.');
        }
    }

    public function addPassenger(Request $request, Booking $booking)
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
            'gender' => 'nullable|in:male,female',
            'ticket_fare_id' => 'nullable|exists:ticket_fares,id',
        ]);

        $dob = Carbon::parse($validated['date_of_birth']);
        $ageInMonths = $dob->diffInMonths(Carbon::now());

        $passengerType = match(true) {
            $ageInMonths < 24 => PassengerType::INFANT,
            $ageInMonths < 144 => PassengerType::CHILD,
            default => PassengerType::ADULT,
        };

        $validated['booking_id'] = $booking->id;
        $validated['passenger_type'] = $passengerType->value;
        $validated['service_required'] = $validated['service_required'] ?? 'All';
        $validated['stay_duration'] = $validated['stay_duration'] ?? 14;
        $validated['ticket_fare_id'] = $validated['ticket_fare_id'] ?? $booking->package?->ticket_fare_id;

        $passenger = Passenger::create($validated);

        $booking->update(['pax_qty' => $booking->passengers()->count()]);

        return response()->json([
            'success' => true,
            'message' => 'Passenger added successfully',
            'passenger' => $passenger
        ]);
    }

    public function removePassenger(Booking $booking, Passenger $passenger)
    {
        if ($passenger->booking_id !== $booking->id) {
            return response()->json(['success' => false, 'message' => 'Passenger does not belong to this booking'], 403);
        }

        try {
            $passenger->delete();
            $booking->update(['pax_qty' => $booking->passengers()->count()]);
            return response()->json(['success' => true, 'message' => 'Passenger removed successfully']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to remove passenger'], 500);
        }
    }

    public function calculatePassengerType(Request $request)
    {
        $dateOfBirth = $request->input('date_of_birth');
        
        if (!$dateOfBirth) {
            return response()->json(['passenger_type' => null]);
        }

        $dob = Carbon::parse($dateOfBirth);
        $ageInMonths = $dob->diffInMonths(Carbon::now());

        $passengerType = match(true) {
            $ageInMonths < 24 => PassengerType::INFANT,
            $ageInMonths < 144 => PassengerType::CHILD,
            default => PassengerType::ADULT,
        };

        return response()->json([
            'passenger_type' => $passengerType->value,
            'age_in_months' => $ageInMonths,
        ]);
    }

    public function getFingerprintCharge(Request $request)
    {
        $districtId = $request->input('district_id');
        $location = $request->input('location', 'Office');

        if (!$districtId) {
            return response()->json(['charge' => 0]);
        }

        $fingerprintCharge = FingerprintCharge::where('district_id', $districtId)->first();

        if (!$fingerprintCharge) {
            return response()->json(['charge' => 0]);
        }

        $charge = $location === 'Home' ? $fingerprintCharge->fingerprint_charge : 0;

        return response()->json(['charge' => $charge]);
    }
}