<?php

namespace App\Http\Controllers;

use App\Enums\PaymentMethod;
use App\Models\CancelledPassenger;
use App\Models\Passenger;
use App\Services\PassengerCancellationService;
use Illuminate\Http\Request;

class PassengerCancellationActionController extends Controller
{
    public function initiate(Request $request, Passenger $passenger)
    {
        $validated = $request->validate([
            'cancellation_branch_id' => 'required|exists:branches,id',
            'service_charge_deduction' => 'nullable|numeric|min:0',
        ]);

        try {
            $service = app(PassengerCancellationService::class);
            $cancelledPassenger = $service->initiateCancellation($passenger, $validated);

            return response()->json([
                'success' => true,
                'message' => 'Cancellation initiated',
                'data' => [
                    'id' => $cancelledPassenger->id,
                    'status' => $cancelledPassenger->status->value,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function revert(CancelledPassenger $cancelledPassenger)
    {
        $this->ensureBranchAccess($cancelledPassenger);

        try {
            $service = app(PassengerCancellationService::class);
            $service->revertCancellation($cancelledPassenger);

            return redirect()->route('pending-refunds.index', ['tab' => 'passengers'])
                ->with('success', 'Cancellation reverted successfully.');
        } catch (\Exception $e) {
            return redirect()->route('pending-refunds.index', ['tab' => 'passengers'])
                ->with('error', $e->getMessage());
        }
    }

    public function confirmSubmit(Request $request, CancelledPassenger $cancelledPassenger)
    {
        $this->ensureBranchAccess($cancelledPassenger);

        $validated = $request->validate([
            'payment_method' => 'required|in:'.implode(',', array_column(PaymentMethod::cases(), 'value')),
            'balance_adjusted_amount' => 'required|numeric|min:0',
            'remarks' => 'nullable|string|max:500',
        ]);

        try {
            $service = app(PassengerCancellationService::class);
            $service->confirmCancellation($cancelledPassenger, $validated);

            return redirect()->route('pending-refunds.index', ['tab' => 'passengers'])
                ->with('success', 'Passenger cancellation confirmed successfully.');
        } catch (\Exception $e) {
            return redirect()->route('pending-refunds.index', ['tab' => 'passengers'])
                ->with('error', $e->getMessage());
        }
    }

    private function ensureBranchAccess(CancelledPassenger $cancelledPassenger): void
    {
        if (auth()->user()->roles->pluck('name')->intersect(['Fingerprint Admin'])->isNotEmpty()
            && ! auth()->user()->branch_id) {
            abort(403);
        }

        if (auth()->user()->branch_id
            && auth()->user()->branch_id !== $cancelledPassenger->cancellation_branch_id) {
            abort(403);
        }
    }
}
