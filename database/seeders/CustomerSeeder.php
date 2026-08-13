<?php

namespace Database\Seeders;

use App\Models\Customer;
use Illuminate\Database\Seeder;

class CustomerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Customer::insert([
            [
                'name' => 'Ahmed Hassan',
                'iqama_type' => 'self',
                'passport_no' => 'AB1234567',
                'iqama_no' => 'BD7654321',
                'mobile_no' => '01712345678',
                'ref_iqama_no' => null,
                'ref_mobile_no' => null,
                'ref_iqama_doc' => null,
                'address' => 'House #12, Road #5, Dhanmondi, Dhaka 1205, Bangladesh',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Fatima Khatun',
                'iqama_type' => 'referral',
                'passport_no' => 'CD9876543',
                'iqama_no' => 'EF1234567',
                'mobile_no' => '01787654321',
                'ref_iqama_no' => 'GH9876543',
                'ref_mobile_no' => '01711223344',
                'ref_iqama_doc' => '/storage/docs/ref_doc_1.pdf',
                'address' => 'House #8, Road #12, Mohammadpur, Dhaka 1206, Bangladesh',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Mohammad Rahman',
                'iqama_type' => 'none',
                'passport_no' => 'IJ4567890',
                'iqama_no' => null,
                'mobile_no' => '01799887766',
                'ref_iqama_no' => null,
                'ref_mobile_no' => null,
                'ref_iqama_doc' => null,
                'address' => 'House #45, Street #3, Gulshan, Dhaka 1212, Bangladesh',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
