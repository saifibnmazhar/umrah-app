<?php

namespace Database\Seeders;

use App\Models\FlightDateGap;
use Illuminate\Database\Seeder;

class FlightDateGapSeeder extends Seeder
{
    public function run(): void
    {
        FlightDateGap::firstOrCreate(['gap' => 30]);
    }
}
