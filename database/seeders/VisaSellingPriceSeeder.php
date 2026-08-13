<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\VisaSellingPrice;
use Illuminate\Database\Seeder;

class VisaSellingPriceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $admin = User::where('email', 'admin@example.com')->first();

        if ($admin) {
            VisaSellingPrice::firstOrCreate(
                ['user_id' => $admin->id],
                ['selling_price' => 350.00]
            );
        }
    }
}