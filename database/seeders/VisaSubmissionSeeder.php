<?php

namespace Database\Seeders;

use App\Models\Passenger;
use App\Models\VisaAgent;
use App\Models\VisaSellingPrice;
use App\Models\VisaSubmission;
use Illuminate\Database\Seeder;

class VisaSubmissionSeeder extends Seeder
{
    public function run(): void
    {
        $visaAgent = VisaAgent::firstOrCreate(
            ['name' => 'Global Visa Services'],
            [
                'address' => 'Dhaka, Bangladesh',
                'contacts' => '+880 2-1234-5678',
            ]
        );

        $visaSellingPrice = VisaSellingPrice::firstOrCreate(
            ['selling_price' => 1200.00],
            [
                'user_id' => 1,
            ]
        );

        $passengers = Passenger::all();

        if ($passengers->isEmpty()) {
            $this->command?->info('No passengers found. Run BookingSeeder first or create passengers manually.');

            return;
        }

        foreach ($passengers as $passenger) {
            if (! $passenger->visaSubmission) {
                VisaSubmission::create([
                    'passenger_id' => $passenger->id,
                    'visa_agent_id' => $visaAgent->id,
                    'agent_commission' => 150.00,
                    'visa_selling_price_id' => $visaSellingPrice->id,
                    'net_visa_cost' => 950.00,
                    'additional_cost' => 50.00,
                    'final_cost' => 1200.00,
                    'visa_number' => 'VISA-2024-'.str_pad($passenger->id, 4, '0', STR_PAD_LEFT),
                    'status' => 'pending',
                    'remarks' => 'Sample visa submission for booking #'.$passenger->booking_id,
                ]);
            }
        }
    }
}
