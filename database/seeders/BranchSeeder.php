<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BranchSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('branches')->insertOrIgnore([
            [
                'name' => 'Dhaka Branch',
                'address' => '123, Shahbagh Road, Dhaka, Bangladesh',
                'contacts' => '+880 2-9123456',
                'location' => 'BD',
                'fingerprint_operation' => true,
                'branch_code' => 'DHK01',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Chittagong Branch',
                'address' => '45, Agrabad Shopping Center, Chittagong, Bangladesh',
                'contacts' => '+880 31-9123456',
                'location' => 'BD',
                'fingerprint_operation' => true,
                'branch_code' => 'CTG01',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
