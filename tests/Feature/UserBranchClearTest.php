<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserBranchClearTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Branch $branch;

    private Role $branchRole;

    private Role $centralRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::create([
            'name' => 'Main Branch',
            'address' => 'Addr',
            'contacts' => '0123456789',
            'location' => 'KSA',
            'fingerprint_operation' => true,
            'branch_code' => 'MAIN01',
        ]);

        $this->admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin-'.uniqid().'@example.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $this->admin->roles()->attach(Role::create(['name' => 'Super Admin']));

        $this->branchRole = Role::create(['name' => 'Branch Staff']);
        $this->centralRole = Role::create(['name' => 'Co Admin']);

        $this->actingAs($this->admin);
    }

    public function test_update_clears_branch_when_switching_to_non_branch_role_without_branch_key(): void
    {
        $user = User::create([
            'name' => 'Branch User',
            'email' => 'branch-'.uniqid().'@example.com',
            'password' => bcrypt('password'),
            'is_active' => true,
            'branch_id' => $this->branch->id,
        ]);
        $user->roles()->attach($this->branchRole);

        // Simulate the fixed frontend: hidden input submits empty branch_id
        // when the branch field is hidden (role without branch).
        $response = $this->put(route('users.update', $user), [
            'name' => 'Branch User',
            'email' => $user->email,
            'role_id' => $this->centralRole->id,
            'branch_id' => '',
        ]);

        $response->assertRedirect(route('users.index'));
        $this->assertNull($user->fresh()->branch_id);
    }

    public function test_update_clears_branch_when_branch_key_missing_entirely(): void
    {
        $user = User::create([
            'name' => 'Branch User',
            'email' => 'branch-'.uniqid().'@example.com',
            'password' => bcrypt('password'),
            'is_active' => true,
            'branch_id' => $this->branch->id,
        ]);
        $user->roles()->attach($this->branchRole);

        // Old buggy frontend (x-if removes select) sends no branch_id key at all.
        // Backend must still clear the stale value.
        $response = $this->put(route('users.update', $user), [
            'name' => 'Branch User',
            'email' => $user->email,
            'role_id' => $this->centralRole->id,
        ]);

        $response->assertRedirect(route('users.index'));
        $this->assertNull($user->fresh()->branch_id);
    }

    public function test_store_forces_null_branch_for_non_branch_role(): void
    {
        $response = $this->post(route('users.store'), [
            'name' => 'Central User',
            'email' => 'central-'.uniqid().'@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role_id' => $this->centralRole->id,
            'branch_id' => $this->branch->id,
        ]);

        $response->assertRedirect(route('users.index'));
        $created = User::where('email', 'like', 'central-%')->latest('id')->first();
        // Fallback lookup in case email filter is fragile
        if (! $created) {
            $created = User::latest('id')->first();
        }
        $this->assertNull($created->branch_id);
    }
}
