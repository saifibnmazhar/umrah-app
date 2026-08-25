<?php

namespace App\Http\Controllers;

use App\Enums\PaymentMethod;
use App\Enums\TicketStatus;
use App\Enums\TicketType;
use App\Models\BaggageAllowance;
use App\Models\Booking;
use App\Models\IssuedTicket;
use App\Models\Passenger;
use App\Models\Payment;
use App\Models\RefundedTicket;
use App\Models\ReIssuedTicket;
use App\Models\ReIssueRefundReason;
use App\Models\TicketAgent;
use App\Models\TicketFare;
use App\Models\TicketRequest;
use App\Models\TransactionType;
use App\Services\InvoiceService;
use App\Services\VoucherService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TicketRequestController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'request_type' => 'required|in:re_issue,refund,additional',
            'booking_id' => 'required|exists:bookings,id',
            'passengers' => 'required|array|min:1',
            'passengers.*.passenger_id' => 'required|exists:passengers,id',
            'passengers.*.name' => 'nullable|string',
            'passengers.*.passport' => 'nullable|string',
            'passengers.*.tickets' => 'required_if:request_type,re_issue,refund|array|min:1',
            'passengers.*.tickets.*.issued_ticket_id' => 'required_if:request_type,re_issue,refund|nullable|exists:issued_tickets,id',
            'passengers.*.tickets.*.ticket_number' => 'nullable|string',
            'passengers.*.tickets.*.pnr' => 'nullable|string',
            'passengers.*.tickets.*.route' => 'nullable|string',
            'passengers.*.tickets.*.route_type' => 'nullable|string',
            'passengers.*.tickets.*.probable_date_up' => 'nullable|date',
            'passengers.*.tickets.*.probable_date_down' => 'nullable|date',
            'passengers.*.tickets.*.visa_expiry' => 'nullable|date',
            'passengers.*.ticket_option' => 'nullable|in:up,down,both',
            'passengers.*.probable_date_up' => 'nullable|date',
            'passengers.*.probable_date_down' => 'nullable|date',
            'passengers.*.visa_expiry' => 'nullable|date',
        ]);

        if (in_array($validated['request_type'], ['re_issue', 'refund'])) {
            $ticketIds = collect($validated['passengers'])
                ->flatMap(fn ($p) => collect($p['tickets'] ?? []))
                ->pluck('issued_ticket_id')
                ->filter()
                ->unique()
                ->values();

            if ($ticketIds->isNotEmpty() && TicketRequest::whereIn('issued_ticket_id', $ticketIds)
                ->where('status', 'pending')
                ->whereIn('request_type', ['re_issue', 'refund'])
                ->exists()) {
                return response()->json(['message' => 'A re-issue or refund request is already pending for one of the selected tickets.'], 422);
            }
        }

        $passengerIds = collect($validated['passengers'])->pluck('passenger_id');
        $restrictedCount = Passenger::whereIn('id', $passengerIds)
            ->whereHas('status', fn ($q) => $q->whereIn('name', ['Hold', 'Cancel']))
            ->count();

        if ($restrictedCount > 0) {
            return response()->json(['message' => 'Requests cannot be created for passengers with Hold or Cancel status.'], 422);
        }

        if ($validated['request_type'] === 'refund' && $ticketIds->isNotEmpty()) {
            $notRefundableIds = IssuedTicket::whereIn('id', $ticketIds)
                ->whereNotIn('status', [TicketStatus::ISSUED->value, TicketStatus::RE_ISSUED->value])
                ->pluck('id')
                ->all();

            if ($notRefundableIds) {
                return response()->json(['message' => 'One of the selected tickets is not eligible for refund.'], 422);
            }
        }

        $booking = Booking::findOrFail($validated['booking_id']);
        $userId = auth()->id();
        $branchId = auth()->user()->branch_id;

        $rows = [];

        if ($validated['request_type'] === 'additional') {
            foreach ($validated['passengers'] as $p) {
                $rows[] = [
                    'user_id' => $userId,
                    'request_branch_id' => $branchId,
                    'booking_id' => $booking->id,
                    'passenger_id' => $p['passenger_id'],
                    'issued_ticket_id' => null,
                    'request_type' => 'additional',
                    'status' => 'pending',
                    'ticket_option' => $p['ticket_option'] ?? null,
                    'probable_date_up' => $p['probable_date_up'] ?? null,
                    'probable_date_down' => $p['probable_date_down'] ?? null,
                    'visa_expiry_date' => $p['visa_expiry'] ?? null,
                    'requested_at' => now(),
                ];
            }
        } else {
            foreach ($validated['passengers'] as $p) {
                foreach ($p['tickets'] as $t) {
                    $rows[] = [
                        'user_id' => $userId,
                        'request_branch_id' => $branchId,
                        'booking_id' => $booking->id,
                        'passenger_id' => $p['passenger_id'],
                        'issued_ticket_id' => $t['issued_ticket_id'] ?? null,
                        'request_type' => $validated['request_type'],
                        'status' => 'pending',
                        'ticket_option' => null,
                        'probable_date_up' => $t['probable_date_up'] ?? null,
                        'probable_date_down' => $t['probable_date_down'] ?? null,
                        'visa_expiry_date' => $t['visa_expiry'] ?? null,
                        'remark' => null,
                        'requested_at' => now(),
                    ];
                }
            }
        }

        $created = TicketRequest::insert($rows);

        return response()->json([
            'success' => true,
            'message' => 'Request submitted successfully.',
            'booking_id' => $booking->id,
            'count' => $created,
        ]);
    }

    public function processReIssue(Request $request, TicketRequest $ticketRequest)
    {
        if ($ticketRequest->status !== 'pending') {
            return response()->json(['message' => 'This request has already been processed.'], 400);
        }

        $passenger = $ticketRequest->passenger;
        if ($passenger && ($passenger->isOnHold() || $passenger->isOnCancel())) {
            return response()->json(['message' => 'This request cannot be processed — the passenger has '.$passenger->status?->name.' status.'], 422);
        }

        $validated = $request->validate([
            'reason_id' => 'required|exists:re_issue_refund_reasons,id',
            're_issue_charge' => 'required|numeric|min:0',
            'fare_difference' => 'required|numeric',
            'other_costs' => 'required|numeric|min:0',
            'service_charge' => 'required|numeric|min:0',
            'total_customer_payment' => 'required_if:payment_by,customer|numeric|min:0',
            'remarks' => 'nullable|string',
            'payment_by' => 'nullable|in:customer,airline,employee,company',
            'payment_option' => 'nullable|required_if:payment_by,customer|in:customer_payment,refund_adjustment',
            'refund_adjustment_amount' => [
                Rule::requiredIf(function () use ($request) {
                    return $request->input('payment_by') === 'customer'
                        && $request->input('payment_option') === 'refund_adjustment';
                }),
                'numeric',
                'min:0',
            ],
            'travel_date' => 'nullable|date',
            'inbound_date' => 'nullable|date',
            'outbound_date' => 'nullable|date',
            'ticket_agent_id' => 'nullable|exists:ticket_agents,id',
            'ticket_fare_id' => 'required|exists:ticket_fares,id',
            'route_id' => 'nullable|exists:routes,id',
            'selling_fare' => 'nullable|numeric|min:0',
            'net_fare' => 'nullable|numeric|min:0',
            'offer_price' => 'nullable|numeric|min:0',
            'route' => 'nullable|string|max:255',
            'agent' => 'nullable|string|max:255',
            'payment_method' => 'nullable|string|max:255',
            'bank_method' => 'nullable|string|max:255',
            'branch' => 'nullable|string|max:255',
        ]);

        $issuedTicket = $ticketRequest->issuedTicket;
        if (! $issuedTicket) {
            return response()->json(['message' => 'Issued ticket not found.'], 404);
        }

        $wasRefunded = $issuedTicket->status === 'refunded';

        $selectedFare = TicketFare::findOrFail($validated['ticket_fare_id']);

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

            $reIssueData = [
                'user_id' => auth()->id(),
                'issued_ticket_id' => $issuedTicket->id,
                'ticket_number' => $issuedTicket->ticket_number,
                'pnr' => $issuedTicket->pnr,
                'ticket_agent_id' => $validated['ticket_agent_id'] ?? $issuedTicket->ticket_agent_id,
                'ticket_fare_id' => $selectedFare->id,
                'route_id' => $validated['route_id'] ?? null,
                'group_ticket_id' => $selectedFare->groupTicket?->id ?? $issuedTicket->group_ticket_id,
                're_issue_date' => $validated['travel_date'] ?? now(),
                'inbound_date' => $validated['inbound_date'] ?? $issuedTicket->inbound_date,
                'outbound_date' => $validated['outbound_date'] ?? $issuedTicket->outbound_date,
                'selling_fare' => $validated['selling_fare'] ?? $selectedFare->selling_fare ?? $issuedTicket->selling_fare ?? 0,
                'net_fare' => $validated['net_fare'] ?? $selectedFare->net_fare ?? $issuedTicket->net_fare ?? 0,
                'offer_price' => $validated['offer_price'] ?? $selectedFare->offer_price ?? $issuedTicket->offer_price ?? 0,
                'is_refundable' => $selectedFare->is_refundable ?? $issuedTicket->is_refundable,
                'is_exchangeable' => $selectedFare->is_exchangeable ?? $issuedTicket->is_exchangeable,
                'baggage_inbound' => BaggageAllowance::where('ticket_fare_id', $selectedFare->id)->where('passenger_type', $ticketRequest->passenger->passenger_type)->where('travel_direction', 'inbound')->value('allowance'),
                'baggage_outbound' => BaggageAllowance::where('ticket_fare_id', $selectedFare->id)->where('passenger_type', $ticketRequest->passenger->passenger_type)->where('travel_direction', 'outbound')->value('allowance'),
                're_issue_charge' => $validated['re_issue_charge'],
                'fare_difference' => $validated['fare_difference'],
                'other_costs' => $validated['other_costs'],
                'service_charge' => $validated['service_charge'],
                'reason_id' => $validated['reason_id'],
                'remarks' => $validated['remarks'] ?? null,
                'payment_by' => $validated['payment_by'] ?? null,
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
            ];

            $reIssuedTicket = ReIssuedTicket::create($reIssueData);
            $issuedTicket->update(['status' => 're-issued']);

            $newData = $reIssuedTicket->toArray();
            $newData['status'] = TicketStatus::RE_ISSUED->value;
            $newData['log_source'] = 're_issued_tickets';
            $newData['re_issued_ticket_id'] = $reIssuedTicket->id;

            $issuedTicket->logAction('re-issued', $oldData, $newData);

            $ticketRequest->update([
                'status' => 'processed',
                'processed_at' => now(),
                'result_re_issued_ticket_id' => $reIssuedTicket->id,
            ]);

            if (($validated['payment_by'] ?? null) === 'customer') {
                $refundedNetFare = $wasRefunded
                    ? (float) ($issuedTicket->latestRefundedTicket?->net_fare ?? $issuedTicket->net_fare ?? 0)
                    : 0;

                $totalCost = (float) $validated['re_issue_charge']
                    + (float) $validated['fare_difference']
                    + (float) $validated['other_costs']
                    + $refundedNetFare;

                $totalCustomerPayment = $totalCost + (float) $validated['service_charge'];

                $passenger = $ticketRequest->passenger;
                $booking = $ticketRequest->booking;

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
                            'branch_id' => $ticketRequest->request_branch_id ?? $booking->booking_branch_id,
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
                            'branch_id' => $ticketRequest->request_branch_id ?? $booking->booking_branch_id,
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

                    $remainingPayment = $totalCustomerPayment - $amount;
                    if ($remainingPayment > 0) {
                        $invoice = $booking->invoice;
                        if ($invoice) {
                            app(InvoiceService::class)->updateTotals(
                                $invoice,
                                (float) $invoice->total_amount + $remainingPayment,
                                're_issue_cost_added'
                            );
                        }
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

    public function processRefund(Request $request, TicketRequest $ticketRequest)
    {
        if ($ticketRequest->status !== 'pending') {
            return response()->json(['message' => 'This request has already been processed.'], 400);
        }

        $passenger = $ticketRequest->passenger;
        if ($passenger && ($passenger->isOnHold() || $passenger->isOnCancel())) {
            return response()->json(['message' => 'This request cannot be processed — the passenger has '.$passenger->status?->name.' status.'], 422);
        }

        $issuedTicket = $ticketRequest->issuedTicket;
        if (! $issuedTicket) {
            return response()->json(['message' => 'Issued ticket not found.'], 404);
        }

        $refundSource = ($issuedTicket->status === 're-issued' && $issuedTicket->latestReIssuedTicket)
            ? $issuedTicket->latestReIssuedTicket
            : $issuedTicket;
        $refundNetFare = (float) ($refundSource->net_fare ?? 0);

        $validated = $request->validate([
            'reason_id' => 'required|exists:re_issue_refund_reasons,id',
            'iata_refund' => 'required|numeric|min:0|max:'.$refundNetFare,
            'customer_refund' => 'required|numeric|min:0|max:'.$refundNetFare,
            'service_charge' => 'required|numeric',
            'remarks' => 'nullable|string',
            'payment_by' => 'nullable|in:customer,airline,employee,company',
            'payment_method' => 'nullable|string|max:255',
            'bank_method' => 'nullable|string|max:255',
            'branch' => 'nullable|string|max:255',
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

            $refundData = [
                'user_id' => auth()->id(),
                'issued_ticket_id' => $issuedTicket->id,
                'ticket_number' => $refundSource->ticket_number,
                'pnr' => $refundSource->pnr,
                'ticket_agent_id' => $refundSource->ticket_agent_id,
                'ticket_fare_id' => $refundSource->ticket_fare_id,
                'group_ticket_id' => $refundSource->group_ticket_id,
                'refund_date' => now(),
                'inbound_date' => $refundSource->inbound_date,
                'outbound_date' => $refundSource->outbound_date,
                'selling_fare' => $refundSource->selling_fare ?? 0,
                'net_fare' => $refundSource->net_fare ?? 0,
                'offer_price' => $refundSource->offer_price ?? 0,
                'is_refundable' => $refundSource->is_refundable,
                'is_exchangeable' => $refundSource->is_exchangeable,
                'baggage_inbound' => $refundSource->baggage_inbound,
                'baggage_outbound' => $refundSource->baggage_outbound,
                'iata_refunded_amount' => $validated['iata_refund'],
                'refund_to_customer' => $validated['customer_refund'],
                'service_charge' => $validated['service_charge'],
                'refund_compensation' => $refundNetFare - (float) $validated['iata_refund'],
                'reason_id' => $validated['reason_id'],
                'remarks' => $validated['remarks'] ?? null,
                'payment_by' => $validated['payment_by'] ?? null,
            ];

            $refundedTicket = RefundedTicket::create($refundData);
            $ticketRequest->passenger?->increaseRefundPayable((float) $validated['customer_refund']);
            $issuedTicket->update(['status' => 'refunded']);

            $newData = $refundedTicket->toArray();
            $newData['log_source'] = 'refunded_tickets';
            $newData['refunded_ticket_id'] = $refundedTicket->id;

            $issuedTicket->logAction('refunded', $oldData, $newData);

            $ticketRequest->update([
                'status' => 'processed',
                'processed_at' => now(),
                'result_refunded_ticket_id' => $refundedTicket->id,
            ]);

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

    public function processAdditional(Request $request, TicketRequest $ticketRequest)
    {
        if ($ticketRequest->status !== 'pending') {
            return response()->json(['message' => 'This request has already been processed.'], 400);
        }

        $passenger = $ticketRequest->passenger;
        if ($passenger && ($passenger->isOnHold() || $passenger->isOnCancel())) {
            return response()->json(['message' => 'This request cannot be processed — the passenger has '.$passenger->status?->name.' status.'], 422);
        }

        $validated = $request->validate([
            'ticket_number' => 'nullable|string|max:100',
            'pnr' => 'nullable|string|max:50',
            'ticket_agent_id' => 'nullable|exists:ticket_agents,id',
            'ticket_fare_id' => 'required|exists:ticket_fares,id',
            'issued_date' => 'nullable|date',
            'inbound_date' => 'nullable|date',
            'outbound_date' => 'nullable|date',
            'remarks' => 'nullable|string',
        ]);

        $passenger = $ticketRequest->passenger;
        if (! $passenger) {
            return response()->json(['message' => 'Passenger not found.'], 404);
        }

        $selectedFare = TicketFare::findOrFail($validated['ticket_fare_id']);

        try {
            DB::beginTransaction();

            $issuedTicket = IssuedTicket::create([
                'passenger_id' => $passenger->id,
                'booking_id' => $ticketRequest->booking_id,
                'user_id' => auth()->id(),
                'ticket_agent_id' => $validated['ticket_agent_id'] ?? null,
                'ticket_fare_id' => $selectedFare->id,
                'ticket_number' => $validated['ticket_number'] ?? null,
                'pnr' => $validated['pnr'] ?? null,
                'issued_date' => $validated['issued_date'] ?? now(),
                'inbound_date' => $validated['inbound_date'] ?? null,
                'outbound_date' => $validated['outbound_date'] ?? null,
                'selling_fare' => $selectedFare->selling_fare ?? 0,
                'net_fare' => $selectedFare->net_fare ?? 0,
                'offer_price' => $selectedFare->offer_price ?? 0,
                'is_refundable' => $selectedFare->is_refundable ?? false,
                'is_exchangeable' => $selectedFare->is_exchangeable ?? false,
                'baggage_inbound' => BaggageAllowance::where('ticket_fare_id', $selectedFare->id)->where('passenger_type', $passenger->passenger_type)->where('travel_direction', 'inbound')->value('allowance'),
                'baggage_outbound' => BaggageAllowance::where('ticket_fare_id', $selectedFare->id)->where('passenger_type', $passenger->passenger_type)->where('travel_direction', 'outbound')->value('allowance'),
                'issue_type' => 'additional',
                'status' => 'issued',
            ]);

            $passenger->update(['ticket_status' => 'issued']);

            $ticketRequest->update([
                'status' => 'processed',
                'processed_at' => now(),
                'result_issued_ticket_id' => $issuedTicket->id,
            ]);

            $invoice = $ticketRequest->booking->invoice;
            if ($invoice) {
                $ticketAmount = $selectedFare->ticket_type === TicketType::OFFER
                    ? (float) ($issuedTicket->offer_price ?: $issuedTicket->selling_fare ?? 0)
                    : (float) ($issuedTicket->selling_fare ?? 0);

                app(InvoiceService::class)->updateTotals(
                    $invoice,
                    (float) $invoice->total_amount + $ticketAmount,
                    'additional_ticket_added'
                );
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Additional ticket issued successfully.',
                'issued_ticket' => $issuedTicket,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Additional ticket issue failed: '.$e->getMessage());

            return response()->json(['message' => 'Failed to issue additional ticket.'], 500);
        }
    }

    public function reject(TicketRequest $ticketRequest)
    {
        if ($ticketRequest->status !== 'pending') {
            return response()->json(['message' => 'This request has already been processed.'], 400);
        }

        $ticketRequest->update([
            'status' => 'rejected',
            'rejected_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Request rejected.',
        ]);
    }

    public function byBooking(Booking $booking, Request $request)
    {
        $type = $request->query('type');
        $query = TicketRequest::where('booking_id', $booking->id);

        if ($type) {
            $query->where('request_type', $type);
        }

        $requests = $query->with([
            'passenger.ticketFare.route.fromCity',
            'passenger.ticketFare.route.toCity',
            'passenger.ticketFare.route.returnCity',
            'passenger.ticketFare.route.multiSegments.fromCity',
            'passenger.ticketFare.route.multiSegments.toCity',
            'passenger.ticketFare.airline',
            'passenger.ticketFare.airlineClass.class',
            'passenger.ticketFareInbound.route.fromCity',
            'passenger.ticketFareInbound.route.toCity',
            'passenger.ticketFareInbound.route.returnCity',
            'passenger.ticketFareInbound.route.multiSegments.fromCity',
            'passenger.ticketFareInbound.route.multiSegments.toCity',
            'passenger.ticketFareInbound.airline',
            'passenger.ticketFareInbound.airlineClass.class',
            'passenger.ticketFareOutbound.route.fromCity',
            'passenger.ticketFareOutbound.route.toCity',
            'passenger.ticketFareOutbound.route.returnCity',
            'passenger.ticketFareOutbound.route.multiSegments.fromCity',
            'passenger.ticketFareOutbound.route.multiSegments.toCity',
            'passenger.ticketFareOutbound.airline',
            'passenger.ticketFareOutbound.airlineClass.class',
            'booking.customer',
            'booking.bookingBranch',
            'issuedTicket.ticketFare.route.fromCity',
            'issuedTicket.ticketFare.route.toCity',
            'issuedTicket.ticketFare.route.returnCity',
            'issuedTicket.ticketFare.route.multiSegments.fromCity',
            'issuedTicket.ticketFare.route.multiSegments.toCity',
            'issuedTicket.ticketFare.airline',
            'issuedTicket.ticketFare.airlineClass.class',
            'issuedTicket.latestReIssuedTicket',
            'issuedTicket.latestRefundedTicket',
            'issuedTicket.latestReIssuedTicket.ticketAgent',
            'issuedTicket.latestReIssuedTicket.ticketFare.airline',
            'issuedTicket.latestReIssuedTicket.ticketFare.airlineClass.class',
            'issuedTicket.latestReIssuedTicket.ticketFare.route.fromCity',
            'issuedTicket.latestReIssuedTicket.ticketFare.route.toCity',
            'issuedTicket.latestReIssuedTicket.ticketFare.route.returnCity',
            'issuedTicket.latestReIssuedTicket.ticketFare.route.multiSegments.fromCity',
            'issuedTicket.latestReIssuedTicket.ticketFare.route.multiSegments.toCity',
        ])->get();

        $requests->each(fn ($r) => $r->passenger?->append([
            'route_display', 'flight_date_display', 'airline_display', 'class_display',
        ]));

        return response()->json($requests);
    }

    public function reasons(Request $request)
    {
        $type = $request->query('type');
        $query = ReIssueRefundReason::query();

        if ($type === 're_issue') {
            $query->where('reason_of', 're-issue');
        } elseif ($type === 'refund') {
            $query->where('reason_of', 'refund');
        }

        return response()->json($query->get(['id', 'name', 'default_payment_by']));
    }

    public function agents()
    {
        return response()->json(TicketAgent::orderBy('name')->get(['id', 'name']));
    }

    public function paymentMethods()
    {
        return response()->json(
            collect(PaymentMethod::cases())->map(fn ($case) => ['value' => $case->value, 'label' => ucfirst($case->value)])->values()
        );
    }

    public function ticketFares(Request $request)
    {
        $query = TicketFare::query();

        if ($routeType = $request->query('route_type')) {
            $query->whereHas('route', fn ($q) => $q->where('route_type', $routeType));
        }
        if ($ticketType = $request->query('ticket_type')) {
            $query->where('ticket_type', $ticketType);
        }
        if ($flightType = $request->query('flight_type')) {
            $query->whereHas('route', fn ($q) => $q->where('flight_type', $flightType));
        }

        $fares = $query->with([
            'route.fromCity', 'route.toCity', 'route.returnCity',
            'route.multiSegments.fromCity', 'route.multiSegments.toCity',
            'airline', 'airlineClass.class',
            'baggageAllowances',
        ])->get();

        return response()->json($fares);
    }

    public function additionalTicketsByBooking(Booking $booking)
    {
        $tickets = IssuedTicket::where('booking_id', $booking->id)
            ->where('issue_type', 'additional')
            ->with([
                'passenger',
                'ticketFare.route.fromCity',
                'ticketFare.route.toCity',
                'ticketFare.route.returnCity',
                'ticketFare.route.multiSegments.fromCity',
                'ticketFare.route.multiSegments.toCity',
                'ticketFare.airline',
                'ticketFare.airlineClass.class',
            ])
            ->orderBy('issued_date', 'asc')
            ->get();

        return response()->json($tickets);
    }
}
