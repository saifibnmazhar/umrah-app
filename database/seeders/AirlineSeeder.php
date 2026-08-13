<?php

namespace Database\Seeders;

use App\Models\Airline;
use Illuminate\Database\Seeder;

class AirlineSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Airline::insert([
            [
                'name' => 'Saudi Arabian Airlines',
                'code' => 'SV',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Emirates SkyCargo',
                'code' => 'EK',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Etihad Airways',
                'code' => 'EY',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Gulf Air',
                'code' => 'GF',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Qatar Airways',
                'code' => 'QR',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
