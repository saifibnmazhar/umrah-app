<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AirlineSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('airlines')->insert([
            [
                'name' => 'Saudi Arabian Airlines',
                'code' => 'SV',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Biman Bangladesh Airlines',
                'code' => 'BG',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Emirates',
                'code' => 'EK',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
