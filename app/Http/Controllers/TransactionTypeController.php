<?php

namespace App\Http\Controllers;

use App\Models\TransactionType;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TransactionTypeController extends Controller
{
    public function index()
    {
        $transactionTypes = TransactionType::with(['vouchers'])
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();
        return view('transaction-types.index', compact('transactionTypes'));
    }

    public function create()
    {
        return view('transaction-types.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:transaction_type,name',
            'type' => 'required|in:debit,credit',
        ]);

        try {
            TransactionType::create($validated);
            return redirect()->route('transaction-types.index')->with('success', 'Transaction type created successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to create transaction type.')->withInput();
        }
    }

    public function edit(TransactionType $transactionType)
    {
        return view('transaction-types.edit', compact('transactionType'));
    }

    public function update(Request $request, TransactionType $transactionType)
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('transaction_type', 'name')->ignore($transactionType->id),
            ],
            'type' => 'required|in:debit,credit',
        ]);

        try {
            $transactionType->update($validated);
            return redirect()->route('transaction-types.index')->with('success', 'Transaction type updated successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to update transaction type.')->withInput();
        }
    }

    public function destroy(TransactionType $transactionType)
    {
        try {
            $transactionType->delete();
            return redirect()->route('transaction-types.index')->with('success', 'Transaction type deleted successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to delete transaction type.');
        }
    }
}