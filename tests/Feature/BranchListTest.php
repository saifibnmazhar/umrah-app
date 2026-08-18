<?php

namespace Tests\Feature;

use App\Livewire\Branch\BranchListTable;
use App\Models\Branch;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;
use Tests\TestCase;

class BranchListTest extends TestCase
{
    use RefreshDatabase;

    private ?User $user = null;

    private function setupUser(): User
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

    private function createBranch(string $name): Branch
    {
        return Branch::create([
            'name' => $name,
            'address' => 'Test Address',
            'contacts' => '01712345678',
            'location' => 'KSA',
            'fingerprint_operation' => false,
            'branch_code' => strtoupper(substr($name, 0, 3)),
        ]);
    }

    /** @test */
    public function test_branch_list_renders_as_livewire_component(): void
    {
        $this->setupUser();
        $branch = $this->createBranch('Riyadh Branch');

        Auth::login($this->user);

        $response = $this->get(route('branches.index'));
        $response->assertOk();
        $response->assertSee('wire:id', false);
        $response->assertSee('Branch Code');
        $response->assertSee($branch->name);
    }

    /** @test */
    public function test_branch_list_search_by_name(): void
    {
        $this->setupUser();
        $this->createBranch('Riyadh Branch');
        $this->createBranch('Jeddah Branch');
        $this->createBranch('Dammam Branch');

        Auth::login($this->user);

        Livewire::test(BranchListTable::class)
            ->set('search', 'Jeddah')
            ->assertSee('Jeddah Branch')
            ->assertDontSee('Riyadh Branch')
            ->assertDontSee('Dammam Branch');
    }

    /** @test */
    public function test_branch_list_search_by_branch_code(): void
    {
        $this->setupUser();
        $this->createBranch('Riyadh Branch');
        $this->createBranch('Jeddah Branch');

        Auth::login($this->user);

        Livewire::test(BranchListTable::class)
            ->set('search', 'JED')
            ->assertSee('Jeddah Branch')
            ->assertDontSee('Riyadh Branch');
    }
}
