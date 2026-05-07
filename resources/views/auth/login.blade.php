@extends('layouts.app')
@section('title', 'Login')
@section('content')
<div class="min-h-screen flex items-center justify-center">
    <div class="w-full max-w-md">
        <div class="bg-white rounded-lg shadow-sm border border-slate-200 p-8">
            <div class="text-center mb-8">
                <h1 class="text-2xl font-bold text-slate-800">BM Umrah Booking</h1>
                <p class="text-slate-500 mt-2">Sign in to your account</p>
            </div>

            @if($errors->any())
                <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-6">
                    <ul class="list-disc list-inside text-sm">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('login') }}" class="space-y-5">
                @csrf

                <div>
                    <label for="email" class="block text-sm font-medium text-slate-700 mb-1">Email</label>
                    <input
                        type="email"
                        name="email"
                        id="email"
                        value="{{ old('email') }}"
                        required
                        autofocus
                        placeholder="Enter your email"
                        class="block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 sm:text-sm px-3 py-2 border @error('email') border-red-500 @enderror"
                    >
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-slate-700 mb-1">Password</label>
                    <input
                        type="password"
                        name="password"
                        id="password"
                        required
                        placeholder="Enter your password"
                        class="block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 sm:text-sm px-3 py-2 border @error('password') border-red-500 @enderror"
                    >
                </div>

                <div class="flex items-center">
                    <input
                        type="checkbox"
                        name="remember"
                        id="remember"
                        class="h-4 w-4 text-slate-800 border-slate-300 rounded focus:ring-slate-500"
                    >
                    <label for="remember" class="ml-2 text-sm text-slate-600">Remember me</label>
                </div>

                <button
                    type="submit"
                    class="w-full px-4 py-2 bg-slate-800 text-white rounded-md hover:bg-slate-700 transition font-medium"
                >
                    Sign In
                </button>
            </form>
        </div>
    </div>
</div>
@endsection
