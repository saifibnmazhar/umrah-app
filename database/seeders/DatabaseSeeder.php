<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            // Reference data first
            RoleSeeder::class,
            TransactionTypeSeeder::class,
            BranchSeeder::class,
            DistrictSeeder::class,
            TravelClassSeeder::class,
            CityCodeSeeder::class,

            // Users and authentication
            UserSeeder::class,
            CurrencyRateSeeder::class,

            // Airline and route data
            AirlineSeeder::class,
            RouteSeeder::class,
            AirlineClassSeeder::class,
            TicketFareSeeder::class,

            // Core business entities
            PackageSeeder::class,
            CustomerSeeder::class,

            // Status and configuration
            PassengerStatusSeeder::class,
        ]);
    }
}
