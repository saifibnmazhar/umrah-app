<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\VisaSellingPrice;
use Illuminate\Database\Seeder;

class VisaSellingPriceSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('email', 'admin@example.com')->first();

        $prices = [
            ['selling_price' => 35.00, 'user_id' => $admin?->id],
            ['selling_price' => 40.00, 'user_id' => $admin?->id],
            ['selling_price' => 45.00, 'user_id' => $admin?->id],
        ];

        foreach ($prices as $price) {
            VisaSellingPrice::firstOrCreate(
                ['selling_price' => $price['selling_price']],
                $price
            );
        }
    }
}
