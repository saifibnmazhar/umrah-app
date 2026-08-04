<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\IssuedTicket;
use App\Models\Passenger;
use App\Models\TicketFare;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TicketIssueController extends Controller
{
    public function issue(Request $request, Booking $booking, Passenger $passenger)
    {
        if ($passenger->booking_id !== $booking->id) {
            abort(403, 'Passenger does not belong to this booking.');
        }

        if ($passenger->isOnHold() || $passenger->isOnCancel()) {
            return response()->json(['success' => false, 'message' => 'Cannot modify ticket for a passenger on Hold or Cancel'], 422);
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

        if (!in_array($issuedTicket->status, ['pending', 'awaiting-group'])) {
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
            } else {
                $updateData['issue_type'] = 'regular';
            }

            $issuedTicket->update($updateData);

            $passenger->update(['ticket_status' => 'issued']);

            if ($issuedTicket->issue_type !== 'pending_outbound' && !empty($validated['ticket_fare_id'])) {
                $this->clearPendingOutboundForRoundMulti($passenger, $validated['ticket_fare_id'], $issuedTicket);
            } elseif ($validated['clear_double_ticket'] ?? false) {
                IssuedTicket::where('passenger_id', $passenger->id)
                    ->where('issue_type', 'pending_outbound')
                    ->where('status', 'pending')
                    ->delete();
            } elseif ($issuedTicket->issue_type !== 'pending_outbound' && ($validated['outbound_pending'] ?? false)) {
                $existingPendingOutbound = IssuedTicket::where('passenger_id', $passenger->id)
                    ->where('issue_type', 'pending_outbound')
                    ->exists();

                if (! $existingPendingOutbound) {
                    IssuedTicket::create([
                        'passenger_id' => $issuedTicket->passenger_id,
                        'booking_id' => $issuedTicket->booking_id,
                        'user_id' => auth()->id(),
                        'issue_type' => 'pending_outbound',
                        'status' => 'pending',
                        'is_refundable' => false,
                        'is_exchangeable' => false,
                        'outbound_pending' => false,
                        'ticket_fare_id' => null,
                    ]);
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

        if ($passenger->isOnHold() || $passenger->isOnCancel()) {
            return response()->json(['success' => false, 'message' => 'Cannot modify ticket for a passenger on Hold or Cancel'], 422);
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

            if ($issuedTicket->issue_type !== 'pending_outbound' && !empty($validated['ticket_fare_id'])) {
                $this->clearPendingOutboundForRoundMulti($passenger, $validated['ticket_fare_id'], $issuedTicket);
            } elseif ($validated['clear_double_ticket'] ?? false) {
                IssuedTicket::where('passenger_id', $passenger->id)
                    ->where('issue_type', 'pending_outbound')
                    ->where('status', 'pending')
                    ->delete();
            } elseif ($validated['outbound_pending'] ?? false) {
                $existingPendingOutbound = IssuedTicket::where('passenger_id', $passenger->id)
                    ->where('issue_type', 'pending_outbound')
                    ->exists();

                if (! $existingPendingOutbound) {
                    IssuedTicket::create([
                        'passenger_id' => $issuedTicket->passenger_id,
                        'booking_id' => $issuedTicket->booking_id,
                        'user_id' => auth()->id(),
                        'issue_type' => 'pending_outbound',
                        'status' => 'pending',
                        'is_refundable' => false,
                        'is_exchangeable' => false,
                        'outbound_pending' => false,
                        'ticket_fare_id' => null,
                    ]);
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

    public function createPendingOutbound(Request $request, Passenger $passenger)
    {
        if ($passenger->isOnHold() || $passenger->isOnCancel()) {
            return response()->json(['success' => false, 'message' => 'Cannot modify ticket for a passenger on Hold or Cancel'], 422);
        }

        if (!$passenger->ticket_fare_outbound_id) {
            return response()->json(['message' => 'No outbound fare configured for this passenger.'], 400);
        }

        $existing = IssuedTicket::where('passenger_id', $passenger->id)
            ->where('issue_type', 'pending_outbound')
            ->whereIn('status', ['pending', 'awaiting-group'])
            ->first();

        if ($existing) {
            return response()->json([
                'success' => true,
                'message' => 'Pending outbound ticket already exists.',
                'ticket' => $existing->load([
                    'ticketFare.airline',
                    'ticketFare.airlineClass.class',
                    'ticketFare.route.fromCity',
                    'ticketFare.route.toCity',
                    'ticketFare.route.returnCity',
                    'ticketFare.route.multiSegments.fromCity',
                    'ticketFare.route.multiSegments.toCity',
                ]),
            ]);
        }

        try {
            $ticket = IssuedTicket::create([
                'passenger_id' => $passenger->id,
                'booking_id' => $passenger->booking_id,
                'user_id' => auth()->id(),
                'issue_type' => 'pending_outbound',
                'status' => 'pending',
                'is_refundable' => false,
                'is_exchangeable' => false,
                'outbound_pending' => false,
                'ticket_fare_id' => null,
            ]);

            IssuedTicket::where('passenger_id', $passenger->id)
                ->where(function ($q) {
                    $q->whereNull('issue_type')->orWhere('issue_type', 'regular');
                })
                ->where(function ($q) {
                    $q->whereNull('outbound_pending')->orWhere('outbound_pending', false);
                })
                ->update(['outbound_pending' => true]);

            $ticket->load([
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
                'message' => 'Pending outbound ticket created.',
                'ticket' => $ticket,
            ]);
        } catch (\Exception $e) {
            \Log::error('Create pending outbound failed: '.$e->getMessage());
            return response()->json(['message' => 'Failed to create pending outbound ticket.'], 500);
        }
    }

    public function confirmGroup(Request $request, Passenger $passenger)
    {
        if ($passenger->isOnHold() || $passenger->isOnCancel()) {
            return response()->json(['success' => false, 'message' => 'Cannot modify ticket for a passenger on Hold or Cancel'], 422);
        }

        $validated = $request->validate([
            'action' => 'required|in:all,in,out,both',
            'booking_id' => 'required|exists:bookings,id',
        ]);

        $booking = Booking::findOrFail($validated['booking_id']);
        if ($passenger->booking_id !== $booking->id) {
            abort(403, 'Passenger does not belong to this booking.');
        }

        $allTickets = $passenger->allIssuedTickets;
        $regularTicket = $allTickets->first(fn($t) => is_null($t->issue_type) || $t->issue_type === 'regular');
        $outboundTicket = $allTickets->first(fn($t) => $t->issue_type === 'pending_outbound');

        $updatedIds = [];
        $createdTicket = null;

        try {
            DB::beginTransaction();

            $action = $validated['action'];

            if ($action === 'all') {
                $confirmable = $passenger->allIssuedTickets->filter(fn($t) => in_array($t->status, ['pending', 'refunded']));
                foreach ($confirmable as $ticket) {
                    $oldData = $ticket->toArray();
                    $ticket->update(['status' => 'awaiting-group']);
                    $ticket->logAction('confirmed_group', $oldData, $ticket->toArray());
                    $updatedIds[] = $ticket->id;
                }
            }

            if ($action === 'in') {
                if ($regularTicket && in_array($regularTicket->status, ['pending', 'refunded'])) {
                    $oldData = $regularTicket->toArray();
                    $regularTicket->update(['status' => 'awaiting-group']);
                    $regularTicket->logAction('confirmed_group', $oldData, $regularTicket->toArray());
                    $updatedIds[] = $regularTicket->id;
                }
            }

            if ($action === 'out' || $action === 'both') {
                if (!$outboundTicket) {
                    $createdTicket = IssuedTicket::create([
                        'passenger_id' => $passenger->id,
                        'booking_id' => $booking->id,
                        'user_id' => auth()->id(),
                        'issue_type' => 'pending_outbound',
                        'status' => 'awaiting-group',
                        'is_refundable' => false,
                        'is_exchangeable' => false,
                        'outbound_pending' => false,
                        'ticket_fare_id' => null,
                    ]);
                    $updatedIds[] = $createdTicket->id;
                    $outboundTicket = $createdTicket;
                }
                if ($outboundTicket && in_array($outboundTicket->status, ['pending', 'refunded'])) {
                    $oldData = $outboundTicket->toArray();
                    $outboundTicket->update(['status' => 'awaiting-group']);
                    $outboundTicket->logAction('confirmed_group', $oldData, $outboundTicket->toArray());
                    $updatedIds[] = $outboundTicket->id;
                }
                if ($regularTicket && !$regularTicket->outbound_pending) {
                    $oldData = $regularTicket->toArray();
                    $regularTicket->update(['outbound_pending' => true]);
                }
            }

            if ($action === 'both') {
                if ($regularTicket && in_array($regularTicket->status, ['pending', 'refunded'])) {
                    $oldData = $regularTicket->toArray();
                    $regularTicket->update(['status' => 'awaiting-group']);
                    $regularTicket->logAction('confirmed_group', $oldData, $regularTicket->toArray());
                    $updatedIds[] = $regularTicket->id;
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Tickets confirmed successfully.',
                'updated_ids' => $updatedIds,
                'created_ticket' => $createdTicket,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Ticket confirm group failed: '.$e->getMessage());

            return response()->json(['message' => 'Failed to confirm tickets.'], 500);
        }
    }

    private function clearPendingOutboundForRoundMulti(Passenger $passenger, int $ticketFareId, IssuedTicket $issuedTicket): void
    {
        if (! $passenger->ticket_fare_inbound_id || ! $passenger->ticket_fare_outbound_id) {
            return;
        }

        $routeType = TicketFare::with('route')->find($ticketFareId)?->route?->route_type?->value;

        if (! in_array($routeType, ['round', 'multi_city'], true)) {
            return;
        }

        IssuedTicket::where('passenger_id', $passenger->id)
            ->where('issue_type', 'pending_outbound')
            ->whereIn('status', ['pending', 'awaiting-group'])
            ->delete();

        $issuedTicket->update(['outbound_pending' => false]);
    }
}
