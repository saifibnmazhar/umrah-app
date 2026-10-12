<?php

namespace App\Console\Commands;

use App\Enums\CancelledBookingStatus;
use App\Models\Booking;
use App\Models\Passenger;
use App\Services\BookingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Historic repair tool for passengers.package_value / bookings.total_value /
 * invoices.total_amount (plan 17 D5).
 *
 * - Recomputes each non-cancelled passenger at their own historicAnchor()
 *   (max(created_at, lastSwapAt)) — never at live prices.
 * - Re-totals the booking, re-applies the discount and pushes the invoice
 *   delta with reason='historic_backfill' so every backfill movement is
 *   greppable in invoice_update_logs.
 * - Cancelled bookings (cancelled_bookings status=cancelled) and
 *   is_cancelled passengers are never recomputed (D4).
 * - --dry-run prints the old -> new table per booking without writing.
 * - Idempotent: the anchor derives from immutable facts, so a second run
 *   moves nothing.
 */
class SyncBookingFinancials extends Command
{
    protected $signature = 'bookings:sync-financials {--dry-run : Report old -> new values without writing}';

    protected $description = 'Repair booking financials from historic prices (passenger package_value, totals, invoice delta)';

    public function handle(): int
    {
        $service = app(BookingService::class);
        $dryRun = (bool) $this->option('dry-run');

        $scanned = 0;
        $changedBookings = 0;
        $changedPassengers = 0;

        Booking::has('passengers')
            ->whereDoesntHave('cancelledBooking', fn ($q) => $q->where('status', CancelledBookingStatus::CANCELLED->value))
            ->with(['passengers', 'invoice', 'package'])
            ->chunk(100, function ($bookings) use ($service, $dryRun, &$scanned, &$changedBookings, &$changedPassengers) {
                foreach ($bookings as $booking) {
                    $scanned++;

                    if ($dryRun) {
                        [$paxChanges, $wouldChange] = $this->dryRunBooking($booking, $service);
                    } else {
                        [$paxChanges, $wouldChange] = $this->repairBooking($booking, $service);
                    }

                    if ($wouldChange) {
                        $changedBookings++;
                        $changedPassengers += count($paxChanges);
                    }
                }
            });

        if ($dryRun) {
            $this->info(sprintf(
                '[DRY-RUN] Scanned %d booking(s); %d would change; %d passenger(s) would be recomputed.',
                $scanned,
                $changedBookings,
                $changedPassengers
            ));
        } else {
            $this->info(sprintf(
                'Scanned %d booking(s); repaired %d; %d passenger(s) recomputed.',
                $scanned,
                $changedBookings,
                $changedPassengers
            ));
        }

        return self::SUCCESS;
    }

    /**
     * Propose per-passenger values at their historic anchors without writing.
     *
     * @return array{0: array<int, array{0: float, 1: float, 2: Passenger}>, 1: bool}
     */
    private function inspectPassengers(Booking $booking, BookingService $service): array
    {
        $changes = [];

        foreach ($booking->passengers as $passenger) {
            if ($passenger->is_cancelled) {
                continue;
            }

            $passenger->setRelation('booking', $booking);

            $old = (float) $passenger->package_value;
            $new = $service->calculatePackageValue($passenger, $passenger->historicAnchor());

            if (abs($old - $new) >= 0.005) {
                $changes[$passenger->id] = [$old, $new, $passenger];
            }
        }

        return $changes;
    }

    /**
     * @param  array<int, array{0: float, 1: float, 2: Passenger}>  $paxChanges
     * @return array{0: array<int, array{0: float, 1: float, 2: Passenger}>, 1: bool}
     */
    private function report(Booking $booking, array $paxChanges, float $oldTotal, float $newTotal, ?float $oldInvoiceTotal, ?float $newInvoiceTotal): array
    {
        $wouldChange = $paxChanges !== []
            || abs($oldTotal - $newTotal) >= 0.005
            || ($oldInvoiceTotal !== null && abs($oldInvoiceTotal - (float) $newInvoiceTotal) >= 0.005);

        if (! $wouldChange) {
            return [$paxChanges, false];
        }

        $this->line(sprintf('Booking #%d (%s):', $booking->id, $booking->invoice_id));
        foreach ($paxChanges as [$old, $new, $passenger]) {
            $this->line(sprintf(
                '  passenger #%d %s %s: %s -> %s',
                $passenger->id,
                $passenger->first_name,
                $passenger->last_name,
                $this->fmt($old),
                $this->fmt($new)
            ));
        }
        $this->line(sprintf('  total_value: %s -> %s', $this->fmt($oldTotal), $this->fmt($newTotal)));
        if ($oldInvoiceTotal !== null && $newInvoiceTotal !== null) {
            $this->line(sprintf('  invoice total_amount: %s -> %s', $this->fmt($oldInvoiceTotal), $this->fmt($newInvoiceTotal)));
        }

        return [$paxChanges, true];
    }

    /**
     * Compute the would-be totals/invoice purely in memory (no writes).
     *
     * @return array{0: array<int, array{0: float, 1: float, 2: Passenger}>, 1: bool}
     */
    private function dryRunBooking(Booking $booking, BookingService $service): array
    {
        $oldTotal = (float) $booking->total_value;
        $oldInvoiceTotal = $booking->invoice ? (float) $booking->invoice->total_amount : null;

        $paxChanges = $this->inspectPassengers($booking, $service);

        // Mirror refreshBookingTotals: stored (or proposed) values + current district charge.
        $passengerTotal = 0.0;
        foreach ($booking->passengers as $passenger) {
            $passengerTotal += isset($paxChanges[$passenger->id])
                ? $paxChanges[$passenger->id][1]
                : (float) $passenger->package_value;
        }

        $fingerprintLocation = $booking->fingerprint_location;
        if ($fingerprintLocation instanceof \BackedEnum) {
            $fingerprintLocation = $fingerprintLocation->value;
        }
        $newTotal = $passengerTotal + $service->getFingerprintCharge($booking->district_id, $fingerprintLocation);

        // Mirror applyDiscount + applyInvoiceDelta.
        $discountType = $booking->discount_type;
        if ($discountType instanceof \BackedEnum) {
            $discountType = $discountType->value;
        }
        $newDiscount = $service->calculateDiscount($newTotal, $discountType ?? 'fixed', (float) ($booking->discount_value ?? 0));
        $oldDiscounted = max(0, $oldTotal - (float) $booking->discount_amount);
        $newDiscounted = max(0, $newTotal - $newDiscount);
        $newInvoiceTotal = $oldInvoiceTotal === null ? null : $oldInvoiceTotal + ($newDiscounted - $oldDiscounted);

        $wouldChange = $paxChanges !== []
            || abs($oldTotal - $newTotal) >= 0.005
            || ($newInvoiceTotal !== null && abs($oldInvoiceTotal - $newInvoiceTotal) >= 0.005);

        if ($wouldChange) {
            $this->line(sprintf('Booking #%d (%s):', $booking->id, $booking->invoice_id));
            foreach ($paxChanges as [$old, $new, $passenger]) {
                $this->line(sprintf(
                    '  passenger #%d %s %s: %s -> %s',
                    $passenger->id,
                    $passenger->first_name,
                    $passenger->last_name,
                    $this->fmt($old),
                    $this->fmt($new)
                ));
            }
            $this->line(sprintf('  total_value: %s -> %s', $this->fmt($oldTotal), $this->fmt($newTotal)));
            if ($oldInvoiceTotal !== null && $newInvoiceTotal !== null) {
                $this->line(sprintf('  invoice total_amount: %s -> %s', $this->fmt($oldInvoiceTotal), $this->fmt($newInvoiceTotal)));
            }
        }

        return [$paxChanges, $wouldChange];
    }

    /**
     * Repair one booking atomically (per-booking transaction, plan 17 §2d).
     *
     * @return array{0: array<int, array{0: float, 1: float, 2: Passenger}>, 1: bool}
     */
    private function repairBooking(Booking $booking, BookingService $service): array
    {
        return DB::transaction(function () use ($booking, $service) {
            $oldTotal = (float) $booking->total_value;
            $oldDiscount = (float) $booking->discount_amount;
            $oldInvoiceTotal = $booking->invoice ? (float) $booking->invoice->total_amount : null;

            $paxChanges = $this->inspectPassengers($booking, $service);

            // Per-passenger historic anchors (null => anchor derivation).
            $service->recalculatePassengerValues($booking);
            $service->refreshBookingTotals($booking);
            $service->applyDiscount($booking);
            $service->applyInvoiceDelta($booking, $oldTotal, $oldDiscount, 'historic_backfill');

            $newTotal = (float) $booking->total_value;
            $newInvoiceTotal = $booking->invoice ? (float) $booking->invoice->total_amount : null;

            return $this->report($booking, $paxChanges, $oldTotal, $newTotal, $oldInvoiceTotal, $newInvoiceTotal);
        });
    }

    private function fmt(float $value): string
    {
        return number_format($value, 2, '.', '');
    }
}
