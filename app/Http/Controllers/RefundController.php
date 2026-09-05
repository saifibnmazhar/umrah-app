<?php

namespace App\Http\Controllers;

use App\Enums\RefundPaymentStatus;
use App\Models\Booking;
use App\Models\IssuedTicket;
use App\Models\Passenger;
use App\Models\Payment;
use App\Models\RefundedTicket;
use App\Models\TransactionType;
use App\Services\VoucherService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RefundController extends Controller
{
    public function store(Request $request, Booking $booking, Passenger $passenger)
    {
        if ($passenger->booking_id !== $booking->id) {
            abort(403, 'Passenger does not belong to this booking.');
        }

        if ($passenger->isOnHold() || $passenger->isOnCancel()) {
            return response()->json(['message' => 'Refund is not allowed for passengers with Hold or Cancel status.'], 422);
        }

        $issuedTicket = IssuedTicket::where('id', $request->input('issued_ticket_id'))
            ->where('passenger_id', $passenger->id)
            ->first();

        if (! $issuedTicket) {
            return response()->json(['message' => 'Ticket record not found for this passenger.'], 404);
        }

        if ($issuedTicket->pendingRequests()->exists()) {
            return response()->json(['message' => 'A re-issue/refund request is pending for this ticket; process it first.'], 422);
        }

        $refundSource = ($issuedTicket->status === 're-issued' && $issuedTicket->latestReIssuedTicket)
            ? $issuedTicket->latestReIssuedTicket
            : $issuedTicket;
        $refundNetFare = (float) ($refundSource->net_fare ?? 0);

        $validated = $request->validate([
            'issued_ticket_id' => 'required|exists:issued_tickets,id',
            'ticket_number' => 'nullable|string|max:100',
            'pnr' => 'nullable|string|max:50',
            'ticket_agent_id' => 'nullable|exists:ticket_agents,id',
            'ticket_fare_id' => 'nullable|exists:ticket_fares,id',
            'group_ticket_id' => 'nullable|exists:group_tickets,id',
            'refund_date' => 'required|date',
            'inbound_date' => 'nullable|date',
            'outbound_date' => 'nullable|date',
            'is_refundable' => 'boolean',
            'is_exchangeable' => 'boolean',
            'baggage_inbound' => 'nullable|string|max:255',
            'baggage_outbound' => 'nullable|string|max:255',

            'reason_id' => 'required|exists:re_issue_refund_reasons,id',
            'iata_refund' => 'required|numeric|min:0|max:'.$refundNetFare,
            'customer_refund' => 'required|numeric|min:0|max:'.$refundNetFare,
            'service_charge' => 'required|numeric',
            'remarks' => 'nullable|string',
            'payment_by' => 'nullable|in:customer,airline,employee,company',
        ]);

        if (! in_array($issuedTicket->status, ['issued', 're-issued'])) {
            return response()->json(['message' => 'This ticket cannot be refunded.'], 400);
        }

        try {
            DB::beginTransaction();

            if ($issuedTicket->status === 're-issued') {
                $latestRe = $issuedTicket->latestReIssuedTicket;
                $oldData = $latestRe ? $latestRe->toArray() : $issuedTicket->toArray();
                $oldData['log_source'] = 're_issued_tickets';
                $oldData['re_issued_ticket_id'] = $latestRe?->id;
            } else {
                $oldData = $issuedTicket->toArray();
                $oldData['log_source'] = 'issued_tickets';
                $oldData['issued_ticket_id'] = $issuedTicket->id;
            }

            $refundData = array_merge($validated, [
                'user_id' => auth()->id(),
                'selling_fare' => $refundSource->selling_fare ?? 0,
                'net_fare' => $refundSource->net_fare ?? 0,
                'offer_price' => $refundSource->offer_price ?? 0,
                'iata_refunded_amount' => $validated['iata_refund'] ?? 0,
                'refund_to_customer' => $validated['customer_refund'] ?? 0,
                'refund_compensation' => $refundNetFare - (float) $validated['iata_refund'],
            ]);

            $refundedTicket = RefundedTicket::create($refundData);

            $passenger->increaseRefundPayable((float) $validated['customer_refund']);

            $issuedTicket->update(['status' => 'refunded']);

            $newData = $refundedTicket->toArray();
            $newData['log_source'] = 'refunded_tickets';
            $newData['refunded_ticket_id'] = $refundedTicket->id;

            $issuedTicket->logAction('refunded', $oldData, $newData);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Ticket refunded successfully.',
                'refunded_ticket' => $refundedTicket,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Ticket refund failed: '.$e->getMessage());

            return response()->json(['message' => 'Failed to refund ticket.'], 500);
        }
    }

    public function byBooking(Booking $booking)
    {
        $refundedTickets = RefundedTicket::whereHas('issuedTicket', function ($q) use ($booking) {
            $q->where('booking_id', $booking->id);
        })
            ->with([
                'ticketAgent',
                'issuedTicket.passenger',
            ])
            ->orderBy('refund_date', 'asc')
            ->get();

        return response()->json($refundedTickets);
    }

    public function assignBranch(Request $request, Passenger $passenger)
    {
        $validated = $request->validate([
            'branch_id' => 'required|exists:branches,id',
        ]);

        if ((float) $passenger->refund_payable <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'Passenger has no refund payable balance.',
            ], 422);
        }

        if ($passenger->refund_payment_status !== null
            && $passenger->refund_payment_status !== RefundPaymentStatus::PENDING) {
            return response()->json([
                'success' => false,
                'message' => 'Refund payment is already in progress or completed.',
            ], 422);
        }

        $passenger->update([
            'refund_payment_status' => RefundPaymentStatus::PROCESSING,
            'refund_payment_branch_id' => $validated['branch_id'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Refund payment branch assigned successfully.',
        ]);
    }

    public function confirm(Request $request, Passenger $passenger)
    {
        $validated = $request->validate([
            'payment_method' => 'required|in:cash,bank',
            'remarks' => 'nullable|string|max:500',
        ]);

        if ($passenger->refund_payment_status !== RefundPaymentStatus::PROCESSING) {
            return response()->json([
                'success' => false,
                'message' => 'Passenger is not in processing status.',
            ], 422);
        }

        $booking = $passenger->booking;
        $invoice = $booking->invoice;
        $amount = (float) $passenger->refund_payable;

        if ($amount <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'Refund payable balance is zero.',
            ], 422);
        }

        if (! $invoice) {
            return response()->json([
                'success' => false,
                'message' => 'Booking has no invoice.',
            ], 422);
        }

        return DB::transaction(function () use ($passenger, $booking, $invoice, $amount, $validated) {
            $passenger = Passenger::lockForUpdate()->find($passenger->id);

            $transactionType = TransactionType::where('name', 'Ticket Refund - Payment')->first();

            if (! $transactionType) {
                throw new \RuntimeException('Transaction type "Ticket Refund - Payment" not found.');
            }

            $payment = Payment::create([
                'invoice_id' => $invoice->id,
                'booking_id' => $booking->id,
                'branch_id' => $passenger->refund_payment_branch_id,
                'user_id' => auth()->id(),
                'currency_rate_id' => $booking->currency_rate_id,
                'payment_date' => now(),
                'payment_method' => $validated['payment_method'],
                'amount' => $amount,
                'bdt_amount' => 0,
                'passenger_id' => $passenger->id,
                'remarks' => $validated['remarks'] ?? null,
            ]);

            $voucher = app(VoucherService::class)->createVoucher([
                'invoice_id' => $invoice->id,
                'booking_id' => $booking->id,
                'payment_id' => $payment->id,
                'branch_id' => $passenger->refund_payment_branch_id,
                'user_id' => auth()->id(),
                'currency_rate_id' => $booking->currency_rate_id,
                'transaction_type_id' => $transactionType->id,
                'payment_date' => now(),
                'payment_method' => $validated['payment_method'],
                'amount' => $amount,
                'bdt_amount' => 0,
                'notes' => $validated['remarks'] ?? null,
            ]);

            $passenger->decreaseRefundPayable($amount);

            $passenger->update([
                'refund_payment_status' => RefundPaymentStatus::PAID,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Refund payment processed successfully.',
                'data' => [
                    'payment_id' => $payment->id,
                    'voucher_id' => $voucher->id,
                    'amount' => $amount,
                ],
            ]);
        });
    }

    public function revert(Passenger $passenger)
    {
        if ($passenger->refund_payment_status !== RefundPaymentStatus::PROCESSING) {
            return response()->json([
                'success' => false,
                'message' => 'Passenger is not in processing status.',
            ], 422);
        }

        $passenger->update([
            'refund_payment_status' => RefundPaymentStatus::PENDING,
            'refund_payment_branch_id' => null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Refund payment reverted to pending.',
        ]);
    }
}
