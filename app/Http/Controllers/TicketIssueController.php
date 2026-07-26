<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\IssuedTicket;
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
            'issued_ticket_id' => 'required|exists:issued_tickets,id',
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
            'offer_price' => 'nullable|numeric|min:0',
            'is_refundable' => 'boolean',
            'is_exchangeable' => 'boolean',
            'baggage_inbound' => 'nullable|string|max:255',
            'baggage_outbound' => 'nullable|string|max:255',
            'outbound_pending' => 'boolean',
            'issue_type' => 'nullable|in:regular,additional,pending_outbound',
            'clear_double_ticket' => 'boolean',
            'ticket_fare_inbound_id' => 'nullable|exists:ticket_fares,id',
            'ticket_fare_outbound_id' => 'nullable|exists:ticket_fares,id',
        ]);

        $issuedTicket = IssuedTicket::where('id', $validated['issued_ticket_id'])
            ->where('passenger_id', $passenger->id)
            ->first();

        if (! $issuedTicket) {
            return response()->json(['message' => 'Ticket record not found for this passenger.'], 404);
        }

        if ($issuedTicket->status !== 'pending') {
            return response()->json(['message' => 'This ticket has already been issued.'], 400);
        }

        try {
            DB::beginTransaction();

            $oldData = $issuedTicket->toArray();

            $updateData = array_merge($validated, [
                'status' => 'issued',
                'user_id' => auth()->id(),
            ]);

            if ($issuedTicket->issue_type === 'pending_outbound') {
                unset($updateData['issue_type']);
                if (isset($validated['ticket_fare_id'])) {
                    $passenger->update(['ticket_fare_outbound_id' => $validated['ticket_fare_id']]);
                }
            } else {
                $updateData['issue_type'] = 'regular';
            }

            $issuedTicket->update($updateData);

            $passenger->update(['ticket_status' => 'issued']);

            if ($validated['clear_double_ticket'] ?? false) {
                $passenger->update([
                    'ticket_fare_inbound_id' => null,
                    'ticket_fare_outbound_id' => null,
                    'ticket_fare_id' => $validated['ticket_fare_id'] ?? null,
                ]);
                IssuedTicket::where('passenger_id', $passenger->id)
                    ->where('issue_type', 'pending_outbound')
                    ->where('status', 'pending')
                    ->delete();
            } elseif ($issuedTicket->issue_type !== 'pending_outbound' && ($validated['outbound_pending'] ?? false)) {
                $existingPendingOutbound = IssuedTicket::where('passenger_id', $passenger->id)
                    ->where('issue_type', 'pending_outbound')
                    ->where('status', 'pending')
                    ->exists();

                if (! $existingPendingOutbound) {
                    $pendingOutboundFareId = $validated['ticket_fare_outbound_id'] ?? $validated['ticket_fare_id'] ?? null;
                    IssuedTicket::create([
                        'passenger_id' => $issuedTicket->passenger_id,
                        'booking_id' => $issuedTicket->booking_id,
                        'user_id' => auth()->id(),
                        'issue_type' => 'pending_outbound',
                        'status' => 'pending',
                        'is_refundable' => false,
                        'is_exchangeable' => false,
                        'outbound_pending' => false,
                        'ticket_fare_id' => $pendingOutboundFareId,
                    ]);
                    if ($pendingOutboundFareId) {
                        $passenger->update(['ticket_fare_outbound_id' => $pendingOutboundFareId]);
                    }
                }
            }

            $issuedTicket->logAction('issued', $oldData, $issuedTicket->toArray());

            $pendingOutboundTicket = IssuedTicket::where('passenger_id', $passenger->id)
                ->where('issue_type', 'pending_outbound')
                ->where('status', 'pending')
                ->first();

            DB::commit();

            $issuedTicket->load([
                'ticketAgent',
                'ticketFare.airline',
                'ticketFare.airlineClass.class',
                'ticketFare.route.fromCity',
                'ticketFare.route.toCity',
                'ticketFare.route.returnCity',
                'ticketFare.route.multiSegments.fromCity',
                'ticketFare.route.multiSegments.toCity',
            ]);

            if ($pendingOutboundTicket) {
                $pendingOutboundTicket->load([
                    'ticketFare.airline',
                    'ticketFare.airlineClass.class',
                    'ticketFare.route.fromCity',
                    'ticketFare.route.toCity',
                    'ticketFare.route.returnCity',
                    'ticketFare.route.multiSegments.fromCity',
                    'ticketFare.route.multiSegments.toCity',
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Ticket issued successfully.',
                'issued_ticket' => $issuedTicket,
                'pending_outbound_ticket' => $pendingOutboundTicket,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Ticket issue failed: '.$e->getMessage());

            return response()->json(['message' => 'Failed to issue ticket.'], 500);
        }
    }

    public function edit(Request $request, Booking $booking, Passenger $passenger)
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
            'issued_date' => 'nullable|date',
            'inbound_date' => 'nullable|date',
            'outbound_date' => 'nullable|date',
            'selling_fare' => 'nullable|numeric|min:0',
            'net_fare' => 'nullable|numeric|min:0',
            'offer_price' => 'nullable|numeric|min:0',
            'is_refundable' => 'boolean',
            'is_exchangeable' => 'boolean',
            'baggage_inbound' => 'nullable|string|max:255',
            'baggage_outbound' => 'nullable|string|max:255',
            'outbound_pending' => 'boolean',
            'clear_double_ticket' => 'boolean',
            'ticket_fare_inbound_id' => 'nullable|exists:ticket_fares,id',
            'ticket_fare_outbound_id' => 'nullable|exists:ticket_fares,id',
        ]);

        $issuedTicket = IssuedTicket::where('id', $validated['issued_ticket_id'])
            ->where('passenger_id', $passenger->id)
            ->first();

        if (! $issuedTicket) {
            return response()->json(['message' => 'Ticket record not found for this passenger.'], 404);
        }

        try {
            DB::beginTransaction();

            $oldData = $issuedTicket->toArray();

            $issuedTicket->update($validated);

            $issuedTicket->logAction('edited', $oldData, $issuedTicket->toArray());

            if ($issuedTicket->issue_type === 'pending_outbound' && isset($validated['ticket_fare_id'])) {
                $passenger->update(['ticket_fare_outbound_id' => $validated['ticket_fare_id']]);
            }

            if ($validated['clear_double_ticket'] ?? false) {
                $passenger->update([
                    'ticket_fare_inbound_id' => null,
                    'ticket_fare_outbound_id' => null,
                    'ticket_fare_id' => $validated['ticket_fare_id'] ?? null,
                ]);
                IssuedTicket::where('passenger_id', $passenger->id)
                    ->where('issue_type', 'pending_outbound')
                    ->where('status', 'pending')
                    ->delete();
            } elseif ($validated['outbound_pending'] ?? false) {
                $existingPendingOutbound = IssuedTicket::where('passenger_id', $passenger->id)
                    ->where('issue_type', 'pending_outbound')
                    ->where('status', 'pending')
                    ->exists();

                if (! $existingPendingOutbound) {
                    $pendingOutboundFareId = $validated['ticket_fare_outbound_id'] ?? $validated['ticket_fare_id'] ?? null;
                    IssuedTicket::create([
                        'passenger_id' => $issuedTicket->passenger_id,
                        'booking_id' => $issuedTicket->booking_id,
                        'user_id' => auth()->id(),
                        'issue_type' => 'pending_outbound',
                        'status' => 'pending',
                        'is_refundable' => false,
                        'is_exchangeable' => false,
                        'outbound_pending' => false,
                        'ticket_fare_id' => $pendingOutboundFareId,
                    ]);
                    if ($pendingOutboundFareId) {
                        $passenger->update(['ticket_fare_outbound_id' => $pendingOutboundFareId]);
                    }
                }
            }

            DB::commit();

            $issuedTicket->load([
                'ticketAgent',
                'ticketFare.airline',
                'ticketFare.airlineClass.class',
                'ticketFare.route.fromCity',
                'ticketFare.route.toCity',
                'ticketFare.route.returnCity',
                'ticketFare.route.multiSegments.fromCity',
                'ticketFare.route.multiSegments.toCity',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Ticket updated successfully.',
                'issued_ticket' => $issuedTicket,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Ticket edit failed: '.$e->getMessage());

            return response()->json(['message' => 'Failed to update ticket.'], 500);
        }
    }
}
