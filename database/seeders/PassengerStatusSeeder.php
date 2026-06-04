<?php

namespace Database\Seeders;

use App\Models\PassengerStatus;
use Illuminate\Database\Seeder;

class PassengerStatusSeeder extends Seeder
{
    public function run(): void
    {
        $statuses = [
            ['name' => 'Processing'],
            ['name' => 'Fingerprint Done'],
            ['name' => 'Visa Submitted'],
            ['name' => 'Visa Issued'],
            ['name' => 'Ticket Issued'],
            ['name' => 'Ticket Issued before Visa'],
            ['name' => 'Visa Issued before Ticket'],
            ['name' => 'Delivered'],
            ['name' => 'Hold'],
            ['name' => 'Cancel'],
            ['name' => 'Refund Done'],
            ['name' => 'Departure Done'],
        ];

        foreach ($statuses as $status) {
            PassengerStatus::updateOrCreate(['name' => $status['name']], $status);
        }
    }
}
