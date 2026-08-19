@extends('layouts.app')
@section('title', 'Profit/Loss Report')
@section('content')

@livewire('report.profit-loss-report-table')

@endsection

@push('scripts')
<style>
.search-input {
    background: linear-gradient(to bottom, #fff 0%, #f8f9fa 100%);
    border: 1px solid #d4d4d4;
    box-shadow: inset 0 1px 2px rgba(0,0,0,0.075);
}
.filter-btn {
    background: linear-gradient(to bottom, #fff 0%, #e9ecef 100%);
    border: 1px solid #d4d4d4;
    box-shadow: 0 1px 0 rgba(255,255,255,0.5);
}
.filter-btn:hover {
    background: linear-gradient(to bottom, #f0f0f0 0%, #e2e6ea 100%);
}
.date-input {
    background: linear-gradient(to bottom, #fff 0%, #f8f9fa 100%);
    border: 1px solid #d4d4d4;
    box-shadow: inset 0 1px 2px rgba(0,0,0,0.075);
}
.table-header {
    background: linear-gradient(to bottom, #f3f3f3 0%, #e8e8e8 100%);
    border: 1px solid #d4d4d4;
}
.table-row {
    background-color: #ffffff;
    border: 1px solid #d4d4d4;
}
.table-row:nth-child(even) {
    background-color: #fafafa;
}
.table-row:hover {
    background-color: #e8f4fc !important;
}
.export-btn {
    background: linear-gradient(to bottom, #fff 0%, #e9ecef 100%);
    border: 1px solid #d4d4d4;
    box-shadow: 0 1px 0 rgba(255,255,255,0.5);
}
.export-btn:hover {
    background: linear-gradient(to bottom, #f0f0f0 0%, #dee2e6 100%);
}
input[type="date"]::-webkit-calendar-picker-indicator {
    filter: invert(0.5);
    cursor: pointer;
}
.scrollbar-thin::-webkit-scrollbar {
    height: 8px;
    width: 8px;
}
.scrollbar-thin::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 4px;
}
.scrollbar-thin::-webkit-scrollbar-thumb {
    background: #c1c1c1;
    border-radius: 4px;
}
.scrollbar-thin::-webkit-scrollbar-thumb:hover {
    background: #a1a1a1;
}
.tab-btn {
    background: linear-gradient(to bottom, #f8f9fa 0%, #e9ecef 100%);
    border: 1px solid #d4d4d4;
    border-bottom: none;
    transition: all 0.2s ease;
}
.tab-btn:hover {
    background: linear-gradient(to bottom, #fff 0%, #f8f9fa 100%);
}
.tab-btn.active {
    background: linear-gradient(to bottom, #fff 0%, #fff 100%);
    border-bottom: 2px solid white;
    color: #1e293b;
    font-weight: 600;
}
.amount-profit {
    color: #166534;
    font-weight: 600;
}
.amount-loss {
    color: #dc2626;
    font-weight: 600;
}
.animate-fade {
    animation: fadeIn 0.2s ease-in-out;
}
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-4px); }
    to { opacity: 1; transform: translateY(0); }
}
.content-fade {
    transition: opacity 0.15s ease-in-out, transform 0.15s ease-in-out;
}
.content-fade.hidden {
    display: none;
}
</style>
<script>
function profitLossCurrency() {
    return {
        init() {
            this.$store.currency.convertAll();
        }
    };
}
</script>
@endpush
