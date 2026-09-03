<?php

namespace App\Console\Commands;

use App\Models\Passenger;
use Illuminate\Console\Command;

class VerifyRefundPayableCommand extends Command
{
    protected $signature = 'refund:verify';

    protected $description = 'Cross-check stored passengers.refund_payable against dynamic aggregation';

    public function handle(): int
    {
        $mismatches = 0;

        Passenger::has('refundedTickets')
            ->orHas('reIssueSettlements')
            ->chunkById(200, function ($passengers) use (&$mismatches) {
                foreach ($passengers as $passenger) {
                    $computed = $passenger->verifyRefundPayable();

                    if (abs($computed - (float) $passenger->refund_payable) < 0.000001) {
                        continue;
                    }

                    $mismatches++;
                    $this->line("Mismatch passenger #{$passenger->id}: "
                        ."stored={$passenger->refund_payable} computed={$computed}");
                }
            });

        $this->info($mismatches ? "{$mismatches} mismatch(es) found." : 'All refund payables are in sync.');

        return 0;
    }
}
