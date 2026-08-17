<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            TransactionTypeSeeder::class,
            UserSeeder::class,
            PassengerStatusSeeder::class,

            // Reference data (no FK dependencies)
            DistrictSeeder::class,
            BranchSeeder::class,
            CurrencyRateSeeder::class,
            CityCodeSeeder::class,
            AirlineSeeder::class,
            AirlineClassSeeder::class,
            VisaSellingPriceSeeder::class,
            FlightDateGapSeeder::class,
            FingerprintChargeSeeder::class,
            ReIssueRefundReasonSeeder::class,

            // Dependent data (requires airlines, classes, routes, etc.)
            RouteSeeder::class,
            TicketFareSeeder::class,
            PackageSeeder::class,
            CustomerSeeder::class,

            // Sample bookings with passengers, invoices, payments, etc.
            BookingSeeder::class,
            VisaSubmissionSeeder::class,
            PaymentSeeder::class,
        ]);
    }
}
