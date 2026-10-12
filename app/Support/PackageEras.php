<?php

namespace App\Support;

use App\Models\BookingUpdateLog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Pure reconstruction of a booking's package "eras" from the append-only
 * booking_update_logs stream (plan 17 amendment: earliest-era anchoring).
 *
 * An era is the interval during which one package was active on the booking.
 * Eras are rebuilt on demand — never stored — so command re-runs stay
 * idempotent by construction; the log rows are immutable facts.
 *
 * Reconstruction rules (evidence-closed edge cases, see plan 17):
 * - The INITIAL package comes from the booking's `created` row, else the
 *   first logged swap's old package. With no package_id mention at all the
 *   booking has never swapped, so the current package is the initial one.
 * - The current package's era START is the earliest moment it became
 *   active: booking creation (if it was the initial package) or any swap
 *   that targeted it. Earliest wins — that is what makes a swap-back
 *   restore first-era prices.
 * - Swaps before 2026-07-14 (booking_update_logs inception) are unlogged;
 *   their era boundaries fall back as described above (created_at).
 */
class PackageEras
{
    /**
     * The booking log rows relevant to era reconstruction (creation snapshot
     * + package swaps), chronological. One query per booking (L4 budget).
     *
     * @return Collection<int, BookingUpdateLog>
     */
    public static function logsFor(int $bookingId): Collection
    {
        return BookingUpdateLog::where('booking_id', $bookingId)
            ->whereIn('action', ['created', 'updated'])
            ->orderBy('created_at')
            ->get()
            ->filter(fn (BookingUpdateLog $log) => self::mentionsPackage($log))
            ->values();
    }

    /**
     * When the current package's EARLIEST era began, or null when the logs
     * never show it active (incoherent history — caller falls back).
     *
     * @param  iterable<int, BookingUpdateLog>  $logs
     */
    public static function eraStart(\DateTimeInterface|string $bookingCreatedAt, int|string|null $currentPackageId, iterable $logs): ?Carbon
    {
        $current = $currentPackageId === null ? null : (int) $currentPackageId;
        if ($current === null) {
            return null;
        }

        $rows = self::sorted($logs);

        // No package_id anywhere in the history ⇒ never swapped ⇒ the
        // current package has been active since booking creation.
        $initial = self::initialPackageId($rows) ?? $current;

        $earliest = null;
        if ($initial === $current) {
            $earliest = Carbon::parse($bookingCreatedAt);
        }

        foreach ($rows as $log) {
            if ($log->action !== 'updated' || ! is_array($log->new_values)
                || ! array_key_exists('package_id', $log->new_values)) {
                continue;
            }
            if ((int) $log->new_values['package_id'] !== $current) {
                continue;
            }

            $at = $log->created_at;
            if ($earliest === null || $at->lt($earliest)) {
                $earliest = $at;
            }
        }

        return $earliest;
    }

    /**
     * The moment whose prices govern a package_value computed against the
     * booking's current package: max(earliest era start, own creation).
     * The passenger floor keeps late additions at their add-time prices;
     * an unknown era falls back to the passenger's creation (pre-amendment
     * no-swap behavior).
     *
     * @param  iterable<int, BookingUpdateLog>  $logs
     */
    public static function anchorAt(\DateTimeInterface|string $bookingCreatedAt, int|string|null $currentPackageId, iterable $logs, \DateTimeInterface|string $passengerCreatedAt): Carbon
    {
        $eraStart = self::eraStart($bookingCreatedAt, $currentPackageId, $logs);
        $passengerAt = Carbon::parse($passengerCreatedAt);

        if ($eraStart === null) {
            return $passengerAt;
        }

        return $passengerAt->gt($eraStart) ? $passengerAt : $eraStart;
    }

    /**
     * Every package id the booking has been on (initial + every swap target
     * + the current one), sorted — the "was this package used before?"
     * oracle for the pre-save swap preview.
     *
     * @param  iterable<int, BookingUpdateLog>  $logs
     * @return array<int, int>
     */
    public static function usedPackageIds(int|string|null $currentPackageId, iterable $logs): array
    {
        $ids = [];

        if ($currentPackageId !== null) {
            $ids[(int) $currentPackageId] = true;
        }

        $rows = self::sorted($logs);
        $initial = self::initialPackageId($rows);
        if ($initial === null && $currentPackageId !== null) {
            $initial = (int) $currentPackageId; // never swapped — current is initial
        }
        if ($initial !== null) {
            $ids[$initial] = true;
        }

        foreach ($rows as $log) {
            if ($log->action === 'updated' && is_array($log->new_values)
                && array_key_exists('package_id', $log->new_values)) {
                $ids[(int) $log->new_values['package_id']] = true;
            }
        }

        $ids = array_keys($ids);
        sort($ids);

        return $ids;
    }

    /**
     * @param  iterable<int, BookingUpdateLog>  $logs
     * @return Collection<int, BookingUpdateLog>
     */
    private static function sorted(iterable $logs): Collection
    {
        return collect($logs)
            ->filter(fn ($log) => $log->created_at)
            ->sortBy('created_at')
            ->values();
    }

    /**
     * The booking's package at creation: the `created` snapshot row wins
     * over the first logged swap's origin (a created row can sort after
     * early swaps when fixtures backdate swap timestamps).
     *
     * @param  Collection<int, BookingUpdateLog>  $rows
     */
    private static function initialPackageId(Collection $rows): ?int
    {
        $created = $rows->first(fn (BookingUpdateLog $log) => $log->action === 'created'
            && is_array($log->new_values)
            && array_key_exists('package_id', $log->new_values));
        if ($created) {
            return (int) $created->new_values['package_id'];
        }

        $firstSwap = $rows->first(fn (BookingUpdateLog $log) => $log->action === 'updated'
            && is_array($log->old_values)
            && array_key_exists('package_id', $log->old_values));

        return $firstSwap ? (int) $firstSwap->old_values['package_id'] : null;
    }

    private static function mentionsPackage(BookingUpdateLog $log): bool
    {
        if ($log->action === 'created') {
            return is_array($log->new_values) && array_key_exists('package_id', $log->new_values);
        }

        if ($log->action !== 'updated') {
            return false;
        }

        return (is_array($log->old_values) && array_key_exists('package_id', $log->old_values))
            || (is_array($log->new_values) && array_key_exists('package_id', $log->new_values));
    }
}
