<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    public function index()
    {
        $customers = Customer::orderBy('name')
            ->paginate(10)
            ->withQueryString();
        return view('customers.index', compact('customers'));
    }

    public function create()
    {
        return view('customers.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'iqama_type' => 'required|in:self,referral',
            'passport_no' => 'required|string|unique:customers,passport_no',
            'iqama_no' => 'required|string|unique:customers,iqama_no',
            'mobile_no' => 'required|string',
            'ref_iqama_no' => 'nullable|string',
            'ref_mobile_no' => 'nullable|string',
            'ref_iqama_doc' => 'nullable|string',
            'address' => 'required|string',
        ]);

        try {
            Customer::create($validated);
            return redirect()->route('customers.index')->with('success', 'Customer created successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to create customer.')->withInput();
        }
    }

    public function edit(Customer $customer)
    {
        return view('customers.edit', compact('customer'));
    }

    public function update(Request $request, Customer $customer)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'iqama_type' => 'required|in:self,referral',
            'passport_no' => 'required|string|unique:customers,passport_no,' . $customer->id,
            'iqama_no' => 'required|string|unique:customers,iqama_no,' . $customer->id,
            'mobile_no' => 'required|string',
            'ref_iqama_no' => 'nullable|string',
            'ref_mobile_no' => 'nullable|string',
            'ref_iqama_doc' => 'nullable|string',
            'address' => 'required|string',
        ]);

        try {
            $customer->update($validated);
            return redirect()->route('customers.index')->with('success', 'Customer updated successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to update customer.')->withInput();
        }
    }

    public function destroy(Customer $customer)
    {
        try {
            $customer->delete();
            return redirect()->route('customers.index')->with('success', 'Customer deleted successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to delete customer.');
        }
    }
}