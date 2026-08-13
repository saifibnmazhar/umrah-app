<?php

namespace Database\Seeders;

use App\Models\Package;
use App\Models\TicketFare;
use Illuminate\Database\Seeder;

class PackageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $ticketFares = TicketFare::where('is_active', true)->take(3)->get();

        foreach ($ticketFares as $index => $fare) {
            Package::create([
                'package_name' => 'Umrah Package '.($index + 1).' - Standard',
                'ticket_fare_id' => $fare->id,
                'regular_price' => 450000.00,
                'offer_price' => 420000.00,
                'service_charge' => 2000.00,
                'is_active' => true,
                'is_double_ticket' => false,
            ]);
        }

        // Create a double ticket package
        if ($ticketFares->count() >= 3) {
            Package::create([
                'package_name' => 'Umrah Package - Double Ticket',
                'ticket_fare_id' => $ticketFares[0]->id,
                'ticket_fare_inbound_id' => $ticketFares[0]->id,
                'ticket_fare_outbound_id' => $ticketFares[0]->id,
                'regular_price' => 850000.00,
                'offer_price' => 800000.00,
                'service_charge' => 4000.00,
                'is_active' => true,
                'is_double_ticket' => true,
            ]);
        }
    }
}
