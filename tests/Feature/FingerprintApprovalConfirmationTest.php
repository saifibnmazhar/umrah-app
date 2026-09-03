<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\CreatesFingerprintFixtures;
use Tests\TestCase;

class FingerprintApprovalConfirmationTest extends TestCase
{
    use CreatesFingerprintFixtures, RefreshDatabase;

    public function test_response_includes_all_done_true_when_all_passengers_are_done(): void
    {
        $admin = $this->roleUser('Fingerprint Admin');
        $fixture = $this->fingerprintFixture($admin, ['passengers' => 2]);
        [$detailA, $detailB] = $fixture['details'];

        $this->actingAs($admin);

        $this->putJson("/api/fingerprints/detail/{$detailA->id}/status", ['status' => 'done'])
            ->assertOk()
            ->assertJson(['success' => true, 'all_done' => false]);

        $response = $this->putJson("/api/fingerprints/detail/{$detailB->id}/status", ['status' => 'done']);
        $response->assertOk();
        $response->assertJson(['success' => true, 'all_done' => true]);
    }

    public function test_all_done_is_false_when_any_passenger_is_not_done(): void
    {
        $admin = $this->roleUser('Fingerprint Admin');
        $fixture = $this->fingerprintFixture($admin, ['passengers' => 2, 'statuses' => ['done', 'processing']]);
        $detail = $fixture['details'][0];

        $this->actingAs($admin)
            ->putJson("/api/fingerprints/detail/{$detail->id}/status", ['status' => 'done'])
            ->assertOk()
            ->assertJson(['success' => true, 'all_done' => false]);
    }

    public function test_auto_approval_no_longer_happens_when_all_passengers_are_done(): void
    {
        $admin = $this->roleUser('Fingerprint Admin');
        $fixture = $this->fingerprintFixture($admin, ['passengers' => 2]);
        [$detailA, $detailB] = $fixture['details'];

        $this->actingAs($admin);

        $this->putJson("/api/fingerprints/detail/{$detailA->id}/status", ['status' => 'done']);
        $this->putJson("/api/fingerprints/detail/{$detailB->id}/status", ['status' => 'done']);

        $this->assertSame('done', $detailA->fresh()->status->value);
        $this->assertSame('done', $detailB->fresh()->status->value);
    }

    public function test_approve_all_batch_approves_all_done_details(): void
    {
        $admin = $this->roleUser('Fingerprint Admin');
        $fixture = $this->fingerprintFixture($admin, ['passengers' => 2, 'statuses' => ['done', 'done']]);
        [$detailA, $detailB] = $fixture['details'];

        $this->actingAs($admin)
            ->postJson("/api/fingerprints/{$fixture['fingerprint']->id}/approve-all")
            ->assertOk()
            ->assertJson(['success' => true, 'message' => 'All fingerprints approved successfully']);

        $this->assertSame('approved', $detailA->fresh()->status->value);
        $this->assertSame('approved', $detailB->fresh()->status->value);
    }

    public function test_approve_all_returns_403_for_unauthorized_user(): void
    {
        $unauthorized = $this->roleUser('Branch Manager');
        $fixture = $this->fingerprintFixture($unauthorized, ['passengers' => 1, 'statuses' => ['done']]);

        $this->actingAs($unauthorized)
            ->postJson("/api/fingerprints/{$fixture['fingerprint']->id}/approve-all")
            ->assertStatus(403);
    }

    public function test_approve_all_returns_422_for_cancelled_booking(): void
    {
        $admin = $this->roleUser('Fingerprint Admin');
        $fixture = $this->fingerprintFixture($admin, [
            'is_cancelled' => true,
            'passengers' => 1,
            'statuses' => ['done'],
        ]);

        $this->actingAs($admin)
            ->postJson("/api/fingerprints/{$fixture['fingerprint']->id}/approve-all")
            ->assertStatus(422)
            ->assertJson(['success' => false, 'message' => 'Cannot approve for a cancelled booking']);
    }

    public function test_direct_approved_selection_allowed_when_other_details_are_done(): void
    {
        $admin = $this->roleUser('Fingerprint Admin');
        $fixture = $this->fingerprintFixture($admin, ['passengers' => 2, 'statuses' => ['done', 'processing']]);
        [$detailA, $detailB] = $fixture['details'];

        $this->actingAs($admin)
            ->putJson("/api/fingerprints/detail/{$detailB->id}/status", ['status' => 'approved'])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertSame('done', $detailA->fresh()->status->value);
        $this->assertSame('approved', $detailB->fresh()->status->value);
    }
}
