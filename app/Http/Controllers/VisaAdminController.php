<?php

namespace App\Http\Controllers;

use App\Models\VisaSellingPrice;
use App\Models\VisaAgentCost;
use App\Models\VisaAgent;

class VisaAdminController extends Controller
{
    public function index()
    {
        $visaSellingPrices = VisaSellingPrice::orderBy('created_at', 'desc')
            ->paginate(10)
            ->withQueryString();

        $visaAgentCosts = VisaAgentCost::with(['visaAgent'])
            ->orderBy('id', 'desc')
            ->paginate(10)
            ->withQueryString();

        $visaAgents = VisaAgent::orderBy('name')->paginate(10)->withQueryString();
        $allVisaAgents = VisaAgent::orderBy('name')->get();

        return view('visas.admin', compact('visaSellingPrices', 'visaAgentCosts', 'visaAgents', 'allVisaAgents'));
    }
}