<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Voucher;
use Illuminate\Validation\ValidationException;

class RefundCapService
{
    public function __construct(protected CurrencyRateService $currencyRates) {}

    public function getCap(Invoice $invoice): array
    {
        $voucherPaid = (float) Voucher::where('invoice_id', $invoice->id)
            ->whereHas('transactionType', fn ($q) => $q->whereIn('name', ['Initial Payment', 'Due Collection']))
            ->sum('amount');

        // Fall back to the invoice ledger when no payment vouchers exist
        // (e.g. legacy rows); otherwise the voucher comparison governs.
        $paid = $voucherPaid > 0 ? $voucherPaid : (float) $invoice->paid_amount;

        $refunded = (float) Voucher::where('invoice_id', $invoice->id)
            ->whereHas('transactionType', fn ($q) => $q->where('name', 'Customer Refund'))
            ->sum('amount');

        return [
            'paid' => $paid,
            'refunded' => $refunded,
            'remaining' => max(0, $paid - $refunded),
        ];
    }

    public function normalizeToSar(float $amount, ?string $currency = null): float
    {
        if (strtoupper((string) $currency) === 'BDT') {
            return (float) $this->currencyRates->convertBdtToSar($amount);
        }

        return $amount;
    }

    public function assertRefundAllowed(Invoice $invoice, float $requestedSar, string $field = 'refund_amount'): void
    {
        $cap = $this->getCap($invoice);

        if ($requestedSar - $cap['remaining'] > 0.000001) {
            throw ValidationException::withMessages([
                $field => "Customer refund {$requestedSar} exceeds remaining refundable amount {$cap['remaining']} (paid {$cap['paid']} − refunded {$cap['refunded']}).",
            ]);
        }
    }
}
