<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['name' => 'Super Admin'],
            ['name' => 'Co Admin'],
            ['name' => 'Auditor'],
            ['name' => 'Ticket Admin'],
            ['name' => 'Visa Admin'],
            ['name' => 'Ticket Staff'],
            ['name' => 'Visa Staff'],
            ['name' => 'Fingerprint Admin'],
            ['name' => 'Fingerprint Staff'],
            ['name' => 'Branch Manager'],
            ['name' => 'Branch Staff'],
            ['name' => 'Delivery Staff'],
        ];

        foreach ($roles as $role) {
            Role::updateOrCreate(['name' => $role['name']], $role);
        }
    }
}
