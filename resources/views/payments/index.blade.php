@extends('layouts.app')
@section('title', 'Payments')
@section('content')
<div class="max-w-7xl mx-auto">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-slate-800">Payments</h1>
        @if(auth()->user()->hasRole('Super Admin') || auth()->user()->hasRole('Co Admin'))
        <a href="{{ route('payments.create') }}" class="px-4 py-2 bg-slate-700 text-white rounded-lg hover:bg-slate-800 transition font-medium flex items-center gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
            </svg>
            Add Payment
        </a>
        @endif
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

    <livewire:payment.payment-list-table />
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    function updateLocalTimes() {
        document.querySelectorAll('.local-time').forEach(function(el) {
            var d = new Date(el.getAttribute('data-utc'));
            if (!isNaN(d)) {
                el.textContent = d.toLocaleTimeString('en-US', {
                    hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
                });
            }
        });
    }
    updateLocalTimes();
    document.addEventListener('livewire:update', updateLocalTimes);
});
</script>
@endpush