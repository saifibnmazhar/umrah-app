<?php

namespace App\Livewire\Booking;

use App\Enums\FingerprintLocation;
use App\Livewire\IndexDataTable;

class BookingIndexTable extends IndexDataTable
{
    public ?string $bookingDateFrom = null;

    public ?string $bookingDateTo = null;

    public ?string $fingerprintLocation = null;

    public ?string $bookingStatus = null;

    public ?string $branchId = null;

    public array $fingerprintLocations = [];

    public $data = [];

    public mixed $perPage = null;

    protected function endpoint(): string
    {
        return '/api/bookings/data';
    }

    protected function controllerMethod(): string
    {
        return 'data';
    }

    protected function filterParams(): array
    {
        return [
            'booking_date_from' => $this->bookingDateFrom,
            'booking_date_to' => $this->bookingDateTo,
            'fingerprint_location' => $this->fingerprintLocation,
            'booking_status' => $this->bookingStatus,
            'booking_branch_id' => $this->branchId,
        ];
    }

    protected function dataTableProperty(): string
    {
        return 'data';
    }

    public function boot()
    {
        $this->perPage = 10;
        $this->branches = $this->branchOptions();
        $this->fingerprintLocations = FingerprintLocation::cases();
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

    public function render()
    {
        return view('livewire.booking.booking-index-table', [
            'branches' => $this->branches,
            'fingerprintLocations' => FingerprintLocation::cases(),
            'bookings' => $this->data,
            'summary' => $this->summary,
            'pagination' => $this->pagination,
            'loading' => $this->loading,
        ]);
    }
}
