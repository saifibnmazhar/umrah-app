<?php

namespace App\Console\Commands;

use App\Models\Fingerprint;
use App\Models\FingerprintDetail;
use App\Models\Passenger;
use Illuminate\Console\Command;

class BackfillMissingFingerprintDetails extends Command
{
    protected $signature = 'fingerprints:backfill-missing-details';

    protected $description = 'Create missing fingerprint_detail records for passengers that lack them';

    public function handle(): int
    {
        $passengers = Passenger::whereDoesntHave('fingerprintDetail')
            ->with('booking')
            ->orderBy('id')
            ->get();

        $total = $passengers->count();

        if ($total === 0) {
            $this->info('All passengers already have fingerprint detail records.');

            return Command::SUCCESS;
        }

        $this->info("Found {$total} passenger(s) missing fingerprint detail records.");

        $progress = $this->output->createProgressBar($total);
        $progress->start();

        $created = 0;
        $errors = 0;

        foreach ($passengers as $passenger) {
            try {
                $fingerprint = Fingerprint::firstOrCreate(
                    ['booking_id' => $passenger->booking_id],
                    ['deadline' => $passenger->booking->created_at->addDays(10), 'cost' => 0]
                );

                FingerprintDetail::create([
                    'fingerprint_id' => $fingerprint->id,
                    'passenger_id' => $passenger->id,
                    'status' => 'none',
                ]);

                $created++;
            } catch (\Exception $e) {
                $this->warn("Failed for passenger ID {$passenger->id}: {$e->getMessage()}");
                $errors++;
            }

            $progress->advance();
        }

        $progress->finish();
        $this->newLine(2);
        $this->info("Done. Created {$created} fingerprint detail record(s).");

        if ($errors > 0) {
            $this->warn("Failed for {$errors} passenger(s). Check logs for details.");

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
