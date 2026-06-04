<?php

namespace App\Http\Controllers;

use App\Models\Fingerprint;
use App\Models\FingerprintDetail;
use App\Models\Branch;
use App\Models\District;
use App\Models\Office;
use App\Models\User;
use App\Queries\FingerprintReportQuery;
use App\Enums\FingerprintStatus;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class FingerprintReportController extends Controller
{
    const PER_PAGE = 25;

    public function index(Request $request): View
    {
        $branches = Branch::orderBy('name')->get(['id', 'name']);
        $districts = District::orderBy('name')->get(['id', 'name']);
        $offices = Office::orderBy('name')->get(['id', 'name']);
        $staffUsers = User::whereHas('roles', fn($q) => $q->where('name', 'Fingerprint Staff'))
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('reports.fingerprint.index', compact(
            'branches', 'districts', 'offices', 'staffUsers'
        ));
    }

    public function data(Request $request): JsonResponse
    {
        $query = (new FingerprintReportQuery($request))->getQuery();
        $officeId = $this->getOfficeFilter();
        if ($officeId) {
            $query->whereHas('booking', fn($q) => $q->where('office_id', $officeId));
        }
        $fingerprints = $query->paginate(self::PER_PAGE);

        $canViewFinancials = $this->canViewFinancials();
        $items = $this->mapReportData($fingerprints->items(), $canViewFinancials);

        $allQuery = (new FingerprintReportQuery($request))->getQuery();
        if ($officeId) {
            $allQuery->whereHas('booking', fn($q) => $q->where('office_id', $officeId));
        }
        $allFingerprints = $allQuery->get();
        $allItems = $this->mapReportData($allFingerprints->all(), $canViewFinancials);
        $summary = $this->computeTotals($allItems);

        return response()->json([
            'data' => $items,
            'summary' => $summary,
            'pagination' => [
                'current_page' => $fingerprints->currentPage(),
                'last_page'    => $fingerprints->lastPage(),
                'per_page'     => $fingerprints->perPage(),
                'total'        => $fingerprints->total(),
            ],
        ]);
    }

    public function print(Request $request): View
    {
        $query = (new FingerprintReportQuery($request))->getQuery();
        $officeId = $this->getOfficeFilter();
        if ($officeId) {
            $query->whereHas('booking', fn($q) => $q->where('office_id', $officeId));
        }
        $fingerprints = $query->get();

        $canViewFinancials = $this->canViewFinancials();
        $items = $this->mapReportData($fingerprints->all(), $canViewFinancials);

        $totals = $this->computeTotals($items);

        return view('reports.fingerprint.print', compact('items', 'totals', 'canViewFinancials'));
    }

    public function details(FingerprintDetail $fingerprintDetail): JsonResponse
    {
        $fingerprintDetail->load([
            'fingerprint.booking.customer',
            'fingerprint.booking.fingerprintCharge',
            'fingerprint.assignedStaff',
            'passenger',
            'rescheduledFingerprints' => fn($q) => $q->orderBy('occurrence'),
        ]);

        $fingerprint = $fingerprintDetail->fingerprint;
        $booking = $fingerprint->booking;

        $officeId = $this->getOfficeFilter();
        if ($officeId && $booking->office_id !== $officeId) {
            abort(403, 'Unauthorized access to booking data from another office.');
        }
        $passenger = $fingerprintDetail->passenger;

        $rescheduledBy = $this->resolveRescheduledBy($fingerprint, $booking);

        $rescheduleHistory = $fingerprintDetail->rescheduledFingerprints->map(function ($r) use ($fingerprint, $rescheduledBy) {
            $reasonLabel = $this->mapRescheduleReasonLabel($r->reason->value, $r->other_reason);
            return [
                'previous_date' => $fingerprint->deadline?->format('Y-m-d'),
                'new_date' => $r->next_date?->format('Y-m-d'),
                'rescheduled_by' => $rescheduledBy,
                'rescheduled_at' => $r->created_at?->format('Y-m-d h:i A'),
                'reason' => $reasonLabel,
                'remarks' => $r->remarks ?? '-',
            ];
        });

        $statusDisplay = $this->computeStatusDisplay($fingerprintDetail);

        $canViewFinancials = $this->canViewFinancials();

        return response()->json([
            'invoice_id' => $booking->invoice_id,
            'customer_name' => $booking->customer?->name ?? '-',
            'booking_date' => $booking->created_at?->format('Y-m-d'),
            'fingerprint_deadline' => $fingerprint->deadline?->format('Y-m-d'),
            'fingerprint_charge' => $canViewFinancials ? (float)($booking->fingerprintCharge?->fingerprint_charge ?? 0) : null,
            'fingerprint_cost' => (float)($fingerprint->cost ?? 0),
            'profit_loss' => $canViewFinancials ? ((float)($booking->fingerprintCharge?->fingerprint_charge ?? 0) - (float)($fingerprint->cost ?? 0)) : null,
            'passenger' => [
                'name' => trim(($passenger->first_name ?? '') . ' ' . ($passenger->last_name ?? '')),
                'passport_no' => $passenger->passport_no ?? '-',
                'mobile' => $passenger->mobile_no ?? '-',
                'address' => $passenger->address ?? '-',
            ],
            'customer_mobile' => $booking->customer?->mobile_no ?? '-',
            'completed_date' => $fingerprintDetail->status === FingerprintStatus::APPROVED
                ? $fingerprintDetail->updated_at?->format('Y-m-d')
                : '-',
            'fingerprint_status' => $fingerprintDetail->status?->value ?? 'none',
            'status_display' => $statusDisplay,
            'required_flight' => $passenger->flight_date_display ?? '-',
            'actual_flight' => $passenger->actual_flight_date?->format('Y-m-d') ?? '-',
            'reschedule_history' => $rescheduleHistory,
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
            if (!$booking) continue;

            $passengers = $booking->passengers;
            $fingerprintCharge = $booking->fingerprintCharge?->fingerprint_charge ?? 0;
            $cost = $fingerprint->cost ?? 0;
            $profitLoss = $fingerprintCharge - $cost;

            $allDetails = $fingerprint->fingerprintDetails;
            $allApproved = $allDetails->isNotEmpty() && $allDetails->every(
                fn($d) => $d->status === FingerprintStatus::APPROVED
            );

            $isNewInvoice = $booking->invoice_id !== $lastInvoiceId;
            if ($isNewInvoice) {
                $invoiceIndex++;
                $lastInvoiceId = $booking->invoice_id;
            }

            foreach ($passengers as $pIdx => $passenger) {
                $detail = $allDetails->firstWhere('passenger_id', $passenger->id);
                $statusDisplay = $this->computeStatusDisplay($detail);

                $remarks = $detail?->rescheduledFingerprints
                    ? $detail->rescheduledFingerprints
                        ->sortByDesc('created_at')
                        ->first()?->remarks
                    : null;

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
                    'passenger_name' => trim(($passenger->first_name ?? '') . ' ' . ($passenger->last_name ?? '')),
                    'passport_no' => $passenger->passport_no ?? '-',
                    'passenger_mobile' => $passenger->mobile_no ?? '-',
                    'fingerprint_charge' => $canViewFinancials ? (float)$fingerprintCharge : null,
                    'fingerprint_cost' => (float)$cost,
                    'fingerprint_deadline' => $fingerprint->deadline?->format('Y-m-d'),
                    'completed_date' => $detail && $detail->status === FingerprintStatus::APPROVED
                        ? $detail->updated_at?->format('Y-m-d')
                        : '-',
                    'status_display' => $statusDisplay,
                    'required_flight' => $passenger->flight_date_display ?? '-',
                    'actual_flight' => $passenger->actual_flight_date?->format('Y-m-d') ?? '-',
                    'remarks' => $remarks ?? '-',
                    'profit_loss' => $canViewFinancials ? (float)$profitLoss : null,
                ];
            }
        }

        return $result;
    }

    protected function computeTotals(array $items): array
    {
        $uniqueInvoices = collect($items)->pluck('invoice_id')->unique();
        $totalPAX = count($items);

        $firstRows = collect($items)->filter(fn($r) => $r['_isFirstPassenger']);

        return [
            'total_invoices' => $uniqueInvoices->count(),
            'total_pax' => $totalPAX,
            'total_fingerprint_charge' => $firstRows->sum('fingerprint_charge'),
            'total_fingerprint_cost' => $firstRows->sum('fingerprint_cost'),
            'total_profit_loss' => $firstRows->sum('profit_loss'),
        ];
    }

    protected function computeStatusDisplay(?FingerprintDetail $detail): string
    {
        if (!$detail) return 'None';

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
        if (!$fingerprint) return false;

        $allDetails = $fingerprint->fingerprintDetails;
        return $allDetails->isNotEmpty() && $allDetails->every(
            fn($d) => $d->status === FingerprintStatus::APPROVED
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

    protected function resolveRescheduledBy($fingerprint, $booking): string
    {
        if ($fingerprint->assignedStaff) {
            return $fingerprint->assignedStaff->name;
        }

        $fingerprintAdmin = User::whereHas('roles', fn($q) => $q->where('name', 'Fingerprint Admin'))
            ->where('office_id', $booking->office_id)
            ->first();

        return $fingerprintAdmin?->name ?? '-';
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

    protected function getOfficeFilter(): ?int
    {
        $user = auth()->user();
        if ($user->office_id && !$user->hasRole('Super Admin') && !$user->hasRole('Co Admin')) {
            return $user->office_id;
        }
        return null;
    }
}
