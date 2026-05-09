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
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'iqama_type' => 'nullable|string|max:50',
                'passport_no' => 'required|string|max:50',
                'iqama_no' => 'nullable|string|max:50',
                'mobile_no' => 'required|string|max:20',
                'ref_iqama_no' => 'nullable|string|max:50',
                'ref_mobile_no' => 'nullable|string|max:20',
                'ref_iqama_doc' => 'nullable|string|max:512',
                'address' => 'nullable|string|max:500',
            ]);

            $customer = Customer::create($validated);
            return response()->json([
                'success' => true,
                'customer' => $customer,
                'message' => 'Customer created successfully'
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create customer: ' . $e->getMessage()
            ], 500);
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
            'passport_no' => 'required|string|max:50|unique:customers,passport_no,' . $customer->id,
            'iqama_no' => 'required|string|max:50|unique:customers,iqama_no,' . $customer->id,
            'mobile_no' => 'required|string|max:20',
            'ref_iqama_no' => 'nullable|string|max:50',
            'ref_mobile_no' => 'nullable|string|max:20',
            'ref_iqama_doc' => 'nullable|string|max:512',
            'address' => 'required|string|max:500',
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

    public function search(Request $request)
    {
        $query = $request->input('q', '');
        
        if (strlen($query) < 2) {
            return response()->json([]);
        }

        $customers = Customer::where(function ($q) use ($query) {
                $q->where('passport_no', 'like', "%{$query}%")
                  ->orWhere('iqama_no', 'like', "%{$query}%")
                  ->orWhere('name', 'like', "%{$query}%")
                  ->orWhere('mobile_no', 'like', "%{$query}%");
            })
            ->limit(20)
            ->get(['id', 'name', 'passport_no', 'iqama_no', 'mobile_no']);

        return response()->json($customers);
    }
}