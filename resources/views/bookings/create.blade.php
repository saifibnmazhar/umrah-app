@extends('layouts.app')
@section('title', 'Create Booking')
@section('content')
<script>window.__bookingServerData = { 
    ticketFares: @json($ticketFares ?? []), 
    packages: @json($packages ?? []), 
    preSelectedPackageId: {{ $preSelectedPackageId ?? 'null' }},
    currentCurrencyRate: {{ $currentCurrencyRate?->rate ?? 0 }}
};</script>
<div class="max-w-5xl mx-auto" x-data="createBookingApp()" x-init="init()">
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
            <input type="hidden" name="payment[amount]" :value="paymentSaved ? (parseFloat(paymentData.amount_sar) || 0) : 0">
            <input type="hidden" name="payment[bdt_amount]" :value="paymentSaved ? (parseFloat(paymentData.amount_bdt) || 0) : 0">
            <input type="hidden" name="payment[currency]" :value="paymentSaved ? paymentData.currency : 'SAR'">
            <input type="hidden" name="payment[payment_method]" :value="paymentSaved && paymentData.method === 'cash' ? 'cash' : 'bank'">
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
                        @foreach(\App\Models\District::orderBy('name')->get() as $district)
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
                <div id="booking_customer_docs_list" class="mt-2 space-y-1"></div>
            </div>

            <div class="mb-6" x-show="passengers.length === 0">
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
                                        <div><span class="text-slate-500">Service:</span> <span class="text-slate-700 ml-1" x-text="serviceLabel(passenger.service_required)"></span></div>
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
                <button type="submit"
                    class="w-64 px-8 py-3 bg-slate-700 text-white rounded-lg hover:bg-slate-800 transition font-medium"
                    :disabled="(!paymentSaved || (parseFloat(paymentData.amount_sar) || 0) <= 0)"
                    :class="(!paymentSaved || (parseFloat(paymentData.amount_sar) || 0) <= 0) ? 'opacity-50 cursor-not-allowed' : ''"
                    :title="(!paymentSaved || (parseFloat(paymentData.amount_sar) || 0) <= 0) ? 'Please save payment first' : ''"
                >Submit</button>
                <button type="button" @click="clearForm()" class="px-6 py-3 border border-slate-300 text-slate-600 rounded-lg hover:bg-slate-50 transition font-medium">Clear</button>
                <a href="{{ route('bookings.index') }}" class="px-6 py-3 border border-slate-300 text-slate-600 rounded-lg hover:bg-slate-50 transition font-medium">Cancel</a>
            </div>
        </form>
    </div>

    @include('partials.passenger-form-modal')

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
                            <option value="cash">Cash</option>
                            <option value="bank">Bank</option>
                        </select>
                    </div>
                    
                    <div x-show="paymentData.method === 'bank'" x-cloak class="col-span-2">
                        <label class="block text-sm font-medium text-slate-700 mb-1">Bank Method</label>
                        <select id="paymentBankMethod" x-model="paymentData.bank_method" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none bg-white">
                            <option value="">Select Bank</option>
                            <option value="AL-Raji">AL-Raji</option>
                            <option value="SNB">SNB</option>
                            <option value="Bkash-BMT">Bkash-BMT</option>
                            <option value="IBBL-BMT">IBBL-BMT</option>
                        </select>
                    </div>
                    
                    <div x-show="paymentData.method === 'bank'" x-cloak>
                        <label class="block text-sm font-medium text-slate-700 mb-1">TRX ID</label>
                        <input type="text" id="paymentTRXID" x-model="paymentData.trx_id" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none" placeholder="Enter TRX ID">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Amount (SAR)</label>
                        <input type="number" id="paymentAmountSAR" x-model="paymentData.amount_sar" :disabled="paymentData.currency === 'BDT'" @input="handleSarAmountInput()" :max="paymentMaxAmount" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none" :class="{'bg-slate-100 cursor-not-allowed': paymentData.currency === 'BDT'}" placeholder="Enter SAR amount">
                    </div>
                    
                    <div x-show="paymentData.currency === 'BDT'" x-cloak>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Amount (BDT)</label>
                        <input type="number" id="paymentAmountBDT" x-model="paymentData.amount_bdt" @input="handleBdtAmountInput()" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none" placeholder="Enter BDT amount">
                    </div>
                    
                    <div x-show="paymentData.currency === 'BDT'" class="col-span-2 mt-2">
                        <template x-if="exchangeRate > 0">
                            <p class="text-sm text-slate-500">1 SAR = <span x-text="exchangeRate"></span> BDT</p>
                        </template>
                        <template x-if="exchangeRate <= 0">
                            <p class="text-sm text-red-500">Exchange rate not available. Cannot process BDT payment.</p>
                        </template>
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