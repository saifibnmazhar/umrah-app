@extends('layouts.app')

@section('title', 'Fingerprint Staff')

@section('content')
<div class="max-w-3xl mx-auto pt-6" x-data="{
    search: '',
    passports: ['A12345678', 'B98765432', 'C55667788', 'D11223344', 'E99887766'],
    names: ['Ahmed Ali', 'Mohammed Hassan', 'Ibrahim Omar', 'Youssef Ahmed', 'Ali Abdullah'],
    mobiles: ['+966501234567', '+966509876543', '+966551122334', '+966552233445', '+966553344556'],
    offices: ['Riyadh Office', 'Jeddah Office', 'Makkah Office', 'Riyadh Office', 'Jeddah Office'],
    statuses: ['Completed', 'Pending', 'Failed', 'Pending', 'Completed'],
    dates: ['2026-05-03', '2026-05-03', '2026-05-03', '2026-05-03', '2026-05-03'],
    get filteredData() {
        if (this.search === '') {
            return this.passports.map((_, i) => ({
                passport: this.passports[i],
                name: this.names[i],
                mobile: this.mobiles[i],
                office: this.offices[i],
                status: this.statuses[i],
                date: this.dates[i]
            }));
        }
        const s = this.search.toLowerCase();
        return this.passports.map((_, i) => ({
            passport: this.passports[i],
            name: this.names[i],
            mobile: this.mobiles[i],
            office: this.offices[i],
            status: this.statuses[i],
            date: this.dates[i]
        })).filter(item => 
            item.passport.toLowerCase().includes(s) ||
            item.name.toLowerCase().includes(s) ||
            item.mobile.toLowerCase().includes(s) ||
            item.office.toLowerCase().includes(s)
        );
    }
}">
    <h1 class="text-2xl font-bold text-slate-800 mb-6">Fingerprint Staff</h1>

    <div class="bg-white rounded-lg shadow-md mb-6">
        <div class="p-6">
            <form class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Passport</label>
                    <input type="text" x-model="search" placeholder="Search passport" class="w-full px-3 py-2 border border-slate-300 rounded-md focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Name</label>
                    <input type="text" placeholder="Search name" class="w-full px-3 py-2 border border-slate-300 rounded-md focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Mobile</label>
                    <input type="text" placeholder="Search mobile" class="w-full px-3 py-2 border border-slate-300 rounded-md focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Office</label>
                    <input type="text" placeholder="Search office" class="w-full px-3 py-2 border border-slate-300 rounded-md focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                </div>
            </form>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow-md">
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead class="bg-slate-100 text-slate-700 uppercase text-xs">
                    <tr>
                        <th class="px-4 py-3 rounded-tl-md">Passport</th>
                        <th class="px-4 py-3">Name</th>
                        <th class="px-4 py-3">Mobile</th>
                        <th class="px-4 py-3">Office</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3 rounded-tr-md">Date</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    <template x-for="row in filteredData" :key="row.passport">
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-3" x-text="row.passport"></td>
                            <td class="px-4 py-3" x-text="row.name"></td>
                            <td class="px-4 py-3" x-text="row.mobile"></td>
                            <td class="px-4 py-3" x-text="row.office"></td>
                            <td class="px-4 py-3">
                                <span class="px-2 py-1 rounded-full text-xs font-medium"
                                    :class="{
                                        'bg-green-100 text-green-800': row.status === 'Completed',
                                        'bg-yellow-100 text-yellow-800': row.status === 'Pending',
                                        'bg-red-100 text-red-800': row.status === 'Failed'
                                    }"
                                    x-text="row.status"></span>
                            </td>
                            <td class="px-4 py-3" x-text="row.date"></td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection