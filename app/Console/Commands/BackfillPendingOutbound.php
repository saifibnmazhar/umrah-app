<?php

namespace App\Console\Commands;

use App\Models\IssuedTicket;
use Illuminate\Console\Command;

class BackfillPendingOutbound extends Command
{
    protected $signature = 'tickets:backfill-pending-outbound {--dry-run : Only count without creating}';

    protected $description = 'Create missing pending_outbound tickets for regular tickets with outbound_pending=true';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $created = 0;
        $skipped = 0;

        IssuedTicket::where('outbound_pending', true)
            ->where(function ($q) {
                $q->whereNull('issue_type')->orWhere('issue_type', 'regular');
            })
            ->whereDoesntHave('passenger.allIssuedTickets', function ($q) {
                $q->where('issue_type', 'pending_outbound');
            })
            ->chunk(100, function ($regulars) use ($dryRun, &$created, &$skipped) {
                foreach ($regulars as $regular) {
                    $exists = IssuedTicket::where('passenger_id', $regular->passenger_id)
                        ->where('issue_type', 'pending_outbound')
                        ->whereIn('status', ['pending', 'awaiting-group'])
                        ->exists();

                    if ($exists) {
                        $skipped++;

                        continue;
                    }

                    if ($dryRun) {
                        $created++;

                        continue;
                    }

                    IssuedTicket::create([
                        'passenger_id' => $regular->passenger_id,
                        'booking_id' => $regular->booking_id,
                        'user_id' => $regular->user_id ?? $regular->booking?->user_id ?? 1,
                        'issue_type' => 'pending_outbound',
                        'status' => 'pending',
                        'is_refundable' => false,
                        'is_exchangeable' => false,
                        'outbound_pending' => false,
                        'ticket_fare_id' => null,
                    ]);

                    $created++;
                }
            });

        $this->info($dryRun ? "Would create {$created} pending_outbound records ({$skipped} skipped)." : "Created {$created} pending_outbound records ({$skipped} skipped).");

        return self::SUCCESS;
    }
}
