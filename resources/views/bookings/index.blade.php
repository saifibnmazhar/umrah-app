@extends('layouts.app')
@section('title', 'Booking')
@section('content')
@php
$bookings = [
    ['invoiceNo' => 'INV-2024-001', 'bookingDate' => '2024-04-01', 'customerName' => 'Ahmed Abdullah', 'mobile' => '+966501111111', 'route' => 'DAC-JED-DAC', 'status' => 'None', 'totalAmount' => 5500, 'paidAmount' => 3000, 'dueAmount' => 2500],
    ['invoiceNo' => 'INV-2024-002', 'bookingDate' => '2024-04-02', 'customerName' => 'Fatima Ali', 'mobile' => '+966559876543', 'route' => 'DAC-RUH-DAC', 'status' => 'Visa Application', 'totalAmount' => 6200, 'paidAmount' => 6200, 'dueAmount' => 0],
    ['invoiceNo' => 'INV-2024-003', 'bookingDate' => '2024-04-03', 'customerName' => 'Mohammad Khan', 'mobile' => '+966550123456', 'route' => 'DAC-MED-DAC', 'status' => 'Issued', 'totalAmount' => 4800, 'paidAmount' => 4800, 'dueAmount' => 0],
];
@endphp
<div class="max-w-3xl mx-auto" x-data="{ activeTab: 'bookingIndex' }">
    <h1 class="text-2xl font-bold text-slate-800 mb-6">Booking</h1>

    <div class="flex space-x-4 border-b border-slate-300 mb-6">
        <button 
            @click="activeTab = 'bookingIndex'"
            :class="{ 'border-b-2 border-slate-800 text-slate-800': activeTab === 'bookingIndex', 'text-slate-500 hover:text-slate-700': activeTab !== 'bookingIndex' }"
            class="pb-2 px-4 font-medium transition"
        >
            Booking Index
        </button>
        <button 
            @click="activeTab = 'addBooking'"
            :class="{ 'border-b-2 border-slate-800 text-slate-800': activeTab === 'addBooking', 'text-slate-500 hover:text-slate-700': activeTab !== 'addBooking' }"
            class="pb-2 px-4 font-medium transition"
        >
            Add Booking
        </button>
    </div>

    <div x-show="activeTab === 'bookingIndex'" x-cloak>
        <div class="overflow-x-auto">
            <table class="min-w-full bg-white border border-slate-200 rounded-lg shadow-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-3 py-2 text-left text-xs font-medium text-slate-600 uppercase">Booking Date</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-slate-600 uppercase">Invoice</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-slate-600 uppercase">Pax Qty</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-slate-600 uppercase">Guardian</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-slate-600 uppercase">Mobile</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-slate-600 uppercase">Passenger</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-slate-600 uppercase">Passport</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-slate-600 uppercase">Route</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-slate-600 uppercase">Current Status</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-slate-600 uppercase">Ticket Fare</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-slate-600 uppercase">Visa</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-slate-600 uppercase">Visa Agent</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-slate-600 uppercase">Visa Status</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-slate-600 uppercase">Required Flight Date</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-slate-600 uppercase">Actual Flight Date</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-slate-600 uppercase">F. Cost</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-slate-600 uppercase">Package</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-slate-600 uppercase">Package Value</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-slate-600 uppercase">Total Cost</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-slate-600 uppercase">Markup (Profit)</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-slate-600 uppercase">Due</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-slate-600 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    <tr>
                        <td class="px-3 py-2 text-sm text-slate-700">{{ $booking->booking_date ?? '2024-01-01' }}</td>
                        <td class="px-3 py-2 text-sm text-slate-700">{{ $booking->invoice ?? 'INV-0001' }}</td>
                        <td class="px-3 py-2 text-sm text-slate-700">{{ $booking->pax_qty ?? '1' }}</td>
                        <td class="px-3 py-2 text-sm text-slate-700">{{ $booking->guardian ?? 'John Doe' }}</td>
                        <td class="px-3 py-2 text-sm text-slate-700">{{ $booking->mobile ?? '01700000000' }}</td>
                        <td class="px-3 py-2 text-sm text-slate-700">{{ $booking->passenger ?? 'Passenger Name' }}</td>
                        <td class="px-3 py-2 text-sm text-slate-700">{{ $booking->passport ?? 'AB1234567' }}</td>
                        <td class="px-3 py-2 text-sm text-slate-700">{{ $booking->route ?? 'DXB-JFK' }}</td>
                        <td class="px-3 py-2 text-sm text-slate-700">{{ $booking->current_status ?? 'Pending' }}</td>
                        <td class="px-3 py-2 text-sm text-slate-700">{{ $booking->ticket_fare ?? '50000' }}</td>
                        <td class="px-3 py-2 text-sm text-slate-700">{{ $booking->visa ?? 'Yes' }}</td>
                        <td class="px-3 py-2 text-sm text-slate-700">{{ $booking->visa_agent ?? 'Agent Name' }}</td>
                        <td class="px-3 py-2 text-sm text-slate-700">{{ $booking->visa_status ?? 'Processing' }}</td>
                        <td class="px-3 py-2 text-sm text-slate-700">{{ $booking->required_flight_date ?? '2024-02-01' }}</td>
                        <td class="px-3 py-2 text-sm text-slate-700">{{ $booking->actual_flight_date ?? '2024-02-01' }}</td>
                        <td class="px-3 py-2 text-sm text-slate-700">{{ $booking->f_cost ?? '45000' }}</td>
                        <td class="px-3 py-2 text-sm text-slate-700">{{ $booking->package ?? 'Standard' }}</td>
                        <td class="px-3 py-2 text-sm text-slate-700">{{ $booking->package_value ?? '25000' }}</td>
                        <td class="px-3 py-2 text-sm text-slate-700">{{ $booking->total_cost ?? '70000' }}</td>
                        <td class="px-3 py-2 text-sm text-slate-700">{{ $booking->markup ?? '5000' }}</td>
                        <td class="px-3 py-2 text-sm text-slate-700">{{ $booking->due ?? '20000' }}</td>
                        <td class="px-3 py-2 text-sm">
                            <button class="text-slate-600 hover:text-slate-900">Edit</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <div x-show="activeTab === 'addBooking'" x-cloak>
        <form method="POST" action="{{ route('booking.store') }}" class="bg-white p-6 rounded-lg shadow-sm border border-slate-200">
            @csrf
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Customer Name</label>
                    <input type="text" name="customer_name" class="w-full px-3 py-2 border border-slate-300 rounded-md" placeholder="Customer Name">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Customer Mobile</label>
                    <input type="text" name="customer_mobile" class="w-full px-3 py-2 border border-slate-300 rounded-md" placeholder="+966501234567">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Passenger Name</label>
                    <input type="text" name="passenger_name[]" class="w-full px-3 py-2 border border-slate-300 rounded-md" placeholder="Passenger Name">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Passenger Passport</label>
                    <input type="text" name="passenger_passport[]" class="w-full px-3 py-2 border border-slate-300 rounded-md" placeholder="Passport Number">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Route</label>
                    <input type="text" name="route" class="w-full px-3 py-2 border border-slate-300 rounded-md" placeholder="DAC-JED-DAC">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Package</label>
                    <select name="package_type" class="w-full px-3 py-2 border border-slate-300 rounded-md focus:outline-none focus:ring-2 focus:ring-slate-500 focus:border-transparent">
                        <option value="">Select Package</option>
                        <option value="standard">Standard</option>
                        <option value="premium">Premium</option>
                        <option value="vip">VIP</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">PAX QTY</label>
                    <input type="number" name="pax_qty" class="w-full px-3 py-2 border border-slate-300 rounded-md focus:outline-none focus:ring-2 focus:ring-slate-500 focus:border-transparent" placeholder="Enter quantity">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-slate-700 mb-1">Remarks</label>
                    <textarea name="remarks" rows="3" class="w-full px-3 py-2 border border-slate-300 rounded-md focus:outline-none focus:ring-2 focus:ring-slate-500 focus:border-transparent" placeholder="Enter remarks"></textarea>
                </div>
            </div>
            <div class="mt-6">
                <button type="submit" class="w-full md:w-auto px-6 py-2 bg-slate-800 text-white rounded-md hover:bg-slate-700 transition">Submit</button>
            </div>
        </form>
    </div>
</div>
@endsection