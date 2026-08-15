<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DistrictSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('districts')->insert([
            [
                'name' => 'Dhaka',
                'division' => 'Dhaka',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Chittagong',
                'division' => 'Chittagong',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
