<?php

namespace App\Http\Controllers;

use App\Models\VisaSellingPrice;
use App\Models\VisaAgentCost;
use App\Models\VisaAgent;
use App\Models\CommissionAgent;

class VisaAdminController extends Controller
{
    public function index()
    {
        $visaSellingPrices = VisaSellingPrice::withCount(['packages', 'visaSubmissions'])
            ->orderBy('created_at', 'desc')
            ->paginate(10)
            ->withQueryString();

        $visaAgentCosts = VisaAgentCost::with(['visaAgent'])
            ->orderBy('id', 'desc')
            ->paginate(10)
            ->withQueryString();

        $visaAgents = VisaAgent::orderBy('name')->paginate(10)->withQueryString();
        $allVisaAgents = VisaAgent::orderBy('name')->get();

        $commissionAgents = CommissionAgent::with('visaAgent')
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        return view('visas.admin', compact('visaSellingPrices', 'visaAgentCosts', 'visaAgents', 'allVisaAgents', 'commissionAgents'));
    }
}