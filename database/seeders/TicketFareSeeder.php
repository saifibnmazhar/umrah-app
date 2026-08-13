<?php

namespace Database\Seeders;

use App\Enums\TicketType;
use App\Models\Airline;
use App\Models\Route;
use App\Models\TicketFare;
use App\Models\TravelClass;
use App\Models\User;
use Illuminate\Database\Seeder;

class TicketFareSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $admin = User::where('email', 'admin@example.com')->first();
        $routes = Route::all();
        $airlines = Airline::all();
        $classes = TravelClass::all();

        foreach ($routes as $route) {
            foreach ($airlines as $airline) {
                foreach ($classes as $class) {
                    TicketFare::create([
                        'airline_id' => $airline->id,
                        'airline_classes_id' => $class->id,
                        'route_id' => $route->id,
                        'ticket_type' => TicketType::REGULAR->value,
                        'effective_from' => now()->addMonths(1)->format('Y-m-d'),
                        'effective_to' => now()->addYear()->format('Y-m-d'),
                        'net_fare' => 50000.00,
                        'selling_fare' => 55000.00,
                        'offer_price' => null,
                        'child_fare_percentage' => 75.00,
                        'infant_fare_percentage' => 10.00,
                        'with_meal' => true,
                        'user_id' => $admin?->id ?? 1,
                        'is_active' => true,
                    ]);
                }
            }
        }
    }
}
