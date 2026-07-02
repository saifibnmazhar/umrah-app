<?php

namespace App\Console\Commands;

use App\Models\Booking;
use Illuminate\Console\Command;

class BackfillBranchCodeInvoiceIds extends Command
{
    protected $signature = 'umrah:backfill-branch-code-invoice-ids';
    protected $description = 'Replace INV+branch_id prefix with branch_code in existing invoice IDs';

    public function handle(): void
    {
        $count = 0;

        Booking::whereNotNull('invoice_id')
            ->chunk(100, function ($bookings) use (&$count) {
                foreach ($bookings as $booking) {
                    $oldId = $booking->invoice_id;

                    $suffix = substr($oldId, 5);

                    $branch = $booking->bookingBranch;
                    $code = $branch?->branch_code ?? '(###)';

                    $newId = $code . $suffix;

                    $attempt = 0;
                    while (Booking::where('invoice_id', $newId)->where('id', '!=', $booking->id)->exists()) {
                        $attempt++;
                        $newId = $code . $suffix . '-' . $attempt;
                    }

                    if ($newId !== $oldId) {
                        $booking->update(['invoice_id' => $newId]);
                        $count++;
                    }
                }
            });

        $this->info("Backfilled {$count} invoice ID(s) with branch code.");
    }
}
