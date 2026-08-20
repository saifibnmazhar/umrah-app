<?php

namespace App\Livewire\Branch;

use App\Livewire\BaseListTable;
use App\Models\Branch;
use Livewire\Attributes\On;

class BranchListTable extends BaseListTable
{
    #[On('refresh')]
    public function refresh(): void
    {
        // Re-render is handled automatically by Livewire after method execution.
    }

    public function render()
    {
        return view('livewire.branch.list-table', [
            'branches' => $this->branches,
        ]);
    }

    public function getBranchesProperty()
    {
        return $this->applySearch(Branch::query(), ['name', 'branch_code', 'location'])
            ->orderBy('name')
            ->paginate(10);
    }
}
