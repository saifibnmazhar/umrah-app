<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\TicketAgent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TicketAgentReportTest extends TestCase
{
    use RefreshDatabase;

    private $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        $superAdminRole = Role::firstOrCreate(['name' => 'Super Admin']);
        $this->superAdmin = User::factory()->create(['email' => 'admin@test.com', 'password' => 'password']);
        $this->superAdmin->roles()->attach($superAdminRole);
        $this->actingAs($this->superAdmin);
    }

    #[Test]
    public function test_ticket_agent_report_renders_livewire_component(): void
    {
        $response = $this->get('/reports/ticket-agent');
        $response->assertOk();
        $response->assertSee('Ticket Agent Report');
        $response->assertSeeLivewire('report.ticket-agent-report-table');
    }

    #[Test]
    public function test_ticket_agent_report_renders_empty_state(): void
    {
        Livewire::test('report.ticket-agent-report-table')
            ->assertSee('No agents found matching your criteria.');
    }

    #[Test]
    public function test_ticket_agent_report_shows_agent(): void
    {
        $agent = TicketAgent::create([
            'name' => 'Test Ticket Agent',
            'address' => 'Test Address',
            'contacts' => '0123456789',
        ]);

        Livewire::test('report.ticket-agent-report-table')
            ->assertSee('Test Ticket Agent')
            ->assertSee('Total Agents:')
            ->assertSee('All Agents');
    }
}
