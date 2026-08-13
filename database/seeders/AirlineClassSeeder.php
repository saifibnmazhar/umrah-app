<?php

namespace Database\Seeders;

use App\Models\Airline;
use App\Models\TravelClass;
use Illuminate\Database\Seeder;

class AirlineClassSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get the class IDs array
        $classIds = TravelClass::pluck('id')->toArray();

        foreach (Airline::all() as $airline) {
            // Use sync which handles duplicates gracefully
            $airline->travelClasses()->sync(
                array_fill_keys($classIds, []),
                false // Don't detach existing
            );
        }
    }
}
