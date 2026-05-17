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
        $invoice->paid_amount = $invoice->payments()->sum('bdt_amount');
        $invoice->balance = $invoice->total_amount - $invoice->paid_amount;

        if ($invoice->balance <= 0) {
            $invoice->status = InvoiceStatus::PAID;
        } elseif ($invoice->paid_amount > 0) {
            $invoice->status = InvoiceStatus::PARTIAL;
        } else {
            $invoice->status = InvoiceStatus::PENDING;
        }

        $invoice->save();
    }

    public function canAcceptPayment(Invoice $invoice, float $amount): bool
    {
        return ($invoice->balance - $amount) >= 0;
    }

    public function calculateBalance(Invoice $invoice): float
    {
        return $invoice->total_amount - $invoice->paid_amount;
    }
}