<?php

namespace Tests\Unit;

use App\Support\DateFormatter;
use Carbon\Carbon;
use Tests\TestCase;

class DateFormatterTest extends TestCase
{
    public function test_short_formats_as_d_M_Y(): void
    {
        $date = Carbon::create(2025, 1, 15, 14, 30, 0);

        $this->assertEquals('15-Jan-2025', DateFormatter::short($date));
    }

    public function test_short_accepts_string(): void
    {
        $this->assertEquals('15-Jan-2025', DateFormatter::short('2025-01-15'));
    }

    public function test_short_accepts_null(): void
    {
        $this->assertEquals('-', DateFormatter::short(null));
    }

    public function test_iso_formats_as_Y_m_d(): void
    {
        $date = Carbon::create(2025, 1, 15, 14, 30, 0);

        $this->assertEquals('2025-01-15', DateFormatter::iso($date));
    }

    public function test_iso_accepts_null(): void
    {
        $this->assertEquals('-', DateFormatter::iso(null));
    }

    public function test_date_time_formats_as_Y_m_d_H_i(): void
    {
        $date = Carbon::create(2025, 1, 15, 14, 30, 45);

        $this->assertEquals('2025-01-15 14:30', DateFormatter::dateTime($date));
    }

    public function test_date_time_accepts_null(): void
    {
        $this->assertEquals('-', DateFormatter::dateTime(null));
    }
}
