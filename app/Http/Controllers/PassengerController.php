<?php

namespace App\Http\Controllers;

use App\Enums\PassengerType;
use App\Exceptions\DatabaseErrorHumanizer;
use App\Models\Document;
use App\Models\FingerprintCharge;
use App\Models\Package;
use App\Models\Passenger;
use App\Models\StayDurationLimit;
use App\Models\TicketFare;
use App\Models\VisaAgent;
use App\Models\VisaSellingPrice;
use App\Models\VisaSubmission;
use App\Services\BookingService;
use App\Services\CurrencyRateService;
use App\Services\InvoiceService;
use App\Support\DiagnosticLogger;
use App\Traits\ConvertsDocumentsToPdf;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PassengerController extends Controller
{
    use ConvertsDocumentsToPdf;

    public function __construct(
        private BookingService $bookingService,
        private InvoiceService $invoiceService,
    ) {}

    public function show(Passenger $passenger)
    {
        $this->ensurePassengerBranchAccess($passenger);

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
            'ticketFareInbound.airline',
            'ticketFareInbound.airlineClass.class',
            'ticketFareInbound.route',
            'ticketFareOutbound.airline',
            'ticketFareOutbound.airlineClass.class',
            'ticketFareOutbound.route',
            'documents',
            'visaSubmission.visaAgent.visaAgentCost',
            'visaSubmission.visaAgent.commissionAgents',
            'visaSubmission.commissionAgent',
            'visaSubmission.visaSellingPrice',
            'visaSubmission.cancelledSubmissions',
            'visaSubmission.cancelledSubmission',
            'visaSubmission.logs.user',
            'latestIssuedTicket',
            'allIssuedTickets.ticketFare.airline',
            'allIssuedTickets.ticketFare.airlineClass.class',
            'allIssuedTickets.ticketFare.route',
        ]);

        $routeDisplay = null;
        if ($passenger->ticket_fare_inbound_id) {
            $inbound = $passenger->ticketFareInbound?->route;
            $outbound = $passenger->ticketFareOutbound?->route;
            $fmt = fn ($r) => $r ? (($r->fromCity?->code ?? '?').'-'.($r->toCity?->code ?? '?')) : '?';
            $routeDisplay = $fmt($inbound).' → '.$fmt($outbound);
        } elseif ($passenger->ticketFare?->route) {
            $route = $passenger->ticketFare->route;
            $routeType = $route->route_type?->value;
            if ($routeType === 'multi_city') {
                $routeDisplay = $route->multiSegments->map(fn ($s) => ($s->fromCity?->code ?? '?').'-'.($s->toCity?->code ?? '?'))->implode(', ');
            } elseif ($routeType === 'round') {
                $routeDisplay = ($route->fromCity?->code ?? '?').' → '.($route->toCity?->code ?? '?').' → '.($route->returnCity?->code ?? '?');
            } else {
                $routeDisplay = ($route->fromCity?->code ?? '?').' → '.($route->toCity?->code ?? '?');
            }
        }

        $inboundIssuedTicket = $passenger->allIssuedTickets
            ->first(fn ($t) => is_null($t->issue_type) || $t->issue_type === 'regular');
        $outboundIssuedTicket = $passenger->allIssuedTickets
            ->first(fn ($t) => $t->issue_type === 'pending_outbound');

        $ticketFare = 0;
        $passengerType = $passenger->passenger_type;
        if ($passengerType instanceof \BackedEnum) {
            $passengerType = $passengerType->value;
        }
        $passengerType = strtolower($passengerType ?? '');

        $fareBase = function ($fare) {
            if (! $fare) {
                return 0;
            }
            if ($fare->ticket_type?->value === 'offer') {
                return (float) ($fare->offer_price ?? $fare->selling_fare ?? $fare->net_fare ?? 0);
            }

            return (float) ($fare->selling_fare ?? $fare->net_fare ?? 0);
        };

        $fareForType = function ($fare, $pType) use ($fareBase) {
            if (! $fare) {
                return 0;
            }
            $base = $fareBase($fare);

            return match ($pType) {
                'child' => $base * ((float) $fare->child_fare_percentage) / 100,
                'infant' => $base * ((float) $fare->infant_fare_percentage) / 100,
                default => $base,
            };
        };

        if ($passenger->ticket_fare_inbound_id && $passenger->ticket_fare_outbound_id) {
            $ticketFare = $fareForType($passenger->ticketFareInbound, $passengerType)
                        + $fareForType($passenger->ticketFareOutbound, $passengerType);
        } elseif ($passenger->ticketFare) {
            $ticketFare = $fareForType($passenger->ticketFare, $passengerType);
        }
        $visaCost = $passenger->booking?->package?->visaSellingPrice?->selling_price ?? 0;
        $fingerprintCost = 0;
        $fpLocation = $passenger->booking?->fingerprint_location;
        if ($fpLocation instanceof \BackedEnum) {
            $fpLocation = $fpLocation->value;
        }
        if ($fpLocation && strtolower($fpLocation) !== 'office') {
            $fpCharge = FingerprintCharge::where('district_id', $passenger->booking?->district_id)->first();
            $fingerprintCost = $fpCharge ? (float) $fpCharge->fingerprint_charge : 0;
        }
        $due = $passenger->booking?->invoice?->balance ?? 0;
        $paid = $passenger->booking?->invoice?->paid_amount ?? 0;

        $visaAgents = VisaAgent::with(['visaAgentCost', 'commissionAgents'])
            ->orderBy('name')
            ->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'name' => $a->name,
                'cost' => (float) ($a->visaAgentCost?->visa_agent_cost ?? 0),
                'commission_agents' => $a->commissionAgents->map(fn ($ca) => [
                    'id' => $ca->id,
                    'name' => $ca->name,
                ]),
            ]);

        $canEditVisa = $this->canEditVisa();

        $historyRows = [];
        $visaSubmission = $passenger->visaSubmission;

        if ($visaSubmission) {
            $statusLogs = $visaSubmission->logs()
                ->orderBy('created_at', 'asc')
                ->get()
                ->filter(fn ($log) => isset($log->new_values['status']));

            $agentIds = $statusLogs->map(fn ($l) => $l->new_values['visa_agent_id'] ?? null)
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
                    $runningAgentCost = is_numeric($nv['net_visa_cost']) ? (float) $nv['net_visa_cost'] : null;
                }
                if (array_key_exists('additional_cost', $nv)) {
                    $runningAdditional = is_numeric($nv['additional_cost']) ? (float) $nv['additional_cost'] : null;
                }
                if (array_key_exists('agent_commission', $nv)) {
                    $runningCommission = is_numeric($nv['agent_commission']) ? (float) $nv['agent_commission'] : null;
                }

                $caFee = null;
                if (($nv['status'] ?? '') === 'cancelled' && isset($cancelledQueue[$cancelledIdx])) {
                    $caFee = (float) $cancelledQueue[$cancelledIdx]->cancellation_fee ?: null;
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

        return view('passengers.show', compact('passenger', 'routeDisplay', 'ticketFare', 'visaCost', 'fingerprintCost', 'due', 'paid', 'visaAgents', 'canEditVisa', 'historyRows', 'rate', 'inboundIssuedTicket', 'outboundIssuedTicket'));
    }

    public function edit(Passenger $passenger)
    {
        $this->ensurePassengerBranchAccess($passenger);

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
            'ticketFareInbound.route',
            'ticketFareInbound.airline',
            'ticketFareInbound.airlineClass.class',
            'ticketFareOutbound.route',
            'ticketFareOutbound.airline',
            'ticketFareOutbound.airlineClass.class',
            'documents',
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
                    return $seg->fromCity?->code.'-'.$seg->toCity?->code;
                })->toArray();
                $routeCode = implode(', ', $segments);
            } elseif ($routeType === 'round') {
                $routeCode = $fare->route->fromCity?->code.'-'.
                    $fare->route->toCity?->code.'-'.
                    $fare->route->returnCity?->code;
            } else {
                $routeCode = $fare->route->fromCity?->code.'-'.$fare->route->toCity?->code;
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
            ->with(['ticketFare', 'ticketFareInbound', 'ticketFareOutbound'])
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'package_name' => $p->package_name,
                'visa_selling_price' => $p->visaSellingPrice?->selling_price ?? 0,
                'service_charge' => $p->service_charge ?? 0,
                'ticket_fare_id' => $p->ticket_fare_id,
                'is_double_ticket' => (bool) $p->is_double_ticket,
                'ticket_fare_inbound_id' => $p->ticket_fare_inbound_id ? (string) $p->ticket_fare_inbound_id : null,
                'ticket_fare_outbound_id' => $p->ticket_fare_outbound_id ? (string) $p->ticket_fare_outbound_id : null,
            ]);

        $booking = $passenger->booking;
        $rate = $booking?->currencyRate?->rate
            ?? app(CurrencyRateService::class)->getRateForDate($booking?->created_at)?->rate
            ?? 0;

        return view('passengers.edit', compact('passenger', 'ticketFares', 'packages', 'rate'));
    }

    public function uploadDocument(Request $request, Passenger $passenger)
    {
        DiagnosticLogger::arrival($request, 'passengers.documents.store');

        $request->validate([
            'files' => 'required|array',
            'files.*' => 'file|mimes:pdf,jpg,jpeg,png|max:5120',
        ], [
            'files.*.max' => 'Each file must not exceed 5 MB.',
            'files.*.mimes' => 'Only PDF, JPG, JPEG, and PNG files are allowed.',
        ]);

        $totalSize = collect($request->file('files'))->sum(fn ($f) => $f->getSize());
        if ($totalSize > 20 * 1024 * 1024) {
            return response()->json([
                'success' => false,
                'message' => 'The total size of all uploaded files must not exceed 20 MB.',
            ], 422);
        }

        try {
            $passenger->load('booking');
            $invoiceId = $passenger->booking->invoice_id ?? 'INV';
            $passengerName = $passenger->first_name.' '.$passenger->last_name;
            $passportId = $passenger->passport_no ?? 'NOPASS';
            $maxNumber = 0;
            foreach ($passenger->documents as $doc) {
                $parts = explode(' ', $doc->display_name);
                $maxNumber = max($maxNumber, (int) end($parts));
            }

            $documents = [];

            foreach ($request->file('files', []) as $index => $file) {
                $filename = Str::slug($passenger->first_name.' '.$passenger->last_name).'_'.time().'_'.Str::random(6).'.'.$file->getClientOriginalExtension();
                $path = $file->storeAs('passenger-documents', $filename);

                $documents[] = Document::create([
                    'owner_type' => Passenger::class,
                    'owner_id' => $passenger->id,
                    'file_path' => $path,
                    'display_name' => "{$invoiceId} {$passengerName} {$passportId} ".($maxNumber + $index + 1),
                ]);
            }

            $count = count($documents);

            return response()->json([
                'success' => true,
                'message' => $count.' document(s) uploaded successfully',
                'documents' => $documents,
                'count' => $count,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e instanceof QueryException
                    ? DatabaseErrorHumanizer::humanize($e)
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
        if (! $fullPath || ! file_exists($fullPath)) {
            abort(404, 'File not found');
        }

        $tmpFile = storage_path('app/tmp/doc_'.uniqid().'.pdf');
        $fileName = $document->display_name.'.pdf';

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
        if (! $this->isAdmin()) {
            $this->ensurePassengerBranchAccess($passenger);
        }

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

        $passengerName = $passenger->first_name.' '.$passenger->last_name;
        $fileName = "{$passengerName} Documents.pdf";

        $tmpDir = storage_path('app/tmp/merge_'.uniqid());
        mkdir($tmpDir, 0755, true);

        $pdfFiles = [];

        try {
            foreach ($allDocs as $doc) {
                $fullPath = $this->resolveDocumentPath($doc);
                if (! $fullPath || ! file_exists($fullPath)) {
                    continue;
                }

                $tmpFile = $tmpDir.'/doc_'.$doc->id.'.pdf';
                $this->convertToPdf($fullPath, $tmpFile);
                $pdfFiles[] = $tmpFile;
            }

            abort_if(empty($pdfFiles), 404, 'No processable documents found');

            $outputPdf = $tmpDir.'/merged.pdf';
            $this->mergePdfs($pdfFiles, $outputPdf);

            $mergedContent = file_get_contents($outputPdf);
        } finally {
            array_map('unlink', glob($tmpDir.'/*'));
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
            'stay_duration' => 'nullable|integer|min:'.($limits = StayDurationLimit::getOrCreate())->min_days.'|max:'.$limits->max_days,
            'flight_date_from' => 'nullable|date',
            'flight_date_to' => 'nullable|date',
            'address' => 'nullable|string|max:500',
            'passenger_type' => 'nullable|in:adult,child,infant',
            'gender' => 'nullable|in:male,female',
            'ticket_fare_id' => 'nullable|exists:ticket_fares,id',
            'ticket_fare_inbound_id' => 'nullable|exists:ticket_fares,id',
            'ticket_fare_outbound_id' => 'nullable|exists:ticket_fares,id',
        ]);

        try {
            $passenger->update($validated);

            $newServiceRequired = $validated['service_required'] ?? null;
            if ($newServiceRequired && $newServiceRequired !== 'ticket_only' && ! $passenger->visaSubmission()->exists()) {
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
                $this->bookingService->syncFinancials($booking, 'passenger_updated');

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
            $dbMessage = $e instanceof QueryException
                ? DatabaseErrorHumanizer::humanize($e)
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
                $this->bookingService->syncFinancials($booking, 'passenger_removed');

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
                'message' => 'Failed to delete passenger',
            ], 500);
        }
    }

    public function calculateAge(Request $request)
    {
        $dateOfBirth = $request->input('date_of_birth');

        if (! $dateOfBirth) {
            return response()->json([
                'age_in_months' => null,
                'passenger_type' => null,
            ]);
        }

        $dob = Carbon::parse($dateOfBirth);
        $ageInMonths = $dob->diffInMonths(Carbon::now());

        $passengerType = match (true) {
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

        if (! $query || strlen($query) < 2) {
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
        $this->ensurePassengerBranchAccess($passenger);

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
                'passenger_status_id' => $passenger->passenger_status_id,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update status',
            ], 500);
        }
    }

    public function updateTicketRemarks(Request $request, Passenger $passenger)
    {
        $validated = $request->validate([
            'ticket_remarks' => 'nullable|string|max:65535',
        ]);

        $passenger->update(['ticket_remarks' => $validated['ticket_remarks'] ?? null]);

        return response()->json([
            'success' => true,
            'message' => 'Remarks updated successfully.',
        ]);
    }
}
