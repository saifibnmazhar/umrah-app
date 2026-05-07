<?php

namespace App\Http\Controllers;

use App\Models\District;
use App\Models\FingerprintCharge;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function index()
    {
        $fingerprintChargesQuery = FingerprintCharge::with(['district', 'user']);

        if (request()->has('division') && request('division')) {
            $fingerprintChargesQuery->whereHas('district', fn($q) => $q->where('division', request('division')));
        }

        $fingerprintCharges = $fingerprintChargesQuery->orderBy('id')->paginate(10)->withQueryString();
        $districts = District::orderBy('division')->orderBy('name')->get();
        $divisions = District::distinct()->pluck('division')->sort();

        return view('settings.index', compact('fingerprintCharges', 'districts', 'divisions'));
    }

    public function updateFlightDateGap(Request $request)
    {
        return redirect()->route('settings')->with('success', 'Flight date gap settings updated');
    }

    public function updateFingerprintCharge(Request $request)
    {
        return redirect()->route('settings')->with('success', 'Fingerprint charge settings updated');
    }

    public function updatePackageConfiguration(Request $request)
    {
        return redirect()->route('settings')->with('success', 'Package configuration updated');
    }
}