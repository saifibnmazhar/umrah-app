<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Branch;
use App\Models\CancelledBooking;
use App\Models\CancelledPassenger;
use App\Models\Customer;
use App\Models\Passenger;
use App\Models\Payment;
use App\Models\User;
use App\Models\Voucher;
use Tests\TestCase;

class VoucherTransactionIdBlankTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(new User(['name' => 'Tester']));
    }

    private function makeTicketPayment(?string $transactionId): Payment
    {
        $payment = new Payment;
        $payment->forceFill([
            'transaction_id' => $transactionId,
            'payment_method' => 'cash',
            'amount' => 1500,
        ]);
        $payment->created_at = now();

        $voucher = new Voucher;
        $voucher->forceFill(['voucher_id' => 'V-1']);
        $voucher->payment_date = now();
        $payment->setRelation('voucher', $voucher);

        $booking = new Booking;
        $booking->forceFill(['invoice_id' => 'INV-1']);
        $customer = new Customer;
        $customer->forceFill(['name' => 'Test Customer', 'mobile_no' => '0123456789', 'iqama_no' => 'IQ-1']);
        $booking->setRelation('customer', $customer);
        $payment->setRelation('booking', $booking);

        $passenger = new Passenger;
        $passenger->forceFill(['first_name' => 'John', 'last_name' => 'Doe']);
        $payment->setRelation('passenger', $passenger);

        $branch = new Branch;
        $branch->forceFill(['name' => 'Main Branch']);
        $payment->setRelation('branch', $branch);

        $payment->setRelation('user', new User(['name' => 'Cashier']));

        return $payment;
    }

    private function refundPaymentStub(?string $transactionId): Payment
    {
        $payment = new Payment;
        $payment->forceFill([
            'transaction_id' => $transactionId,
            'payment_method' => 'bank',
        ]);

        return $payment;
    }

    private function makeCancelledBooking(?string $transactionId): CancelledBooking
    {
        $cb = new CancelledBooking;
        $cb->forceFill(['total_paid' => 2500, 'service_charge_deduction' => 300, 'refund_amount' => 1200]);
        $cb->created_at = now();

        $booking = new Booking;
        $booking->forceFill(['invoice_id' => 'INV-2']);
        $customer = new Customer;
        $customer->forceFill(['name' => 'Test Customer', 'mobile_no' => '0123456789', 'iqama_no' => 'IQ-1']);
        $booking->setRelation('customer', $customer);
        $cb->setRelation('booking', $booking);

        $cb->setRelation('refundPayment', $this->refundPaymentStub($transactionId));

        return $cb;
    }

    private function makeCancelledPassenger(?string $transactionId): CancelledPassenger
    {
        $cp = new CancelledPassenger;
        $cp->forceFill(['refund_amount' => 800]);
        $cp->created_at = now();

        $booking = new Booking;
        $booking->forceFill(['invoice_id' => 'INV-3']);
        $customer = new Customer;
        $customer->forceFill(['name' => 'Test Customer', 'mobile_no' => '0123456789', 'iqama_no' => 'IQ-1']);
        $booking->setRelation('customer', $customer);
        $cp->setRelation('booking', $booking);

        $cp->setRelation('refundPayment', $this->refundPaymentStub($transactionId));

        return $cp;
    }

    private function assertTransactionIdBlank(string $html): void
    {
        $this->assertMatchesRegularExpression(
            '/Transaction ID:<\/span>\s*<span class="blank"/',
            $html
        );
    }

    private function assertTransactionIdFilled(string $html, string $transactionId): void
    {
        $this->assertStringContainsString($transactionId, $html);
        $this->assertDoesNotMatchRegularExpression(
            '/Transaction ID:<\/span>\s*<span class="blank"/',
            $html
        );
    }

    public function test_all_vouchers_style_blank_line_inside_info_rows(): void
    {
        $renders = [
            view('ticket-refund-payments.print-voucher', [
                'payment' => $this->makeTicketPayment(null),
                'currencyRate' => 0,
            ])->render(),
            view('cancelled-bookings.print-voucher', [
                'cancelledBooking' => $this->makeCancelledBooking(null),
            ])->render(),
            view('cancelled-passengers.print-voucher', [
                'cancelledPassenger' => $this->makeCancelledPassenger(null),
            ])->render(),
        ];

        foreach ($renders as $html) {
            $this->assertStringContainsString('.info-row .blank', $html);
            $this->assertStringContainsString('border-bottom: 1px dashed', $html);
        }
    }

    public function test_ticket_refund_voucher_shows_blank_line_when_no_transaction_id(): void
    {
        $html = view('ticket-refund-payments.print-voucher', [
            'payment' => $this->makeTicketPayment(null),
            'currencyRate' => 0,
        ])->render();

        $this->assertTransactionIdBlank($html);
    }

    public function test_ticket_refund_voucher_shows_transaction_id_when_present(): void
    {
        $html = view('ticket-refund-payments.print-voucher', [
            'payment' => $this->makeTicketPayment('TRX-999'),
            'currencyRate' => 0,
        ])->render();

        $this->assertTransactionIdFilled($html, 'TRX-999');
    }

    public function test_cancelled_booking_voucher_shows_blank_line_when_no_transaction_id(): void
    {
        $html = view('cancelled-bookings.print-voucher', [
            'cancelledBooking' => $this->makeCancelledBooking(null),
        ])->render();

        $this->assertTransactionIdBlank($html);
    }

    public function test_cancelled_booking_voucher_shows_transaction_id_when_present(): void
    {
        $html = view('cancelled-bookings.print-voucher', [
            'cancelledBooking' => $this->makeCancelledBooking('TRX-111'),
        ])->render();

        $this->assertTransactionIdFilled($html, 'TRX-111');
    }

    public function test_cancelled_passenger_voucher_shows_blank_line_when_no_transaction_id(): void
    {
        $html = view('cancelled-passengers.print-voucher', [
            'cancelledPassenger' => $this->makeCancelledPassenger(null),
        ])->render();

        $this->assertTransactionIdBlank($html);
    }

    public function test_cancelled_passenger_voucher_shows_transaction_id_when_present(): void
    {
        $html = view('cancelled-passengers.print-voucher', [
            'cancelledPassenger' => $this->makeCancelledPassenger('TRX-222'),
        ])->render();

        $this->assertTransactionIdFilled($html, 'TRX-222');
    }
}
