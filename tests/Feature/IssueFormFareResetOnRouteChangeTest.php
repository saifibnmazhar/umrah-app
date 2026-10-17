<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Changing Route Type (or Flight Type) clears the selected Ticket, so the Net
 * Fare it populated must be cleared with it instead of holding a stale value.
 */
class IssueFormFareResetOnRouteChangeTest extends TestCase
{
    private function bookingsIndex(): string
    {
        return (string) file_get_contents(resource_path('views/bookings/index.blade.php'));
    }

    private function methodBody(string $html, string $start, string $end): string
    {
        $i = strpos($html, $start);
        $this->assertNotFalse($i, "start marker not found: {$start}");
        $j = strpos($html, $end, $i);
        $this->assertNotFalse($j, "end marker not found: {$end}");

        return substr($html, $i, $j - $i);
    }

    public function test_route_or_flight_type_change_clears_net_fare(): void
    {
        $body = $this->methodBody($this->bookingsIndex(), 'handleRouteTypeOrFlightTypeChange() {', 'handleAirlineChange() {');

        $this->assertStringContainsString(
            "this.ticketFareForm.ticket_option = '';",
            $body,
            'the Ticket field must still be cleared'
        );
        $this->assertStringContainsString(
            "this.ticketFareForm.net_fare = '';",
            $body,
            'Net Fare (SAR) must be emptied when the ticket is cleared'
        );
        $this->assertStringContainsString(
            "this.ticketFareForm.net_fare_bdt = '';",
            $body,
            'Net Fare (BDT) must be emptied when the ticket is cleared'
        );
    }
}
