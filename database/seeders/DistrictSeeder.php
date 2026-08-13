<?php

namespace Database\Seeders;

use App\Models\District;
use Illuminate\Database\Seeder;

class DistrictSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        District::insert([
            ['name' => 'Dhaka', 'division' => 'Dhaka', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Chittagong', 'division' => 'Chittagong', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Khulna', 'division' => 'Khulna', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Rajshahi', 'division' => 'Rajshahi', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Sylhet', 'division' => 'Sylhet', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Barisal', 'division' => 'Barisal', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Rangpur', 'division' => 'Rangpur', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Comilla', 'division' => 'Chittagong', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Narayanganj', 'division' => 'Dhaka', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Savar', 'division' => 'Dhaka', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }
}
