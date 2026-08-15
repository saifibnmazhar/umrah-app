<?php

namespace Database\Seeders;

use App\Enums\IqamaType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CustomerSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('customers')->insert([
            [
                'name' => 'Mohammad Hossain',
                'iqama_type' => IqamaType::SELF->value,
                'passport_no' => 'PA1000001',
                'iqama_no' => 'IQ1000001',
                'mobile_no' => '+880 171-1111111',
                'address' => 'House 10, Road 5, Dhanmondi, Dhaka',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Abdullah Rahman',
                'iqama_type' => IqamaType::REFERRAL->value,
                'passport_no' => 'PA1000002',
                'iqama_no' => 'IQ1000002',
                'mobile_no' => '+880 191-2222222',
                'ref_iqama_no' => 'IQ1000001',
                'ref_mobile_no' => '+880 171-1111111',
                'address' => 'Apt 5B, 12 Dhanmondi, Dhaka',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Fatima Akter',
                'iqama_type' => IqamaType::NONE->value,
                'passport_no' => 'PA1000003',
                'mobile_no' => '+880 175-3333333',
                'address' => '32, Shantibagh Road, Dhaka',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
