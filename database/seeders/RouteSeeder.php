<?php

namespace Database\Seeders;

use App\Models\Airline;
use App\Models\CityCode;
use App\Models\Route;
use Illuminate\Database\Seeder;

class RouteSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $dubai = CityCode::where('city_code', 'DXB')->first();
        $jeddah = CityCode::where('city_code', 'JED')->first();
        $dhaka = CityCode::where('city_code', 'DAC')->first();
        $chittagong = CityCode::where('city_code', 'CGP')->first();
        $doha = CityCode::where('city_code', 'DOH')->first;
        $abuDhabi = CityCode::where('city_code', 'AUH')->first;

        $emirates = Airline::where('code', 'EK')->first();
        $qatar = Airline::where('code', 'QR')->first;
        $saudi = Airline::where('code', 'SV')->first;

        Route::insert([
            [
                'airline_id' => $emirates?->id ?? 1,
                'route_type' => 'round',
                'flight_type' => 'direct',
                'from_city_id' => $dubai?->id ?? 1,
                'to_city_id' => $dhaka?->id ?? 1,
                'return_city_id' => null,
                'additional_gap' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'airline_id' => $qatar?->id ?? 1,
                'route_type' => 'oneway_outbound',
                'flight_type' => 'transit',
                'from_city_id' => $doha?->id ?? 1,
                'to_city_id' => $chittagong?->id ?? 2,
                'return_city_id' => null,
                'additional_gap' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'airline_id' => $saudi?->id ?? 1,
                'route_type' => 'oneway_inbound',
                'flight_type' => 'direct',
                'from_city_id' => $jeddah?->id ?? 2,
                'to_city_id' => $dhaka?->id ?? 1,
                'return_city_id' => null,
                'additional_gap' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
