@extends('layouts.app')

@section('title', 'Visa Admin')

@section('content')
<div class="max-w-5xl mx-auto pt-6" x-data="{
    activeTab: new URLSearchParams(window.location.search).get('tab') || 'visa-selling-prices',
    updateUrlTab(name) {
        const url = new URL(window.location);
        url.searchParams.set('tab', name);
        history.replaceState(null, '', url);
    },
}">
    <h1 class="text-2xl font-bold text-slate-800 mb-6">Visa Admin</h1>

    <div class="border-b border-gray-200 mb-6">
        <nav class="-mb-px flex space-x-8">
            <button
                @click="activeTab = 'visa-selling-prices'; updateUrlTab('visa-selling-prices')"
                :class="{ 'border-blue-500 text-blue-600': activeTab === 'visa-selling-prices', 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300': activeTab !== 'visa-selling-prices' }"
                class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-colors duration-200"
            >
                Visa Selling Prices
            </button>
            <button
                @click="activeTab = 'visa-agent-costs'; updateUrlTab('visa-agent-costs')"
                :class="{ 'border-blue-500 text-blue-600': activeTab === 'visa-agent-costs', 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300': activeTab !== 'visa-agent-costs' }"
                class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-colors duration-200"
            >
                Visa Agent Costs
            </button>
            <button
                @click="activeTab = 'visa-agents'; updateUrlTab('visa-agents')"
                :class="{ 'border-blue-500 text-blue-600': activeTab === 'visa-agents', 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300': activeTab !== 'visa-agents' }"
                class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-colors duration-200"
            >
                Visa Agents
            </button>
            @if(auth()->user()->roles->pluck('name')->intersect(['Super Admin', 'Co Admin'])->isNotEmpty())
            <button
                @click="activeTab = 'commission-agents'; updateUrlTab('commission-agents')"
                :class="{ 'border-blue-500 text-blue-600': activeTab === 'commission-agents', 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300': activeTab !== 'commission-agents' }"
                class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-colors duration-200"
            >
                Commission Agents
            </button>
            @endif
        </nav>
    </div>

    <!-- Visa Selling Prices Tab -->
    <div x-show="activeTab === 'visa-selling-prices'" x-cloak>
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-xl font-bold text-slate-800">Visa Selling Prices</h2>
            <button onclick="openVisaPriceModal()" class="px-4 py-2 bg-slate-700 text-white rounded-lg hover:bg-slate-800 transition font-medium flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                </svg>
                Add Visa Price
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
                <table class="w-full min-w-[400px] text-sm">
                    <thead class="bg-slate-50 text-slate-600">
                        <tr>
                            <th class="px-3 py-2 text-left font-medium">Date</th>
                            <th class="px-3 py-2 text-left font-medium">Price (SAR)</th>
                            <th class="px-3 py-2 text-left font-medium">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        @forelse($visaSellingPrices as $price)
                            <tr class="hover:bg-slate-50">
                                <td class="px-3 py-2 text-slate-600">{{ $price->created_at->format('Y-m-d') }}</td>
                                <td class="px-3 py-2 text-slate-800 font-medium">@currency($price->selling_price, 2)</td>
                                <td class="px-3 py-2">
                                    <div class="flex gap-2">
                                        @if($price->is_locked)
                                            <span class="text-xs bg-slate-100 text-slate-400 px-2 py-1 rounded cursor-not-allowed" title="In use by packages or visa submissions">Edit</span>
                                            <span class="text-xs bg-red-100 text-red-400 px-2 py-1 rounded cursor-not-allowed" title="In use by packages or visa submissions">Delete</span>
                                        @else
                                            <button onclick="editVisaPrice({{ $price->id }}, {{ $price->selling_price }})" class="text-xs bg-slate-100 hover:bg-slate-200 text-slate-600 px-2 py-1 rounded">Edit</button>
                                            <form method="POST" action="{{ route('visa-selling-prices.destroy', $price->id) }}" onsubmit="return confirm('Are you sure?')" class="inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-xs bg-red-100 hover:bg-red-200 text-red-600 px-2 py-1 rounded">Delete</button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="px-3 py-8 text-center text-slate-500">
                                    No visa price records yet.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4 flex justify-center">
                {{ $visaSellingPrices->links() }}
            </div>
        </div>
    </div>

    <!-- Visa Agent Costs Tab -->
    <div x-show="activeTab === 'visa-agent-costs'" x-cloak>
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-xl font-bold text-slate-800">Visa Agent Costs</h2>
            <button onclick="openAgentCostModal()" class="px-4 py-2 bg-slate-700 text-white rounded-lg hover:bg-slate-800 transition font-medium flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                </svg>
                Add Agent Price
            </button>
        </div>

        <div class="bg-white rounded-lg shadow-lg p-6">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[600px] text-sm">
                    <thead class="bg-slate-50 text-slate-600">
                        <tr>
                            <th class="px-3 py-2 text-left font-medium">Agent Name</th>
                            <th class="px-3 py-2 text-left font-medium">Agent Visa Price (SAR)</th>
                            <th class="px-3 py-2 text-left font-medium">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        @forelse($visaAgentCosts as $cost)
                            <tr class="hover:bg-slate-50">
                                <td class="px-3 py-2 text-slate-800 font-medium">{{ $cost->visaAgent->name ?? 'N/A' }}</td>
                                <td class="px-3 py-2 text-slate-800 font-medium">@currency($cost->visa_agent_cost, 2)</td>
                                <td class="px-3 py-2">
                                    <div class="flex gap-2">
                                        <button onclick="editAgentCost({{ $cost->id }}, {{ $cost->visa_agent_id }}, {{ $cost->visa_agent_cost }})" class="text-xs bg-slate-100 hover:bg-slate-200 text-slate-600 px-2 py-1 rounded">Edit</button>
                                        <form method="POST" action="{{ route('visa-agent-costs.destroy', $cost->id) }}" onsubmit="return confirm('Are you sure?')" class="inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-xs bg-red-100 hover:bg-red-200 text-red-600 px-2 py-1 rounded">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                            <td colspan="3" class="px-3 py-8 text-center text-slate-500">
                                No agent price records.
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

    <!-- Visa Agents Tab -->
    <div x-show="activeTab === 'visa-agents'" x-cloak>
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-xl font-bold text-slate-800">Visa Agents</h2>
            <button onclick="openVisaAgentModal()" class="px-4 py-2 bg-slate-700 text-white rounded-lg hover:bg-slate-800 transition font-medium flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                </svg>
                Add Visa Agent
            </button>
        </div>

        <div class="bg-white rounded-lg shadow-sm border border-slate-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-slate-600 text-xs font-medium uppercase tracking-wider">
                        <tr>
                            <th class="px-6 py-4 text-left">Name</th>
                            <th class="px-6 py-4 text-left">Address</th>
                            <th class="px-6 py-4 text-left">Contacts</th>
                            <th class="px-6 py-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        @forelse($visaAgents as $agent)
                            <tr class="hover:bg-slate-50">
                                <td class="px-6 py-4 text-slate-700 font-medium">{{ $agent->name }}</td>
                                <td class="px-6 py-4 text-slate-600">{{ $agent->address ?? '—' }}</td>
                                <td class="px-6 py-4 text-slate-600">{{ $agent->contacts ?? '—' }}</td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex items-center justify-end gap-4">
                                        <button onclick="editVisaAgent({{ $agent->id }}, '{{ $agent->name }}', '{{ $agent->address ?? '' }}', '{{ $agent->contacts ?? '' }}')" class="text-slate-600 hover:text-slate-800 font-medium text-sm">Edit</button>
                                        <form method="POST" action="{{ route('visa-agents.destroy', $agent->id) }}" onsubmit="return confirm('Are you sure?')" class="inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-red-600 hover:text-red-800 font-medium text-sm">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-6 py-12 text-center text-slate-500">
                                    No visa agents found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-6 flex justify-center"
             @click.prevent="
                 const el = $event.target.closest('a');
                 if (el && el.href) {
                     const url = new URL(el.href);
                     url.searchParams.set('tab', activeTab);
                     window.location.href = url.toString();
                 }
             ">
            {{ $visaAgents->links() }}
        </div>
    </div>

    @if(auth()->user()->roles->pluck('name')->intersect(['Super Admin', 'Co Admin'])->isNotEmpty())
    <!-- Commission Agents Tab -->
    <div x-show="activeTab === 'commission-agents'" x-cloak>
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-xl font-bold text-slate-800">Commission Agents</h2>
            <button onclick="openCommissionAgentModal()" class="px-4 py-2 bg-slate-700 text-white rounded-lg hover:bg-slate-800 transition font-medium flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                </svg>
                Add Commission Agent
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

        <div class="bg-white rounded-lg shadow-sm border border-slate-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-slate-600 text-xs font-medium uppercase tracking-wider">
                        <tr>
                            <th class="px-6 py-4 text-left">Visa Agent</th>
                            <th class="px-6 py-4 text-left">Name</th>
                            <th class="px-6 py-4 text-left">Address</th>
                            <th class="px-6 py-4 text-left">Contacts</th>
                            <th class="px-6 py-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        @forelse($commissionAgents as $agent)
                            <tr class="hover:bg-slate-50">
                                <td class="px-6 py-4 text-slate-700 font-medium">{{ $agent->visaAgent->name ?? '—' }}</td>
                                <td class="px-6 py-4 text-slate-700 font-medium">{{ $agent->name }}</td>
                                <td class="px-6 py-4 text-slate-600">{{ $agent->address ?? '—' }}</td>
                                <td class="px-6 py-4 text-slate-600">{{ $agent->contacts ?? '—' }}</td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex items-center justify-end gap-4">
                                        <button onclick="editCommissionAgent({{ $agent->id }}, {{ $agent->visa_agent_id }}, '{{ $agent->name }}', '{{ $agent->address ?? '' }}', '{{ $agent->contacts ?? '' }}')" class="text-slate-600 hover:text-slate-800 font-medium text-sm">Edit</button>
                                        <form method="POST" action="{{ route('commission-agents.destroy', $agent->id) }}" onsubmit="return confirm('Are you sure?')" class="inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-red-600 hover:text-red-800 font-medium text-sm">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-12 text-center text-slate-500">
                                    No commission agents found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-6 flex justify-center"
             @click.prevent="
                 const el = $event.target.closest('a');
                 if (el && el.href) {
                     const url = new URL(el.href);
                     url.searchParams.set('tab', activeTab);
                     window.location.href = url.toString();
                 }
             ">
            {{ $commissionAgents->links() }}
        </div>
    </div>
    @endif
</div>

<!-- Visa Price Modal -->
<div id="visaPriceModal" class="hidden fixed inset-0 z-50 flex items-center justify-center">
    <div class="absolute inset-0 bg-black/50" onclick="closeVisaPriceModal()"></div>
    <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-sm mx-4 p-6">
        <h3 class="text-xl font-semibold text-slate-800 mb-4" id="visaPriceModalTitle">Add Visa Price</h3>
        <form id="visaPriceForm" method="POST" action="{{ route('visa-selling-prices.store') }}" x-data="{ bdtValue: 0 }">
            @csrf
            <input type="hidden" id="visaPriceFormMethod" name="_method" value="POST">
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Date</label>
                    <input type="text" id="visaPriceDate" class="w-full px-4 py-2 border border-slate-200 rounded-lg bg-slate-50 text-slate-600" value="{{ date('Y-m-d') }}" readonly>
                </div>
                <div x-show="$store.currency.mode === 'BDT'" x-cloak>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Price (BDT)</label>
                    <input type="number" x-model="bdtValue" @input="document.getElementById('visaPriceInput').value = (parseFloat(bdtValue || 0) / ($store.currency.rate || 1)).toFixed(6)" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none" min="0" step="0.01" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Price (SAR)</label>
                    <input type="number" id="visaPriceInput" name="selling_price" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none" value="" min="0" step="0.01" required :readonly="$store.currency.mode === 'BDT'">
                </div>
            </div>
            <div class="flex gap-3 mt-6">
                <button type="button" onclick="closeVisaPriceModal()" class="flex-1 px-6 py-2 border border-slate-300 text-slate-700 rounded-lg hover:bg-slate-50 transition font-medium">Cancel</button>
                <button type="submit" class="flex-1 px-6 py-2 bg-slate-700 text-white rounded-lg hover:bg-slate-800 transition font-medium">Save</button>
            </div>
        </form>
    </div>
</div>

<!-- Agent Cost Modal -->
<div id="agentCostModal" class="hidden fixed inset-0 z-50 flex items-center justify-center">
    <div class="absolute inset-0 bg-black/50" onclick="closeAgentCostModal()"></div>
    <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-sm mx-4 p-6">
        <h3 class="text-xl font-semibold text-slate-800 mb-4" id="agentCostModalTitle">Add Agent Price</h3>
        <form id="agentCostForm" method="POST" action="{{ route('visa-agent-costs.store') }}" x-data="{ bdtValue: 0 }">
            @csrf
            <input type="hidden" id="agentCostFormMethod" name="_method" value="POST">
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
                <div x-show="$store.currency.mode === 'BDT'" x-cloak>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Agent Visa Price (BDT)</label>
                    <input type="number" x-model="bdtValue" @input="document.getElementById('agentCostInput').value = (parseFloat(bdtValue || 0) / ($store.currency.rate || 1)).toFixed(6)" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none" min="0" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Agent Visa Price (SAR)</label>
                    <input type="number" id="agentCostInput" name="visa_agent_cost" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none" value="" min="0" required :readonly="$store.currency.mode === 'BDT'">
                </div>
            </div>
            <div class="flex gap-3 mt-6">
                <button type="button" onclick="closeAgentCostModal()" class="flex-1 px-6 py-2 border border-slate-300 text-slate-700 rounded-lg hover:bg-slate-50 transition font-medium">Cancel</button>
                <button type="submit" class="flex-1 px-6 py-2 bg-slate-700 text-white rounded-lg hover:bg-slate-800 transition font-medium">Save</button>
            </div>
        </form>
    </div>
</div>

<!-- Visa Agent Modal -->
<div id="visaAgentModal" class="hidden fixed inset-0 z-50 flex items-center justify-center">
    <div class="absolute inset-0 bg-black/50" onclick="closeVisaAgentModal()"></div>
    <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-sm mx-4 p-6">
        <h3 class="text-xl font-semibold text-slate-800 mb-4" id="visaAgentModalTitle">Add Visa Agent</h3>
        <form id="visaAgentForm" method="POST" action="{{ route('visa-agents.store') }}">
            @csrf
            <input type="hidden" id="visaAgentFormMethod" name="_method" value="POST">
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Name</label>
                    <input type="text" id="visaAgentName" name="name" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Address</label>
                    <input type="text" id="visaAgentAddress" name="address" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Contacts</label>
                    <input type="text" id="visaAgentContacts" name="contacts" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none">
                </div>
            </div>
            <div class="flex gap-3 mt-6">
                <button type="button" onclick="closeVisaAgentModal()" class="flex-1 px-6 py-2 border border-slate-300 text-slate-700 rounded-lg hover:bg-slate-50 transition font-medium">Cancel</button>
                <button type="submit" class="flex-1 px-6 py-2 bg-slate-700 text-white rounded-lg hover:bg-slate-800 transition font-medium">Save</button>
            </div>
        </form>
    </div>
</div>

<!-- Commission Agent Modal -->
<div id="commissionAgentModal" class="hidden fixed inset-0 z-50 flex items-center justify-center">
    <div class="absolute inset-0 bg-black/50" onclick="closeCommissionAgentModal()"></div>
    <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-sm mx-4 p-6">
        <h3 class="text-xl font-semibold text-slate-800 mb-4" id="commissionAgentModalTitle">Add Commission Agent</h3>
        <form id="commissionAgentForm" method="POST" action="{{ route('commission-agents.store') }}">
            @csrf
            <input type="hidden" id="commissionAgentFormMethod" name="_method" value="POST">
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Visa Agent</label>
                    <select id="commissionAgentVisaAgent" name="visa_agent_id" required class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none bg-white">
                        <option value="">Select Visa Agent</option>
                        @foreach($allVisaAgents as $agent)
                            <option value="{{ $agent->id }}">{{ $agent->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Name</label>
                    <input type="text" id="commissionAgentName" name="name" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Address</label>
                    <input type="text" id="commissionAgentAddress" name="address" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Contacts</label>
                    <input type="text" id="commissionAgentContacts" name="contacts" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none">
                </div>
            </div>
            <div class="flex gap-3 mt-6">
                <button type="button" onclick="closeCommissionAgentModal()" class="flex-1 px-6 py-2 border border-slate-300 text-slate-700 rounded-lg hover:bg-slate-50 transition font-medium">Cancel</button>
                <button type="submit" class="flex-1 px-6 py-2 bg-slate-700 text-white rounded-lg hover:bg-slate-800 transition font-medium">Save</button>
            </div>
        </form>
    </div>
</div>

<script>
// Visa Price Modal Functions
function openVisaPriceModal() {
    document.getElementById('visaPriceModal').classList.remove('hidden');
    document.getElementById('visaPriceFormMethod').value = 'POST';
    document.getElementById('visaPriceForm').action = '{{ route("visa-selling-prices.store") }}';
    document.getElementById('visaPriceModalTitle').textContent = 'Add Visa Price';
    document.getElementById('visaPriceInput').value = '';
    document.getElementById('visaPriceDate').value = new Date().toISOString().split('T')[0];
    const visaPriceFormEl = document.getElementById('visaPriceForm');
    if (visaPriceFormEl._x_dataStack) {
        Alpine.$data(visaPriceFormEl).bdtValue = 0;
    }
}

function closeVisaPriceModal() {
    document.getElementById('visaPriceModal').classList.add('hidden');
}

function editVisaPrice(id, price) {
    document.getElementById('visaPriceModal').classList.remove('hidden');
    document.getElementById('visaPriceFormMethod').value = 'PUT';
    document.getElementById('visaPriceForm').action = '/visa-selling-prices/' + id;
    document.getElementById('visaPriceModalTitle').textContent = 'Edit Visa Price';
    document.getElementById('visaPriceInput').value = price;
    const visaPriceFormEl = document.getElementById('visaPriceForm');
    if (visaPriceFormEl._x_dataStack && Alpine.store('currency').mode === 'BDT') {
        const rate = window.__currencyRate || 1;
        Alpine.$data(visaPriceFormEl).bdtValue = price * (rate || 1);
    }
}

// Agent Cost Modal Functions
function openAgentCostModal() {
    document.getElementById('agentCostModal').classList.remove('hidden');
    document.getElementById('agentCostFormMethod').value = 'POST';
    document.getElementById('agentCostForm').action = '{{ route("visa-agent-costs.store") }}';
    document.getElementById('agentCostModalTitle').textContent = 'Add Agent Price';
    document.getElementById('visaAgentSelect').value = '';
    document.getElementById('agentCostInput').value = '';
    const agentCostFormEl = document.getElementById('agentCostForm');
    if (agentCostFormEl._x_dataStack) {
        Alpine.$data(agentCostFormEl).bdtValue = 0;
    }
}

function closeAgentCostModal() {
    document.getElementById('agentCostModal').classList.add('hidden');
}

function editAgentCost(id, agentId, cost) {
    document.getElementById('agentCostModal').classList.remove('hidden');
    document.getElementById('agentCostFormMethod').value = 'PUT';
    document.getElementById('agentCostForm').action = '/visa-agent-costs/' + id;
    document.getElementById('agentCostModalTitle').textContent = 'Edit Agent Price';
    document.getElementById('visaAgentSelect').value = agentId;
    document.getElementById('agentCostInput').value = cost;
    const agentCostFormEl = document.getElementById('agentCostForm');
    if (agentCostFormEl._x_dataStack && Alpine.store('currency').mode === 'BDT') {
        const rate = window.__currencyRate || 1;
        Alpine.$data(agentCostFormEl).bdtValue = cost * (rate || 1);
    }
}

// Visa Agent Modal Functions
function openVisaAgentModal() {
    document.getElementById('visaAgentModal').classList.remove('hidden');
    document.getElementById('visaAgentFormMethod').value = 'POST';
    document.getElementById('visaAgentForm').action = '{{ route("visa-agents.store") }}';
    document.getElementById('visaAgentModalTitle').textContent = 'Add Visa Agent';
    document.getElementById('visaAgentName').value = '';
    document.getElementById('visaAgentAddress').value = '';
    document.getElementById('visaAgentContacts').value = '';
}

function closeVisaAgentModal() {
    document.getElementById('visaAgentModal').classList.add('hidden');
}

function editVisaAgent(id, name, address, contacts) {
    document.getElementById('visaAgentModal').classList.remove('hidden');
    document.getElementById('visaAgentFormMethod').value = 'PUT';
    document.getElementById('visaAgentForm').action = '/visa-agents/' + id;
    document.getElementById('visaAgentModalTitle').textContent = 'Edit Visa Agent';
    document.getElementById('visaAgentName').value = name;
    document.getElementById('visaAgentAddress').value = address;
    document.getElementById('visaAgentContacts').value = contacts;
}

// Commission Agent Modal Functions
function openCommissionAgentModal() {
    document.getElementById('commissionAgentModal').classList.remove('hidden');
    document.getElementById('commissionAgentFormMethod').value = 'POST';
    document.getElementById('commissionAgentForm').action = '{{ route("commission-agents.store") }}';
    document.getElementById('commissionAgentModalTitle').textContent = 'Add Commission Agent';
    document.getElementById('commissionAgentVisaAgent').value = '';
    document.getElementById('commissionAgentName').value = '';
    document.getElementById('commissionAgentAddress').value = '';
    document.getElementById('commissionAgentContacts').value = '';
}

function closeCommissionAgentModal() {
    document.getElementById('commissionAgentModal').classList.add('hidden');
}

function editCommissionAgent(id, visaAgentId, name, address, contacts) {
    document.getElementById('commissionAgentModal').classList.remove('hidden');
    document.getElementById('commissionAgentFormMethod').value = 'PUT';
    document.getElementById('commissionAgentForm').action = '/commission-agents/' + id;
    document.getElementById('commissionAgentModalTitle').textContent = 'Edit Commission Agent';
    document.getElementById('commissionAgentVisaAgent').value = visaAgentId;
    document.getElementById('commissionAgentName').value = name;
    document.getElementById('commissionAgentAddress').value = address;
    document.getElementById('commissionAgentContacts').value = contacts;
}
</script>
@endsection