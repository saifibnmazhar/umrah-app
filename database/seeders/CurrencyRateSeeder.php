<?php

namespace Database\Seeders;

use App\Models\CurrencyRate;
use App\Models\User;
use Illuminate\Database\Seeder;

class CurrencyRateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $admin = User::where('email', 'admin@example.com')->first();

        if ($admin) {
            // Default SAR rate (base currency)
            CurrencyRate::create([
                'user_id' => $admin->id,
                'rate' => 1.0,
            ]);
        }
    }
}
