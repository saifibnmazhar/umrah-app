@extends('layouts.app')
@section('title', 'Vouchers')
@section('content')
<div class="max-w-5xl mx-auto">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-slate-800">Vouchers</h1>
        <a href="{{ route('vouchers.create') }}" class="px-4 py-2 bg-slate-700 text-white rounded-lg hover:bg-slate-800 transition font-medium flex items-center gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
            </svg>
            Add Voucher
        </a>
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
            <table class="w-full min-w-[900px] text-sm">
                <thead class="bg-slate-50 text-slate-600">
                    <tr>
                        <th class="px-3 py-2 text-left font-medium">Voucher ID</th>
                        <th class="px-3 py-2 text-left font-medium">Booking</th>
                        <th class="px-3 py-2 text-left font-medium">Type</th>
                        <th class="px-3 py-2 text-left font-medium">Date</th>
                        <th class="px-3 py-2 text-left font-medium">Method</th>
                        <th class="px-3 py-2 text-right font-medium">Amount (SAR)</th>
                        <th class="px-3 py-2 text-right font-medium">BDT Amount</th>
                        <th class="px-3 py-2 text-left font-medium">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    @forelse($vouchers as $voucher)
                        <tr class="hover:bg-slate-50">
                            <td class="px-3 py-2 text-slate-800 font-medium">{{ $voucher->voucher_id }}</td>
                            <td class="px-3 py-2 text-slate-600">#{{ $voucher->booking->id ?? 'N/A' }}</td>
                            <td class="px-3 py-2">
                                @if($voucher->transactionType)
                                    @if($voucher->transactionType->type === 'debit')
                                        <span class="px-2 py-1 text-xs font-medium rounded-full bg-amber-100 text-amber-700">{{ $voucher->transactionType->name }}</span>
                                    @else
                                        <span class="px-2 py-1 text-xs font-medium rounded-full bg-teal-100 text-teal-700">{{ $voucher->transactionType->name }}</span>
                                    @endif
                                @else
                                    N/A
                                @endif
                            </td>
                            <td class="px-3 py-2 text-slate-600">{{ $voucher->payment_date }}</td>
                            <td class="px-3 py-2">
                                @if($voucher->payment_method === 'cash')
                                    <span class="px-2 py-1 text-xs font-medium rounded-full bg-amber-100 text-amber-700">Cash</span>
                                @else
                                    <span class="px-2 py-1 text-xs font-medium rounded-full bg-blue-100 text-blue-700">Bank</span>
                                @endif
                            </td>
                            <td class="px-3 py-2 text-right text-slate-800 font-medium">@currency($voucher->amount, 2)</td>
                            <td class="px-3 py-2 text-right text-slate-800 font-medium">{{ number_format($voucher->bdt_amount, 2) }}</td>
                            <td class="px-3 py-2">
                                <div class="flex gap-2">
                                    <a href="{{ route('vouchers.show', $voucher->id) }}" class="text-xs bg-blue-100 hover:bg-blue-200 text-blue-600 px-2 py-1 rounded">View</a>
                                    <a href="{{ route('vouchers.edit', $voucher->id) }}" class="text-xs bg-slate-100 hover:bg-slate-200 text-slate-600 px-2 py-1 rounded">Edit</a>
                                    <form method="POST" action="{{ route('vouchers.destroy', $voucher->id) }}" onsubmit="return confirm('Are you sure you want to delete this voucher?')" class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-xs bg-red-100 hover:bg-red-200 text-red-600 px-2 py-1 rounded">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-3 py-8 text-center text-slate-500">
                                No vouchers yet. Click "Add Voucher" to create a new one.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4 flex justify-center">
            {{ $vouchers->links() }}
        </div>
    </div>
</div>
@endsection