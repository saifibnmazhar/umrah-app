<?php

namespace App\Http\Controllers;

use App\Models\VisaAgent;
use Illuminate\Http\Request;

class VisaAgentController extends Controller
{
    public function index()
    {
        $visaAgents = VisaAgent::orderBy('name')
            ->paginate(10)
            ->withQueryString();
        return view('visa-agents.index', compact('visaAgents'));
    }

    public function create()
    {
        return view('visa-agents.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'nullable|string|max:500',
            'contacts' => 'nullable|string|max:255',
        ]);

        try {
            VisaAgent::create($validated);
            return redirect()->route('visa.admin', ['tab' => 'visa-agents'])->with('success', 'Visa agent created successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to create visa agent.')->withInput();
        }
    }

    public function edit(VisaAgent $visaAgent)
    {
        return view('visa-agents.edit', compact('visaAgent'));
    }

    public function update(Request $request, VisaAgent $visaAgent)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'nullable|string|max:500',
            'contacts' => 'nullable|string|max:255',
        ]);

        try {
            $visaAgent->update($validated);
            return redirect()->route('visa.admin', ['tab' => 'visa-agents'])->with('success', 'Visa agent updated successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to update visa agent.')->withInput();
        }
    }

    public function destroy(VisaAgent $visaAgent)
    {
        try {
            $visaAgent->delete();
            return redirect()->route('visa.admin', ['tab' => 'visa-agents'])->with('success', 'Visa agent deleted successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to delete visa agent.');
        }
    }
}