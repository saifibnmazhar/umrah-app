<?php

namespace Database\Seeders;

use App\Models\TravelClass;
use Illuminate\Database\Seeder;

class TravelClassSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        TravelClass::insert([
            ['name' => 'Economy', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Premium Economy', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Business', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'First', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }
}
