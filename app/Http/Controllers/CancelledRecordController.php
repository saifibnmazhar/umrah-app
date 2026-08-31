<?php

namespace App\Http\Controllers;

use App\Enums\CancelledBookingStatus;
use App\Models\Branch;
use App\Models\CancelledBooking;
use App\Models\CancelledPassenger;
use Illuminate\Http\Request;

class CancelledRecordController extends Controller
{
    public function bookingIndex(Request $request)
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

        $cancelledBookings = $query->latest()->paginate(20)->withQueryString();

        return view('cancelled-bookings.index', [
            'tab' => 'bookings',
            'cancelledBookings' => $cancelledBookings,
            'cancelledPassengers' => collect(),
            'branches' => Branch::select('id', 'name')->orderBy('name')->get(),
        ]);
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
}
