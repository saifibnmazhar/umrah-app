<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Models\Fingerprint;
use App\Models\FingerprintDetail;
use Illuminate\Console\Command;

class FillBookingFingerprints extends Command
{
    protected $signature = 'bookings:fill-fingerprints
                            {--chunk=100 : Number of bookings to process per chunk}
                            {--reset-cost : Reset existing fingerprint costs to 0}';

    protected $description = 'Backfill fingerprint records for existing bookings that lack them';

    public function handle(): int
    {
        if ($this->option('reset-cost')) {
            $count = Fingerprint::where('cost', '>', 0)->update(['cost' => 0]);
            $this->info("Reset {$count} existing fingerprint record(s) cost to 0.");
        }

        $chunkSize = (int) $this->option('chunk');

        $bookings = Booking::whereDoesntHave('fingerprint')
            ->with('passengers')
            ->orderBy('id')
            ->get();

        $total = $bookings->count();

        if ($total === 0) {
            $this->info('All bookings already have fingerprint records.');

            return Command::SUCCESS;
        }

        $this->info("Found {$total} booking(s) without fingerprint records.");

        $progress = $this->output->createProgressBar($total);
        $progress->start();

        $created = 0;
        $errors = 0;

        foreach ($bookings as $booking) {
            try {
                $fingerprint = Fingerprint::create([
                    'booking_id' => $booking->id,
                    'deadline' => $booking->created_at->addDays(10),
                    'cost' => 0,
                    'assigned_staff_id' => null,
                ]);

                foreach ($booking->passengers as $passenger) {
                    FingerprintDetail::create([
                        'fingerprint_id' => $fingerprint->id,
                        'passenger_id' => $passenger->id,
                        'status' => 'none',
                    ]);
                }

                $created++;
            } catch (\Exception $e) {
                $this->warn("Failed for booking ID {$booking->id}: {$e->getMessage()}");
                $errors++;
            }

            $progress->advance();
        }

        $progress->finish();
        $this->newLine(2);
        $this->info("Done. Created fingerprint records for {$created} booking(s).");

        if ($errors > 0) {
            $this->warn("Failed for {$errors} booking(s). Check logs for details.");

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
