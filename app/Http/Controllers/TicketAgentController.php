<?php

namespace App\Http\Controllers;

use App\Models\TicketAgent;
use Illuminate\Http\Request;

class TicketAgentController extends Controller
{
    public function index()
    {
        $ticketAgents = TicketAgent::orderBy('name')
            ->paginate(10)
            ->withQueryString();
        return view('ticket-agents.index', compact('ticketAgents'));
    }

    public function create()
    {
        return view('ticket-agents.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'nullable|string|max:500',
            'contacts' => 'nullable|string|max:255',
        ]);

        try {
            TicketAgent::create($validated);
            return redirect()->route('ticket-agents.index')->with('success', 'Ticket agent created successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to create ticket agent.')->withInput();
        }
    }

    public function edit(TicketAgent $ticketAgent)
    {
        return view('ticket-agents.edit', compact('ticketAgent'));
    }

    public function update(Request $request, TicketAgent $ticketAgent)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'nullable|string|max:500',
            'contacts' => 'nullable|string|max:255',
        ]);

        try {
            $ticketAgent->update($validated);
            return redirect()->route('ticket-agents.index')->with('success', 'Ticket agent updated successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to update ticket agent.')->withInput();
        }
    }

    public function destroy(TicketAgent $ticketAgent)
    {
        try {
            $ticketAgent->delete();
            return redirect()->route('ticket-agents.index')->with('success', 'Ticket agent deleted successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to delete ticket agent.');
        }
    }
}