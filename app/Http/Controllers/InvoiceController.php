<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Branch;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    public function index()
    {
        $invoices = Invoice::with(['booking', 'branch', 'user'])
            ->orderBy('created_at', 'desc')
            ->paginate(10)
            ->withQueryString();

        return view('invoices.index', compact('invoices'));
    }

    public function create()
    {
        $bookings = Booking::orderBy('id', 'desc')->get();
        $branches = Branch::orderBy('name')->get();

        return view('invoices.create', compact('bookings', 'branches'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'booking_id' => 'required|exists:bookings,id',
            'branch_id' => 'required|exists:branches,id',
        ]);

        $userId = auth()->id() ?? User::first()?->id;
        $validated['user_id'] = $userId;

        try {
            Invoice::create($validated);

            return redirect()->route('invoices.index')->with('success', 'Invoice created successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to create invoice.')->withInput();
        }
    }

    public function show(Invoice $invoice)
    {
        $invoice->load(['booking', 'branch', 'user']);

        return view('invoices.details', compact('invoice'));
    }

    public function edit(Invoice $invoice)
    {
        $bookings = Booking::orderBy('id', 'desc')->get();
        $branches = Branch::orderBy('name')->get();

        return view('invoices.edit', compact('invoice', 'bookings', 'branches'));
    }

    public function update(Request $request, Invoice $invoice)
    {
        $validated = $request->validate([
            'booking_id' => 'required|exists:bookings,id',
            'branch_id' => 'required|exists:branches,id',
        ]);

        try {
            $invoice->update($validated);

            return redirect()->route('invoices.index')->with('success', 'Invoice updated successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to update invoice.')->withInput();
        }
    }

    public function destroy(Invoice $invoice)
    {
        try {
            $invoice->delete();

            return redirect()->route('invoices.index')->with('success', 'Invoice deleted successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to delete invoice.');
        }
    }
}
