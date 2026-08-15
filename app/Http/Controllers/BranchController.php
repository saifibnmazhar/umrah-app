<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use Illuminate\Http\Request;

class BranchController extends Controller
{
    public function index()
    {
        $branches = Branch::orderBy('name')->paginate(10)->withQueryString();

        return view('branches.index', compact('branches'));
    }

    public function create()
    {
        return view('branches.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'required|string|max:255',
            'contacts' => 'required|string|max:255',
            'location' => 'required|in:KSA,BD',
            'branch_code' => 'nullable|string|max:255|unique:branches,branch_code',
        ]);

        $validated['fingerprint_operation'] = $validated['location'] === 'BD';

        try {
            Branch::create($validated);

            return redirect()->route('branches.index')->with('success', 'Branch created successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to create branch.')->withInput();
        }
    }

    public function edit(Branch $branch)
    {
        return view('branches.edit', compact('branch'));
    }

    public function update(Request $request, Branch $branch)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'required|string|max:255',
            'contacts' => 'required|string|max:255',
            'location' => 'required|in:KSA,BD',
            'branch_code' => 'nullable|string|max:255|unique:branches,branch_code,'.$branch->id,
        ]);

        $validated['fingerprint_operation'] = $validated['location'] === 'BD';

        try {
            $branch->update($validated);

            return redirect()->route('branches.index')->with('success', 'Branch updated successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to update branch.')->withInput();
        }
    }

    public function destroy(Branch $branch)
    {
        try {
            $branch->delete();

            return redirect()->route('branches.index')->with('success', 'Branch deleted successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to delete branch.');
        }
    }
}
