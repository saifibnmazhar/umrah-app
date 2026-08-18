<?php

namespace App\Livewire\User;

use App\Models\User;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

class UserListTable extends Component
{
    use WithPagination;

    public string $search = '';

    #[Locked]
    public bool $isSuperAdmin = false;

    public function render()
    {
        return view('livewire.user.list-table', [
            'users' => $this->users,
        ]);
    }

    public function getUsersProperty()
    {
        return User::with(['branch', 'roles'])
            ->when($this->search, function ($q) {
                $q->where(function ($query) {
                    $query->where('name', 'like', "%{$this->search}%")
                        ->orWhere('email', 'like', "%{$this->search}%")
                        ->orWhereHas('branch', fn ($q) => $q->where('name', 'like', "%{$this->search}%"));
                });
            })
            ->orderBy('id')
            ->paginate(10);
    }

    public function resetSearch()
    {
        $this->search = '';
    }
}
