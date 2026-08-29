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

        if ($passenger->isOnHold() || $passenger->isOnCancel() || $passenger->is_cancelled) {
            return response()->json(['success' => false, 'message' => 'Cannot modify ticket for a cancelled passenger'], 422);
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

        if (! in_array($issuedTicket->status, ['pending', 'awaiting-group'])) {
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

            if ($issuedTicket->issue_type !== 'pending_outbound' && ! empty($validated['ticket_fare_id'])) {
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
                        'ticket_fare_id' => null,
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

        if ($passenger->isOnHold() || $passenger->isOnCancel() || $passenger->is_cancelled) {
            return response()->json(['success' => false, 'message' => 'Cannot modify ticket for a cancelled passenger'], 422);
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
            'reason_id' => 'nullable|exists:re_issue_refund_reasons,id',
            're_issue_charge' => 'nullable|numeric|min:0',
            'fare_difference' => 'nullable|numeric',
            'other_costs' => 'nullable|numeric|min:0',
            'service_charge' => 'nullable|numeric|min:0',
            'total_customer_payment' => 'nullable|numeric|min:0',
            'remarks' => 'nullable|string',
            'payment_by' => 'nullable|in:customer,airline,employee,company',
            'payment_option' => 'nullable|in:customer_payment,refund_adjustment',
            'refund_adjustment_amount' => 'nullable|numeric|min:0',
        ]);

        $issuedTicket = IssuedTicket::where('id', $validated['issued_ticket_id'])
            ->where('passenger_id', $passenger->id)
            ->first();

        if (! $issuedTicket) {
            return response()->json(['message' => 'Ticket record not found for this passenger.'], 404);
        }

        try {
            DB::beginTransaction();

            if ($issuedTicket->status === 're-issued') {
                $latestRe = $issuedTicket->latestReIssuedTicket;

                if (! $latestRe) {
                    DB::rollBack();

                    return response()->json(['message' => 'Re-issued ticket record not found.'], 404);
                }

                $oldData = $latestRe->toArray();
                $oldData['log_source'] = 're_issued_tickets';
                $oldData['re_issued_ticket_id'] = $latestRe->id;

                $latestRe->update([
                    'ticket_number' => $validated['ticket_number'] ?? $latestRe->ticket_number,
                    'pnr' => $validated['pnr'] ?? $latestRe->pnr,
                    'ticket_agent_id' => $validated['ticket_agent_id'] ?? $latestRe->ticket_agent_id,
                    'ticket_fare_id' => $validated['ticket_fare_id'] ?? $latestRe->ticket_fare_id,
                    'group_ticket_id' => $validated['group_ticket_id'] ?? $latestRe->group_ticket_id,
                    're_issue_date' => $validated['issued_date'] ?? $latestRe->re_issue_date,
                    'inbound_date' => $validated['inbound_date'] ?? $latestRe->inbound_date,
                    'outbound_date' => $validated['outbound_date'] ?? $latestRe->outbound_date,
                    'selling_fare' => $validated['selling_fare'] ?? $latestRe->selling_fare,
                    'net_fare' => $validated['net_fare'] ?? $latestRe->net_fare,
                    'offer_price' => $validated['offer_price'] ?? $latestRe->offer_price,
                    'is_refundable' => $validated['is_refundable'] ?? $latestRe->is_refundable,
                    'is_exchangeable' => $validated['is_exchangeable'] ?? $latestRe->is_exchangeable,
                    'baggage_inbound' => $validated['baggage_inbound'] ?? $latestRe->baggage_inbound,
                    'baggage_outbound' => $validated['baggage_outbound'] ?? $latestRe->baggage_outbound,
                ]);

                $reIssueCharge = $validated['re_issue_charge'] ?? (float) $latestRe->re_issue_charge;
                $fareDifference = $validated['fare_difference'] ?? (float) $latestRe->fare_difference;
                $otherCosts = $validated['other_costs'] ?? (float) $latestRe->other_costs;
                $refundAdjustment = $validated['refund_adjustment_amount'] ?? (float) $latestRe->refund_adjustment_amount;
                $totalCost = (float) $reIssueCharge + (float) $fareDifference + (float) $otherCosts - (float) $refundAdjustment;

                $latestRe->update([
                    'reason_id' => array_key_exists('reason_id', $validated) ? $validated['reason_id'] : $latestRe->reason_id,
                    're_issue_charge' => $reIssueCharge,
                    'fare_difference' => $fareDifference,
                    'other_costs' => $otherCosts,
                    'service_charge' => array_key_exists('service_charge', $validated) ? (float) $validated['service_charge'] : $latestRe->service_charge,
                    'total_customer_payment' => array_key_exists('total_customer_payment', $validated) ? (float) $validated['total_customer_payment'] : $latestRe->total_customer_payment,
                    'remarks' => array_key_exists('remarks', $validated) ? $validated['remarks'] : $latestRe->remarks,
                    'payment_by' => array_key_exists('payment_by', $validated) ? $validated['payment_by'] : $latestRe->payment_by,
                    'payment_option' => array_key_exists('payment_option', $validated) && $validated['payment_by'] === 'customer'
                        ? $validated['payment_option']
                        : (array_key_exists('payment_by', $validated) && $validated['payment_by'] !== 'customer' ? null : $latestRe->payment_option),
                    'refund_adjustment_amount' => $refundAdjustment,
                    'total_cost' => round($totalCost, 6),
                ]);

                $newData = $latestRe->toArray();
                $newData['log_source'] = 're_issued_tickets';
                $newData['re_issued_ticket_id'] = $latestRe->id;

                $issuedTicket->logAction('edited', $oldData, $newData);

                DB::commit();

                $latestRe->load([
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
                    're_issued_ticket' => $latestRe,
                ]);
            }

            $oldData = $issuedTicket->toArray();

            $issuedTicket->update($validated);

            $issuedTicket->logAction('edited', $oldData, $issuedTicket->toArray());

            if ($issuedTicket->issue_type !== 'pending_outbound' && ! empty($validated['ticket_fare_id'])) {
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
                        'ticket_fare_id' => null,
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

    public function createPendingOutbound(Request $request, Passenger $passenger)
    {
        if ($passenger->isOnHold() || $passenger->isOnCancel() || $passenger->is_cancelled) {
            return response()->json(['success' => false, 'message' => 'Cannot modify ticket for a cancelled passenger'], 422);
        }

        if (! $passenger->ticket_fare_outbound_id) {
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
        if ($passenger->isOnHold() || $passenger->isOnCancel() || $passenger->is_cancelled) {
            return response()->json(['success' => false, 'message' => 'Cannot modify ticket for a cancelled passenger'], 422);
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
        $regularTicket = $allTickets->first(fn ($t) => is_null($t->issue_type) || $t->issue_type === 'regular');
        $outboundTicket = $allTickets->first(fn ($t) => $t->issue_type === 'pending_outbound');

        $updatedIds = [];
        $createdTicket = null;

        try {
            DB::beginTransaction();

            $action = $validated['action'];

            if ($action === 'all') {
                $confirmable = $passenger->allIssuedTickets->filter(fn ($t) => in_array($t->status, ['pending', 'refunded']));
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
                if (! $outboundTicket) {
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
                if ($regularTicket && ! $regularTicket->outbound_pending) {
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
