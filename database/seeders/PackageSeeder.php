<?php

namespace Database\Seeders;

use App\Models\Package;
use App\Models\TicketFare;
use App\Models\VisaSellingPrice;
use Illuminate\Database\Seeder;

class PackageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $ticketFares = TicketFare::where('is_active', true)->take(3)->get();
        $visaSellingPrice = VisaSellingPrice::first();

        // Don't create packages if we don't have the required data
        if ($ticketFares->isEmpty() || ! $visaSellingPrice) {
            return;
        }

        foreach ($ticketFares as $index => $fare) {
            Package::create([
                'package_name' => 'Umrah Package '.($index + 1).' - Standard',
                'ticket_fare_id' => $fare->id,
                'visa_selling_price_id' => $visaSellingPrice->id,
                'regular_price' => 450000.00,
                'offer_price' => 420000.00,
                'service_charge' => 2000.00,
                'is_active' => true,
                'is_double_ticket' => false,
            ]);
        }

        // Create a double ticket package only if we have multiple fares
        // and no package exists for this fare
        if ($ticketFares->count() >= 3) {
            $thirdFare = $ticketFares[2];
            if (! Package::where('ticket_fare_id', $thirdFare->id)->exists()) {
                Package::create([
                    'package_name' => 'Umrah Package - Double Ticket',
                    'ticket_fare_id' => $thirdFare->id,
                    'ticket_fare_inbound_id' => $thirdFare->id,
                    'ticket_fare_outbound_id' => $thirdFare->id,
                    'visa_selling_price_id' => $visaSellingPrice->id,
                    'regular_price' => 850000.00,
                    'offer_price' => 800000.00,
                    'service_charge' => 4000.00,
                    'is_active' => true,
                    'is_double_ticket' => true,
                ]);
            }
        }
    }
}
