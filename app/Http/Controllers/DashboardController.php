<?php

namespace App\Http\Controllers;

use App\Models\Package;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $packages = Package::with(['ticketFare.route.fromCity', 'ticketFare.route.toCity', 'ticketFare.route.returnCity', 'ticketFare.airline', 'ticketFare.airlineClass'])
            ->orderBy('id', 'desc')
            ->limit(6)
            ->get();

        return view('dashboard.index', compact('packages'));
    }
}