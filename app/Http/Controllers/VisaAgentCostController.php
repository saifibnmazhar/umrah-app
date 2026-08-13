<?php

namespace App\Http\Controllers;

use App\Models\VisaAgent;
use App\Models\VisaAgentCost;
use Illuminate\Http\Request;

class VisaAgentCostController extends Controller
{
    public function index()
    {
        $visaAgentCosts = VisaAgentCost::with(['visaAgent', 'user'])
            ->orderBy('id')
            ->paginate(10)
            ->withQueryString();
        $visaAgents = VisaAgent::orderBy('name')->get();
        return view('visa-agent-costs.index', compact('visaAgentCosts', 'visaAgents'));
    }

    public function create()
    {
        $visaAgents = VisaAgent::orderBy('name')->get();
        return view('visa-agent-costs.create', compact('visaAgents'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'visa_agent_id' => 'required|integer|exists:visa_agents,id',
            'visa_agent_cost' => 'required|numeric|min:0',
        ]);

        if (VisaAgentCost::where('visa_agent_id', $request->visa_agent_id)->exists()) {
            return redirect()->route('visa.admin', ['tab' => 'visa-agent-costs'])
                ->with('toast', ['message' => 'Visa agent cost already exists for that agent. Please update that cost.', 'type' => 'error'])
                ->withInput();
        }

        try {
            $validated['user_id'] = auth()->id() ?? 1;
            VisaAgentCost::create($validated);
            return redirect()->route('visa.admin', ['tab' => 'visa-agent-costs'])->with('success', 'Visa agent cost created successfully.');
        } catch (\Exception $e) {
            \Log::error('VisaAgentCost Create Error: ' . $e->getMessage());
            $message = $e instanceof \Illuminate\Database\QueryException
                ? \App\Exceptions\DatabaseErrorHumanizer::humanize($e)
                : 'Failed to create visa agent cost.';
            return redirect()->back()->with('error', $message)->withInput();
        }
    }

    public function edit(VisaAgentCost $visaAgentCost)
    {
        $visaAgents = VisaAgent::orderBy('name')->get();
        return view('visa-agent-costs.edit', compact('visaAgentCost', 'visaAgents'));
    }

    public function update(Request $request, VisaAgentCost $visaAgentCost)
    {
        $validated = $request->validate([
            'visa_agent_id' => 'required|integer|exists:visa_agents,id',
            'visa_agent_cost' => 'required|numeric|min:0',
        ]);

        if (VisaAgentCost::where('visa_agent_id', $request->visa_agent_id)
            ->where('id', '!=', $visaAgentCost->id)
            ->exists()) {
            return redirect()->route('visa.admin', ['tab' => 'visa-agent-costs'])
                ->with('toast', ['message' => 'Visa agent cost already exists for that agent. Please update that cost.', 'type' => 'error'])
                ->withInput();
        }

        try {
            $visaAgentCost->update($validated);
            return redirect()->route('visa.admin', ['tab' => 'visa-agent-costs'])->with('success', 'Visa agent cost updated successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to update visa agent cost.')->withInput();
        }
    }

    public function destroy(VisaAgentCost $visaAgentCost)
    {
        try {
            $visaAgentCost->delete();
            return redirect()->route('visa.admin', ['tab' => 'visa-agent-costs'])->with('success', 'Visa agent cost deleted successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to delete visa agent cost.');
        }
    }
}