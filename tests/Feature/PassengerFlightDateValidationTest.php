<?php

namespace Tests\Feature;

use App\Http\Controllers\PassengerController;
use PHPUnit\Framework\TestCase as BaseTestCase;

class PassengerFlightDateValidationTest extends BaseTestCase
{
    private function isValidFlightDateGroup(?string $from, ?string $to): bool
    {
        $reflection = new \ReflectionClass(PassengerController::class);
        $method = $reflection->getMethod('isValidFlightDateGroup');

        $controller = $reflection->newInstanceWithoutConstructor();

        return $method->invoke($controller, $from, $to);
    }

    public function test_valid_group_1_10(): void
    {
        $this->assertTrue($this->isValidFlightDateGroup('2026-09-01', '2026-09-10'));
    }

    public function test_valid_group_11_20(): void
    {
        $this->assertTrue($this->isValidFlightDateGroup('2026-09-11', '2026-09-20'));
    }

    public function test_valid_group_21_last_day(): void
    {
        $this->assertTrue($this->isValidFlightDateGroup('2026-09-21', '2026-09-30'));
    }

    public function test_valid_group_21_last_day_on_short_month(): void
    {
        $this->assertTrue($this->isValidFlightDateGroup('2026-02-21', '2026-02-28'));
    }

    public function test_valid_group_21_last_day_on_leap_month(): void
    {
        $this->assertTrue($this->isValidFlightDateGroup('2028-02-21', '2028-02-29'));
    }

    public function test_invalid_from_day_rejected(): void
    {
        $this->assertFalse($this->isValidFlightDateGroup('2026-09-05', '2026-09-10'));
    }

    public function test_invalid_to_day_rejected(): void
    {
        $this->assertFalse($this->isValidFlightDateGroup('2026-09-01', '2026-09-15'));
    }

    public function test_cross_month_group_rejected(): void
    {
        $this->assertFalse($this->isValidFlightDateGroup('2026-09-21', '2026-10-30'));
    }

    public function test_cross_year_group_rejected(): void
    {
        $this->assertFalse($this->isValidFlightDateGroup('2026-12-21', '2027-01-31'));
    }

    public function test_misaligned_but_valid_days_rejected(): void
    {
        $this->assertFalse($this->isValidFlightDateGroup('2026-09-01', '2026-09-20'));
        $this->assertFalse($this->isValidFlightDateGroup('2026-09-11', '2026-09-30'));
    }

    public function test_null_or_missing_rejected(): void
    {
        $this->assertFalse($this->isValidFlightDateGroup(null, '2026-09-10'));
        $this->assertFalse($this->isValidFlightDateGroup('2026-09-01', null));
        $this->assertFalse($this->isValidFlightDateGroup(null, null));
    }

    public function test_malformed_date_rejected(): void
    {
        $this->assertFalse($this->isValidFlightDateGroup('not-a-date', '2026-09-10'));
        $this->assertFalse($this->isValidFlightDateGroup('2026-09-01', ''));
    }
}
