<?php

namespace Tests\Feature;

use App\Models\FlightDateGap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FlightDateGapSeedTest extends TestCase
{
    use RefreshDatabase;

    public function test_seed_creates_multiple_flight_date_gaps(): void
    {
        $this->seed();

        $gaps = FlightDateGap::pluck('gap')->toArray();

        $this->assertContains(7, $gaps, '7-day gap missing from seed data');
        $this->assertContains(30, $gaps, '30-day gap missing from seed data');
    }

    public function test_seed_flight_date_gaps_are_positive_integers(): void
    {
        $this->seed();

        $gaps = FlightDateGap::all();
        foreach ($gaps as $gap) {
            $this->assertIsInt($gap->gap, 'Gap is not an integer');
            $this->assertGreaterThan(0, $gap->gap, 'Gap must be positive');
        }
    }

    public function test_seed_flight_date_gaps_cover_short_stay_options(): void
    {
        $this->seed();

        $gaps = FlightDateGap::pluck('gap')->toArray();

        // Short stays: 7, 10, 14 days should be available for passenger creation form
        $this->assertContains(7, $gaps, '7-day option missing for short stay');
        $this->assertContains(10, $gaps, '10-day option missing for short stay');
        $this->assertContains(14, $gaps, '14-day option missing for short stay');
    }
}
