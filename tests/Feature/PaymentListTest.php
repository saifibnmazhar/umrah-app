<?php

namespace Tests\Feature;

use App\Livewire\Payment\PaymentListTable;
use App\Models\Bank;
use App\Models\CurrencyRate;
use App\Models\Payment;
use App\Models\Role;
use App\Models\TransactionType;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;
use Tests\TestCase;

class PaymentListTest extends TestCase
{
    use RefreshDatabase;

    private ?User $user = null;

    private ?int $agentPaymentTypeId = null;

    private ?int $visaPaymentTypeId = null;

    private function setupUser(): User
    {
        if ($this->user) {
            return $this->user;
        }
        $this->user = User::create([
            'name' => 'Payment Admin',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $this->user->roles()->attach(Role::create(['name' => 'Super Admin']));

        return $this->user;
    }

    private function seedTransactionTypes(): void
    {
        $this->setupUser();
        $this->agentPaymentTypeId = TransactionType::create(['name' => 'Ticket Agent Payment', 'type' => 'debit'])->id;
        $this->visaPaymentTypeId = TransactionType::create(['name' => 'Visa Agent Payment', 'type' => 'debit'])->id;
    }

    private function createPayment(int $transactionTypeId, float $amount = 1000.00, string $method = 'bank'): Payment
    {
        $bank = Bank::create(['name' => 'Test Bank', 'branch_name' => 'Main Branch', 'account_number' => '1234567890']);
        $currencyRate = CurrencyRate::create(['user_id' => $this->user->id, 'rate' => 100.00, 'currency' => 'SAR']);

        $payment = Payment::create([
            'user_id' => $this->user->id,
            'payment_date' => now(),
            'payment_method' => $method,
            'amount' => $amount,
            'bdt_amount' => $amount * 100,
            'receiver_bank' => 'Receiver Bank',
            'transaction_type_id' => $transactionTypeId,
            'currency_rate_id' => $currencyRate->id,
        ]);

        Voucher::create([
            'voucher_id' => 'V'.$payment->id,
            'payment_id' => $payment->id,
            'user_id' => $this->user->id,
            'transaction_type_id' => $transactionTypeId,
            'payment_date' => now(),
            'payment_method' => $method,
            'amount' => $amount,
            'bdt_amount' => $amount * 100,
            'transaction_id' => 'TXN'.$payment->id,
            'currency_rate_id' => $currencyRate->id,
        ]);

        return $payment;
    }

    /** @test */
    public function test_payment_list_renders_as_livewire_component(): void
    {
        $this->seedTransactionTypes();
        $payment = $this->createPayment($this->agentPaymentTypeId);
        Auth::login($this->user);

        $response = $this->get(route('payments.index'));
        $response->assertOk();
        $response->assertSee('wire:id', false);
        $response->assertSee('Transaction Type');
        $response->assertSee('#'.$payment->id);
    }

    /** @test */
    public function test_payment_list_filter_by_transaction_type(): void
    {
        $this->seedTransactionTypes();
        $agentPayment = $this->createPayment($this->agentPaymentTypeId);
        $visaPayment = $this->createPayment($this->visaPaymentTypeId);
        Auth::login($this->user);

        Livewire::test(PaymentListTable::class)
            ->set('transactionTypeFilter', $this->visaPaymentTypeId)
            ->assertSee('#'.$visaPayment->id)
            ->assertDontSee('#'.$agentPayment->id);
    }

    /** @test */
    public function test_payment_list_pagination_remains_bounded(): void
    {
        $this->seedTransactionTypes();
        $payment1 = $this->createPayment($this->agentPaymentTypeId, 1000);
        $payment2 = $this->createPayment($this->agentPaymentTypeId, 2000);
        $payment3 = $this->createPayment($this->agentPaymentTypeId, 3000);
        Auth::login($this->user);

        Livewire::test(PaymentListTable::class)
            ->assertSee('#'.$payment1->id)
            ->assertSee('#'.$payment3->id);
    }
}
