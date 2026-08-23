<?php

namespace Tests\Unit;

use App\Models\Bank;
use App\Models\Branch;
use App\Models\CurrencyRate;
use App\Models\Payment;
use App\Models\Role;
use App\Models\TransactionType;
use App\Models\User;
use App\Models\Voucher;
use App\Queries\BranchWiseReportQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BranchWiseReportQueryTest extends TestCase
{
    use RefreshDatabase;

    private $superAdmin;

    private $currencyRate;

    private $branches;

    protected function setUp(): void
    {
        parent::setUp();
        $superAdminRole = Role::firstOrCreate(['name' => 'Super Admin']);
        $this->superAdmin = User::factory()->create(['email' => 'admin@test.com', 'password' => 'password']);
        $this->superAdmin->roles()->attach($superAdminRole);
        $this->actingAs($this->superAdmin);

        $this->currencyRate = CurrencyRate::create([
            'rate' => 10.0, 'currency_code' => 'USD',
            'date' => now()->toDateString(), 'user_id' => $this->superAdmin->id,
        ]);

        // Seed transaction types needed by vouchers
        TransactionType::updateOrCreate(['name' => 'Initial Payment'], ['type' => 'credit']);
        TransactionType::updateOrCreate(['name' => 'Due Collection'], ['type' => 'credit']);
        TransactionType::updateOrCreate(['name' => 'Customer Refund'], ['type' => 'debit']);
        TransactionType::updateOrCreate(['name' => 'Ticket Refund - Payment'], ['type' => 'debit']);
        TransactionType::updateOrCreate(['name' => 'Ticket Refund - Re-issue'], ['type' => 'debit']);

        DB::table('branches')->insertOrIgnore([
            ['name' => 'Central HQ', 'branch_code' => 'CB01', 'location' => 'BD', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Riyadh Office', 'branch_code' => 'RO02', 'location' => 'KSA', 'created_at' => now(), 'updated_at' => now()],
        ]);
        $this->branches = Branch::orderBy('id')->get();
    }

    private function makePayment(Branch $branch, float $amount = 1000, string $method = 'bank'): Payment
    {
        $bank = Bank::create([
            'name' => 'Test Bank '.$branch->id, 'branch_name' => 'Main',
            'account_number' => '1234567', 'account_name' => 'Test',
        ]);
        $user = User::factory()->create(['branch_id' => $branch->id]);

        $payment = Payment::create([
            'user_id' => $user->id,
            'branch_id' => $branch->id,
            'bank_id' => $bank->id,
            'payment_date' => now()->toDateString(),
            'payment_method' => $method === 'bank' ? 'bank' : 'cash',
            'amount' => $amount,
            'bdt_amount' => $amount * 10,
        ]);

        Voucher::create([
            'voucher_id' => 'V'.$payment->id,
            'payment_id' => $payment->id,
            'invoice_id' => null,
            'booking_id' => null,
            'user_id' => $user->id,
            'transaction_type_id' => TransactionType::where('name', $method === 'bank' ? 'Initial Payment' : 'Initial Payment')->first()->id,
            'payment_date' => now()->toDateString(),
            'payment_method' => $method === 'bank' ? 'bank' : 'cash',
            'amount' => $amount,
            'bdt_amount' => $amount * 10,
        ]);

        return $payment;
    }

    public function test_empty_result_when_no_data(): void
    {
        $query = new BranchWiseReportQuery(now()->subDays(30), now(), null);

        $summary = $query->summary();

        $this->assertSame(0, $summary['totalPassengers']);
        $this->assertSame(0, $summary['invoiceCount']);
        $this->assertSame(0.0, $summary['totalInitialPayment']);
        $this->assertSame(0.0, $summary['totalDueCollection']);
        $this->assertSame(0.0, $summary['totalProfit']);
        $this->assertSame(0.0, $summary['totalRefund']);
        $this->assertSame(0.0, $summary['totalTicketRefund']);
    }

    public function test_summary_returns_expected_structure(): void
    {
        $branch = $this->branches[1]; // Riyadh Office
        $this->makePayment($branch, 5000, 'bank');

        $dateFrom = now()->subDays(5)->startOfDay();
        $dateTo = now()->endOfDay();

        $query = new BranchWiseReportQuery($dateFrom, $dateTo, $branch->id);
        $summary = $query->summary();

        $this->assertIsArray($summary);
        $this->assertArrayHasKey('totalPassengers', $summary);
        $this->assertArrayHasKey('invoiceCount', $summary);
        $this->assertArrayHasKey('invoiceTotalAmount', $summary);
        $this->assertArrayHasKey('totalDue', $summary);
        $this->assertArrayHasKey('totalRefund', $summary);
        $this->assertArrayHasKey('totalTicketRefund', $summary);
        $this->assertArrayHasKey('totalInitialPayment', $summary);
        $this->assertArrayHasKey('totalDueCollection', $summary);
        $this->assertArrayHasKey('totalProfit', $summary);
        $this->assertArrayHasKey('totalProfitBdt', $summary);
        $this->assertArrayHasKey('inboundTicket', $summary);
        $this->assertArrayHasKey('visaSubmitted', $summary);
        $this->assertArrayHasKey('fingerprintApproved', $summary);
    }

    public function test_branch_scoping_filters_by_branch_id(): void
    {
        $branch1 = $this->branches[0]; // Central HQ
        $branch2 = $this->branches[1]; // Riyadh Office

        $this->makePayment($branch1, 1000, 'cash');
        $this->makePayment($branch2, 5000, 'bank');

        $dateFrom = now()->subDays(5);
        $dateTo = now();

        // Query scoped to branch1 only
        $query1 = new BranchWiseReportQuery($dateFrom, $dateTo, $branch1->id);
        $summary1 = $query1->summary();

        // Query scoped to branch2 only
        $query2 = new BranchWiseReportQuery($dateFrom, $dateTo, $branch2->id);
        $summary2 = $query2->summary();

        $this->assertSame(1000.0, (float) $summary1['totalInitialPayment']);
        $this->assertSame(5000.0, (float) $summary2['totalInitialPayment']);
    }

    public function test_payment_history_returns_vouchers_for_branch(): void
    {
        $branch = $this->branches[1]; // Riyadh Office
        $this->makePayment($branch, 2000, 'cash');
        $this->makePayment($branch, 3000, 'bank');

        $dateFrom = now()->subDays(5);
        $dateTo = now();

        $query = new BranchWiseReportQuery($dateFrom, $dateTo, $branch->id);
        $history = $query->paymentHistory($dateFrom, $dateTo, $branch->id);

        $this->assertIsArray($history);
        $this->assertCount(2, $history);
        $this->assertSame(5000.0, (float) collect($history)->sum('amount'));
        $this->assertArrayHasKey('receive_branch_location', $history[0]);
        $this->assertArrayHasKey('receive_branch_id', $history[0]);
    }

    public function test_payment_history_filters_by_method(): void
    {
        $branch = $this->branches[1]; // Riyadh Office
        $this->makePayment($branch, 2000, 'cash');
        $this->makePayment($branch, 3000, 'bank');

        $dateFrom = now()->subDays(5);
        $dateTo = now();

        $query = new BranchWiseReportQuery($dateFrom, $dateTo, $branch->id);

        // Cash only
        $cashHistory = $query->paymentHistory($dateFrom, $dateTo, $branch->id, 'cash');
        $this->assertCount(1, $cashHistory);

        // Bank only
        $bankHistory = $query->paymentHistory($dateFrom, $dateTo, $branch->id, 'bank');
        $this->assertCount(1, $bankHistory);
    }

    public function test_branches_and_banks_methods(): void
    {
        $branch = $this->branches[1]; // Riyadh Office
        $this->makePayment($branch, 2000, 'bank');

        $query = new BranchWiseReportQuery(now()->subDays(30), now(), null);

        $branches = $query->branches();
        $banks = $query->banks();

        $this->assertCount(2, $branches);
        $this->assertTrue($branches->contains('name', 'Riyadh Office'));

        $this->assertCount(1, $banks);
        $this->assertTrue($banks->contains('name', 'Test Bank '.$branch->id));
    }

    public function test_query_methods_exist(): void
    {
        $query = new BranchWiseReportQuery(now()->subDays(30), now(), null);

        $this->assertTrue(method_exists($query, 'summary'));
        $this->assertTrue(method_exists($query, 'paymentHistory'));
        $this->assertTrue(method_exists($query, 'branches'));
        $this->assertTrue(method_exists($query, 'banks'));
    }
}
