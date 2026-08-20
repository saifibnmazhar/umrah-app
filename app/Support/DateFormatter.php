<?php

namespace App\Support;

use Carbon\Carbon;
use DateTimeInterface;
use Stringable;

class DateFormatter implements Stringable
{
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

    protected static function format(Carbon|DateTimeInterface|string|null $date, string $format): string
    {
        if (is_null($date)) {
            return '-';
        }

        if (is_string($date)) {
            $date = Carbon::parse($date);
        }

        return $date->format($format);
    }

    public function __toString(): string
    {
        return '-';
    }
}
