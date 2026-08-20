<?php

namespace App\Queries;

use App\Enums\FingerprintLocation;
use App\Enums\VisaStatus;
use App\Models\Branch;
use App\Models\Package;
use App\Models\Passenger;
use App\Models\PassengerStatus;
use App\Models\Route;
use App\Models\TicketAgent;
use App\Models\VisaAgent;
use Illuminate\Http\Request;

/**
 * Encapsulates the Passenger query + filters used by:
 *  - BookingController::passengerData() (Livewire PassengerIndexTable endpoint)
 *
 * Replaces the ~130 lines of inline query-builder chain in passengerData().
 * Follows the pattern established by FingerprintReportQuery.
 */
class PassengerIndexQuery
{
    protected $query;

    protected ?string $search;

    protected ?string $bookingDateFrom;

    protected ?string $bookingDateTo;

    protected ?string $actualFlightFrom;

    protected ?string $actualFlightTo;

    protected ?string $returnDateFrom;

    protected ?string $returnDateTo;

    protected ?string $fingerprintStatus;

    protected ?string $visaStatus;

    protected ?string $ticketStatus;

    protected ?string $visaAgentId;

    protected ?string $ticketAgentId;

    protected ?string $passengerStatus;

    protected ?string $routeDisplay;

    protected ?string $packageId;

    protected ?string $statusChangeAction;

    protected ?string $paymentWise;

    protected ?string $bookingBranchId;

    protected ?string $bookingStatus;

    protected ?int $userBranchId;

    protected int $perPage;

    protected ?int $selectedBranchId;

    protected array $eagerLoads = [
        'booking.customer',
        'booking.package',
        'booking.invoice',
        'booking.fingerprintBranch',
        'booking.bookingBranch',
        'booking.district',
        'booking.currencyRate',
        'booking.documents',
        'visaSubmission',
        'visaSubmission.visaAgent',
        'visaSubmission.commissionAgent',
        'fingerprintDetail.fingerprint.fingerprintDetails',
        'fingerprintDetail.approvedLog',
        'allIssuedTickets',
        'allIssuedTickets.ticketAgent',
        'allIssuedTickets.ticketFare.airline',
        'allIssuedTickets.ticketFare.airlineClass.class',
        'allIssuedTickets.ticketFare.route.fromCity',
        'allIssuedTickets.ticketFare.route.toCity',
        'allIssuedTickets.ticketFare.route.returnCity',
        'allIssuedTickets.latestReIssuedTicket',
        'allIssuedTickets.latestRefundedTicket',
        'latestIssuedTicket',
        'status',
    ];

    public function __construct(Request $request)
    {
        $user = $request->user();
        $userBranchId = $user?->branch_id;

        $this->search = $request->filled('search') ? $request->input('search') : null;
        $this->bookingDateFrom = $request->filled('booking_date_from') ? $request->input('booking_date_from') : null;
        $this->bookingDateTo = $request->filled('booking_date_to') ? $request->input('booking_date_to') : null;
        $this->actualFlightFrom = $request->filled('actual_flight_from') ? $request->input('actual_flight_from') : null;
        $this->actualFlightTo = $request->filled('actual_flight_to') ? $request->input('actual_flight_to') : null;
        $this->returnDateFrom = $request->filled('return_date_from') ? $request->input('return_date_from') : null;
        $this->returnDateTo = $request->filled('return_date_to') ? $request->input('return_date_to') : null;
        $this->fingerprintStatus = $request->filled('fingerprint_status') ? $request->input('fingerprint_status') : null;
        $this->visaStatus = $request->filled('visa_status') ? $request->input('visa_status') : null;
        $this->ticketStatus = $request->filled('ticket_status') ? $request->input('ticket_status') : null;
        $this->visaAgentId = $request->filled('visa_agent_id') ? $request->input('visa_agent_id') : null;
        $this->ticketAgentId = $request->filled('ticket_agent_id') ? $request->input('ticket_agent_id') : null;
        $this->passengerStatus = $request->filled('passenger_status') ? $request->input('passenger_status') : null;
        $this->routeDisplay = $request->filled('route_display') ? $request->input('route_display') : null;
        $this->packageId = $request->filled('package_id') ? $request->input('package_id') : null;
        $this->statusChangeAction = $request->filled('status_change_action') ? $request->input('status_change_action') : null;
        $this->paymentWise = $request->filled('payment_wise') ? $request->input('payment_wise') : null;
        $this->bookingBranchId = $request->filled('booking_branch_id') ? $request->input('booking_branch_id') : null;
        $this->bookingStatus = $request->filled('booking_status') ? $request->input('booking_status') : null;

        $this->perPage = (int) ($request->filled('per_page') ? $request->input('per_page') : 15);
        $this->userBranchId = $userBranchId;
        $this->selectedBranchId = ! $userBranchId && $request->filled('booking_branch_id')
            ? (int) $request->input('booking_branch_id')
            : null;

        $this->query = Passenger::query();
        $this->applyScopes($userBranchId);
    }

    protected function applyScopes(?int $userBranchId): static
    {
        $this->query
            ->when($this->search, function ($q) {
                $term = $this->search;
                $q->where(function ($q) use ($term) {
                    $q->whereHas('booking.customer', fn ($cq) => $cq->where('name', 'like', "%{$term}%")
                        ->orWhere('mobile_no', 'like', "%{$term}%")
                        ->orWhere('passport_no', 'like', "%{$term}%"))
                        ->orWhereHas('booking', fn ($bq) => $bq->where('invoice_id', 'like', "%{$term}%"))
                        ->orWhere('first_name', 'like', "%{$term}%")
                        ->orWhere('last_name', 'like', "%{$term}%")
                        ->orWhere('passport_no', 'like', "%{$term}%")
                        ->orWhere('mobile_no', 'like', "%{$term}%")
                        ->orWhereHas('visaSubmission', fn ($vq) => $vq->where('visa_number', 'like', "%{$term}%"))
                        ->orWhereHas('allIssuedTickets', fn ($iq) => $iq->where('pnr', 'like', "%{$term}%")->orWhere('ticket_number', 'like', "%{$term}%"));
                });
            })
            ->when($userBranchId, fn ($q) => $q->whereHas('booking', fn ($q) => $q->where(function ($q) use ($userBranchId) {
                $q->where('booking_branch_id', $userBranchId)
                    ->orWhere('fingerprint_branch_id', $userBranchId);
            })))
            ->when($this->selectedBranchId, fn ($q) => $q->whereHas('booking', fn ($q) => $q->where('booking_branch_id', $this->selectedBranchId)))
            ->when($this->bookingStatus, function ($q) {
                $status = $this->bookingStatus;
                if ($status === 'active') {
                    $q->whereHas('booking', fn ($bq) => $bq->where('is_cancelled', false));
                } elseif ($status === 'cancellation_processing') {
                    $q->whereHas('booking', fn ($bq) => $bq->where('is_cancelled', true)
                        ->whereHas('cancelledBooking', fn ($cq) => $cq->where('status', 'cancellation processing')));
                } elseif ($status === 'cancelled') {
                    $q->whereHas('booking', fn ($bq) => $bq->where('is_cancelled', true)
                        ->where(fn ($bw) => $bw->whereDoesntHave('cancelledBooking')
                            ->orWhereHas('cancelledBooking', fn ($cq) => $cq->where('status', 'cancelled'))));
                }
            })->when($this->fingerprintStatus, fn ($q) => $q->whereHas('fingerprintDetail', fn ($q) => $q->where('status', $this->fingerprintStatus))
            )->when($this->visaStatus, fn ($q) => $q->whereHas('visaSubmission', fn ($q) => $q->where('status', $this->visaStatus))
            )->when($this->ticketStatus, function ($q) {
                $val = $this->ticketStatus;

                if (in_array($val, ['partial-re-issued', 'partial-refunded', 're-issued', 'refunded'])) {
                    $targetStatus = str_contains($val, 're-issued') ? 're-issued' : 'refunded';
                    $q->whereHas('allIssuedTickets', fn ($iq) => $iq->where('status', $targetStatus));
                } elseif (str_starts_with($val, 'issued-') || str_starts_with($val, 'awaiting-group')) {
                    $isIssued = str_starts_with($val, 'issued-');
                    $status = $isIssued ? 'issued' : 'awaiting-group';
                    $q->whereHas('allIssuedTickets', fn ($iq) => $iq->where('status', $status));
                } elseif ($val === 'pending') {
                    $q->whereDoesntHave('allIssuedTickets');
                }
            })->when($this->visaAgentId, fn ($q) => $q->whereHas('visaSubmission', fn ($q) => $q->where('visa_agent_id', $this->visaAgentId))
            )->when($this->ticketAgentId, fn ($q) => $q->whereHas('allIssuedTickets', fn ($q) => $q->where('ticket_agent_id', $this->ticketAgentId))
            )->when($this->passengerStatus, fn ($q) => $q->where('passenger_status_id', $this->passengerStatus)
            )->when($this->routeDisplay, fn ($q) => $q->whereHas('ticketFare.route', fn ($q) => $q->where('id', $this->routeDisplay))
            )->when($this->packageId, fn ($q) => $q->whereHas('booking', fn ($q) => $q->where('package_id', $this->packageId))
            )->when($this->bookingDateFrom, fn ($q) => $q->whereHas('booking', fn ($q) => $q->whereDate('created_at', '>=', $this->bookingDateFrom))
            )->when($this->bookingDateTo, fn ($q) => $q->whereHas('booking', fn ($q) => $q->whereDate('created_at', '<=', $this->bookingDateTo))
            )->when($this->actualFlightFrom, function ($q) {
                $d = $this->actualFlightFrom;
                $q->where(function ($q) use ($d) {
                    $q->whereDate('actual_flight_from', '>=', $d)
                        ->orWhereHas('allIssuedTickets', fn ($iq) => $iq->whereDate('actual_flight_from', '>=', $d));
                });
            })->when($this->actualFlightTo, function ($q) {
                $d = $this->actualFlightTo;
                $q->where(function ($q) use ($d) {
                    $q->whereDate('actual_flight_to', '<=', $d)
                        ->orWhereHas('allIssuedTickets', fn ($iq) => $iq->whereDate('actual_flight_to', '<=', $d));
                });
            })->when($this->returnDateFrom, fn ($q) => $q->whereDate('actual_return_from', '>=', $this->returnDateFrom)
            )->when($this->returnDateTo, fn ($q) => $q->whereDate('actual_return_to', '<=', $this->returnDateTo)
            )->when($this->statusChangeAction, function ($q) {
                $action = $this->statusChangeAction;
                if ($action) {
                    $q->where(function ($q) use ($action) {
                        $q->where('passenger_status_id', $action)
                            ->orWhereHas('statusChanges', fn ($sq) => $sq->where('new_values->passenger_status_id', $action));
                    });
                }
            })->when($this->paymentWise, function ($q) {
                $val = $this->paymentWise;
                if ($val === 'paid') {
                    $q->whereHas('booking.invoice', fn ($iq) => $iq->whereColumn('paid_amount', '>=', 'total_amount'));
                } elseif ($val === 'due') {
                    $q->whereHas('booking.invoice', fn ($iq) => $iq->whereColumn('paid_amount', '<', 'total_amount'));
                }
            });

        $this->query->with($this->eagerLoads)
            ->withCount('documents')
            ->orderBy('created_at', 'desc');

        return $this;
    }

    public function getQuery()
    {
        return clone $this->query;
    }

    public function paginate(?int $perPage = null, ?string $pageName = 'page', ?int $page = null)
    {
        return $this->query->paginate($perPage ?? $this->perPage, ['*'], $pageName, $page);
    }

    public function getTotalCount(): int
    {
        $q = clone $this->query;
        $q->getQuery()->orders = [];

        return $q->count();
    }

    public function getFilterOptions(): array
    {
        $user = request()->user();
        $userBranchId = $user?->branch_id;

        return [
            'passengerStatuses' => PassengerStatus::all(),
            'visaStatuses' => VisaStatus::cases(),
            'ticketStatuses' => [
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
            ],
            'visaAgents' => $user && in_array($user->roles->pluck('name')->first(), ['Super Admin', 'Co Admin', 'Visa Admin'])
                ? VisaAgent::with(['visaAgentCost', 'commissionAgents'])
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
                    ])
                : collect(),
            'ticketAgents' => $user && in_array($user->roles->pluck('name')->first(), ['Super Admin', 'Co Admin', 'Ticket Admin'])
                ? TicketAgent::orderBy('name')->get()
                : collect(),
            'routesList' => Route::orderBy('from_city_id')->get(['id', 'route_type', 'from_city_id', 'to_city_id']),
            'packagesList' => Package::where('is_active', true)->orderBy('package_name')->get(['id', 'package_name']),
            'fingerprintLocations' => FingerprintLocation::cases(),
            'bookingBranches' => $userBranchId ? collect() : Branch::orderBy('name')->get(['id', 'name']),
            'canEditVisa' => $user && in_array($user->roles->pluck('name')->first(), ['Super Admin', 'Co Admin', 'Visa Admin']),
            'canEditFingerprint' => $user && in_array($user->roles->pluck('name')->first(), ['Super Admin', 'Co Admin', 'Fingerprint Admin', 'Delivery Staff']),
            'canEditTicket' => $user && in_array($user->roles->pluck('name')->first(), ['Super Admin', 'Co Admin', 'Ticket Admin']),
        ];
    }
}
