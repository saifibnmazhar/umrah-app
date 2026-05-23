<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Booking;
use App\Enums\InvoiceStatus;

class InvoiceService
{
    public function createForBooking(Booking $booking): Invoice
    {
        return Invoice::create([
            'booking_id' => $booking->id,
            'branch_id' => $booking->branch_id,
            'user_id' => $booking->user_id,
            'total_amount' => $booking->total_value,
            'paid_amount' => 0,
            'balance' => $booking->total_value,
            'status' => InvoiceStatus::PENDING,
        ]);
    }

    public function updatePaymentStatus(Invoice $invoice): void
    {
        \Log::info('InvoiceService: Updating payment status for invoice ID: ' . $invoice->id);

        $invoice = $invoice->fresh();

        $invoice->paid_amount = $invoice->payments()->sum('amount');
        $invoice->balance = $invoice->total_amount - $invoice->paid_amount;

        \Log::info('InvoiceService: Paid amount calculated: ' . $invoice->paid_amount . ', Balance: ' . $invoice->balance);

        if ($invoice->balance <= 0) {
            $invoice->status = InvoiceStatus::PAID;
        } elseif ($invoice->paid_amount > 0) {
            $invoice->status = InvoiceStatus::PARTIAL;
        } else {
            $invoice->status = InvoiceStatus::PENDING;
        }

        $invoice->save();

        \Log::info('InvoiceService: Invoice updated successfully. Status: ' . $invoice->status->value);
    }

    public function canAcceptPayment(Invoice $invoice, float $amount): bool
    {
        return ($invoice->balance - $amount) >= 0;
    }

    public function calculateBalance(Invoice $invoice): float
    {
        return $invoice->total_amount - $invoice->paid_amount;
    }

    public function updateTotals(Invoice $invoice, float $newTotal): void
    {
        $invoice->total_amount = $newTotal;
        $invoice->balance = max(0, $newTotal - $invoice->paid_amount);

        $invoice->status = match (true) {
            $invoice->balance <= 0 => InvoiceStatus::PAID,
            $invoice->paid_amount > 0 => InvoiceStatus::PARTIAL,
            default => InvoiceStatus::PENDING,
        };

        $invoice->save();
    }
}