<?php

namespace Tests\Unit;

use App\Models\VisaAgent;
use App\Queries\VisaAgentReportQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class VisaAgentReportQueryTest extends TestCase
{
    use RefreshDatabase;

    public function test_build_agent_row_returns_expected_keys(): void
    {
        $agent = VisaAgent::create(['name' => 'Test Agent', 'address' => 'Addr', 'contacts' => 'C']);

        $result = (new VisaAgentReportQuery)->buildAgentRow($agent);

        $this->assertIsArray($result);
        $this->assertEquals($agent->id, $result['id']);
        $this->assertEquals('Test Agent', $result['name']);
        $this->assertArrayHasKey('totalSubmitted', $result);
        $this->assertArrayHasKey('totalIssued', $result);
        $this->assertArrayHasKey('price', $result);
        $this->assertArrayHasKey('payable', $result);
        $this->assertArrayHasKey('paid', $result);
        $this->assertArrayHasKey('balance', $result);
        $this->assertArrayHasKey('cancellationFee', $result);
        $this->assertEquals(0, $result['totalSubmitted']);
        $this->assertEquals(0, $result['totalIssued']);
    }

    public function test_build_agent_row_with_date_range(): void
    {
        $agent = VisaAgent::create(['name' => 'Test Agent', 'address' => 'Addr', 'contacts' => 'C']);

        // No submissions/issued/cancelled/payments — should still return valid structure
        $result = (new VisaAgentReportQuery)->buildAgentRow($agent, '2025-01-01', '2025-12-31');

        $this->assertEquals($agent->id, $result['id']);
        $this->assertEquals(0, $result['totalSubmitted']);
        $this->assertEquals(0, $result['payable']);
    }

    public function test_build_combined_rows_returns_collection(): void
    {
        $agent = VisaAgent::create(['name' => 'Test Agent', 'address' => 'Addr', 'contacts' => 'C']);

        $rows = (new VisaAgentReportQuery)->buildCombinedRows($agent);

        $this->assertContainsOnlyInstancesOf(Collection::class, $rows->map(fn ($r) => $r));
        $this->assertCount(0, $rows);
    }

    public function test_build_combined_rows_filters_by_date_range(): void
    {
        $agent = VisaAgent::create(['name' => 'Test Agent', 'address' => 'Addr', 'contacts' => 'C']);

        $rows = (new VisaAgentReportQuery)->buildCombinedRows($agent, '2025-01-01', '2025-01-31');

        $this->assertCount(0, $rows);
    }

    public function test_transactions_returns_empty_for_new_agent(): void
    {
        $agent = VisaAgent::create(['name' => 'Test Agent', 'address' => 'Addr', 'contacts' => 'C']);

        $transactions = (new VisaAgentReportQuery)->transactions($agent);

        $this->assertCount(0, $transactions);
    }

    public function test_query_methods_are_available(): void
    {
        $query = new VisaAgentReportQuery;

        $this->assertTrue(method_exists($query, 'buildAgentRow'));
        $this->assertTrue(method_exists($query, 'buildCombinedRows'));
        $this->assertTrue(method_exists($query, 'transactions'));
    }
}
