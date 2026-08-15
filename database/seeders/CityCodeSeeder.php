<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CityCodeSeeder extends Seeder
{
    public function run(): void
    {
        $cities = [
            ['city_name' => 'Dhaka', 'code' => 'DAC', 'country' => 'BD'],
            ['city_name' => 'Chittagong', 'code' => 'CGP', 'country' => 'BD'],
            ['city_name' => 'Riyadh', 'code' => 'RUH', 'country' => 'SA'],
            ['city_name' => 'Jeddah', 'code' => 'JED', 'country' => 'SA'],
            ['city_name' => 'Dammam', 'code' => 'DMM', 'country' => 'SA'],
            ['city_name' => 'Kuwait City', 'code' => 'KUW', 'country' => 'KW'],
            ['city_name' => 'Muscat', 'code' => 'MCT', 'country' => 'OM'],
            ['city_name' => 'Dubai', 'code' => 'DXB', 'country' => 'AE'],
        ];

        foreach ($cities as $city) {
            DB::table('city_codes')->insertOrIgnore($city);
        }
    }
}
