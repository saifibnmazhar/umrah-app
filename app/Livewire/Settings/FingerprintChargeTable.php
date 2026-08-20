<?php

namespace App\Livewire\Settings;

use App\Livewire\BaseListTable;
use App\Models\District;
use App\Models\FingerprintCharge;

class FingerprintChargeTable extends BaseListTable
{
    public string $divisionFilter = '';

    public function render()
    {
        return view('livewire.settings.fingerprint-charge-table', [
            'fingerprintCharges' => $this->fingerprintCharges,
            'divisions' => $this->divisions,
        ]);
    }

    public function getFingerprintChargesProperty()
    {
        return FingerprintCharge::with(['district', 'user'])
            ->when($this->divisionFilter, function ($q) {
                $q->whereHas('district', fn ($q) => $q->where('division', $this->divisionFilter));
            })
            ->orderBy('id')
            ->paginate(10);
    }

    public function getDivisionsProperty()
    {
        return District::distinct()->pluck('division')->sort();
    }
}
