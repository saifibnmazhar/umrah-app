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
            ['city_code' => 'DXB', 'city' => 'Dubai', 'country' => 'UAE', 'created_at' => now(), 'updated_at' => now()],
            ['city_code' => 'RUH', 'city' => 'Riyadh', 'country' => 'Saudi Arabia', 'created_at' => now(), 'updated_at' => now()],
            ['city_code' => 'JED', 'city' => 'Jeddah', 'country' => 'Saudi Arabia', 'created_at' => now(), 'updated_at' => now()],
            ['city_code' => 'DHA', 'city' => 'Dhaka', 'country' => 'Bangladesh', 'created_at' => now(), 'updated_at' => now()],
            ['city_code' => 'CGP', 'city' => 'Chittagong', 'country' => 'Bangladesh', 'created_at' => now(), 'updated_at' => now()],
            ['city_code' => 'DAC', 'city' => 'Dhaka', 'country' => 'Bangladesh', 'created_at' => now(), 'updated_at' => now()],
            ['city_code' => 'DOH', 'city' => 'Doha', 'country' => 'Qatar', 'created_at' => now(), 'updated_at' => now()],
            ['city_code' => 'AUH', 'city' => 'Abu Dhabi', 'country' => 'UAE', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }
}
