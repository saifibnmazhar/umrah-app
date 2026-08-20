<?php

namespace App\Livewire\User;

use App\Livewire\BaseListTable;
use App\Models\User;
use Livewire\Attributes\Locked;

class UserListTable extends BaseListTable
{
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
}
