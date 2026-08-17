<?php

namespace Database\Seeders;

use App\Models\TransactionType;
use Illuminate\Database\Seeder;

class TransactionTypeSeeder extends Seeder
{
    public function run(): void
    {
        $transactionTypes = [
            [
                'name' => 'Initial Payment',
                'type' => 'credit',
            ],
            [
                'name' => 'Due Collection',
                'type' => 'credit',
            ],
            [
                'name' => 'Customer Refund',
                'type' => 'debit',
            ],
            [
                'name' => 'Ticket Refund - Payment',
                'type' => 'debit',
            ],
            [
                'name' => 'Ticket Refund - Re-issue',
                'type' => 'debit',
            ],
            [
                'name' => 'Ticket Agent Payment',
                'type' => 'debit',
            ],
            [
                'name' => 'Visa Agent Payment',
                'type' => 'debit',
            ],
            [
                'name' => 'Commission Agent Payment',
                'type' => 'debit',
            ],
            [
                'name' => 'Service Charge Deduction',
                'type' => 'credit',
            ],
        ];

        foreach ($transactionTypes as $type) {
            TransactionType::updateOrCreate(
                ['name' => $type['name']],
                $type
            );
        }
    }
}
