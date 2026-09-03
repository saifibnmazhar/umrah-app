<?php

namespace Database\Seeders;

use App\Models\Package;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PackageSeeder extends Seeder
{
    public function run(): void
    {
        $visaSellingPriceId = DB::table('visa_selling_prices')->value('id');

        // Get all existing ticket fares (created by TicketFareSeeder)
        $ticketFares = DB::table('ticket_fares')->orderBy('id')->get();
        $ticketFareIds = $ticketFares->pluck('id')->toArray();

        if (empty($ticketFareIds)) {
            // Fallback: create a ticket fare inline
            $routeId = DB::table('routes')->value('id');
            $airlineId = DB::table('airlines')->value('id');
            $classId = DB::table('airline_classes')->value('id');

            $ticketFareId = DB::table('ticket_fares')->insertGetId([
                'airline_id' => $airlineId,
                'airline_classes_id' => $classId,
                'route_id' => $routeId,
                'ticket_type' => 'regular',
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
            ]);
            $ticketFareIds = [$ticketFareId];
        }

        // packages has unique(ticket_fare_id) so we can only have one package per fare
        // Create one package per available ticket fare
        $packages = [
            [
                'package_name' => 'Umrah Package - 10 Days',
                'ticket_fare_id' => $ticketFareIds[0] ?? null,
                'visa_selling_price_id' => $visaSellingPriceId,
                'regular_price' => 35000.00,
                'offer_price' => 32000.00,
                'service_charge' => 1500.00,
                'is_active' => true,
            ],
        ];

        // Add double-ticket package if we have a second fare
        if (count($ticketFareIds) > 1) {
            $packages[] = [
                'package_name' => 'Deluxe Umrah Package - Double Ticket',
                'ticket_fare_id' => $ticketFareIds[1],
                'visa_selling_price_id' => $visaSellingPriceId,
                'regular_price' => 42000.00,
                'offer_price' => 40000.00,
                'service_charge' => 2000.00,
                'is_active' => true,
                'is_double_ticket' => true,
            ];
        }

        foreach ($packages as $pkg) {
            Package::firstOrCreate(
                ['package_name' => $pkg['package_name']],
                array_merge($pkg, ['created_at' => now(), 'updated_at' => now()])
            );
        }
    }
}
