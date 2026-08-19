<?php

namespace App\Livewire\Payment;

use App\Models\Payment;
use App\Models\TransactionType;
use Livewire\Component;
use Livewire\WithPagination;

class PaymentListTable extends Component
{
    use WithPagination;

    public string $transactionTypeFilter = '';

    public function render()
    {
        return view('livewire.payment.list-table', [
            'payments' => $this->payments,
            'transactionTypes' => $this->transactionTypes,
        ]);
    }

    public function getPaymentsProperty()
    {
        $typeNames = ['Ticket Agent Payment', 'Visa Agent Payment', 'Commission Agent Payment'];

        $query = Payment::with(['user', 'bank', 'senderBank', 'voucher.transactionType'])
            ->whereHas('voucher.transactionType', function ($q) use ($typeNames) {
                $q->whereIn('name', $typeNames);
            })
            ->orderBy('created_at', 'desc');

        if ($this->transactionTypeFilter) {
            $query->whereHas('voucher', function ($q) {
                $q->where('transaction_type_id', $this->transactionTypeFilter);
            });
        }

        return $query->paginate(10);
    }

    public function getTransactionTypesProperty()
    {
        return TransactionType::whereIn('name', ['Ticket Agent Payment', 'Visa Agent Payment', 'Commission Agent Payment'])
            ->orderBy('name')
            ->get();
    }
}
