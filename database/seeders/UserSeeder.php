<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin',
                'password' => 'password',
                'is_active' => true,
            ]
        );

        $superAdminRole = Role::where('name', 'Super Admin')->first();

        if ($superAdminRole && !$admin->roles()->where('role_id', $superAdminRole->id)->exists()) {
            $admin->roles()->attach($superAdminRole->id);
        }
    }
}
