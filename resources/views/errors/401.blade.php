@extends('layouts.app')

@section('title', '401 Unauthorized')

@section('content')
<div class="min-h-screen flex items-center justify-center">
    <div class="text-center">
        <h1 class="text-6xl font-bold text-slate-300 mb-4">401</h1>
        <h2 class="text-2xl font-semibold text-slate-700 mb-2">Unauthorized</h2>
        <p class="text-slate-500 mb-6">You are not authenticated. Please log in to continue.</p>
        <a href="{{ route('login') }}" class="inline-block px-6 py-3 bg-slate-800 text-white rounded-lg hover:bg-slate-700 transition font-medium">
            Go to Login
        </a>
    </div>
</div>
@endsection
