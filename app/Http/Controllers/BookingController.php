<?php

namespace App\Http\Controllers;

use App\Enums\FingerprintLocation;
use App\Enums\FingerprintStatus;
use App\Enums\VisaStatus;
use App\Exceptions\DatabaseErrorHumanizer;
use App\Models\Bank;
use App\Models\Booking;
use App\Models\BookingCondition;
use App\Models\Branch;
use App\Models\CancelledSubmission;
use App\Models\CurrencyRate;
use App\Models\Customer;
use App\Models\District;
use App\Models\Document;
use App\Models\Fingerprint;
use App\Models\FingerprintCharge;
use App\Models\FingerprintDetail;
use App\Models\FlightDateGap;
use App\Models\Invoice;
use App\Models\IssuedTicket;
use App\Models\Package;
use App\Models\Passenger;
use App\Models\PassengerStatus;
use App\Models\Payment;
use App\Models\ReIssueRefundReason;
use App\Models\RescheduledFingerprint;
use App\Models\Route;
use App\Models\StayDurationLimit;
use App\Models\TicketAgent;
use App\Models\TicketFare;
use App\Models\TransactionType;
use App\Models\VisaAgent;
use App\Models\VisaSellingPrice;
use App\Models\VisaSubmission;
use App\Models\Voucher;
use App\Queries\BookingPassengerQuery;
use App\Services\BookingService;
use App\Services\CostTrackingService;
use App\Services\CurrencyRateService;
use App\Services\InvoiceService;
use App\Services\PaymentService;
use App\Services\ProfitCalculationService;
use App\Services\RefundCapService;
use App\Support\DiagnosticLogger;
use App\Traits\ConvertsDocumentsToPdf;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class BookingController extends Controller
{
    use ConvertsDocumentsToPdf;

    public function __construct(
        private BookingService $bookingService,
        private InvoiceService $invoiceService,
    ) {}

    private function isAdminRole(): bool
    {
        $user = auth()->user();

        return $user->hasRole('Super Admin') || $user->hasRole('Co Admin');
    }

    private function isBranchScoped(): bool
    {
        $user = auth()->user();

        return ! $this->isAdminRole()
            && ($user->hasRole('Branch Manager') || $user->hasRole('Branch Staff'));
    }

    private function isGlobalNonAdmin(): bool
    {
        return ! auth()->user()->branch_id && ! $this->isAdminRole();
    }

    private function resolveBookingBranch(Request $request, bool $forUpdate): int
    {
        $user = auth()->user();

        if (! $user->branch_id && $request->filled('booking_branch_id')) {
            return (int) $request->input('booking_branch_id');
        }

        if ($user->branch_id) {
            return (int) $user->branch_id;
        }

        abort(422, 'Your account is not assigned to a branch. Contact an administrator.');
    }

    private function ensureBranchAccess(Booking $booking): void
    {
        if (auth()->user()->branch_id
            && auth()->user()->branch_id !== $booking->booking_branch_id
            && auth()->user()->branch_id !== $booking->fingerprint_branch_id) {
            abort(403);
        }
    }

    private function ensureEditWindow(Booking $booking): void
    {
        if ($this->isAdminRole()) {
            return;
        }

        if ($booking->created_at->diffInHours(now()) >= 12) {
            abort(403, 'Edit window has expired. Bookings can only be edited within 12 hours of creation.');
        }
    }

    private function syncBookingFinancials(Booking $booking, ?string $reason = null): array
    {
        $this->bookingService->syncFinancials($booking, $reason);

        $invoice = $booking->invoice;
        if ($invoice) {
            $invoice = $invoice->fresh();

            return [
                'total_amount' => (float) $invoice->total_amount,
                'paid_amount' => (float) $invoice->paid_amount,
                'balance' => (float) $invoice->balance,
                'original_total' => (float) $booking->total_value,
                'discount_amount' => (float) $booking->discount_amount,
                'discount_type' => $booking->discount_type?->value,
                'discount_value' => (float) $booking->discount_value,
            ];
        }

        return [
            'total_amount' => 0,
            'paid_amount' => 0,
            'balance' => 0,
            'original_total' => (float) $booking->total_value,
            'discount_amount' => (float) $booking->discount_amount,
            'discount_type' => $booking->discount_type?->value,
            'discount_value' => (float) $booking->discount_value,
        ];
    }

    public function index(Request $request)
    {
        $tab = $request->get('tab', 'booking');

        $user = auth()->user();
        abort_unless($user, 401, 'Unauthenticated');
        $userBranchId = $user->branch_id;

        $bookingBranches = $userBranchId ? collect() : Branch::orderBy('name')->get(['id', 'name']);
        $selectedBranchId = $userBranchId ? null : $request->get('booking_branch_id');
        $selectedFingerprintStatus = $request->get('fingerprint_status');
        $selectedVisaStatus = $request->get('visa_status');
        $selectedTicketStatus = $request->get('ticket_status');
        $selectedVisaAgentId = $request->get('visa_agent_id');
        $selectedBookingDateFrom = $request->get('booking_date_from');
        $selectedBookingDateTo = $request->get('booking_date_to');
        $selectedFingerprintLocation = $request->get('fingerprint_location');
        $selectedBookingStatus = $request->get('booking_status') ?? 'active';
        $selectedPassengerStatus = $request->get('passenger_status');
        $selectedPackageId = $request->get('package_id');
        $selectedTicketAgentId = $request->get('ticket_agent_id');
        $selectedActualFlightFrom = $request->get('actual_flight_from');
        $selectedActualFlightTo = $request->get('actual_flight_to');
        $selectedReturnDateFrom = $request->get('return_date_from');
        $selectedReturnDateTo = $request->get('return_date_to');
        $selectedStatusChangeAction = $request->get('status_change_action');
        $selectedStatusChangeFrom = $request->get('status_change_from');
        $selectedStatusChangeTo = $request->get('status_change_to');
        $selectedPaymentWise = $request->get('payment_wise');

        $allRouteMaps = Route::with(['fromCity', 'toCity', 'returnCity', 'multiSegments.fromCity', 'multiSegments.toCity'])
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'display' => match ($r->route_type?->value) {
                    'multi_city' => $r->multiSegments->map(fn ($s) => ($s->fromCity?->code ?? '?').'-'.($s->toCity?->code ?? '?'))->implode(', '),
                    'round' => ($r->fromCity?->code ?? '?').'-'.($r->toCity?->code ?? '?').'-'.($r->returnCity?->code ?? '?'),
                    default => ($r->fromCity?->code ?? '?').'-'.($r->toCity?->code ?? '?'),
                },
                'route_type' => $r->route_type?->value,
                'flight_type' => $r->flight_type?->value,
                'airline_id' => $r->airline_id,
            ]);

        $routesList = $allRouteMaps->unique('display')->values();
        $routeDisplayMap = $allRouteMaps->groupBy('display')->map(fn ($g) => $g->pluck('id')->toArray());

        $selectedRouteDisplay = $request->input('route_display');
        if (! $selectedRouteDisplay && $request->filled('route_id')) {
            $oldRoute = $allRouteMaps->firstWhere('id', (int) $request->input('route_id'));
            $selectedRouteDisplay = $oldRoute['display'] ?? null;
        }

        $branchCounts = ! $userBranchId
            ? Booking::selectRaw('booking_branch_id, COUNT(*) as total')
                ->whereNotNull('booking_branch_id')
                ->groupBy('booking_branch_id')
                ->pluck('total', 'booking_branch_id')
                ->toArray()
            : [];
        $allBookingCount = ! $userBranchId ? Booking::count() : 0;

        $bookingQuery = Booking::with(['customer', 'passengers', 'fingerprintBranch', 'bookingBranch', 'invoice', 'district', 'package'])
            ->when($userBranchId, fn ($q) => $q->where(function ($q) {
                $q->where('booking_branch_id', auth()->user()->branch_id)
                    ->orWhere('fingerprint_branch_id', auth()->user()->branch_id);
            })
            )
            ->when($selectedBranchId, fn ($q) => $q->where('booking_branch_id', $selectedBranchId)
            )
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = $request->input('search');
                $q->where(function ($query) use ($search) {
                    $query->where('invoice_id', 'like', "%{$search}%")
                        ->orWhereHas('customer', fn ($q) => $q->where('mobile_no', 'like', "%{$search}%"))
                        ->orWhereHas('passengers', fn ($q) => $q->where('passport_no', 'like', "%{$search}%"));
                });
            })
            ->when($request->filled('booking_date_from'), fn ($q) => $q->whereDate('created_at', '>=', $request->input('booking_date_from'))
            )
            ->when($request->filled('booking_date_to'), fn ($q) => $q->whereDate('created_at', '<=', $request->input('booking_date_to'))
            )
            ->when($request->filled('fingerprint_location'), fn ($q) => $q->where('fingerprint_location', $request->input('fingerprint_location'))
            )
            ->when($selectedBookingStatus && $selectedBookingStatus !== 'all', function ($q) use ($selectedBookingStatus) {
                if ($selectedBookingStatus === 'active') {
                    $q->where('is_cancelled', false);
                } elseif ($selectedBookingStatus === 'cancellation_processing') {
                    $q->where('is_cancelled', true)
                        ->whereHas('cancelledBooking', fn ($q) => $q->where('status', 'cancellation processing'));
                } elseif ($selectedBookingStatus === 'cancelled') {
                    $q->where('is_cancelled', true)
                        ->where(function ($q) {
                            $q->whereDoesntHave('cancelledBooking')
                                ->orWhereHas('cancelledBooking', fn ($q) => $q->where('status', 'cancelled'));
                        });
                }
            })
            ->when($request->filled('payment_wise'), function ($q) use ($request) {
                $paymentWise = $request->input('payment_wise');
                $rate = (float) (app(CurrencyRateService::class)->getCurrentRateValue() ?? 0);
                $q->whereHas('invoice', function ($iq) use ($paymentWise, $rate) {
                    if ($paymentWise === 'clear') {
                        $iq->where('balance', '<=', 0);
                    } elseif ($paymentWise === 'due') {
                        $iq->where('balance', '>', 0);
                    } elseif ($paymentWise === 'due_below_1000') {
                        $iq->where('balance', '>', 0);
                        if ($rate > 0) {
                            $iq->whereRaw('balance * ? < 1000', [$rate]);
                        } else {
                            $iq->where('balance', '<', 1000);
                        }
                    } elseif ($paymentWise === 'due_above_1000') {
                        if ($rate > 0) {
                            $iq->whereRaw('balance * ? >= 1000', [$rate]);
                        } else {
                            $iq->where('balance', '>=', 1000);
                        }
                    }
                });
            })
            ->orderBy('created_at', 'desc');

        $totalBookingCount = (clone $bookingQuery)->count();
        $totalBookingPassengerCount = (clone $bookingQuery)->sum('pax_qty');

        $bookings = $bookingQuery->paginate(10)
            ->appends(['tab' => $tab])
            ->withQueryString();

        $canFilterByVisaAgent = auth()->user()->roles->pluck('name')
            ->intersect(['Super Admin', 'Co Admin', 'Visa Admin', 'Ticket Admin'])->isNotEmpty();
        $canFilterByTicketAgent = auth()->user()->roles->pluck('name')
            ->intersect(['Super Admin', 'Co Admin', 'Visa Admin', 'Ticket Admin'])->isNotEmpty();

        $passengerQuery = new BookingPassengerQuery($request);
        $passengersBase = $passengerQuery->getQuery();

        $totalPassengerCount = (clone $passengersBase)->count();

        $currencyRateService = app(CurrencyRateService::class);
        $firstRate = (float) ($currencyRateService->getFirstRate()?->rate ?? 0);

        $bookingIdsSub = (clone $passengersBase)->select('passengers.booking_id')->distinct();
        $bookingIdsSub->getQuery()->orders = [];

        $invoiceTotals = Invoice::whereIn('booking_id', $bookingIdsSub)
            ->selectRaw('COALESCE(SUM(total_amount), 0) as package, COALESCE(SUM(balance), 0) as due')
            ->first();
        $totalPackageValue = (float) ($invoiceTotals->package ?? 0);
        $totalDue = (float) ($invoiceTotals->due ?? 0);

        $bdtTotals = Invoice::whereIn('invoices.booking_id', $bookingIdsSub)
            ->leftJoin('bookings', 'bookings.id', '=', 'invoices.booking_id')
            ->leftJoin('currency_rates', 'currency_rates.id', '=', 'bookings.currency_rate_id')
            ->selectRaw('COALESCE(SUM(invoices.total_amount * COALESCE(currency_rates.rate, ?)), 0) as package_bdt, COALESCE(SUM(invoices.balance * COALESCE(currency_rates.rate, ?)), 0) as due_bdt', [$firstRate, $firstRate])
            ->first();
        $totalPackageBdt = (float) ($bdtTotals->package_bdt ?? 0);
        $totalDueBdt = (float) ($bdtTotals->due_bdt ?? 0);

        $passengers = new LengthAwarePaginator(collect(), 0, 15, 1);
        $passengerStatuses = PassengerStatus::all();
        $statusChangeOptions = $passengerStatuses->filter(fn ($s) => in_array($s->name, ['Cancel', 'Delivered', 'Hold'])
        )->values();
        $statusChangeOptions = $statusChangeOptions->concat(collect([
            (object) ['id' => 'visa_submitted', 'name' => 'Visa Submitted'],
            (object) ['id' => 'visa_issued', 'name' => 'Visa Issued'],
            (object) ['id' => 'ticket_issued', 'name' => 'Ticket Issued'],
        ]));

        $visaAgents = collect();
        if ($canFilterByVisaAgent) {
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
        }

        $ticketAgents = collect();
        if ($canFilterByTicketAgent) {
            $ticketAgents = TicketAgent::orderBy('name')->get();
        }

        $canEditVisa = auth()->user()->roles->pluck('name')->intersect(['Super Admin', 'Co Admin', 'Visa Admin'])->isNotEmpty();

        $fingerprintStatuses = FingerprintStatus::cases();
        $visaStatuses = VisaStatus::cases();
        $ticketStatuses = [
            ['value' => 'pending', 'label' => 'Pending'],
            ['value' => 'issued-inbound', 'label' => 'Issued - Inbound'],
            ['value' => 'issued-outbound', 'label' => 'Issued - Outbound'],
            ['value' => 'issued-both', 'label' => 'Issued - Both'],
            ['value' => 'awaiting-group', 'label' => 'Awaiting Group'],
            ['value' => 'awaiting-group-inbound', 'label' => 'Awaiting Group - Inbound'],
            ['value' => 'awaiting-group-outbound', 'label' => 'Awaiting Group - Outbound'],
            ['value' => 'awaiting-group-both', 'label' => 'Awaiting Group - Both'],
            ['value' => 'partial-re-issued', 'label' => 'Partial Re-Issued'],
            ['value' => 're-issued', 'label' => 'Re-Issued'],
            ['value' => 'partial-refunded', 'label' => 'Partial Refunded'],
            ['value' => 'refunded', 'label' => 'Refunded'],
        ];
        $fingerprintLocations = FingerprintLocation::cases();

        $reIssueReasons = ReIssueRefundReason::where('reason_of', 're-issue')->get();

        return view('bookings.index', compact(
            'tab', 'bookings', 'passengers', 'passengerStatuses', 'visaAgents', 'ticketAgents', 'canEditVisa',
            'canFilterByVisaAgent', 'canFilterByTicketAgent',
            'currencyRateService', 'bookingBranches', 'selectedBranchId', 'totalBookingCount',
            'totalBookingPassengerCount', 'branchCounts', 'allBookingCount',
            'selectedFingerprintStatus', 'selectedVisaStatus', 'selectedTicketStatus', 'selectedVisaAgentId',
            'selectedBookingDateFrom', 'selectedBookingDateTo', 'selectedFingerprintLocation',
            'selectedBookingStatus',
            'selectedPassengerStatus', 'selectedRouteDisplay', 'routesList', 'selectedPackageId', 'selectedTicketAgentId',
            'selectedActualFlightFrom', 'selectedActualFlightTo',
            'selectedReturnDateFrom', 'selectedReturnDateTo',
            'selectedStatusChangeAction', 'selectedStatusChangeFrom', 'selectedStatusChangeTo',
            'selectedPaymentWise',
            'statusChangeOptions',
            'fingerprintStatuses', 'visaStatuses', 'ticketStatuses', 'fingerprintLocations',
            'totalPassengerCount', 'totalPackageValue', 'totalDue', 'totalPackageBdt', 'totalDueBdt',
            'reIssueReasons'
        ));
    }

    public function passengerData(Request $request)
    {
        $query = (new BookingPassengerQuery($request))->getQuery();
        $page = $query->with([
            'booking' => fn ($q) => $q->with([
                'customer', 'invoice', 'package', 'currencyRate',
                'fingerprint', 'passengers', 'documents',
                'customer.documents', 'cancelledBooking',
            ]),
            'ticketFare' => fn ($q) => $q->with([
                'route.fromCity', 'route.toCity', 'route.returnCity',
                'route.multiSegments.fromCity', 'route.multiSegments.toCity',
                'airline', 'airlineClass.class', 'baggageAllowances', 'groupTicket',
            ]),
            'ticketFareInbound' => fn ($q) => $q->with([
                'route.fromCity', 'route.toCity', 'route.returnCity',
                'route.multiSegments.fromCity', 'route.multiSegments.toCity',
                'airline', 'airlineClass.class', 'baggageAllowances',
            ]),
            'ticketFareOutbound' => fn ($q) => $q->with([
                'route.fromCity', 'route.toCity', 'route.returnCity',
                'route.multiSegments.fromCity', 'route.multiSegments.toCity',
                'airline', 'airlineClass.class', 'baggageAllowances',
            ]),
            'status',
            'visaSubmission.visaAgent',
            'visaSubmission.visaSellingPrice',
            'visaSubmission.commissionAgent',
            'visaSubmission.cancelledSubmissions',
            'fingerprintDetail.fingerprint.fingerprintDetails',
            'fingerprintDetail.approvedLog',
            'allIssuedTickets' => fn ($q) => $q->with([
                'ticketAgent',
                'ticketFare.airline',
                'ticketFare.airlineClass.class',
                'ticketFare.route.fromCity',
                'ticketFare.route.toCity',
                'ticketFare.route.returnCity',
                'ticketFare.route.multiSegments.fromCity',
                'ticketFare.route.multiSegments.toCity',
                'latestReIssuedTicket.ticketAgent',
                'latestReIssuedTicket.ticketFare',
                'latestRefundedTicket.ticketAgent',
                'latestRefundedTicket.ticketFare',
                'reIssuedTickets.reason',
                'refundedTickets.reason',
                'pendingRequests',
            ]),
            'cancelledPassengers',
            'documents',
        ])->paginate((int) $request->input('per_page', 15))->withQueryString();

        $currencyRateService = app(CurrencyRateService::class);
        $firstRate = (float) ($currencyRateService->getFirstRate()?->rate ?? 0);
        $costService = app(CostTrackingService::class);
        $profitService = app(ProfitCalculationService::class);

        $data = $page->getCollection()->map(function ($p) use ($firstRate, $costService, $profitService) {
            $passBookingRate = $p->booking?->currencyRate?->rate
                ?? app(CurrencyRateService::class)->getRateForDate($p->booking?->created_at)?->rate
                ?? $firstRate;

            return [
                'id' => $p->id,
                'booking_id' => $p->booking_id,
                'first_name' => $p->first_name,
                'last_name' => $p->last_name,
                'passport_no' => $p->passport_no,
                'mobile_no' => $p->mobile_no,
                'flight_date_from' => $p->flight_date_from?->format('d M Y'),
                'flight_date_to' => $p->flight_date_to?->format('d M Y'),
                'stay_duration' => $p->stay_duration,
                'passenger_type' => $p->passenger_type?->value,
                'service_required' => $p->service_required?->value,
                'package_value' => $p->package_value,
                'ticket_remarks' => $p->ticket_remarks ?? '',
                'is_ticket_held' => (bool) ($p->is_ticket_held ?? false),
                'is_visa_held' => (bool) ($p->is_visa_held ?? false),
                'is_cancelled' => $p->booking?->is_cancelled ?? false,
                'profit' => (float) ($p->profit ?? 0),
                'refund_payable' => (float) ($p->refund_payable ?? 0),
                'route_display' => $p->route_display ?? '—',
                'pass_booking_rate' => $passBookingRate,

                'booking' => [
                    'created_at' => $p->booking?->created_at?->format('d M Y'),
                    'created_at_yyyy_mm_dd' => $p->booking?->created_at?->format('Y-m-d'),
                    'invoice_id' => $p->booking?->invoice_id,
                    'pax_qty' => $p->booking?->pax_qty,
                    'remarks' => $p->booking?->remarks,
                    'fingerprint_location' => $p->booking?->fingerprint_location?->value,
                    'is_cancelled' => $p->booking?->is_cancelled ?? false,
                    'customer' => [
                        'name' => $p->booking?->customer?->name,
                        'mobile_no' => $p->booking?->customer?->mobile_no,
                    ],
                    'package' => [
                        'package_name' => $p->booking?->package?->package_name,
                    ],
                    'invoice' => $p->booking?->invoice ? [
                        'total_amount' => (float) ($p->booking->invoice->total_amount ?? 0),
                        'balance' => (float) ($p->booking->invoice->balance ?? 0),
                        'paid_amount' => (float) ($p->booking->invoice->paid_amount ?? 0),
                    ] : null,
                    'discount_amount' => (float) ($p->booking?->discount_amount ?? 0),
                ],

                'fare_amount' => $this->computeFareAmount($p),
                'actual_flight_date' => $this->computeActualFlightDate($p),
                'return_date' => $this->computeReturnDate($p),
                'fingerprint_display_status' => $this->computeFingerprintDisplayStatus($p),
                'cancelled_passenger' => $this->computeCancelledPassengerInfo($p),
                'documents_count' => $p->documents->count(),
                'has_booking_documents' => $p->booking?->documents?->isNotEmpty() ?? false,
                'has_customer_documents' => ($p->booking?->customer && $p->booking->customer->documents->isNotEmpty()) ?? false,
                'passenger_url' => route('passengers.show', $p->id).'?return_url='.urlencode(request()->fullUrl()),
                'passenger_download_url' => route('passengers.download-all-docs', $p->id),
                'booking_download_url' => route('bookings.download-all-docs', ['booking' => $p->booking_id, 'passenger_id' => $p->id]),

                'visa_data' => $this->computeVisaData($p),
                'ticket_data' => $this->computeTicketData($p, $passBookingRate, $profitService),
                'cost' => $this->computePassengerCost($p, $costService),
            ];
        })->values();

        return response()->json([
            'data' => $data,
            'summary' => $this->passengerSummary($request),
            'pagination' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    protected function passengerSummary(Request $request): array
    {
        $base = (new BookingPassengerQuery($request))->getBaseQueryForAggregates();
        $base->getQuery()->orders = [];
        $row = (clone $base)->selectRaw('COUNT(*) as total')->first();

        return ['total' => (int) $row->total];
    }

    private function computeFareAmount(Passenger $p): float
    {
        $calcFare = function ($fare, $pType) {
            if (! $fare) {
                return 0;
            }
            $base = $fare->ticket_type?->value === 'offer'
                ? ($fare->offer_price ?? $fare->selling_fare ?? $fare->net_fare ?? 0)
                : ($fare->selling_fare ?? $fare->net_fare ?? 0);

            return match ($pType) {
                'child' => $base * ($fare->child_fare_percentage) / 100,
                'infant' => $base * ($fare->infant_fare_percentage) / 100,
                default => $base,
            };
        };

        if ($p->ticket_fare_inbound_id && $p->ticket_fare_outbound_id) {
            return $calcFare($p->ticketFareInbound, $p->passenger_type?->value)
                + $calcFare($p->ticketFareOutbound, $p->passenger_type?->value);
        }

        return $calcFare($p->ticketFare, $p->passenger_type?->value);
    }

    private function computeActualFlightDate(Passenger $p): ?string
    {
        $regularTicket = $p->allIssuedTickets
            ->first(fn ($t) => in_array($t->issue_type, [null, 'regular'], true)
                && in_array($t->status, ['issued', 're-issued']));

        if (! $regularTicket) {
            return null;
        }

        $date = $regularTicket->status === 're-issued'
            ? ($regularTicket->latestReIssuedTicket?->inbound_date ?? $regularTicket->inbound_date)
            : $regularTicket->inbound_date;

        return $date?->format('d M Y');
    }

    private function computeReturnDate(Passenger $p): ?string
    {
        $regularTicket = $p->allIssuedTickets
            ->first(fn ($t) => in_array($t->issue_type, [null, 'regular'], true)
                && in_array($t->status, ['issued', 're-issued']));

        if (! $regularTicket) {
            return null;
        }

        if ($regularTicket->outbound_pending) {
            $pendingTicket = $p->allIssuedTickets
                ->first(fn ($t) => $t->issue_type === 'pending_outbound'
                    && in_array($t->status, ['issued', 're-issued'], true));
            if ($pendingTicket) {
                $d = $pendingTicket->status === 're-issued'
                    ? ($pendingTicket->latestReIssuedTicket?->outbound_date ?? $pendingTicket->outbound_date)
                    : $pendingTicket->outbound_date;

                return $d?->format('d M Y');
            }

            return null;
        }

        $d = $regularTicket->status === 're-issued'
            ? ($regularTicket->latestReIssuedTicket?->outbound_date ?? $regularTicket->outbound_date)
            : $regularTicket->outbound_date;

        return $d?->format('d M Y');
    }

    private function computeFingerprintDisplayStatus(Passenger $p): array
    {
        $detail = $p->fingerprintDetail;
        $rawStatus = $detail?->status?->value;
        $displayStatus = $rawStatus;
        $approvedDate = null;

        if ($rawStatus === 'approved') {
            $allDetails = $detail->fingerprint?->fingerprintDetails;
            $allApproved = $allDetails && $allDetails->every(fn ($d) => $d->status->value === 'approved');
            if (! $allApproved) {
                $displayStatus = 'Partially Approved';
            }
            $approvedDate = ($detail->approvedLog?->created_at ?? $detail->updated_at)?->format('d|m|y');
        } elseif ($rawStatus === 'done') {
            $displayStatus = 'Pending Pax Completion';
        }

        return [
            'raw' => $rawStatus,
            'display' => $displayStatus,
            'approved_date' => $approvedDate,
        ];
    }

    private function computeCancelledPassengerInfo(Passenger $p): ?array
    {
        $activeCancellation = $p->cancelledPassengers->first();
        if (! $activeCancellation) {
            return null;
        }

        return [
            'is_confirmed' => $activeCancellation->status === 'cancelled' && $activeCancellation->confirmed_at,
            'is_processing' => $activeCancellation->status === 'cancellation processing' && ! $activeCancellation->confirmed_at,
        ];
    }

    private function computeVisaData(Passenger $p): array
    {
        return [
            'id' => $p->id,
            'booking_id' => $p->booking_id,
            'service_required' => $p->service_required?->value ?? 'all',
            'is_visa_held' => (bool) ($p->is_visa_held ?? false),
            'visa' => $p->visaSubmission ? [
                'id' => $p->visaSubmission->id,
                'agent_id' => $p->visaSubmission->visa_agent_id,
                'agent' => $p->visaSubmission?->visaAgent?->name ?? '',
                'visa_number' => $p->visaSubmission?->visa_number ?? '',
                'selling_price' => (float) ($p->visaSubmission?->visaSellingPrice?->selling_price ?? 0),
                'agent_commission' => (float) ($p->visaSubmission?->agent_commission ?? 0),
                'net_visa_cost' => (float) ($p->visaSubmission?->net_visa_cost ?? 0),
                'additional_cost' => (float) ($p->visaSubmission?->additional_cost ?? 0),
                'remarks' => $p->visaSubmission?->remarks ?? '',
                'final_cost' => (float) ($p->visaSubmission?->final_cost ?? 0),
                'commission_agent_id' => $p->visaSubmission?->commission_agent_id,
                'commission_agent' => $p->visaSubmission?->commissionAgent?->name ?? '',
                'status' => $p->visaSubmission->status?->value ?? 'pending',
            ] : null,
        ];
    }

    private function computeTicketData(Passenger $p, float $firstRate, ProfitCalculationService $profitService): array
    {
        $paxCount = max($p->booking->passengers->count(), 1);
        $fpCost = $p->booking->fingerprint?->cost ?? 0;

        return [
            'id' => $p->id,
            'booking_id' => $p->booking_id,
            'service_required' => $p->service_required?->value ?? 'all',
            'booking_date' => $p->booking?->created_at?->format('Y-m-d') ?? '',
            'invoice_no' => $p->booking?->invoice_id ?? '',
            'passenger_name' => trim($p->first_name.' '.$p->last_name),
            'passport' => $p->passport_no ?? '',
            'route' => $p->route_display ?? '',
            'airline' => $p->ticketFare?->airline?->name ?? $p->booking?->package?->ticketFare?->airline?->name ?? '',
            'travel_class' => $p->ticketFare?->airlineClass?->class?->name ?? $p->booking?->package?->ticketFare?->airlineClass?->class?->name ?? '',
            'passenger_type' => $p->passenger_type?->value ?? 'adult',
            'mobile_no' => $p->mobile_no ?? '',
            'is_ticket_held' => (bool) ($p->is_ticket_held ?? false),
            'ticket_status' => $p->allIssuedTickets
                ->filter(fn ($t) => is_null($t->issue_type) || $t->issue_type === 'regular')
                ->sortByDesc('id')
                ->first()?->status ?? null,
            'ticket_remarks' => $p->ticket_remarks ?? '',
            'due' => $p->booking?->invoice?->balance ?? 0,
            'refund_payable' => (float) ($p->refund_payable ?? 0),
            'profit' => (float) ($p->profit ?? 0),
            'profit_breakdown' => $profitService->getPassengerProfitBreakdown($p),
            'required_flight_date' => $p->flight_date_from?->format('Y-m-d') ?? '',
            'actual_flight_date' => $p->actual_flight_date?->format('Y-m-d') ?? '',
            'fingerprint_location' => $p->booking?->fingerprint_location?->value ?? 'None',
            'fingerprint_status' => $p->fingerprintDetail?->status?->value ?? null,
            'status' => $p->status?->name ?? 'None',
            'is_cancelled' => $p->booking?->is_cancelled ?? false,
            'fingerprint_cost' => $fpCost > 0 ? round($fpCost / $paxCount, 6) : 0,
            'ticket_fare_inbound_id' => $p->ticket_fare_inbound_id,
            'ticket_fare_outbound_id' => $p->ticket_fare_outbound_id,
            'is_double_ticket' => ! is_null($p->ticket_fare_inbound_id),
            'package_is_double_ticket' => $p->booking?->package?->is_double_ticket ?? false,
            'inbound_ticket_fare' => $this->computeInboundFareData($p),
            'outbound_ticket_fare' => $this->computeOutboundFareData($p),
            'ticket_fare' => $this->computeCurrentTicketFareData($p),
            'latest_issued_ticket' => $this->computeLatestIssuedTicket($p),
            'all_issued_tickets' => $this->computeAllIssuedTickets($p),
            'pending_outbound_issued_ticket' => $this->computePendingOutboundTicket($p),
        ];
    }

    private function computeInboundFareData(Passenger $p): ?array
    {
        $fare = $p->ticketFareInbound;
        if (! $fare) {
            return null;
        }
        $route = $fare->route;
        $pType = $p->passenger_type?->value ?? 'adult';

        return [
            'id' => $fare->id,
            'route_display' => $this->formatRouteDisplay($route),
            'airline' => $fare->airline?->name ?? '',
            'travel_class' => $fare->airlineClass?->class?->name ?? '',
            'ticket_type' => $fare->ticket_type?->value ?? 'regular',
            'flight_type' => $route?->flight_type?->value ?? '',
            'selling_fare' => (float) ($fare->selling_fare ?? 0),
            'net_fare' => (float) ($fare->net_fare ?? 0),
            'offer_price' => $fare->ticket_type?->value === 'offer' ? (float) ($fare->offer_price ?? 0) : null,
            'with_offer' => (bool) ($fare->ticket_type?->value === 'offer'),
            'child_fare_percentage' => (float) ($fare->child_fare_percentage ?? 70),
            'infant_fare_percentage' => (float) ($fare->infant_fare_percentage ?? 30),
            'baggage_inbound' => $fare->baggageAllowances
                ->filter(fn ($ba) => $ba->travel_direction?->value === 'inbound' && $ba->passenger_type?->value === $pType)
                ->first()?->allowance ?? '',
            'baggage_outbound' => $fare->baggageAllowances
                ->filter(fn ($ba) => $ba->travel_direction?->value === 'outbound' && $ba->passenger_type?->value === $pType)
                ->first()?->allowance ?? '',
        ];
    }

    private function computeOutboundFareData(Passenger $p): ?array
    {
        $fare = $p->ticketFareOutbound;
        if (! $fare) {
            return null;
        }
        $route = $fare->route;
        $pType = $p->passenger_type?->value ?? 'adult';

        return [
            'id' => $fare->id,
            'route_display' => $this->formatRouteDisplay($route),
            'airline' => $fare->airline?->name ?? '',
            'travel_class' => $fare->airlineClass?->class?->name ?? '',
            'ticket_type' => $fare->ticket_type?->value ?? 'regular',
            'flight_type' => $route?->flight_type?->value ?? '',
            'selling_fare' => (float) ($fare->selling_fare ?? 0),
            'net_fare' => (float) ($fare->net_fare ?? 0),
            'offer_price' => $fare->ticket_type?->value === 'offer' ? (float) ($fare->offer_price ?? 0) : null,
            'with_offer' => (bool) ($fare->ticket_type?->value === 'offer'),
            'child_fare_percentage' => (float) ($fare->child_fare_percentage ?? 70),
            'infant_fare_percentage' => (float) ($fare->infant_fare_percentage ?? 30),
            'baggage_inbound' => $fare->baggageAllowances
                ->filter(fn ($ba) => $ba->travel_direction?->value === 'inbound' && $ba->passenger_type?->value === $pType)
                ->first()?->allowance ?? '',
            'baggage_outbound' => $fare->baggageAllowances
                ->filter(fn ($ba) => $ba->travel_direction?->value === 'outbound' && $ba->passenger_type?->value === $pType)
                ->first()?->allowance ?? '',
        ];
    }

    private function computeCurrentTicketFareData(Passenger $p): ?array
    {
        $fare = $p->ticketFare;
        if (! $fare) {
            return null;
        }
        $route = $fare->route;

        return [
            'ticket_type' => $fare->ticket_type?->value ?? 'regular',
            'route_type' => match ($route?->route_type?->value) {
                'oneway_inbound' => 'One Way-Inbound',
                'oneway_outbound' => 'One Way-Outbound',
                'round' => 'Round',
                'multi_city' => 'Multi City',
                default => '',
            },
            'flight_type' => match ($route?->flight_type?->value) {
                'direct' => 'Direct',
                'transit' => 'Transit',
                default => '',
            },
            'group_ticket_id' => $fare->groupTicket?->id ?? null,
            'inbound_date' => $fare->groupTicket?->inbound_date ?? '',
            'outbound_date' => $fare->groupTicket?->outbound_date ?? '',
            'pnr' => $fare->groupTicket?->pnr ?? '',
            'ticket_number' => '',
            'date' => '',
            'ticket_agent' => '',
            'ticket_fare_id' => $fare->id,
            'airline_id' => $fare->airline_id,
            'airline_classes_id' => $fare->airline_classes_id,
            'route_id' => $fare->route_id,
            'selling_fare' => (float) ($fare->selling_fare ?? 0),
            'net_fare' => (float) ($fare->net_fare ?? 0),
            'child_fare_percentage' => (float) ($fare->child_fare_percentage ?? 70),
            'infant_fare_percentage' => (float) ($fare->infant_fare_percentage ?? 30),
            'offer_price' => $fare->ticket_type?->value === 'offer' ? (float) ($fare->offer_price ?? 0) : null,
            'with_offer' => (bool) ($fare->ticket_type?->value === 'offer'),
            'refundable' => false,
            'non_refundable' => false,
            'non_exchangeable' => false,
            'baggage_inbound' => '',
            'baggage_outbound' => '',
            'outbound_pending' => false,
            'issue_type' => null,
            'issued_ticket_id' => null,
            'baggage_allowances' => $fare->baggageAllowances->map(fn ($b) => [
                'passenger_type' => $b->passenger_type,
                'travel_direction' => $b->travel_direction,
                'allowance' => $b->allowance,
            ]) ?? [],
        ];
    }

    private function computeLatestIssuedTicket(Passenger $p): ?array
    {
        $lit = $p->allIssuedTickets
            ->filter(fn ($t) => is_null($t->issue_type) || $t->issue_type === 'regular')
            ->sortByDesc('id')
            ->first();

        if (! $lit) {
            return null;
        }

        return [
            'id' => $lit->id,
            'ticket_number' => $lit->ticket_number ?? '',
            'pnr' => $lit->pnr ?? '',
            'ticket_agent_id' => $lit->ticket_agent_id,
            'ticket_agent_name' => $lit->ticketAgent?->name ?? '',
            'ticket_fare_id' => $lit->ticket_fare_id,
            'group_ticket_id' => $lit->group_ticket_id,
            'issued_date' => $lit->issued_date?->format('Y-m-d') ?? '',
            'inbound_date' => $lit->inbound_date?->format('Y-m-d') ?? '',
            'outbound_date' => $lit->outbound_date?->format('Y-m-d') ?? '',
            'selling_fare' => (float) ($lit->selling_fare ?? 0),
            'net_fare' => (float) ($lit->net_fare ?? 0),
            'offer_price' => (float) ($lit->offer_price ?? 0),
            'is_refundable' => $lit->is_refundable ?? false,
            'is_exchangeable' => $lit->is_exchangeable ?? false,
            'baggage_inbound' => $lit->baggage_inbound ?? '',
            'baggage_outbound' => $lit->baggage_outbound ?? '',
            'outbound_pending' => $lit->outbound_pending ?? false,
            'issue_type' => $lit->issue_type,
            'status' => $lit->status,
            'refunded_net_fare' => $lit->latestRefundedTicket
                ? (float) ($lit->latestRefundedTicket->net_fare ?? $lit->net_fare ?? 0)
                : 0,
            'was_refunded' => (bool) $lit->latestRefundedTicket,
            'has_pending_request' => $lit->pendingRequests->isNotEmpty(),
            'ticket_type' => $lit->ticketFare?->ticket_type?->value ?? '',
            'airline' => $lit->ticketFare?->airline?->name ?? '',
            'travel_class' => $lit->ticketFare?->airlineClass?->class?->name ?? '',
            'route' => $this->formatRouteDisplay($lit->ticketFare?->route),
            'route_type' => $lit->ticketFare?->route?->route_type?->value,
            'latest_re_issued_ticket' => $this->computeLatestReIssuedTicket($lit),
            'latest_refunded_ticket' => $this->computeLatestRefundedTicket($lit),
        ];
    }

    private function computeLatestReIssuedTicket(IssuedTicket $lit): ?array
    {
        $lrt = $lit->latestReIssuedTicket;
        if (! $lrt) {
            return null;
        }

        return [
            'id' => $lrt->id,
            'ticket_number' => $lrt->ticket_number ?? '',
            'pnr' => $lrt->pnr ?? '',
            're_issue_date' => $lrt->re_issue_date?->format('Y-m-d') ?? '',
            'inbound_date' => $lrt->inbound_date?->format('Y-m-d') ?? '',
            'outbound_date' => $lrt->outbound_date?->format('Y-m-d') ?? '',
            'selling_fare' => (float) ($lrt->selling_fare ?? 0),
            'net_fare' => (float) ($lrt->net_fare ?? 0),
            'offer_price' => (float) ($lrt->offer_price ?? 0),
            'is_refundable' => $lrt->is_refundable ?? false,
            'is_exchangeable' => $lrt->is_exchangeable ?? false,
            'baggage_inbound' => $lrt->baggage_inbound ?? '',
            'baggage_outbound' => $lrt->baggage_outbound ?? '',
            'ticket_agent_id' => $lrt->ticket_agent_id,
            'ticket_agent_name' => $lrt->ticketAgent?->name ?? '',
            'ticket_fare_id' => $lrt->ticket_fare_id,
            'group_ticket_id' => $lrt->group_ticket_id,
            'route_id' => $lrt->route_id,
            'reason_id' => $lrt->reason_id,
            're_issue_charge' => (float) ($lrt->re_issue_charge ?? 0),
            'fare_difference' => (float) ($lrt->fare_difference ?? 0),
            'other_costs' => (float) ($lrt->other_costs ?? 0),
            'service_charge' => (float) ($lrt->service_charge ?? 0),
            'payment_by' => $lrt->payment_by,
            'payment_option' => $lrt->payment_option?->value,
            'refund_adjustment_amount' => (float) ($lrt->refund_adjustment_amount ?? 0),
            'total_customer_payment' => (float) ($lrt->total_customer_payment ?? 0),
            'remarks' => $lrt->remarks ?? '',
            'ticket_type' => $lrt->ticketFare?->ticket_type?->value ?? $lit->ticketFare?->ticket_type?->value ?? '',
            'route_type' => $lrt->ticketFare?->route?->route_type?->value ?? $lit->ticketFare?->route?->route_type?->value ?? '',
            'flight_type' => $lrt->ticketFare?->route?->flight_type?->value ?? $lit->ticketFare?->route?->flight_type?->value ?? '',
            'airline' => $lrt->ticketFare?->airline?->name ?? $lit->ticketFare?->airline?->name ?? '',
            'travel_class' => $lrt->ticketFare?->airlineClass?->class?->name ?? $lit->ticketFare?->airlineClass?->class?->name ?? '',
            'route' => $this->formatRouteDisplay($lrt->ticketFare?->route ?? $lit->ticketFare?->route),
        ];
    }

    private function computeLatestRefundedTicket(IssuedTicket $lit): ?array
    {
        $frt = $lit->latestRefundedTicket;
        if (! $frt) {
            return null;
        }

        return [
            'id' => $frt->id,
            'ticket_number' => $frt->ticket_number ?? '',
            'pnr' => $frt->pnr ?? '',
            'refund_date' => $frt->refund_date?->format('Y-m-d') ?? '',
            'inbound_date' => $frt->inbound_date?->format('Y-m-d') ?? '',
            'outbound_date' => $frt->outbound_date?->format('Y-m-d') ?? '',
            'selling_fare' => (float) ($frt->selling_fare ?? 0),
            'net_fare' => (float) ($frt->net_fare ?? 0),
            'offer_price' => (float) ($frt->offer_price ?? 0),
            'is_refundable' => $frt->is_refundable ?? false,
            'is_exchangeable' => $frt->is_exchangeable ?? false,
            'baggage_inbound' => $frt->baggage_inbound ?? '',
            'baggage_outbound' => $frt->baggage_outbound ?? '',
            'ticket_agent_id' => $frt->ticket_agent_id,
            'ticket_agent_name' => $frt->ticketAgent?->name ?? '',
            'ticket_fare_id' => $frt->ticket_fare_id,
            'group_ticket_id' => $frt->group_ticket_id,
            'reason_id' => $frt->reason_id,
            'iata_refunded_amount' => (float) ($frt->iata_refunded_amount ?? 0),
            'refund_to_customer' => (float) ($frt->refund_to_customer ?? 0),
            'service_charge' => (float) ($frt->service_charge ?? 0),
            'refund_compensation' => (float) ($frt->refund_compensation ?? 0),
            'payment_by' => $frt->payment_by,
            'remarks' => $frt->remarks ?? '',
            'ticket_type' => $frt->ticketFare?->ticket_type?->value ?? '',
            'route_type' => $frt->ticketFare?->route?->route_type?->value ?? '',
            'flight_type' => $frt->ticketFare?->route?->flight_type?->value ?? '',
            'airline' => $frt->ticketFare?->airline?->name ?? '',
            'travel_class' => $frt->ticketFare?->airlineClass?->class?->name ?? '',
            'route' => $this->formatRouteDisplay($frt->ticketFare?->route),
        ];
    }

    private function computeAllIssuedTickets(Passenger $p): array
    {
        return $p->allIssuedTickets->map(fn ($t) => [
            'id' => $t->id,
            'passenger_id' => $t->passenger_id,
            'outbound_pending' => $t->outbound_pending ?? false,
            'ticket_number' => $t->ticket_number ?? '',
            'issued_date' => $t->issued_date?->format('Y-m-d') ?? '',
            'inbound_date' => $t->inbound_date?->format('Y-m-d') ?? '',
            'outbound_date' => $t->outbound_date?->format('Y-m-d') ?? '',
            'selling_fare' => (float) ($t->selling_fare ?? 0),
            'net_fare' => (float) ($t->net_fare ?? 0),
            'offer_price' => (float) ($t->offer_price ?? 0),
            'pnr' => $t->pnr ?? '',
            'status' => $t->status,
            'issue_type' => $t->issue_type,
            'has_pending_request' => $t->pendingRequests->isNotEmpty(),
            'is_refundable' => $t->is_refundable ?? false,
            'is_exchangeable' => $t->is_exchangeable ?? false,
            'baggage_inbound' => $t->baggage_inbound ?? '',
            'baggage_outbound' => $t->baggage_outbound ?? '',
            'ticket_agent_id' => $t->ticket_agent_id,
            'ticket_fare_id' => $t->ticket_fare_id,
            'group_ticket_id' => $t->group_ticket_id,
            'airline' => $t->ticketFare?->airline?->name ?? '',
            'travel_class' => $t->ticketFare?->airlineClass?->class?->name ?? '',
            'route' => $this->formatRouteDisplay($t->ticketFare?->route),
            'route_type' => $t->ticketFare?->route?->route_type?->value,
            'flight_type' => $t->ticketFare?->route?->flight_type?->value ?? '',
            'ticket_type' => $t->ticketFare?->ticket_type?->value ?? '',
            'ticket_agent_name' => $t->ticketAgent?->name ?? '',
            'issuer_name' => $t->issuer?->name ?? '',
            'latest_re_issued_ticket' => $this->computeLatestReIssuedTicket($t),
            'latest_refunded_ticket' => $this->computeLatestRefundedTicket($t),
            'reason' => match ($t->status) {
                're-issued' => $t->reIssuedTickets->sortByDesc('id')->first()?->reason?->name ?? null,
                'refunded' => $t->refundedTickets->sortByDesc('id')->first()?->reason?->name ?? null,
                default => null,
            },
            'remarks' => match ($t->status) {
                're-issued' => $t->reIssuedTickets->sortByDesc('id')->first()?->remarks ?? null,
                'refunded' => $t->refundedTickets->sortByDesc('id')->first()?->remarks ?? null,
                default => null,
            },
            'refunded_net_fare' => $t->latestRefundedTicket
                ? (float) ($t->latestRefundedTicket->net_fare ?? $t->net_fare ?? 0)
                : 0,
            'was_refunded' => (bool) $t->latestRefundedTicket,
        ])->values()->all();
    }

    private function computePendingOutboundTicket(Passenger $p): ?array
    {
        $poit = $p->allIssuedTickets->first(fn ($t) => $t->issue_type === 'pending_outbound');
        if (! $poit) {
            return null;
        }

        $src = $poit->status === 're-issued' ? ($poit->latestReIssuedTicket ?? $poit) : $poit;
        $fare = $src->ticketFare ?? $poit->ticketFare;

        return [
            'id' => $poit->id,
            'ticket_number' => $src->ticket_number ?? $poit->ticket_number ?? '',
            'pnr' => $src->pnr ?? $poit->pnr ?? '',
            'ticket_agent_id' => $src->ticket_agent_id ?? $poit->ticket_agent_id,
            'ticket_agent_name' => $src->ticketAgent?->name ?? $poit->ticketAgent?->name ?? '',
            'ticket_fare_id' => $src->ticket_fare_id ?? $poit->ticket_fare_id,
            'ticket_type' => $fare?->ticket_type?->value ?? '',
            'flight_type' => $fare?->route?->flight_type?->value ?? '',
            'route_display' => $this->formatRouteDisplay($fare?->route),
            'airline' => $fare?->airline?->name ?? '',
            'travel_class' => $fare?->airlineClass?->class?->name ?? '',
            'issued_date' => $src !== $poit
                ? ($src->re_issue_date?->format('Y-m-d') ?? $poit->issued_date?->format('Y-m-d') ?? '')
                : ($poit->issued_date?->format('Y-m-d') ?? ''),
            'outbound_date' => $src->outbound_date?->format('Y-m-d') ?? $poit->outbound_date?->format('Y-m-d') ?? '',
            'selling_fare' => (float) ($src->selling_fare ?? $poit->selling_fare ?? 0),
            'net_fare' => (float) ($src->net_fare ?? $poit->net_fare ?? 0),
            'offer_price' => (float) ($src->offer_price ?? $poit->offer_price ?? 0),
            'is_refundable' => $src->is_refundable ?? $poit->is_refundable ?? false,
            'is_exchangeable' => $src->is_exchangeable ?? $poit->is_exchangeable ?? false,
            'baggage_outbound' => $src->baggage_outbound ?? $poit->baggage_outbound ?? '',
            'status' => $poit->status,
            'refunded_net_fare' => $poit->latestRefundedTicket
                ? (float) ($poit->latestRefundedTicket->net_fare ?? $poit->net_fare ?? 0)
                : 0,
            'was_refunded' => (bool) $poit->latestRefundedTicket,
            're_issue_details' => $poit->status === 're-issued' && $src !== $poit ? [
                'reason_id' => $src->reason_id,
                're_issue_charge' => (float) ($src->re_issue_charge ?? 0),
                'fare_difference' => (float) ($src->fare_difference ?? 0),
                'other_costs' => (float) ($src->other_costs ?? 0),
                'service_charge' => (float) ($src->service_charge ?? 0),
                'payment_by' => $src->payment_by,
                'payment_option' => $src->payment_option?->value,
                'refund_adjustment_amount' => (float) ($src->refund_adjustment_amount ?? 0),
                'remarks' => $src->remarks ?? '',
            ] : null,
        ];
    }

    private function computePassengerCost(Passenger $p, CostTrackingService $costService): float
    {
        static $bookingCostCache = [];
        $booking = $p->booking;
        if (! $booking) {
            return 0;
        }

        $bid = $booking->id;
        if (! isset($bookingCostCache[$bid])) {
            $bookingCostCache[$bid] = $costService->getPassengerCosts($booking)->keyBy('passenger_id');
        }
        $c = $bookingCostCache[$bid]->get($p->id);

        return (float) ($c['total_cost'] ?? 0);
    }

    private function formatRouteDisplay($route): string
    {
        if (! $route) {
            return '';
        }

        $rt = $route->route_type?->value;
        if ($rt === 'multi_city') {
            if ($route->multiSegments && $route->multiSegments->count() > 0) {
                return $route->multiSegments
                    ->map(fn ($s) => ($s->fromCity?->code ?? '?').'-'.($s->toCity?->code ?? '?'))
                    ->implode(', ');
            }

            return '-';
        }

        $from = $route->fromCity?->code ?? '?';
        $to = $route->toCity?->code ?? '?';
        $return = $route->returnCity?->code ?? '';

        if ($rt === 'round' && $return) {
            return "{$from}-{$to}-{$return}";
        }

        return "{$from}-{$to}";
    }

    public function create(Request $request)
    {
        $packageId = $request->query('package_id');
        $preSelectedPackageId = null;

        $user = auth()->user();

        // Safety: if the authenticated user's session was lost behind the
        // Cloudflare proxy (auth middleware passed but user() is null),
        // redirect to login instead of crashing on $user->branch.
        if (! $user) {
            return redirect()->route('login');
        }

        $userBranch = $user->branch;
        $bookingBranches = ! $userBranch ? Branch::orderBy('name')->get(['id', 'name']) : collect();
        $fingerprintBranches = Branch::where('fingerprint_operation', true)->orderBy('name')->get(['id', 'name']);

        $showBookingBranch = ! $userBranch;
        $showFingerprintBranch = ! $userBranch || ! $userBranch->fingerprint_operation;

        if ($packageId) {
            $package = Package::find($packageId);
            $preSelectedPackageId = $package ? $package->id : null;
        }

        $districts = District::orderBy('name')->get();
        $packages = Package::where('is_active', true)->with(['ticketFare', 'visaSellingPrice', 'ticketFareInbound', 'ticketFareOutbound'])->orderBy('package_name')->get()->map(function ($pkg) {
            return [
                'id' => $pkg->id,
                'package_name' => $pkg->package_name,
                'ticket_fare_id' => $pkg->ticket_fare_id,
                'is_double_ticket' => $pkg->is_double_ticket,
                'ticket_fare_inbound_id' => $pkg->ticket_fare_inbound_id,
                'ticket_fare_outbound_id' => $pkg->ticket_fare_outbound_id,
                'visa_selling_price' => $pkg->visaSellingPrice?->selling_price ?? 0,
                'service_charge' => $pkg->service_charge ?? 0,
            ];
        });

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
                'airline' => $fare->airline->name,
                'airline_class' => $fare->airlineClass->class?->name,
                'ticket_type' => $fare->ticket_type->value,
                'selling_fare' => $fare->selling_fare,
                'child_fare_percentage' => $fare->child_fare_percentage,
                'infant_fare_percentage' => $fare->infant_fare_percentage,
                'offer_price' => $fare->offer_price,
                'available_seats' => $fare->groupTicket?->ticket_qty ?? null,
                'route_type' => $routeType,
                'flight_type' => $fare->route->flight_type?->value,
                'baggage_allowances' => $fare->baggageAllowances->map(function ($ba) {
                    return [
                        'passenger_type' => $ba->passenger_type,
                        'travel_direction' => $ba->travel_direction,
                        'allowance' => $ba->allowance,
                    ];
                })->toArray(),
            ];
        });

        $currencyRates = CurrencyRate::orderBy('created_at', 'desc')->get();
        $currentCurrencyRate = app(CurrencyRateService::class)->getRateForDate(now());

        $userBranchLocation = $userBranch?->location?->value;
        $banks = Bank::orderBy('name')->get(['id', 'name', 'currency', 'location']);

        return view('bookings.create', compact(
            'districts', 'packages', 'preSelectedPackageId', 'ticketFares',
            'currencyRates', 'currentCurrencyRate', 'bookingBranches',
            'fingerprintBranches', 'showBookingBranch', 'showFingerprintBranch',
            'userBranchLocation', 'banks'
        ));
    }

    public function store(Request $request)
    {
        DiagnosticLogger::arrival($request, 'bookings.store');

        $validator = \Validator::make($request->all(), [
            'customer_id' => 'required|exists:customers,id',
            'district_id' => 'required|exists:districts,id',
            'booking_branch_id' => 'nullable|exists:branches,id',
            'fingerprint_branch_id' => 'nullable|exists:branches,id',
            'package_id' => 'nullable|exists:packages,id',
            'fingerprint_charge_id' => 'required|exists:fingerprint_charges,id',
            'fingerprint_location' => 'nullable|in:office,home',
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
            'passengers.*.service_required' => 'nullable|in:all,visa_only,ticket_only',
            'passengers.*.stay_duration' => 'nullable|integer|min:'.($limits = StayDurationLimit::getOrCreate())->min_days.'|max:'.$limits->max_days,
            'passengers.*.flight_date_from' => 'nullable|date',
            'passengers.*.flight_date_to' => 'nullable|date|after:passengers.*.flight_date_from',
            'passengers.*.address' => 'nullable|string|max:500',
            'passengers.*.gender' => 'nullable|in:male,female',
            'passengers.*.ticket_fare_id' => 'nullable|exists:ticket_fares,id',
            'passengers.*.ticket_fare_inbound_id' => 'nullable|exists:ticket_fares,id',
            'passengers.*.ticket_fare_outbound_id' => 'nullable|exists:ticket_fares,id',
            'booking_customer_docs' => 'nullable|array',
            'booking_customer_docs.*' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
            'passenger_docs' => 'nullable|array',
            'passenger_docs.*' => 'nullable|array',
            'passenger_docs.*.*' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
            'payment' => 'required|array',
            'payment.amount' => 'required|numeric|min:0.01',
            'payment.bdt_amount' => 'nullable|numeric|min:0',
            'payment.currency' => 'required|in:SAR,BDT',
            'payment.payment_method' => 'required|in:cash,bank',
            'payment.payment_date' => 'nullable|date',
            'payment.bank_id' => 'nullable|exists:banks,id',
            'payment.transaction_id' => 'nullable|string|max:255',
        ], [
            'booking_customer_docs.*.max' => 'Each file must not exceed 5 MB.',
            'booking_customer_docs.*.mimes' => 'Only PDF, JPG, JPEG, and PNG files are allowed.',
            'passenger_docs.*.*.max' => 'Each file must not exceed 5 MB.',
            'passenger_docs.*.*.mimes' => 'Only PDF, JPG, JPEG, and PNG files are allowed.',
        ]);

        $validator->after(function ($validator) use ($request) {
            $totalSize = 0;
            if ($request->hasFile('booking_customer_docs')) {
                $totalSize += collect($request->file('booking_customer_docs'))->sum(fn ($f) => $f->getSize());
            }
            if ($request->hasFile('passenger_docs')) {
                foreach ($request->file('passenger_docs') as $passengerFiles) {
                    if (is_array($passengerFiles)) {
                        $totalSize += collect($passengerFiles)->sum(fn ($f) => $f instanceof UploadedFile ? $f->getSize() : 0);
                    }
                }
            }
            if ($totalSize > 20 * 1024 * 1024) {
                $validator->errors()->add('files', 'The total size of all uploaded files must not exceed 20 MB.');
            }
        });

        if ($validator->fails()) {
            \Log::warning('Booking store validation failed', [
                'errors' => $validator->errors()->toArray(),
                'input_keys' => array_keys($request->all()),
                'files' => array_keys($request->allFiles()),
            ]);
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            return redirect()->back()->withErrors($validator)->withInput();
        }

        $validated = $validator->validated();

        try {
            DB::beginTransaction();

            $currentCurrencyRate = app(CurrencyRateService::class)->getRateForDate(now());

            $user = auth()->user();
            $userBranch = $user->branch;

            $resolvedBranchId = $this->resolveBookingBranch($request, forUpdate: false);

            if ($userBranch && $userBranch->fingerprint_operation) {
                $bookingBranchId = $userBranch->id;
                $fingerprintBranchId = $userBranch->id;
            } elseif ($userBranch) {
                $bookingBranchId = $userBranch->id;
                $fingerprintBranchId = $validated['fingerprint_branch_id'] ?? null;
            } else {
                $bookingBranchId = $validated['booking_branch_id'] ?? $resolvedBranchId;
                $fingerprintBranchId = $validated['fingerprint_branch_id'] ?? null;
            }

            $booking = Booking::create([
                'user_id' => auth()->id(),
                'booking_branch_id' => $bookingBranchId,
                'invoice_id' => $this->bookingService->generateInvoiceId($bookingBranchId),
                'date_gap_id' => FlightDateGap::getOrCreate()->id,
                'customer_id' => $validated['customer_id'],
                'district_id' => $validated['district_id'] ?? null,
                'fingerprint_branch_id' => $fingerprintBranchId,
                'package_id' => $validated['package_id'] ?? null,
                'fingerprint_charge_id' => $validated['fingerprint_charge_id'] ?? null,
                'fingerprint_location' => $validated['fingerprint_location'] ?? 'Office',
                'pax_qty' => count($validated['passengers']),
                'discount_type' => $this->isAdminRole() ? (($validated['discount_type'] ?? 'fixed') === 'fixed' ? 'fixed_amount' : 'percentage') : 'fixed_amount',
                'discount_value' => $this->isAdminRole() ? ($validated['discount_value'] ?? 0) : 0,
                'discount_amount' => 0,
                'remarks' => $validated['remarks'] ?? null,
                'currency_rate_id' => $currentCurrencyRate?->id,
                'total_value' => 0,
            ]);

            $booking->load('customer');
            $invoiceId = $booking->invoice_id ?? 'INV';
            $customerName = $booking->customer->name ?? 'Customer';

            $customerDocCount = 0;
            if ($booking->customer) {
                $customerDocCount = $booking->customer->documents->count();
                foreach ($booking->customer->documents as $idx => $doc) {
                    $doc->update(['display_name' => "{$invoiceId} {$customerName} ".($idx + 1)]);
                }
            }

            $customerDocs = $request->file('booking_customer_docs', []);
            if (is_array($customerDocs) && count($customerDocs) > 0) {
                $bookingDocCount = $booking->documents()->count();

                foreach ($customerDocs as $index => $file) {
                    if ($file instanceof UploadedFile && $file->isValid()) {
                        $booking->documents()->create([
                            'owner_type' => 'booking',
                            'owner_id' => $booking->id,
                            'file_path' => $file->store('booking-docs', 'public'),
                            'display_name' => "{$invoiceId} {$customerName} ".($customerDocCount + $bookingDocCount + $index + 1),
                        ]);
                    }
                }
            }

            $createdPassengers = [];
            foreach ($validated['passengers'] as $passengerIndex => $passengerData) {
                $passengerType = $this->bookingService->calculatePassengerType(
                    $passengerData['date_of_birth'],
                    // $passengerData['stay_duration'] ?? null
                );

                $isDoubleTicket = $booking->package?->is_double_ticket;

                $passenger = Passenger::create([
                    'booking_id' => $booking->id,
                    'first_name' => $passengerData['first_name'],
                    'last_name' => $passengerData['last_name'],
                    'passport_no' => $passengerData['passport_no'],
                    'date_of_birth' => $passengerData['date_of_birth'],
                    'gender' => $passengerData['gender'] ?? null,
                    'passenger_type' => $passengerType,
                    'passport_expiry' => $passengerData['passport_expiry'] ?? now()->addYears(5)->toDateString(),
                    'mobile_no' => $passengerData['mobile_no'] ?? '',
                    'service_required' => $passengerData['service_required'] ?? 'all',
                    'stay_duration' => $passengerData['stay_duration'] ?? 14,
                    'flight_date_from' => $passengerData['flight_date_from'] ?? now()->toDateString(),
                    'flight_date_to' => $passengerData['flight_date_to'] ?? now()->addDays(14)->toDateString(),
                    'address' => $passengerData['address'] ?? '',
                    'ticket_fare_id' => $isDoubleTicket
                        ? null
                        : (($passengerData['service_required'] ?? '') === 'visa_only'
                            ? null
                            : $booking->package?->ticket_fare_id),
                    'ticket_fare_inbound_id' => $isDoubleTicket
                        ? $booking->package?->ticket_fare_inbound_id
                        : null,
                    'ticket_fare_outbound_id' => $isDoubleTicket
                        ? $booking->package?->ticket_fare_outbound_id
                        : null,
                    'package_value' => 0,
                ]);

                $createdPassengers[$passengerIndex] = $passenger;

                if (($passengerData['service_required'] ?? 'all') !== 'ticket_only') {
                    VisaSubmission::create([
                        'passenger_id' => $passenger->id,
                        'visa_selling_price_id' => $booking->package?->visa_selling_price_id ?? VisaSellingPrice::latest('id')->value('id'),
                        'status' => 'pending',
                    ]);
                }

                IssuedTicket::create([
                    'booking_id' => $booking->id,
                    'passenger_id' => $passenger->id,
                    'ticket_fare_id' => $passenger->ticket_fare_id,
                    'user_id' => auth()->id(),
                    'status' => 'pending',
                ]);
            }

            $passengerDocs = $request->file('passenger_docs', []);
            if (is_array($passengerDocs) && count($passengerDocs) > 0) {
                foreach ($passengerDocs as $passengerIndex => $files) {
                    if (! isset($createdPassengers[$passengerIndex])) {
                        continue;
                    }
                    $passenger = $createdPassengers[$passengerIndex];
                    foreach ($files as $fileIdx => $file) {
                        if ($file instanceof UploadedFile && $file->isValid()) {
                            $passenger->documents()->create([
                                'file_path' => $file->store('passenger-documents'),
                                'display_name' => "{$invoiceId} {$passenger->first_name} {$passenger->last_name} {$passenger->passport_no} ".($fileIdx + 1),
                            ]);
                        }
                    }
                }
            }

            $fingerprint = Fingerprint::create([
                'booking_id' => $booking->id,
                'deadline' => now()->addDays(10),
                'cost' => 0,
                'assigned_staff_id' => null,
            ]);

            $booking->load('passengers');
            foreach ($booking->passengers as $passenger) {
                FingerprintDetail::create([
                    'fingerprint_id' => $fingerprint->id,
                    'passenger_id' => $passenger->id,
                    'status' => 'none',
                ]);
            }

            $booking->refresh();
            $this->bookingService->recalculateBookingTotal($booking);

            $invoice = $this->bookingService->createInvoiceForBooking($booking);

            $discountAmount = $this->bookingService->calculateDiscount(
                $booking->total_value,
                $validated['discount_type'] ?? 'fixed',
                $validated['discount_value'] ?? 0
            );
            $booking->discount_amount = $discountAmount;
            $booking->save();

            $discountedTotal = max(0, $booking->total_value - $discountAmount);
            $invoice->total_amount = $discountedTotal;
            $invoice->balance = $discountedTotal;
            $invoice->audit_reason = 'booking_created';
            $invoice->save();

            $paymentAmount = (float) ($validated['payment']['amount'] ?? 0);
            $paymentBdtAmount = (float) ($validated['payment']['bdt_amount'] ?? 0);

            \Log::info('Payment debug - amount: '.$paymentAmount.', bdt_amount: '.$paymentBdtAmount.', payment array: ', $validated['payment'] ?? []);

            if ($paymentAmount > 0 || $paymentBdtAmount > 0) {
                \Log::info('Processing payment...');
                try {
                    $initialPaymentTransactionType = TransactionType::where('name', 'Initial Payment')->first();

                    if (! $initialPaymentTransactionType) {
                        throw new \Exception('Initial Payment transaction type not found. Please seed transaction types.');
                    }

                    $paymentData = [
                        'branch_id' => $booking->booking_branch_id,
                        'user_id' => $booking->user_id,
                        'payment_date' => $validated['payment']['payment_date'] ?? now()->toDateString(),
                        'payment_method' => $validated['payment']['payment_method'] ?? 'cash',
                        'amount' => $validated['payment']['amount'] ?? 0,
                        'bdt_amount' => $validated['payment']['bdt_amount'] ?? 0,
                        'currency' => $validated['payment']['currency'] ?? 'SAR',
                        'bank_id' => $validated['payment']['bank_id'] ?? null,
                        'transaction_id' => $validated['payment']['transaction_id'] ?? null,
                        'transaction_type_id' => $initialPaymentTransactionType->id,
                    ];

                    app(PaymentService::class)->createCustomerPaymentAndUpdateInvoice($invoice, $paymentData);
                } catch (\Exception $e) {
                    \Log::error('Payment creation failed: '.$e->getMessage());
                    throw $e;
                }
            }

            DB::commit();

            if ($request->ajax() || $request->wantsJson()) {
                $paymentMessage = ($paymentAmount > 0 || $paymentBdtAmount > 0)
                    ? ' with initial payment'
                    : '';

                return response()->json([
                    'success' => true,
                    'message' => 'Booking created successfully with '.count($validated['passengers']).' passenger(s)'.$paymentMessage,
                    'url' => route('bookings.print', $booking->id),
                ]);
            }

            $paymentMessage = ($paymentAmount > 0 || $paymentBdtAmount > 0)
                ? ' and initial payment recorded'
                : '';

            return redirect()->route('bookings.print', $booking->id)
                ->with('success', 'Booking created successfully with '.count($validated['passengers']).' passenger(s)'.$paymentMessage);

        } catch (\Exception $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            $dbMessage = $e instanceof QueryException
                ? DatabaseErrorHumanizer::humanize($e)
                : 'An unexpected error occurred. Please try again.';

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $dbMessage,
                ], 500);
            }

            return redirect()->back()->with('error', $dbMessage)->withInput();
        }
    }

    public function show(Booking $booking)
    {
        $isCrossBranchViewer = auth()->user()?->branch_id
            && auth()->user()?->branch_id !== $booking->booking_branch_id
            && auth()->user()?->branch_id !== $booking->fingerprint_branch_id;

        $booking->load([
            'customer',
            'customer.documents',
            'passengers',
            'passengers.documents',
            'passengers.ticketFare',
            'passengers.ticketFareInbound.route',
            'passengers.ticketFareOutbound.route',
            'passengers.allIssuedTickets',
            'passengers.allIssuedTickets.ticketAgent',
            'passengers.allIssuedTickets.ticketFare.airline',
            'passengers.allIssuedTickets.ticketFare.airlineClass.class',
            'passengers.allIssuedTickets.ticketFare.route.fromCity',
            'passengers.allIssuedTickets.ticketFare.route.toCity',
            'passengers.allIssuedTickets.ticketFare.route.returnCity',
            'passengers.allIssuedTickets.ticketFare.route.multiSegments.fromCity',
            'passengers.allIssuedTickets.ticketFare.route.multiSegments.toCity',
            'passengers.allIssuedTickets.latestReIssuedTicket',
            'passengers.allIssuedTickets.latestReIssuedTicket.ticketAgent',
            'passengers.allIssuedTickets.latestReIssuedTicket.ticketFare.airline',
            'passengers.allIssuedTickets.latestReIssuedTicket.ticketFare.airlineClass.class',
            'passengers.allIssuedTickets.latestReIssuedTicket.ticketFare.route.fromCity',
            'passengers.allIssuedTickets.latestReIssuedTicket.ticketFare.route.toCity',
            'passengers.allIssuedTickets.latestReIssuedTicket.ticketFare.route.returnCity',
            'passengers.allIssuedTickets.latestReIssuedTicket.ticketFare.route.multiSegments.fromCity',
            'passengers.allIssuedTickets.latestReIssuedTicket.ticketFare.route.multiSegments.toCity',
            'passengers.allIssuedTickets.latestRefundedTicket',
            'passengers.allIssuedTickets.latestRefundedTicket.ticketAgent',
            'passengers.allIssuedTickets.latestRefundedTicket.ticketFare.airline',
            'passengers.allIssuedTickets.latestRefundedTicket.ticketFare.airlineClass.class',
            'passengers.allIssuedTickets.latestRefundedTicket.ticketFare.route.fromCity',
            'passengers.allIssuedTickets.latestRefundedTicket.ticketFare.route.toCity',
            'passengers.allIssuedTickets.latestRefundedTicket.ticketFare.route.returnCity',
            'passengers.allIssuedTickets.latestRefundedTicket.ticketFare.route.multiSegments.fromCity',
            'passengers.allIssuedTickets.latestRefundedTicket.ticketFare.route.multiSegments.toCity',
            'passengers.allIssuedTickets.pendingRequests',
            'user',
            'district',
            'package',
            'fingerprintBranch',
            'invoice',
            'payments.vouchers.transactionType', 'payments.bank',
        ]);

        $booking->setRelation(
            'payments',
            $booking->payments->filter(function ($payment) {
                return $payment->vouchers->contains(function ($voucher) {
                    return in_array($voucher->transactionType?->name, ['Initial Payment', 'Due Collection']);
                });
            })->values()
        );

        $packages = Package::where('is_active', true)->with(['ticketFare', 'visaSellingPrice', 'ticketFareInbound', 'ticketFareOutbound'])->orderBy('package_name')->get()->map(function ($pkg) {
            return [
                'id' => $pkg->id,
                'package_name' => $pkg->package_name,
                'ticket_fare_id' => $pkg->ticket_fare_id,
                'is_double_ticket' => $pkg->is_double_ticket,
                'ticket_fare_inbound_id' => $pkg->ticket_fare_inbound_id,
                'ticket_fare_outbound_id' => $pkg->ticket_fare_outbound_id,
                'visa_selling_price' => $pkg->visaSellingPrice?->selling_price ?? 0,
                'service_charge' => $pkg->service_charge ?? 0,
                'package_value' => $pkg->is_double_ticket
                    ? (($pkg->ticketFareInbound?->selling_fare ?? 0) + ($pkg->ticketFareOutbound?->selling_fare ?? 0) + ($pkg->visaSellingPrice?->selling_price ?? 0) + ($pkg->service_charge ?? 0))
                    : (($pkg->ticketFare?->selling_fare ?? 0) + ($pkg->visaSellingPrice?->selling_price ?? 0) + ($pkg->service_charge ?? 0)),
            ];
        });

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
                'airline' => $fare->airline->name,
                'airline_class' => $fare->airlineClass->class?->name,
                'ticket_type' => $fare->ticket_type->value,
                'selling_fare' => $fare->selling_fare,
                'child_fare_percentage' => $fare->child_fare_percentage,
                'infant_fare_percentage' => $fare->infant_fare_percentage,
                'offer_price' => $fare->offer_price,
                'available_seats' => $fare->groupTicket?->ticket_qty ?? null,
                'route_type' => $routeType,
                'flight_type' => $fare->route->flight_type?->value,
                'baggage_allowances' => $fare->baggageAllowances->map(function ($ba) {
                    return [
                        'passenger_type' => $ba->passenger_type,
                        'travel_direction' => $ba->travel_direction,
                        'allowance' => $ba->allowance,
                    ];
                })->toArray(),
            ];
        });

        $currencyRate = $booking->currencyRate;
        if (! $currencyRate) {
            $currencyRate = app(CurrencyRateService::class)->getRateForDate($booking->created_at);
        }
        $currentCurrencyRate = $currencyRate;

        $rate = $currentCurrencyRate?->rate ?? 0;
        $totalAmount = $booking->invoice?->total_amount ?? 0;
        $paidAmount = $booking->invoice?->paid_amount ?? 0;
        $balance = $booking->invoice?->balance ?? 0;
        $totalAmountBdt = $rate > 0 ? $totalAmount * $rate : 0;
        $paidAmountBdt = $rate > 0 ? $paidAmount * $rate : 0;
        $balanceBdt = $rate > 0 ? $balance * $rate : 0;

        $originalTotal = (float) ($booking->total_value ?? 0);
        $originalTotalBdt = $rate > 0 ? $originalTotal * $rate : 0;
        $discountedTotalBdt = $totalAmountBdt;

        $userBranchLocation = auth()->user()?->branch?->location?->value;
        $banks = Bank::orderBy('name')->get(['id', 'name', 'currency', 'location']);

        return view('bookings.show', compact(
            'booking', 'ticketFares', 'packages', 'currentCurrencyRate',
            'totalAmountBdt', 'paidAmountBdt', 'balanceBdt',
            'originalTotal', 'originalTotalBdt', 'discountedTotalBdt',
            'userBranchLocation', 'banks', 'isCrossBranchViewer'
        ));
    }

    public function edit(Booking $booking)
    {
        $this->ensureBranchAccess($booking);
        $this->ensureEditWindow($booking);

        if ($this->isGlobalNonAdmin() && $booking->user_id !== auth()->id()) {
            abort(403);
        }

        $booking->load(['customer', 'passengers', 'district', 'fingerprintBranch', 'package', 'documents', 'passengers.documents', 'passengers.ticketFare', 'passengers.ticketFareInbound.route', 'passengers.ticketFareOutbound.route', 'fingerprintCharge']);

        $bookingBranches = $this->isAdminRole() ? Branch::orderBy('name')->get(['id', 'name']) : collect();
        $fingerprintBranches = Branch::where('fingerprint_operation', true)->orderBy('name')->get(['id', 'name']);

        $districts = District::orderBy('name')->get();
        $packages = Package::where('is_active', true)->with(['ticketFare', 'visaSellingPrice', 'ticketFareInbound', 'ticketFareOutbound'])->orderBy('package_name')->get()->map(function ($pkg) {
            return [
                'id' => $pkg->id,
                'package_name' => $pkg->package_name,
                'ticket_fare_id' => $pkg->ticket_fare_id,
                'is_double_ticket' => $pkg->is_double_ticket,
                'ticket_fare_inbound_id' => $pkg->ticket_fare_inbound_id,
                'ticket_fare_outbound_id' => $pkg->ticket_fare_outbound_id,
                'visa_selling_price' => $pkg->visaSellingPrice?->selling_price ?? 0,
                'service_charge' => $pkg->service_charge ?? 0,
                'package_value' => $pkg->is_double_ticket
                    ? (($pkg->ticketFareInbound?->selling_fare ?? 0) + ($pkg->ticketFareOutbound?->selling_fare ?? 0) + ($pkg->visaSellingPrice?->selling_price ?? 0) + ($pkg->service_charge ?? 0))
                    : (($pkg->ticketFare?->selling_fare ?? 0) + ($pkg->visaSellingPrice?->selling_price ?? 0) + ($pkg->service_charge ?? 0)),
                'is_active' => true,
            ];
        });

        if ($booking->package_id) {
            $currentPackage = Package::with(['ticketFare', 'visaSellingPrice', 'ticketFareInbound', 'ticketFareOutbound'])->find($booking->package_id);
            if ($currentPackage && ! $currentPackage->is_active) {
                $packages->push([
                    'id' => $currentPackage->id,
                    'package_name' => $currentPackage->package_name,
                    'ticket_fare_id' => $currentPackage->ticket_fare_id,
                    'is_double_ticket' => $currentPackage->is_double_ticket,
                    'ticket_fare_inbound_id' => $currentPackage->ticket_fare_inbound_id,
                    'ticket_fare_outbound_id' => $currentPackage->ticket_fare_outbound_id,
                    'visa_selling_price' => $currentPackage->visaSellingPrice?->selling_price ?? 0,
                    'service_charge' => $currentPackage->service_charge ?? 0,
                    'package_value' => $currentPackage->is_double_ticket
                        ? (($currentPackage->ticketFareInbound?->selling_fare ?? 0) + ($currentPackage->ticketFareOutbound?->selling_fare ?? 0) + ($currentPackage->visaSellingPrice?->selling_price ?? 0) + ($currentPackage->service_charge ?? 0))
                        : (($currentPackage->ticketFare?->selling_fare ?? 0) + ($currentPackage->visaSellingPrice?->selling_price ?? 0) + ($currentPackage->service_charge ?? 0)),
                    'is_active' => false,
                ]);
            }
        }

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
                'airline' => $fare->airline->name,
                'airline_class' => $fare->airlineClass->class?->name,
                'ticket_type' => $fare->ticket_type->value,
                'selling_fare' => $fare->selling_fare,
                'child_fare_percentage' => $fare->child_fare_percentage,
                'infant_fare_percentage' => $fare->infant_fare_percentage,
                'offer_price' => $fare->offer_price,
                'available_seats' => $fare->groupTicket?->ticket_qty ?? null,
                'route_type' => $routeType,
                'flight_type' => $fare->route->flight_type?->value,
                'baggage_allowances' => $fare->baggageAllowances->map(function ($ba) {
                    return [
                        'passenger_type' => $ba->passenger_type,
                        'travel_direction' => $ba->travel_direction,
                        'allowance' => $ba->allowance,
                    ];
                })->toArray(),
            ];
        });

        $customers = Customer::orderBy('name')->get(['id', 'name', 'passport_no', 'iqama_no', 'mobile_no']);
        $currencyRate = $booking->currencyRate;
        if (! $currencyRate) {
            $currencyRate = app(CurrencyRateService::class)->getRateForDate($booking->created_at);
        }
        $currentCurrencyRate = $currencyRate;

        return view('bookings.edit', compact(
            'booking', 'districts', 'packages', 'ticketFares', 'customers', 'currentCurrencyRate', 'bookingBranches', 'fingerprintBranches'
        ));
    }

    public function update(Request $request, Booking $booking)
    {
        $this->ensureBranchAccess($booking);
        $this->ensureEditWindow($booking);

        if ($this->isGlobalNonAdmin() && $booking->user_id !== auth()->id()) {
            abort(403);
        }

        $validated = $request->validate([
            'customer_id' => 'sometimes|required|exists:customers,id',
            'district_id' => 'nullable|exists:districts,id',
            'fingerprint_branch_id' => 'nullable|exists:branches,id',
            'fingerprint_charge_id' => 'nullable|exists:fingerprint_charges,id',
            'booking_branch_id' => 'nullable|exists:branches,id',
            'fingerprint_location' => 'nullable|in:office,home',
            'package_id' => 'nullable|exists:packages,id',
            'discount_type' => 'nullable|in:fixed,percentage',
            'discount_value' => 'nullable|numeric|min:0',
            'remarks' => 'nullable|string|max:1000',
            'booking_customer_docs' => 'nullable|array',
            'booking_customer_docs.*' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
        ], [
            'booking_customer_docs.*.max' => 'Each file must not exceed 5 MB.',
            'booking_customer_docs.*.mimes' => 'Only PDF, JPG, JPEG, and PNG files are allowed.',
        ]);

        if ($request->hasFile('booking_customer_docs')) {
            $totalSize = collect($request->file('booking_customer_docs'))->sum(fn ($f) => $f->getSize());
            if ($totalSize > 20 * 1024 * 1024) {
                if ($request->expectsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'The total size of all uploaded files must not exceed 20 MB.',
                    ], 422);
                }

                return redirect()->back()->withErrors(['files' => 'The total size of all uploaded files must not exceed 20 MB.'])->withInput();
            }
        }

        try {
            DB::beginTransaction();

            $validated['discount_type'] = ($validated['discount_type'] ?? 'fixed') === 'fixed' ? 'fixed_amount' : 'percentage';
            if (! $this->isAdminRole()) {
                unset($validated['booking_branch_id']);
                unset($validated['package_id']);
                unset($validated['discount_type']);
                unset($validated['discount_value']);
            }
            $booking->update($validated);

            if ($request->has('package_id') && $booking->wasChanged('package_id')) {
                $package = Package::with(['ticketFare', 'ticketFareInbound', 'ticketFareOutbound'])->find($request->input('package_id'));
                if ($package) {
                    if ($package->is_double_ticket) {
                        $booking->passengers()
                            ->where(function ($q) {
                                $q->where('service_required', '!=', 'visa_only')
                                    ->orWhereNull('service_required');
                            })
                            ->update([
                                'ticket_fare_id' => null,
                                'ticket_fare_inbound_id' => $package->ticket_fare_inbound_id,
                                'ticket_fare_outbound_id' => $package->ticket_fare_outbound_id,
                            ]);
                    } elseif ($package->ticket_fare_id) {
                        $booking->passengers()
                            ->where(function ($q) {
                                $q->where('service_required', '!=', 'visa_only')
                                    ->orWhereNull('service_required');
                            })
                            ->update([
                                'ticket_fare_id' => $package->ticket_fare_id,
                                'ticket_fare_inbound_id' => null,
                                'ticket_fare_outbound_id' => null,
                            ]);
                    }
                }
            }

            if (($validated['fingerprint_location'] ?? null) === 'office' && $booking->fingerprint) {
                $booking->fingerprint->update(['assigned_staff_id' => null]);
            }

            $booking = $booking->fresh();
            $invoiceData = $this->syncBookingFinancials($booking, 'booking_updated');

            $discountType = $booking->discount_type;
            if ($discountType instanceof \BackedEnum) {
                $discountType = $discountType->value;
            }

            $customerDocs = $request->file('booking_customer_docs', []);
            if (is_array($customerDocs) && count($customerDocs) > 0) {
                $booking->load('customer');
                $invoiceId = $booking->invoice_id ?? 'INV';
                $customerName = $booking->customer->name ?? 'Customer';
                $customerDocCount = $booking->customer ? $booking->customer->documents->count() : 0;
                $bookingDocCount = $booking->documents()->count();

                foreach ($customerDocs as $index => $file) {
                    if ($file instanceof UploadedFile && $file->isValid()) {
                        $booking->documents()->create([
                            'owner_type' => 'booking',
                            'owner_id' => $booking->id,
                            'file_path' => $file->store('booking-docs', 'public'),
                            'display_name' => "{$invoiceId} {$customerName} ".($customerDocCount + $bookingDocCount + $index + 1),
                        ]);
                    }
                }
            }

            DB::commit();

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Booking updated successfully',
                    'invoice' => $invoiceData,
                    'discount' => [
                        'type' => $discountType,
                        'value' => (float) $booking->discount_value,
                        'amount' => (float) $booking->discount_amount,
                    ],
                ]);
            }

            return redirect()->route('bookings.show', $booking->id)
                ->with('success', 'Booking updated successfully');
        } catch (\Exception $e) {
            DB::rollBack();

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to update booking.',
                ], 500);
            }

            return redirect()->back()->with('error', 'Failed to update booking.')->withInput();
        }
    }

    public function updateFingerprintLocation(Request $request, Booking $booking)
    {
        $this->ensureBranchAccess($booking);

        $validated = $request->validate([
            'fingerprint_location' => 'required|in:home,office',
        ]);

        $user = auth()->user();
        if (! $user->hasRole('Super Admin') && ! $user->hasRole('Co Admin')) {
            $currentLocation = $booking->fingerprint_location?->value;
            $newLocation = $validated['fingerprint_location'];

            if ($currentLocation === 'home' && $newLocation === 'office') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot change Fingerprint Location from Home to Office.',
                ], 403);
            }
        }

        try {
            $booking->update(['fingerprint_location' => $validated['fingerprint_location']]);

            if ($validated['fingerprint_location'] === 'office' && $booking->fingerprint) {
                $booking->fingerprint->update(['assigned_staff_id' => null]);
            }

            $booking = $booking->fresh();
            $invoiceData = $this->syncBookingFinancials($booking, 'fingerprint_location_updated');

            return response()->json([
                'success' => true,
                'message' => 'Fingerprint location updated successfully',
                'fingerprint_location' => $booking->fingerprint_location?->value,
                'invoice' => $invoiceData,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update fingerprint location',
            ], 500);
        }
    }

    public function destroy(Booking $booking)
    {
        $this->ensureBranchAccess($booking);

        try {
            DB::transaction(function () use ($booking) {
                $passengerIds = $booking->passengers()->pluck('id');

                $fingerprintDetailIds = FingerprintDetail::whereIn('passenger_id', $passengerIds)->pluck('id');

                IssuedTicket::whereIn('passenger_id', $passengerIds)->forceDelete();
                RescheduledFingerprint::whereIn('fingerprint_detail_id', $fingerprintDetailIds)->delete();
                CancelledSubmission::whereIn('visa_submission_id', VisaSubmission::whereIn('passenger_id', $passengerIds)->pluck('id'))->delete();
                VisaSubmission::whereIn('passenger_id', $passengerIds)->delete();
                FingerprintDetail::whereIn('passenger_id', $passengerIds)->delete();
                Voucher::where('booking_id', $booking->id)->delete();
                $booking->payments()->delete();
                $booking->invoice()->delete();
                $booking->fingerprint()->delete();
                IssuedTicket::where('booking_id', $booking->id)->forceDelete();
                $booking->passengers()->delete();
                $booking->documents()->delete();
                $booking->delete();
            });

            return redirect()->route('bookings.index')
                ->with('success', 'Booking deleted successfully');
        } catch (\Exception $e) {
            \Log::error('Failed to delete booking: '.$e->getMessage());

            return redirect()->back()->with('error', 'Failed to delete booking.');
        }
    }

    public function addPassenger(Request $request, Booking $booking)
    {
        $this->ensureBranchAccess($booking);

        if ($this->isGlobalNonAdmin() && $booking->user_id !== auth()->id()) {
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
            'gender' => 'nullable|in:male,female',
            'ticket_fare_id' => 'nullable|exists:ticket_fares,id',
            'ticket_fare_inbound_id' => 'nullable|exists:ticket_fares,id',
            'ticket_fare_outbound_id' => 'nullable|exists:ticket_fares,id',
        ]);

        $passengerType = $this->bookingService->calculatePassengerType(
            $validated['date_of_birth'],
            // $validated['stay_duration'] ?? null
        );

        $isDoubleTicket = $booking->package?->is_double_ticket;

        $validated['booking_id'] = $booking->id;
        $validated['passenger_type'] = $passengerType;
        $validated['service_required'] = $validated['service_required'] ?? 'All';
        $validated['stay_duration'] = $validated['stay_duration'] ?? 14;
        $validated['ticket_fare_id'] = $isDoubleTicket
            ? null
            : (($validated['service_required'] ?? '') === 'visa_only'
                ? null
                : $booking->package?->ticket_fare_id);
        $validated['ticket_fare_inbound_id'] = $isDoubleTicket
            ? $booking->package?->ticket_fare_inbound_id
            : null;
        $validated['ticket_fare_outbound_id'] = $isDoubleTicket
            ? $booking->package?->ticket_fare_outbound_id
            : null;

        return DB::transaction(function () use ($booking, $validated) {
            $passenger = Passenger::create($validated);

            if (($validated['service_required'] ?? 'all') !== 'ticket_only') {
                VisaSubmission::create([
                    'passenger_id' => $passenger->id,
                    'visa_selling_price_id' => $booking->package?->visa_selling_price_id ?? VisaSellingPrice::latest('id')->value('id'),
                    'status' => 'pending',
                ]);
            }

            IssuedTicket::create([
                'booking_id' => $booking->id,
                'passenger_id' => $passenger->id,
                'ticket_fare_id' => $passenger->ticket_fare_id,
                'user_id' => auth()->id(),
                'status' => 'pending',
            ]);

            $fingerprint = Fingerprint::firstOrCreate(
                ['booking_id' => $booking->id],
                ['deadline' => $booking->created_at->addDays(10), 'cost' => 0]
            );

            FingerprintDetail::create([
                'fingerprint_id' => $fingerprint->id,
                'passenger_id' => $passenger->id,
                'status' => 'none',
            ]);

            $booking->update(['pax_qty' => $booking->passengers()->count()]);
            $booking = $booking->fresh();

            $invoiceData = $this->syncBookingFinancials($booking, 'passenger_added');

            $passenger = $passenger->fresh()->load('ticketFare');

            return response()->json([
                'success' => true,
                'message' => 'Passenger added successfully',
                'passenger' => $passenger,
                'display_total' => $passenger->package_value ?? 0,
                'invoice' => $invoiceData,
            ]);
        });
    }

    public function removePassenger(Booking $booking, Passenger $passenger)
    {
        $this->ensureBranchAccess($booking);

        if ($passenger->booking_id !== $booking->id) {
            return response()->json(['success' => false, 'message' => 'Passenger does not belong to this booking'], 403);
        }

        try {
            $detail = FingerprintDetail::where('passenger_id', $passenger->id)->first();
            if ($detail) {
                $detail->rescheduledFingerprints()->delete();
                $detail->delete();
            }

            $passenger->issuedTickets()->forceDelete();
            $passenger->delete();
            $booking->update(['pax_qty' => $booking->passengers()->count()]);
            $booking = $booking->fresh();

            $invoiceData = $this->syncBookingFinancials($booking, 'passenger_removed');

            return response()->json([
                'success' => true,
                'message' => 'Passenger removed successfully',
                'invoice' => $invoiceData,
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to remove passenger'], 500);
        }
    }

    public function calculatePassengerType(Request $request)
    {
        $dateOfBirth = $request->input('date_of_birth');
        // $stayDuration = $request->input('stay_duration');

        if (! $dateOfBirth) {
            return response()->json(['passenger_type' => null]);
        }

        $passengerType = $this->bookingService->calculatePassengerType($dateOfBirth /* , $stayDuration */);

        return response()->json([
            'passenger_type' => $passengerType,
        ]);
    }

    public function getFingerprintCharge(Request $request)
    {
        $districtId = $request->input('district_id');
        $location = $request->input('location', 'Office');

        if (! $districtId) {
            return response()->json(['error' => 'District is required'], 422);
        }

        $fingerprintCharge = FingerprintCharge::where('district_id', $districtId)->first();

        if (! $fingerprintCharge) {
            return response()->json(['error' => 'No fingerprint charge found for selected district. Please contact admin to set up fingerprint charges.'], 422);
        }

        $charge = $location === 'home' ? $fingerprintCharge->fingerprint_charge : 0;

        return response()->json([
            'charge' => $charge,
            'fingerprint_charge_id' => $fingerprintCharge->id,
        ]);
    }

    public function print(Booking $booking)
    {
        $booking = Booking::with([
            'customer',
            'bookingBranch',
            'fingerprintBranch',
            'package',
            'currencyRate',
            'passengers',
            'passengers.ticketFare.airline',
            'passengers.ticketFare.airlineClass.travelClass',
            'passengers.ticketFare.route',
            'passengers.ticketFare.route.fromCity',
            'passengers.ticketFare.route.toCity',
            'passengers.ticketFare.route.returnCity',
            'passengers.ticketFare.route.multiSegments.fromCity',
            'passengers.ticketFare.route.multiSegments.toCity',
            'passengers.ticketFare.baggageAllowances',
            'passengers.allIssuedTickets.ticketFare.airline',
            'passengers.allIssuedTickets.ticketFare.airlineClass.travelClass',
            'passengers.allIssuedTickets.ticketFare.route',
            'passengers.allIssuedTickets.ticketFare.route.fromCity',
            'passengers.allIssuedTickets.ticketFare.route.toCity',
            'passengers.allIssuedTickets.ticketFare.route.returnCity',
            'passengers.allIssuedTickets.ticketFare.route.multiSegments.fromCity',
            'passengers.allIssuedTickets.ticketFare.route.multiSegments.toCity',
            'passengers.allIssuedTickets.ticketFare.baggageAllowances',
            'passengers.latestIssuedTicket.ticketFare.airline',
            'passengers.latestIssuedTicket.ticketFare.airlineClass.travelClass',
            'passengers.latestIssuedTicket.ticketFare.route.fromCity',
            'passengers.latestIssuedTicket.ticketFare.route.toCity',
            'passengers.latestIssuedTicket.ticketFare.route.returnCity',
            'passengers.latestIssuedTicket.ticketFare.route.multiSegments.fromCity',
            'passengers.latestIssuedTicket.ticketFare.route.multiSegments.toCity',
            'passengers.latestIssuedTicket.ticketFare.baggageAllowances',
            'passengers.latestIssuedTicket.latestReIssuedTicket.ticketFare.airline',
            'passengers.latestIssuedTicket.latestReIssuedTicket.ticketFare.airlineClass.travelClass',
            'passengers.latestIssuedTicket.latestReIssuedTicket.ticketFare.route.fromCity',
            'passengers.latestIssuedTicket.latestReIssuedTicket.ticketFare.route.toCity',
            'passengers.latestIssuedTicket.latestReIssuedTicket.ticketFare.route.returnCity',
            'passengers.latestIssuedTicket.latestReIssuedTicket.ticketFare.route.multiSegments.fromCity',
            'passengers.latestIssuedTicket.latestReIssuedTicket.ticketFare.route.multiSegments.toCity',
            'passengers.latestIssuedTicket.latestReIssuedTicket.ticketFare.baggageAllowances',
            'passengers.latestIssuedTicket.latestReIssuedTicket.route.fromCity',
            'passengers.latestIssuedTicket.latestReIssuedTicket.route.toCity',
            'payments',
            'invoice',
        ])->findOrFail($booking->id);

        $subTotal = (float) $booking->passengers->sum('package_value');

        $fingerprintCharge = 0;
        $fpLocation = $booking->fingerprint_location;
        if ($fpLocation instanceof \BackedEnum) {
            $fpLocation = $fpLocation->value;
        }
        if ($fpLocation && strtolower($fpLocation) !== 'office') {
            $fpCharge = FingerprintCharge::where('district_id', $booking->district_id)->first();
            $fingerprintCharge = $fpCharge ? (float) $fpCharge->fingerprint_charge : 0;
        }

        $totalPackages = $booking->pax_qty;

        $discount = 0;
        $baseForDiscount = $subTotal + $fingerprintCharge;
        if ($booking->discount_value && $booking->discount_value > 0 && $booking->discount_type) {
            $discountType = $booking->discount_type;
            if ($discountType instanceof \BackedEnum) {
                $discountType = $discountType->value;
            }
            $discount = $discountType === 'percentage'
                ? $baseForDiscount * ($booking->discount_value / 100)
                : min($booking->discount_value, $baseForDiscount);
        }

        $grandTotal = $subTotal + $fingerprintCharge - $discount;

        // Currency rate resolution
        $currencyRate = $booking->currencyRate;
        if (! $currencyRate) {
            $currencyRate = CurrencyRate::where('created_at', '<=', $booking->created_at)
                ->orderBy('created_at', 'desc')
                ->first();
        }
        $rate = $currencyRate?->rate;
        $useBdt = $rate && $rate > 0;
        $currencySuffix = $useBdt ? 'BDT' : 'SAR';

        if ($useBdt) {
            $displayRate = (float) $rate;
            $displaySubTotal = $subTotal * $rate;
            $displayFingerprintCharge = $fingerprintCharge * $rate;
            $displayDiscount = $discount * $rate;
            $displayGrandTotal = $grandTotal * $rate;
            $displayCurrentPaid = (float) ($booking->payments->last()?->bdt_amount ?? 0);
            $displayTotalPaid = (float) $booking->payments->sum('bdt_amount');
        } else {
            $displayRate = 0;
            $displaySubTotal = $subTotal;
            $displayFingerprintCharge = $fingerprintCharge;
            $displayDiscount = $discount;
            $displayGrandTotal = $grandTotal;
            $displayCurrentPaid = (float) ($booking->payments->last()?->amount ?? 0);
            $displayTotalPaid = (float) ($booking->payments->sum('amount'));
        }

        $displayPreviousPaid = $displayTotalPaid - $displayCurrentPaid;
        $displayDueAmount = $displayGrandTotal - $displayTotalPaid;
        $totalPaid = (float) ($booking->invoice->paid_amount ?? 0);
        $currentPaid = (float) ($booking->payments->last()?->amount ?? 0);
        $dueAmount = (float) ($booking->invoice->total_amount ?? 0) - $totalPaid;
        $invoiceDate = $booking->payments->last()?->payment_date?->format('d M Y');

        $conditions = BookingCondition::where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get();

        return view('bookings.invoice-print', compact(
            'booking',
            'subTotal',
            'fingerprintCharge',
            'totalPackages',
            'discount',
            'grandTotal',
            'totalPaid',
            'currentPaid',
            'dueAmount',
            'invoiceDate',
            'conditions',
            'useBdt',
            'currencySuffix',
            'displayRate',
            'displaySubTotal',
            'displayFingerprintCharge',
            'displayDiscount',
            'displayGrandTotal',
            'displayCurrentPaid',
            'displayTotalPaid',
            'displayPreviousPaid',
            'displayDueAmount',
            'rate'
        ));
    }

    public function storePayment(Request $request, Booking $booking)
    {
        if ($booking->is_cancelled && $booking->cancelledBooking?->status?->value === 'cancelled') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot process payment for a cancelled booking.',
            ], 422);
        }

        $validated = $request->validate([
            'amount' => 'nullable|numeric|min:0',
            'amount_bdt' => 'nullable|numeric|min:0',
            'currency' => 'nullable|in:SAR,BDT',
            'payment_method' => 'nullable|in:cash,bank',
            'bank_method' => 'nullable|string|max:255',
            'transaction_id' => 'nullable|string|max:255',
        ]);

        $amount = $validated['amount'] ?? 0;
        $bdtAmount = $validated['amount_bdt'] ?? 0;

        if ($amount == 0 && $bdtAmount == 0) {
            return response()->json([
                'success' => false,
                'message' => 'Please enter payment amount',
            ], 422);
        }

        try {
            $invoice = $booking->invoice;
            if (! $invoice) {
                $invoice = Invoice::create([
                    'booking_id' => $booking->id,
                    'total_amount' => $booking->total_value ?? 0,
                    'paid_amount' => 0,
                    'balance' => $booking->total_value ?? 0,
                ]);
            }

            $dueCollectionTransactionType = TransactionType::where('name', 'Due Collection')->first();
            if (! $dueCollectionTransactionType) {
                throw new \Exception('Due Collection transaction type not found. Please seed transaction types.');
            }

            $bankId = null;
            if (($validated['payment_method'] ?? '') === 'bank' && ! empty($validated['bank_method'])) {
                $bank = Bank::where('name', $validated['bank_method'])->first();
                $bankId = $bank?->id;
            }

            $paymentData = [
                'branch_id' => auth()->user()->branch_id ?? $booking->booking_branch_id,
                'user_id' => auth()->id(),
                'payment_date' => now()->toDateString(),
                'payment_method' => $validated['payment_method'] ?? 'cash',
                'amount' => $amount,
                'bdt_amount' => $bdtAmount,
                'currency' => $validated['currency'] ?? 'SAR',
                'bank_id' => $bankId,
                'transaction_id' => $validated['transaction_id'] ?? null,
                'transaction_type_id' => $dueCollectionTransactionType->id,
            ];

            [$payment, $voucher] = app(PaymentService::class)->createCustomerPaymentAndUpdateInvoice($invoice, $paymentData);

            $invoice->refresh();
            if ($booking->is_cancelled
                && $booking->cancelledBooking?->status?->value === 'cancellation processing') {
                $costSummary = app(CostTrackingService::class)->getBookingCostSummary($booking);
                $totalCost = $costSummary['total_cost'];
                $serviceCharge = $booking->cancelledBooking->service_charge_deduction ?? 0;
                $rawRefund = max(0, $invoice->paid_amount - $totalCost - $serviceCharge);
                $remaining = app(RefundCapService::class)->getCap($invoice)['remaining'];
                $refundAmount = min($rawRefund, $remaining);
                $booking->cancelledBooking->update([
                    'total_paid' => $invoice->paid_amount,
                    'refund_amount' => $refundAmount,
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Payment saved successfully',
                'payment' => $payment,
                'voucher' => $voucher,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e instanceof QueryException
                    ? DatabaseErrorHumanizer::humanize($e)
                    : 'Failed to save payment.',
            ], 500);
        }
    }

    public function updatePayment(Request $request, Booking $booking, Payment $payment)
    {
        if ($booking->is_cancelled && $booking->cancelledBooking?->status?->value === 'cancelled') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot update payment for a cancelled booking.',
            ], 422);
        }

        $validated = $request->validate([
            'amount' => 'nullable|numeric|min:0',
            'amount_bdt' => 'nullable|numeric|min:0',
            'currency' => 'nullable|in:SAR,BDT',
            'payment_method' => 'nullable|in:cash,bank',
            'bank_method' => 'nullable|string|max:255',
            'transaction_id' => 'nullable|string|max:255',
        ]);

        $amount = $validated['amount'] ?? 0;
        $bdtAmount = $validated['amount_bdt'] ?? 0;

        if ($amount == 0 && $bdtAmount == 0) {
            return response()->json([
                'success' => false,
                'message' => 'Please enter payment amount',
            ], 422);
        }

        try {
            $bankId = null;
            if (($validated['payment_method'] ?? '') === 'bank' && ! empty($validated['bank_method'])) {
                $bank = Bank::where('name', $validated['bank_method'])->first();
                $bankId = $bank?->id;
            }

            DB::transaction(function () use ($payment, $validated, $amount, $bdtAmount, $bankId, $booking) {
                $payment->update([
                    'payment_method' => $validated['payment_method'] ?? 'cash',
                    'amount' => $amount,
                    'bdt_amount' => $bdtAmount,
                    'bank_id' => $bankId,
                    'transaction_id' => $validated['transaction_id'] ?? null,
                ]);

                $voucher = $payment->vouchers()->first();
                if ($voucher) {
                    $voucher->update([
                        'amount' => $amount,
                        'bdt_amount' => $bdtAmount,
                        'payment_method' => $validated['payment_method'] ?? 'cash',
                        'transaction_id' => $validated['transaction_id'] ?? null,
                    ]);
                }

                $invoice = $booking->invoice;
                if ($invoice) {
                    $totalPaid = $booking->payments()->sum('amount');
                    $dueAdjustments = (float) $booking->payments()
                        ->whereNotNull('cancelled_passenger_id')
                        ->whereHas('voucher.transactionType', function ($query) {
                            $query->where('name', 'Due Adjustment');
                        })
                        ->sum('amount');
                    $invoice->update([
                        'paid_amount' => $totalPaid,
                        'balance' => max(0, $invoice->total_amount - $totalPaid - $dueAdjustments),
                    ]);
                }
            });

            return response()->json([
                'success' => true,
                'message' => 'Payment updated successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e instanceof QueryException
                    ? DatabaseErrorHumanizer::humanize($e)
                    : 'Failed to update payment.',
            ], 500);
        }
    }

    public function recalculatePassengerValue(Passenger $passenger)
    {
        $this->ensureBranchAccess($passenger->booking);

        $packageValue = $this->bookingService->calculatePackageValue($passenger);
        $passenger->update(['package_value' => $packageValue]);
        $booking = $passenger->booking->fresh();

        $invoiceData = $this->syncBookingFinancials($booking, 'passenger_value_recalculated');

        return response()->json([
            'package_value' => $packageValue,
            'total_value' => $booking->total_value,
            'invoice' => $invoiceData,
        ]);
    }

    public function downloadAllDocs(Booking $booking)
    {
        $this->ensureBranchAccess($booking);

        $booking->load('customer', 'passengers');

        $passengerIds = $booking->passengers->pluck('id');
        $scope = request()->query('scope');
        $allDocs = Document::where(function ($q) use ($booking, $passengerIds, $scope) {
            if ($scope === 'customer') {
                $q->where(function ($q) use ($booking) {
                    $q->whereIn('owner_type', ['App\Models\Booking', 'booking'])
                        ->where('owner_id', $booking->id);
                });
                if ($booking->customer) {
                    $q->orWhere(function ($q) use ($booking) {
                        $q->where('owner_type', 'App\Models\Customer')
                            ->where('owner_id', $booking->customer_id);
                    });
                }
            } elseif ($scope === 'passenger') {
                $q->where('owner_type', 'App\Models\Passenger')
                    ->whereIn('owner_id', $passengerIds);
            } else {
                $q->where(function ($q) use ($booking) {
                    $q->whereIn('owner_type', ['App\Models\Booking', 'booking'])
                        ->where('owner_id', $booking->id);
                });
                if ($booking->customer) {
                    $q->orWhere(function ($q) use ($booking) {
                        $q->where('owner_type', 'App\Models\Customer')
                            ->where('owner_id', $booking->customer_id);
                    });
                }
            }
        })->when(! $scope || $scope === 'all', function ($q) use ($passengerIds) {
            $passengerId = request()->query('passenger_id');
            $targetIds = $passengerId ? [$passengerId] : $passengerIds;
            $q->orWhere(function ($q) use ($targetIds) {
                $q->where('owner_type', 'App\Models\Passenger')
                    ->whereIn('owner_id', $targetIds);
            });
        })->get();

        abort_if($allDocs->isEmpty(), 404, 'No documents found');

        $invoiceId = $booking->invoice_id ?? 'INV';
        $customerName = $booking->customer->name ?? 'Customer';
        $suffix = $scope === 'customer' ? 'Customer Docs' : ($scope === 'passenger' ? 'Passenger Docs' : 'All Docs');
        $fileName = "{$invoiceId} {$customerName} {$suffix}.pdf";

        $tmpDir = storage_path('app/tmp/merge_'.uniqid());
        mkdir($tmpDir, 0755, true);

        $pdfFiles = [];

        try {
            foreach ($allDocs as $doc) {
                $fullPath = $this->resolveDocumentPath($doc);
                if (! $fullPath || ! file_exists($fullPath)) {
                    continue;
                }

                $ext = strtolower(pathinfo($doc->file_path, PATHINFO_EXTENSION));
                $tmpFile = $tmpDir.'/doc_'.$doc->id.'.pdf';

                if (in_array($ext, ['jpg', 'jpeg', 'png'])) {
                    $pdf = new \FPDF;
                    $pdf->AddPage();
                    [$imgW, $imgH] = getimagesize($fullPath);
                    $scale = min($pdf->GetPageWidth() / $imgW, $pdf->GetPageHeight() / $imgH);
                    $w = $imgW * $scale;
                    $h = $imgH * $scale;
                    $x = ($pdf->GetPageWidth() - $w) / 2;
                    $y = ($pdf->GetPageHeight() - $h) / 2;
                    $pdf->Image($fullPath, $x, $y, $w, $h);
                    $pdf->Output('F', $tmpFile);
                    $pdfFiles[] = $tmpFile;
                } elseif ($ext === 'pdf') {
                    copy($fullPath, $tmpFile);
                    $pdfFiles[] = $tmpFile;
                }
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

    public function searchInvoice(Request $request)
    {
        $query = $request->input('q', '');

        if (strlen($query) < 1) {
            return response()->json([]);
        }

        $bookings = Booking::where('invoice_id', 'like', "%{$query}%")
            ->limit(10)
            ->get(['id', 'invoice_id']);

        return response()->json($bookings);
    }

    private function resolveDocumentPath(Document $doc): ?string
    {
        if (Storage::disk('public')->exists($doc->file_path)) {
            return Storage::disk('public')->path($doc->file_path);
        }
        if (Storage::exists($doc->file_path)) {
            return Storage::path($doc->file_path);
        }

        return null;
    }
}
