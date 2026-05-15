@extends('layouts.app')
@section('title', 'Create Booking')
@section('content')
<script>window.__bookingServerData = { ticketFares: @json($ticketFares ?? []), packages: @json($packages ?? []), preSelectedPackageId: {{ $preSelectedPackageId ?? 'null' }} };</script>
<div class="max-w-5xl mx-auto" x-data="createBookingApp()">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-slate-800">Create Booking</h1>
        <a href="{{ route('bookings.index') }}" class="px-6 py-3 bg-slate-700 text-white rounded-lg hover:bg-slate-800 transition font-medium flex items-center gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 17l-5-5m5 5l5-5m-5 5h14" />
            </svg>
            Back to Bookings
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
        <form method="POST" action="{{ route('bookings.store') }}" @submit="submitForm($event)">
            @csrf
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
                    <select x-model="bookingData.fingerprint_office" name="fingerprint_office" @change="$el.blur()" class="w-full px-4 py-3 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none transition bg-white">
                        <option value="">Select Office</option>
                        @foreach(\App\Models\Office::orderBy('name')->get() as $office)
                        <option value="{{ $office->name }}">{{ $office->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-2">District *</label>
                    <select x-model="bookingData.district_id" @change="updateFingerprintCharge(); $el.blur()" name="district_id" class="w-full px-4 py-3 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none transition bg-white">
                        <option value="">Select District</option>
                        @foreach(\App\Models\District::orderBy('name')->get() as $district)
                        <option value="{{ $district->id }}">{{ $district->name }}</option>
                        @endforeach
                    </select>
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
                <div id="booking_customer_docs_list" class="mt-2 space-y-1"></div>
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
                    <span class="w-1/6 text-center">Value</span>
                </div>
                <div class="flex justify-between font-medium text-slate-800">
                    <span id="summaryPackage" class="w-1/6 text-center">-</span>
                    <span class="w-1/6 text-center" x-text="fingerprintCharge > 0 ? fingerprintCharge + ' SAR' : '-'">-</span>
                    <span class="w-1/6 text-center" x-text="passengerCount">0</span>
                    <span class="w-1/6 text-center" x-text="bookingData.discount_value > 0 ? '-' + bookingData.discount_value + (bookingData.discount_type === 'percentage' ? '%' : ' SAR') : '-'">-</span>
<span id="summaryTotalBeforeDiscount" class="w-1/6 text-center" x-text="(grandTotalValue ?? 0).toFixed(2) + ' SAR'">0 SAR</span>
                    <span id="summaryTotalValue" class="w-1/6 text-center" x-text="(totalPackageValue ?? 0).toFixed(2) + ' SAR'">0 SAR</span>
                </div>
            </div>

            <div class="flex justify-end gap-3 pt-6 border-t border-slate-200">
                <button type="submit" class="w-64 px-8 py-3 bg-slate-700 text-white rounded-lg hover:bg-slate-800 transition font-medium" :disabled="passengers.length === 0">Submit</button>
                <button type="button" @click="clearForm()" class="px-6 py-3 border border-slate-300 text-slate-600 rounded-lg hover:bg-slate-50 transition font-medium">Clear</button>
                <a href="{{ route('bookings.index') }}" class="px-6 py-3 border border-slate-300 text-slate-600 rounded-lg hover:bg-slate-50 transition font-medium">Cancel</a>
            </div>
        </form>
    </div>

    <div x-show="passengerModalVisible" x-cloak class="fixed inset-0 z-[60] flex items-center justify-center">
        <div class="fixed inset-0 bg-black/50" @click="closePassengerModal()"></div>
        <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-2xl mx-4 p-6 max-h-[90vh] overflow-y-auto">
            <h3 class="text-xl font-semibold text-slate-800 mb-4">Add Passenger</h3>
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
                                       :class="{
                                           'bg-green-50 border-green-300 text-green-700': passengerData.passenger_type === 'Infant',
                                           'bg-blue-50 border-blue-300 text-blue-700': passengerData.passenger_type === 'Child',
                                           'bg-slate-50 border-slate-200 text-slate-600': !passengerData.passenger_type || passengerData.passenger_type === 'Adult'
                                       }"
                                       placeholder="Enter DOB to auto-calculate">
                            </div>
                            <p x-show="passengerData.date_of_birth && !passengerData.passenger_type" class="text-xs text-slate-400 mt-1">Calculating...</p>
                            <p x-show="passengerData.passenger_type && !passengerData.stay_duration" class="text-xs text-slate-500 mt-1">Auto-filled based on date of birth</p>
                            <p x-show="passengerData.passenger_type && passengerData.stay_duration" class="text-xs text-slate-500 mt-1">Auto-filled based on date of birth & stay duration</p>
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
                                <option value="All">All</option>
                                <option value="Visa Only">Visa Only</option>
                                <option value="Ticket Only">Ticket Only</option>
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
                            <input type="text"
                                   x-model="passengerData.baggage_weight"
                                   readonly
                                   class="w-full px-4 py-2 border border-slate-200 rounded-lg bg-slate-50 text-slate-600 font-medium"
                                   :class="{
                                       'bg-yellow-50 border-yellow-300 text-yellow-700': passengerData.baggage_weight && !passengerData.baggage_weight.includes('Select') && !passengerData.baggage_weight.includes('Define') && !passengerData.baggage_weight.includes('No baggage') && !passengerData.baggage_weight.includes('Route Type'),
                                       'bg-red-50 border-red-200 text-red-500': passengerData.baggage_weight === 'No baggage allowance defined',
                                       'bg-blue-50 border-blue-200 text-blue-600': passengerData.baggage_weight.includes('Select') || passengerData.baggage_weight.includes('Define') || passengerData.baggage_weight.includes('Route Type')
                                   }"
                                   placeholder="Baggage allowance will appear here">
                            <p x-show="!passengerData.baggage_weight || passengerData.baggage_weight.includes('Select') || passengerData.baggage_weight.includes('Define') || passengerData.baggage_weight.includes('Route Type')" class="text-xs text-slate-400 mt-1">Select ticket, route type and define passenger type to see baggage</p>
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

                {{--
                <div class="mb-4">
                    <h4 class="text-sm font-medium text-slate-600 mb-3 pb-2 border-b border-slate-200">Ticket Options</h4>
                    <div class="flex flex-wrap gap-6">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" x-model="passengerData.with_offer" class="w-4 h-4 text-slate-600 border-slate-300 rounded focus:ring-slate-400">
                            <span class="text-sm text-slate-700">With Offer</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" x-model="passengerData.refundable" class="w-4 h-4 text-slate-600 border-slate-300 rounded focus:ring-slate-400">
                            <span class="text-sm text-slate-700">Refundable</span>
                        </label>
                    </div>
                </div>
                --}}

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

    <div x-show="discountModalVisible" x-cloak class="fixed inset-0 z-[60] flex items-center justify-center">
        <div class="fixed inset-0 bg-black/50" @click="closeDiscountModal()"></div>
        <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-md mx-4 p-6">
            <h3 class="text-xl font-semibold text-slate-800 mb-4">Apply Discount</h3>
            <div class="mb-4">
                <label class="block text-sm font-medium text-slate-600 mb-1">Discount Type</label>
                <select x-model="bookingData.discount_type" id="discountType" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none bg-white">
                    <option value="fixed">Fixed (SAR)</option>
                    <option value="percentage">Percentage (%)</option>
                </select>
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-slate-600 mb-1">Discount Value</label>
                <input type="number" x-model="bookingData.discount_value" step="0.01" min="0" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none">
            </div>
            <div class="flex gap-3 pt-4 border-t border-slate-200">
                <button type="button" @click="closeDiscountModal()" class="flex-1 px-6 py-2 bg-slate-700 text-white rounded-lg hover:bg-slate-800 transition font-medium">Apply</button>
                <button type="button" @click="closeDiscountModal()" class="flex-1 px-6 py-2 border border-slate-300 text-slate-700 rounded-lg hover:bg-slate-50 transition font-medium">Cancel</button>
            </div>
        </div>
    </div>

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
                        <label class="block text-sm font-medium text-slate-600 mb-1">Mobile No. *</label>
                        <input type="text" x-model="newCustomer.mobile_no" required class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Address</label>
                        <input type="text" x-model="newCustomer.address" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Iqama Type</label>
                        <select x-model="newCustomer.iqama_type" required class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none bg-white">
                            <option value="">Select</option>
                            <option value="none">None</option>
                            <option value="self">Self</option>
                            <option value="referral">Referral</option>
                        </select>
                    </div>
                    <div x-show="newCustomer.iqama_type === 'referral'">
                        <label class="block text-sm font-medium text-slate-600 mb-1">Referral Iqama No.</label>
                        <input type="text" x-model="newCustomer.ref_iqama_no" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none">
                    </div>
                    <div x-show="newCustomer.iqama_type === 'referral'">
                        <label class="block text-sm font-medium text-slate-600 mb-1">Referral Mobile No.</label>
                        <input type="text" x-model="newCustomer.ref_mobile_no" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none">
                    </div>
                    <div x-show="newCustomer.iqama_type === 'referral'">
                        <label class="block text-sm font-medium text-slate-600 mb-1">Upload Ref. Iqama *</label>
                        <div class="border-2 border-dashed border-slate-300 rounded-lg p-4 text-center hover:bg-slate-50 transition cursor-pointer" onclick="document.getElementById('ref_iqama_doc').click()">
                            <input type="file" id="ref_iqama_doc" name="ref_iqama_doc" class="hidden" accept=".jpg,.jpeg,.png,.pdf" onchange="handleRefIqamaFileUpload(this)">
                            <div class="text-slate-500">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 mx-auto mb-2 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                                </svg>
                                <span id="ref_iqama_doc_filename">click to upload</span>
                            </div>
                        </div>
                    </div>
                    <div x-show="newCustomer.iqama_type !== 'none'&& newCustomer.iqama_type !== ''">
                        <label class="block text-sm font-medium text-slate-600 mb-1">Iqama No.</label>
                        <input type="text" x-model="newCustomer.iqama_no" x-show="newCustomer.iqama_type !== 'none' && newCustomer.iqama_type !== ''" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Customer Docs</label>
                        <div class="border-2 border-dashed border-slate-300 rounded-lg p-4 text-center hover:bg-slate-50 transition cursor-pointer" onclick="document.getElementById('customer_docs').click()">
                            <input type="file" id="customer_docs" name="customer_docs[]" class="hidden" accept=".jpg,.jpeg,.png,.pdf" multiple onchange="handleCustomerDocUpload(this)">
                            <div class="text-slate-500">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 mx-auto mb-2 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                                </svg>
                                <span>click to upload</span>
                            </div>
                        </div>
                        <div id="customer_docs_list" class="mt-2 space-y-1"></div>
                    </div>
                </div>
                <div class="flex gap-3 pt-4 border-t border-slate-200">
                    <button type="submit" class="flex-1 px-6 py-2 bg-slate-700 text-white rounded-lg hover:bg-slate-800 transition font-medium">Save Customer</button>
                    <button type="button" @click="closeCustomerModal()" class="flex-1 px-6 py-2 border border-slate-300 text-slate-700 rounded-lg hover:bg-slate-50 transition font-medium">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <div x-show="customDurationModalVisible" x-cloak class="fixed inset-0 z-[60] flex items-center justify-center" @keydown.escape="closeCustomDurationModal()">
        <div class="fixed inset-0 bg-black/50" @click="closeCustomDurationModal()"></div>
        <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-sm mx-4 p-6">
            <h3 class="text-xl font-semibold text-slate-800 mb-4">Set Custom Duration</h3>
            <div class="mb-4">
                <label class="block text-sm font-medium text-slate-700 mb-1">Duration (days)</label>
                <input type="number" id="customDurationDays" x-model="passengerData.customDurationDays" min="30" max="89" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 outline-none" placeholder="Enter days (30-89)">
                <p class="text-xs text-slate-500 mt-1">Enter a value between 30 and 89 days</p>
            </div>
            <div class="flex gap-3">
                <button type="button" @click="saveCustomDuration()" class="flex-1 px-6 py-2 bg-slate-700 text-white rounded-lg hover:bg-slate-800 transition font-medium">Save</button>
                <button type="button" @click="closeCustomDurationModal()" class="flex-1 px-6 py-2 border border-slate-300 text-slate-700 rounded-lg hover:bg-slate-50 transition font-medium">Cancel</button>
            </div>
        </div>
    </div>
</div>
@endsection