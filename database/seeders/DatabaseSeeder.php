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
            RoleSeeder::class,
            TransactionTypeSeeder::class,
            UserSeeder::class,
            PassengerStatusSeeder::class,

            BranchSeeder::class,
            DistrictSeeder::class,
            CurrencyRateSeeder::class,
            CustomerSeeder::class,

            AirlineSeeder::class,
            RouteSeeder::class,
            TicketFareSeeder::class,

            PackageSeeder::class,
            FingerprintChargeSeeder::class,
            VisaAgentSeeder::class,
        ]);
    }
}
