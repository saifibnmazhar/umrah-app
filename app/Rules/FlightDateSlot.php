<?php

namespace App\Rules;

use App\Models\FlightDateGap;
use Carbon\Carbon;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates a flight date range pair (flight_date_from + flight_date_to).
 *
 * Attach to the `flight_date_to` attribute, passing the paired `from` value.
 * Enforces the client-side slot logic server-side:
 *  - same calendar month, exactly 1-10 | 11-20 | 21-lastDay
 *  - `from` must not be earlier than the slot containing (today + finalGap),
 *    where finalGap = defaultGap (flight_date_gaps.gap) + additionalGap (route.additional_gap)
 *
 * Missing/empty pair members hard-fail so empty submissions cannot fall
 * through to placeholder defaults.
 */
class FlightDateSlot implements ValidationRule
{
    public function __construct(
        private ?string $from = null,
        private int $defaultGap = 30,
        private int $additionalGap = 0,
        private ?Carbon $today = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $to = is_string($value) ? $value : null;

        if ($this->from === null || $this->from === '' || $to === null || $to === '') {
            $fail('Flight date range is required. Use 1-10, 11-20, or 21-last day of the same month.');

            return;
        }

        if (! self::isValidSlot($this->from, $to)) {
            $fail('Invalid flight date range. Use 1-10, 11-20, or 21-last day of the same month.');

            return;
        }

        $minFrom = self::minimumFromDate($this->defaultGap, $this->additionalGap, $this->today);

        if (Carbon::parse($this->from)->startOfDay()->lt($minFrom)) {
            $fail('Flight date range is too early. It must be on or after '.$minFrom->format('Y-m-d').'.');
        }
    }

    /**
     * Slot shape check only (no gap logic): same Y-m and exactly
     * 1-10 | 11-20 | 21-lastDay (last day derived via days-in-month, Feb-aware).
     */
    public static function isValidSlot(?string $from, ?string $to): bool
    {
        if (! $from || ! $to) {
            return false;
        }

        try {
            $fromDate = Carbon::createFromFormat('Y-m-d', $from);
            $toDate = Carbon::createFromFormat('Y-m-d', $to);
        } catch (\Throwable) {
            return false;
        }

        if ($fromDate->format('Y-m-d') !== $from || $toDate->format('Y-m-d') !== $to) {
            return false;
        }

        if ($fromDate->format('Y-m') !== $toDate->format('Y-m')) {
            return false;
        }

        $fromDay = (int) $fromDate->format('j');
        $toDay = (int) $toDate->format('j');
        $lastDay = (int) $fromDate->format('t');

        return ($fromDay === 1 && $toDay === 10)
            || ($fromDay === 11 && $toDay === 20)
            || ($fromDay === 21 && $toDay === $lastDay);
    }

    /**
     * Earliest allowed flight_date_from: first day of the slot containing
     * (today + defaultGap + additionalGap), mirroring booking.js bucketing:
     * day 1-5 -> slot 1-10, 6-15 -> slot 11-20, 16-25 -> slot 21-lastDay,
     * 26-31 -> slot 1-10 of the next month.
     */
    public static function minimumFromDate(int $defaultGap, int $additionalGap = 0, ?Carbon $today = null): Carbon
    {
        $today = ($today ?? Carbon::today())->copy()->startOfDay();
        $expected = $today->copy()->addDays($defaultGap + $additionalGap);
        $day = (int) $expected->format('j');

        if ($day >= 1 && $day <= 5) {
            $monthOffset = 0;
            $slot = 0;
        } elseif ($day <= 15) {
            $monthOffset = 0;
            $slot = 1;
        } elseif ($day <= 25) {
            $monthOffset = 0;
            $slot = 2;
        } else {
            $monthOffset = 1;
            $slot = 0;
        }

        $startDay = [1, 11, 21][$slot];

        return $expected->copy()->startOfMonth()->addMonths($monthOffset)->addDays($startDay - 1)->startOfDay();
    }

    public static function slotEndFor(Carbon $from): Carbon
    {
        $day = (int) $from->format('j');

        if ($day === 1) {
            return $from->copy()->day(10)->startOfDay();
        }

        if ($day === 11) {
            return $from->copy()->day(20)->startOfDay();
        }

        return $from->copy()->endOfMonth()->startOfDay();
    }

    /**
     * Build a [from, to] pair (Y-m-d strings) that passes both the slot
     * shape and the gap rule. For tests and seed scripts.
     */
    public static function validPairForTesting(int $additionalGap = 0, ?int $defaultGap = null, ?Carbon $today = null): array
    {
        $defaultGap ??= FlightDateGap::first()?->gap ?? 30;
        $from = self::minimumFromDate($defaultGap, $additionalGap, $today);

        return [$from->format('Y-m-d'), self::slotEndFor($from)->format('Y-m-d')];
    }
}
