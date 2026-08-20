<?php

namespace App\Livewire\Branch;

use App\Models\Branch;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

class BranchListTable extends Component
{
    use WithPagination;

    public string $search = '';

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
        return Branch::when($this->search, function ($query) {
            $query->where(function ($q) {
                $q->where('name', 'like', '%'.$this->search.'%')
                    ->orWhere('branch_code', 'like', '%'.$this->search.'%')
                    ->orWhere('location', 'like', '%'.$this->search.'%');
            });
        })->orderBy('name')->paginate(10);
    }

    public function resetSearch()
    {
        $this->search = '';
    }
}
