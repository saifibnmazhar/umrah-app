<?php

namespace App\Services;

use App\Enums\CancelledBookingStatus;
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

        $visaCost = $this->computeVisaCost($passenger);
        $visaBreakdown = $this->getVisaCostBreakdown($passenger);

        $ticketCost = $this->computeTicketCost($passenger);
        $ticketBreakdown = $this->getTicketCostBreakdown($passenger);

        $refundPayable = (float) $passenger->refund_payable;

        $branches = Branch::select('id', 'name')->orderBy('name')->get();

        return [
            'package_value' => $packageValue,
            'visa_cost' => $visaBreakdown,
            'ticket_cost' => $ticketBreakdown,
            'total_cost' => $visaCost + $ticketCost,
            'refund_payable' => $refundPayable,
            'refundable_amount' => max(0, $packageValue - $visaCost - $ticketCost + $refundPayable),
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
        $visaCost = $this->computeVisaCost($passenger);
        $ticketCost = $this->computeTicketCost($passenger);
        $serviceCharge = isset($data['service_charge_deduction']) ? (float) $data['service_charge_deduction'] : 0;
        $refundPayable = (float) $passenger->refund_payable;
        $refundable = max(0, $packageValue - $visaCost - $ticketCost - $serviceCharge + $refundPayable);

        return DB::transaction(function () use ($passenger, $booking, $data, $packageValue, $visaCost, $ticketCost, $serviceCharge, $refundable) {
            $holdStatus = PassengerStatus::firstOrCreate(['name' => 'Hold']);

            $cancelledPassenger = CancelledPassenger::create([
                'booking_id' => $booking->id,
                'passenger_id' => $passenger->id,
                'invoice_id' => $booking->invoice_id,
                'user_id' => auth()->id(),
                'package_value' => $packageValue,
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

            $pkg = (float) $cancelledPassenger->package_value;
            $refundable = (float) $cancelledPassenger->refundable_amount;
            $adjusted = (float) $data['balance_adjusted_amount'];
            $refund = max(0, $refundable - $adjusted);
            $serviceCharge = (float) ($cancelledPassenger->service_charge_deduction ?? 0);
            $currencyRateId = $booking->currency_rate_id;
            $paymentMethod = $data['payment_method'];
            $remarks = $data['remarks'] ?? null;

            // 1. Reduce totals
            $booking->update(['pax_qty' => $booking->pax_qty - 1]);
            $booking->update(['total_value' => (float) $booking->total_value - $pkg]);
            $invoice->update(['total_amount' => (float) $invoice->total_amount - $pkg]);

            // 2. Credit balance
            $newBalance = max(0, (float) $invoice->total_amount - (float) $invoice->paid_amount);
            $invoice->update(['balance' => max(0, $newBalance - $adjusted)]);

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

            $refundPaymentId = null;
            $refundVoucherId = null;

            // 4. Refund payment (customer payout)
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

            // 5. Reduce refund_payable
            $passenger->update([
                'refund_payable' => max(0, (float) $passenger->refund_payable - $refundable),
            ]);

            // 6. Set permanent status
            $cancelStatus = PassengerStatus::firstOrCreate(['name' => 'Cancel']);
            $passenger->update([
                'passenger_status_id' => $cancelStatus->id,
            ]);

            // 7. Update cancelled_passengers record
            $cancelledPassenger->update([
                'status' => CancelledBookingStatus::CANCELLED,
                'balance_adjusted_amount' => $adjusted,
                'refund_amount' => $refund,
                'confirmed_by_id' => auth()->id(),
                'deduction_payment_id' => $deductionPaymentId,
                'deduction_voucher_id' => $deductionVoucherId,
                'refund_payment_id' => $refundPaymentId,
                'refund_voucher_id' => $refundVoucherId,
            ]);

            // 8. Recompute invoice status
            $invoiceService = app(InvoiceService::class);
            $invoiceService->updatePaymentStatus($invoice);

            return $cancelledPassenger->fresh();
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
