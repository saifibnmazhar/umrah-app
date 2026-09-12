<?php

namespace Tests\Feature;

use App\Models\FingerprintDetail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Concerns\CreatesFingerprintFixtures;
use Tests\TestCase;

class FingerprintPaginationTest extends TestCase
{
    use CreatesFingerprintFixtures, RefreshDatabase;

    private ?User $seedUser = null;

    private function seedDetails(int $bookings = 3, int $perBooking = 2): int
    {
        $admin = $this->roleUser('Super Admin');
        $this->seedUser = $admin;
        for ($i = 0; $i < $bookings; $i++) {
            $this->fingerprintFixture($admin, ['passengers' => $perBooking]);
        }

        return $bookings * $perBooking;
    }

    public function test_shell_admin_returns_200(): void
    {
        $admin = $this->roleUser('Super Admin');
        $this->actingAs($admin)->get(route('fingerprint.admin'))->assertOk();
    }

    public function test_shell_staff_returns_200(): void
    {
        $admin = $this->roleUser('Super Admin');
        $this->actingAs($admin)->get(route('fingerprint.staff'))->assertOk();
    }

    public function test_admin_returns_passenger_grain_25(): void
    {
        $total = $this->seedDetails(3, 2);
        $admin = $this->seedUser;

        $response = $this->actingAs($admin)->getJson('/api/fingerprints/admin');

        $response->assertOk()
            ->assertJsonPath('pagination.per_page', 25)
            ->assertJsonPath('pagination.total', $total)
            ->assertJsonPath('summary.total', $total);
        $this->assertCount($total, $response->json('data'));
        $this->assertArrayHasKey('fingerprint_detail_id', $response->json('data.0'));
    }

    public function test_staff_returns_passenger_grain_25(): void
    {
        $total = $this->seedDetails(3, 2);
        $admin = $this->seedUser;

        $response = $this->actingAs($admin)->getJson('/api/fingerprints/staff');

        $response->assertOk()
            ->assertJsonPath('pagination.per_page', 25)
            ->assertJsonPath('pagination.total', $total);
        $this->assertCount($total, $response->json('data'));
    }

    public function test_admin_stays_bounded_query_count(): void
    {
        $this->seedDetails(5, 2);
        $admin = $this->seedUser;

        DB::enableQueryLog();
        $response = $this->actingAs($admin)->getJson('/api/fingerprints/admin');
        DB::disableQueryLog();

        $response->assertOk();
        $this->assertLessThan(30, count(DB::getQueryLog()), 'Admin API should stay bounded, no N+1');
        $this->assertSame(FingerprintDetail::count(), $response->json('pagination.total'));
    }
}
