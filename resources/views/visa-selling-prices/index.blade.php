@extends('layouts.app')
@section('title', 'Visa Selling Price')
@section('content')
<div class="max-w-4xl mx-auto">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-slate-800">Visa Selling Price</h1>
        <button onclick="openModal()" class="px-4 py-2 bg-slate-700 text-white rounded-lg hover:bg-slate-800 transition font-medium flex items-center gap-2">
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
                                        <button onclick="editPrice({{ $price->id }}, {{ $price->selling_price }})" class="text-xs bg-slate-100 hover:bg-slate-200 text-slate-600 px-2 py-1 rounded">Edit</button>
                                        <form method="POST" action="{{ route('visa-selling-prices.destroy', $price->id) }}" onsubmit="return confirm('Are you sure you want to delete this visa price record?')" class="inline">
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
                                No visa price records yet. Click "Add Visa Price" to create a new price record.
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

<!-- Modal -->
<div id="visaPriceModal" class="hidden fixed inset-0 z-50 flex items-center justify-center">
    <div class="absolute inset-0 bg-black/50" onclick="closeModal()"></div>
    <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-sm mx-4 p-6">
        <h3 class="text-xl font-semibold text-slate-800 mb-4" id="modalTitle">Add Visa Price</h3>
        <form id="priceForm" method="POST" action="{{ route('visa-selling-prices.store') }}">
            @csrf
            <input type="hidden" id="formMethod" name="_method" value="POST">
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Date</label>
                    <input type="text" id="priceDate" class="w-full px-4 py-2 border border-slate-200 rounded-lg bg-slate-50 text-slate-600" value="{{ now()->format('Y-m-d') }}" readonly>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Price (SAR)</label>
                    <input type="number" id="sellingPriceInput" name="selling_price" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none @error('selling_price') border-red-500 @enderror" value="{{ old('selling_price') }}" min="0" step="0.01" required>
                    @error('selling_price')
                        <span class="text-red-500 text-sm mt-1 block">{{ $message }}</span>
                    @enderror
                </div>
            </div>
            <div class="flex gap-3 mt-6">
                <button type="submit" class="flex-1 px-6 py-2 bg-slate-700 text-white rounded-lg hover:bg-slate-800 transition font-medium">Save</button>
                <button type="button" onclick="closeModal()" class="flex-1 px-6 py-2 border border-slate-300 text-slate-700 rounded-lg hover:bg-slate-50 transition font-medium">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal() {
    document.getElementById('visaPriceModal').classList.remove('hidden');
    document.getElementById('formMethod').value = 'POST';
    document.getElementById('priceForm').action = '{{ route("visa-selling-prices.store") }}';
    document.getElementById('modalTitle').textContent = 'Add Visa Price';
    document.getElementById('sellingPriceInput').value = '{{ old('selling_price') }}';
    document.getElementById('priceDate').value = new Date().toISOString().split('T')[0];
}

function closeModal() {
    document.getElementById('visaPriceModal').classList.add('hidden');
}

function editPrice(id, price) {
    document.getElementById('visaPriceModal').classList.remove('hidden');
    document.getElementById('formMethod').value = 'PUT';
    document.getElementById('priceForm').action = '/visa-selling-prices/' + id;
    document.getElementById('modalTitle').textContent = 'Edit Visa Price';
    document.getElementById('sellingPriceInput').value = price;
    document.getElementById('priceDate').value = new Date().toISOString().split('T')[0];
}

// Auto-open modal if there are validation errors
@if($errors->any() || session('error'))
    document.addEventListener('DOMContentLoaded', function() {
        openModal();
    });
@endif
</script>
@endsection