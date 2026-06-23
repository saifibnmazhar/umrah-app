<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\User;
use App\Models\CurrencyRate;
use App\Models\Bank;
use App\Models\TicketAgent;
use App\Models\VisaAgent;
use App\Models\CommissionAgent;
use App\Services\CurrencyRateService;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function index()
    {
        $payments = Payment::with(['booking', 'branch', 'user', 'bank'])
            ->orderBy('payment_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate(10)
            ->withQueryString();
        
        return view('payments.index', compact('payments'));
    }

    public function create()
    {
        $currencyRates = CurrencyRate::orderBy('created_at', 'desc')->get();
        $banks = Bank::orderBy('name')->get();
        $ticketAgents = TicketAgent::orderBy('name')->get();
        $visaAgents = VisaAgent::orderBy('name')->get();
        $commissionAgents = CommissionAgent::orderBy('name')->get();
        
        return view('payments.create', compact(
            'currencyRates', 'banks',
            'ticketAgents', 'visaAgents', 'commissionAgents'
        ));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'payment_date' => 'required|date',
            'payment_method' => 'required|in:cash,bank',
            'amount' => 'required|numeric|min:0',
            'bdt_amount' => 'required|numeric|min:0',
            'currency_rate_id' => 'nullable|exists:currency_rates,id',
            'bank_id' => 'nullable|exists:banks,id',
            'ticket_agent_id' => 'nullable|exists:ticket_agents,id',
            'visa_agent_id' => 'nullable|exists:visa_agents,id',
            'commission_agent_id' => 'nullable|exists:commission_agents,id',
            'transaction_id' => 'nullable|string|max:255',
        ]);

        $userId = auth()->id() ?? User::first()?->id;
        $validated['user_id'] = $userId;

        try {
            Payment::create($validated);
            return redirect()->route('payments.index')->with('success', 'Payment created successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to create payment.')->withInput();
        }
    }

    public function show(Payment $payment)
    {
        $payment->load(['booking', 'branch', 'user', 'currencyRate', 'bank', 'ticketAgent', 'visaAgent', 'commissionAgent']);
        $currencyRateService = app(CurrencyRateService::class);
        $rate = $payment->booking?->currencyRate?->rate
            ?? $currencyRateService->getRateForDate($payment->booking?->created_at)?->rate
            ?? 0;
        return view('payments.show', compact('payment', 'rate'));
    }

    public function edit(Payment $payment)
    {
        $currencyRates = CurrencyRate::orderBy('created_at', 'desc')->get();
        $banks = Bank::orderBy('name')->get();
        $ticketAgents = TicketAgent::orderBy('name')->get();
        $visaAgents = VisaAgent::orderBy('name')->get();
        $commissionAgents = CommissionAgent::orderBy('name')->get();
        
        return view('payments.edit', compact(
            'payment', 'currencyRates', 'banks',
            'ticketAgents', 'visaAgents', 'commissionAgents'
        ));
    }

    public function update(Request $request, Payment $payment)
    {
        $validated = $request->validate([
            'payment_date' => 'required|date',
            'payment_method' => 'required|in:cash,bank',
            'amount' => 'required|numeric|min:0',
            'bdt_amount' => 'required|numeric|min:0',
            'currency_rate_id' => 'nullable|exists:currency_rates,id',
            'bank_id' => 'nullable|exists:banks,id',
            'ticket_agent_id' => 'nullable|exists:ticket_agents,id',
            'visa_agent_id' => 'nullable|exists:visa_agents,id',
            'commission_agent_id' => 'nullable|exists:commission_agents,id',
            'transaction_id' => 'nullable|string|max:255',
        ]);

        try {
            $payment->update($validated);
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