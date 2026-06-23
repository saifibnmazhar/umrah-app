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
                'name' => 'Ticket Agent Payment',
                'type' => 'debit',
            ],
            [
                'name' => 'Visa Agent Payment',
                'type' => 'debit',
            ],
            [
                'name' => 'Commision Agent Payment',
                'type' => 'debit',
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