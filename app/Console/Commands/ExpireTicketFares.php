<?php

namespace App\Console\Commands;

use App\Models\TicketFare;
use Illuminate\Console\Command;

class ExpireTicketFares extends Command
{
    protected $signature = 'ticket-fares:expire';
    protected $description = 'Deactivate ticket fares whose effective_to date has passed, along with their associated packages';

    public function handle(): void
    {
        $expiredFares = TicketFare::where('is_active', true)
            ->where('effective_to', '<', now()->startOfDay())
            ->get();

        $fareCount = $expiredFares->count();
        $packageCount = 0;

        foreach ($expiredFares as $fare) {
            $packageCount += $fare->packages()->where('is_active', true)->count();
            $fare->packages()->update(['is_active' => false]);
            $fare->update(['is_active' => false]);
        }

        $this->info("Deactivated {$fareCount} expired ticket fare(s) and {$packageCount} associated package(s).");
    }
}
