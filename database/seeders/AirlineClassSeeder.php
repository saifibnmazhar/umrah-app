<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AirlineClassSeeder extends Seeder
{
    public function run(): void
    {
        // Seed the 'classes' table (base class names)
        $classes = [
            ['name' => 'Economy'],
            ['name' => 'Business'],
            ['name' => 'First'],
        ];

        foreach ($classes as $class) {
            DB::table('classes')->insertOrIgnore($class);
        }

        // Seed the 'airline_classes' pivot table (linking airlines to classes)
        $airlineIds = DB::table('airlines')->pluck('id')->all();
        $classIds = DB::table('classes')->pluck('id')->all();

        foreach ($airlineIds as $airlineId) {
            foreach ($classIds as $classId) {
                DB::table('airline_classes')->insertOrIgnore([
                    'airline_id' => $airlineId,
                    'class_id' => $classId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
}
