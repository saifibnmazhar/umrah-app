<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Passenger;
use App\Models\VisaSubmission;
use App\Models\VisaAgent;
use Illuminate\Http\Request;

class VisaSubmissionController extends Controller
{
    public function submit(Request $request, Booking $booking, Passenger $passenger)
    {
        if ($passenger->booking_id !== $booking->id) {
            return response()->json(['success' => false, 'message' => 'Passenger does not belong to this booking'], 403);
        }

        $validated = $request->validate([
            'visa_agent_id' => 'required|exists:visa_agents,id',
            'commission_agent_id' => 'nullable|exists:commission_agents,id',
            'agent_commission' => 'nullable|numeric|min:0',
        ]);

        $visaSubmission = $passenger->visaSubmission;

        if (!$visaSubmission) {
            return response()->json(['success' => false, 'message' => 'No visa submission found for this passenger'], 404);
        }

        $visaAgent = VisaAgent::with('visaAgentCost')->findOrFail($validated['visa_agent_id']);
        $netVisaCost = (float) ($visaAgent->visaAgentCost?->visa_agent_cost ?? 0);
        $agentCommission = (float) ($validated['agent_commission'] ?? 0);
        $finalCost = $netVisaCost + $agentCommission;

        $visaSubmission->update([
            'visa_agent_id' => $validated['visa_agent_id'],
            'commission_agent_id' => $validated['commission_agent_id'] ?? null,
            'agent_commission' => $agentCommission ?: null,
            'net_visa_cost' => $netVisaCost ?: null,
            'final_cost' => $finalCost ?: null,
            'status' => 'submitted',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Visa submitted successfully',
            'visa_submission' => $visaSubmission->fresh()->load(['visaAgent', 'commissionAgent', 'visaSellingPrice']),
        ]);
    }

    public function issue(Request $request, Booking $booking, Passenger $passenger)
    {
        if ($passenger->booking_id !== $booking->id) {
            return response()->json(['success' => false, 'message' => 'Passenger does not belong to this booking'], 403);
        }

        $validated = $request->validate([
            'visa_number' => 'required|string|max:255',
            'additional_cost' => 'nullable|numeric|min:0',
        ]);

        $visaSubmission = $passenger->visaSubmission;

        if (!$visaSubmission) {
            return response()->json(['success' => false, 'message' => 'No visa submission found for this passenger'], 404);
        }

        if ($visaSubmission->status?->value !== 'submitted') {
            return response()->json(['success' => false, 'message' => 'Visa must be in submitted status to issue'], 422);
        }

        $additionalCost = (float) ($validated['additional_cost'] ?? 0);
        $currentFinalCost = (float) ($visaSubmission->final_cost ?? 0);
        $finalCost = $currentFinalCost + $additionalCost;

        $visaSubmission->update([
            'visa_number' => $validated['visa_number'],
            'additional_cost' => $additionalCost ?: null,
            'final_cost' => $finalCost ?: null,
            'status' => 'issued',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Visa issued successfully',
            'visa_submission' => $visaSubmission->fresh()->load(['visaAgent', 'commissionAgent', 'visaSellingPrice']),
        ]);
    }

    public function edit(Request $request, Booking $booking, Passenger $passenger)
    {
        if ($passenger->booking_id !== $booking->id) {
            return response()->json(['success' => false, 'message' => 'Passenger does not belong to this booking'], 403);
        }

        $validated = $request->validate([
            'visa_agent_id' => 'required|exists:visa_agents,id',
            'commission_agent_id' => 'nullable|exists:commission_agents,id',
            'agent_commission' => 'nullable|numeric|min:0',
            'additional_cost' => 'nullable|numeric|min:0',
            'visa_number' => 'nullable|string|max:255',
            'remarks' => 'nullable|string|max:1000',
        ]);

        $visaSubmission = $passenger->visaSubmission;

        if (!$visaSubmission) {
            return response()->json(['success' => false, 'message' => 'No visa submission found for this passenger'], 404);
        }

        $agentCommission = (float) ($validated['agent_commission'] ?? 0);
        $additionalCost = (float) ($validated['additional_cost'] ?? 0);
        $netVisaCost = (float) ($visaSubmission->net_visa_cost ?? 0);
        $finalCost = $netVisaCost + $agentCommission + $additionalCost;

        $visaSubmission->update([
            'visa_agent_id' => $validated['visa_agent_id'],
            'commission_agent_id' => $validated['commission_agent_id'] ?? null,
            'agent_commission' => $agentCommission ?: null,
            'additional_cost' => $additionalCost ?: null,
            'visa_number' => $validated['visa_number'] ?? $visaSubmission->visa_number,
            'remarks' => $validated['remarks'] ?? null,
            'final_cost' => $finalCost ?: null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Visa updated successfully',
            'visa_submission' => $visaSubmission->fresh()->load(['visaAgent', 'commissionAgent', 'visaSellingPrice']),
        ]);
    }
}
