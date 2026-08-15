<?php

namespace Database\Seeders;

use App\Enums\TicketType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TicketFareSeeder extends Seeder
{
    public function run(): void
    {
        // Get the 3 most recently inserted routes for airlines SV, BG, EK
        $svAirline = DB::table('airlines')->where('code', 'SV')->latest('id')->value('id');
        $bgAirline = DB::table('airlines')->where('code', 'BG')->latest('id')->value('id');

        $routes = DB::table('routes')
            ->whereIn('airline_id', [$svAirline, $bgAirline])
            ->orderBy('created_at', 'desc')
            ->take(3)
            ->pluck('id')
            ->toArray();

        if (count($routes) < 3) {
            // Fallback: use any 3 existing routes
            $routes = DB::table('routes')->orderBy('id')->take(3)->pluck('id')->toArray();
        }

        // Get an airline_classes_id for the airlines we'll use
        $airlineClassIds = DB::table('airline_classes')
            ->join('classes', 'classes.id', '=', 'airline_classes.class_id')
            ->where('classes.name', 'Economy')
            ->pluck('airline_classes.id')
            ->toArray();

        $economyClassId = $airlineClassIds[0] ?? null;

        // Use insertOrIgnore to avoid duplicate route_id conflicts
        $fares = [
            [
                'airline_id' => $svAirline,
                'airline_classes_id' => $economyClassId,
                'route_id' => $routes[0],
                'ticket_type' => TicketType::REGULAR->value,
                'effective_from' => now()->subDays(30),
                'effective_to' => now()->addDays(30),
                'net_fare' => 25000.00,
                'selling_fare' => 28000.00,
                'offer_price' => null,
                'child_fare_percentage' => 75.00,
                'infant_fare_percentage' => 10.00,
                'with_meal' => true,
                'user_id' => 1,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'airline_id' => $bgAirline,
                'airline_classes_id' => $economyClassId,
                'route_id' => $routes[1],
                'ticket_type' => TicketType::OFFER->value,
                'effective_from' => now()->subDays(15),
                'effective_to' => now()->addDays(15),
                'net_fare' => 22000.00,
                'selling_fare' => 24000.00,
                'offer_price' => 23000.00,
                'child_fare_percentage' => 75.00,
                'infant_fare_percentage' => 10.00,
                'with_meal' => true,
                'user_id' => 1,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'airline_id' => $svAirline,
                'airline_classes_id' => $economyClassId,
                'route_id' => $routes[2],
                'ticket_type' => TicketType::GROUP->value,
                'effective_from' => now()->subDays(5),
                'effective_to' => now()->addDays(60),
                'net_fare' => 30000.00,
                'selling_fare' => 33000.00,
                'offer_price' => null,
                'child_fare_percentage' => 75.00,
                'infant_fare_percentage' => 10.00,
                'with_meal' => true,
                'user_id' => 1,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        foreach ($fares as $fare) {
            // Avoid duplicates: check if a fare already exists for this route_id
            $exists = DB::table('ticket_fares')->where('route_id', $fare['route_id'])->exists();
            if (! $exists) {
                DB::table('ticket_fares')->insert($fare);
            }
        }
    }
}
