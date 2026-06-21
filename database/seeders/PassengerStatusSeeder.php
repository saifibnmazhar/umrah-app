<?php

namespace Database\Seeders;

use App\Models\PassengerStatus;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PassengerStatusSeeder extends Seeder
{
    public function run(): void
    {
        // Remove "Visa Issued before Ticket" — nullify FK references first, then delete
        $statusToRemove = PassengerStatus::where('name', 'Visa Issued before Ticket')->first();
        if ($statusToRemove) {
            DB::table('passengers')->where('passenger_status_id', $statusToRemove->id)->update(['passenger_status_id' => null]);
            $statusToRemove->delete();
        }

        // Rename "Refund Done" → "Ticket Refund Done"
        PassengerStatus::where('name', 'Refund Done')->update(['name' => 'Ticket Refund Done']);

        $desiredStatuses = [
            'Processing',
            'Fingerprint Done',
            'Visa Submitted',
            'Visa Issued',
            'Ticket Issued',
            'Ticket Issued before Visa',
            'Delivered',
            'Hold',
            'Cancel',
            'Ticket Refund Done',
            'Departure Done',
        ];

        // Remove any leftover statuses not in the desired list
        PassengerStatus::whereNotIn('name', $desiredStatuses)->each(function ($status) {
            DB::table('passengers')->where('passenger_status_id', $status->id)->update(['passenger_status_id' => null]);
            $status->delete();
        });

        // Ensure all desired statuses exist
        foreach ($desiredStatuses as $name) {
            PassengerStatus::firstOrCreate(['name' => $name]);
        }
    }
}
