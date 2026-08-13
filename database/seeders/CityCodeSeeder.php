<?php

namespace Database\Seeders;

use App\Models\CityCode;
use Illuminate\Database\Seeder;

class CityCodeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        CityCode::insert([
            ['city_name' => 'Dubai', 'code' => 'DXB', 'country' => 'UAE', 'created_at' => now(), 'updated_at' => now()],
            ['city_name' => 'Riyadh', 'code' => 'RUH', 'country' => 'Saudi Arabia', 'created_at' => now(), 'updated_at' => now()],
            ['city_name' => 'Jeddah', 'code' => 'JED', 'country' => 'Saudi Arabia', 'created_at' => now(), 'updated_at' => now()],
            ['city_name' => 'Dhaka', 'code' => 'DHA', 'country' => 'Bangladesh', 'created_at' => now(), 'updated_at' => now()],
            ['city_name' => 'Chittagong', 'code' => 'CGP', 'country' => 'Bangladesh', 'created_at' => now(), 'updated_at' => now()],
            ['city_name' => 'Dhaka', 'code' => 'DAC', 'country' => 'Bangladesh', 'created_at' => now(), 'updated_at' => now()],
            ['city_name' => 'Doha', 'code' => 'DOH', 'country' => 'Qatar', 'created_at' => now(), 'updated_at' => now()],
            ['city_name' => 'Abu Dhabi', 'code' => 'AUH', 'country' => 'UAE', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }
}
