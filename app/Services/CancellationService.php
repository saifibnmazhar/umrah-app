<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\CancelledBooking;
use App\Models\Payment;
use App\Models\Voucher;
use App\Models\TransactionType;
use App\Enums\CancelledBookingStatus;
use App\Enums\InvoiceStatus;
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
        $refundAmount = $totalPaid - $totalCost - ($serviceCharge ?? 0);

        return DB::transaction(function () use ($booking, $invoice, $data, $totalPaid, $serviceCharge, $refundAmount) {
            $cancelledBooking = CancelledBooking::create([
                'booking_id'              => $booking->id,
                'invoice_id'              => $invoice->id,
                'user_id'                 => auth()->id(),
                'total_paid'              => $totalPaid,
                'service_charge_deduction' => $serviceCharge,
                'refund_amount'           => $refundAmount,
                'cancellation_branch_id'  => $data['cancellation_branch_id'],
                'status'                  => CancelledBookingStatus::PROCESSING,
            ]);

            $booking->update(['is_cancelled' => true]);
            $invoice->audit_reason = 'booking_cancelled';
            $invoice->update(['status' => InvoiceStatus::CANCELLED]);

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

            $booking->update(['is_cancelled' => false]);

            $invoiceService = app(InvoiceService::class);
            $invoice->refresh();
            $invoiceService->updatePaymentStatus($invoice);

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
                    'invoice_id'           => $invoice->id,
                    'booking_id'           => $booking->id,
                    'branch_id'            => $cancelledBooking->cancellation_branch_id,
                    'user_id'              => auth()->id(),
                    'currency_rate_id'     => $currencyRateId,
                    'payment_date'         => now(),
                    'payment_method'       => $paymentMethod,
                    'amount'               => $serviceCharge,
                    'bdt_amount'           => 0,
                    'cancelled_booking_id' => $cancelledBooking->id,
                    'remarks'              => $remarks,
                ]);

                $deductionVoucher = app(\App\Services\VoucherService::class)->createVoucher([
                    'invoice_id'           => $invoice->id,
                    'booking_id'           => $booking->id,
                    'payment_id'           => $deductionPayment->id,
                    'branch_id'            => $cancelledBooking->cancellation_branch_id,
                    'user_id'              => auth()->id(),
                    'currency_rate_id'     => $currencyRateId,
                    'transaction_type_id'  => $deductionType->id,
                    'payment_date'         => now(),
                    'payment_method'       => $paymentMethod,
                    'amount'               => $serviceCharge,
                    'bdt_amount'           => 0,
                    'cancelled_booking_id' => $cancelledBooking->id,
                    'notes'                => $remarks,
                ]);

                $deductionPaymentId = $deductionPayment->id;
                $deductionVoucherId = $deductionVoucher->id;
            }

            $refundAmount = (float) $data['refund_amount'];
            $refundType = TransactionType::where('name', 'Customer Refund')->first();

            $refundPayment = Payment::create([
                'invoice_id'           => $invoice->id,
                'booking_id'           => $booking->id,
                'branch_id'            => $cancelledBooking->cancellation_branch_id,
                'user_id'              => auth()->id(),
                'currency_rate_id'     => $currencyRateId,
                'payment_date'         => now(),
                'payment_method'       => $paymentMethod,
                'amount'               => $refundAmount,
                'bdt_amount'           => 0,
                'cancelled_booking_id' => $cancelledBooking->id,
                'remarks'              => $remarks,
            ]);

            $refundVoucher = app(\App\Services\VoucherService::class)->createVoucher([
                'invoice_id'           => $invoice->id,
                'booking_id'           => $booking->id,
                'payment_id'           => $refundPayment->id,
                'branch_id'            => $cancelledBooking->cancellation_branch_id,
                'user_id'              => auth()->id(),
                'currency_rate_id'     => $currencyRateId,
                'transaction_type_id'  => $refundType->id,
                'payment_date'         => now(),
                'payment_method'       => $paymentMethod,
                'amount'               => $refundAmount,
                'bdt_amount'           => 0,
                'cancelled_booking_id' => $cancelledBooking->id,
                'notes'                => $remarks,
            ]);

            $cancelledBooking->update([
                'deduction_payment_id' => $deductionPaymentId,
                'deduction_voucher_id' => $deductionVoucherId,
                'refund_payment_id'    => $refundPayment->id,
                'refund_voucher_id'    => $refundVoucher->id,
                'refund_amount'        => $refundAmount,
                'status'               => CancelledBookingStatus::CANCELLED,
            ]);

            $invoice->audit_reason = 'refund';
            $invoice->update([
                'status'  => InvoiceStatus::REFUNDED,
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
            'total_amount'       => $invoice->total_amount,
            'total_paid'         => $invoice->paid_amount,
            'balance'            => $invoice->balance,
            'costs'              => [
                'fingerprint_cost' => $costSummary['fingerprint_cost'],
                'visa_cost'        => $costSummary['visa_cost'],
                'ticket_cost'      => $costSummary['ticket_cost'],
                'total_cost'       => $costSummary['total_cost'],
            ],
            'passenger_costs'    => $costSummary['passengers'],
            'service_charge'     => 0,
            'potential_refund'   => $invoice->paid_amount - $costSummary['total_cost'],
            'currency_rate_id'   => $booking->currency_rate_id,
            'booking_branch_id'  => $booking->booking_branch_id,
            'booking_branch_name'=> $booking->bookingBranch?->name,
            'booking_location'   => $booking->bookingBranch?->location,
        ];
    }
}
