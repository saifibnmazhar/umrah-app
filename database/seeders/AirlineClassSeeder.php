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
        $airlines = Airline::all();
        $classes = TravelClass::all();

        foreach ($airlines as $airline) {
            foreach ($classes as $class) {
                // Attach all airlines with all classes
                $airline->travelClasses()->attach($class->id);
            }
        }
    }
}
