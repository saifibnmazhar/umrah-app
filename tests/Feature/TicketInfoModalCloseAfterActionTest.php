<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Any action launched from the Ticket Info modal (Edit fare, Re-Issue,
 * Refund, Void) must close the modal on success.
 *
 * The modal tracks its passenger by a positional index into
 * passengersTicketData, which goes stale after loadPassengerData()
 * re-fetches the list (filtered rows shift), leaving the modal open on
 * another passenger. Closing unconditionally on success guarantees that
 * never happens.
 */
class TicketInfoModalCloseAfterActionTest extends TestCase
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

    public function test_void_always_closes_ticket_info_modal_after_success(): void
    {
        $body = $this->methodBody($this->bookingsIndex(), 'async handleTicketVoid(', 'handleReIssueSarInput(');

        $this->assertStringContainsString('this.isTicketInfoModalOpen = false;', $body,
            'void success must close the Ticket Info modal');
        $this->assertStringNotContainsString('viewableTickets(this.ticketInfoPassengerIndex)', $body,
            'void must not keep the modal open based on a stale positional index');
    }

    public function test_refund_closes_ticket_info_modal_after_success(): void
    {
        $body = $this->methodBody($this->bookingsIndex(), 'handleRefundSubmit() {', 'async handleTicketVoid(');

        $this->assertStringContainsString('this.isTicketInfoModalOpen = false;', $body,
            'refund success must close the Ticket Info modal');
    }

    public function test_reissue_closes_ticket_info_modal_after_success(): void
    {
        $body = $this->methodBody($this->bookingsIndex(), 'handleReIssueSubmit() {', 'handleTicketFareRouteTypeChange() {');

        $this->assertStringContainsString('this.isTicketInfoModalOpen = false;', $body,
            're-issue success must close the Ticket Info modal');
    }

    public function test_ticket_fare_edit_closes_ticket_info_modal_after_success(): void
    {
        $body = $this->methodBody($this->bookingsIndex(), 'handleTicketFareSubmit() {', 'handleRouteTypeOrFlightTypeChange() {');

        $this->assertGreaterThanOrEqual(2, substr_count($body, 'this.isTicketInfoModalOpen = false;'),
            'both ticket-fare success paths must close the Ticket Info modal');
    }
}
