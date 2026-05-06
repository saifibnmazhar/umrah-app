<?php

namespace App\Http\Controllers;

use App\Models\VisaSellingPrice;
use Illuminate\Http\Request;

class VisaSellingPriceController extends Controller
{
    public function index()
    {
        $visaSellingPrices = VisaSellingPrice::with(['user'])
            ->orderBy('id')
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
            $validated['user_id'] = auth()->id();
            VisaSellingPrice::create($validated);
            return redirect()->route('visa-selling-prices.index')->with('success', 'Visa selling price created successfully.');
        } catch (\Exception $e) {
            \Log::error('VisaSellingPrice Create Error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to create visa selling price: ' . $e->getMessage())->withInput();
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
            $visaSellingPrice->update($validated);
            return redirect()->route('visa-selling-prices.index')->with('success', 'Visa selling price updated successfully.');
        } catch (\Exception $e) {
            \Log::error('VisaSellingPrice Update Error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to update visa selling price: ' . $e->getMessage())->withInput();
        }
    }

    public function destroy(VisaSellingPrice $visaSellingPrice)
    {
        try {
            $visaSellingPrice->delete();
            return redirect()->route('visa-selling-prices.index')->with('success', 'Visa selling price deleted successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to delete visa selling price.');
        }
    }
}