<?php

namespace App\Observers;

use App\Models\Invoice;
use App\Models\InvoiceUpdateLog;
use Illuminate\Support\Facades\Auth;

class InvoiceObserver
{
    public function created(Invoice $invoice): void
    {
        InvoiceUpdateLog::create([
            'invoice_id' => $invoice->id,
            'user_id' => Auth::id(),
            'booking_invoice_id' => $invoice->booking?->invoice_id,
            'action' => 'created',
            'reason' => $invoice->audit_reason ?? 'created',
            'old_values' => null,
            'new_values' => $invoice->attributesToArray(),
        ]);
    }

    public function updated(Invoice $invoice): void
    {
        $dirty = $invoice->getDirty();
        if (empty($dirty)) {
            return;
        }

        $original = $invoice->getOriginal();
        $old = $new = [];
        foreach ($dirty as $key => $value) {
            $old[$key] = $original[$key] ?? null;
            $new[$key] = $value;
        }

        InvoiceUpdateLog::create([
            'invoice_id' => $invoice->id,
            'user_id' => Auth::id(),
            'booking_invoice_id' => $invoice->booking?->invoice_id,
            'action' => 'updated',
            'reason' => $invoice->audit_reason ?? 'updated',
            'old_values' => $old,
            'new_values' => $new,
        ]);
    }

    public function deleting(Invoice $invoice): void
    {
        InvoiceUpdateLog::create([
            'invoice_id' => $invoice->id,
            'user_id' => Auth::id(),
            'booking_invoice_id' => $invoice->booking?->invoice_id,
            'action' => 'deleted',
            'reason' => 'deleted',
            'old_values' => collect($invoice->attributesToArray())->except(['created_at', 'updated_at'])->toArray(),
            'new_values' => null,
        ]);
    }
}
