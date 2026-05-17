<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Invoice;
use App\Models\TransactionType;
use Illuminate\Support\Facades\DB;

class PaymentService
{
    public function __construct(
        private VoucherService $voucherService,
        private InvoiceService $invoiceService
    ) {}

    public function createCustomerPayment(Invoice $invoice, array $data): array
    {
        if (!$this->invoiceService->canAcceptPayment($invoice, $data['bdt_amount'])) {
            throw new \Exception('Payment exceeds invoice balance');
        }

        return DB::transaction(function () use ($invoice, $data) {
            $payment = Payment::create([
                'invoice_id' => $invoice->id,
                'booking_id' => $invoice->booking_id,
                'branch_id' => $data['branch_id'],
                'user_id' => $data['user_id'],
                'payment_date' => $data['payment_date'],
                'payment_method' => $data['payment_method'],
                'amount' => $data['amount'],
                'bdt_amount' => $data['bdt_amount'],
                'bank_id' => $data['bank_id'] ?? null,
                'transaction_id' => $data['transaction_id'] ?? null,
                'currency_rate_id' => $data['currency_rate_id'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            $transactionType = TransactionType::find($data['transaction_type_id']);

            $voucher = $this->voucherService->createVoucher([
                'invoice_id' => $invoice->id,
                'booking_id' => $invoice->booking_id,
                'payment_id' => $payment->id,
                'branch_id' => $data['branch_id'],
                'user_id' => $data['user_id'],
                'transaction_type_id' => $data['transaction_type_id'],
                'payment_date' => $data['payment_date'],
                'payment_method' => $data['payment_method'],
                'amount' => $data['amount'],
                'bdt_amount' => $data['bdt_amount'],
                'bank_id' => $data['bank_id'] ?? null,
                'transaction_id' => $data['transaction_id'] ?? null,
                'currency_rate_id' => $data['currency_rate_id'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            $this->invoiceService->updatePaymentStatus($invoice);

            return [$payment, $voucher];
        });
    }

    public function createAgentPayment(string $agentType, array $data): array
    {
        $agentIdField = $agentType . '_id';

        return DB::transaction(function () use ($agentType, $agentIdField, $data) {
            $payment = Payment::create([
                'invoice_id' => null,
                'booking_id' => $data['booking_id'] ?? null,
                'branch_id' => $data['branch_id'],
                'user_id' => $data['user_id'],
                $agentIdField => $data[$agentIdField],
                'payment_date' => $data['payment_date'],
                'payment_method' => $data['payment_method'],
                'amount' => $data['amount'],
                'bdt_amount' => $data['bdt_amount'],
                'bank_id' => $data['bank_id'] ?? null,
                'transaction_id' => $data['transaction_id'] ?? null,
                'currency_rate_id' => $data['currency_rate_id'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            $voucher = $this->voucherService->createVoucher([
                'invoice_id' => null,
                'booking_id' => $data['booking_id'] ?? null,
                'payment_id' => $payment->id,
                'branch_id' => $data['branch_id'],
                'user_id' => $data['user_id'],
                $agentIdField => $data[$agentIdField],
                'transaction_type_id' => $data['transaction_type_id'],
                'payment_date' => $data['payment_date'],
                'payment_method' => $data['payment_method'],
                'amount' => $data['amount'],
                'bdt_amount' => $data['bdt_amount'],
                'bank_id' => $data['bank_id'] ?? null,
                'transaction_id' => $data['transaction_id'] ?? null,
                'currency_rate_id' => $data['currency_rate_id'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            return [$payment, $voucher];
        });
    }
}