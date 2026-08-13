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
            ['name' => 'Dhaka', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Chittagong', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Khulna', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Rajshahi', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Sylhet', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Barisal', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Rangpur', 'created_at' => now(), 'updated_at' => now()],
            ['name' => '.Comilla', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Narayanganj', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Savar', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }
}
