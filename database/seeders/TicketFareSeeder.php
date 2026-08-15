<?php

namespace Database\Seeders;

use App\Enums\TicketType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TicketFareSeeder extends Seeder
{
    public function run(): void
    {
        $routes = DB::table('routes')->pluck('id', 'airline_id')->toArray();

        $airlineClasses = DB::table('airline_classes')
            ->select('airline_classes.id', 'classes.name')
            ->join('classes', 'classes.id', '=', 'airline_classes.class_id')
            ->whereIn('airline_classes.airline_id', [1, 2, 3])
            ->whereIn('classes.name', ['Economy', 'Business'])
            ->get();

        $airlineClassMap = [];
        foreach ($airlineClasses as $ac) {
            if (! isset($airlineClassMap[$ac->name])) {
                $airlineClassMap[$ac->name] = $ac->id;
            }
        }

        $economyClassId = $airlineClassMap['Economy'] ?? $airlineClasses->first()?->id;

        $routeIds = array_values($routes);

        DB::table('ticket_fares')->insert([
            [
                'airline_id' => 1, // Saudi Arabian Airlines
                'airline_classes_id' => $economyClassId,
                'route_id' => $routeIds[0],
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
                'airline_id' => 2, // Biman Bangladesh Airlines
                'airline_classes_id' => $economyClassId,
                'route_id' => $routeIds[1],
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
                'airline_id' => 3, // Emirates
                'airline_classes_id' => $economyClassId,
                'route_id' => $routeIds[2],
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
        ]);
    }
}
