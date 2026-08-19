<?php

namespace App\Livewire\Booking;

use App\Enums\FingerprintLocation;
use App\Http\Controllers\BookingController;
use App\Models\Branch;
use Illuminate\Http\Request;
use Livewire\Component;

class BookingIndexTable extends Component
{
    public ?string $search = null;

    public ?string $bookingDateFrom = null;

    public ?string $bookingDateTo = null;

    public ?string $fingerprintLocation = null;

    public ?string $bookingStatus = null;

    public ?string $branchId = null;

    public int $perPage = 10;

    public int $page = 1;

    public $data = [];

    public $summary = [
        'totalBookingCount' => 0,
        'totalBookingPassengerCount' => 0,
    ];

    public $pagination = [
        'current_page' => 1,
        'last_page' => 1,
        'per_page' => 10,
        'total' => 0,
    ];

    public $branches = [];

    public $fingerprintLocations = [];

    public bool $loading = false;

    protected function loadData(): void
    {
        $this->loading = true;

        $params = array_filter([
            'search' => $this->search,
            'booking_date_from' => $this->bookingDateFrom,
            'booking_date_to' => $this->bookingDateTo,
            'fingerprint_location' => $this->fingerprintLocation,
            'booking_status' => $this->bookingStatus,
            'booking_branch_id' => $this->branchId,
            'per_page' => $this->perPage,
        ], fn ($v) => $v !== null && $v !== '');

        $params['page'] = $this->pagination['current_page'];

        $request = Request::create('/api/bookings/data', 'GET', $params);
        $response = app(BookingController::class)->data($request);
        $payload = $response->getData(true);

        $this->data = $payload['data'] ?? [];
        $this->summary = $payload['summary'] ?? $this->summary;
        $this->pagination = $payload['pagination'] ?? $this->pagination;

        $this->loading = false;
    }

    public function boot()
    {
        $this->branches = ! auth()->user()->branch_id
            ? Branch::orderBy('name')->get(['id', 'name'])->keyBy('id')->toArray()
            : [];
        $this->fingerprintLocations = FingerprintLocation::cases();

        $this->loadData();
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

    public function updatedFingerprintLocation(): void
    {
        $this->pagination['current_page'] = 1;
        $this->loadData();
    }

    public function updatedBookingStatus(): void
    {
        $this->pagination['current_page'] = 1;
        $this->loadData();
    }

    public function updatedBranchId(): void
    {
        $this->pagination['current_page'] = 1;
        $this->loadData();
    }

    public function resetFilters(): void
    {
        $this->search = null;
        $this->bookingDateFrom = null;
        $this->bookingDateTo = null;
        $this->fingerprintLocation = null;
        $this->bookingStatus = null;
        $this->branchId = null;
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
        return view('livewire.booking.booking-index-table', [
            'branches' => $this->branches,
            'fingerprintLocations' => $this->fingerprintLocations,
            'bookings' => $this->data,
            'summary' => $this->summary,
            'pagination' => $this->pagination,
            'loading' => $this->loading,
        ]);
    }
}
