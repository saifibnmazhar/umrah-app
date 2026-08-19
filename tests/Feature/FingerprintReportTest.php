<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FingerprintReportTest extends TestCase
{
    use RefreshDatabase;

    private $superAdmin;

    private $branches;

    protected function setUp(): void
    {
        parent::setUp();
        $superAdminRole = Role::firstOrCreate(['name' => 'Super Admin']);
        $this->superAdmin = User::factory()->create(['email' => 'admin@test.com', 'password' => 'password']);
        $this->superAdmin->roles()->attach($superAdminRole);
        $this->actingAs($this->superAdmin);

        Branch::insertOrIgnore([
            ['name' => 'Central HQ', 'branch_code' => 'CB01', 'location' => 'BD', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Riyadh Office', 'branch_code' => 'RO02', 'location' => 'KSA', 'created_at' => now(), 'updated_at' => now()],
        ]);
        $this->branches = Branch::orderBy('id')->get();
    }

    /** @test */
    public function test_fingerprint_report_renders_livewire_component(): void
    {
        $response = $this->get('/reports/fingerprint');
        $response->assertOk();
        $response->assertSee('Fingerprint Report');
        $response->assertSeeLivewire('report.fingerprint-report-table');
    }

    /** @test */
    public function test_fingerprint_report_renders_empty_state(): void
    {
        Livewire::test('report.fingerprint-report-table')
            ->assertSee('No fingerprint records found')
            ->assertSee('Total PAX');
    }

    /** @test */
    public function test_fingerprint_report_has_filter_fields(): void
    {
        Livewire::test('report.fingerprint-report-table')
            ->assertSee('Search by Invoice No')
            ->assertSee('Booking Date')
            ->assertSee('Completion Date')
            ->assertSee('Branch')
            ->assertOk();
    }
}
