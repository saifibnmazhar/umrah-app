<?php

namespace Tests\Feature;

use App\Models\FingerprintDetail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\CreatesFingerprintFixtures;
use Tests\TestCase;

class FingerprintStatusTimeLimitTest extends TestCase
{
    use CreatesFingerprintFixtures, RefreshDatabase;

    public function test_fingerprint_admin_can_change_status_from_approved_within_30_minutes(): void
    {
        $admin = $this->roleUser('Fingerprint Admin');
        $fixture = $this->fingerprintFixture($admin);
        $detail = $fixture['details'][0];

        $this->actingAs($admin);
        $this->markApproved($detail);

        $this->putJson("/api/fingerprints/detail/{$detail->id}/status", [
            'status' => 'processing',
        ])->assertOk()->assertJson(['success' => true]);

        $this->assertSame('processing', $detail->fresh()->status->value);
    }

    public function test_fingerprint_admin_cannot_change_status_from_approved_after_30_minutes(): void
    {
        $admin = $this->roleUser('Fingerprint Admin');
        $fixture = $this->fingerprintFixture($admin);
        $detail = $fixture['details'][0];

        $this->actingAs($admin);
        $this->markApproved($detail, 31);

        $this->putJson("/api/fingerprints/detail/{$detail->id}/status", [
            'status' => 'processing',
        ])->assertStatus(422)
            ->assertJson(['success' => false])
            ->assertJsonPath('message', 'Cannot change status from Approved after 30 minutes. Only Super Admin or Co Admin can do this.');

        $this->assertSame('approved', $detail->fresh()->status->value);
    }

    public function test_fingerprint_staff_cannot_change_status_from_approved_after_30_minutes(): void
    {
        $staff = $this->roleUser('Fingerprint Staff');
        $fixture = $this->fingerprintFixture($staff, ['assigned_staff_id' => $staff->id]);
        $detail = $fixture['details'][0];

        $this->actingAs($staff);
        $this->markApproved($detail, 31);

        $this->putJson("/api/fingerprints/detail/{$detail->id}/status", [
            'status' => 'cancelled',
        ])->assertStatus(422)
            ->assertJson(['success' => false])
            ->assertJsonPath('message', 'Cannot change status from Approved after 30 minutes. Only Super Admin or Co Admin can do this.');
    }

    public function test_super_admin_can_change_status_from_approved_after_30_minutes(): void
    {
        $superAdmin = $this->roleUser('Super Admin');
        $fixture = $this->fingerprintFixture($superAdmin);
        $detail = $fixture['details'][0];

        $this->actingAs($superAdmin);
        $this->markApproved($detail, 31);

        $this->putJson("/api/fingerprints/detail/{$detail->id}/status", [
            'status' => 'processing',
        ])->assertOk()->assertJson(['success' => true]);

        $this->assertSame('processing', $detail->fresh()->status->value);
    }

    public function test_co_admin_can_change_status_from_approved_after_30_minutes(): void
    {
        $coAdmin = $this->roleUser('Co Admin');
        $fixture = $this->fingerprintFixture($coAdmin);
        $detail = $fixture['details'][0];

        $this->actingAs($coAdmin);
        $this->markApproved($detail, 31);

        $this->putJson("/api/fingerprints/detail/{$detail->id}/status", [
            'status' => 'processing',
        ])->assertOk()->assertJson(['success' => true]);

        $this->assertSame('processing', $detail->fresh()->status->value);
    }

    public function test_hold_blocked_for_fingerprint_admin_after_30_minutes(): void
    {
        $admin = $this->roleUser('Fingerprint Admin');
        $fixture = $this->fingerprintFixture($admin);
        $detail = $fixture['details'][0];

        $this->actingAs($admin);
        $this->markApproved($detail, 31);

        $this->postJson("/api/fingerprints/detail/{$detail->id}/hold", [
            'reason' => 'rescheduled_by_client',
            'next_date' => now()->addDays(1)->format('Y-m-d'),
            'remarks' => 'test',
        ])->assertStatus(422)
            ->assertJson(['success' => false])
            ->assertJsonPath('message', 'Cannot hold an Approved fingerprint after 30 minutes. Only Super Admin or Co Admin can do this.');
    }

    public function test_hold_allowed_for_super_admin_after_30_minutes(): void
    {
        $superAdmin = $this->roleUser('Super Admin');
        $fixture = $this->fingerprintFixture($superAdmin);
        $detail = $fixture['details'][0];

        $this->actingAs($superAdmin);
        $this->markApproved($detail, 31);

        $this->postJson("/api/fingerprints/detail/{$detail->id}/hold", [
            'reason' => 'rescheduled_by_client',
            'next_date' => now()->addDays(1)->format('Y-m-d'),
            'remarks' => 'test',
        ])->assertOk()->assertJson(['success' => true]);

        $this->assertSame('processing', FingerprintDetail::find($detail->id)->status->value);
    }
}
