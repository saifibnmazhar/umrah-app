<?php

namespace Database\Seeders;

use App\Models\CurrencyRate;
use App\Models\User;
use Illuminate\Database\Seeder;

class CurrencyRateSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('email', 'admin@example.com')->first();

        $rates = [
            ['currency' => 'SAR', 'rate' => 1.00, 'user_id' => $admin?->id],
            ['currency' => 'BDT', 'rate' => 1.00, 'user_id' => $admin?->id],
            ['currency' => 'USD', 'rate' => 0.85, 'user_id' => $admin?->id],
        ];

        foreach ($rates as $rate) {
            CurrencyRate::firstOrCreate(
                ['currency' => $rate['currency'], 'user_id' => $rate['user_id']],
                ['rate' => $rate['rate']]
            );
        }
    }
}
