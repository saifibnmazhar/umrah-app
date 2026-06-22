<?php

namespace App\Http\Controllers;

use App\Models\Voucher;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Branch;
use App\Models\User;
use App\Models\CurrencyRate;
use App\Models\Bank;
use App\Models\TicketAgent;
use App\Models\VisaAgent;
use App\Models\CommissionAgent;
use App\Models\TransactionType;
use App\Services\CurrencyRateService;
use Illuminate\Http\Request;

class VoucherController extends Controller
{
    public function index()
    {
        $vouchers = Voucher::with(['booking', 'payment', 'branch', 'user', 'transactionType'])
            ->orderBy('created_at', 'desc')
            ->paginate(10)
            ->withQueryString();
        
        return view('vouchers.index', compact('vouchers'));
    }

    public function create()
    {
        $bookings = Booking::orderBy('id', 'desc')->get();
        $payments = Payment::orderBy('id', 'desc')->get();
        $branches = Branch::orderBy('name')->get();
        $currencyRates = CurrencyRate::orderBy('created_at', 'desc')->get();
        $banks = Bank::orderBy('name')->get();
        $ticketAgents = TicketAgent::orderBy('name')->get();
        $visaAgents = VisaAgent::orderBy('name')->get();
        $commissionAgents = CommissionAgent::orderBy('name')->get();
        $transactionTypes = TransactionType::orderBy('name')->get();
        
        return view('vouchers.create', compact(
            'bookings', 'payments', 'branches', 'currencyRates', 'banks',
            'ticketAgents', 'visaAgents', 'commissionAgents', 'transactionTypes'
        ));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'booking_id' => 'required|exists:bookings,id',
            'payment_id' => 'required|exists:payments,id',
            'branch_id' => 'required|exists:branches,id',
            'transaction_type_id' => 'required|exists:transaction_type,id',
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
        
        $validated['voucher_id'] = 'VCH-' . date('Ymd') . '-' . str_pad(Voucher::count() + 1, 4, '0', STR_PAD_LEFT);

        try {
            Voucher::create($validated);
            return redirect()->route('vouchers.index')->with('success', 'Voucher created successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to create voucher.')->withInput();
        }
    }

    public function show(Voucher $voucher)
    {
        $voucher->load(['booking', 'payment', 'branch', 'user', 'currencyRate', 'bank', 'ticketAgent', 'visaAgent', 'commissionAgent', 'transactionType']);
        $currencyRateService = app(CurrencyRateService::class);
        $rate = $voucher->booking?->currencyRate?->rate
            ?? $currencyRateService->getRateForDate($voucher->booking?->created_at)?->rate
            ?? 0;
        return view('vouchers.show', compact('voucher', 'rate'));
    }

    public function edit(Voucher $voucher)
    {
        $bookings = Booking::orderBy('id', 'desc')->get();
        $payments = Payment::with('booking')->orderBy('id', 'desc')->get();
        $branches = Branch::orderBy('name')->get();
        $currencyRates = CurrencyRate::orderBy('created_at', 'desc')->get();
        $banks = Bank::orderBy('name')->get();
        $ticketAgents = TicketAgent::orderBy('name')->get();
        $visaAgents = VisaAgent::orderBy('name')->get();
        $commissionAgents = CommissionAgent::orderBy('name')->get();
        $transactionTypes = TransactionType::orderBy('name')->get();

        return view('vouchers.edit', compact(
            'voucher', 'bookings', 'payments', 'branches', 'currencyRates', 'banks',
            'ticketAgents', 'visaAgents', 'commissionAgents', 'transactionTypes'
        ));
    }

    public function update(Request $request, Voucher $voucher)
    {
        $validated = $request->validate([
            'booking_id' => 'required|exists:bookings,id',
            'payment_id' => 'required|exists:payments,id',
            'branch_id' => 'required|exists:branches,id',
            'transaction_type_id' => 'required|exists:transaction_type,id',
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
            $voucher->update($validated);
            return redirect()->route('vouchers.index')->with('success', 'Voucher updated successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to update voucher.')->withInput();
        }
    }

    public function destroy(Voucher $voucher)
    {
        try {
            $voucher->delete();
            return redirect()->route('vouchers.index')->with('success', 'Voucher deleted successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to delete voucher.');
        }
    }
}