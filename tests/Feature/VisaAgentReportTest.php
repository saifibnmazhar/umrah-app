<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Models\VisaAgent;
use App\Models\VisaAgentCost;
use App\Models\VisaSellingPrice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class VisaAgentReportTest extends TestCase
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

    private function seedPrerequisites(): array
    {
        $visaPrice = VisaSellingPrice::create([
            'user_id' => $this->superAdmin->id,
            'selling_price' => 2000.00,
        ]);

        $agent = VisaAgent::create([
            'name' => 'Test Visa Agent',
            'address' => 'Test Address',
            'contacts' => '0123456789',
        ]);

        VisaAgentCost::create([
            'visa_agent_id' => $agent->id,
            'user_id' => $this->superAdmin->id,
            'visa_agent_cost' => 500.00,
        ]);

        return ['visaPrice' => $visaPrice, 'agent' => $agent];
    }

    #[Test]
    public function test_visa_agent_report_renders_livewire_component(): void
    {
        $response = $this->get('/reports/visa-agent');
        $response->assertOk();
        $response->assertSee('Visa Agent Report');
        $response->assertSeeLivewire('report.visa-agent-report-table');
    }

    #[Test]
    public function test_visa_agent_report_renders_empty_state(): void
    {
        Livewire::test('report.visa-agent-report-table')
            ->assertSee('No data found');
    }

    #[Test]
    public function test_visa_agent_report_shows_agent(): void
    {
        $deps = $this->seedPrerequisites();

        Livewire::test('report.visa-agent-report-table')
            ->assertSee('Test Visa Agent')
            ->assertSee('Total Agents:')
            ->assertOk();
    }
}
