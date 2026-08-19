<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BookingCancellationReportTest extends TestCase
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
    public function test_booking_cancellation_report_renders_livewire_component(): void
    {
        $response = $this->get('/reports/booking-cancellation');
        $response->assertOk();
        $response->assertSee('Booking Cancellation Report');
        $response->assertSeeLivewire('report.booking-cancellation-report-table');
    }

    #[Test]
    public function test_booking_cancellation_report_renders_empty_state(): void
    {
        Livewire::test('report.booking-cancellation-report-table')
            ->assertSee('No cancellation records found');
    }

    #[Test]
    public function test_booking_cancellation_report_has_branch_filter(): void
    {
        Branch::create([
            'name' => 'Test Branch',
            'address' => 'Address',
            'contacts' => '0123456789',
            'location' => 'KSA',
            'booking_branch_id' => null,
            'office_id' => null,
        ]);

        Livewire::test('report.booking-cancellation-report-table')
            ->assertSee('All Branches')
            ->assertSee('Test Branch');
    }
}
