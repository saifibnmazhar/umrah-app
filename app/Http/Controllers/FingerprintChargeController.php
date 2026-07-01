<?php

namespace App\Http\Controllers;

use App\Models\District;
use App\Models\FingerprintCharge;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FingerprintChargeController extends Controller
{
    public function index()
    {
        $query = FingerprintCharge::with(['district', 'user']);

        if (request()->has('division') && request('division')) {
            $query->whereHas('district', fn($q) => $q->where('division', request('division')));
        }

        if (request()->has('district') && request('district')) {
            $query->where('district_id', request('district'));
        }

        $fingerprintCharges = $query->orderBy('id')->paginate(10)->withQueryString();
        $districts = District::orderBy('division')->orderBy('name')->get();
        $divisions = District::distinct()->pluck('division')->sort();
        return view('fingerprint-charges.index', compact('fingerprintCharges', 'districts', 'divisions'));
    }

    public function create()
    {
        abort_if(!auth()->user()->roles->whereIn('name', ['Super Admin', 'Co Admin'])->isNotEmpty(), 403);
        $districts = District::orderBy('name')->get();
        return view('fingerprint-charges.create', compact('districts'));
    }

    public function store(Request $request)
    {
        abort_if(!auth()->user()->roles->whereIn('name', ['Super Admin', 'Co Admin'])->isNotEmpty(), 403);
        $validated = $request->validate([
            'district_id' => [
                'required',
                'integer',
                'exists:districts,id',
                Rule::unique('fingerprint_charges')->where(function ($query) use ($request) {
                    return $query->where('district_id', $request->district_id);
                }),
            ],
            'fingerprint_charge' => 'required|numeric|min:0',
        ]);

        $userId = auth()->id() ?? User::first()?->id;
        $validated['user_id'] = $userId;

        try {
            $division = $request->query('division');
            FingerprintCharge::create($validated);
            $tab = $request->input('tab', 'fingerprint-charge');
            return redirect()->route('settings', ['tab' => $tab])->with('success', 'Fingerprint charge created successfully.')->with('division', $division);
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to create fingerprint charge.')->withInput();
        }
    }

    public function edit(FingerprintCharge $fingerprintCharge)
    {
        abort_if(!auth()->user()->roles->whereIn('name', ['Super Admin', 'Co Admin'])->isNotEmpty(), 403);
        $districts = District::orderBy('name')->get();
        return view('fingerprint-charges.edit', compact('fingerprintCharge', 'districts'));
    }

    public function update(Request $request, FingerprintCharge $fingerprintCharge)
    {
        abort_if(!auth()->user()->roles->whereIn('name', ['Super Admin', 'Co Admin'])->isNotEmpty(), 403);
        $validated = $request->validate([
            'district_id' => [
                'required',
                'integer',
                'exists:districts,id',
                Rule::unique('fingerprint_charges')->where(function ($query) use ($request) {
                    return $query->where('district_id', $request->district_id);
                })->ignore($fingerprintCharge->id),
            ],
            'fingerprint_charge' => 'required|numeric|min:0',
        ]);

        try {
            $fingerprintCharge->update($validated);
            $tab = $request->input('tab', 'fingerprint-charge');
            return redirect()->route('settings', ['tab' => $tab])->with('success', 'Fingerprint charge updated successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to update fingerprint charge.')->withInput();
        }
    }

    public function destroy(Request $request, FingerprintCharge $fingerprintCharge)
    {
        abort_if(!auth()->user()->roles->whereIn('name', ['Super Admin', 'Co Admin'])->isNotEmpty(), 403);
        try {
            $fingerprintCharge->delete();
            $tab = $request->input('tab', 'fingerprint-charge');
            return redirect()->route('settings', ['tab' => $tab])->with('success', 'Fingerprint charge deleted successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to delete fingerprint charge.');
        }
    }
}