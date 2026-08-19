<?php

namespace App\Livewire\Booking;

use App\Enums\FingerprintLocation;
use App\Http\Controllers\BookingController;
use App\Models\Branch;
use Illuminate\Http\Request;
use Livewire\Component;

class PassengerIndexTable extends Component
{
    public ?string $search = null;

    public ?string $bookingDateFrom = null;

    public ?string $bookingDateTo = null;

    public ?string $actualFlightFrom = null;

    public ?string $actualFlightTo = null;

    public ?string $returnDateFrom = null;

    public ?string $returnDateTo = null;

    public ?string $fingerprintStatus = null;

    public ?string $visaStatus = null;

    public ?string $ticketStatus = null;

    public ?string $visaAgentId = null;

    public ?string $ticketAgentId = null;

    public ?string $passengerStatus = null;

    public ?string $routeDisplay = null;

    public ?string $packageId = null;

    public ?string $statusChangeAction = null;

    public ?string $statusChangeFrom = null;

    public ?string $statusChangeTo = null;

    public ?string $paymentWise = null;

    public ?string $bookingBranchId = null;

    public ?string $bookingStatus = null;

    public int $perPage = 15;

    public $passengers = [];

    public $summary = [];

    public $pagination = [
        'current_page' => 1,
        'last_page' => 1,
        'per_page' => 15,
        'total' => 0,
    ];

    public $filterOptions = [];

    public $branches = [];

    public bool $loading = false;

    public function boot()
    {
        $this->branches = $this->branchOptions();
        $this->loadData();
    }

    protected function branchOptions()
    {
        if (auth()->user()->branch_id) {
            return [];
        }

        return Branch::orderBy('name')->get(['id', 'name'])->keyBy('id')->toArray();
    }

    protected function loadData(): void
    {
        $params = array_filter([
            'search' => $this->search,
            'booking_date_from' => $this->bookingDateFrom,
            'booking_date_to' => $this->bookingDateTo,
            'actual_flight_from' => $this->actualFlightFrom,
            'actual_flight_to' => $this->actualFlightTo,
            'return_date_from' => $this->returnDateFrom,
            'return_date_to' => $this->returnDateTo,
            'fingerprint_status' => $this->fingerprintStatus,
            'visa_status' => $this->visaStatus,
            'ticket_status' => $this->ticketStatus,
            'visa_agent_id' => $this->visaAgentId,
            'ticket_agent_id' => $this->ticketAgentId,
            'passenger_status' => $this->passengerStatus,
            'route_display' => $this->routeDisplay,
            'package_id' => $this->packageId,
            'status_change_action' => $this->statusChangeAction,
            'status_change_from' => $this->statusChangeFrom,
            'status_change_to' => $this->statusChangeTo,
            'payment_wise' => $this->paymentWise,
            'booking_branch_id' => $this->bookingBranchId,
            'booking_status' => $this->bookingStatus,
            'per_page' => $this->perPage,
        ], fn ($v) => $v !== null && $v !== '');

        $request = Request::create('/api/passengers/data', 'GET', $params);
        $request->query->set('page', $this->pagination['current_page']);

        $response = app(BookingController::class)->passengerData($request);
        $payload = $response->getData(true);

        $this->passengers = $payload['data'] ?? [];
        $this->summary = $payload['summary'] ?? [];
        $this->pagination = array_merge($this->pagination, $payload['pagination'] ?? []);
        $this->filterOptions = $payload['filterOptions'] ?? [];
    }

    public function updatedSearch(): void
    {
        $this->pagination['current_page'] = 1;
        $this->loadData();
    }

    public function updatedBookingDateFrom(): void
    {
        $this->pagination['current_page'] = 1;
        $this->loadData();
    }

    public function updatedBookingDateTo(): void
    {
        $this->pagination['current_page'] = 1;
        $this->loadData();
    }

    public function updatedActualFlightFrom(): void
    {
        $this->pagination['current_page'] = 1;
        $this->loadData();
    }

    public function updatedActualFlightTo(): void
    {
        $this->pagination['current_page'] = 1;
        $this->loadData();
    }

    public function updatedReturnDateFrom(): void
    {
        $this->pagination['current_page'] = 1;
        $this->loadData();
    }

    public function updatedReturnDateTo(): void
    {
        $this->pagination['current_page'] = 1;
        $this->loadData();
    }

    public function updatedFingerprintStatus(): void
    {
        $this->pagination['current_page'] = 1;
        $this->loadData();
    }

    public function updatedVisaStatus(): void
    {
        $this->pagination['current_page'] = 1;
        $this->loadData();
    }

    public function updatedTicketStatus(): void
    {
        $this->pagination['current_page'] = 1;
        $this->loadData();
    }

    public function updatedVisaAgentId(): void
    {
        $this->pagination['current_page'] = 1;
        $this->loadData();
    }

    public function updatedTicketAgentId(): void
    {
        $this->pagination['current_page'] = 1;
        $this->loadData();
    }

    public function updatedPassengerStatus(): void
    {
        $this->pagination['current_page'] = 1;
        $this->loadData();
    }

    public function updatedRouteDisplay(): void
    {
        $this->pagination['current_page'] = 1;
        $this->loadData();
    }

    public function updatedPackageId(): void
    {
        $this->pagination['current_page'] = 1;
        $this->loadData();
    }

    public function updatedStatusChangeAction(): void
    {
        $this->pagination['current_page'] = 1;
        $this->loadData();
    }

    public function updatedStatusChangeFrom(): void
    {
        $this->pagination['current_page'] = 1;
        $this->loadData();
    }

    public function updatedStatusChangeTo(): void
    {
        $this->pagination['current_page'] = 1;
        $this->loadData();
    }

    public function updatedPaymentWise(): void
    {
        $this->pagination['current_page'] = 1;
        $this->loadData();
    }

    public function updatedBookingBranchId(): void
    {
        $this->pagination['current_page'] = 1;
        $this->loadData();
    }

    public function updatedBookingStatus(): void
    {
        $this->pagination['current_page'] = 1;
        $this->loadData();
    }

    public function updatedPerPage(): void
    {
        $this->pagination['current_page'] = 1;
        $this->loadData();
    }

    public function resetFilters(): void
    {
        $this->search = null;
        $this->bookingDateFrom = null;
        $this->bookingDateTo = null;
        $this->actualFlightFrom = null;
        $this->actualFlightTo = null;
        $this->returnDateFrom = null;
        $this->returnDateTo = null;
        $this->fingerprintStatus = null;
        $this->visaStatus = null;
        $this->ticketStatus = null;
        $this->visaAgentId = null;
        $this->ticketAgentId = null;
        $this->passengerStatus = null;
        $this->routeDisplay = null;
        $this->packageId = null;
        $this->statusChangeAction = null;
        $this->statusChangeFrom = null;
        $this->statusChangeTo = null;
        $this->paymentWise = null;
        $this->bookingBranchId = null;
        $this->bookingStatus = null;
        $this->pagination['current_page'] = 1;
        $this->loadData();
    }

    public function goToPage(int $page): void
    {
        $this->pagination['current_page'] = $page;
        $this->loadData();
    }

    public function render()
    {
        $filterOptions = $this->filterOptions;
        $fingerprintLocations = FingerprintLocation::cases();
        $summary = $this->summary;
        $canEditVisa = $filterOptions['canEditVisa'] ?? false;
        $canEditFingerprint = $filterOptions['canEditFingerprint'] ?? false;
        $canEditTicket = $filterOptions['canEditTicket'] ?? false;

        return view('livewire.booking.passenger-index-table', compact(
            'filterOptions', 'fingerprintLocations', 'summary',
            'canEditVisa', 'canEditFingerprint', 'canEditTicket'
        ));
    }
}
