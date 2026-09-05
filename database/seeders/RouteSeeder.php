<?php

namespace Database\Seeders;

use App\Enums\FlightType;
use App\Enums\RouteType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RouteSeeder extends Seeder
{
    public function run(): void
    {
        // Resolve airline IDs by code (may already exist or be inserted by AirlineSeeder)
        $sv = DB::table('airlines')->where('code', 'SV')->latest('id')->value('id');
        $bg = DB::table('airlines')->where('code', 'BG')->latest('id')->value('id');

        // Resolve city IDs from city_codes
        $dhaka = DB::table('city_codes')->where('city_name', 'Dhaka')->value('id');
        $riyadh = DB::table('city_codes')->where('city_name', 'Riyadh')->value('id');
        $jeddah = DB::table('city_codes')->where('city_name', 'Jeddah')->value('id');

        // Only insert routes that don't already exist for these airlines on these city pairs
        $existingRoutes = DB::table('routes')
            ->whereIn('airline_id', [$sv, $bg])
            ->whereIn('from_city_id', [$dhaka, $jeddah])
            ->pluck('id')
            ->toArray();

        if (empty($existingRoutes)) {
            DB::table('routes')->insert([
                [
                    'airline_id' => $sv,
                    'route_type' => RouteType::ROUND->value,
                    'flight_type' => FlightType::DIRECT->value,
                    'from_city_id' => $dhaka,
                    'to_city_id' => $riyadh,
                    'return_city_id' => $dhaka,
                    'additional_gap' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'airline_id' => $bg,
                    'route_type' => RouteType::ONEWAY_OUTBOUND->value,
                    'flight_type' => FlightType::TRANSIT->value,
                    'from_city_id' => $dhaka,
                    'to_city_id' => $jeddah,
                    'return_city_id' => null,
                    'additional_gap' => 2,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'airline_id' => $sv,
                    'route_type' => RouteType::MULTI_CITY->value,
                    'flight_type' => FlightType::DIRECT->value,
                    'from_city_id' => $jeddah,
                    'to_city_id' => $dhaka,
                    'return_city_id' => $riyadh,
                    'additional_gap' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);
        }
    }
}
