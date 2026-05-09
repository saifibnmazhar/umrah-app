@extends('layouts.app')
@section('title', 'Booking')
@section('content')
<div class="max-w-7xl mx-auto" x-data="bookingApp()">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-slate-800">Booking</h1>
        <button @click="showForm()" x-show="!formVisible" class="px-6 py-3 bg-slate-700 text-white rounded-lg hover:bg-slate-800 transition font-medium flex items-center gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
            </svg>
            Add Booking
        </button>
    </div>

    @if(session('success'))
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
        {{ session('success') }}
    </div>
    @endif

    @if(session('error'))
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
        {{ session('error') }}
    </div>
    @endif

    <div class="flex gap-2 mb-4">
        <button @click="activeTab = 'booking'" :class="activeTab === 'booking' ? 'bg-slate-700 text-white' : 'bg-slate-200 text-slate-700 hover:bg-slate-300'" class="px-4 py-2 rounded-lg font-medium transition">Booking Index</button>
        <button @click="activeTab = 'passenger'" :class="activeTab === 'passenger' ? 'bg-slate-700 text-white' : 'bg-slate-200 text-slate-700 hover:bg-slate-300'" class="px-4 py-2 rounded-lg font-medium transition">Passenger Index</button>
    </div>

    <div x-show="activeTab === 'booking'" x-cloak>
        <div class="bg-white rounded-xl shadow-lg p-6">
            <div class="mb-4">
                <input type="text" x-model="searchTerm" @input="searchBookings()" class="w-full md:w-64 px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none transition" placeholder="Search by Mobile or Invoice No...">
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[1000px] text-sm">
                    <thead class="bg-slate-50 text-slate-600">
                        <tr>
                            <th class="px-3 py-2 text-left font-medium">Invoice No</th>
                            <th class="px-3 py-2 text-left font-medium">Booking Date</th>
                            <th class="px-3 py-2 text-left font-medium">Customer</th>
                            <th class="px-3 py-2 text-left font-medium">Mobile</th>
                            <th class="px-3 py-2 text-left font-medium">Passengers</th>
                            <th class="px-3 py-2 text-left font-medium">Fingerprint Location</th>
                            <th class="px-3 py-2 text-left font-medium">Office</th>
                            <th class="px-3 py-2 text-left font-medium">District</th>
                            <th class="px-3 py-2 text-left font-medium">Package</th>
                            <th class="px-3 py-2 text-left font-medium">Total</th>
                            <th class="px-3 py-2 text-left font-medium">Paid</th>
                            <th class="px-3 py-2 text-left font-medium">Due</th>
                            <th class="px-3 py-2 text-left font-medium">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        @forelse($bookings as $booking)
                        <tr>
                            <td class="px-3 py-2 text-slate-700">{{ $booking->id }}</td>
                            <td class="px-3 py-2 text-slate-700">{{ $booking->created_at->format('Y-m-d') }}</td>
                            <td class="px-3 py-2 text-slate-700">{{ $booking->customer->name ?? '-' }}</td>
                            <td class="px-3 py-2 text-slate-700">{{ $booking->customer->mobile_no ?? '-' }}</td>
                            <td class="px-3 py-2 text-slate-700">{{ $booking->pax_qty }}</td>
                            <td class="px-3 py-2 text-slate-700">{{ $booking->fingerprint_location ?? '-' }}</td>
                            <td class="px-3 py-2 text-slate-700">{{ $booking->fingerprint_office ?? '-' }}</td>
                            <td class="px-3 py-2 text-slate-700">{{ $booking->district->name ?? '-' }}</td>
                            <td class="px-3 py-2 text-slate-700">{{ $booking->package->package_name ?? '-' }}</td>
                            <td class="px-3 py-2 text-slate-700">0 SAR</td>
                            <td class="px-3 py-2 text-slate-700">0 SAR</td>
                            <td class="px-3 py-2 text-slate-700">0 SAR</td>
                            <td class="px-3 py-2 text-sm">
                                <a href="{{ route('bookings.show', $booking->id) }}" class="text-slate-600 hover:text-slate-900 mr-2">View</a>
                                <a href="{{ route('bookings.edit', $booking->id) }}" class="text-slate-600 hover:text-slate-900">Edit</a>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="13" class="px-3 py-8 text-center text-slate-500">No bookings yet. Create a new booking to see it here.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4">
                {{ $bookings->links() }}
            </div>
        </div>
    </div>

    <div x-show="activeTab === 'passenger'" x-cloak>
        <div class="bg-white rounded-xl shadow-lg p-6">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[1200px] text-sm">
                    <thead class="bg-slate-50 text-slate-600">
                        <tr>
                            <th class="px-3 py-2 text-left font-medium">Booking Date</th>
                            <th class="px-3 py-2 text-left font-medium">Invoice</th>
                            <th class="px-3 py-2 text-left font-medium">Guardian</th>
                            <th class="px-3 py-2 text-left font-medium">Mobile</th>
                            <th class="px-3 py-2 text-left font-medium">Passenger</th>
                            <th class="px-3 py-2 text-left font-medium">Passport</th>
                            <th class="px-3 py-2 text-left font-medium">Passenger Type</th>
                            <th class="px-3 py-2 text-left font-medium">Service Required</th>
                            <th class="px-3 py-2 text-left font-medium">Status</th>
                            <th class="px-3 py-2 text-left font-medium">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        @forelse($passengers as $passenger)
                        <tr>
                            <td class="px-3 py-2 text-slate-700">{{ $passenger->created_at->format('Y-m-d') }}</td>
                            <td class="px-3 py-2 text-slate-700">{{ $passenger->booking_id }}</td>
                            <td class="px-3 py-2 text-slate-700">{{ $passenger->booking->customer->name ?? '-' }}</td>
                            <td class="px-3 py-2 text-slate-700">{{ $passenger->booking->customer->mobile_no ?? '-' }}</td>
                            <td class="px-3 py-2 text-slate-700">{{ $passenger->first_name }} {{ $passenger->last_name }}</td>
                            <td class="px-3 py-2 text-slate-700">{{ $passenger->passport_no }}</td>
                            <td class="px-3 py-2 text-slate-700">{{ ucfirst($passenger->passenger_type) }}</td>
                            <td class="px-3 py-2 text-slate-700">{{ $passenger->service_required }}</td>
                            <td class="px-3 py-2 text-slate-700">{{ ucfirst($passenger->ticket_status ?? 'None') }}</td>
                            <td class="px-3 py-2 text-sm">
                                <a href="{{ route('passengers.show', $passenger->id) }}" class="text-slate-600 hover:text-slate-900">View</a>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="10" class="px-3 py-8 text-center text-slate-500">No passengers yet.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4">
                {{ $passengers->links() }}
            </div>
        </div>
    </div>

    <div x-show="formVisible" x-cloak class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto">
        <div class="fixed inset-0 bg-black/50" @click="hideForm()"></div>
        <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-4xl mx-4 p-6 my-8 max-h-[90vh] overflow-y-auto">
            <h2 class="text-xl font-semibold text-slate-700 mb-6 pb-2 border-b border-slate-200">Add Booking</h2>
            
            <form method="POST" action="{{ route('bookings.store') }}" @submit="submitForm($event)">
                @csrf
                <div class="mb-6">
                    <label class="block text-sm font-medium text-slate-700 mb-2">Customer <span class="text-slate-400">(Passport No.)</span></label>
                    <div class="relative">
                        <input type="text" x-model="customerSearch" @input="searchCustomers()" class="w-full px-4 py-3 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none transition" placeholder="Enter Passport Number">
                        <div x-show="customerSuggestions.length > 0" class="absolute z-10 w-full mt-1 bg-white border border-slate-200 rounded-lg shadow-lg max-h-60 overflow-y-auto">
                            <template x-for="customer in customerSuggestions">
                                <div @click="selectCustomer(customer)" class="px-4 py-3 hover:bg-slate-50 cursor-pointer border-b border-slate-100">
                                    <div class="font-medium text-slate-800" x-text="customer.name"></div>
                                    <div class="text-sm text-slate-500">Passport: <span x-text="customer.passport_no"></span> | Iqama: <span x-text="customer.iqama_no"></span></div>
                                </div>
                            </template>
                        </div>
                        <div x-show="customerSearch.length >= 2 && customerSuggestions.length === 0 && !selectedCustomer" class="absolute z-10 w-full mt-1 bg-white border border-slate-200 rounded-lg shadow-lg p-4">
                            <p class="text-slate-600 mb-2">No customer found for "<span x-text="customerSearch"></span>"</p>
                            <button type="button" @click="openCustomerModal()" class="text-slate-700 font-medium hover:underline flex items-center gap-1">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                                </svg>
                                Add New Customer
                            </button>
                        </div>
                    </div>
                    <div x-show="selectedCustomer" class="hidden mt-3 p-3 bg-slate-50 border border-slate-200 rounded-lg">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="font-medium text-slate-800" x-text="selectedCustomer?.name"></p>
                                <p class="text-sm text-slate-500">Passport: <span x-text="selectedCustomer?.passport_no"></span> | Mobile: <span x-text="selectedCustomer?.mobile_no"></span></p>
                            </div>
                            <button type="button" @click="clearSelectedCustomer()" class="text-sm text-slate-500 hover:text-slate-700">Clear</button>
                        </div>
                        <input type="hidden" name="customer_id" :value="selectedCustomer?.id">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">Fingerprint Location *</label>
                        <select x-model="bookingData.fingerprint_location" @change="updateFingerprintCharge()" name="fingerprint_location" required class="w-full px-4 py-3 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none transition bg-white">
                            <option value="Office">Office</option>
                            <option value="Home">Home</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">Fingerprint Office *</label>
                        <select x-model="bookingData.fingerprint_office" name="fingerprint_office" class="w-full px-4 py-3 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none transition bg-white">
                            <option value="">Select Office</option>
                            <option value="BMT-Dhaka">BMT-Dhaka</option>
                            <option value="BMT-Chattogram">BMT-Chattogram</option>
                            <option value="BMT-Sylhet">BMT-Sylhet</option>
                            <option value="BMT-Rangpur">BMT-Rangpur</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">District *</label>
                        <select x-model="bookingData.district_id" @change="updateFingerprintCharge()" name="district_id" class="w-full px-4 py-3 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none transition bg-white">
                            <option value="">Select District</option>
                            @foreach(\App\Models\District::orderBy('name')->get() as $district)
                            <option value="{{ $district->id }}">{{ $district->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">Package</label>
                        <select x-model="bookingData.package_id" name="package_id" class="w-full px-4 py-3 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none transition bg-white">
                            <option value="">Select Package</option>
                            @foreach(\App\Models\Package::orderBy('package_name')->get() as $package)
                            <option value="{{ $package->id }}">{{ $package->package_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">PAX QTY</label>
                        <input type="number" x-model="passengerCount" readonly class="w-full px-4 py-3 border border-slate-200 rounded-lg bg-slate-50 text-slate-600">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">Remarks</label>
                        <input type="text" x-model="bookingData.remarks" name="remarks" class="w-full px-4 py-3 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none transition" placeholder="Enter remarks">
                    </div>
                </div>

                <div class="mb-6">
                    <button type="button" @click="openPassengerModal()" class="px-6 py-3 bg-slate-700 text-white rounded-lg hover:bg-slate-800 transition font-medium flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                        </svg>
                        Add Passenger
                    </button>
                </div>

                <div x-show="passengers.length > 0" class="mb-6">
                    <h3 class="text-lg font-semibold text-slate-700 mb-4">Passengers</h3>
                    <div class="space-y-4">
                        <template x-for="(passenger, index) in passengers" :key="index">
                            <div class="bg-slate-50 border border-slate-200 rounded-lg p-4">
                                <div class="flex items-start justify-between">
                                    <div class="flex-1">
                                        <div class="flex items-center gap-3 mb-2">
                                            <span class="bg-slate-700 text-white text-xs font-medium px-2 py-1 rounded" x-text="'P' + (index + 1)"></span>
                                            <h4 class="font-semibold text-slate-800" x-text="passenger.first_name + ' ' + passenger.last_name"></h4>
                                        </div>
                                        <div class="grid grid-cols-2 md:grid-cols-4 gap-2 text-sm">
                                            <div><span class="text-slate-500">Passport:</span> <span class="text-slate-700 ml-1" x-text="passenger.passport_no"></span></div>
                                            <div><span class="text-slate-500">Type:</span> <span class="text-slate-700 ml-1" x-text="passenger.passenger_type"></span></div>
                                            <div><span class="text-slate-500">Service:</span> <span class="text-slate-700 ml-1" x-text="passenger.service_required"></span></div>
                                            <div><span class="text-slate-500">DOB:</span> <span class="text-slate-700 ml-1" x-text="passenger.date_of_birth"></span></div>
                                        </div>
                                        <input type="hidden" :name="'passengers[' + index + '][first_name]'" :value="passenger.first_name">
                                        <input type="hidden" :name="'passengers[' + index + '][last_name]'" :value="passenger.last_name">
                                        <input type="hidden" :name="'passengers[' + index + '][passport_no]'" :value="passenger.passport_no">
                                        <input type="hidden" :name="'passengers[' + index + '][date_of_birth]'" :value="passenger.date_of_birth">
                                        <input type="hidden" :name="'passengers[' + index + '][mobile_no]'" :value="passenger.mobile_no">
                                        <input type="hidden" :name="'passengers[' + index + '][passport_expiry]'" :value="passenger.passport_expiry">
                                        <input type="hidden" :name="'passengers[' + index + '][service_required]'" :value="passenger.service_required">
                                        <input type="hidden" :name="'passengers[' + index + '][stay_duration]'" :value="passenger.stay_duration">
                                    </div>
                                    <div class="flex items-center gap-2 ml-4">
                                        <button type="button" @click="editPassenger(index)" class="px-3 py-1.5 text-sm border border-slate-300 text-slate-600 rounded hover:bg-slate-100 transition">Edit</button>
                                        <button type="button" @click="removePassenger(index)" class="px-3 py-1.5 text-sm border border-red-300 text-red-600 rounded hover:bg-red-100 transition">Delete</button>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                    <button type="button" @click="openPassengerModal()" class="mt-4 px-6 py-3 border-2 border-slate-700 text-slate-700 rounded-lg hover:bg-slate-50 transition font-medium">+ Add More</button>
                </div>

                <div class="bg-slate-50 rounded-lg p-4 mb-6 border border-slate-200">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-semibold text-slate-700">Summary Card</h3>
                        <button type="button" @click="openDiscountModal()" class="text-sm bg-slate-200 hover:bg-slate-300 text-slate-600 px-3 py-1 rounded">Discount</button>
                    </div>
                    <div class="flex justify-between text-sm text-slate-500 mb-2">
                        <span class="w-1/6 text-center">Package</span>
                        <span class="w-1/6 text-center">Fingerprint</span>
                        <span class="w-1/6 text-center">Pax Qty</span>
                        <span class="w-1/6 text-center">Discount</span>
                        <span class="w-1/6 text-center">Total</span>
                        <span class="w-1/6 text-center">Value</span>
                    </div>
                    <div class="flex justify-between font-medium text-slate-800">
                        <span class="w-1/6 text-center">-</span>
                        <span class="w-1/6 text-center" x-text="fingerprintCharge > 0 ? fingerprintCharge + ' SAR' : '-'">-</span>
                        <span class="w-1/6 text-center" x-text="passengerCount">0</span>
                        <span class="w-1/6 text-center" x-text="bookingData.discount_value > 0 ? '-' + bookingData.discount_value + (bookingData.discount_type === 'percentage' ? '%' : ' SAR') : '-'">-</span>
                        <span class="w-1/6 text-center">0 SAR</span>
                        <span class="w-1/6 text-center">0 SAR</span>
                    </div>
                </div>

                <div class="flex gap-3 pt-6 border-t border-slate-200">
                    <button type="submit" class="flex-1 px-6 py-3 bg-slate-700 text-white rounded-lg hover:bg-slate-800 transition font-medium" :disabled="passengers.length === 0">Submit</button>
                    <button type="button" @click="clearForm()" class="px-6 py-3 border border-slate-300 text-slate-600 rounded-lg hover:bg-slate-50 transition font-medium">Clear</button>
                    <button type="button" @click="hideForm()" class="px-6 py-3 border border-slate-300 text-slate-600 rounded-lg hover:bg-slate-50 transition font-medium">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <div x-show="passengerModalVisible" x-cloak class="fixed inset-0 z-[60] flex items-center justify-center">
        <div class="fixed inset-0 bg-black/50" @click="closePassengerModal()"></div>
        <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-2xl mx-4 p-6 max-h-[90vh] overflow-y-auto">
            <h3 class="text-xl font-semibold text-slate-800 mb-4">Add Passenger</h3>
            <form @submit.prevent="savePassenger()">
                <div class="mb-4">
                    <h4 class="text-sm font-medium text-slate-600 mb-3 pb-2 border-b border-slate-200">Basic Information</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">First Name *</label>
                            <input type="text" x-model="passengerData.first_name" required class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Last Name *</label>
                            <input type="text" x-model="passengerData.last_name" required class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Passport No. *</label>
                            <input type="text" x-model="passengerData.passport_no" required class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Mobile No.</label>
                            <input type="tel" x-model="passengerData.mobile_no" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Date of Birth *</label>
                            <input type="date" x-model="passengerData.date_of_birth" @change="calculatePassengerType()" required class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Passenger Type</label>
                            <input type="text" x-model="passengerData.passenger_type" readonly class="w-full px-4 py-2 border border-slate-200 rounded-lg bg-slate-50 text-slate-600">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Passport Expiry</label>
                            <input type="date" x-model="passengerData.passport_expiry" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none">
                        </div>
                    </div>
                </div>

                <div class="mb-4">
                    <h4 class="text-sm font-medium text-slate-600 mb-3 pb-2 border-b border-slate-200">Service Information</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Stay Duration *</label>
                            <select x-model="passengerData.stay_duration" required class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none bg-white">
                                <option value="14">Group (14 Days)</option>
                                <option value="85">Family (85 Days)</option>
                                <option value="30">Custom (30 Days)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Service Required *</label>
                            <select x-model="passengerData.service_required" required class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none bg-white">
                                <option value="All">All</option>
                                <option value="Visa Only">Visa Only</option>
                                <option value="Ticket Only">Ticket Only</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="flex gap-3 mt-6">
                    <button type="submit" class="flex-1 px-6 py-2 bg-slate-700 text-white rounded-lg hover:bg-slate-800 transition font-medium">Add</button>
                    <button type="button" @click="closePassengerModal()" class="flex-1 px-6 py-2 border border-slate-300 text-slate-700 rounded-lg hover:bg-slate-50 transition font-medium">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <div x-show="discountModalVisible" x-cloak class="fixed inset-0 z-50 flex items-center justify-center">
        <div class="fixed inset-0 bg-black/50" @click="closeDiscountModal()"></div>
        <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-md mx-4 p-6">
            <h3 class="text-xl font-semibold text-slate-800 mb-4">Apply Discount</h3>
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Original Total (SAR)</label>
                    <input type="text" id="discountOriginalTotal" readonly class="w-full px-4 py-2 border border-slate-200 rounded-lg bg-slate-50 text-slate-600" value="0">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Discount Type</label>
                    <select x-model="bookingData.discount_type" id="discountType" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none bg-white">
                        <option value="fixed">Fixed Amount</option>
                        <option value="percentage">Percentage</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Discount Value</label>
                    <input type="number" x-model="bookingData.discount_value" id="discountValue" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none" value="0" min="0">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Discount Amount (SAR)</label>
                    <input type="text" id="discountAmount" readonly class="w-full px-4 py-2 border border-slate-200 rounded-lg bg-slate-50 text-slate-600" value="0">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">New Total (SAR)</label>
                    <input type="text" id="discountNewTotal" readonly class="w-full px-4 py-2 border border-slate-200 rounded-lg bg-slate-100 text-slate-800 font-semibold" value="0">
                </div>
            </div>
            <div class="flex gap-3 mt-6">
                <button type="button" @click="closeDiscountModal()" class="flex-1 px-6 py-2 bg-slate-700 text-white rounded-lg hover:bg-slate-800 transition font-medium">Apply</button>
                <button type="button" @click="closeDiscountModal()" class="flex-1 px-6 py-2 border border-slate-300 text-slate-700 rounded-lg hover:bg-slate-50 transition font-medium">Cancel</button>
            </div>
        </div>
    </div>

    <div x-show="customerModalVisible" x-cloak class="fixed inset-0 z-50 flex items-center justify-center">
        <div class="fixed inset-0 bg-black/50" @click="closeCustomerModal()"></div>
        <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-md mx-4 p-6">
            <h3 class="text-xl font-semibold text-slate-800 mb-4">Add New Customer</h3>
            <form @submit.prevent="submitNewCustomer()">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Name *</label>
                        <input type="text" x-model="newCustomer.name" required class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none" placeholder="Full Name">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Iqama Type *</label>
                        <select x-model="newCustomer.iqama_type" required class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none bg-white">
                            <option value="">Select</option>
                            <option value="none">None</option>
                            <option value="self">Self</option>
                            <option value="referral">Referral</option>
                        </select>
                    </div>
                    <div x-show="newCustomer.iqama_type === 'referral'" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Ref. Iqama *</label>
                            <input type="text" x-model="newCustomer.ref_iqama_no" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none" placeholder="Referrer Iqama Number">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Ref. Mobile No. *</label>
                            <input type="tel" x-model="newCustomer.ref_mobile_no" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none" placeholder="05XXXXXXXX">
                        </div>
                    </div>
                    <div x-show="newCustomer.iqama_type !== 'none'">
                        <label class="block text-sm font-medium text-slate-700 mb-1">Iqama No. *</label>
                        <input type="text" x-model="newCustomer.iqama_no" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none" placeholder="Iqama Number">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Passport No. *</label>
                        <input type="text" x-model="newCustomer.passport_no" required class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none" placeholder="Passport Number">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Mobile No. *</label>
                        <input type="tel" x-model="newCustomer.mobile_no" required class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none" placeholder="05XXXXXXXX">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Address</label>
                        <input type="text" x-model="newCustomer.address" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none" placeholder="Address">
                    </div>
                </div>
                <div class="flex gap-3 mt-6">
                    <button type="submit" class="flex-1 px-6 py-2 bg-slate-700 text-white rounded-lg hover:bg-slate-800 transition font-medium">Add</button>
                    <button type="button" @click="closeCustomerModal()" class="flex-1 px-6 py-2 border border-slate-300 text-slate-700 rounded-lg hover:bg-slate-50 transition font-medium">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function bookingApp() {
    return {
        activeTab: '{{ $tab ?? 'booking' }}',
        formVisible: false,
        searchTerm: '',
        customerSearch: '',
        customerSuggestions: [],
        selectedCustomer: null,
        passengers: [],
        passengerCount: 0,
        fingerprintCharge: 0,
        editingPassengerIndex: null,
        passengerModalVisible: false,
        discountModalVisible: false,
        customerModalVisible: false,
        newCustomer: {
            name: '',
            iqama_type: '',
            iqama_no: '',
            passport_no: '',
            mobile_no: '',
            ref_iqama_no: '',
            ref_mobile_no: '',
            address: ''
        },
        bookingData: {
            fingerprint_location: 'Office',
            fingerprint_office: '',
            district_id: '',
            package_id: '',
            discount_type: 'fixed',
            discount_value: 0,
            remarks: ''
        },
        passengerData: {
            first_name: '',
            last_name: '',
            passport_no: '',
            date_of_birth: '',
            passenger_type: '',
            mobile_no: '',
            passport_expiry: '',
            service_required: 'All',
            stay_duration: '14'
        },
        showForm() {
            this.formVisible = true;
        },
        hideForm() {
            this.formVisible = false;
            this.clearForm();
        },
        clearForm() {
            this.selectedCustomer = null;
            this.customerSearch = '';
            this.customerSuggestions = [];
            this.passengers = [];
            this.passengerCount = 0;
            this.newCustomer = {
                name: '',
                iqama_type: '',
                iqama_no: '',
                passport_no: '',
                mobile_no: '',
                ref_iqama_no: '',
                ref_mobile_no: '',
                address: ''
            };
            this.bookingData = {
                fingerprint_location: 'Office',
                fingerprint_office: '',
                district_id: '',
                package_id: '',
                remarks: ''
            };
        },
        async searchCustomers() {
            if (this.customerSearch.length < 2) {
                this.customerSuggestions = [];
                return;
            }
            try {
                const response = await fetch('/api/customers/search?q=' + this.customerSearch);
                this.customerSuggestions = await response.json();
            } catch (e) {
                console.error(e);
            }
        },
        selectCustomer(customer) {
            this.selectedCustomer = customer;
            this.customerSearch = customer.passport_no;
            this.customerSuggestions = [];
        },
        clearSelectedCustomer() {
            this.selectedCustomer = null;
            this.customerSearch = '';
        },
        async calculatePassengerType() {
            if (!this.passengerData.date_of_birth) return;
            try {
                const response = await fetch('/api/bookings/calculate-type', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ date_of_birth: this.passengerData.date_of_birth })
                });
                const data = await response.json();
                this.passengerData.passenger_type = data.passenger_type || 'adult';
            } catch (e) {
                console.error(e);
                this.passengerData.passenger_type = 'adult';
            }
        },
        async updateFingerprintCharge() {
            if (!this.bookingData.district_id) return;
            try {
                const response = await fetch('/api/bookings/fingerprint-charge?district_id=' + this.bookingData.district_id + '&location=' + this.bookingData.fingerprint_location);
                const data = await response.json();
                this.fingerprintCharge = data.charge || 0;
            } catch (e) {
                console.error(e);
            }
        },
        openPassengerModal() {
            this.editingPassengerIndex = null;
            this.passengerData = {
                first_name: '',
                last_name: '',
                passport_no: '',
                date_of_birth: '',
                passenger_type: '',
                mobile_no: '',
                passport_expiry: '',
                service_required: 'All',
                stay_duration: '14'
            };
            this.passengerModalVisible = true;
        },
        closePassengerModal() {
            this.passengerModalVisible = false;
        },
        editPassenger(index) {
            this.editingPassengerIndex = index;
            this.passengerData = { ...this.passengers[index] };
            this.passengerModalVisible = true;
        },
        savePassenger() {
            if (!this.passengerData.first_name || !this.passengerData.last_name || !this.passengerData.passport_no || !this.passengerData.date_of_birth) {
                alert('Please fill in all required fields');
                return;
            }
            if (this.editingPassengerIndex !== null) {
                this.passengers[this.editingPassengerIndex] = { ...this.passengerData };
            } else {
                this.passengers.push({ ...this.passengerData });
            }
            this.passengerCount = this.passengers.length;
            this.closePassengerModal();
        },
        removePassenger(index) {
            if (confirm('Are you sure you want to remove this passenger?')) {
                this.passengers.splice(index, 1);
                this.passengerCount = this.passengers.length;
            }
        },
        searchBookings() {
            console.log('Searching:', this.searchTerm);
        },
        submitForm(e) {
            if (!this.selectedCustomer) {
                alert('Please select a customer');
                e.preventDefault();
                return;
            }
            if (this.passengers.length === 0) {
                alert('Please add at least one passenger');
                e.preventDefault();
                return;
            }
        },
        openDiscountModal() {
            this.discountModalVisible = true;
        },
        closeDiscountModal() {
            this.discountModalVisible = false;
        },
        openCustomerModal() {
            this.newCustomer.passport_no = this.customerSearch;
            this.customerModalVisible = true;
        },
        closeCustomerModal() {
            this.customerModalVisible = false;
        },
        async submitNewCustomer() {
            try {
                const response = await fetch('{{ route("customers.store") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify(this.newCustomer)
                });
                const text = await response.text();
                console.log('Response status:', response.status);
                console.log('Raw response:', text);
                
                let data;
                try {
                    data = JSON.parse(text);
                } catch (parseError) {
                    alert('Server error: Received non-JSON response. Check console for details.');
                    console.log('Parse error:', parseError);
                    return;
                }
                
                if (data.success) {
                    this.selectedCustomer = data.customer;
                    this.customerSearch = data.customer.passport_no;
                    this.customerSuggestions = [];
                    this.closeCustomerModal();
                    this.newCustomer = {
                        name: '',
                        iqama_type: '',
                        iqama_no: '',
                        passport_no: '',
                        mobile_no: '',
                        ref_iqama_no: '',
                        ref_mobile_no: '',
                        address: ''
                    };
                    alert('Customer added successfully');
                } else {
                    alert(data.message || 'Failed to add customer');
                }
            } catch (e) {
                console.error('Error:', e);
                alert('Failed to add customer');
            }
        }
    };
}
</script>
@endsection