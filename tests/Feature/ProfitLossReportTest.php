<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProfitLossReportTest extends TestCase
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

    /** @test */
    public function test_profit_loss_report_renders_livewire_component(): void
    {
        $response = $this->get('/reports/profit-loss');
        $response->assertOk();
        $response->assertSee('Profit/Loss Report');
        $response->assertSeeLivewire('report.profit-loss-report-table');
    }

    /** @test */
    public function test_profit_loss_report_renders_empty_state(): void
    {
        Livewire::test('report.profit-loss-report-table')
            ->assertSee('No data found');
    }

    /** @test */
    public function test_profit_loss_report_has_filter_fields(): void
    {
        Livewire::test('report.profit-loss-report-table')
            ->assertSee('From:')
            ->assertSee('To:')
            ->assertSee('Search by Invoice ID')
            ->assertSee('Per Customer')
            ->assertSee('Per Passenger')
            ->assertOk();
    }
}
