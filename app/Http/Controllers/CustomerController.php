<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;

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
            $request->validate([
                'name' => 'required|string|max:255',
                'iqama_type' => 'nullable|string|max:50',
                'passport_no' => 'required|string|max:50',
                'iqama_no' => 'nullable|string|max:50',
                'mobile_no' => 'required|string|max:20',
                'ref_iqama_no' => 'nullable|string|max:50',
                'ref_mobile_no' => 'nullable|string|max:20',
                'ref_iqama_doc' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
                'documents' => 'nullable|array',
                'documents.*' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:10240',
                'address' => 'nullable|string|max:500',
            ]);

            $data = $request->except(['ref_iqama_doc', 'documents']);

            if ($request->hasFile('ref_iqama_doc')) {
                $path = $request->file('ref_iqama_doc')->store('customer-docs', 'public');
                $data['ref_iqama_doc'] = $path;
            }

            $customer = Customer::create($data);

            if ($request->hasFile('documents')) {
                foreach ($request->file('documents') as $file) {
                    $customer->documents()->create([
                        'owner_type' => 'customer',
                        'owner_id' => $customer->id,
                        'file_path' => $file->store('customer-docs', 'public'),
                        'display_name' => $file->getClientOriginalName(),
                    ]);
                }
            }

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
            'iqama_type' => 'nullable|string|max:50',
            'passport_no' => 'required|string|max:50|unique:customers,passport_no,' . $customer->id,
            'iqama_no' => 'nullable|string|max:50|unique:customers,iqama_no,' . $customer->id,
            'mobile_no' => 'required|string|max:20',
            'ref_iqama_no' => 'nullable|string|max:50',
            'ref_mobile_no' => 'nullable|string|max:20',
            'ref_iqama_doc' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
            'customer_docs' => 'nullable|array',
            'customer_docs.*' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
            'address' => 'nullable|string|max:500',
        ]);

        try {
            if ($request->hasFile('ref_iqama_doc')) {
                if ($customer->ref_iqama_doc && Storage::disk('public')->exists($customer->ref_iqama_doc)) {
                    Storage::disk('public')->delete($customer->ref_iqama_doc);
                }
                $path = $request->file('ref_iqama_doc')->store('customer-docs', 'public');
                $validated['ref_iqama_doc'] = $path;
            }

            if ($request->hasFile('customer_docs')) {
                foreach ($request->file('customer_docs') as $file) {
                    $customer->documents()->create([
                        'owner_type' => 'customer',
                        'owner_id' => $customer->id,
                        'file_path' => $file->store('customer-docs', 'public'),
                        'display_name' => $file->getClientOriginalName(),
                    ]);
                }
            }

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