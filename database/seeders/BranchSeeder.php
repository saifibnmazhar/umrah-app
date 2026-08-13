<?php

namespace Database\Seeders;

use App\Models\Branch;
use Illuminate\Database\Seeder;

class BranchSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Branch::insert([
            [
                'name' => 'Dhaka Office',
                'address' => 'Motijheel, Dhaka 1205, Bangladesh',
                'contacts' => '01712345678',
                'location' => 'BD',
                'fingerprint_operation' => true,
                'branch_code' => 'DHK',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Chittagong Office',
                'address' => 'Police Line, Chittagong 4000, Bangladesh',
                'contacts' => '01787654321',
                'location' => 'BD',
                'fingerprint_operation' => true,
                'branch_code' => 'CTG',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Khulna Office',
                'address' => 'Police Line, Khulna 9000, Bangladesh',
                'contacts' => '01711223344',
                'location' => 'BD',
                'fingerprint_operation' => false,
                'branch_code' => 'KUL',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
