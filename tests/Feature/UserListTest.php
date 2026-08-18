<?php

namespace Tests\Feature;

use App\Livewire\User\UserListTable;
use App\Models\Branch;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;
use Tests\TestCase;

class UserListTest extends TestCase
{
    use RefreshDatabase;

    private ?User $user = null;

    private function setupSuperAdmin(): User
    {
        if ($this->user) {
            return $this->user;
        }
        $this->user = User::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'is_active' => true,
            'branch_id' => null,
        ]);
        $this->user->roles()->attach(Role::create(['name' => 'Super Admin']));

        return $this->user;
    }

    private function createUser(string $name, ?string $branch = null): User
    {
        $branchModel = $branch ? Branch::where('name', $branch)->first() : null;
        $user = User::create([
            'name' => $name,
            'email' => strtolower(str_replace(' ', '.', $name)).'@example.com',
            'password' => bcrypt('password'),
            'is_active' => true,
            'branch_id' => $branchModel?->id,
        ]);
        $role = Role::firstOrCreate(['name' => 'Ticket Staff']);
        $user->roles()->attach($role);

        return $user;
    }

    private function seedBranches(): void
    {
        Branch::create(['name' => 'Riyadh Branch', 'address' => 'Address', 'contacts' => '0123', 'location' => 'KSA', 'fingerprint_operation' => false, 'branch_code' => 'RJH']);
        Branch::create(['name' => 'Jeddah Branch', 'address' => 'Address', 'contacts' => '0123', 'location' => 'KSA', 'fingerprint_operation' => false, 'branch_code' => 'JED']);
    }

    /** @test */
    public function test_user_list_renders_as_livewire_component(): void
    {
        $this->setupSuperAdmin();
        $this->seedBranches();
        $this->createUser('John Doe', 'Riyadh Branch');

        Auth::login($this->user);

        $response = $this->get(route('users.index'));
        $response->assertOk();
        $response->assertSee('wire:id', false);
        $response->assertSee('Name');
        $response->assertSee('John Doe');
    }

    /** @test */
    public function test_user_list_search_by_name(): void
    {
        $this->setupSuperAdmin();
        $this->seedBranches();
        $this->createUser('John Doe', 'Riyadh Branch');
        $this->createUser('Jane Smith', 'Jeddah Branch');

        Auth::login($this->user);

        Livewire::test(UserListTable::class)
            ->set('search', 'John')
            ->assertSee('John Doe')
            ->assertDontSee('Jane Smith');
    }

    /** @test */
    public function test_user_list_search_by_branch_name(): void
    {
        $this->setupSuperAdmin();
        $this->seedBranches();
        $this->createUser('John Doe', 'Riyadh Branch');
        $this->createUser('Jane Smith', 'Jeddah Branch');

        Auth::login($this->user);

        Livewire::test(UserListTable::class)
            ->set('search', 'Jeddah')
            ->assertSee('Jane Smith')
            ->assertDontSee('John Doe');
    }

    /** @test */
    public function test_user_list_role_based_actions_visible(): void
    {
        $this->setupSuperAdmin();
        $this->seedBranches();
        $user = $this->createUser('John Doe', 'Riyadh Branch');

        Auth::login($this->user);

        Livewire::test(UserListTable::class, ['isSuperAdmin' => true])
            ->assertSee('Actions')
            ->assertSee('Edit')
            ->assertSee('Delete');
    }
}
