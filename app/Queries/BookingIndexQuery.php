<?php

namespace App\Queries;

use App\Models\Booking;
use Illuminate\Http\Request;

/**
 * Encapsulates the Booking query + filters used by:
 *  - BookingController::index()      (initial page render)
 *  - BookingController::data()       (Livewire BookingIndexTable data endpoint)
 *
 * Replaces the duplicated inline query-builder chains that previously existed
 * in both methods. Follows the pattern established by FingerprintReportQuery.
 */
class BookingIndexQuery
{
    protected $query;

    protected Request $request;

    protected ?string $search;

    protected ?string $bookingDateFrom;

    protected ?string $bookingDateTo;

    protected ?string $fingerprintLocation;

    protected ?string $bookingStatus;

    protected ?int $selectedBranchId;

    protected int $perPage;

    public function __construct(Request $request)
    {
        $this->request = $request;
        $this->search = $request->filled('search') ? $request->input('search') : null;
        $this->bookingDateFrom = $request->filled('booking_date_from') ? $request->input('booking_date_from') : null;
        $this->bookingDateTo = $request->filled('booking_date_to') ? $request->input('booking_date_to') : null;
        $this->fingerprintLocation = $request->filled('fingerprint_location') ? $request->input('fingerprint_location') : null;
        $this->bookingStatus = $request->filled('booking_status') ? $request->input('booking_status') : null;
        $this->perPage = (int) ($request->filled('per_page') ? $request->input('per_page') : 10);

        $user = $request->user();
        $userBranchId = $user?->branch_id;

        $this->selectedBranchId = ! $userBranchId && $request->filled('booking_branch_id')
            ? (int) $request->input('booking_branch_id')
            : null;

        $this->query = Booking::with([
            'customer',
            'passengers',
            'fingerprintBranch',
            'bookingBranch',
            'invoice',
            'district',
            'package',
            'currencyRate',
        ]);

        $this->applyScopes($userBranchId);
    }

    /**
     * Apply the common WHERE scopes shared by all callers.
     * Extracted from the duplicated `index()` and `data()` methods.
     */
    protected function applyScopes($userBranchId): static
    {
        if ($userBranchId) {
            $this->query->where(function ($q) use ($userBranchId) {
                $q->where('booking_branch_id', $userBranchId)
                    ->orWhere('fingerprint_branch_id', $userBranchId);
            });
        }

        $this->query
            ->when($this->selectedBranchId, fn ($q) => $q->where('booking_branch_id', $this->selectedBranchId))
            ->when($this->search, function ($q) {
                $search = $this->search;
                $q->where(function ($query) use ($search) {
                    $query->where('invoice_id', 'like', "%{$search}%")
                        ->orWhereHas('customer', fn ($q) => $q->where('mobile_no', 'like', "%{$search}%"))
                        ->orWhereHas('passengers', fn ($q) => $q->where('passport_no', 'like', "%{$search}%"));
                });
            })
            ->when($this->bookingDateFrom, fn ($q) => $q->whereDate('created_at', '>=', $this->bookingDateFrom))
            ->when($this->bookingDateTo, fn ($q) => $q->whereDate('created_at', '<=', $this->bookingDateTo))
            ->when($this->fingerprintLocation, fn ($q) => $q->where('fingerprint_location', $this->fingerprintLocation))
            ->when($this->bookingStatus, function ($q) {
                $status = $this->bookingStatus;
                if ($status === 'active') {
                    $q->where('is_cancelled', false);
                } elseif ($status === 'cancellation_processing') {
                    $q->where('is_cancelled', true)
                        ->whereHas('cancelledBooking', fn ($q) => $q->where('status', 'cancellation processing'));
                } elseif ($status === 'cancelled') {
                    $q->where('is_cancelled', true)
                        ->where(function ($q) {
                            $q->whereDoesntHave('cancelledBooking')
                                ->orWhereHas('cancelledBooking', fn ($q) => $q->where('status', 'cancelled'));
                        });
                }
            })
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

    public function getSummary(): array
    {
        $q = clone $this->query;
        $q->getQuery()->orders = [];

        return [
            'totalBookingCount' => $q->count(),
            'totalBookingPassengerCount' => (clone $q)->sum('pax_qty'),
        ];
    }
}
