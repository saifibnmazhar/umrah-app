<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\IssuedTicket;
use App\Models\Passenger;
use App\Models\ReIssuedTicket;
use App\Services\InvoiceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReIssueController extends Controller
{
    public function store(Request $request, Booking $booking, Passenger $passenger)
    {
        if ($passenger->booking_id !== $booking->id) {
            abort(403, 'Passenger does not belong to this booking.');
        }

        $validated = $request->validate([
            'issued_ticket_id' => 'required|exists:issued_tickets,id',
            'ticket_number' => 'nullable|string|max:100',
            'pnr' => 'nullable|string|max:50',
            'ticket_agent_id' => 'nullable|exists:ticket_agents,id',
            'ticket_fare_id' => 'nullable|exists:ticket_fares,id',
            'group_ticket_id' => 'nullable|exists:group_tickets,id',
            're_issue_date' => 'nullable|date',
            'inbound_date' => 'nullable|date',
            'outbound_date' => 'nullable|date',
            'is_refundable' => 'boolean',
            'is_exchangeable' => 'boolean',
            'baggage_inbound' => 'nullable|string|max:255',
            'baggage_outbound' => 'nullable|string|max:255',
            'selling_fare' => 'nullable|numeric|min:0',
            'net_fare' => 'nullable|numeric|min:0',
            'offer_price' => 'nullable|numeric|min:0',

            'reason_id' => 'required|exists:re_issue_refund_reasons,id',
            're_issue_charge' => 'required|numeric|min:0',
            'fare_difference' => 'required|numeric',
            'other_costs' => 'required|numeric|min:0',
            'service_charge' => 'required|numeric|min:0',
            'remarks' => 'nullable|string',
            'payment_by' => 'nullable|in:customer,airline,employee',
        ]);

        $issuedTicket = IssuedTicket::where('id', $validated['issued_ticket_id'])
            ->where('passenger_id', $passenger->id)
            ->first();

        if (! $issuedTicket) {
            return response()->json(['message' => 'Ticket record not found for this passenger.'], 404);
        }

        if (! in_array($issuedTicket->status, ['issued', 'refunded'])) {
            return response()->json(['message' => 'This ticket cannot be re-issued.'], 400);
        }

        try {
            DB::beginTransaction();

            $oldData = $issuedTicket->toArray();

            $reIssueData = array_merge($validated, [
                'user_id' => auth()->id(),
                'selling_fare' => $validated['selling_fare'] ?? $issuedTicket->selling_fare ?? 0,
                'net_fare' => $validated['net_fare'] ?? $issuedTicket->net_fare ?? 0,
                'offer_price' => $validated['offer_price'] ?? $issuedTicket->offer_price ?? 0,
            ]);

            $reIssuedTicket = ReIssuedTicket::create($reIssueData);

            $issuedTicket->update(['status' => 're-issued']);

            $issuedTicket->logAction('re-issued', $oldData, $issuedTicket->toArray());

            if (($validated['payment_by'] ?? null) === 'customer') {
                $totalCustomerPayment = (float) $validated['re_issue_charge']
                    + (float) $validated['fare_difference']
                    + (float) $validated['other_costs']
                    + (float) $validated['service_charge'];

                if ($totalCustomerPayment > 0) {
                    $invoice = $booking->invoice;
                    if ($invoice) {
                        app(InvoiceService::class)->updateTotals(
                            $invoice,
                            (float) $invoice->total_amount + $totalCustomerPayment
                        );
                    }
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Ticket re-issued successfully.',
                're_issued_ticket' => $reIssuedTicket,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Ticket re-issue failed: '.$e->getMessage());

            return response()->json(['message' => 'Failed to re-issue ticket.'], 500);
        }
    }
}
