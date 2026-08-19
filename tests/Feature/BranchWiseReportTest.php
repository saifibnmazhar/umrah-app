<?php

namespace Tests\Feature;

use App\Models\Bank;
use App\Models\Branch;
use App\Models\CurrencyRate;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Role;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class BranchWiseReportTest extends TestCase
{
    use RefreshDatabase;

    private $superAdmin;

    private $branches;

    private $currencyRate;

    protected function setUp(): void
    {
        parent::setUp();
        $superAdminRole = Role::firstOrCreate(['name' => 'Super Admin']);
        $this->superAdmin = User::factory()->create(['email' => 'admin@test.com', 'password' => 'password']);
        $this->superAdmin->roles()->attach($superAdminRole);
        $this->actingAs($this->superAdmin);

        $this->currencyRate = CurrencyRate::create(['rate' => 10.0, 'currency_code' => 'USD', 'date' => now()->toDateString(), 'user_id' => $this->superAdmin->id]);
        DB::table('branches')->insertOrIgnore([
            ['name' => 'Central HQ', 'branch_code' => 'CB01', 'location' => 'BD', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Riyadh Office', 'branch_code' => 'RO02', 'location' => 'KSA', 'created_at' => now(), 'updated_at' => now()],
        ]);
        $this->branches = Branch::orderBy('id')->get();
    }

    private function createPaymentForBranch(Branch $branch, float $amount = 1000, string $method = 'bank'): Payment
    {
        $bank = Bank::create(['name' => 'Test Bank '.$branch->id, 'branch_name' => 'Main', 'account_number' => '1234567', 'account_name' => 'Test']);
        $user = User::factory()->create(['branch_id' => $branch->id]);
        $invoice = Invoice::create(['booking_id' => null, 'branch_id' => $branch->id, 'total_amount' => $amount, 'balance' => $amount, 'due_date' => now()]);
        $voucher = Voucher::create(['user_id' => $user->id, 'bank_id' => $bank->id, 'invoice_id' => $invoice->id, 'transaction_type_id' => 1, 'amount' => $amount, 'currency_rate_id' => $this->currencyRate->id, 'payment_method' => $method === 'bank' ? 'bank' : 'cash']);

        return Payment::create(['user_id' => $user->id, 'voucher_id' => $voucher->id, 'bank_id' => $bank->id, 'amount' => $amount, 'payment_method' => $method]);
    }

    /** @test */
    public function test_branch_wise_report_renders_livewire_component(): void
    {
        $response = $this->get('/reports/branch-wise');
        $response->assertOk();
        $response->assertSee('Branch Wise Report');
        $response->assertSeeLivewire('report.branch-wise-report-filters');
    }

    /** @test */
    public function test_branch_wise_report_filters_by_date_via_livewire(): void
    {
        Livewire::test('report.branch-wise-report-filters')
            ->set('dateFrom', now()->subDays(5)->format('Y-m-d'))
            ->set('dateTo', now()->format('Y-m-d'))
            ->assertOk();
    }

    /** @test */
    public function test_branch_wise_report_filters_by_branch_via_livewire(): void
    {
        Livewire::test('report.branch-wise-report-filters')
            ->set('branchId', $this->branches[1]->id)
            ->assertOk();
    }
}
