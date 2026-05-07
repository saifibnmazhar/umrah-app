@extends('layouts.app')

@section('title', 'Fingerprint Admin')

@section('content')
<div class="max-w-3xl mx-auto pt-6" x-data="{ activeTab: 'fingerprint' }">
    <h1 class="text-2xl font-bold text-slate-800 mb-6">Fingerprint Admin</h1>

    <div class="bg-white rounded-lg shadow-md mb-6">
        <div class="flex border-b border-slate-200">
            <button @click="activeTab = 'fingerprint'" 
                class="px-6 py-3 text-sm font-medium transition border-b-2"
                :class="activeTab === 'fingerprint' ? 'border-emerald-600 text-emerald-600' : 'border-transparent text-slate-500 hover:text-slate-700'">
                Fingerprint Status Management
            </button>
            <button @click="activeTab = 'office'" 
                class="px-6 py-3 text-sm font-medium transition border-b-2"
                :class="activeTab === 'office' ? 'border-emerald-600 text-emerald-600' : 'border-transparent text-slate-500 hover:text-slate-700'">
                Office Management
            </button>
        </div>

        <div class="p-6">
            <div x-show="activeTab === 'fingerprint'">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead class="bg-slate-100 text-slate-700 uppercase text-xs">
                            <tr>
                                <th class="px-4 py-3 rounded-tl-md">Passport</th>
                                <th class="px-4 py-3">Name</th>
                                <th class="px-4 py-3">Mobile</th>
                                <th class="px-4 py-3">Office</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3">Date</th>
                                <th class="px-4 py-3 rounded-tr-md">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200">
                            <tr class="hover:bg-slate-50">
                                <td class="px-4 py-3">A12345678</td>
                                <td class="px-4 py-3">Ahmed Ali</td>
                                <td class="px-4 py-3">+966501234567</td>
                                <td class="px-4 py-3">Riyadh Office</td>
                                <td class="px-4 py-3">
                                    <span class="px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">Completed</span>
                                </td>
                                <td class="px-4 py-3">2026-04-15</td>
                                <td class="px-4 py-3">
                                    <button class="text-blue-600 hover:text-blue-800 mr-3">Edit</button>
                                    <button class="text-red-600 hover:text-red-800">Delete</button>
                                </td>
                            </tr>
                            <tr class="hover:bg-slate-50">
                                <td class="px-4 py-3">B98765432</td>
                                <td class="px-4 py-3">Mohammed Hassan</td>
                                <td class="px-4 py-3">+966509876543</td>
                                <td class="px-4 py-3">Jeddah Office</td>
                                <td class="px-4 py-3">
                                    <span class="px-2 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">Pending</span>
                                </td>
                                <td class="px-4 py-3">2026-04-20</td>
                                <td class="px-4 py-3">
                                    <button class="text-blue-600 hover:text-blue-800 mr-3">Edit</button>
                                    <button class="text-red-600 hover:text-red-800">Delete</button>
                                </td>
                            </tr>
                            <tr class="hover:bg-slate-50">
                                <td class="px-4 py-3">C55667788</td>
                                <td class="px-4 py-3">Ibrahim Omar</td>
                                <td class="px-4 py-3">+966551122334</td>
                                <td class="px-4 py-3">Makkah Office</td>
                                <td class="px-4 py-3">
                                    <span class="px-2 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800">Failed</span>
                                </td>
                                <td class="px-4 py-3">2026-04-22</td>
                                <td class="px-4 py-3">
                                    <button class="text-blue-600 hover:text-blue-800 mr-3">Edit</button>
                                    <button class="text-red-600 hover:text-red-800">Delete</button>
                                </td>
                            </tr>
                            <tr class="hover:bg-slate-50">
                                <td class="px-4 py-3">D11223344</td>
                                <td class="px-4 py-3">Youssef Ahmed</td>
                                <td class="px-4 py-3">+966552233445</td>
                                <td class="px-4 py-3">Riyadh Office</td>
                                <td class="px-4 py-3">
                                    <span class="px-2 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">Pending</span>
                                </td>
                                <td class="px-4 py-3">2026-04-25</td>
                                <td class="px-4 py-3">
                                    <button class="text-blue-600 hover:text-blue-800 mr-3">Edit</button>
                                    <button class="text-red-600 hover:text-red-800">Delete</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div x-show="activeTab === 'office'" style="display: none;">
                <div class="text-center py-8 text-slate-500">
                    Office management coming soon.
                </div>
            </div>
        </div>
    </div>
</div>
@endsection