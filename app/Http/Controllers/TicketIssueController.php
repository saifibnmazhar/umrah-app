<?php

namespace App\Http\Controllers;

use App\Models\IssuedTicket;
use App\Models\Booking;
use App\Models\Passenger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TicketIssueController extends Controller
{
    public function issue(Request $request, Booking $booking, Passenger $passenger)
    {
        if ($passenger->booking_id !== $booking->id) {
            abort(403, 'Passenger does not belong to this booking.');
        }

        $validated = $request->validate([
            'ticket_number' => 'nullable|string|max:100',
            'pnr' => 'nullable|string|max:50',
            'ticket_agent_id' => 'nullable|exists:ticket_agents,id',
            'ticket_fare_id' => 'nullable|exists:ticket_fares,id',
            'group_ticket_id' => 'nullable|exists:group_tickets,id',
            'issued_date' => 'nullable|date',
            'inbound_date' => 'nullable|date',
            'outbound_date' => 'nullable|date',
            'selling_fare' => 'nullable|numeric|min:0',
            'net_fare' => 'nullable|numeric|min:0',
            'is_refundable' => 'boolean',
            'is_exchangeable' => 'boolean',
            'baggage_inbound' => 'nullable|string|max:255',
            'baggage_outbound' => 'nullable|string|max:255',
            'outbound_pending' => 'boolean',
            'issue_type' => 'nullable|in:regular,additional,pending_outbound',
        ]);

        $issuedTicket = IssuedTicket::where('passenger_id', $passenger->id)
            ->where('status', 'pending')
            ->latest()
            ->first();

        if (!$issuedTicket) {
            return response()->json(['message' => 'No pending ticket record found for this passenger.'], 404);
        }

        try {
            DB::beginTransaction();

            if (!empty($validated['group_ticket_id'])) {
                \App\Models\GroupTicket::where('id', $validated['group_ticket_id'])
                    ->decrement('ticket_qty');
            }

            $oldData = $issuedTicket->toArray();

            $issuedTicket->update(array_merge($validated, [
                'status' => 'issued',
                'issue_type' => 'regular',
                'user_id' => auth()->id(),
            ]));

            $passenger->update(['ticket_status' => 'issued']);

            $issuedTicket->logAction('issued', $oldData, $issuedTicket->toArray());

            DB::commit();

            $issuedTicket->load([
                'ticketAgent',
                'ticketFare.airline',
                'ticketFare.airlineClass.class',
                'ticketFare.route',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Ticket issued successfully.',
                'issued_ticket' => $issuedTicket,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Ticket issue failed: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to issue ticket.'], 500);
        }
    }

    public function edit(Request $request, Booking $booking, Passenger $passenger)
    {
        if ($passenger->booking_id !== $booking->id) {
            abort(403, 'Passenger does not belong to this booking.');
        }

        $validated = $request->validate([
            'ticket_number' => 'nullable|string|max:100',
            'pnr' => 'nullable|string|max:50',
            'ticket_agent_id' => 'nullable|exists:ticket_agents,id',
            'ticket_fare_id' => 'nullable|exists:ticket_fares,id',
            'group_ticket_id' => 'nullable|exists:group_tickets,id',
            'issued_date' => 'nullable|date',
            'inbound_date' => 'nullable|date',
            'outbound_date' => 'nullable|date',
            'selling_fare' => 'nullable|numeric|min:0',
            'net_fare' => 'nullable|numeric|min:0',
            'is_refundable' => 'boolean',
            'is_exchangeable' => 'boolean',
            'baggage_inbound' => 'nullable|string|max:255',
            'baggage_outbound' => 'nullable|string|max:255',
            'outbound_pending' => 'boolean',
        ]);

        $issuedTicket = IssuedTicket::where('passenger_id', $passenger->id)
            ->where('status', 'issued')
            ->latest()
            ->first();

        if (!$issuedTicket) {
            return response()->json(['message' => 'No issued ticket found for this passenger.'], 404);
        }

        try {
            DB::beginTransaction();

            $oldData = $issuedTicket->toArray();

            $issuedTicket->update($validated);

            $issuedTicket->logAction('edited', $oldData, $issuedTicket->toArray());

            DB::commit();

            $issuedTicket->load([
                'ticketAgent',
                'ticketFare.airline',
                'ticketFare.airlineClass.class',
                'ticketFare.route',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Ticket updated successfully.',
                'issued_ticket' => $issuedTicket,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Ticket edit failed: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to update ticket.'], 500);
        }
    }
}
