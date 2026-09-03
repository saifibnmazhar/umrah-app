<?php

namespace App\Queries;

use App\Models\Fingerprint;
use Illuminate\Http\Request;

class FingerprintReportQuery
{
    protected $query;

    public function __construct(Request $request)
    {
        $this->query = Fingerprint::with([
            'booking.customer',
            'booking.bookingBranch',
            'booking.district',
            'booking.fingerprintBranch',
            'booking.fingerprintCharge',
            'booking.passengers',
            'booking.currencyRate',
            'fingerprintDetails.passenger',
            'fingerprintDetails.rescheduledFingerprints',
            'assignedStaff',
        ])->orderBy('created_at', 'desc');

        $this->applyFilters($request);
    }

    public function getQuery()
    {
        return $this->query;
    }

    /**
     * Return a clone of the query with eager-load relations removed,
     * suitable for aggregate (count/sum/groupBy) queries without
     * hydrating full models or re-running per-row lazy loads.
     */
    public function getBaseQueryForAggregates()
    {
        return clone $this->query;
    }

    protected function applyFilters(Request $request): void
    {
        $this->applySearch($request)
            ->applyBookingDateRange($request)
            ->applyCompletionDateRange($request)
            ->applyStatus($request)
            ->applyAssignedStaff($request)
            ->applyLocation($request)
            ->applyBookingBranch($request)
            ->applyDistrict($request)
            ->applyFingerprintBranch($request);
    }

    protected function applySearch(Request $request): static
    {
        if ($search = $request->filled('search') ? $request->search : null) {
            $this->query->where(function ($q) use ($search) {
                $q->whereHas('booking', function ($q) use ($search) {
                    $q->where('invoice_id', 'LIKE', "%{$search}%")
                        ->orWhereHas('customer', function ($q) use ($search) {
                            $q->where('name', 'LIKE', "%{$search}%")
                                ->orWhere('mobile_no', 'LIKE', "%{$search}%");
                        });
                })->orWhereHas('fingerprintDetails.passenger', function ($q) use ($search) {
                    $q->where('first_name', 'LIKE', "%{$search}%")
                        ->orWhere('last_name', 'LIKE', "%{$search}%")
                        ->orWhere('mobile_no', 'LIKE', "%{$search}%");
                });
            });
        }

        return $this;
    }

    protected function applyBookingDateRange(Request $request): static
    {
        if ($request->filled('booking_date_from')) {
            $this->query->whereHas('booking', function ($q) use ($request) {
                $q->whereDate('created_at', '>=', $request->booking_date_from);
            });
        }
        if ($request->filled('booking_date_to')) {
            $this->query->whereHas('booking', function ($q) use ($request) {
                $q->whereDate('created_at', '<=', $request->booking_date_to);
            });
        }

        return $this;
    }

    protected function applyCompletionDateRange(Request $request): static
    {
        if ($request->filled('completion_date_from') || $request->filled('completion_date_to')) {
            $this->query->whereHas('fingerprintDetails', function ($q) use ($request) {
                if ($request->filled('completion_date_from')) {
                    $q->whereDate('updated_at', '>=', $request->completion_date_from);
                }
                if ($request->filled('completion_date_to')) {
                    $q->whereDate('updated_at', '<=', $request->completion_date_to);
                }
            });
        }

        return $this;
    }

    protected function applyStatus(Request $request): static
    {
        if ($status = $request->filled('status') ? $request->status : null) {
            $this->query->whereHas('fingerprintDetails', function ($q) use ($status) {
                $q->where('status', $status);
            });
        }

        return $this;
    }

    protected function applyAssignedStaff(Request $request): static
    {
        if ($staffId = $request->filled('assigned_staff_id') ? $request->assigned_staff_id : null) {
            $this->query->where('assigned_staff_id', $staffId);
        }

        return $this;
    }

    protected function applyLocation(Request $request): static
    {
        if ($location = $request->filled('fingerprint_location') ? $request->fingerprint_location : null) {
            $this->query->whereHas('booking', function ($q) use ($location) {
                $q->where('fingerprint_location', $location);
            });
        }

        return $this;
    }

    protected function applyBookingBranch(Request $request): static
    {
        if ($branchId = $request->filled('booking_branch_id') ? $request->booking_branch_id : null) {
            $this->query->whereHas('booking', function ($q) use ($branchId) {
                $q->where('booking_branch_id', $branchId);
            });
        }

        return $this;
    }

    protected function applyDistrict(Request $request): static
    {
        if ($districtId = $request->filled('district_id') ? $request->district_id : null) {
            $this->query->whereHas('booking', function ($q) use ($districtId) {
                $q->where('district_id', $districtId);
            });
        }

        return $this;
    }

    protected function applyFingerprintBranch(Request $request): static
    {
        if ($branchId = $request->filled('fingerprint_branch_id') ? $request->fingerprint_branch_id : null) {
            $this->query->whereHas('booking', function ($q) use ($branchId) {
                $q->where('fingerprint_branch_id', $branchId);
            });
        }

        return $this;
    }

    public function applyFingerprintBranchFilter(?int $branchId): static
    {
        if ($branchId) {
            $this->query->whereHas('booking', function ($q) use ($branchId) {
                $q->where('fingerprint_branch_id', $branchId);
            });
        }

        return $this;
    }
}
