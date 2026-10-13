<?php

namespace App\Console\Commands;

use App\Enums\CancelledBookingStatus;
use App\Models\Booking;
use App\Models\BookingUpdateLog;
use App\Models\PackageUpdateLog;
use App\Models\Passenger;
use App\Models\PassengerUpdateLog;
use App\Models\TicketFareUpdateLog;
use App\Services\BookingService;
use Carbon\CarbonInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Query-scoped repair companion to bookings:sync-financials (plan 17).
 *
 * Finds passengers whose package_value was WRONGLY recalculated - their last
 * package_value change came from a live-price recompute triggered by an
 * unrelated booking/passenger edit - and resets them to the historic replay
 * value at their historicAnchor(). Classification is the PHP port of the
 * wrong-recalculation audit query:
 *
 *  - never flagged (valid triggers): passenger_type / extra_charge /
 *    service_required change on the same row OR on the same passenger within
 *    the [-60s, +10s] request window; a package swap (booking_update_logs row
 *    with package_id) within that window;
 *  - counted only when a fare or package price update exists strictly between
 *    the passenger's anchor and the recalculation event (the "package update"
 *    rule): without price drift live == historic and there is no wrong
 *    difference to reset.
 *
 * Scope vs bookings:sync-financials - that command repairs EVERY stored-vs-
 * historic divergence regardless of cause (type-change drift, extra_charge
 * inclusions, backfills); this one touches only wrongly recalculated
 * passengers. Both write the same target value. Invoice movements carry
 * reason='wrong_recalc_repair'. Cancelled bookings and is_cancelled
 * passengers are never touched (D4). --dry-run reports without writing.
 */
class RepairWrongRecalcs extends Command
{
    /** Seconds before an event that still count as the same request. */
    private const WINDOW_BEFORE = 60;

    /** Seconds after an event that still count as the same request. */
    private const WINDOW_AFTER = 10;

    private const TRIGGER_KEYS = ['passenger_type', 'extra_charge', 'service_required'];

    private const FARE_PRICE_KEYS = ['selling_fare', 'offer_price', 'ticket_type', 'child_fare_percentage', 'infant_fare_percentage'];

    private const PACKAGE_PRICE_KEYS = ['service_charge', 'visa_selling_price_id'];

    protected $signature = 'bookings:repair-wrong-recalcs {--dry-run : Report old -> new values without writing}';

    protected $description = 'Reset package_value for passengers wrongly recalculated from live prices (audit-query scope)';

    public function handle(): int
    {
        $service = app(BookingService::class);
        $dryRun = (bool) $this->option('dry-run');

        $flagged = $this->flaggedPassengers();
        $flaggedCount = $flagged->count();
        $bookingCount = $flagged->pluck('booking_id')->unique()->count();

        $changedBookings = 0;
        $changedPassengers = 0;

        foreach ($flagged->groupBy('booking_id') as $passengers) {
            $booking = Booking::with(['passengers', 'invoice'])->find((int) $passengers->first()->booking_id);
            if (! $booking) {
                continue;
            }

            $changes = $this->bookingChanges($booking, $passengers, $service);
            if ($changes === []) {
                // Already at the correct value (e.g. a second run) - nothing to move.
                continue;
            }

            [$paxChanges, $wouldChange] = $dryRun
                ? $this->dryRunBooking($booking, $changes, $service)
                : $this->repairBooking($booking, $changes, $service);

            if ($wouldChange) {
                $changedBookings++;
                $changedPassengers += count($paxChanges);
            }
        }

        if ($dryRun) {
            $this->info(sprintf(
                '[DRY-RUN] Flagged %d wrongly recalculated passenger(s) across %d booking(s); %d value(s) would change.',
                $flaggedCount,
                $bookingCount,
                $changedPassengers
            ));
        } else {
            $this->info(sprintf(
                'Flagged %d wrongly recalculated passenger(s) across %d booking(s); repaired %d passenger(s) in %d booking(s).',
                $flaggedCount,
                $bookingCount,
                $changedPassengers,
                $changedBookings
            ));
        }

        return self::SUCCESS;
    }

    /**
     * Passengers whose LAST package_value change event classifies as a wrong
     * live-fare recalculation (the audit query), minus D4 exclusions.
     *
     * @return Collection<int, Passenger>
     */
    private function flaggedPassengers(): Collection
    {
        $events = PassengerUpdateLog::query()
            ->where('action', 'updated')
            ->where('old_values', 'like', '%"package_value"%')
            ->where('new_values', 'like', '%"package_value"%')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->filter(fn (PassengerUpdateLog $log) => is_array($log->old_values)
                && array_key_exists('package_value', $log->old_values)
                && is_array($log->new_values)
                && array_key_exists('package_value', $log->new_values));

        if ($events->isEmpty()) {
            return collect();
        }

        $passengers = Passenger::whereIn('id', $events->pluck('passenger_id')->unique()->all())
            ->get()
            ->keyBy('id');

        if ($passengers->isEmpty()) {
            return collect();
        }

        $bookingIds = $passengers->pluck('booking_id')->unique()->all();

        // D4: fully cancelled bookings are out of scope entirely.
        $cancelledBookingIds = Booking::whereIn('id', $bookingIds)
            ->whereHas('cancelledBooking', fn ($q) => $q->where('status', CancelledBookingStatus::CANCELLED->value))
            ->pluck('id')
            ->flip();

        $swapRows = BookingUpdateLog::where('action', 'updated')
            ->whereIn('booking_id', $bookingIds)
            ->where('old_values', 'like', '%"package_id"%')
            ->get()
            ->filter(fn (BookingUpdateLog $log) => is_array($log->old_values)
                && array_key_exists('package_id', $log->old_values))
            ->groupBy('booking_id');

        $paxLogsByPassenger = PassengerUpdateLog::where('action', 'updated')
            ->whereIn('passenger_id', $passengers->keys()->all())
            ->get()
            ->groupBy('passenger_id');

        $fareIds = $passengers
            ->flatMap(fn (Passenger $p) => [$p->ticket_fare_id, $p->ticket_fare_inbound_id, $p->ticket_fare_outbound_id])
            ->filter()
            ->unique()
            ->all();
        $fareLogsByFare = TicketFareUpdateLog::where('action', 'updated')
            ->whereIn('ticket_fare_id', $fareIds)
            ->get()
            ->groupBy('ticket_fare_id');

        $packageIdByBooking = Booking::whereIn('id', $bookingIds)->pluck('package_id', 'id');
        $packageLogsByPackage = PackageUpdateLog::whereIn(
            'package_id',
            $packageIdByBooking->filter()->unique()->all()
        )->get()->groupBy('package_id');

        $lastDecision = [];

        foreach ($events as $event) {
            $passenger = $passengers[$event->passenger_id] ?? null;
            if (! $passenger || $passenger->is_cancelled) {
                continue;
            }
            if (isset($cancelledBookingIds[$passenger->booking_id])) {
                continue;
            }

            $lastSwapAt = $swapRows->get($passenger->booking_id)?->max('created_at');
            $anchor = ($lastSwapAt && $lastSwapAt->gt($passenger->created_at))
                ? $lastSwapAt
                : $passenger->created_at;

            $lastDecision[$passenger->id] = $this->isWrongRecalc($event, $passenger, $anchor, [
                'swaps' => $swapRows->get($passenger->booking_id, collect()),
                'pax_logs' => $paxLogsByPassenger->get($passenger->id, collect()),
                'fare_logs' => $fareLogsByFare,
                'package_logs' => $packageLogsByPackage->get($packageIdByBooking[$passenger->booking_id] ?? null, collect()),
            ]);
        }

        return $passengers->only(array_keys(array_filter($lastDecision)))->values();
    }

    /**
     * The audit query's is_wrong predicate, ported to PHP:
     * !valid_trigger && !part_of_swap && (fare_drift || package_drift).
     */
    private function isWrongRecalc(PassengerUpdateLog $event, Passenger $passenger, CarbonInterface $anchor, array $context): bool
    {
        // Valid trigger on the same row.
        if ($this->hasAnyKey($event->old_values, self::TRIGGER_KEYS)
            || $this->hasAnyKey($event->new_values, self::TRIGGER_KEYS)) {
            return false;
        }

        $windowStart = $event->created_at->copy()->subSeconds(self::WINDOW_BEFORE);
        $windowEnd = $event->created_at->copy()->addSeconds(self::WINDOW_AFTER);

        // Valid trigger on the same passenger within the same request: the
        // controller writes the trigger row and the package_value row as two
        // separate observer rows in the same second.
        $hasNearbyTrigger = $context['pax_logs']->contains(function (PassengerUpdateLog $log) use ($event, $windowStart, $windowEnd) {
            return $log->id !== $event->id
                && $log->created_at->between($windowStart, $windowEnd)
                && ($this->hasAnyKey($log->old_values, self::TRIGGER_KEYS)
                    || $this->hasAnyKey($log->new_values, self::TRIGGER_KEYS));
        });
        if ($hasNearbyTrigger) {
            return false;
        }

        // Package swap within the same request.
        $isSwap = $context['swaps']->contains(
            fn (BookingUpdateLog $log) => $log->created_at->between($windowStart, $windowEnd)
        );
        if ($isSwap) {
            return false;
        }

        // "Package update" rule: price drift strictly between anchor and event.
        $inDriftWindow = fn ($log) => $log->created_at->gt($anchor)
            && $log->created_at->lte($event->created_at);

        foreach ([$passenger->ticket_fare_id, $passenger->ticket_fare_inbound_id, $passenger->ticket_fare_outbound_id] as $fareId) {
            if (! $fareId) {
                continue;
            }
            $fareDrift = ($context['fare_logs']->get($fareId) ?? collect())->contains(
                fn (TicketFareUpdateLog $log) => $inDriftWindow($log)
                    && $this->hasAnyKey($log->old_values, self::FARE_PRICE_KEYS)
            );
            if ($fareDrift) {
                return true;
            }
        }

        return $context['package_logs']->contains(function (PackageUpdateLog $log) use ($inDriftWindow) {
            if (! $inDriftWindow($log)) {
                return false;
            }

            return $log->action === 'fare_updated'
                || ($log->action === 'updated' && $this->hasAnyKey($log->old_values, self::PACKAGE_PRICE_KEYS));
        });
    }

    private function hasAnyKey(mixed $values, array $keys): bool
    {
        if (! is_array($values)) {
            return false;
        }

        return array_intersect($keys, array_keys($values)) !== [];
    }

    /**
     * Proposed historic values for the flagged passengers of one booking.
     *
     * @return array<int, array{0: float, 1: float, 2: Passenger}>
     */
    private function bookingChanges(Booking $booking, Collection $passengers, BookingService $service): array
    {
        $changes = [];

        foreach ($passengers as $passenger) {
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
     * Repair one booking atomically (mirrors SyncBookingFinancials::
     * repairBooking, scoped to the flagged passengers only).
     *
     * @param  array<int, array{0: float, 1: float, 2: Passenger}>  $changes
     * @return array{0: array<int, array{0: float, 1: float, 2: Passenger}>, 1: bool}
     */
    private function repairBooking(Booking $booking, array $changes, BookingService $service): array
    {
        return DB::transaction(function () use ($booking, $changes, $service) {
            $oldTotal = (float) $booking->total_value;
            $oldDiscount = (float) $booking->discount_amount;
            $oldInvoiceTotal = $booking->invoice ? (float) $booking->invoice->total_amount : null;

            foreach ($changes as [, , $passenger]) {
                $service->recalculatePassengerValues($booking, $passenger->historicAnchor(), $passenger);
            }
            $service->refreshBookingTotals($booking);
            $service->applyDiscount($booking);
            $service->applyInvoiceDelta($booking, $oldTotal, $oldDiscount, 'wrong_recalc_repair');

            $newTotal = (float) $booking->total_value;
            $newInvoiceTotal = $booking->invoice ? (float) $booking->invoice->total_amount : null;

            return $this->report($booking, $changes, $oldTotal, $newTotal, $oldInvoiceTotal, $newInvoiceTotal);
        });
    }

    /**
     * Compute the would-be totals/invoice purely in memory (no writes).
     *
     * @param  array<int, array{0: float, 1: float, 2: Passenger}>  $changes
     * @return array{0: array<int, array{0: float, 1: float, 2: Passenger}>, 1: bool}
     */
    private function dryRunBooking(Booking $booking, array $changes, BookingService $service): array
    {
        $oldTotal = (float) $booking->total_value;
        $oldInvoiceTotal = $booking->invoice ? (float) $booking->invoice->total_amount : null;

        // Mirror refreshBookingTotals: stored (or proposed) values + district charge.
        $passengerTotal = 0.0;
        foreach ($booking->passengers as $passenger) {
            $passengerTotal += isset($changes[$passenger->id])
                ? $changes[$passenger->id][1]
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

        return $this->report($booking, $changes, $oldTotal, $newTotal, $oldInvoiceTotal, $newInvoiceTotal);
    }

    /**
     * @param  array<int, array{0: float, 1: float, 2: Passenger}>  $changes
     * @return array{0: array<int, array{0: float, 1: float, 2: Passenger}>, 1: bool}
     */
    private function report(Booking $booking, array $changes, float $oldTotal, float $newTotal, ?float $oldInvoiceTotal, ?float $newInvoiceTotal): array
    {
        $wouldChange = $changes !== []
            || abs($oldTotal - $newTotal) >= 0.005
            || ($oldInvoiceTotal !== null && abs($oldInvoiceTotal - (float) $newInvoiceTotal) >= 0.005);

        if (! $wouldChange) {
            return [$changes, false];
        }

        $this->line(sprintf('Booking #%d (%s):', $booking->id, $booking->invoice_id));
        foreach ($changes as [$old, $new, $passenger]) {
            $this->line(sprintf(
                '  passenger #%d %s %s (passport %s): stored %s -> historic %s',
                $passenger->id,
                $passenger->first_name,
                $passenger->last_name,
                $passenger->passport_no ?? 'n/a',
                $this->fmt($old),
                $this->fmt($new)
            ));
        }
        $this->line(sprintf('  total_value: %s -> %s', $this->fmt($oldTotal), $this->fmt($newTotal)));
        if ($oldInvoiceTotal !== null && $newInvoiceTotal !== null) {
            $this->line(sprintf('  invoice total_amount: %s -> %s', $this->fmt($oldInvoiceTotal), $this->fmt($newInvoiceTotal)));
        }

        return [$changes, true];
    }

    private function fmt(float $value): string
    {
        return number_format($value, 2, '.', '');
    }
}
