<?php

namespace App\Http\Controllers;

use App\Models\District;
use App\Models\FingerprintCharge;
use App\Models\FlightDateGap;
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

        $flightDateGap = FlightDateGap::first();

        $settings = [
            'package_name' => 'Umrah Premium Package',
            'package_price' => 2500,
            'package_features' => '• 5-star accommodation\n• Round-trip flights\n• Visa processing\n• Airport transfers\n• Guided tours\n• 24/7 support',
            'package_duration' => 10,
            'package_status' => 'active',
        ];

        return view('settings.index', compact('fingerprintCharges', 'districts', 'divisions', 'flightDateGap', 'settings'));
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