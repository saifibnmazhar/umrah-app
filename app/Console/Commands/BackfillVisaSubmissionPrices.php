<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Models\BookingUpdateLog;
use App\Models\Package;
use App\Models\PackageUpdateLog;
use App\Models\VisaSubmission;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class BackfillVisaSubmissionPrices extends Command
{
    protected $signature = 'umrah:backfill-visa-submission-prices {--dry-run : Report what would change without writing}';

    protected $description = 'Sync visa submission selling price ids with the booking package, using booking/package update history';

    /** @var array<int, array{action: string, target: int|null}> */
    private array $bookingDecisions = [];

    private int $applied = 0;

    private int $kept = 0;

    private int $skipped = 0;

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $ids = VisaSubmission::query()
            ->join('passengers', 'passengers.id', '=', 'visa_submissions.passenger_id')
            ->join('bookings', 'bookings.id', '=', 'passengers.booking_id')
            ->join('packages', 'packages.id', '=', 'bookings.package_id')
            ->whereColumn('visa_submissions.visa_selling_price_id', '!=', 'packages.visa_selling_price_id')
            ->orderBy('visa_submissions.id')
            ->pluck('visa_submissions.id');

        foreach ($ids->chunk(100) as $chunk) {
            $submissions = VisaSubmission::with(['passenger.booking.package'])
                ->whereIn('id', $chunk)
                ->get();

            foreach ($submissions as $submission) {
                $this->process($submission, $dryRun);
            }
        }

        $this->info("Applied: {$this->applied}, Kept: {$this->kept}, Skipped: {$this->skipped}");

        return self::SUCCESS;
    }

    private function process(VisaSubmission $submission, bool $dryRun): void
    {
        $booking = $submission->passenger?->booking;
        $package = $booking?->package;

        if (! $booking || ! $package) {
            $this->skipped++;

            return;
        }

        $decision = $this->bookingDecisions[$booking->id] ??= $this->decide($booking, $package);

        if ($decision['action'] === 'keep') {
            $this->kept++;

            return;
        }

        if ($decision['action'] === 'skip') {
            $this->skipped++;

            return;
        }

        $target = (int) $decision['target'];
        $current = (int) $submission->visa_selling_price_id;

        if ($target === $current) {
            $this->kept++;

            return;
        }

        if (! $dryRun) {
            $submission->update(['visa_selling_price_id' => $target]);
        }

        $this->applied++;
        $prefix = $dryRun ? '[dry-run] ' : '';
        $this->line("{$prefix}visa_submission {$submission->id}: {$current} -> {$target} (passenger {$submission->passenger_id}, booking {$booking->id})");
    }

    /**
     * Decide what the submission's price id should be for this booking.
     *
     * - Booking package never changed -> keep the submission as-is (it holds
     *   the historical price for that package).
     * - Booking package changed (swap at T) -> the price the package had at T
     *   per package_update_logs; if no logged visa_selling_price_id change
     *   exists after T, the price never changed after the swap -> current.
     */
    private function decide(Booking $booking, Package $package): array
    {
        $logs = BookingUpdateLog::where('booking_id', $booking->id)
            ->where('action', '!=', 'deleted')
            ->orderByDesc('created_at')
            ->get();

        $swapLog = $logs->first(fn (BookingUpdateLog $log) => is_array($log->old_values)
            && array_key_exists('package_id', $log->old_values));

        if ($swapLog) {
            $target = $this->resolvePriceAt($package, Carbon::parse($swapLog->created_at));

            return $target === null
                ? ['action' => 'skip', 'target' => null]
                : ['action' => 'apply', 'target' => $target];
        }

        $createdLog = $logs->first(fn (BookingUpdateLog $log) => $log->action === 'created');
        $createdPackageId = $createdLog?->new_values['package_id'] ?? null;

        if ($createdPackageId !== null && (int) $createdPackageId !== (int) $booking->package_id) {
            // The package was changed but the swap itself was never logged:
            // the swap moment is unknown, so we cannot pick a historic price.
            return ['action' => 'skip', 'target' => null];
        }

        return ['action' => 'keep', 'target' => null];
    }

    /**
     * The visa selling price id the package held at the given moment,
     * reconstructed from package_update_logs rows containing
     * visa_selling_price_id (logged edits that don't touch the price —
     * fare_updated, renames — do not count). When no such row exists after
     * the moment, the price was never changed after the swap
     * (pre-log era: used packages were locked), so the current price id is
     * returned. packages.updated_at is deliberately ignored.
     * Returns null only when a price row after the moment lacks old_values.
     */
    private function resolvePriceAt(Package $package, Carbon $at): ?int
    {
        $priceLogs = PackageUpdateLog::where('package_id', $package->id)
            ->get()
            ->filter(fn (PackageUpdateLog $log) => (is_array($log->old_values) && array_key_exists('visa_selling_price_id', $log->old_values))
                || (is_array($log->new_values) && array_key_exists('visa_selling_price_id', $log->new_values)))
            ->filter(fn (PackageUpdateLog $log) => $log->created_at !== null)
            ->values();

        $afterAt = $priceLogs->filter(fn (PackageUpdateLog $log) => Carbon::parse($log->created_at)->greaterThan($at));

        if ($afterAt->isNotEmpty()) {
            $value = (int) $package->visa_selling_price_id;

            foreach ($afterAt->sortByDesc(fn (PackageUpdateLog $log) => $log->created_at) as $log) {
                $previous = $log->old_values['visa_selling_price_id'] ?? null;

                if ($previous === null) {
                    return null;
                }

                $value = (int) $previous;
            }

            return $value;
        }

        // No logged visa_selling_price_id change after the swap:
        // the package's price was never updated (price-wise) -> current price.
        return (int) $package->visa_selling_price_id;
    }
}
