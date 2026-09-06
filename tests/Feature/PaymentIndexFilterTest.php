<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\CommissionAgent;
use App\Models\Payment;
use App\Models\Role;
use App\Models\TicketAgent;
use App\Models\TransactionType;
use App\Models\User;
use App\Models\VisaAgent;
use App\Models\Voucher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentIndexFilterTest extends TestCase
{
    use RefreshDatabase;

    private function setupUser(): User
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => uniqid().'@example.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $user->roles()->attach(Role::create(['name' => 'Super Admin']));

        return $user;
    }

    private function makeBranch(): Branch
    {
        return Branch::create([
            'name' => 'Branch '.uniqid(),
            'address' => 'Addr',
            'contacts' => '0123',
            'location' => 'KSA',
            'fingerprint_operation' => true,
            'branch_code' => 'BR'.substr(uniqid(), -6),
        ]);
    }

    private function seedTypes(): array
    {
        return [
            'ticket' => TransactionType::create(['name' => 'Ticket Agent Payment', 'type' => 'debit']),
            'visa' => TransactionType::create(['name' => 'Visa Agent Payment', 'type' => 'debit']),
            'commission' => TransactionType::create(['name' => 'Commission Agent Payment', 'type' => 'debit']),
        ];
    }

    private function createAgentPayment(User $user, TransactionType $type, array $extra = []): Payment
    {
        $payment = Payment::create(array_merge([
            'user_id' => $user->id,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'cash',
            'amount' => 1000,
            'bdt_amount' => 28000,
        ], $extra));
        Voucher::create([
            'voucher_id' => 'VCH-'.uniqid(),
            'payment_id' => $payment->id,
            'user_id' => $user->id,
            'transaction_type_id' => $type->id,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'cash',
            'amount' => 1000,
            'bdt_amount' => 28000,
            'branch_id' => $extra['branch_id'] ?? null,
            'ticket_agent_id' => $extra['ticket_agent_id'] ?? null,
            'visa_agent_id' => $extra['visa_agent_id'] ?? null,
            'commission_agent_id' => $extra['commission_agent_id'] ?? null,
        ]);

        return $payment;
    }

    public function test_branch_column_shows_branch_name_and_referral(): void
    {
        $user = $this->setupUser();
        $types = $this->seedTypes();
        $branch = $this->makeBranch();
        $this->createAgentPayment($user, $types['ticket'], ['branch_id' => $branch->id]);
        $this->createAgentPayment($user, $types['ticket'], ['branch_id' => null, 'payment_referral' => 'ReferralXyz']);

        $this->actingAs($user)
            ->get(route('payments.index'))
            ->assertOk()
            ->assertSee('Branch')
            ->assertSee($branch->name)
            ->assertSee('ReferralXyz');
    }

    public function test_branch_filter_isolates_branch_and_other(): void
    {
        $user = $this->setupUser();
        $types = $this->seedTypes();
        $branchA = $this->makeBranch();
        $branchB = $this->makeBranch();
        $paymentA = $this->createAgentPayment($user, $types['ticket'], ['branch_id' => $branchA->id]);
        $paymentB = $this->createAgentPayment($user, $types['ticket'], ['branch_id' => $branchB->id]);
        $other = $this->createAgentPayment($user, $types['ticket'], ['branch_id' => null, 'payment_referral' => 'OtherRef']);

        $this->actingAs($user)
            ->get(route('payments.index', ['branch_id' => $branchA->id]))
            ->assertOk()
            ->assertSee('#'.$paymentA->id)
            ->assertDontSee('#'.$paymentB->id)
            ->assertDontSee('#'.$other->id)
            ->assertDontSee('OtherRef');

        $this->actingAs($user)
            ->get(route('payments.index', ['branch_id' => 'other']))
            ->assertOk()
            ->assertSee('#'.$other->id)
            ->assertSee('OtherRef')
            ->assertDontSee('#'.$paymentA->id)
            ->assertDontSee('#'.$paymentB->id);
    }

    public function test_agent_filter_options_follow_transaction_type(): void
    {
        $user = $this->setupUser();
        $types = $this->seedTypes();
        $ticketAgent = TicketAgent::create(['name' => 'TicketAg'.uniqid(), 'address' => 'A', 'contacts' => '1']);
        $visaAgent = VisaAgent::create(['name' => 'VisaAg'.uniqid(), 'address' => 'A', 'contacts' => '1']);
        $commissionAgent = CommissionAgent::create(['name' => 'CommAg'.uniqid(), 'visa_agent_id' => $visaAgent->id, 'address' => 'A', 'contacts' => '1']);
        $ticketPayment = $this->createAgentPayment($user, $types['ticket'], ['ticket_agent_id' => $ticketAgent->id]);
        $visaPayment = $this->createAgentPayment($user, $types['visa'], ['visa_agent_id' => $visaAgent->id]);

        $this->actingAs($user)
            ->get(route('payments.index', ['transaction_type_id' => $types['ticket']->id]))
            ->assertOk()
            ->assertSee('#'.$ticketPayment->id)
            ->assertDontSee('#'.$visaPayment->id)
            ->assertSee($ticketAgent->name)
            ->assertDontSee($visaAgent->name)
            ->assertDontSee($commissionAgent->name);

        $this->actingAs($user)
            ->get(route('payments.index', [
                'transaction_type_id' => $types['visa']->id,
                'agent_id' => $visaAgent->id,
            ]))
            ->assertOk()
            ->assertSee($visaAgent->name)
            ->assertDontSee($ticketAgent->name);
    }

    public function test_agent_dropdown_empty_without_transaction_type(): void
    {
        $user = $this->setupUser();
        $types = $this->seedTypes();
        $ticketAgent = TicketAgent::create(['name' => 'TicketAg'.uniqid(), 'address' => 'A', 'contacts' => '1']);
        $this->createAgentPayment($user, $types['ticket'], ['ticket_agent_id' => $ticketAgent->id]);

        $response = $this->actingAs($user)
            ->get(route('payments.index'))
            ->assertOk()
            ->assertSee('Select transaction type first');
        $agentSelect = substr($response->getContent(), strpos($response->getContent(), 'id="agent-filter"'));
        $agentSelect = substr($agentSelect, 0, strpos($agentSelect, '</select>'));
        $this->assertStringNotContainsString($ticketAgent->name, $agentSelect);
    }
}
