<?php

namespace App\Livewire\Fingerprint;

use App\Livewire\BaseListTable;
use App\Models\District;
use App\Models\FingerprintCharge;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;

class FingerprintChargeList extends BaseListTable
{
    public Collection $divisions;

    public ?string $divisionFilter = null;

    public ?string $search = null;

    public int $perPage = 10;

    #[On('refresh')]
    public function refresh(): void
    {
        // Re-render is handled automatically by Livewire after method execution.
    }

    public function boot()
    {
        $this->divisions = District::distinct()->pluck('division')->sort();
    }

    public function updatedDivisionFilter()
    {
        $this->resetPage();
    }

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function clearFilter()
    {
        $this->divisionFilter = null;
        $this->search = null;
        $this->resetPage();
    }

    public function render()
    {
        $query = FingerprintCharge::with(['district', 'user']);

        if ($this->divisionFilter) {
            $query->whereHas('district', fn ($q) => $q->where('division', $this->divisionFilter));
        }

        if ($this->search) {
            $query->where('fingerprint_charge', 'like', '%'.$this->search.'%')
                ->orWhereHas('district', fn ($q) => $q->where('name', 'like', '%'.$this->search.'%'));
        }

        $fingerprintCharges = $query->orderBy('id')->paginate($this->perPage);

        $isAdmin = Auth::user()->roles->whereIn('name', ['Super Admin', 'Co Admin'])->isNotEmpty();

        return view('livewire.fingerprint.charge-list', [
            'fingerprintCharges' => $fingerprintCharges,
            'isAdmin' => $isAdmin,
        ]);
    }
}
