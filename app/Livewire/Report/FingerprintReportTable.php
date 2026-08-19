<?php

namespace App\Livewire\Report;

use App\Enums\FingerprintStatus;
use App\Models\Branch;
use App\Models\District;
use App\Models\FingerprintDetail;
use App\Models\User;
use App\Queries\FingerprintReportQuery;
use Illuminate\Http\Request;
use Livewire\Component;

class FingerprintReportTable extends Component
{
    public string $search = '';

    public ?string $bookingDateFrom = null;

    public ?string $bookingDateTo = null;

    public ?string $completionDateFrom = null;

    public ?string $completionDateTo = null;

    public ?string $fingerprintLocation = null;

    public ?string $status = null;

    public ?string $assignedStaffId = null;

    public ?string $branchId = null;

    public ?string $districtId = null;

    public ?string $fingerprintBranchId = null;

    public int $perPage = 25;

    public int $page = 1;

    public $branches = [];

    public $districts = [];

    public $fingerprintBranches = [];

    public $staffUsers = [];

    public $allData = [];

    public array $summary = [];

    public int $totalRecords = 0;

    public int $lastPage = 1;

    public ?string $userBranchId = null;

    public function boot()
    {
        $this->userBranchId = auth()->user()?->branch_id;
        $this->branches = Branch::orderBy('name')->get(['id', 'name']);
        $this->districts = District::orderBy('name')->get(['id', 'name']);
        $this->fingerprintBranches = Branch::where('fingerprint_operation', true)
            ->orderBy('name')
            ->get(['id', 'name']);
        $this->staffUsers = User::whereHas('roles', fn ($q) => $q->where('name', 'Fingerprint Staff'))
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    protected function buildQueryParams(): array
    {
        return array_filter([
            'search' => $this->search,
            'booking_date_from' => $this->bookingDateFrom,
            'booking_date_to' => $this->bookingDateTo,
            'completion_date_from' => $this->completionDateFrom,
            'completion_date_to' => $this->completionDateTo,
            'fingerprint_location' => $this->fingerprintLocation,
            'status' => $this->status,
            'assigned_staff_id' => $this->assignedStaffId,
            'branch_id' => $this->branchId,
            'district_id' => $this->districtId,
            'fingerprint_branch_id' => $this->fingerprintBranchId,
            'page' => $this->page,
        ], fn ($v) => $v !== '' && $v !== null);
    }

    public function render()
    {
        $queryParams = $this->buildQueryParams();
        $request = Request::create('/', 'GET', $queryParams);

        $query = (new FingerprintReportQuery($request))->getQuery();

        $branchFilter = $this->getFingerprintBranchFilter();
        if ($branchFilter) {
            $query->whereHas('booking', fn ($q) => $q->where('fingerprint_branch_id', $branchFilter));
        }

        $fingerprints = $query->paginate($this->perPage, 'page', $this->page);

        $canViewFinancials = $this->canViewFinancials();
        $items = $this->mapReportData($fingerprints->items(), $canViewFinancials);

        $totals = $this->computeTotalsFromQuery($request, $branchFilter);

        return view('livewire.report.fingerprint-report-table', [
            'items' => $items,
            'summary' => $totals,
            'canViewFinancials' => $canViewFinancials,
            'branches' => $this->branches,
            'districts' => $this->districts,
            'fingerprintBranches' => $this->fingerprintBranches,
            'staffUsers' => $this->staffUsers,
            'userBranchId' => $this->userBranchId,
            'currentPage' => $fingerprints->currentPage(),
            'lastPage' => $fingerprints->lastPage(),
            'totalRecords' => $fingerprints->total(),
            'perPage' => $fingerprints->perPage(),
        ]);
    }

    protected function mapReportData(array $fingerprints, bool $canViewFinancials): array
    {
        $invoiceIndex = -1;
        $lastInvoiceId = null;
        $result = [];

        $fingerprintCollection = collect($fingerprints);

        foreach ($fingerprintCollection as $fingerprint) {
            $booking = $fingerprint->booking;
            if (! $booking) {
                continue;
            }

            $passengers = $booking->passengers;
            $fingerprintCharge = $booking->fingerprintCharge?->fingerprint_charge ?? 0;
            $cost = $fingerprint->cost ?? 0;
            $profitLoss = $fingerprintCharge - $cost;

            $allDetails = $fingerprint->fingerprintDetails;

            $isNewInvoice = $booking->invoice_id !== $lastInvoiceId;
            if ($isNewInvoice) {
                $invoiceIndex++;
                $lastInvoiceId = $booking->invoice_id;
            }

            foreach ($passengers as $pIdx => $passenger) {
                $detail = $allDetails->firstWhere('passenger_id', $passenger->id);
                $statusDisplay = $this->computeStatusDisplay($detail);
                $remarks = null;
                if ($detail?->rescheduledFingerprints) {
                    $remarks = $detail->rescheduledFingerprints
                        ->sortByDesc('created_at')
                        ->first()?->remarks;
                }
                $rate = $booking?->currencyRate?->rate ?? 0;
                $result[] = [
                    '_isFirstPassenger' => $pIdx === 0,
                    '_isLastPassenger' => $pIdx === $passengers->count() - 1,
                    '_isOddInvoice' => $invoiceIndex % 2 === 1,
                    'fingerprint_id' => $fingerprint->id,
                    'fingerprint_detail_id' => $detail?->id,
                    'invoice_id' => $booking->invoice_id,
                    'booking_date' => $booking->created_at?->format('Y-m-d'),
                    'customer_name' => $booking->customer?->name ?? '-',
                    'customer_mobile' => $booking->customer?->mobile_no ?? '-',
                    'passenger_name' => trim(($passenger->first_name ?? '').' '.($passenger->last_name ?? '')),
                    'passport_no' => $passenger->passport_no ?? '-',
                    'passenger_mobile' => $passenger->mobile_no ?? '-',
                    'district' => $booking->district?->name ?? '-',
                    'fingerprint_charge' => $canViewFinancials ? (float) $fingerprintCharge : null,
                    'fingerprint_cost' => (float) $cost,
                    'fingerprint_deadline' => $fingerprint->deadline?->format('Y-m-d'),
                    'completed_date' => $detail && $detail->status === FingerprintStatus::APPROVED
                        ? $detail->updated_at?->format('Y-m-d')
                        : '-',
                    'status_display' => $statusDisplay,
                    'required_flight' => $passenger->flight_date_display ?? '-',
                    'actual_flight' => $passenger->actual_flight_date?->format('Y-m-d') ?? '-',
                    'remarks' => $remarks ?? '-',
                    'profit' => $canViewFinancials ? max(0, (float) $profitLoss) : null,
                    'loss' => $canViewFinancials ? abs(min(0, (float) $profitLoss)) : null,
                    'rate' => $rate,
                ];
            }
        }

        return $result;
    }

    protected function computeTotalsFromQuery(Request $request, ?int $branchId): array
    {
        $query = (new FingerprintReportQuery($request))->getBaseQueryForAggregates();
        if ($branchId) {
            $query->whereHas('booking', fn ($q) => $q->where('fingerprint_branch_id', $branchId));
        }
        $query->getQuery()->orders = [];

        $canViewFinancials = $this->canViewFinancials();
        $base = $query->join('bookings', 'fingerprints.booking_id', '=', 'bookings.id');

        $paxAndInvoices = (clone $base)
            ->selectRaw('COUNT(DISTINCT fingerprints.id) as total_pax')
            ->selectRaw('COUNT(DISTINCT bookings.invoice_id) as total_invoices')
            ->selectRaw('SUM(COALESCE(bookings.pax_qty, 0)) as total_pax_qty')
            ->first();

        $financials = $canViewFinancials
            ? (clone $base)
                ->leftJoin('fingerprint_charges', 'bookings.fingerprint_charge_id', '=', 'fingerprint_charges.id')
                ->selectRaw('SUM(COALESCE(fingerprint_charges.fingerprint_charge, 0)) as total_fingerprint_charge')
                ->selectRaw('SUM(COALESCE(fingerprints.cost, 0)) as total_fingerprint_cost')
                ->first()
            : null;

        $totalFingerprintCharge = $canViewFinancials ? (float) ($financials->total_fingerprint_charge ?? 0) : 0;
        $totalFingerprintCost = $canViewFinancials ? (float) ($financials->total_fingerprint_cost ?? 0) : 0;
        $totalProfit = max(0, $totalFingerprintCharge - $totalFingerprintCost);
        $totalLoss = abs(min(0, $totalFingerprintCharge - $totalFingerprintCost));

        return [
            'total_invoices' => (int) ($paxAndInvoices->total_invoices ?? 0),
            'total_pax' => (int) ($paxAndInvoices->total_pax_qty ?? 0),
            'total_fingerprint_charge' => $totalFingerprintCharge,
            'total_fingerprint_cost' => $totalFingerprintCost,
            'total_profit' => $totalProfit,
            'total_loss' => $totalLoss,
            'total_profit_loss' => $totalProfit - $totalLoss,
        ];
    }

    protected function computeStatusDisplay(?FingerprintDetail $detail): string
    {
        if (! $detail) {
            return 'None';
        }

        $status = $detail->status;
        $reschedules = $detail->rescheduledFingerprints;

        return match ($status) {
            FingerprintStatus::NONE => 'None',
            FingerprintStatus::APPROVED => $this->isAllApproved($detail) ? 'Done' : 'Partially Approved',
            FingerprintStatus::PROCESSING => $reschedules->isNotEmpty()
                ? $this->mapRescheduleReasonLabel(
                    $reschedules->sortByDesc('created_at')->first()->reason?->value,
                    $reschedules->sortByDesc('created_at')->first()->other_reason
                )
                : 'Processing',
            FingerprintStatus::CANCELLED => 'Hold by BMT',
            default => 'None',
        };
    }

    protected function isAllApproved(FingerprintDetail $detail): bool
    {
        $fingerprint = $detail->fingerprint;
        if (! $fingerprint) {
            return false;
        }

        $allDetails = $fingerprint->fingerprintDetails;

        return $allDetails->isNotEmpty() && $allDetails->every(
            fn ($d) => $d->status === FingerprintStatus::APPROVED
        );
    }

    protected function mapRescheduleReasonLabel(?string $reason, ?string $otherReason): string
    {
        return match ($reason) {
            'rescheduled_by_client' => 'Rescheduled by Client',
            'rescheduled_by_bmt' => 'Rescheduled by BMT',
            'nfc_problem' => 'NFC Problem',
            'others' => $otherReason ?? 'Others',
            default => $reason ?? 'Processing',
        };
    }

    protected function canViewFinancials(): bool
    {
        $user = auth()->user();

        return $user && (
            $user->hasRole('Super Admin') ||
            $user->hasRole('Co Admin') ||
            $user->hasRole('Auditor')
        );
    }

    protected function getFingerprintBranchFilter(): ?int
    {
        $user = auth()->user();
        if ($user->branch?->fingerprint_operation && ! $user->hasRole('Super Admin') && ! $user->hasRole('Co Admin')) {
            return $user->branch_id;
        }

        return null;
    }
}
