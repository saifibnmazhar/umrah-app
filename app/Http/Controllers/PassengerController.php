<?php

namespace App\Http\Controllers;

use App\Models\Passenger;
use App\Models\Booking;
use App\Models\Document;
use App\Models\Package;
use App\Models\TicketFare;
use App\Models\VisaAgent;
use App\Models\VisaSellingPrice;
use App\Models\VisaSubmission;
use App\Enums\PassengerType;
use App\Services\BookingService;
use App\Services\CurrencyRateService;
use App\Services\InvoiceService;
use App\Traits\ConvertsDocumentsToPdf;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PassengerController extends Controller
{
    use ConvertsDocumentsToPdf;

    public function __construct(
        private BookingService $bookingService,
        private InvoiceService $invoiceService,
    ) {}

    private function ensureBranchAccess(Passenger $passenger): void
    {
        if (auth()->user()->branch_id
            && auth()->user()->branch_id !== $passenger->booking->booking_branch_id
            && auth()->user()->branch_id !== $passenger->booking->fingerprint_branch_id) {
            abort(403);
        }
    }

    private function isGlobalNonAdmin(): bool
    {
        $user = auth()->user();
        return !$user->branch_id
            && !$user->hasRole('Super Admin')
            && !$user->hasRole('Co Admin');
    }

    public function show(Passenger $passenger)
    {
        $this->ensureBranchAccess($passenger);
        
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
            'documents',
            'visaSubmission.visaAgent.visaAgentCost',
            'visaSubmission.visaAgent.commissionAgents',
            'visaSubmission.commissionAgent',
            'visaSubmission.visaSellingPrice',
            'visaSubmission.cancelledSubmissions',
            'visaSubmission.cancelledSubmission',
            'visaSubmission.logs.user',
            'latestIssuedTicket',
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

        $ticketFare = 0;
        if ($passenger->ticketFare) {
            $baseFare = (float) $passenger->ticketFare->selling_fare;
            $passengerType = $passenger->passenger_type;
            if ($passengerType instanceof \BackedEnum) {
                $passengerType = $passengerType->value;
            }
            $ticketFare = match (strtolower($passengerType ?? '')) {
                'child' => $baseFare * ((float) $passenger->ticketFare->child_fare_percentage) / 100,
                'infant' => $baseFare * ((float) $passenger->ticketFare->infant_fare_percentage) / 100,
                default => $baseFare,
            };
        }
        $visaCost = $passenger->booking?->package?->visaSellingPrice?->selling_price ?? 0;
        $fingerprintCost = ($passenger->booking?->fingerprint_location === 'home' && $passenger->booking?->fingerprintCharge)
            ? $passenger->booking->fingerprintCharge->fingerprint_charge
            : 0;
        $due = $passenger->booking?->invoice?->balance ?? 0;
        $paid = $passenger->booking?->invoice?->paid_amount ?? 0;

        $visaAgents = VisaAgent::with(['visaAgentCost', 'commissionAgents'])
            ->orderBy('name')
            ->get()
            ->map(fn($a) => [
                'id' => $a->id,
                'name' => $a->name,
                'cost' => (float)($a->visaAgentCost?->visa_agent_cost ?? 0),
                'commission_agents' => $a->commissionAgents->map(fn($ca) => [
                    'id' => $ca->id,
                    'name' => $ca->name,
                ]),
            ]);

        $canEditVisa = auth()->user()->roles->pluck('name')
            ->intersect(['Super Admin', 'Co Admin', 'Visa Admin'])
            ->isNotEmpty();

        $historyRows = [];
        $visaSubmission = $passenger->visaSubmission;

        if ($visaSubmission) {
            $statusLogs = $visaSubmission->logs()
                ->orderBy('created_at', 'asc')
                ->get()
                ->filter(fn($log) => isset($log->new_values['status']));

            $agentIds = $statusLogs->map(fn($l) => $l->new_values['visa_agent_id'] ?? null)
                ->filter()->unique()->values()->toArray();
            $agentLookup = VisaAgent::whereIn('id', $agentIds)->get()->keyBy('id');

            $runningAgentName = 'N/A';
            $runningAgentCost = null;
            $runningAdditional = null;
            $runningCommission = null;

            $cancelledQueue = $visaSubmission->cancelledSubmissions
                ->sortBy('created_at')
                ->values();
            $cancelledIdx = 0;

            $historyRows[] = [
                'date' => $visaSubmission->created_at,
                'agent' => 'N/A',
                'agent_cost' => null,
                'add_cost' => null,
                'agent_commission' => null,
                'status' => 'pending',
                'cancellation_fee' => null,
            ];

            foreach ($statusLogs as $log) {
                $nv = $log->new_values;

                if (array_key_exists('visa_agent_id', $nv)) {
                    $runningAgentName = $nv['visa_agent_id']
                        ? ($agentLookup[$nv['visa_agent_id']]?->name ?? 'N/A')
                        : 'N/A';
                }
                if (array_key_exists('net_visa_cost', $nv)) {
                    $runningAgentCost = is_numeric($nv['net_visa_cost']) ? (float)$nv['net_visa_cost'] : null;
                }
                if (array_key_exists('additional_cost', $nv)) {
                    $runningAdditional = is_numeric($nv['additional_cost']) ? (float)$nv['additional_cost'] : null;
                }
                if (array_key_exists('agent_commission', $nv)) {
                    $runningCommission = is_numeric($nv['agent_commission']) ? (float)$nv['agent_commission'] : null;
                }

                $caFee = null;
                if (($nv['status'] ?? '') === 'cancelled' && isset($cancelledQueue[$cancelledIdx])) {
                    $caFee = (float)$cancelledQueue[$cancelledIdx]->cancellation_fee ?: null;
                    $cancelledIdx++;
                }

                $historyRows[] = [
                    'date' => $log->created_at,
                    'agent' => $runningAgentName,
                    'agent_cost' => $runningAgentCost,
                    'add_cost' => $runningAdditional,
                    'agent_commission' => $runningCommission,
                    'status' => $nv['status'] ?? $visaSubmission->status?->value,
                    'cancellation_fee' => $caFee,
                ];
            }
        }

        $currencyRateService = app(CurrencyRateService::class);
        $booking = $passenger->booking;
        $rate = $booking?->currencyRate?->rate
            ?? $currencyRateService->getRateForDate($booking?->created_at)?->rate
            ?? 0;

        return view('passengers.show', compact('passenger', 'routeDisplay', 'ticketFare', 'visaCost', 'fingerprintCost', 'due', 'paid', 'visaAgents', 'canEditVisa', 'historyRows', 'rate'));
    }

    public function edit(Passenger $passenger)
    {
        $this->ensureBranchAccess($passenger);

        if ($this->isGlobalNonAdmin() && $passenger->booking->user_id !== auth()->id()) {
            abort(403);
        }

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

        $ticketFares = TicketFare::where('is_active', true)->with([
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

        $packages = Package::where('is_active', true)
            ->with(['ticketFare'])
            ->get()
            ->map(fn($p) => [
                'id' => $p->id,
                'package_name' => $p->package_name,
                'visa_selling_price' => $p->visaSellingPrice?->selling_price ?? 0,
                'service_charge' => $p->service_charge ?? 0,
                'ticket_fare_id' => $p->ticket_fare_id,
            ]);

        $booking = $passenger->booking;
        $rate = $booking?->currencyRate?->rate
            ?? app(CurrencyRateService::class)->getRateForDate($booking?->created_at)?->rate
            ?? 0;

        return view('passengers.edit', compact('passenger', 'ticketFares', 'packages', 'rate'));
    }

    public function uploadDocument(Request $request, Passenger $passenger)
    {
        
        $request->validate([
            'files' => 'required|array',
            'files.*' => 'file|mimes:pdf,jpg,jpeg,png|max:5120',
        ], [
            'files.*.max' => 'Each file must not exceed 5 MB.',
            'files.*.mimes' => 'Only PDF, JPG, JPEG, and PNG files are allowed.',
        ]);

        $totalSize = collect($request->file('files'))->sum(fn($f) => $f->getSize());
        if ($totalSize > 20 * 1024 * 1024) {
            return response()->json([
                'success' => false,
                'message' => 'The total size of all uploaded files must not exceed 20 MB.',
            ], 422);
        }

        try {
            $passenger->load('booking');
            $invoiceId = $passenger->booking->invoice_id ?? 'INV';
            $passengerName = $passenger->first_name . ' ' . $passenger->last_name;
            $passportId = $passenger->passport_no ?? 'NOPASS';
            $existingCount = $passenger->documents()->count();

            $documents = [];

            foreach ($request->file('files', []) as $index => $file) {
                $filename = Str::slug($passenger->first_name . ' ' . $passenger->last_name) . '_' . time() . '_' . Str::random(6) . '.' . $file->getClientOriginalExtension();
                $path = $file->storeAs('passenger-documents', $filename);

                $documents[] = Document::create([
                    'owner_type' => Passenger::class,
                    'owner_id' => $passenger->id,
                    'file_path' => $path,
                    'display_name' => "{$invoiceId} {$passengerName} {$passportId} " . ($existingCount + $index + 1),
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
                'message' => $e instanceof \Illuminate\Database\QueryException
                    ? \App\Exceptions\DatabaseErrorHumanizer::humanize($e)
                    : 'Failed to upload documents.',
            ], 500);
        }
    }

    public function downloadDocument(Passenger $passenger, Document $document)
    {
        
        if ($document->owner_id !== $passenger->id || $document->owner_type !== Passenger::class) {
            abort(403, 'Unauthorized');
        }

        $fullPath = $this->resolveDocumentPath($document);
        if (!$fullPath || !file_exists($fullPath)) {
            abort(404, 'File not found');
        }

        $tmpFile = storage_path('app/tmp/doc_' . uniqid() . '.pdf');
        $fileName = $document->display_name . '.pdf';

        try {
            $this->convertToPdf($fullPath, $tmpFile);
            $content = file_get_contents($tmpFile);
        } finally {
            if (file_exists($tmpFile)) {
                unlink($tmpFile);
            }
        }

        return response()->streamDownload(function () use ($content) {
            echo $content;
        }, $fileName);
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

    public function downloadAllDocuments(Passenger $passenger)
    {
        
        $passenger->load('booking.customer');
        $allDocs = $passenger->documents;

        abort_if($allDocs->isEmpty(), 404, 'No documents found');

        $passengerName = $passenger->first_name . ' ' . $passenger->last_name;
        $fileName = "{$passengerName} Documents.pdf";

        $tmpDir = storage_path('app/tmp/merge_' . uniqid());
        mkdir($tmpDir, 0755, true);

        $pdfFiles = [];

        try {
            foreach ($allDocs as $doc) {
                $fullPath = $this->resolveDocumentPath($doc);
                if (!$fullPath || !file_exists($fullPath)) continue;

                $tmpFile = $tmpDir . '/doc_' . $doc->id . '.pdf';
                $this->convertToPdf($fullPath, $tmpFile);
                $pdfFiles[] = $tmpFile;
            }

            abort_if(empty($pdfFiles), 404, 'No processable documents found');

            $outputPdf = $tmpDir . '/merged.pdf';
            $this->mergePdfs($pdfFiles, $outputPdf);

            $mergedContent = file_get_contents($outputPdf);
        } finally {
            array_map('unlink', glob($tmpDir . '/*'));
            rmdir($tmpDir);
        }

        return response()->streamDownload(function () use ($mergedContent) {
            echo $mergedContent;
        }, $fileName);
    }

    public function update(Request $request, Passenger $passenger)
    {
        if ($this->isGlobalNonAdmin() && $passenger->booking->user_id !== auth()->id()) {
            abort(403);
        }

        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'passport_no' => 'required|string|max:50',
            'date_of_birth' => 'required|date|before:today',
            'mobile_no' => 'nullable|string|max:20',
            'passport_expiry' => 'nullable|date',
            'service_required' => 'nullable|in:all,visa_only,ticket_only',
            'stay_duration' => 'nullable|integer|min:' . ($limits = \App\Models\StayDurationLimit::getOrCreate())->min_days . '|max:' . $limits->max_days,
            'flight_date_from' => 'nullable|date',
            'flight_date_to' => 'nullable|date',
            'address' => 'nullable|string|max:500',
            'passenger_type' => 'nullable|in:adult,child,infant',
            'gender' => 'nullable|in:male,female',
            'ticket_fare_id' => 'nullable|exists:ticket_fares,id',
        ]);

        try {
            $passenger->update($validated);

            $newServiceRequired = $validated['service_required'] ?? null;
            if ($newServiceRequired && $newServiceRequired !== 'ticket_only' && !$passenger->visaSubmission()->exists()) {
                $booking = $passenger->booking;
                VisaSubmission::create([
                    'passenger_id' => $passenger->id,
                    'visa_selling_price_id' => $booking?->package?->visa_selling_price_id ?? VisaSellingPrice::latest('id')->value('id'),
                    'status' => 'pending',
                ]);
            }

            $booking = $passenger->booking;
            if ($booking) {
                $booking = $booking->fresh();
                $this->bookingService->syncFinancials($booking);

                $invoice = $booking->invoice;
                if ($invoice) {
                    $invoice = $invoice->fresh();
                    $invoiceData = [
                        'total_amount' => (float) $invoice->total_amount,
                        'paid_amount' => (float) $invoice->paid_amount,
                        'balance' => (float) $invoice->balance,
                    ];
                }
            }

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Passenger updated successfully',
                    'passenger' => $passenger->fresh(),
                    'invoice' => $invoiceData ?? null,
                ]);
            }

            return redirect()->route('passengers.show', $passenger->id)
                ->with('success', 'Passenger updated successfully.');
        } catch (\Exception $e) {
            $dbMessage = $e instanceof \Illuminate\Database\QueryException
                ? \App\Exceptions\DatabaseErrorHumanizer::humanize($e)
                : 'Failed to update passenger.';

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $dbMessage,
                ], 500);
            }

            return redirect()->route('passengers.edit', $passenger->id)
                ->with('error', $dbMessage);
        }
    }

    public function destroy(Passenger $passenger)
    {
        
        try {
            $booking = $passenger->booking;
            $passenger->delete();
            
            if ($booking) {
                $booking->update(['pax_qty' => $booking->passengers()->count()]);
                $booking = $booking->fresh();
                $this->bookingService->syncFinancials($booking);

                $invoice = $booking->invoice;
                if ($invoice) {
                    $invoice = $invoice->fresh();
                    $invoiceData = [
                        'total_amount' => (float) $invoice->total_amount,
                        'paid_amount' => (float) $invoice->paid_amount,
                        'balance' => (float) $invoice->balance,
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Passenger deleted successfully',
                'invoice' => $invoiceData ?? null,
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
            $ageInMonths < 19 => PassengerType::INFANT,
            $ageInMonths < 139 => PassengerType::CHILD,
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

        $passengers = Passenger::where(function ($q) use ($query) {
                $q->where('passport_no', 'like', "%{$query}%")
                    ->orWhere('first_name', 'like', "%{$query}%")
                    ->orWhere('last_name', 'like', "%{$query}%")
                    ->orWhere('mobile_no', 'like', "%{$query}%");
            })
            ->with(['booking', 'booking.customer'])
            ->limit(20)
            ->get();

        return response()->json($passengers);
    }

    public function toggleTicketHold(Passenger $passenger)
    {
        $this->ensureBranchAccess($passenger);

        if ($passenger->is_ticket_held) {
            $passenger->update([
                'is_ticket_held' => false,
                'ticket_held_by' => null,
                'ticket_held_at' => null,
            ]);
            $message = 'Ticket hold released';
        } else {
            $passenger->update([
                'is_ticket_held' => true,
                'ticket_held_by' => auth()->id(),
                'ticket_held_at' => now(),
            ]);
            $message = 'Ticket hold applied';
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'is_ticket_held' => $passenger->fresh()->is_ticket_held,
        ]);
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