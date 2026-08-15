<?php

namespace Database\Seeders;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Models\Booking;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Database\Seeder;

class PaymentSeeder extends Seeder
{
    public function run(): void
    {
        $bookings = Booking::with('invoice')->get();

        if ($bookings->isEmpty()) {
            $this->command?->info('No bookings found. Run BookingSeeder first.');

            return;
        }

        foreach ($bookings as $booking) {
            $invoice = $booking->invoice;
            if (! $invoice) {
                continue;
            }

            // Only seed a payment if the invoice still has a balance > 0
            if ($invoice->balance > 0) {
                $amount = $invoice->balance;

                $payment = Payment::create([
                    'invoice_id' => $invoice->id,
                    'booking_id' => $booking->id,
                    'branch_id' => $booking->booking_branch_id ?? $booking->fingerprint_branch_id,
                    'user_id' => $booking->user_id,
                    'payment_method' => PaymentMethod::CASH,
                    'amount' => $amount,
                    'bdt_amount' => $amount * 25, // approximate SAR to BDT conversion
                    'currency' => 'BDT',
                    'currency_rate_id' => $booking->currency_rate_id,
                    'payment_date' => now(),
                    'notes' => 'Sample balance payment for booking #'.$booking->id,
                ]);

                // Set a transaction id now that the payment is saved
                $payment->update([
                    'transaction_id' => 'TXN-'.str_pad($payment->id, 6, '0', STR_PAD_LEFT),
                ]);

                // Update invoice status after payment
                $invoice->paid_amount += $amount;
                $invoice->balance -= $amount;

                if ($invoice->balance <= 0) {
                    $invoice->status = InvoiceStatus::PAID;
                    $invoice->balance = 0;
                } else {
                    $invoice->status = InvoiceStatus::PARTIAL;
                }
                $invoice->save();
            }
        }
    }
}
