<?php

namespace App\Services;

use App\Enums\CancelledBookingStatus;
use App\Enums\TicketType;
use App\Models\Branch;
use App\Models\CancelledPassenger;
use App\Models\Passenger;
use App\Models\PassengerStatus;
use App\Models\Payment;
use App\Models\TransactionType;
use Illuminate\Support\Facades\DB;

class PassengerCancellationService
{
    public function getCancellationPreview(Passenger $passenger): array
    {
        $packageValue = (float) $passenger->package_value;
        $additionalTicketValue = $this->computeAdditionalTicketValue($passenger);
        $totalPassengerDue = $packageValue + $additionalTicketValue;

        $visaCost = $this->computeVisaCost($passenger);
        $visaBreakdown = $this->getVisaCostBreakdown($passenger);

        $ticketCost = $this->computeTicketCost($passenger);
        $ticketBreakdown = $this->getTicketCostBreakdown($passenger);

        $refundPayable = (float) $passenger->refund_payable;

        $branches = Branch::select('id', 'name')->orderBy('name')->get();

        return [
            'package_value' => $packageValue,
            'additional_ticket_value' => $additionalTicketValue,
            'total_passenger_due' => $totalPassengerDue,
            'visa_cost' => $visaBreakdown,
            'ticket_cost' => $ticketBreakdown,
            'total_cost' => $visaCost + $ticketCost,
            'refund_payable' => $refundPayable,
            'refundable_amount' => max(0, $totalPassengerDue - $visaCost - $ticketCost + $refundPayable),
            'branches' => $branches,
        ];
    }

    public function initiateCancellation(Passenger $passenger, array $data): CancelledPassenger
    {
        $booking = $passenger->booking;

        if ($passenger->is_cancelled) {
            throw new \Exception('Passenger is already cancelled.');
        }

        $existingCancellation = CancelledPassenger::where('passenger_id', $passenger->id)
            ->where('booking_id', $booking->id)
            ->whereNull('deleted_at')
            ->exists();

        if ($existingCancellation) {
            throw new \Exception('Passenger already has an active cancellation.');
        }

        if ($booking->is_cancelled) {
            throw new \Exception('Booking is already cancelled.');
        }

        $activePassengers = $booking->passengers()->where('is_cancelled', false)->count();

        if ($activePassengers < 2) {
            throw new \Exception('Cannot cancel the last active passenger. Use whole-booking cancellation instead.');
        }

        $packageValue = (float) $passenger->package_value;
        $additionalTicketValue = $this->computeAdditionalTicketValue($passenger);
        $totalPassengerDue = $packageValue + $additionalTicketValue;
        $visaCost = $this->computeVisaCost($passenger);
        $ticketCost = $this->computeTicketCost($passenger);
        $serviceCharge = isset($data['service_charge_deduction']) ? (float) $data['service_charge_deduction'] : 0;
        $refundPayable = (float) $passenger->refund_payable;
        $refundable = max(0, $totalPassengerDue - $visaCost - $ticketCost - $serviceCharge + $refundPayable);

        return DB::transaction(function () use ($passenger, $booking, $data, $packageValue, $additionalTicketValue, $totalPassengerDue, $visaCost, $ticketCost, $serviceCharge, $refundable) {
            $holdStatus = PassengerStatus::firstOrCreate(['name' => 'Hold']);

            // bookings.invoice_id stores the formatted invoice number (string),
            // not the numeric invoices.id required by this FK column.
            $invoiceId = $booking->invoice?->id;

            if ($invoiceId === null) {
                throw new \Exception('Booking has no invoice record.');
            }

            $cancelledPassenger = CancelledPassenger::create([
                'booking_id' => $booking->id,
                'passenger_id' => $passenger->id,
                'invoice_id' => $invoiceId,
                'user_id' => auth()->id(),
                'package_value' => $packageValue,
                'additional_ticket_value' => $additionalTicketValue,
                'total_passenger_due' => $totalPassengerDue,
                'visa_cost' => $visaCost,
                'ticket_cost' => $ticketCost,
                'service_charge_deduction' => $serviceCharge > 0 ? $serviceCharge : null,
                'refundable_amount' => $refundable,
                'cancellation_branch_id' => $data['cancellation_branch_id'],
                'status' => CancelledBookingStatus::PROCESSING,
            ]);

            $passenger->update([
                'passenger_status_id' => $holdStatus->id,
                'is_cancelled' => true,
                'cancelled_at' => now(),
            ]);

            return $cancelledPassenger;
        });
    }

    public function revertCancellation(CancelledPassenger $cancelledPassenger): void
    {
        if ($cancelledPassenger->status !== CancelledBookingStatus::PROCESSING) {
            throw new \Exception('Only processing cancellations can be reverted.');
        }

        DB::transaction(function () use ($cancelledPassenger) {
            $passenger = $cancelledPassenger->passenger;

            $cancelledPassenger->update([
                'reverted_by_id' => auth()->id(),
            ]);
            $cancelledPassenger->delete();

            $passenger->update([
                'is_cancelled' => false,
                'cancelled_at' => null,
                'passenger_status_id' => null,
            ]);
            $passenger->syncComputedStatus();
        });
    }

    public function confirmCancellation(CancelledPassenger $cancelledPassenger, array $data): CancelledPassenger
    {
        if ($cancelledPassenger->status !== CancelledBookingStatus::PROCESSING) {
            throw new \Exception('Only processing cancellations can be confirmed.');
        }

        return DB::transaction(function () use ($cancelledPassenger, $data) {
            $booking = $cancelledPassenger->booking;
            $invoice = $cancelledPassenger->invoice;
            $passenger = $cancelledPassenger->passenger;

            $refundable = (float) $cancelledPassenger->refundable_amount;
            $adjusted = app(RefundCapService::class)->normalizeToSar((float) $data['balance_adjusted_amount'], $data['currency'] ?? null);
            $refund = max(0, $refundable - $adjusted);
            $capInvoice = $invoice ?? $booking->invoice;
            if ($capInvoice) {
                app(RefundCapService::class)->assertRefundAllowed($capInvoice, $refund, 'balance_adjusted_amount');
            }
            $serviceCharge = (float) ($cancelledPassenger->service_charge_deduction ?? 0);
            $currencyRateId = $booking->currency_rate_id;
            $paymentMethod = $data['payment_method'];
            $remarks = $data['remarks'] ?? null;

            // 1. Reduce seat count only. Totals stay untouched: cancellation
            // settles via credit payments, never by rewriting amounts.
            $booking->update(['pax_qty' => $booking->pax_qty - 1]);

            $deductionPaymentId = null;
            $deductionVoucherId = null;

            // 3. Deduction payment (service charge)
            if ($serviceCharge > 0) {
                $deductionType = TransactionType::where('name', 'Service Charge Deduction')->first();

                $deductionPayment = Payment::create([
                    'invoice_id' => $invoice->id,
                    'booking_id' => $booking->id,
                    'branch_id' => $cancelledPassenger->cancellation_branch_id,
                    'user_id' => auth()->id(),
                    'currency_rate_id' => $currencyRateId,
                    'payment_date' => now(),
                    'payment_method' => $paymentMethod,
                    'amount' => $serviceCharge,
                    'bdt_amount' => 0,
                    'cancelled_passenger_id' => $cancelledPassenger->id,
                    'remarks' => $remarks,
                ]);

                $deductionVoucher = app(VoucherService::class)->createVoucher([
                    'invoice_id' => $invoice->id,
                    'booking_id' => $booking->id,
                    'payment_id' => $deductionPayment->id,
                    'branch_id' => $cancelledPassenger->cancellation_branch_id,
                    'user_id' => auth()->id(),
                    'currency_rate_id' => $currencyRateId,
                    'transaction_type_id' => $deductionType->id,
                    'payment_date' => now(),
                    'payment_method' => $paymentMethod,
                    'amount' => $serviceCharge,
                    'bdt_amount' => 0,
                    'cancelled_passenger_id' => $cancelledPassenger->id,
                    'notes' => $remarks,
                ]);

                $deductionPaymentId = $deductionPayment->id;
                $deductionVoucherId = $deductionVoucher->id;
            }

            $adjustmentPaymentId = null;
            $adjustmentVoucherId = null;

            // 4. Due Adjustment settlement (credit against the invoice due)
            if ($adjusted > 0) {
                $adjustmentType = TransactionType::where('name', 'Due Adjustment')->first();

                $adjustmentPayment = Payment::create([
                    'invoice_id' => $invoice->id,
                    'booking_id' => $booking->id,
                    'branch_id' => $cancelledPassenger->cancellation_branch_id,
                    'user_id' => auth()->id(),
                    'currency_rate_id' => $currencyRateId,
                    'payment_date' => now(),
                    'payment_method' => $paymentMethod,
                    'amount' => $adjusted,
                    'bdt_amount' => 0,
                    'cancelled_passenger_id' => $cancelledPassenger->id,
                    'remarks' => $remarks,
                ]);

                $adjustmentVoucher = app(VoucherService::class)->createVoucher([
                    'invoice_id' => $invoice->id,
                    'booking_id' => $booking->id,
                    'payment_id' => $adjustmentPayment->id,
                    'branch_id' => $cancelledPassenger->cancellation_branch_id,
                    'user_id' => auth()->id(),
                    'currency_rate_id' => $currencyRateId,
                    'transaction_type_id' => $adjustmentType->id,
                    'payment_date' => now(),
                    'payment_method' => $paymentMethod,
                    'amount' => $adjusted,
                    'bdt_amount' => 0,
                    'cancelled_passenger_id' => $cancelledPassenger->id,
                    'notes' => $remarks,
                ]);

                $adjustmentPaymentId = $adjustmentPayment->id;
                $adjustmentVoucherId = $adjustmentVoucher->id;
            }

            $refundPaymentId = null;
            $refundVoucherId = null;

            // 5. Refund payment (customer payout)
            if ($refund > 0) {
                $refundType = TransactionType::where('name', 'Customer Refund')->first();

                $refundPayment = Payment::create([
                    'invoice_id' => $invoice->id,
                    'booking_id' => $booking->id,
                    'branch_id' => $cancelledPassenger->cancellation_branch_id,
                    'user_id' => auth()->id(),
                    'currency_rate_id' => $currencyRateId,
                    'payment_date' => now(),
                    'payment_method' => $paymentMethod,
                    'amount' => $refund,
                    'bdt_amount' => 0,
                    'cancelled_passenger_id' => $cancelledPassenger->id,
                    'remarks' => $remarks,
                ]);

                $refundVoucher = app(VoucherService::class)->createVoucher([
                    'invoice_id' => $invoice->id,
                    'booking_id' => $booking->id,
                    'payment_id' => $refundPayment->id,
                    'branch_id' => $cancelledPassenger->cancellation_branch_id,
                    'user_id' => auth()->id(),
                    'currency_rate_id' => $currencyRateId,
                    'transaction_type_id' => $refundType->id,
                    'payment_date' => now(),
                    'payment_method' => $paymentMethod,
                    'amount' => $refund,
                    'bdt_amount' => 0,
                    'cancelled_passenger_id' => $cancelledPassenger->id,
                    'notes' => $remarks,
                ]);

                $refundPaymentId = $refundPayment->id;
                $refundVoucherId = $refundVoucher->id;
            }

            // 6. Reduce refund_payable
            $passenger->update([
                'refund_payable' => max(0, (float) $passenger->refund_payable - $refundable),
            ]);

            // 7. Set permanent status
            $cancelStatus = PassengerStatus::firstOrCreate(['name' => 'Cancel']);
            $passenger->update([
                'passenger_status_id' => $cancelStatus->id,
            ]);

            // 8. Update cancelled_passengers record
            $cancelledPassenger->update([
                'status' => CancelledBookingStatus::CANCELLED,
                'balance_adjusted_amount' => $adjusted,
                'refund_amount' => $refund,
                'confirmed_by_id' => auth()->id(),
                'deduction_payment_id' => $deductionPaymentId,
                'deduction_voucher_id' => $deductionVoucherId,
                'adjustment_payment_id' => $adjustmentPaymentId,
                'adjustment_voucher_id' => $adjustmentVoucherId,
                'refund_payment_id' => $refundPaymentId,
                'refund_voucher_id' => $refundVoucherId,
            ]);

            // 9. Recompute invoice status
            $invoice->audit_reason = $adjusted > 0
                ? 'passenger_cancellation_due_adjustment'
                : 'passenger_cancellation_refund';
            $invoiceService = app(InvoiceService::class);
            $invoiceService->updatePaymentStatus($invoice);

            return $cancelledPassenger->fresh();
        });
    }

    private function computeAdditionalTicketValue(Passenger $passenger): float
    {
        return $passenger->allIssuedTickets
            ->filter(fn ($t) => $t->issue_type === 'additional')
            ->filter(fn ($t) => in_array($t->status, ['issued', 're-issued', 'refunded']))
            ->sum(function ($ticket) {
                $isOffer = $ticket->ticketFare?->ticket_type === TicketType::OFFER;

                // Mirrors the value added to the invoice when the additional
                // ticket was issued: offer price for offer fares, selling fare
                // otherwise.
                return (float) ($isOffer ? ($ticket->offer_price ?: ($ticket->selling_fare ?? 0)) : ($ticket->selling_fare ?? 0));
            });
    }

    private function computeVisaCost(Passenger $passenger): float
    {
        $visa = $passenger->visaSubmission;
        if (! $visa) {
            return 0;
        }

        $netVisaCost = (float) ($visa->net_visa_cost ?? 0);
        $agentCommission = (float) ($visa->agent_commission ?? 0);
        $additionalCost = (float) ($visa->additional_cost ?? 0);
        $cancellationFee = (float) ($visa->cancelledSubmission?->cancellation_fee ?? 0);

        return $netVisaCost + $agentCommission + $additionalCost + $cancellationFee;
    }

    private function computeTicketCost(Passenger $passenger): float
    {
        return $passenger->allIssuedTickets
            ->filter(fn ($t) => in_array($t->status, ['issued', 're-issued', 'refunded']))
            ->sum(function ($ticket) {
                return match ($ticket->status) {
                    'issued' => (float) $ticket->net_fare,
                    're-issued' => (float) $ticket->latestReIssuedTicket?->net_fare ?? 0,
                    'refunded' => (float) $ticket->latestRefundedTicket?->net_fare ?? 0,
                };
            });
    }

    private function getVisaCostBreakdown(Passenger $passenger): array
    {
        $visa = $passenger->visaSubmission;
        if (! $visa) {
            return [
                'net_visa_cost' => 0,
                'agent_commission' => 0,
                'additional_cost' => 0,
                'cancellation_fee' => 0,
                'total' => 0,
            ];
        }

        $netVisaCost = (float) ($visa->net_visa_cost ?? 0);
        $agentCommission = (float) ($visa->agent_commission ?? 0);
        $additionalCost = (float) ($visa->additional_cost ?? 0);
        $cancellationFee = (float) ($visa->cancelledSubmission?->cancellation_fee ?? 0);

        return [
            'net_visa_cost' => $netVisaCost,
            'agent_commission' => $agentCommission,
            'additional_cost' => $additionalCost,
            'cancellation_fee' => $cancellationFee,
            'total' => $netVisaCost + $agentCommission + $additionalCost + $cancellationFee,
        ];
    }

    private function getTicketCostBreakdown(Passenger $passenger): array
    {
        $tickets = $passenger->allIssuedTickets
            ->filter(fn ($t) => in_array($t->status, ['issued', 're-issued', 'refunded']))
            ->map(fn ($ticket) => [
                'ticket_number' => $ticket->ticket_number,
                'status' => $ticket->status,
                'net_fare' => match ($ticket->status) {
                    'issued' => (float) $ticket->net_fare,
                    're-issued' => (float) $ticket->latestReIssuedTicket?->net_fare ?? 0,
                    'refunded' => (float) $ticket->latestRefundedTicket?->net_fare ?? 0,
                    default => 0,
                },
            ]);

        return [
            'tickets' => $tickets->values()->all(),
            'total' => $tickets->sum('net_fare'),
        ];
    }
}
