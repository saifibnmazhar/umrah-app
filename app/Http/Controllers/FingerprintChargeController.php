<?php

namespace App\Http\Controllers;

use App\Models\District;
use App\Models\FingerprintCharge;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FingerprintChargeController extends Controller
{
    public function index()
    {
        $fingerprintCharges = FingerprintCharge::with(['district', 'user'])
            ->orderBy('id')
            ->paginate(10)
            ->withQueryString();
        return view('fingerprint-charges.index', compact('fingerprintCharges'));
    }

    public function create()
    {
        $districts = District::orderBy('name')->get();
        return view('fingerprint-charges.create', compact('districts'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'district_id' => [
                'required',
                'integer',
                'exists:districts,id',
                Rule::unique('fingerprint_charges')->where(function ($query) use ($request) {
                    return $query->where('district_id', $request->district_id);
                }),
            ],
            'user_id' => 'required|integer|exists:users,id',
            'fingerprint_charge' => 'required|numeric|min:0',
        ]);

        try {
            FingerprintCharge::create($validated);
            return redirect()->route('fingerprint-charges.index')->with('success', 'Fingerprint charge created successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to create fingerprint charge.')->withInput();
        }
    }

    public function edit(FingerprintCharge $fingerprintCharge)
    {
        $districts = District::orderBy('name')->get();
        return view('fingerprint-charges.edit', compact('fingerprintCharge', 'districts'));
    }

    public function update(Request $request, FingerprintCharge $fingerprintCharge)
    {
        $validated = $request->validate([
            'district_id' => [
                'required',
                'integer',
                'exists:districts,id',
                Rule::unique('fingerprint_charges')->where(function ($query) use ($request) {
                    return $query->where('district_id', $request->district_id);
                })->ignore($fingerprintCharge->id),
            ],
            'user_id' => 'required|integer|exists:users,id',
            'fingerprint_charge' => 'required|numeric|min:0',
        ]);

        try {
            $fingerprintCharge->update($validated);
            return redirect()->route('fingerprint-charges.index')->with('success', 'Fingerprint charge updated successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to update fingerprint charge.')->withInput();
        }
    }

    public function destroy(FingerprintCharge $fingerprintCharge)
    {
        try {
            $fingerprintCharge->delete();
            return redirect()->route('fingerprint-charges.index')->with('success', 'Fingerprint charge deleted successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to delete fingerprint charge.');
        }
    }
}