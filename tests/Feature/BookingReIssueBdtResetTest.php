<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingReIssueBdtResetTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = $this->createUser();
    }

    private function createUser(): User
    {
        $branch = Branch::create([
            'name' => 'Main Branch',
            'address' => 'Addr',
            'contacts' => '0123456789',
            'location' => 'KSA',
            'fingerprint_operation' => true,
            'branch_code' => 'MAIN01',
        ]);

        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
            'is_active' => true,
            'branch_id' => $branch->id,
        ]);

        $user->roles()->attach(Role::create(['name' => 'Ticket Admin']));

        return $user;
    }

    public function test_open_reissue_modal_resets_bdt_fields_for_cost_inputs(): void
    {
        $this->actingAs($this->user);
        $response = $this->get(route('bookings.index'));
        $html = $response->getContent();

        // The openReIssueModal function must reset BDT fields for cost inputs
        // to prevent stale data from previous re-issue sessions
        $this->assertStringContainsString(
            "this.reIssueForm.re_issue_charge_bdt = '';",
            $html,
            'openReIssueModal must reset re_issue_charge_bdt'
        );
        $this->assertStringContainsString(
            "this.reIssueForm.service_charge_bdt = '';",
            $html,
            'openReIssueModal must reset service_charge_bdt'
        );
        $this->assertStringContainsString(
            "this.reIssueForm.total_payment_bdt = '';",
            $html,
            'openReIssueModal must reset total_payment_bdt'
        );
        $this->assertStringNotContainsString(
            'x-model="reIssueForm.fare_difference"',
            $html,
            'fare_difference must not have a visible form input'
        );
        $this->assertStringNotContainsString(
            'x-model="reIssueForm.other_costs"',
            $html,
            'other_costs must not have a visible form input'
        );
    }
}
