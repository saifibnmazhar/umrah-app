<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Invoice;
use App\Models\TransactionType;
use App\Models\CurrencyRate;
use Illuminate\Support\Facades\DB;

class PaymentService
{
    public function __construct(
        private VoucherService $voucherService,
        private InvoiceService $invoiceService
    ) {}

    public function createCustomerPayment(Invoice $invoice, array $data): array
    {
        $processedData = $this->processCurrencyConversion($data);

        if (!$this->invoiceService->canAcceptPayment($invoice, $processedData['bdt_amount'])) {
            throw new \Exception('Payment exceeds invoice balance');
        }

        return DB::transaction(function () use ($invoice, $data, $processedData) {
            $payment = Payment::create([
                'invoice_id' => $invoice->id,
                'booking_id' => $invoice->booking_id,
                'branch_id' => $processedData['branch_id'],
                'user_id' => $processedData['user_id'],
                'payment_date' => $processedData['payment_date'],
                'payment_method' => $processedData['payment_method'],
                'amount' => $processedData['amount'],
                'bdt_amount' => $processedData['bdt_amount'],
                'bank_id' => $processedData['bank_id'] ?? null,
                'transaction_id' => $processedData['transaction_id'] ?? null,
                'currency_rate_id' => $processedData['currency_rate_id'] ?? null,
                'notes' => $processedData['notes'] ?? null,
            ]);

            $voucher = $this->voucherService->createVoucher([
                'invoice_id' => $invoice->id,
                'booking_id' => $invoice->booking_id,
                'payment_id' => $payment->id,
                'branch_id' => $processedData['branch_id'],
                'user_id' => $processedData['user_id'],
                'transaction_type_id' => $processedData['transaction_type_id'],
                'payment_date' => $processedData['payment_date'],
                'payment_method' => $processedData['payment_method'],
                'amount' => $processedData['amount'],
                'bdt_amount' => $processedData['bdt_amount'],
                'bank_id' => $processedData['bank_id'] ?? null,
                'transaction_id' => $processedData['transaction_id'] ?? null,
                'currency_rate_id' => $processedData['currency_rate_id'] ?? null,
                'notes' => $processedData['notes'] ?? null,
            ]);

            $this->invoiceService->updatePaymentStatus($invoice);

            return [$payment, $voucher];
        });
    }

    public function createAgentPayment(string $agentType, array $data): array
    {
        $processedData = $this->processCurrencyConversion($data);
        $agentIdField = $agentType . '_id';

        return DB::transaction(function () use ($agentType, $agentIdField, $data, $processedData) {
            $payment = Payment::create([
                'invoice_id' => null,
                'booking_id' => $processedData['booking_id'] ?? null,
                'branch_id' => $processedData['branch_id'],
                'user_id' => $processedData['user_id'],
                $agentIdField => $processedData[$agentIdField],
                'payment_date' => $processedData['payment_date'],
                'payment_method' => $processedData['payment_method'],
                'amount' => $processedData['amount'],
                'bdt_amount' => $processedData['bdt_amount'],
                'bank_id' => $processedData['bank_id'] ?? null,
                'transaction_id' => $processedData['transaction_id'] ?? null,
                'currency_rate_id' => $processedData['currency_rate_id'] ?? null,
                'notes' => $processedData['notes'] ?? null,
            ]);

            $voucher = $this->voucherService->createVoucher([
                'invoice_id' => null,
                'booking_id' => $processedData['booking_id'] ?? null,
                'payment_id' => $payment->id,
                'branch_id' => $processedData['branch_id'],
                'user_id' => $processedData['user_id'],
                $agentIdField => $processedData[$agentIdField],
                'transaction_type_id' => $processedData['transaction_type_id'],
                'payment_date' => $processedData['payment_date'],
                'payment_method' => $processedData['payment_method'],
                'amount' => $processedData['amount'],
                'bdt_amount' => $processedData['bdt_amount'],
                'bank_id' => $processedData['bank_id'] ?? null,
                'transaction_id' => $processedData['transaction_id'] ?? null,
                'currency_rate_id' => $processedData['currency_rate_id'] ?? null,
                'notes' => $processedData['notes'] ?? null,
            ]);

            return [$payment, $voucher];
        });
    }

    private function processCurrencyConversion(array $data): array
    {
        $currencyRateService = app(CurrencyRateService::class);
        $currentRate = $currencyRateService->getCurrentRate();
        $currency = $data['currency'] ?? 'SAR';

        $amount = (float) ($data['amount'] ?? 0);
        $bdtAmount = (float) ($data['bdt_amount'] ?? 0);

        if ($currency === 'SAR' && $amount > 0 && $bdtAmount === 0) {
            $data['bdt_amount'] = $currencyRateService->convertSarToBdt($amount);
            $data['currency_rate_id'] = $currentRate?->id;
        } elseif ($currency === 'BDT' && $bdtAmount > 0 && $amount === 0) {
            $data['amount'] = $currencyRateService->convertBdtToSar($bdtAmount);
            $data['currency_rate_id'] = $currentRate?->id;
        }

        return $data;
    }
}