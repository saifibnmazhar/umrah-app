<?php

namespace App\Http\Controllers;

use App\Models\CurrencyRate;
use App\Models\User;
use Illuminate\Http\Request;

class CurrencyRateController extends Controller
{
    public function index()
    {
        $currencyRates = CurrencyRate::with(['user'])
            ->orderBy('created_at', 'desc')
            ->paginate(10)
            ->withQueryString();

        return view('currency-rates.index', compact('currencyRates'));
    }

    public function create()
    {
        return view('currency-rates.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'rate' => 'required|numeric|min:0',
        ]);

        try {
            $userId = auth()->id() ?? User::first()?->id;

            if (! $userId) {
                return redirect()->back()->with('error', 'No users found. Please create a user first.')->withInput();
            }

            $validated['user_id'] = $userId;
            CurrencyRate::create($validated);

            return redirect()->route('currency-rates.index')->with('success', 'Currency rate created successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to create currency rate.')->withInput();
        }
    }

    public function edit(CurrencyRate $currencyRate)
    {
        return view('currency-rates.edit', compact('currencyRate'));
    }

    public function update(Request $request, CurrencyRate $currencyRate)
    {
        $validated = $request->validate([
            'rate' => 'required|numeric|min:0',
        ]);

        try {
            $userId = auth()->id() ?? User::first()?->id;

            if (! $userId) {
                return redirect()->back()->with('error', 'No users found. Please create a user first.')->withInput();
            }

            $validated['user_id'] = $userId;
            $currencyRate->update($validated);

            return redirect()->route('currency-rates.index')->with('success', 'Currency rate updated successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to update currency rate.')->withInput();
        }
    }

    public function destroy(CurrencyRate $currencyRate)
    {
        try {
            $currencyRate->delete();

            return redirect()->route('currency-rates.index')->with('success', 'Currency rate deleted successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to delete currency rate.');
        }
    }
}
