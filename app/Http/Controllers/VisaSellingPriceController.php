<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\VisaSellingPrice;
use Illuminate\Http\Request;

class VisaSellingPriceController extends Controller
{
    public function index()
    {
        $visaSellingPrices = VisaSellingPrice::with(['user'])
            ->orderBy('created_at', 'desc')
            ->paginate(10)
            ->withQueryString();
        return view('visa-selling-prices.index', compact('visaSellingPrices'));
    }

    public function create()
    {
        return view('visa-selling-prices.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'selling_price' => 'required|numeric|min:0',
        ]);

        try {
            $userId = auth()->id() ?? User::first()?->id;

            if (!$userId) {
                return redirect()->back()->with('error', 'No users found. Please create a user first.')->withInput();
            }

            $validated['user_id'] = $userId;
            VisaSellingPrice::create($validated);
            return redirect()->route('visa.admin')->with('success', 'Visa selling price created successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to create visa selling price.')->withInput();
        }
    }

    public function edit(VisaSellingPrice $visaSellingPrice)
    {
        return view('visa-selling-prices.edit', compact('visaSellingPrice'));
    }

    public function update(Request $request, VisaSellingPrice $visaSellingPrice)
    {
        $validated = $request->validate([
            'selling_price' => 'required|numeric|min:0',
        ]);

        try {
            $userId = auth()->id() ?? User::first()?->id;

            if (!$userId) {
                return redirect()->back()->with('error', 'No users found. Please create a user first.')->withInput();
            }

            $validated['user_id'] = $userId;
            $visaSellingPrice->update($validated);
            return redirect()->route('visa.admin')->with('success', 'Visa selling price updated successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to update visa selling price.')->withInput();
        }
    }

    public function destroy(VisaSellingPrice $visaSellingPrice)
    {
        try {
            $visaSellingPrice->delete();
            return redirect()->route('visa.admin')->with('success', 'Visa selling price deleted successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to delete visa selling price.');
        }
    }
}