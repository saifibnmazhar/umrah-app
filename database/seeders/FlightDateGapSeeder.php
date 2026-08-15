<?php

namespace Database\Seeders;

use App\Models\FlightDateGap;
use Illuminate\Database\Seeder;

class FlightDateGapSeeder extends Seeder
{
    public function run(): void
    {
        $gaps = [7, 10, 14, 30];
        foreach ($gaps as $gap) {
            FlightDateGap::firstOrCreate(['gap' => $gap]);
        }
    }
}
