<?php

namespace App\Http\Controllers;

use App\Models\Bank;
use Illuminate\Http\Request;

class BankController extends Controller
{
    public function index()
    {
        $banks = Bank::orderBy('name')->paginate(10)->withQueryString();
        return view('banks.index', compact('banks'));
    }

    public function create()
    {
        return view('banks.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:banks,name',
            'description' => 'nullable|string|max:255',
        ]);

        try {
            Bank::create($validated);
            return redirect()->route('banks.index')->with('success', 'Bank created successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to create bank.')->withInput();
        }
    }

    public function edit(Bank $bank)
    {
        return view('banks.edit', compact('bank'));
    }

    public function update(Request $request, Bank $bank)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:banks,name,' . $bank->id,
            'description' => 'nullable|string|max:255',
        ]);

        try {
            $bank->update($validated);
            return redirect()->route('banks.index')->with('success', 'Bank updated successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to update bank.')->withInput();
        }
    }

    public function destroy(Bank $bank)
    {
        try {
            $bank->delete();
            return redirect()->route('banks.index')->with('success', 'Bank deleted successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to delete bank.');
        }
    }
}