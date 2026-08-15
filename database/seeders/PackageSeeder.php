<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PackageSeeder extends Seeder
{
    public function run(): void
    {
        // Link packages to the first ticket fare (single-ticket scenario)
        $ticketFareId = DB::table('ticket_fares')->value('id');
        $visaSellingPriceId = DB::table('visa_selling_prices')->value('id');

        // Create a second ticket fare to link as inbound/outbound for double-ticket package
        $airlineClasses = DB::table('airline_classes')
            ->select('airline_classes.id')
            ->join('classes', 'classes.id', '=', 'airline_classes.class_id')
            ->whereIn('airline_classes.airline_id', [1, 2, 3])
            ->whereIn('classes.name', ['Economy'])
            ->first();

        $economyClassId = $airlineClasses?->id;

        $routeIdInbound = DB::table('routes')->where('route_type', 'oneway_inbound')->first()?->id
            ?? DB::table('routes')->value('id');
        $routeIdOutbound = DB::table('routes')->where('route_type', 'oneway_outbound')->first()?->id
            ?? DB::table('routes')->value('id');

        $secondTicketFareId = DB::table('ticket_fares')->insertGetId([
            'airline_id' => 1,
            'airline_classes_id' => $economyClassId,
            'route_id' => $routeIdInbound ?? $ticketFareId,
            'ticket_type' => 'regular',
            'effective_from' => now()->subDays(30),
            'effective_to' => now()->addDays(30),
            'net_fare' => 24000.00,
            'selling_fare' => 27000.00,
            'offer_price' => null,
            'child_fare_percentage' => 75.00,
            'infant_fare_percentage' => 10.00,
            'with_meal' => true,
            'user_id' => 1,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $thirdTicketFareId = DB::table('ticket_fares')->insertGetId([
            'airline_id' => 2,
            'airline_classes_id' => $economyClassId,
            'route_id' => $routeIdOutbound ?? $ticketFareId,
            'ticket_type' => 'regular',
            'effective_from' => now()->subDays(30),
            'effective_to' => now()->addDays(30),
            'net_fare' => 26000.00,
            'selling_fare' => 29000.00,
            'offer_price' => null,
            'child_fare_percentage' => 75.00,
            'infant_fare_percentage' => 10.00,
            'with_meal' => true,
            'user_id' => 1,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('packages')->insert([
            [
                'package_name' => 'Umrah Package - 10 Days',
                'ticket_fare_id' => $ticketFareId,
                'visa_selling_price_id' => $visaSellingPriceId,
                'regular_price' => 35000.00,
                'offer_price' => 32000.00,
                'service_charge' => 1500.00,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'package_name' => 'Basic Umrah Package (3 Nights)',
                'ticket_fare_id' => $ticketFareId,
                'visa_selling_price_id' => $visaSellingPriceId,
                'regular_price' => 25000.00,
                'offer_price' => null,
                'service_charge' => 1000.00,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'package_name' => 'Deluxe Umrah Package (7 Nights) - Double Ticket',
                'ticket_fare_id' => $secondTicketFareId,
                'visa_selling_price_id' => $visaSellingPriceId,
                'regular_price' => 42000.00,
                'offer_price' => 40000.00,
                'service_charge' => 2000.00,
                'is_active' => true,
                'ticket_fare_inbound_id' => $secondTicketFareId,
                'ticket_fare_outbound_id' => $thirdTicketFareId,
                'is_double_ticket' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
