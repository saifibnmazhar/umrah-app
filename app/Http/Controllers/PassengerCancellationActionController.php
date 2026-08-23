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

        $refundable = (float) $cancelledPassenger->refundable_amount;
        $balance = max(0, (float) ($cancelledPassenger->invoice?->balance ?? 0));
        $maxAdjustable = min($refundable, $balance);

        // Partial settlements allowed: any amount up to the lesser of
        // refundable and balance is credited to the due, the rest is paid out.
        $validated = $request->validate([
            'balance_adjusted_amount' => 'required|numeric|min:0|max:'.$maxAdjustable,
            'payment_method' => 'required|in:'.implode(',', array_column(PaymentMethod::cases(), 'value')),
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
