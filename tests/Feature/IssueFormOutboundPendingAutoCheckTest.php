<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * In the Issue Ticket form an inbound-only route leaves the outbound leg
 * unissued. Picking a ticket in that state must auto-tick "Outbound Ticket
 * Pending"; leaving the inbound-only route must clear it again.
 */
class IssueFormOutboundPendingAutoCheckTest extends TestCase
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

    public function test_selecting_a_ticket_on_one_way_inbound_checks_outbound_pending(): void
    {
        $body = $this->methodBody($this->bookingsIndex(), 'handleTicketOptionChange() {', 'get isSectorChangeReason() {');

        $this->assertStringContainsString(
            "this.ticketFareForm.route_type === 'One Way-Inbound'",
            $body,
            'ticket selection must only auto-check on an inbound-only route'
        );
        $this->assertStringContainsString(
            'this.ticketFareForm.outbound_pending = true;',
            $body,
            'selecting a ticket on One Way-Inbound must tick Outbound Ticket Pending'
        );
    }

    public function test_leaving_one_way_inbound_unchecks_outbound_pending(): void
    {
        $body = $this->methodBody($this->bookingsIndex(), 'handleTicketFareRouteTypeChange() {', 'handleTicketTypeChange() {');

        $this->assertStringContainsString(
            "routeType !== 'One Way-Inbound'",
            $body,
            'route change must detect leaving the inbound-only route'
        );
        $this->assertStringContainsString(
            'f.outbound_pending = false;',
            $body,
            'leaving One Way-Inbound must clear Outbound Ticket Pending'
        );
    }
}
