<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * On the Ticket Fare create form the Class and Route selects are driven by the
 * selected Airline: with no airline chosen neither list (nor their "+ Add New"
 * actions) offers anything, and routes narrow to the chosen airline.
 */
class TicketFareCreateAirlineFilteringTest extends TestCase
{
    private function createView(): string
    {
        return (string) file_get_contents(resource_path('views/ticket-fares/create.blade.php'));
    }

    private function body(string $html, string $start, string $end): string
    {
        $i = strpos($html, $start);
        $this->assertNotFalse($i, "start marker not found: {$start}");
        $j = strpos($html, $end, $i);
        $this->assertNotFalse($j, "end marker not found: {$end}");

        return substr($html, $i, $j - $i);
    }

    public function test_route_options_carry_their_airline_id(): void
    {
        $html = $this->createView();

        $this->assertStringContainsString(
            'data-airline-id="{{ $route->airline_id }}"',
            $html,
            'route options must expose the airline they belong to'
        );
    }

    public function test_class_list_hides_everything_without_an_airline(): void
    {
        $body = $this->body($this->createView(), 'function filterClasses() {', 'function handleAirlineChange() {');

        $this->assertStringContainsString("option.value === '__add_new__'", $body, 'the Add New Class action must be handled');
        $this->assertStringContainsString("option.style.display = airlineId ? '' : 'none';", $body,
            'class options and Add New must hide when no airline is selected');
        $this->assertStringContainsString('optionAirlineId === airlineId', $body,
            'classes must be matched to the selected airline');
    }

    public function test_route_list_is_filtered_by_airline(): void
    {
        $body = $this->body($this->createView(), 'function filterRoutes() {', 'function handleRouteTypeChange() {');

        $this->assertStringContainsString("option.getAttribute('data-airline-id')", $body,
            'routes must read the airline id');
        $this->assertStringContainsString('&& matchesAirline)', $body,
            'the airline must be part of the route show/hide decision');
        $this->assertStringContainsString("option.value === '__add_new__'", $body, 'the Add New Route action must be handled');
        $this->assertStringContainsString("option.style.display = airlineId ? '' : 'none';", $body,
            'the Add New Route action must hide when no airline is selected');
    }

    public function test_changing_airline_refreshes_both_lists(): void
    {
        $body = $this->body($this->createView(), 'function handleAirlineChange() {', 'document.addEventListener');

        $this->assertStringContainsString('filterClasses();', $body);
        $this->assertStringContainsString('filterRoutes();', $body);
    }
}
