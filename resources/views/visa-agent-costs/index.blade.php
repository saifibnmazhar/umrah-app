@extends('layouts.app')
@section('title', 'Agent Wise Visa Pricing')
@section('content')
<div class="max-w-4xl mx-auto">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-slate-800">Agent Wise Visa Pricing</h1>
        <button onclick="openModal()" class="px-4 py-2 bg-slate-700 text-white rounded-lg hover:bg-slate-800 transition font-medium flex items-center gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
            </svg>
            Add Agent Price
        </button>
    </div>

    @if(session('success'))
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-lg mb-4">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-4">
            {{ session('error') }}
        </div>
    @endif

    <div class="bg-white rounded-lg shadow-lg p-6">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[600px] text-sm">
                <thead class="bg-slate-50 text-slate-600">
                    <tr>
                        <th class="px-3 py-2 text-left font-medium">Agent Name</th>
                        <th class="px-3 py-2 text-left font-medium">Saudi Address</th>
                        <th class="px-3 py-2 text-left font-medium">Agent Visa Price (SAR)</th>
                        <th class="px-3 py-2 text-left font-medium">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    @forelse($visaAgentCosts as $cost)
                        <tr class="hover:bg-slate-50">
                            <td class="px-3 py-2 text-slate-800 font-medium">{{ $cost->visaAgent->name ?? 'N/A' }}</td>
                            <td class="px-3 py-2 text-slate-600">{{ $cost->visaAgent->address ?? '-' }}</td>
                            <td class="px-3 py-2 text-slate-800 font-medium">{{ number_format($cost->visa_agent_cost, 2) }}</td>
                            <td class="px-3 py-2">
                                <div class="flex gap-2">
                                    <button onclick="editCost({{ $cost->id }}, {{ $cost->visa_agent_id }}, '{{ $cost->visaAgent->address ?? '' }}', {{ $cost->visa_agent_cost }})" class="text-xs bg-slate-100 hover:bg-slate-200 text-slate-600 px-2 py-1 rounded">Edit</button>
                                    <form method="POST" action="{{ route('visa-agent-costs.destroy', $cost->id) }}" onsubmit="return confirm('Are you sure you want to delete this agent price record?')" class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-xs bg-red-100 hover:bg-red-200 text-red-600 px-2 py-1 rounded">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-3 py-8 text-center text-slate-500">
                                No agent visa price records.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4 flex justify-center">
            {{ $visaAgentCosts->links() }}
        </div>
    </div>
</div>

<!-- Modal -->
<div id="agentPriceModal" class="hidden fixed inset-0 z-50 flex items-center justify-center">
    <div class="absolute inset-0 bg-black/50" onclick="closeModal()"></div>
    <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-sm mx-4 p-6">
        <h3 class="text-xl font-semibold text-slate-800 mb-4" id="modalTitle">Agent Visa Price</h3>
        <form id="costForm" method="POST" action="{{ route('visa-agent-costs.store') }}">
            @csrf
            <input type="hidden" id="formMethod" name="_method" value="POST">
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Agent Name</label>
                    <select id="visaAgentSelect" name="visa_agent_id" required class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none bg-white">
                        <option value="">Select Agent</option>
                        @foreach($visaAgents as $agent)
                            <option value="{{ $agent->id }}">{{ $agent->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Saudi Address</label>
                    <input type="text" id="agentAddress" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none" placeholder="Saudi address">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Agent Visa Price (SAR)</label>
                    <input type="number" id="costInput" name="visa_agent_cost" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none" value="0" min="0" required>
                </div>
            </div>
            <div class="flex gap-3 mt-6">
                <button type="submit" class="flex-1 px-6 py-2 bg-slate-700 text-white rounded-lg hover:bg-slate-800 transition font-medium">Save Agent Price</button>
                <button type="button" onclick="closeModal()" class="flex-1 px-6 py-2 border border-slate-300 text-slate-700 rounded-lg hover:bg-slate-50 transition font-medium">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal() {
    document.getElementById('agentPriceModal').classList.remove('hidden');
    document.getElementById('formMethod').value = 'POST';
    document.getElementById('costForm').action = '{{ route("visa-agent-costs.store") }}';
    document.getElementById('modalTitle').textContent = 'Agent Visa Price';
    document.getElementById('costInput').value = '0';
    document.getElementById('visaAgentSelect').value = '';
    document.getElementById('agentAddress').value = '';
}

function closeModal() {
    document.getElementById('agentPriceModal').classList.add('hidden');
}

function editCost(id, agentId, agentAddress, cost) {
    document.getElementById('agentPriceModal').classList.remove('hidden');
    document.getElementById('formMethod').value = 'PUT';
    document.getElementById('costForm').action = '/visa-agent-costs/' + id;
    document.getElementById('modalTitle').textContent = 'Edit Agent Visa Price';
    document.getElementById('costInput').value = cost;
    document.getElementById('visaAgentSelect').value = agentId;
    document.getElementById('agentAddress').value = agentAddress;
}
</script>
@endsection