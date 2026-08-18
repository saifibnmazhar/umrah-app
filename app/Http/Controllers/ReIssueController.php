<?php

namespace App\Http\Controllers;

use App\Enums\PaymentMethod;
use App\Enums\TicketStatus;
use App\Models\Booking;
use App\Models\IssuedTicket;
use App\Models\Passenger;
use App\Models\Payment;
use App\Models\ReIssuedTicket;
use App\Models\TransactionType;
use App\Services\InvoiceService;
use App\Services\VoucherService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

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
            'route_id' => 'nullable|exists:routes,id',
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
            'total_customer_payment' => 'required_if:payment_by,customer|numeric|min:0',
            'remarks' => 'nullable|string',
            'payment_by' => 'nullable|in:customer,airline,employee',
            'payment_option' => 'nullable|required_if:payment_by,customer|in:customer_payment,refund_adjustment',
            'refund_adjustment_amount' => [
                Rule::requiredIf(function () use ($request) {
                    return $request->input('payment_by') === 'customer'
                        && $request->input('payment_option') === 'refund_adjustment';
                }),
                'numeric',
                'min:0',
            ],
        ]);

        $issuedTicket = IssuedTicket::where('id', $validated['issued_ticket_id'])
            ->where('passenger_id', $passenger->id)
            ->first();

        if (! $issuedTicket) {
            return response()->json(['message' => 'Ticket record not found for this passenger.'], 404);
        }

        if ($issuedTicket->pendingRequests()->exists()) {
            return response()->json(['message' => 'A re-issue/refund request is pending for this ticket; process it first.'], 422);
        }

        if (! in_array($issuedTicket->status, ['issued', 'refunded', 're-issued'])) {
            return response()->json(['message' => 'This ticket cannot be re-issued.'], 400);
        }

        $wasRefunded = $issuedTicket->status === 'refunded';

        try {
            DB::beginTransaction();

            if ($issuedTicket->status === 're-issued') {
                $latestRe = $issuedTicket->latestReIssuedTicket;
                $oldData = $latestRe ? $latestRe->toArray() : $issuedTicket->toArray();
                $oldData['log_source'] = 're_issued_tickets';
                $oldData['re_issued_ticket_id'] = $latestRe?->id;
            } elseif ($issuedTicket->status === 'refunded') {
                $oldData = [];
                $latestRefund = $issuedTicket->latestRefundedTicket;
                $latestRe = $issuedTicket->latestReIssuedTicket;
                if ($latestRefund) {
                    $refundSnap = $latestRefund->toArray();
                    $refundSnap['log_source'] = 'refunded_tickets';
                    $refundSnap['refunded_ticket_id'] = $latestRefund->id;
                    $oldData[] = $refundSnap;
                }
                if ($latestRe) {
                    $reSnap = $latestRe->toArray();
                    $reSnap['log_source'] = 're_issued_tickets';
                    $reSnap['re_issued_ticket_id'] = $latestRe->id;
                    $oldData[] = $reSnap;
                }
            } else {
                $oldData = $issuedTicket->toArray();
                $oldData['log_source'] = 'issued_tickets';
                $oldData['issued_ticket_id'] = $issuedTicket->id;
            }

            $reIssueData = array_merge($validated, [
                'user_id' => auth()->id(),
                'selling_fare' => $validated['selling_fare'] ?? $issuedTicket->selling_fare ?? 0,
                'net_fare' => $validated['net_fare'] ?? $issuedTicket->net_fare ?? 0,
                'offer_price' => $validated['offer_price'] ?? $issuedTicket->offer_price ?? 0,
                'payment_option' => ($validated['payment_by'] ?? null) === 'customer'
                    ? $validated['payment_option']
                    : null,
                'refund_adjustment_amount' => ($validated['payment_by'] ?? null) === 'customer'
                        && $validated['payment_option'] === 'refund_adjustment'
                    ? (float) $validated['refund_adjustment_amount']
                    : 0,
                'total_customer_payment' => ($validated['payment_by'] ?? null) === 'customer'
                    ? (float) $validated['total_customer_payment']
                    : 0,
            ]);

            $reIssuedTicket = ReIssuedTicket::create($reIssueData);

            $issuedTicket->update(['status' => 're-issued']);

            $newData = $reIssuedTicket->toArray();
            $newData['status'] = TicketStatus::RE_ISSUED->value;
            $newData['log_source'] = 're_issued_tickets';
            $newData['re_issued_ticket_id'] = $reIssuedTicket->id;

            $issuedTicket->logAction('re-issued', $oldData, $newData);

            if (($validated['payment_by'] ?? null) === 'customer') {
                $refundedNetFare = $wasRefunded
                    ? (float) ($issuedTicket->latestRefundedTicket?->net_fare ?? $issuedTicket->net_fare ?? 0)
                    : 0;

                $totalCost = (float) $validated['re_issue_charge']
                    + (float) $validated['fare_difference']
                    + (float) $validated['other_costs']
                    + $refundedNetFare;

                $totalCustomerPayment = $totalCost + (float) $validated['service_charge'];

                if ($validated['payment_option'] === 'refund_adjustment') {
                    $amount = (float) $validated['refund_adjustment_amount'];

                    if ($amount > $totalCustomerPayment) {
                        throw new \InvalidArgumentException('Refund adjustment amount exceeds the total customer payment.');
                    }
                    if ($amount > (float) $passenger->refund_payable) {
                        throw new \InvalidArgumentException('Refund adjustment amount exceeds the available refund payable.');
                    }

                    if ($amount > 0) {
                        $passenger->decreaseRefundPayable($amount);

                        $transactionType = TransactionType::where('name', 'Ticket Refund - Re-issue')->first();

                        $payment = Payment::create([
                            'invoice_id' => $booking->invoice?->id,
                            'booking_id' => $booking->id,
                            'branch_id' => $booking->booking_branch_id,
                            'user_id' => auth()->id(),
                            'currency_rate_id' => $booking->currency_rate_id,
                            'payment_date' => now(),
                            'payment_method' => PaymentMethod::CASH,
                            'amount' => $amount,
                            'bdt_amount' => 0,
                            'passenger_id' => $passenger->id,
                            're_issued_ticket_id' => $reIssuedTicket->id,
                            'remarks' => $validated['remarks'] ?? null,
                        ]);

                        app(VoucherService::class)->createVoucher([
                            'invoice_id' => $booking->invoice?->id,
                            'booking_id' => $booking->id,
                            'payment_id' => $payment->id,
                            'branch_id' => $booking->booking_branch_id,
                            'user_id' => auth()->id(),
                            'currency_rate_id' => $booking->currency_rate_id,
                            'transaction_type_id' => $transactionType?->id,
                            'payment_date' => now(),
                            'payment_method' => PaymentMethod::CASH,
                            'amount' => $amount,
                            'bdt_amount' => 0,
                            'notes' => $validated['remarks'] ?? null,
                        ]);
                    }
                } elseif ($totalCustomerPayment > 0) {
                    $invoice = $booking->invoice;
                    if ($invoice) {
                        app(InvoiceService::class)->updateTotals(
                            $invoice,
                            (float) $invoice->total_amount + $totalCustomerPayment,
                            're_issue_cost_added'
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
        } catch (\InvalidArgumentException $e) {
            DB::rollBack();

            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Ticket re-issue failed: '.$e->getMessage());

            return response()->json(['message' => 'Failed to re-issue ticket.'], 500);
        }
    }

    public function byBooking(Booking $booking)
    {
        $reIssuedTickets = ReIssuedTicket::whereHas('issuedTicket', function ($q) use ($booking) {
            $q->where('booking_id', $booking->id);
        })
            ->with([
            'ticketAgent',
            'ticketFare.airline',
            'ticketFare.airlineClass.class',
            'ticketFare.route.fromCity',
            'ticketFare.route.toCity',
            'ticketFare.route.returnCity',
            'ticketFare.route.multiSegments.fromCity',
            'ticketFare.route.multiSegments.toCity',
            'reason',
            'issuedTicket.passenger',
        ])
            ->orderBy('re_issue_date', 'asc')
            ->get();

        return response()->json($reIssuedTickets);
    }
}
