<?php

namespace App\Support;

use Carbon\Carbon;
use DateTimeInterface;
use Stringable;

class DateFormatter implements Stringable
{
    /**
     * Resolve the per-user timezone, falling back to the app timezone.
     */
    protected static function userTimezone(): string
    {
        if (auth()->check()) {
            return auth()->user()->timezone ?? config('app.timezone', 'UTC');
        }

        return config('app.timezone', 'UTC');
    }

    /**
     * Convert a date to the user's timezone if it's a Carbon instance.
     */
    protected static function inUserTimezone(Carbon|DateTimeInterface $date): Carbon
    {
        if ($date instanceof Carbon) {
            return $date->copy()->setTimezone(static::userTimezone());
        }

        return Carbon::parse($date)->setTimezone(static::userTimezone());
    }

    /**
     * Format as short display: "15-Jan-2025"
     */
    public static function short(Carbon|DateTimeInterface|string|null $date): string
    {
        return static::format($date, 'd-M-Y');
    }

    /**
     * Format as ISO: "2025-01-15"
     */
    public static function iso(Carbon|DateTimeInterface|string|null $date): string
    {
        return static::format($date, 'Y-m-d');
    }

    /**
     * Format as date+time: "2025-01-15 14:30"
     */
    public static function dateTime(Carbon|DateTimeInterface|string|null $date): string
    {
        return static::format($date, 'Y-m-d H:i');
    }

    /**
     * Format as human-readable: "15 Jan 2025"
     */
    public static function humanReadable(Carbon|DateTimeInterface|string|null $date): string
    {
        return static::format($date, 'd M Y');
    }

    /**
     * Format as human-readable datetime: "15 Jan 2025 14:30"
     */
    public static function humanDateTime(Carbon|DateTimeInterface|string|null $date): string
    {
        return static::format($date, 'd M Y H:i');
    }

    protected static function format(Carbon|DateTimeInterface|string|null $date, string $format): string
    {
        if (is_null($date)) {
            return '-';
        }

        if (is_string($date)) {
            $date = Carbon::parse($date);
        }

        $date = static::inUserTimezone($date);

        return $date->format($format);
    }

    public function __toString(): string
    {
        return '-';
    }
}
