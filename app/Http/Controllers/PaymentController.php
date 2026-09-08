<?php

namespace App\Http\Controllers;

use App\Models\Bank;
use App\Models\Branch;
use App\Models\CancelledSubmission;
use App\Models\CommissionAgent;
use App\Models\CurrencyRate;
use App\Models\IssuedTicket;
use App\Models\Payment;
use App\Models\TicketAgent;
use App\Models\TransactionType;
use App\Models\User;
use App\Models\VisaAgent;
use App\Models\VisaSubmission;
use App\Models\Voucher;
use App\Services\VoucherService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{
    public function index(Request $request)
    {
        $typeNames = ['Ticket Agent Payment', 'Visa Agent Payment', 'Commission Agent Payment'];

        $query = Payment::with(['user', 'bank', 'senderBank', 'branch', 'ticketAgent', 'visaAgent', 'commissionAgent', 'voucher.transactionType'])
            ->whereHas('voucher.transactionType', function ($q) use ($typeNames) {
                $q->whereIn('name', $typeNames);
            })
            ->orderBy('created_at', 'desc');

        if ($request->filled('transaction_type_id')) {
            $query->whereHas('voucher', function ($q) use ($request) {
                $q->where('transaction_type_id', $request->transaction_type_id);
            });
        }

        if ($request->filled('branch_id')) {
            if ($request->branch_id === 'other') {
                $query->whereNull('branch_id');
            } else {
                $query->where('branch_id', $request->branch_id);
            }
        }

        $selectedType = $request->filled('transaction_type_id')
            ? TransactionType::find($request->transaction_type_id)
            : null;

        $agentColumn = match ($selectedType?->name) {
            'Ticket Agent Payment' => 'ticket_agent_id',
            'Visa Agent Payment' => 'visa_agent_id',
            'Commission Agent Payment' => 'commission_agent_id',
            default => null,
        };

        if ($agentColumn && $request->filled('agent_id')) {
            $query->where($agentColumn, $request->agent_id);
        }

        $payments = $query->paginate(10)->withQueryString();

        $transactionTypes = TransactionType::whereIn('name', $typeNames)->orderBy('name')->get();
        $branches = Branch::orderBy('name')->get(['id', 'name']);

        $agentOptions = match ($selectedType?->name) {
            'Ticket Agent Payment' => TicketAgent::orderBy('name')->get(['id', 'name']),
            'Visa Agent Payment' => VisaAgent::orderBy('name')->get(['id', 'name']),
            'Commission Agent Payment' => CommissionAgent::orderBy('name')->get(['id', 'name']),
            default => collect(),
        };

        return view('payments.index', compact('payments', 'transactionTypes', 'branches', 'agentOptions'));
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

        if ($request->input('sender_bank_id') === 'other') {
            $request->merge(['sender_bank_id' => null]);
        }

        $validated = $request->validate([
            'payment_date' => 'required|date',
            'payment_method' => 'required|in:cash,bank',
            'amount' => 'required|numeric|min:0',
            'bdt_amount' => 'required|numeric|min:0',
            'sender_bank_id' => 'nullable|exists:banks,id',
            'other_sender_bank' => 'nullable|string|max:255',
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

    public function printVoucher(Payment $payment)
    {
        $payment->load(['user', 'voucher', 'currencyRate', 'ticketAgent', 'visaAgent', 'commissionAgent', 'senderBank']);
        $rate = $payment->currencyRate?->rate ?? 0;
        $loggedUser = auth()->user()->name;

        $lineItems = [];
        $paymentType = '';

        if ($payment->visa_agent_id) {
            $paymentType = 'Visa Agent Payment';

            $visaSubmissions = VisaSubmission::where('visa_agent_id', $payment->visa_agent_id)
                ->where('status', 'issued')
                ->get();

            $cancellationFees = CancelledSubmission::where('visa_agent_id', $payment->visa_agent_id)
                ->sum('cancellation_fee');

            $visaServices = $visaSubmissions->sum('net_visa_cost');
            $otherCharges = $visaSubmissions->sum('additional_cost') + $cancellationFees;

            $lineItems[] = ['description' => 'Visa Services', 'amount' => $visaServices];
            $lineItems[] = ['description' => 'Other Charges', 'amount' => $otherCharges];
        } elseif ($payment->ticket_agent_id) {
            $paymentType = 'Ticket Agent Payment';

            $payable = IssuedTicket::where('ticket_agent_id', $payment->ticket_agent_id)
                ->whereIn('status', ['issued', 're-issued'])
                ->sum('net_fare');

            $lineItems[] = ['description' => 'Ticket Services', 'amount' => $payable];
        } elseif ($payment->commission_agent_id) {
            $paymentType = 'Commission Agent Payment';

            $payable = VisaSubmission::where('commission_agent_id', $payment->commission_agent_id)
                ->sum('agent_commission');

            $lineItems[] = ['description' => 'Commission Services', 'amount' => $payable];
        }

        $totalAmount = collect($lineItems)->sum('amount');
        $paidAmount = (float) $payment->amount;

        $previousPayments = 0;
        if ($payment->visa_agent_id) {
            $previousPayments = Payment::where('visa_agent_id', $payment->visa_agent_id)
                ->where('id', '!=', $payment->id)
                ->whereHas('voucher.transactionType', fn ($q) => $q->where('name', 'Visa Agent Payment'))
                ->sum('amount');
        } elseif ($payment->ticket_agent_id) {
            $previousPayments = Payment::where('ticket_agent_id', $payment->ticket_agent_id)
                ->where('id', '!=', $payment->id)
                ->whereHas('voucher.transactionType', fn ($q) => $q->where('name', 'Ticket Agent Payment'))
                ->sum('amount');
        } elseif ($payment->commission_agent_id) {
            $previousPayments = Payment::where('commission_agent_id', $payment->commission_agent_id)
                ->where('id', '!=', $payment->id)
                ->whereHas('voucher.transactionType', fn ($q) => $q->where('name', 'Commission Agent Payment'))
                ->sum('amount');
        }

        $previousDueAmount = $totalAmount - $previousPayments;
        $dueAmount = $previousDueAmount - $paidAmount;

        $currencyRate = $rate;
        $lineItemsBdt = collect($lineItems)->map(fn ($item) => [
            'description' => $item['description'],
            'amount' => $item['amount'],
            'bdt_amount' => $item['amount'] * ($rate > 0 ? $rate : 0),
        ])->toArray();
        $totalAmountBdt = $totalAmount * ($rate > 0 ? $rate : 0);
        $paidAmountBdt = (float) $payment->bdt_amount;
        $previousDueAmountBdt = $previousDueAmount * ($rate > 0 ? $rate : 0);
        $dueAmountBdt = $previousDueAmountBdt - $paidAmountBdt;

        return view('payments.print-voucher', compact(
            'payment', 'rate', 'currencyRate', 'loggedUser', 'lineItems', 'paymentType',
            'totalAmount', 'paidAmount', 'dueAmount', 'previousDueAmount',
            'lineItemsBdt', 'totalAmountBdt', 'paidAmountBdt', 'dueAmountBdt', 'previousDueAmountBdt'
        ));
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

        if ($request->input('sender_bank_id') === 'other') {
            $request->merge(['sender_bank_id' => null]);
        }

        $validated = $request->validate([
            'payment_date' => 'required|date',
            'payment_method' => 'required|in:cash,bank',
            'amount' => 'required|numeric|min:0',
            'bdt_amount' => 'required|numeric|min:0',
            'sender_bank_id' => 'nullable|exists:banks,id',
            'other_sender_bank' => 'nullable|string|max:255',
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
