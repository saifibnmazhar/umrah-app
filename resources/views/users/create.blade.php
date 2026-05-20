@extends('layouts.app')
@section('title', 'Add User')
@section('content')
<style>[x-cloak]{display:none!important}</style>
<div class="max-w-2xl mx-auto">
    <div class="mb-6">
        <a href="{{ route('users.index') }}" class="text-slate-600 hover:text-slate-800 text-sm">
            &larr; Back to Users
        </a>
    </div>

    <h1 class="text-2xl font-bold text-slate-800 mb-6">Add User</h1>

    @php
    $roleTypeMap = $roles->mapWithKeys(fn($role) => [
        $role->id => str_contains(strtolower($role->name), 'fingerprint') ? 'fingerprint' : (str_contains(strtolower($role->name), 'branch') ? 'branch' : 'other')
    ]);
    @endphp

<script>window.roleTypeMap = @json($roleTypeMap);</script>

    <form method="POST" action="{{ route('users.store') }}" class="bg-white rounded-lg shadow-sm border border-slate-200 p-6 space-y-5"
          x-data="{
              selectedRole: '{{ old('role_id') }}',
              roleTypes: {},
              get roleType() {
                  return this.roleTypes[this.selectedRole] || '';
              },
              init() {
                  if (window.roleTypeMap) {
                      Object.keys(window.roleTypeMap).forEach(key => {
                          this.roleTypes[key] = window.roleTypeMap[key];
                      });
                  }
                  this.$watch('selectedRole', () => {
                      const type = this.roleType;
                      if (type !== 'branch' && this.$refs.branchSelect) this.$refs.branchSelect.value = '';
                      if (type !== 'fingerprint' && this.$refs.officeSelect) this.$refs.officeSelect.value = '';
                  });
              }
          }">
        @csrf

        <div>
            <label for="name" class="block text-sm font-medium text-slate-700 mb-1">Name</label>
            <input
                type="text"
                name="name"
                id="name"
                value="{{ old('name') }}"
                placeholder="Enter user name"
                class="block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 sm:text-sm px-3 py-2 border @error('name') border-red-500 @enderror"
            >
            @error('name')
                <span class="text-sm text-red-600 mt-1">{{ $message }}</span>
            @enderror
        </div>

        <div>
            <label for="email" class="block text-sm font-medium text-slate-700 mb-1">Email</label>
            <input
                type="email"
                name="email"
                id="email"
                value="{{ old('email') }}"
                placeholder="Enter email address"
                class="block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 sm:text-sm px-3 py-2 border @error('email') border-red-500 @enderror"
            >
            @error('email')
                <span class="text-sm text-red-600 mt-1">{{ $message }}</span>
            @enderror
        </div>

        <div>
            <label for="password" class="block text-sm font-medium text-slate-700 mb-1">Password</label>
            <input
                type="password"
                name="password"
                id="password"
                placeholder="Enter password"
                class="block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 sm:text-sm px-3 py-2 border @error('password') border-red-500 @enderror"
            >
            @error('password')
                <span class="text-sm text-red-600 mt-1">{{ $message }}</span>
            @enderror
        </div>

        <div>
            <label for="password_confirmation" class="block text-sm font-medium text-slate-700 mb-1">Confirm Password</label>
            <input
                type="password"
                name="password_confirmation"
                id="password_confirmation"
                placeholder="Confirm password"
                class="block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 sm:text-sm px-3 py-2 border"
            >
        </div>

        <div>
            <label for="role_id" class="block text-sm font-medium text-slate-700 mb-1">Roles</label>
            <select
                name="role_id"
                id="role_id"
                x-model="selectedRole"
                class="block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 sm:text-sm px-3 py-2 border @error('role_id') border-red-500 @enderror"
            >
                <option value="">-- Select Role --</option>
                @foreach($roles as $role)
                    <option value="{{ $role->id }}" {{ old('role_id') == $role->id ? 'selected' : '' }}>
                        {{ $role->name }}
                    </option>
                @endforeach
            </select>
            @error('role_id')
                <span class="text-sm text-red-600 mt-1">{{ $message }}</span>
            @enderror
        </div>

        <div x-show="roleType === 'branch'" x-cloak>
            <label for="branch_id" class="block text-sm font-medium text-slate-700 mb-1">Branch</label>
            <select
                name="branch_id"
                id="branch_id"
                x-ref="branchSelect"
                class="block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 sm:text-sm px-3 py-2 border @error('branch_id') border-red-500 @enderror"
            >
                <option value="">-- Select Branch --</option>
                @foreach($branches as $branch)
                    <option value="{{ $branch->id }}" {{ old('branch_id') == $branch->id ? 'selected' : '' }}>
                        {{ $branch->name }}
                    </option>
                @endforeach
            </select>
            @error('branch_id')
                <span class="text-sm text-red-600 mt-1">{{ $message }}</span>
            @enderror
        </div>

        <div x-show="roleType === 'fingerprint'" x-cloak>
            <label for="office_id" class="block text-sm font-medium text-slate-700 mb-1">Office</label>
            <select
                name="office_id"
                id="office_id"
                x-ref="officeSelect"
                class="block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 sm:text-sm px-3 py-2 border @error('office_id') border-red-500 @enderror"
            >
                <option value="">-- Select Office --</option>
                @foreach($offices as $office)
                    <option value="{{ $office->id }}" {{ old('office_id') == $office->id ? 'selected' : '' }}>
                        {{ $office->name }}
                    </option>
                @endforeach
            </select>
            @error('office_id')
                <span class="text-sm text-red-600 mt-1">{{ $message }}</span>
            @enderror
        </div>

        <div class="pt-4 flex items-center gap-4">
            <button type="submit" class="px-4 py-2 bg-slate-800 text-white rounded-md hover:bg-slate-700 transition">
                Create User
            </button>
            <a href="{{ route('users.index') }}" class="text-slate-600 hover:text-slate-800 text-sm">
                Cancel
            </a>
        </div>
    </form>
</div>
@endsection
