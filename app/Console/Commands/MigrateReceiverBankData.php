<?php

namespace App\Console\Commands;

use App\Models\Bank;
use App\Models\Payment;
use Illuminate\Console\Command;

class MigrateReceiverBankData extends Command
{
    protected $signature = 'migrate:receiver-bank';
    protected $description = 'Backfill receiver_bank from receiver_bank_id via banks.name';

    public function handle()
    {
        $count = 0;
        Payment::whereNotNull('receiver_bank_id')
            ->whereNull('receiver_bank')
            ->each(function (Payment $payment) use (&$count) {
                $bank = Bank::find($payment->receiver_bank_id);
                if ($bank) {
                    $payment->update(['receiver_bank' => $bank->name]);
                    $count++;
                }
            });

        $this->info("Migrated {$count} payments.");
    }
}
