<?php

namespace App\Http\Controllers;

use App\Models\CancelledPassenger;
use App\Models\Passenger;
use App\Services\PassengerCancellationService;

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
