<?php

namespace App\Http\Controllers;

use App\Enums\CancelledBookingStatus;
use App\Models\Branch;
use App\Models\CancelledPassenger;
use App\Models\Passenger;
use App\Services\PassengerCancellationService;
use Illuminate\Http\Request;

class PassengerCancellationViewController extends Controller
{
    public function preview(Passenger $passenger)
    {
        $service = app(PassengerCancellationService::class);

        return response()->json($service->getCancellationPreview($passenger));
    }

    public function confirmPage(CancelledPassenger $cancelledPassenger)
    {
        $this->ensureBranchAccess($cancelledPassenger);

        $cancelledPassenger->load([
            'booking.customer',
            'booking.invoice',
            'passenger',
            'user',
            'cancellationBranch',
        ]);

        $invoice = $cancelledPassenger->booking->invoice;

        return view('cancelled-passengers.confirm', compact('cancelledPassenger', 'invoice'));
    }

    public function passengerIndex(Request $request)
    {
        $this->ensureFingerprintAdminHasBranch();

        $query = CancelledPassenger::with([
            'booking.customer',
            'booking.bookingBranch',
            'passenger',
            'user',
            'cancellationBranch',
        ])->where('status', CancelledBookingStatus::PROCESSING);

        $query->when(auth()->user()->branch_id, fn ($q) => $q->where('cancellation_branch_id', auth()->user()->branch_id));

        if ($request->filled('branch_id')) {
            $query->where('cancellation_branch_id', $request->branch_id);
        }

        $cancelledPassengers = $query->latest()->paginate(20)->withQueryString();
        $branches = Branch::select('id', 'name')->orderBy('name')->get();

        return view('cancelled-passengers.index', compact('cancelledPassengers', 'branches'));
    }

    private function ensureBranchAccess(CancelledPassenger $cancelledPassenger): void
    {
        $this->ensureFingerprintAdminHasBranch();

        if (auth()->user()->branch_id
            && auth()->user()->branch_id !== $cancelledPassenger->cancellation_branch_id) {
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
