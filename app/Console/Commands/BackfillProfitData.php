<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Models\ReIssuedTicket;
use App\Services\ProfitCalculationService;
use Illuminate\Console\Command;

class BackfillProfitData extends Command
{
    protected $signature = 'profit:backfill';

    protected $description = 'Backfill profit values for all existing bookings and passengers';

    public function handle(ProfitCalculationService $profitService): int
    {
        $count = Booking::count();

        if ($count === 0) {
            $this->info('No bookings found.');

            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($count);
        $bar->start();

        Booking::query()
            ->with([
                'passengers.visaSubmission.cancelledSubmissions',
                'passengers.allIssuedTickets.ticketFare',
                'passengers.allIssuedTickets.reIssuedTickets',
                'passengers.refundedTickets',
                'package.ticketFare',
                'package.ticketFareInbound',
                'package.ticketFareOutbound',
                'fingerprint.booking.fingerprintCharge',
                'fingerprintCharge',
            ])
            ->chunkById(100, function ($bookings) use ($profitService, $bar): void {
                foreach ($bookings as $booking) {
                    $profitService->recalculateBookingProfit($booking);
                    $bar->advance();
                }
            });

        $bar->finish();
        $this->newLine();
        $this->info("Backfilled {$count} bookings.");

        $reIssueCount = ReIssuedTicket::where('total_cost', 0)->count();

        if ($reIssueCount > 0) {
            ReIssuedTicket::where('total_cost', 0)->each(function ($reIssue) {
                $rawCost = (float) $reIssue->re_issue_charge
                    + (float) $reIssue->fare_difference
                    + (float) $reIssue->other_costs
                    + (float) $reIssue->net_fare;
                $reIssue->update(['total_cost' => round($rawCost, 6)]);
            });

            $this->info("Backfilled total_cost for {$reIssueCount} re-issued tickets.");
        }

        return self::SUCCESS;
    }
}
