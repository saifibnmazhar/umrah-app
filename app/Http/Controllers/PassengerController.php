<?php

namespace App\Http\Controllers;

use App\Models\Passenger;
use App\Models\Booking;
use App\Models\Document;
use App\Models\Package;
use App\Models\TicketFare;
use App\Enums\PassengerType;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PassengerController extends Controller
{
    public function show(Passenger $passenger)
    {
        $passenger->load([
            'booking',
            'booking.customer',
            'booking.package',
            'booking.package.visaSellingPrice',
            'booking.invoice',
            'booking.fingerprintCharge',
            'booking.district',
            'status',
            'ticketFare',
            'ticketFare.airline',
            'ticketFare.airlineClass',
            'ticketFare.airlineClass.class',
            'ticketFare.route',
            'documents'
        ]);

        $routeDisplay = null;
        if ($passenger->ticketFare?->route) {
            $route = $passenger->ticketFare->route;
            $routeType = $route->route_type?->value;
            if ($routeType === 'multi_city') {
                $routeDisplay = $route->multiSegments->map(fn($s) => ($s->fromCity?->code ?? '?') . '-' . ($s->toCity?->code ?? '?'))->implode(', ');
            } elseif ($routeType === 'round') {
                $routeDisplay = ($route->fromCity?->code ?? '?') . ' → ' . ($route->toCity?->code ?? '?') . ' → ' . ($route->returnCity?->code ?? '?');
            } else {
                $routeDisplay = ($route->fromCity?->code ?? '?') . ' → ' . ($route->toCity?->code ?? '?');
            }
        }

        $ticketFare = $passenger->ticketFare?->selling_fare ?? 0;
        $visaCost = $passenger->booking?->package?->visaSellingPrice?->selling_price ?? 0;
        $fingerprintCost = ($passenger->booking?->fingerprint_location === 'home' && $passenger->booking?->fingerprintCharge)
            ? $passenger->booking->fingerprintCharge->fingerprint_charge
            : 0;
        $due = $passenger->booking?->invoice?->balance ?? 0;
        $paid = $passenger->booking?->invoice?->paid_amount ?? 0;

        return view('passengers.show', compact('passenger', 'routeDisplay', 'ticketFare', 'visaCost', 'fingerprintCost', 'due', 'paid'));
    }

    public function edit(Passenger $passenger)
    {
        $passenger->load([
            'booking',
            'booking.package',
            'booking.package.visaSellingPrice',
            'status',
            'ticketFare',
            'ticketFare.airline',
            'ticketFare.airlineClass',
            'ticketFare.airlineClass.class',
            'ticketFare.route',
            'documents'
        ]);

        $ticketFares = TicketFare::with([
            'route.fromCity',
            'route.toCity',
            'route.returnCity',
            'route.multiSegments.fromCity',
            'route.multiSegments.toCity',
            'airline',
            'airlineClass.class',
            'groupTicket',
            'baggageAllowances',
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
                'airline' => $fare->airline?->name ?? '',
                'airline_class' => $fare->airlineClass?->class?->name ?? '',
                'ticket_type' => $fare->ticket_type->value,
                'selling_fare' => $fare->selling_fare,
                'child_fare_percentage' => $fare->child_fare_percentage,
                'infant_fare_percentage' => $fare->infant_fare_percentage,
                'offer_price' => $fare->offer_price,
                'available_seats' => $fare->groupTicket?->ticket_qty ?? null,
                'route_type' => $routeType,
                'flight_type' => $fare->route->flight_type?->value,
                'baggage_allowances' => $fare->baggageAllowances->map(function ($ba) {
                    $pt = $ba->passenger_type;
                    return [
                        'passenger_type' => $pt instanceof \BackedEnum ? $pt->value : (string) $pt,
                        'travel_direction' => $ba->travel_direction,
                        'allowance' => $ba->allowance,
                    ];
                })->values()->toArray(),
            ];
        });

        $packages = Package::with(['ticketFare'])
            ->get()
            ->map(fn($p) => [
                'id' => $p->id,
                'package_name' => $p->package_name,
                'visa_selling_price' => $p->visaSellingPrice?->selling_price ?? 0,
                'service_charge' => $p->service_charge ?? 0,
                'ticket_fare_id' => $p->ticket_fare_id,
            ]);

        return view('passengers.edit', compact('passenger', 'ticketFares', 'packages'));
    }

    public function uploadDocument(Request $request, Passenger $passenger)
    {
        $request->validate([
            'files' => 'required|array',
            'files.*' => 'file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        try {
            $documents = [];

            foreach ($request->file('files', []) as $file) {
                $filename = Str::slug($passenger->first_name . ' ' . $passenger->last_name) . '_' . time() . '_' . Str::random(6) . '.' . $file->getClientOriginalExtension();
                $path = $file->storeAs('passenger-documents', $filename);

                $documents[] = Document::create([
                    'owner_type' => Passenger::class,
                    'owner_id' => $passenger->id,
                    'file_path' => $path,
                    'display_name' => $file->getClientOriginalName(),
                ]);
            }

            $count = count($documents);

            return response()->json([
                'success' => true,
                'message' => $count . ' document(s) uploaded successfully',
                'documents' => $documents,
                'count' => $count,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload documents: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function downloadDocument(Passenger $passenger, Document $document)
    {
        if ($document->owner_id !== $passenger->id || $document->owner_type !== Passenger::class) {
            abort(403, 'Unauthorized');
        }

        if (!Storage::exists($document->file_path)) {
            abort(404, 'File not found');
        }

        return Storage::download($document->file_path, $document->display_name);
    }

    public function destroyDocument(Passenger $passenger, Document $document)
    {
        if ($document->owner_id !== $passenger->id || $document->owner_type !== Passenger::class) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        try {
            if (Storage::exists($document->file_path)) {
                Storage::delete($document->file_path);
            }
            $document->delete();

            return response()->json([
                'success' => true,
                'message' => 'Document deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete document',
            ], 500);
        }
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

        if (isset($validated['service_required'])) {
            $map = ['All' => 'all', 'Visa Only' => 'visa_only', 'Ticket Only' => 'ticket_only'];
            $validated['service_required'] = $map[$validated['service_required']] ?? $validated['service_required'];
        }

        try {
            $passenger->update($validated);

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Passenger updated successfully',
                    'passenger' => $passenger->fresh()
                ]);
            }

            return redirect()->route('passengers.show', $passenger->id)
                ->with('success', 'Passenger updated successfully.');
        } catch (\Exception $e) {
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to update passenger'
                ], 500);
            }

            return redirect()->route('passengers.edit', $passenger->id)
                ->with('error', 'Failed to update passenger: ' . $e->getMessage());
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

    public function updateStatus(Request $request, Passenger $passenger)
    {
        $validated = $request->validate([
            'passenger_status_id' => 'nullable|exists:passenger_statuses,id',
        ]);

        try {
            $passenger->update(['passenger_status_id' => $validated['passenger_status_id']]);
            return response()->json([
                'success' => true,
                'message' => 'Status updated successfully',
                'passenger_status_id' => $passenger->passenger_status_id
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update status'
            ], 500);
        }
    }
}