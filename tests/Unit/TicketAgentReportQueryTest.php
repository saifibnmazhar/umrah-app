<?php

namespace Tests\Unit;

use App\Models\TicketAgent;
use App\Queries\TicketAgentReportQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketAgentReportQueryTest extends TestCase
{
    use RefreshDatabase;

    public function test_data_returns_empty_result_when_no_agents(): void
    {
        $result = (new TicketAgentReportQuery)->data(null, '2025-01-01', '2025-12-31');

        $this->assertCount(0, $result['data']);
        $this->assertSame(0, $result['summary']['totalAgents']);
        $this->assertSame(0, $result['summary']['totalPayable']);
        $this->assertSame(0, $result['summary']['totalPaid']);
    }

    public function test_data_with_date_range_returns_structure(): void
    {
        $agent = TicketAgent::create(['name' => 'Test Agent', 'address' => 'Addr', 'contacts' => 'C']);

        $result = (new TicketAgentReportQuery)->data(null, '2025-01-01', '2025-12-31');

        $this->assertCount(1, $result['data']);
        $this->assertSame($agent->id, $result['data'][0]['id']);
        $this->assertSame('Test Agent', $result['data'][0]['name']);
        $this->assertArrayHasKey('payable', $result['data'][0]);
        $this->assertArrayHasKey('paid', $result['data'][0]);
        $this->assertArrayHasKey('due', $result['data'][0]);
        $this->assertArrayHasKey('transactions', $result['data'][0]);
        $this->assertSame(1, $result['summary']['totalAgents']);
    }

    public function test_data_filters_by_agent_id(): void
    {
        $agent1 = TicketAgent::create(['name' => 'Agent 1', 'address' => 'A', 'contacts' => 'C']);
        TicketAgent::create(['name' => 'Agent 2', 'address' => 'A', 'contacts' => 'C']);

        $result = (new TicketAgentReportQuery)->data($agent1->id, '2025-01-01', '2025-12-31');

        $this->assertCount(1, $result['data']);
        $this->assertSame($agent1->id, $result['data'][0]['id']);
    }

    public function test_data_defaults_to_current_month_when_dates_null(): void
    {
        TicketAgent::create(['name' => 'Test Agent', 'address' => 'Addr', 'contacts' => 'C']);

        $result = (new TicketAgentReportQuery)->data(null);

        $this->assertCount(1, $result['data']);
        $this->assertSame(1, $result['summary']['totalAgents']);
    }

    public function test_query_methods_are_available(): void
    {
        $query = new TicketAgentReportQuery;

        $this->assertTrue(method_exists($query, 'data'));
    }
}
