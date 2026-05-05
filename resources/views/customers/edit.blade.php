@extends('layouts.app')
@section('title', 'Edit Customer')
@section('content')
<div class="max-w-2xl mx-auto">
    <div class="mb-6">
        <a href="{{ route('customers.index') }}" class="text-slate-600 hover:text-slate-800 text-sm">
            ← Back to Customers
        </a>
    </div>

    <h1 class="text-2xl font-bold text-slate-800 mb-6">Edit Customer</h1>

    <form method="POST" action="{{ route('customers.update', $customer->id) }}" class="bg-white rounded-lg shadow-sm border border-slate-200 p-6 space-y-5">
        @csrf
        @method('PUT')

        <div>
            <label for="name" class="block text-sm font-medium text-slate-700 mb-1">Name</label>
            <input type="text" name="name" id="name" value="{{ old('name') ?? $customer->name }}" class="w-full px-3 py-2 border border-slate-300 rounded-md focus:outline-none focus:ring-2 focus:ring-slate-500 focus:border-transparent @error('name') border-red-500 @enderror">
            @error('name')
                <span class="text-sm text-red-600 mt-1 block">{{ $message }}</span>
            @enderror
        </div>

        <div>
            <label for="iqama_type" class="block text-sm font-medium text-slate-700 mb-1">Iqama Type</label>
            <select name="iqama_type" id="iqama_type" class="w-full px-3 py-2 border border-slate-300 rounded-md focus:outline-none focus:ring-2 focus:ring-slate-500 focus:border-transparent @error('iqama_type') border-red-500 @enderror">
                <option value="">Select Type</option>
                <option value="self" {{ (old('iqama_type') ?? $customer->iqama_type) == 'self' ? 'selected' : '' }}>Self</option>
                <option value="referral" {{ (old('iqama_type') ?? $customer->iqama_type) == 'referral' ? 'selected' : '' }}>Referral</option>
            </select>
            @error('iqama_type')
                <span class="text-sm text-red-600 mt-1 block">{{ $message }}</span>
            @enderror
        </div>

        <div>
            <label for="passport_no" class="block text-sm font-medium text-slate-700 mb-1">Passport No</label>
            <input type="text" name="passport_no" id="passport_no" value="{{ old('passport_no') ?? $customer->passport_no }}" class="w-full px-3 py-2 border border-slate-300 rounded-md focus:outline-none focus:ring-2 focus:ring-slate-500 focus:border-transparent @error('passport_no') border-red-500 @enderror">
            @error('passport_no')
                <span class="text-sm text-red-600 mt-1 block">{{ $message }}</span>
            @enderror
        </div>

        <div>
            <label for="iqama_no" class="block text-sm font-medium text-slate-700 mb-1">Iqama No</label>
            <input type="text" name="iqama_no" id="iqama_no" value="{{ old('iqama_no') ?? $customer->iqama_no }}" class="w-full px-3 py-2 border border-slate-300 rounded-md focus:outline-none focus:ring-2 focus:ring-slate-500 focus:border-transparent @error('iqama_no') border-red-500 @enderror">
            @error('iqama_no')
                <span class="text-sm text-red-600 mt-1 block">{{ $message }}</span>
            @enderror
        </div>

        <div>
            <label for="mobile_no" class="block text-sm font-medium text-slate-700 mb-1">Mobile No</label>
            <input type="text" name="mobile_no" id="mobile_no" value="{{ old('mobile_no') ?? $customer->mobile_no }}" class="w-full px-3 py-2 border border-slate-300 rounded-md focus:outline-none focus:ring-2 focus:ring-slate-500 focus:border-transparent @error('mobile_no') border-red-500 @enderror">
            @error('mobile_no')
                <span class="text-sm text-red-600 mt-1 block">{{ $message }}</span>
            @enderror
        </div>

        <div>
            <label for="ref_iqama_no" class="block text-sm font-medium text-slate-700 mb-1">Referral Iqama No <span class="text-slate-400 text-xs">(optional)</span></label>
            <input type="text" name="ref_iqama_no" id="ref_iqama_no" value="{{ old('ref_iqama_no') ?? $customer->ref_iqama_no }}" class="w-full px-3 py-2 border border-slate-300 rounded-md focus:outline-none focus:ring-2 focus:ring-slate-500 focus:border-transparent @error('ref_iqama_no') border-red-500 @enderror">
            @error('ref_iqama_no')
                <span class="text-sm text-red-600 mt-1 block">{{ $message }}</span>
            @enderror
        </div>

        <div>
            <label for="ref_mobile_no" class="block text-sm font-medium text-slate-700 mb-1">Referral Mobile No <span class="text-slate-400 text-xs">(optional)</span></label>
            <input type="text" name="ref_mobile_no" id="ref_mobile_no" value="{{ old('ref_mobile_no') ?? $customer->ref_mobile_no }}" class="w-full px-3 py-2 border border-slate-300 rounded-md focus:outline-none focus:ring-2 focus:ring-slate-500 focus:border-transparent @error('ref_mobile_no') border-red-500 @enderror">
            @error('ref_mobile_no')
                <span class="text-sm text-red-600 mt-1 block">{{ $message }}</span>
            @enderror
        </div>

        <div>
            <label for="ref_iqama_doc" class="block text-sm font-medium text-slate-700 mb-1">Referral Iqama Doc <span class="text-slate-400 text-xs">(optional)</span></label>
            <input type="text" name="ref_iqama_doc" id="ref_iqama_doc" value="{{ old('ref_iqama_doc') ?? $customer->ref_iqama_doc }}" class="w-full px-3 py-2 border border-slate-300 rounded-md focus:outline-none focus:ring-2 focus:ring-slate-500 focus:border-transparent @error('ref_iqama_doc') border-red-500 @enderror">
            @error('ref_iqama_doc')
                <span class="text-sm text-red-600 mt-1 block">{{ $message }}</span>
            @enderror
        </div>

        <div>
            <label for="address" class="block text-sm font-medium text-slate-700 mb-1">Address</label>
            <textarea name="address" id="address" rows="3" class="w-full px-3 py-2 border border-slate-300 rounded-md focus:outline-none focus:ring-2 focus:ring-slate-500 focus:border-transparent @error('address') border-red-500 @enderror">{{ old('address') ?? $customer->address }}</textarea>
            @error('address')
                <span class="text-sm text-red-600 mt-1 block">{{ $message }}</span>
            @enderror
        </div>

        <div class="pt-4 flex items-center gap-4">
            <button type="submit" class="px-4 py-2 bg-slate-800 text-white rounded-md hover:bg-slate-700 transition">
                Update Customer
            </button>
            <a href="{{ route('customers.index') }}" class="text-slate-600 hover:text-slate-800 text-sm">Cancel</a>
        </div>
    </form>
</div>
@endsection