<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\User;
use App\Models\CurrencyRate;
use App\Models\Bank;
use App\Models\Branch;
use App\Models\TicketAgent;
use App\Models\VisaAgent;
use App\Models\CommissionAgent;
use App\Models\TransactionType;
use App\Models\Voucher;
use App\Services\VoucherService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{
    public function index()
    {
        $payments = Payment::with(['user', 'bank', 'senderBank', 'voucher.transactionType'])
            ->orderBy('created_at', 'desc')
            ->paginate(10)
            ->withQueryString();
        
        return view('payments.index', compact('payments'));
    }

    public function create()
    {
        $currentCurrencyRate = CurrencyRate::orderBy('created_at', 'desc')->first();
        $banks = Bank::orderBy('name')->get();
        $branches = Branch::orderBy('name')->get();
        $transactionTypes = TransactionType::whereIn('name', [
            'Commission Agent Payment', 'Ticket Agent Payment', 'Visa Agent Payment',
        ])->get();
        $ticketPaymentTypeId = $transactionTypes->where('name', 'Ticket Agent Payment')->first()?->id;
        $visaPaymentTypeId = $transactionTypes->where('name', 'Visa Agent Payment')->first()?->id;
        $commissionPaymentTypeId = $transactionTypes->where('name', 'Commission Agent Payment')->first()?->id;
        $ticketAgents = TicketAgent::orderBy('name')->get();
        $visaAgents = VisaAgent::orderBy('name')->get();
        $commissionAgents = CommissionAgent::orderBy('name')->get();
        
        return view('payments.create', compact(
            'currentCurrencyRate', 'banks', 'branches', 'transactionTypes',
            'ticketPaymentTypeId', 'visaPaymentTypeId', 'commissionPaymentTypeId',
            'ticketAgents', 'visaAgents', 'commissionAgents'
        ));
    }

    public function store(Request $request)
    {
        if ($request->input('branch_id') === 'other') {
            $request->merge(['branch_id' => null]);
        }

        $validated = $request->validate([
            'payment_date' => 'required|date',
            'payment_method' => 'required|in:cash,bank',
            'amount' => 'required|numeric|min:0',
            'bdt_amount' => 'required|numeric|min:0',
            'sender_bank_id' => 'nullable|exists:banks,id',
            'receiver_bank' => 'nullable|string|max:255',
            'branch_id' => 'nullable|exists:branches,id',
            'ticket_agent_id' => 'nullable|exists:ticket_agents,id',
            'visa_agent_id' => 'nullable|exists:visa_agents,id',
            'commission_agent_id' => 'nullable|exists:commission_agents,id',
            'transaction_id' => 'nullable|string|max:255',
            'transaction_type_id' => 'required|exists:transaction_types,id',
            'remarks' => 'nullable|string|max:255',
            'payment_referral' => 'nullable|string|max:255',
        ]);

        $userId = auth()->id() ?? User::first()?->id;
        $validated['user_id'] = $userId;

        $currentRate = CurrencyRate::orderBy('created_at', 'desc')->first();
        $validated['currency_rate_id'] = $currentRate?->id;

        try {
            DB::transaction(function () use ($validated) {
                $payment = Payment::create($validated);

                app(VoucherService::class)->createVoucher([
                    'payment_id' => $payment->id,
                    'user_id' => $validated['user_id'],
                    'transaction_type_id' => $validated['transaction_type_id'],
                    'payment_date' => $validated['payment_date'],
                    'payment_method' => $validated['payment_method'],
                    'amount' => $validated['amount'],
                    'bdt_amount' => $validated['bdt_amount'],
                    'branch_id' => $validated['branch_id'] ?? null,
                    'transaction_id' => $validated['transaction_id'] ?? null,
                    'currency_rate_id' => $validated['currency_rate_id'] ?? null,
                    'ticket_agent_id' => $validated['ticket_agent_id'] ?? null,
                    'visa_agent_id' => $validated['visa_agent_id'] ?? null,
                    'commission_agent_id' => $validated['commission_agent_id'] ?? null,
                ]);
            });

            return redirect()->route('payments.index')->with('success', 'Payment created successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to create payment.')->withInput();
        }
    }

    public function show(Payment $payment)
    {
        $payment->load(['user', 'currencyRate', 'bank', 'senderBank', 'ticketAgent', 'visaAgent', 'commissionAgent', 'branch']);
        $rate = $payment->currencyRate?->rate ?? 0;
        return view('payments.show', compact('payment', 'rate'));
    }

    public function edit(Payment $payment)
    {
        $currentCurrencyRate = CurrencyRate::orderBy('created_at', 'desc')->first();
        $banks = Bank::orderBy('name')->get();
        $branches = Branch::orderBy('name')->get();
        $transactionTypes = TransactionType::whereIn('name', [
            'Commission Agent Payment', 'Ticket Agent Payment', 'Visa Agent Payment',
        ])->get();
        $ticketPaymentTypeId = $transactionTypes->where('name', 'Ticket Agent Payment')->first()?->id;
        $visaPaymentTypeId = $transactionTypes->where('name', 'Visa Agent Payment')->first()?->id;
        $commissionPaymentTypeId = $transactionTypes->where('name', 'Commission Agent Payment')->first()?->id;
        $ticketAgents = TicketAgent::orderBy('name')->get();
        $visaAgents = VisaAgent::orderBy('name')->get();
        $commissionAgents = CommissionAgent::orderBy('name')->get();
        
        return view('payments.edit', compact(
            'payment', 'currentCurrencyRate', 'banks', 'branches', 'transactionTypes',
            'ticketPaymentTypeId', 'visaPaymentTypeId', 'commissionPaymentTypeId',
            'ticketAgents', 'visaAgents', 'commissionAgents'
        ));
    }

    public function update(Request $request, Payment $payment)
    {
        if ($request->input('branch_id') === 'other') {
            $request->merge(['branch_id' => null]);
        }

        $validated = $request->validate([
            'payment_date' => 'required|date',
            'payment_method' => 'required|in:cash,bank',
            'amount' => 'required|numeric|min:0',
            'bdt_amount' => 'required|numeric|min:0',
            'sender_bank_id' => 'nullable|exists:banks,id',
            'receiver_bank' => 'nullable|string|max:255',
            'branch_id' => 'nullable|exists:branches,id',
            'ticket_agent_id' => 'nullable|exists:ticket_agents,id',
            'visa_agent_id' => 'nullable|exists:visa_agents,id',
            'commission_agent_id' => 'nullable|exists:commission_agents,id',
            'transaction_id' => 'nullable|string|max:255',
            'transaction_type_id' => 'nullable|exists:transaction_types,id',
            'remarks' => 'nullable|string|max:255',
            'payment_referral' => 'nullable|string|max:255',
        ]);

        $currentRate = CurrencyRate::orderBy('created_at', 'desc')->first();
        $validated['currency_rate_id'] = $currentRate?->id;

        try {
            DB::transaction(function () use ($payment, $validated) {
                $payment->update($validated);

                $voucher = Voucher::where('payment_id', $payment->id)->first();
                if ($voucher) {
                    $voucher->update([
                        'transaction_type_id' => $validated['transaction_type_id'],
                        'payment_date' => $validated['payment_date'],
                        'payment_method' => $validated['payment_method'],
                        'amount' => $validated['amount'],
                        'bdt_amount' => $validated['bdt_amount'],
                        'branch_id' => $validated['branch_id'] ?? null,
                        'transaction_id' => $validated['transaction_id'] ?? null,
                        'ticket_agent_id' => $validated['ticket_agent_id'] ?? null,
                        'visa_agent_id' => $validated['visa_agent_id'] ?? null,
                        'commission_agent_id' => $validated['commission_agent_id'] ?? null,
                        'currency_rate_id' => $validated['currency_rate_id'] ?? null,
                    ]);
                }
            });

            return redirect()->route('payments.index')->with('success', 'Payment updated successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to update payment.')->withInput();
        }
    }

    public function destroy(Payment $payment)
    {
        try {
            $payment->delete();
            return redirect()->route('payments.index')->with('success', 'Payment deleted successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to delete payment.');
        }
    }
}