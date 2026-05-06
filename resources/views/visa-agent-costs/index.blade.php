@extends('layouts.app')
@section('title', 'Visa Agent Costs')
@section('content')
<div class="max-w-4xl mx-auto">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-slate-800">Visa Agent Costs</h1>
        <button onclick="showModal()" class="px-4 py-2 bg-slate-700 text-white rounded-lg hover:bg-slate-800 transition font-medium">
            Add New
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

    <div class="bg-white rounded-lg shadow-sm border border-slate-200 p-6">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-600">
                    <tr>
                        <th class="px-3 py-2 text-left font-medium">Visa Agent</th>
                        <th class="px-3 py-2 text-left font-medium">Created By</th>
                        <th class="px-3 py-2 text-right font-medium">Cost (SAR)</th>
                        <th class="px-3 py-2 text-center font-medium">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    @forelse($visaAgentCosts as $cost)
                        <tr class="hover:bg-slate-50">
                            <td class="px-3 py-2 text-slate-600">{{ $cost->visaAgent->name ?? 'N/A' }}</td>
                            <td class="px-3 py-2 text-slate-600">{{ $cost->user->name ?? 'N/A' }}</td>
                            <td class="px-3 py-2 text-right text-slate-800 font-medium">{{ number_format($cost->visa_agent_cost, 2) }} SAR</td>
                            <td class="px-3 py-2 text-center">
                                <button onclick="editCost({{ $cost->id }}, {{ $cost->visa_agent_id }}, {{ $cost->visa_agent_cost }})" class="text-xs text-slate-600 hover:text-slate-800 mr-3">Edit</button>
                                <form method="POST" action="{{ route('visa-agent-costs.destroy', $cost->id) }}" onsubmit="return confirm('Are you sure you want to delete this visa agent cost?')" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-xs text-red-500 hover:text-red-700">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-3 py-8 text-center text-slate-500">
                                No visa agent costs configured yet.
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
<div id="costModal" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black bg-opacity-50" onclick="hideModal()"></div>
    <div class="fixed top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 bg-white rounded-xl shadow-xl w-full max-w-md mx-4 p-6">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-semibold text-slate-700" id="modalTitle">Add Visa Agent Cost</h3>
            <button onclick="hideModal()" class="text-slate-400 hover:text-slate-600">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        <form id="costForm" method="POST" action="{{ route('visa-agent-costs.store') }}">
            @csrf
            <input type="hidden" id="formMethod" name="_method" value="POST">
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Visa Agent *</label>
                    <select id="visaAgentSelect" name="visa_agent_id" required class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none bg-white">
                        <option value="">Select Visa Agent</option>
                        @foreach($visaAgents as $agent)
                            <option value="{{ $agent->id }}">{{ $agent->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Cost (SAR) *</label>
                    <input type="number" id="costInput" name="visa_agent_cost" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none" value="0" min="0" step="0.01" required>
                </div>
            </div>
            <div class="flex gap-3 mt-6">
                <button type="button" onclick="hideModal()" class="flex-1 px-4 py-2 border border-slate-300 text-slate-700 rounded-lg hover:bg-slate-50 transition font-medium">Cancel</button>
                <button type="submit" class="flex-1 px-4 py-2 bg-slate-700 text-white rounded-lg hover:bg-slate-800 transition font-medium">Submit</button>
            </div>
        </form>
    </div>
</div>

<script>
function showModal() {
    document.getElementById('costModal').classList.remove('hidden');
    document.getElementById('formMethod').value = 'POST';
    document.getElementById('costForm').action = '{{ route("visa-agent-costs.store") }}';
    document.getElementById('modalTitle').textContent = 'Add Visa Agent Cost';
    document.getElementById('costInput').value = '0';
    document.getElementById('visaAgentSelect').value = '';
}

function hideModal() {
    document.getElementById('costModal').classList.add('hidden');
}

function editCost(id, agentId, cost) {
    document.getElementById('costModal').classList.remove('hidden');
    document.getElementById('formMethod').value = 'PUT';
    document.getElementById('costForm').action = '/visa-agent-costs/' + id;
    document.getElementById('modalTitle').textContent = 'Edit Visa Agent Cost';
    document.getElementById('costInput').value = cost;
    document.getElementById('visaAgentSelect').value = agentId;
}
</script>
@endsection