<?php

namespace App\Http\Controllers;

use App\Enums\CancelledBookingStatus;
use App\Models\Branch;
use App\Models\CancelledBooking;
use App\Models\CancelledPassenger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CancelledRecordController extends Controller
{
    public function bookingIndex(Request $request)
    {
        $query = $this->buildBookingQuery($request);

        $cancelledBookings = $query->latest()->paginate(20)->withQueryString();

        return view('cancelled-bookings.index', [
            'tab' => 'bookings',
            'cancelledBookings' => $cancelledBookings,
            'cancelledPassengers' => collect(),
            'branches' => Branch::select('id', 'name')->orderBy('name')->get(),
        ]);
    }

    public function bookingIndexData(Request $request): JsonResponse
    {
        $query = $this->buildBookingQuery($request);

        $cancelledBookings = $query->latest()->paginate(20);

        $items = collect($cancelledBookings->items())->map(function (CancelledBooking $cb) {
            return [
                'id' => $cb->id,
                'invoice_id' => $cb->booking?->invoice_id ?? '—',
                'customer' => $cb->booking?->customer?->name ?? 'N/A',
                'customer_mobile' => $cb->booking?->customer?->mobile_no ?? 'N/A',
                'pax_qty' => $cb->booking?->pax_qty ?? '—',
                'cancellation_branch' => $cb->cancellationBranch?->name ?? '—',
                'total_paid' => (float) $cb->total_paid,
                'service_charge_deduction' => $cb->service_charge_deduction !== null ? (float) $cb->service_charge_deduction : null,
                'refund_amount' => (float) $cb->refund_amount,
                'cancel_date' => $cb->created_at->format('Y-m-d'),
                'status' => $cb->status->value ?? '—',
                'cancelled_by' => $cb->user?->name ?? '—',
                'show_route' => route('cancelled-bookings.show', $cb->id),
                'print_route' => route('cancelled-bookings.print', $cb->id),
            ];
        });

        return $this->paginatedResponse($cancelledBookings, $items);
    }

    public function passengerIndexData(Request $request): JsonResponse
    {
        $query = $this->buildPassengerQuery($request);

        $cancelledPassengers = $query->latest()->paginate(20);

        $items = collect($cancelledPassengers->items())->map(function (CancelledPassenger $cp) {
            return [
                'id' => $cp->id,
                'invoice_id' => $cp->booking?->invoice_id ?? '—',
                'customer' => $cp->booking?->customer?->name ?? 'N/A',
                'passenger' => trim(($cp->passenger?->first_name ?? '').' '.($cp->passenger?->last_name ?? '')) ?: '—',
                'cancellation_branch' => $cp->cancellationBranch?->name ?? '—',
                'package_value' => (float) $cp->package_value,
                'refundable_amount' => (float) $cp->refundable_amount,
                'balance_adjusted_amount' => (float) $cp->balance_adjusted_amount,
                'refund_amount' => (float) $cp->refund_amount,
                'status' => $cp->status->value ?? '—',
                'initiated_by' => $cp->user?->name ?? '—',
                'date' => $cp->created_at->format('Y-m-d'),
                'show_route' => route('cancelled-passengers.show', $cp->id),
                'print_route' => route('cancelled-passengers.print', $cp->id),
            ];
        });

        return $this->paginatedResponse($cancelledPassengers, $items);
    }

    public function bookingShow(CancelledBooking $cancelledBooking)
    {
        $this->ensureBranchAccess($cancelledBooking);

        $cancelledBooking->load([
            'booking.customer',
            'booking.passengers',
            'booking.invoice',
            'booking.bookingBranch',
            'booking.fingerprintBranch',
            'booking.fingerprint',
            'booking.user',
            'cancellationBranch',
            'user',
            'confirmedBy',
            'deductionPayment',
            'deductionVoucher',
            'refundPayment',
            'refundVoucher',
        ]);

        return view('cancelled-bookings.show', compact('cancelledBooking'));
    }

    public function bookingPrint(CancelledBooking $cancelledBooking)
    {
        $this->ensureBranchAccess($cancelledBooking);

        $cancelledBooking->load([
            'booking.customer',
            'booking.passengers',
            'booking.bookingBranch',
            'booking.user',
            'cancellationBranch',
            'user',
            'confirmedBy',
            'refundPayment.voucher',
            'refundVoucher',
            'deductionPayment.voucher',
            'deductionVoucher',
        ]);

        return view('cancelled-bookings.print-voucher', compact('cancelledBooking'));
    }

    public function passengerIndex(Request $request)
    {
        $query = $this->buildPassengerQuery($request);

        $cancelledPassengers = $query->latest()->paginate(20)->withQueryString();

        return view('cancelled-bookings.index', [
            'tab' => 'passengers',
            'cancelledBookings' => collect(),
            'cancelledPassengers' => $cancelledPassengers,
            'branches' => Branch::select('id', 'name')->orderBy('name')->get(),
        ]);
    }

    public function passengerShow(CancelledPassenger $cancelledPassenger)
    {
        $this->ensureBranchAccess($cancelledPassenger);

        $cancelledPassenger->load([
            'booking.customer',
            'booking.invoice',
            'booking.bookingBranch',
            'passenger',
            'cancellationBranch',
            'user',
            'confirmedBy',
            'deductionPayment',
            'deductionVoucher',
            'refundPayment',
            'refundVoucher',
            'adjustmentPayment',
            'adjustmentVoucher',
        ]);

        return view('cancelled-passengers.show', compact('cancelledPassenger'));
    }

    public function passengerPrint(CancelledPassenger $cancelledPassenger)
    {
        $this->ensureBranchAccess($cancelledPassenger);

        $cancelledPassenger->load([
            'booking.customer',
            'booking.invoice',
            'booking.bookingBranch',
            'passenger',
            'cancellationBranch',
            'user',
            'confirmedBy',
            'refundPayment.voucher',
            'refundVoucher',
            'deductionPayment.voucher',
            'deductionVoucher',
            'adjustmentPayment.voucher',
            'adjustmentVoucher',
        ]);

        return view('cancelled-passengers.print-voucher', compact('cancelledPassenger'));
    }

    private function applyBranchFilter($query, string $column): void
    {
        $this->ensureFingerprintAdminHasBranch();

        if (auth()->user()->branch_id) {
            $query->where($column, auth()->user()->branch_id);
        }
    }

    private function ensureBranchAccess($record): void
    {
        $this->ensureFingerprintAdminHasBranch();

        if (auth()->user()->branch_id
            && auth()->user()->branch_id !== $record->cancellation_branch_id) {
            abort(403);
        }
    }

    private function ensureFingerprintAdminHasBranch(): void
    {
        if (auth()->user()->roles->pluck('name')->intersect(['Fingerprint Admin'])->isNotEmpty()
            && ! auth()->user()->branch_id) {
            abort(403);
        }
    }

    private function buildBookingQuery(Request $request)
    {
        $query = CancelledBooking::with([
            'booking.customer',
            'booking.bookingBranch',
            'cancellationBranch',
            'user',
            'confirmedBy',
            'refundPayment',
            'refundVoucher',
            'deductionPayment',
            'deductionVoucher',
        ])->where('status', CancelledBookingStatus::CANCELLED);

        $this->applyBranchFilter($query, 'cancellation_branch_id');

        if ($search = $request->get('search')) {
            $query->whereHas('booking', function ($q) use ($search) {
                $q->where('invoice_id', 'like', "%{$search}%")
                    ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$search}%"));
            });
        }

        if ($request->filled('branch_id')) {
            $query->where('cancellation_branch_id', $request->branch_id);
        }

        return $query;
    }

    private function buildPassengerQuery(Request $request)
    {
        $query = CancelledPassenger::with([
            'booking.customer',
            'booking.bookingBranch',
            'passenger',
            'cancellationBranch',
            'user',
            'confirmedBy',
            'refundPayment',
            'refundVoucher',
            'adjustmentPayment',
            'adjustmentVoucher',
            'deductionPayment',
            'deductionVoucher',
        ])->where('status', CancelledBookingStatus::CANCELLED);

        $this->applyBranchFilter($query, 'cancellation_branch_id');

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->whereHas('booking', function ($b) use ($search) {
                    $b->where('invoice_id', 'like', "%{$search}%")
                        ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$search}%"));
                })->orWhereHas('passenger', function ($p) use ($search) {
                    $p->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%");
                });
            });
        }

        if ($request->filled('branch_id')) {
            $query->where('cancellation_branch_id', $request->branch_id);
        }

        return $query;
    }

    private function paginatedResponse($paginator, $items): JsonResponse
    {
        return response()->json([
            'data' => $items,
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }
}
