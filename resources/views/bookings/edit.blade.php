@extends('layouts.app')
@section('title', 'Edit Booking')
@section('content')
<script>window.__bookingServerData = {
    ticketFares: @json($ticketFares ?? []),
    packages: @json($packages ?? []),
    isEditMode: true,
    existingBooking: @json($booking->toArray()),
    existingPassengers: @json($booking->passengers->toArray()),
    existingCustomer: @json($booking->customer?->toArray() ?? null),
    currentCurrencyRate: {{ $currentCurrencyRate?->rate ?? 0 }},
    bookingId: {{ $booking->id }},
    updateRoute: '{{ route('bookings.update', $booking->id) }}'
};</script>
<div class="max-w-5xl mx-auto" x-data="editBookingApp()" x-init="init()">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-slate-800">Edit Booking</h1>
        <a href="{{ route('bookings.show', $booking->id) }}" class="px-6 py-3 bg-slate-700 text-white rounded-lg hover:bg-slate-800 transition font-medium flex items-center gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 17l-5-5m5 5l5-5m-5 5h14" />
            </svg>
            Back to Invoice
        </a>
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

    <div class="bg-white rounded-xl shadow-lg p-6">
        <form method="POST" action="{{ route('bookings.update', $booking->id) }}" @submit="submitForm($event)">
            @csrf
            @method('PUT')
            <input type="hidden" name="payment[amount]" :value="paymentSaved ? (parseFloat(paymentData.amount_sar) || 0) : 0">
            <input type="hidden" name="payment[bdt_amount]" :value="paymentSaved ? (parseFloat(paymentData.amount_bdt) || 0) : 0">
            <input type="hidden" name="payment[currency]" :value="paymentSaved ? paymentData.currency : 'SAR'">
            <input type="hidden" name="payment[payment_method]" :value="paymentSaved && paymentData.method === 'Cash' ? 'cash' : 'bank'">
            <input type="hidden" name="payment[payment_date]" :value="paymentSaved ? new Date().toISOString().split('T')[0] : ''">
            <input type="hidden" name="payment[transaction_id]" :value="paymentSaved ? (paymentData.trx_id || '') : ''">
            <input type="hidden" name="payment[bank_id]" :value="paymentSaved ? (paymentData.bank_method || '') : ''">
            <div class="mb-6">
                <label class="block text-sm font-medium text-slate-700 mb-2">Customer <span class="text-slate-400">(Passport No.)</span></label>
                <div class="relative">
                    <input type="text" x-model="customerSearch" @input="searchCustomers()" @focus="customerInputFocused = true; searchCustomers()" @blur="setTimeout(() => { customerInputFocused = false; customerSuggestions = []; }, 200)" :disabled="selectedCustomer" class="w-full px-4 py-3 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none transition disabled:bg-slate-100 disabled:cursor-not-allowed" placeholder="Enter Passport Number">
                    <div x-show="customerSuggestions.length > 0" class="absolute z-10 w-full mt-1 bg-white border border-slate-200 rounded-lg shadow-lg max-h-60 overflow-y-auto">
                        <template x-for="customer in customerSuggestions">
                            <div @click="selectCustomer(customer)" class="px-4 py-3 hover:bg-slate-50 cursor-pointer border-b border-slate-100">
                                <div class="font-medium text-slate-800" x-text="customer.name"></div>
                                <div class="text-sm text-slate-500">Passport: <span x-text="customer.passport_no"></span> | Iqama: <span x-text="customer.iqama_no"></span></div>
                            </div>
                        </template>
                    </div>
                    <div x-show="customerSearch.length >= 2 && customerSuggestions.length === 0 && !selectedCustomer && customerInputFocused" class="absolute z-10 w-full mt-1 bg-white border border-slate-200 rounded-lg shadow-lg p-4">
                        <p class="text-slate-600 mb-2">No customer found for "<span x-text="customerSearch"></span>"</p>
                        <button type="button" @click="openCustomerModal()" class="text-slate-700 font-medium hover:underline flex items-center gap-1">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                            </svg>
                            Add New Customer
                        </button>
                    </div>
                </div>
                <div x-show="selectedCustomer" x-cloak class="mt-3 p-3 bg-slate-50 border border-slate-200 rounded-lg">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="font-medium text-slate-800" x-text="selectedCustomer?.name"></p>
                            <p class="text-sm text-slate-500">Passport: <span x-text="selectedCustomer?.passport_no"></span> | Iqama: <span x-text="selectedCustomer?.iqama_no"></span> | Mobile: <span x-text="selectedCustomer?.mobile_no"></span></p>
                        </div>
                        <button type="button" @click="clearSelectedCustomer()" class="text-sm text-slate-500 hover:text-slate-700">Clear</button>
                    </div>
                    <input type="hidden" name="customer_id" :value="selectedCustomer?.id">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-2">Fingerprint Location *</label>
                    <select x-model="bookingData.fingerprint_location" @change="updateFingerprintCharge(); $el.blur()" name="fingerprint_location" required class="w-full px-4 py-3 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none transition bg-white">
                        <option value="office">Office</option>
                        <option value="home">Home</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-2">Office *</label>
                    <select x-model="bookingData.fingerprint_office" name="office_id" @change="$el.blur()" class="w-full px-4 py-3 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none transition bg-white">
                        <option value="">Select Office</option>
                        @foreach(\App\Models\Office::orderBy('name')->get() as $office)
                        <option value="{{ $office->id }}">{{ $office->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-2">District *</label>
                    <select x-model="bookingData.district_id" @change="updateFingerprintCharge(); $el.blur()" name="district_id" class="w-full px-4 py-3 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none transition bg-white">
                        <option value="">Select District</option>
                        @foreach($districts as $district)
                        <option value="{{ $district->id }}">{{ $district->name }}</option>
                        @endforeach
                    </select>
                    <input type="hidden" x-model="bookingData.fingerprint_charge_id" name="fingerprint_charge_id">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-2">Package</label>
                    <select x-model="bookingData.package_id" @change="onPackageChange(); $el.blur()" name="package_id" class="w-full px-4 py-3 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none transition bg-white">
                        <option value="">Select Package</option>
                        <template x-for="pkg in allPackages" :key="pkg.id">
                            <option :value="String(pkg.id)"
                                :data-visa-price="pkg.visa_selling_price ?? 0"
                                :data-service-charge="pkg.service_charge ?? 0"
                                x-text="pkg.package_name"></option>
                        </template>
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

            <div class="mb-4">
                <label class="block text-sm font-medium text-slate-700 mb-1">Customer Docs</label>
                <div class="border-2 border-dashed border-slate-300 rounded-lg p-4 text-center hover:bg-slate-50 transition cursor-pointer" onclick="document.getElementById('booking_customer_docs').click()">
                    <input type="file" id="booking_customer_docs" name="booking_customer_docs[]" class="hidden" accept=".jpg,.jpeg,.png,.pdf" multiple onchange="handleBookingCustomerDocsUpload(this)">
                    <div class="text-slate-500">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 mx-auto mb-2 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                        </svg>
                        <span>click to upload</span>
                    </div>
                </div>
                <div id="booking_customer_docs_list" class="mt-2 space-y-1">
                    @forelse($booking->documents as $doc)
                    <div class="flex items-center justify-between bg-slate-50 rounded px-3 py-2">
                        <span class="text-sm text-slate-700 truncate">{{ $doc->display_name ?? 'Document' }}</span>
                        <button type="button" onclick="removeExistingDoc({{ $doc->id }})" class="text-red-500 hover:text-red-700 text-xs">Remove</button>
                    </div>
                    @empty
                    @endforelse
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
                                        <div><span class="text-slate-500">Route:</span> <span class="text-slate-700 ml-1" x-text="passenger.route || '-'"></span></div>
                                        <div><span class="text-slate-500">Airline:</span> <span class="text-slate-700 ml-1" x-text="passenger.airline || '-'"></span></div>
                                        <div><span class="text-slate-500">Flight:</span> <span class="text-slate-700 ml-1" x-text="passenger.flight_type || '-'"></span></div>
                                        <div><span class="text-slate-500">Duration:</span> <span class="text-slate-700 ml-1" x-text="passenger.stay_duration || '-'"></span></div>
                                    </div>
                                    <input type="hidden" :name="'passengers[' + index + '][id]'" :value="passenger.id">
                                    <input type="hidden" :name="'passengers[' + index + '][first_name]'" :value="passenger.first_name">
                                    <input type="hidden" :name="'passengers[' + index + '][last_name]'" :value="passenger.last_name">
                                    <input type="hidden" :name="'passengers[' + index + '][passport_no]'" :value="passenger.passport_no">
                                    <input type="hidden" :name="'passengers[' + index + '][date_of_birth]'" :value="passenger.date_of_birth">
                                    <input type="hidden" :name="'passengers[' + index + '][mobile_no]'" :value="passenger.mobile_no">
                                    <input type="hidden" :name="'passengers[' + index + '][passport_expiry]'" :value="passenger.passport_expiry">
                                    <input type="hidden" :name="'passengers[' + index + '][service_required]'" :value="passenger.service_required">
                                    <input type="hidden" :name="'passengers[' + index + '][stay_duration]'" :value="passenger.stay_duration_int || passenger.stay_duration">
                                    <input type="hidden" :name="'passengers[' + index + '][gender]'" :value="passenger.gender">
                                    <input type="hidden" :name="'passengers[' + index + '][route_type]'" :value="passenger.route_type">
                                    <input type="hidden" :name="'passengers[' + index + '][flight_type]'" :value="passenger.flight_type">
                                    <input type="hidden" :name="'passengers[' + index + '][ticket_fare_id]'" :value="passenger.ticket_fare_id">
                                    <input type="hidden" :name="'passengers[' + index + '][route]'" :value="passenger.route">
                                    <input type="hidden" :name="'passengers[' + index + '][airline]'" :value="passenger.airline">
                                    <input type="hidden" :name="'passengers[' + index + '][class]'" :value="passenger.class">
                                    <input type="hidden" :name="'passengers[' + index + '][flight_date_from]'" :value="passenger.flight_date_from">
                                    <input type="hidden" :name="'passengers[' + index + '][flight_date_to]'" :value="passenger.flight_date_to">
                                    <input type="hidden" :name="'passengers[' + index + '][address]'" :value="passenger.address">
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
                    <div class="flex gap-2">
                        <button type="button" @click="openDiscountModal()" class="text-sm bg-slate-200 hover:bg-slate-300 text-slate-600 px-3 py-1 rounded">Discount</button>
                        <button type="button" @click="openPaymentModal()" class="text-sm bg-slate-200 hover:bg-slate-300 text-slate-600 px-3 py-1 rounded">Payment</button>
                    </div>
                </div>
                <div class="flex justify-between text-sm text-slate-500 mb-2">
                    <span class="w-1/6 text-center">Package</span>
                    <span class="w-1/6 text-center">Fingerprint</span>
                    <span class="w-1/6 text-center">Pax Qty</span>
                    <span class="w-1/6 text-center">Discount</span>
                    <span class="w-1/6 text-center">Total</span>
                    <span class="w-1/6 text-center">Discounted Total</span>
                </div>
                <div class="flex justify-between font-medium text-slate-800">
                    <span id="summaryPackage" class="w-1/6 text-center" x-text="allPackages.find(p => String(p.id) === String(bookingData.package_id))?.package_name ?? '-'">-</span>
                    <span class="w-1/6 text-center" x-text="fingerprintCharge > 0 ? fingerprintCharge + ' SAR' : '-'">-</span>
                    <span class="w-1/6 text-center" x-text="passengerCount">0</span>
                    <span class="w-1/6 text-center" x-text="bookingData.discount_value > 0 ? '-' + bookingData.discount_value + (bookingData.discount_type === 'percentage' ? '%' : ' SAR') : '-'">-</span>
                    <span id="summaryTotalBeforeDiscount" class="w-1/6 text-center" x-text="(grandTotalValue ?? 0).toFixed(2) + ' SAR'">0 SAR</span>
                    <span id="summaryTotalValue" class="w-1/6 text-center" x-text="discountedTotal !== null ? discountedTotal.toFixed(2) + ' SAR' : 'N/A'">0 SAR</span>
                </div>
            </div>

            <div class="flex justify-end gap-3 pt-6 border-t border-slate-200">
                <button type="submit" class="w-64 px-8 py-3 bg-slate-700 text-white rounded-lg hover:bg-slate-800 transition font-medium" :disabled="passengers.length === 0">Save Changes</button>
                <button type="button" @click="clearForm()" class="px-6 py-3 border border-slate-300 text-slate-600 rounded-lg hover:bg-slate-50 transition font-medium">Clear</button>
                <a href="{{ route('bookings.show', $booking->id) }}" class="px-6 py-3 border border-slate-300 text-slate-600 rounded-lg hover:bg-slate-50 transition font-medium">Cancel</a>
            </div>
        </form>
    </div>

    {{-- Passenger Modal --}}
    <div x-show="passengerModalVisible" x-cloak class="fixed inset-0 z-[60] flex items-center justify-center">
        <div class="fixed inset-0 bg-black/50" @click="closePassengerModal()"></div>
        <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-2xl mx-4 p-6 max-h-[90vh] overflow-y-auto">
            <h3 class="text-xl font-semibold text-slate-800 mb-4" x-text="editingPassengerIndex !== null ? 'Edit Passenger' : 'Add Passenger'">Add Passenger</h3>
            <form @submit.prevent="savePassenger()">
                <div class="mb-4">
                    <h4 class="text-sm font-medium text-slate-600 mb-3 pb-2 border-b border-slate-200">Basic Information</h4>
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">First Name *</label>
                            <input type="text" x-model="passengerData.first_name" required class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none" placeholder="First Name">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Last Name *</label>
                            <input type="text" x-model="passengerData.last_name" required class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none" placeholder="Last Name">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Passport No. *</label>
                            <input type="text" x-model="passengerData.passport_no" required class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none" placeholder="Passport Number">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Mobile No.</label>
                            <input type="tel" x-model="passengerData.mobile_no" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none" placeholder="05XXXXXXXX">
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Date of Birth *</label>
                            <input type="date" x-model="passengerData.date_of_birth" @change="calculatePassengerType()" @input="calculatePassengerType()" required class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none">
                            <div x-show="passengerData.passenger_type === 'Adult'" class="mt-2">
                                <label class="block text-sm font-medium text-slate-700 mb-1">Gender *</label>
                                <select x-model="passengerData.gender" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none bg-white">
                                    <option value="">Select</option>
                                    <option value="male">Male</option>
                                    <option value="female">Female</option>
                                </select>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Passenger Type</label>
                            <div class="relative">
                                <input type="text" x-model="passengerData.passenger_type" readonly
                                       @change="updateBaggageWeight()"
                                       class="w-full px-4 py-2 border border-slate-200 rounded-lg bg-slate-50 text-slate-600 font-medium uppercase"
                                       placeholder="Enter DOB to auto-calculate">
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Passport Expiry Date</label>
                            <input type="date" x-model="passengerData.passport_expiry" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none">
                        </div>
                    </div>
                </div>

                <div class="mb-4">
                    <h4 class="text-sm font-medium text-slate-600 mb-3 pb-2 border-b border-slate-200">Service Information</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Stay Duration *</label>
                            <select x-model="passengerData.stay_duration" @change="handleStayDurationChange(); calculatePassengerType()" required class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none bg-white">
                                <option value="">Select Stay Duration</option>
                                <option value="14">Group (14 Days)</option>
                                <option value="85">Family (85 Days)</option>
                                <option value="Customize (Set Duration)">Customize (Set Duration)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Service Required *</label>
                            <select x-model="passengerData.service_required" @change="recalculateCurrentPassenger(editingPassengerIndex ?? passengers.length)" required class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none bg-white">
                                <option value="">Select Service</option>
                                <option value="all">All</option>
                                <option value="visa_only">Visa Only</option>
                                <option value="ticket_only">Ticket Only</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="mb-4">
                    <h4 class="text-sm font-medium text-slate-600 mb-3 pb-2 border-b border-slate-200">Travel Details</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Route Type *</label>
                            <select x-model="passengerData.route_type" @change="updateBaggageWeight(); filterTickets()" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none bg-white">
                                <option value="">Select</option>
                                <option value="One Way-Inbound">One Way-Inbound</option>
                                <option value="One Way-Outbound">One Way-Outbound</option>
                                <option value="Round">Round</option>
                                <option value="Multi City">Multi City</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Flight Type *</label>
                            <select x-model="passengerData.flight_type" @change="filterTickets()" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none bg-white">
                                <option value="">Select</option>
                                <option value="Transit">Transit</option>
                                <option value="Direct">Direct</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Ticket *</label>
                            <select x-model="passengerData.ticket_fare_id" @change="onTicketChange()" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none bg-white">
                                <option value="">Select Ticket</option>
                                <template x-for="ticket in filteredTickets" :key="ticket.id">
                                    <option :value="String(ticket.id)" x-text="getTicketDisplayText(ticket)"></option>
                                </template>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Route *</label>
                            <input type="text" x-model="passengerData.route" disabled class="w-full px-4 py-2 border border-slate-200 rounded-lg bg-slate-50 text-slate-600 cursor-not-allowed" placeholder="Route">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Airline *</label>
                            <input type="text" x-model="passengerData.airline" disabled class="w-full px-4 py-2 border border-slate-200 rounded-lg bg-slate-50 text-slate-600 cursor-not-allowed" placeholder="Airline">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Class *</label>
                            <input type="text" x-model="passengerData.class" disabled class="w-full px-4 py-2 border border-slate-200 rounded-lg bg-slate-50 text-slate-600 cursor-not-allowed" placeholder="Class">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Flight Date Range *</label>
                            <select id="passengerFlightDateRange" x-model="passengerData.flight_date_range" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none bg-white">
                                <option value="">Select Date Range</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Baggage Allowance</label>
                            <input type="text" x-model="passengerData.baggage_weight" readonly class="w-full px-4 py-2 border border-slate-200 rounded-lg bg-slate-50 text-slate-600 font-medium">
                        </div>
                    </div>
                </div>

                <div class="mb-4">
                    <h4 class="text-sm font-medium text-slate-600 mb-3 pb-2 border-b border-slate-200">Location</h4>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Detailed Address <span class="text-red-500">*</span></label>
                        <input type="text" x-model="passengerData.address" required class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none" placeholder="Full address">
                    </div>
                </div>

                <div class="mb-4">
                    <h4 class="text-sm font-medium text-slate-600 mb-3 pb-2 border-b border-slate-200">Documents</h4>
                    <div class="border-2 border-dashed border-slate-300 rounded-lg p-4 text-center hover:border-slate-400 transition cursor-pointer" onclick="document.getElementById('passenger_doc_input').click()">
                        <input type="file" id="passenger_doc_input" class="hidden" accept=".pdf,.jpg,.jpeg,.png" multiple onchange="handlePassengerDocUpload(this)">
                        <div class="text-slate-500">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 mx-auto mb-2 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                            </svg>
                            <p class="text-sm text-slate-600">Click to upload documents</p>
                            <p class="text-xs text-slate-400">PDF, JPG, PNG</p>
                        </div>
                    </div>
                    <div id="passenger_doc_list" class="mt-3 space-y-2"></div>
                </div>

                <div class="flex gap-3 pt-4 border-t border-slate-200">
                    <button type="submit" class="flex-1 px-6 py-2 bg-slate-700 text-white rounded-lg hover:bg-slate-800 transition font-medium">Add</button>
                    <button type="button" @click="closePassengerModal()" class="flex-1 px-6 py-2 border border-slate-300 text-slate-700 rounded-lg hover:bg-slate-50 transition font-medium">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Discount Modal --}}
    <div x-show="discountModalVisible" x-cloak class="fixed inset-0 z-[60] flex items-center justify-center">
        <div class="fixed inset-0 bg-black/50" @click="closeDiscountModal()"></div>
        <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-md mx-4 p-6">
            <h3 class="text-xl font-semibold text-slate-800 mb-4">Apply Discount</h3>
            <div class="mb-4">
                <label class="block text-sm font-medium text-slate-600 mb-1">Discount Type</label>
                <select x-model="bookingData.discount_type" id="discountType" name="discount_type" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none bg-white">
                    <option value="fixed">Fixed (SAR)</option>
                    <option value="percentage">Percentage (%)</option>
                </select>
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-slate-600 mb-1">Discount Value</label>
                <input type="number" x-model="bookingData.discount_value" name="discount_value" step="0.01" min="0" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none">
            </div>
            <div class="flex gap-3 pt-4 border-t border-slate-200">
                <button type="button" @click="closeDiscountModal()" class="flex-1 px-6 py-2 bg-slate-700 text-white rounded-lg hover:bg-slate-800 transition font-medium">Apply</button>
                <button type="button" @click="closeDiscountModal()" class="flex-1 px-6 py-2 border border-slate-300 text-slate-700 rounded-lg hover:bg-slate-50 transition font-medium">Cancel</button>
            </div>
        </div>
    </div>

    {{-- Payment Modal --}}
    <div x-show="paymentModalVisible" x-cloak class="fixed inset-0 z-[60] flex items-center justify-center">
        <div class="fixed inset-0 bg-black/50" @click="closePaymentModal()"></div>
        <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-lg mx-4 p-6 max-h-[90vh] overflow-y-auto">
            <h3 class="text-xl font-semibold text-slate-800">Payment Interface</h3>
            <p class="text-sm text-slate-500 mb-4">Booking Summary</p>

            <div class="mb-4">
                <div class="grid grid-cols-2 gap-3 text-sm">
                    <div class="flex justify-between">
                        <span class="text-slate-500">Total Package Value:</span>
                        <span id="paymentTotalPackageValue" class="text-slate-800 font-medium text-right">0 SAR</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-500">Paid:</span>
                        <span id="paymentPaid" class="text-slate-800 font-medium text-right">0 SAR</span>
                    </div>
                    <div class="flex justify-between col-span-2">
                        <span class="text-slate-600 font-medium">Due:</span>
                        <span id="paymentDue" class="text-slate-800 font-bold text-right">0 SAR</span>
                    </div>
                </div>
            </div>

            <div class="mb-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Currency</label>
                        <select id="paymentCurrency" x-model="paymentData.currency" @change="handlePaymentCurrencyChange()" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none bg-white">
                            <option value="SAR">SAR</option>
                            <option value="BDT">BDT</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Method</label>
                        <select id="paymentMethod" x-model="paymentData.method" @change="handlePaymentMethodChange()" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none bg-white">
                            <option value="Cash">Cash</option>
                            <option value="Bank">Bank</option>
                        </select>
                    </div>

                    <div x-show="paymentData.method === 'Bank'" x-cloak class="col-span-2">
                        <label class="block text-sm font-medium text-slate-700 mb-1">Bank Method</label>
                        <select id="paymentBankMethod" x-model="paymentData.bank_method" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none bg-white">
                            <option value="">Select Bank</option>
                            <option value="AL-Raji">AL-Raji</option>
                            <option value="SNB">SNB</option>
                            <option value="Bkash-BMT">Bkash-BMT</option>
                            <option value="IBBL-BMT">IBBL-BMT</option>
                        </select>
                    </div>

                    <div x-show="paymentData.method === 'Bank'" x-cloak>
                        <label class="block text-sm font-medium text-slate-700 mb-1">TRX ID</label>
                        <input type="text" id="paymentTRXID" x-model="paymentData.trx_id" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none" placeholder="Enter TRX ID">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Amount (SAR)</label>
                        <input type="number" id="paymentAmountSAR" x-model="paymentData.amount_sar" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none" placeholder="Enter SAR amount">
                    </div>

                    <div x-show="paymentData.currency === 'BDT'" x-cloak>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Amount (BDT)</label>
                        <input type="number" id="paymentAmountBDT" x-model="paymentData.amount_bdt" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none" placeholder="Enter BDT amount">
                    </div>
                </div>
            </div>

            <div class="flex gap-3">
                <button type="button" @click="savePayment()" class="flex-1 px-6 py-2 bg-slate-700 text-white rounded-lg hover:bg-slate-800 transition font-medium">Save</button>
                <button type="button" @click="closePaymentModal()" class="flex-1 px-6 py-2 border border-slate-300 text-slate-700 rounded-lg hover:bg-slate-50 transition font-medium">Cancel</button>
            </div>
        </div>
    </div>

    {{-- Customer Add Modal --}}
    <div x-show="customerModalVisible" x-cloak class="fixed inset-0 z-[60] flex items-center justify-center">
        <div class="fixed inset-0 bg-black/50" @click="closeCustomerModal()"></div>
        <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-2xl mx-4 p-6 max-h-[90vh] overflow-y-auto">
            <h3 class="text-xl font-semibold text-slate-800 mb-4">Add New Customer</h3>
            <form @submit.prevent="submitNewCustomer()">
                <div class="grid grid-cols-1 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Name *</label>
                        <input type="text" x-model="newCustomer.name" required class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Passport No. *</label>
                        <input type="text" x-model="newCustomer.passport_no" required class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Iqama No.</label>
                        <input type="text" x-model="newCustomer.iqama_no" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Mobile No. *</label>
                        <input type="text" x-model="newCustomer.mobile_no" required class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none">
                    </div>
                </div>
                <div class="flex gap-3 pt-4 border-t border-slate-200">
                    <button type="submit" class="flex-1 px-6 py-2 bg-slate-700 text-white rounded-lg hover:bg-slate-800 transition font-medium">Add Customer</button>
                    <button type="button" @click="closeCustomerModal()" class="flex-1 px-6 py-2 border border-slate-300 text-slate-700 rounded-lg hover:bg-slate-50 transition font-medium">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
Alpine.data('editBookingApp', () => ({
    // Data from server
    allPackages: window.__bookingServerData.packages || [],
    allTickets: window.__bookingServerData.ticketFares || [],
    isEditMode: window.__bookingServerData.isEditMode || false,
    existingBooking: window.__bookingServerData.existingBooking || null,
    existingPassengers: window.__bookingServerData.existingPassengers || [],
    existingCustomer: window.__bookingServerData.existingCustomer || null,

    // State
    customerSearch: '',
    customerSuggestions: [],
    customerInputFocused: false,
    selectedCustomer: null,
    passengerCount: 0,
    passengers: [],
    filteredTickets: [],

    // Modals
    passengerModalVisible: false,
    discountModalVisible: false,
    paymentModalVisible: false,
    customerModalVisible: false,

    // Form data
    bookingData: {
        fingerprint_location: 'office',
        fingerprint_office: '',
        district_id: '',
        package_id: '',
        fingerprint_charge_id: '',
        remarks: '',
        discount_type: 'fixed',
        discount_value: 0,
    },

    // Payment data
    paymentSaved: false,
    paymentData: {
        currency: 'SAR',
        method: 'Cash',
        bank_method: '',
        trx_id: '',
        amount_sar: '',
        amount_bdt: '',
    },

    // Passenger form
    passengerData: {},
    editingPassengerIndex: null,

    // New customer
    newCustomer: {
        name: '',
        passport_no: '',
        iqama_no: '',
        mobile_no: '',
    },

    // Computed values
    fingerprintCharge: 0,
    grandTotalValue: 0,
    discountedTotal: null,

    init() {
        if (this.isEditMode && this.existingBooking) {
            this.loadExistingBooking();
        }
        this.initDefaults();
    },

    initDefaults() {
        this.filteredTickets = this.allTickets;
    },

    loadExistingBooking() {
        const booking = this.existingBooking;

        // Load customer
        if (this.existingCustomer) {
            this.selectedCustomer = this.existingCustomer;
            this.customerSearch = this.existingCustomer.passport_no || '';
        }

        // Load booking details
        this.bookingData.fingerprint_location = booking.fingerprint_location || 'office';
        this.bookingData.fingerprint_office = booking.office_id ? String(booking.office_id) : '';
        this.bookingData.district_id = booking.district_id ? String(booking.district_id) : '';
        this.bookingData.package_id = booking.package_id ? String(booking.package_id) : '';
        this.bookingData.remarks = booking.remarks || '';
        this.bookingData.discount_type = booking.discount_type === 'percentage' ? 'percentage' : 'fixed';
        this.bookingData.discount_value = parseFloat(booking.discount_value) || 0;

        // Load passengers
        if (this.existingPassengers && this.existingPassengers.length > 0) {
            this.passengers = this.existingPassengers.map(p => ({
                id: p.id,
                first_name: p.first_name || '',
                last_name: p.last_name || '',
                passport_no: p.passport_no || '',
                date_of_birth: p.date_of_birth || '',
                passenger_type: p.passenger_type || '',
                gender: p.gender || '',
                mobile_no: p.mobile_no || '',
                passport_expiry: p.passport_expiry || '',
                service_required: p.service_required || 'all',
                stay_duration: p.stay_duration ? String(p.stay_duration) : '',
                route: p.route || '',
                airline: p.airline || '',
                class: p.class || p.travel_class || '',
                route_type: p.route_type || '',
                flight_type: p.flight_type || '',
                ticket_fare_id: p.ticket_fare_id ? String(p.ticket_fare_id) : '',
                flight_date_from: p.flight_date_from || '',
                flight_date_to: p.flight_date_to || '',
                address: p.address || '',
                baggage_weight: '',
            }));
            this.passengerCount = this.passengers.length;
        }

        // Update fingerprint charge
        this.updateFingerprintCharge();
        this.updateGrandTotal();
    },

    // Customer functions
    searchCustomers() {
        const term = this.customerSearch.toLowerCase().trim();
        if (term.length < 2) {
            this.customerSuggestions = [];
            return;
        }

        // Search in existing customer data
        fetch('/api/customers/search?q=' + encodeURIComponent(term))
            .then(res => res.json())
            .then(data => {
                this.customerSuggestions = data.customers || [];
            })
            .catch(() => {
                // Fallback: filter from server data if available
                this.customerSuggestions = [];
            });
    },

    selectCustomer(customer) {
        this.selectedCustomer = customer;
        this.customerSearch = customer.passport_no || customer.iqama_no || '';
        this.customerSuggestions = [];
        this.customerInputFocused = false;
    },

    clearSelectedCustomer() {
        this.selectedCustomer = null;
        this.customerSearch = '';
    },

    openCustomerModal() {
        this.customerModalVisible = true;
        this.newCustomer = { name: '', passport_no: '', iqama_no: '', mobile_no: '' };
    },

    closeCustomerModal() {
        this.customerModalVisible = false;
    },

    submitNewCustomer() {
        fetch('/customers', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify(this.newCustomer)
        })
        .then(res => res.json())
        .then(data => {
            if (data.id) {
                this.selectCustomer(data);
                this.closeCustomerModal();
            }
        });
    },

    // Package functions
    onPackageChange() {
        this.updateGrandTotal();
    },

    updateFingerprintCharge() {
        const districtId = this.bookingData.district_id;
        const location = this.bookingData.fingerprint_location;

        if (!districtId || location !== 'home') {
            this.fingerprintCharge = 0;
            this.bookingData.fingerprint_charge_id = '';
            this.updateGrandTotal();
            return;
        }

        // Fetch fingerprint charge for district
        fetch('/api/bookings/fingerprint-charge?district_id=' + districtId)
            .then(res => res.json())
            .then(data => {
                this.fingerprintCharge = data.charge || 0;
                this.bookingData.fingerprint_charge_id = data.id || '';
                this.updateGrandTotal();
            })
            .catch(() => {
                this.fingerprintCharge = 0;
                this.updateGrandTotal();
            });
    },

    updateGrandTotal() {
        const selectedPackage = this.allPackages.find(p => String(p.id) === String(this.bookingData.package_id));
        const packageValue = selectedPackage ? (selectedPackage.package_value || 0) : 0;

        this.grandTotalValue = (packageValue * this.passengerCount) + this.fingerprintCharge;
        this.calculateDiscountedTotal();
    },

    calculateDiscountedTotal() {
        if (!this.grandTotalValue) {
            this.discountedTotal = null;
            return;
        }

        if (this.bookingData.discount_type === 'percentage') {
            this.discountedTotal = this.grandTotalValue - (this.grandTotalValue * this.bookingData.discount_value / 100);
        } else {
            this.discountedTotal = this.grandTotalValue - this.bookingData.discount_value;
        }

        this.discountedTotal = Math.max(0, this.discountedTotal);
    },

    // Discount modal
    openDiscountModal() {
        this.discountModalVisible = true;
    },

    closeDiscountModal() {
        this.discountModalVisible = false;
    },

    // Payment modal
    openPaymentModal() {
        this.updatePaymentSummary();
        this.paymentModalVisible = true;
    },

    closePaymentModal() {
        this.paymentModalVisible = false;
    },

    updatePaymentSummary() {
        document.getElementById('paymentTotalPackageValue').textContent = (this.grandTotalValue || 0).toFixed(2) + ' SAR';
        // Would need to fetch actual paid amount
        document.getElementById('paymentPaid').textContent = '0 SAR';
        const due = (this.discountedTotal || this.grandTotalValue || 0);
        document.getElementById('paymentDue').textContent = due.toFixed(2) + ' SAR';
    },

    handlePaymentCurrencyChange() {
        // Currency change handled by Alpine binding
    },

    handlePaymentMethodChange() {
        // Method change handled by Alpine binding
    },

    savePayment() {
        this.paymentSaved = true;
        this.closePaymentModal();
    },

    // Passenger functions
    openPassengerModal() {
        this.passengerData = {
            first_name: '',
            last_name: '',
            passport_no: '',
            date_of_birth: '',
            passenger_type: '',
            gender: '',
            mobile_no: '',
            passport_expiry: '',
            service_required: 'all',
            stay_duration: '',
            route: '',
            airline: '',
            class: '',
            route_type: '',
            flight_type: '',
            ticket_fare_id: '',
            flight_date_range: '',
            baggage_weight: '',
            address: '',
        };
        this.editingPassengerIndex = null;
        this.passengerModalVisible = true;
        this.filterTickets();
    },

    closePassengerModal() {
        this.passengerModalVisible = false;
        this.passengerData = {};
        this.editingPassengerIndex = null;
    },

    editPassenger(index) {
        const passenger = this.passengers[index];
        this.passengerData = { ...passenger };
        this.editingPassengerIndex = index;
        this.passengerModalVisible = true;
        this.filterTickets();

        // Set ticket if exists
        if (passenger.ticket_fare_id) {
            this.onTicketChange();
        }
    },

    savePassenger() {
        if (!this.passengerData.first_name || !this.passengerData.last_name || !this.passengerData.passport_no) {
            alert('Please fill in required fields');
            return;
        }

        // Calculate passenger type from DOB
        if (this.passengerData.date_of_birth) {
            this.calculatePassengerType();
        }

        if (this.editingPassengerIndex !== null) {
            this.passengers[this.editingPassengerIndex] = { ...this.passengerData };
        } else {
            this.passengers.push({ ...this.passengerData });
        }

        this.passengerCount = this.passengers.length;
        this.updateGrandTotal();
        this.closePassengerModal();
    },

    removePassenger(index) {
        if (confirm('Are you sure you want to remove this passenger?')) {
            this.passengers.splice(index, 1);
            this.passengerCount = this.passengers.length;
            this.updateGrandTotal();
        }
    },

    calculatePassengerType() {
        const dob = this.passengerData.date_of_birth;
        if (!dob) {
            this.passengerData.passenger_type = '';
            return;
        }

        const birthDate = new Date(dob);
        const today = new Date();
        const ageInMonths = (today - birthDate) / (1000 * 60 * 60 * 24 * 30.44);

        if (ageInMonths < 24) {
            this.passengerData.passenger_type = 'Infant';
        } else if (ageInMonths < 144) {
            this.passengerData.passenger_type = 'Child';
        } else {
            this.passengerData.passenger_type = 'Adult';
        }

        this.updateBaggageWeight();
    },

    handleStayDurationChange() {
        // Handle custom duration
    },

    filterTickets() {
        const routeType = this.passengerData.route_type;
        const flightType = this.passengerData.flight_type;

        this.filteredTickets = this.allTickets.filter(ticket => {
            if (routeType && ticket.route_type !== routeType.toLowerCase().replace(' ', '_')) return false;
            if (flightType && ticket.flight_type !== flightType) return false;
            return true;
        });
    },

    onTicketChange() {
        const ticketId = this.passengerData.ticket_fare_id;
        const ticket = this.allTickets.find(t => String(t.id) === String(ticketId));

        if (ticket) {
            this.passengerData.route = ticket.route || '';
            this.passengerData.airline = ticket.airline || '';
            this.passengerData.class = ticket.airline_class || '';

            // Update ticket display in select
            this.filterTickets();
        }

        this.updateBaggageWeight();
    },

    getTicketDisplayText(ticket) {
        return `${ticket.route} - ${ticket.airline} (${ticket.airline_class || 'Economy'}) - ${ticket.selling_fare} SAR`;
    },

    updateBaggageWeight() {
        // Baggage logic - simplified
        if (!this.passengerData.route_type || !this.passengerData.flight_type) {
            this.passengerData.baggage_weight = 'Select Route and Flight Type';
            return;
        }

        this.passengerData.baggage_weight = '20 KG';
    },

    recalculateCurrentPassenger(index) {
        // Recalculate passenger value
    },

    // Form submission
    submitForm(event) {
        if (this.passengers.length === 0) {
            event.preventDefault();
            alert('Please add at least one passenger');
            return;
        }

        if (!this.selectedCustomer) {
            event.preventDefault();
            alert('Please select a customer');
            return;
        }

        // Form will submit normally with PUT method
    },

    clearForm() {
        if (confirm('Are you sure you want to clear the form?')) {
            this.selectedCustomer = null;
            this.customerSearch = '';
            this.passengers = [];
            this.passengerCount = 0;
            this.bookingData = {
                fingerprint_location: 'office',
                fingerprint_office: '',
                district_id: '',
                package_id: '',
                fingerprint_charge_id: '',
                remarks: '',
                discount_type: 'fixed',
                discount_value: 0,
            };
            this.fingerprintCharge = 0;
            this.updateGrandTotal();
        }
    },
}));

// Document upload handlers
function handleBookingCustomerDocsUpload(input) {
    const list = document.getElementById('booking_customer_docs_list');
    for (let file of input.files) {
        const div = document.createElement('div');
        div.className = 'flex items-center justify-between bg-slate-50 rounded px-3 py-2';
        div.innerHTML = '<span class="text-sm text-slate-700 truncate">' + file.name + '</span>';
        list.appendChild(div);
    }
}

function handlePassengerDocUpload(input) {
    const list = document.getElementById('passenger_doc_list');
    for (let file of input.files) {
        const div = document.createElement('div');
        div.className = 'flex items-center justify-between bg-slate-50 rounded px-3 py-2';
        div.innerHTML = '<span class="text-sm text-slate-700 truncate">' + file.name + '</span>';
        list.appendChild(div);
    }
}

function removeExistingDoc(docId) {
    if (confirm('Are you sure you want to delete this document?')) {
        fetch('/documents/' + docId, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            }
        });
    }
}
</script>
@endpush

@endsection