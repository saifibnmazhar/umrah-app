<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Payment;
use App\Models\Role;
use App\Models\TransactionType;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentPrintVoucherTest extends TestCase
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

    private function createPayment(User $user, array $extra = []): Payment
    {
        $type = TransactionType::create(['name' => 'Ticket Agent Payment', 'type' => 'debit']);

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
        ]);

        return $payment;
    }

    public function test_print_voucher_shows_referral_branch_from_branch_relation(): void
    {
        $user = $this->setupUser();
        $branch = $this->makeBranch();
        $payment = $this->createPayment($user, ['branch_id' => $branch->id]);

        $this->actingAs($user)
            ->get(route('payments.print-voucher', $payment))
            ->assertOk()
            ->assertSee('Referral Branch')
            ->assertSee($branch->name);
    }

    public function test_print_voucher_shows_payment_referral_when_branch_is_other(): void
    {
        $user = $this->setupUser();
        $payment = $this->createPayment($user, ['branch_id' => null, 'payment_referral' => 'ReferralXyz']);

        $this->actingAs($user)
            ->get(route('payments.print-voucher', $payment))
            ->assertOk()
            ->assertSee('Referral Branch')
            ->assertSee('ReferralXyz');
    }
}
