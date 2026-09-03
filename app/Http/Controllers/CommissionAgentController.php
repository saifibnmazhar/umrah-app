<?php

namespace App\Http\Controllers;

use App\Models\CommissionAgent;
use Illuminate\Http\Request;

class CommissionAgentController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'visa_agent_id' => 'required|exists:visa_agents,id',
            'name' => 'required|string|max:255',
            'address' => 'nullable|string|max:500',
            'contacts' => 'nullable|string|max:255',
        ]);

        try {
            CommissionAgent::create($validated);

            return redirect()->route('visa.admin', ['tab' => 'commission-agents'])->with('success', 'Commission agent created successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to create commission agent.')->withInput();
        }
    }

    public function update(Request $request, CommissionAgent $commissionAgent)
    {
        $validated = $request->validate([
            'visa_agent_id' => 'required|exists:visa_agents,id',
            'name' => 'required|string|max:255',
            'address' => 'nullable|string|max:500',
            'contacts' => 'nullable|string|max:255',
        ]);

        try {
            $commissionAgent->update($validated);

            return redirect()->route('visa.admin', ['tab' => 'commission-agents'])->with('success', 'Commission agent updated successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to update commission agent.')->withInput();
        }
    }

    public function destroy(CommissionAgent $commissionAgent)
    {
        try {
            $commissionAgent->delete();

            return redirect()->route('visa.admin', ['tab' => 'commission-agents'])->with('success', 'Commission agent deleted successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to delete commission agent.');
        }
    }
}
