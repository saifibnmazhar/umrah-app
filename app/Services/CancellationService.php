<?php

namespace App\Services;

use App\Enums\CancelledBookingStatus;
use App\Enums\InvoiceStatus;
use App\Models\Booking;
use App\Models\CancelledBooking;
use App\Models\PassengerStatus;
use App\Models\Payment;
use App\Models\TransactionType;
use Illuminate\Support\Facades\DB;

class CancellationService
{
    protected CostTrackingService $costTrackingService;

    public function __construct(CostTrackingService $costTrackingService)
    {
        $this->costTrackingService = $costTrackingService;
    }

    public function initiateCancellation(Booking $booking, array $data): CancelledBooking
    {
        if ($booking->is_cancelled) {
            throw new \Exception('Booking is already cancelled.');
        }

        $invoice = $booking->invoice;
        $costSummary = $this->costTrackingService->getBookingCostSummary($booking);

        $totalPaid = (float) $invoice->paid_amount;
        $totalCost = $costSummary['total_cost'];
        $serviceCharge = isset($data['service_charge_deduction']) ? (float) $data['service_charge_deduction'] : null;
        $totalPassengerRefundable = $booking->getTotalPassengerRefundable();
        $rawRefund = max(0, $totalPaid - $totalCost - ($serviceCharge ?? 0) + $totalPassengerRefundable);
        $remaining = app(RefundCapService::class)->getCap($invoice)['remaining'];
        $refundAmount = min($rawRefund, $remaining);

        return DB::transaction(function () use ($booking, $invoice, $data, $totalPaid, $serviceCharge, $refundAmount, $totalPassengerRefundable) {
            $cancelledBooking = CancelledBooking::create([
                'booking_id' => $booking->id,
                'invoice_id' => $invoice->id,
                'user_id' => auth()->id(),
                'total_paid' => $totalPaid,
                'service_charge_deduction' => $serviceCharge,
                'refund_amount' => $refundAmount,
                'total_passenger_refundable' => $totalPassengerRefundable,
                'cancellation_branch_id' => $data['cancellation_branch_id'],
                'status' => CancelledBookingStatus::PROCESSING,
            ]);

            $booking->update(['is_cancelled' => true]);
            $invoice->audit_reason = 'booking_cancelled';
            $invoice->update(['status' => InvoiceStatus::CANCELLED]);

            $holdId = PassengerStatus::firstOrCreate(['name' => 'Hold'])->id;
            $cancelId = PassengerStatus::where('name', 'Cancel')->value('id');

            $snapshot = $booking->passengers()
                ->pluck('passenger_status_id', 'id')
                ->map(fn ($statusId) => $statusId)
                ->toArray();
            $cancelledBooking->update(['passenger_statuses_snapshot' => $snapshot]);

            $booking->passengers()
                ->where(fn ($q) => $q->whereNull('passenger_status_id')
                    ->orWhereNotIn('passenger_status_id', [$holdId, $cancelId]))
                ->each(fn ($passenger) => $passenger->update(['passenger_status_id' => $holdId]));

            return $cancelledBooking;
        });
    }

    public function revertCancellation(CancelledBooking $cancelledBooking): void
    {
        if ($cancelledBooking->status !== CancelledBookingStatus::PROCESSING) {
            throw new \Exception('Only processing cancellations can be reverted.');
        }

        DB::transaction(function () use ($cancelledBooking) {
            $booking = $cancelledBooking->booking;
            $invoice = $cancelledBooking->invoice;

            $snapshot = $cancelledBooking->passenger_statuses_snapshot ?? [];
            foreach ($booking->passengers as $passenger) {
                if (array_key_exists((string) $passenger->id, $snapshot)) {
                    $passenger->update(['passenger_status_id' => $snapshot[(string) $passenger->id]]);
                }
            }

            $booking->passengers()
                ->where('is_cancelled', false)
                ->each(fn ($passenger) => $passenger->update([
                    'refund_payable' => $passenger->verifyRefundPayable(),
                ]));

            $booking->update(['is_cancelled' => false]);

            $invoiceService = app(InvoiceService::class);
            $invoice->refresh();
            $invoiceService->updatePaymentStatus($invoice);

            $cancelledBooking->update([
                'reverted_by_id' => auth()->id(),
            ]);
            $cancelledBooking->delete();
        });
    }

    public function confirmCancellation(CancelledBooking $cancelledBooking, array $data): CancelledBooking
    {
        if ($cancelledBooking->status !== CancelledBookingStatus::PROCESSING) {
            throw new \Exception('Only processing cancellations can be confirmed.');
        }

        return DB::transaction(function () use ($cancelledBooking, $data) {
            $booking = $cancelledBooking->booking;
            $invoice = $cancelledBooking->invoice;

            $currencyRateId = $booking->currency_rate_id;
            $paymentMethod = $data['payment_method'];
            $remarks = $data['remarks'] ?? null;

            $deductionPaymentId = null;
            $deductionVoucherId = null;
            $serviceCharge = (float) $cancelledBooking->service_charge_deduction;

            if ($serviceCharge > 0) {
                $deductionType = TransactionType::where('name', 'Service Charge Deduction')->first();

                $deductionPayment = Payment::create([
                    'invoice_id' => $invoice->id,
                    'booking_id' => $booking->id,
                    'branch_id' => $cancelledBooking->cancellation_branch_id,
                    'user_id' => auth()->id(),
                    'currency_rate_id' => $currencyRateId,
                    'payment_date' => now(),
                    'payment_method' => $paymentMethod,
                    'amount' => $serviceCharge,
                    'bdt_amount' => 0,
                    'cancelled_booking_id' => $cancelledBooking->id,
                    'remarks' => $remarks,
                ]);

                $deductionVoucher = app(VoucherService::class)->createVoucher([
                    'invoice_id' => $invoice->id,
                    'booking_id' => $booking->id,
                    'payment_id' => $deductionPayment->id,
                    'branch_id' => $cancelledBooking->cancellation_branch_id,
                    'user_id' => auth()->id(),
                    'currency_rate_id' => $currencyRateId,
                    'transaction_type_id' => $deductionType->id,
                    'payment_date' => now(),
                    'payment_method' => $paymentMethod,
                    'amount' => $serviceCharge,
                    'bdt_amount' => 0,
                    'cancelled_booking_id' => $cancelledBooking->id,
                    'notes' => $remarks,
                ]);

                $deductionPaymentId = $deductionPayment->id;
                $deductionVoucherId = $deductionVoucher->id;
            }

            $refundAmount = app(RefundCapService::class)->normalizeToSar((float) $data['refund_amount'], $data['currency'] ?? null);
            $refundPaymentAmount = max(0, $refundAmount);
            $capInvoice = $invoice ?? $booking->invoice;
            if ($capInvoice) {
                app(RefundCapService::class)->assertRefundAllowed($capInvoice, $refundAmount);
            }
            $refundType = TransactionType::where('name', 'Customer Refund')->first();

            $refundPayment = Payment::create([
                'invoice_id' => $invoice->id,
                'booking_id' => $booking->id,
                'branch_id' => $cancelledBooking->cancellation_branch_id,
                'user_id' => auth()->id(),
                'currency_rate_id' => $currencyRateId,
                'payment_date' => now(),
                'payment_method' => $paymentMethod,
                'amount' => $refundPaymentAmount,
                'bdt_amount' => 0,
                'cancelled_booking_id' => $cancelledBooking->id,
                'remarks' => $remarks,
            ]);

            $refundVoucher = app(VoucherService::class)->createVoucher([
                'invoice_id' => $invoice->id,
                'booking_id' => $booking->id,
                'payment_id' => $refundPayment->id,
                'branch_id' => $cancelledBooking->cancellation_branch_id,
                'user_id' => auth()->id(),
                'currency_rate_id' => $currencyRateId,
                'transaction_type_id' => $refundType->id,
                'payment_date' => now(),
                'payment_method' => $paymentMethod,
                'amount' => $refundPaymentAmount,
                'bdt_amount' => 0,
                'cancelled_booking_id' => $cancelledBooking->id,
                'notes' => $remarks,
            ]);

            $booking->passengers()
                ->where('is_cancelled', false)
                ->where('refund_payable', '>', 0)
                ->update(['refund_payable' => 0]);

            $cancelId = PassengerStatus::firstOrCreate(['name' => 'Cancel'])->id;
            $now = now();
            $booking->passengers()->each(fn ($passenger) => $passenger->update([
                'passenger_status_id' => $cancelId,
                'is_cancelled' => true,
                'cancelled_at' => $now,
            ]));

            $cancelledBooking->update([
                'deduction_payment_id' => $deductionPaymentId,
                'deduction_voucher_id' => $deductionVoucherId,
                'refund_payment_id' => $refundPayment->id,
                'refund_voucher_id' => $refundVoucher->id,
                'refund_amount' => $refundAmount,
                'confirmed_by_id' => auth()->id(),
                'status' => CancelledBookingStatus::CANCELLED,
            ]);

            // Zero out profit for all passengers in this booking
            $booking->passengers()->update(['profit' => 0]);
            $booking->update(['profit' => 0]);

            $invoice->audit_reason = 'refund';
            $invoice->update([
                'status' => InvoiceStatus::REFUNDED,
                'balance' => 0,
            ]);

            return $cancelledBooking->fresh();
        });
    }

    public function getCostBreakdown(Booking $booking): array
    {
        $invoice = $booking->invoice;
        $costSummary = $this->costTrackingService->getBookingCostSummary($booking);

        return [
            'total_amount' => $invoice->total_amount,
            'total_paid' => $invoice->paid_amount,
            'balance' => $invoice->balance,
            'costs' => [
                'fingerprint_cost' => $costSummary['fingerprint_cost'],
                'visa_cost' => $costSummary['visa_cost'],
                'ticket_cost' => $costSummary['ticket_cost'],
                'total_cost' => $costSummary['total_cost'],
            ],
            'passenger_costs' => $costSummary['passengers'],
            'service_charge' => 0,
            'total_passenger_refundable' => $booking->getTotalPassengerRefundable(),
            'potential_refund' => $invoice->paid_amount - $costSummary['total_cost'] + $booking->getTotalPassengerRefundable(),
            'currency_rate_id' => $booking->currency_rate_id,
            'booking_branch_id' => $booking->booking_branch_id,
            'booking_branch_name' => $booking->bookingBranch?->name,
            'booking_location' => $booking->bookingBranch?->location,
        ];
    }
}
