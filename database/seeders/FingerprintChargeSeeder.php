<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FingerprintChargeSeeder extends Seeder
{
    public function run(): void
    {
        $districts = DB::table('districts')->pluck('id')->toArray();
        $userId = DB::table('users')->value('id');

        foreach ($districts as $districtId) {
            DB::table('fingerprint_charges')->insert([
                'district_id' => $districtId,
                'user_id' => $userId,
                'fingerprint_charge' => 1500.000000,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
