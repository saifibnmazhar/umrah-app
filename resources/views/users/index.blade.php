@extends('layouts.app')
@section('title', 'Users')
@section('content')
<div class="max-w-7xl mx-auto">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-slate-800">Users</h1>
        @if(auth()->user()->hasRole('Super Admin'))
            <a href="{{ route('users.create') }}" class="px-4 py-2 bg-slate-800 text-white rounded-md hover:bg-slate-700 transition">
                Add New
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

<div id="user-table-container">
    @if(auth()->user()->hasRole('Super Admin'))
        <livewire:user.user-list-table :is-super-admin="true" />
    @else
        <livewire:user.user-list-table :is-super-admin="false" />
    @endif
</div>
@endsection
