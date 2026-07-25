@extends('layouts.app')
@section('title', 'Edit Passenger')
@section('content')
<script>window.__bookingServerData = {
    passenger: @json($passenger->toArray()),
    ticketFares: @json($ticketFares ?? []),
    packages: @json($packages ?? []),
    isEditMode: true,
    preSelectedPackageId: {{ $passenger->booking->package_id ?? 'null' }},
    updateRoute: '{{ route('passengers.update', $passenger->id) }}',
    showRoute: '{{ route('passengers.show', $passenger->id) }}',
    currentCurrencyRate: {{ $rate }}
};</script>
<script>window.__currencyRate = {{ $rate }};</script>
<div class="max-w-3xl mx-auto" x-data="editPassengerApp()" x-init="init()">
    <div class="bg-white rounded-xl shadow-lg p-6">
        <div class="flex justify-between items-start mb-6 pb-4 border-b border-slate-200">
            <div>
                <h2 class="text-xl font-semibold text-slate-800">
                    Edit Passenger: {{ trim($passenger->first_name . ' ' . $passenger->last_name) }}
                </h2>
                <p class="text-slate-500 text-sm mt-1">Invoice: <span>{{ $passenger->booking?->invoice?->id ?? '-' }}</span></p>
            </div>
            <a href="{{ route('passengers.show', $passenger->id) }}" class="text-slate-400 hover:text-slate-600">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </a>
        </div>

        @if(session('error'))
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            {{ session('error') }}
        </div>
        @endif

        <form @submit.prevent="savePassenger()">
            <div class="mb-6">
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
                        <div class="flex w-full border border-slate-300 rounded-lg focus-within:ring-2 focus-within:ring-slate-400 focus-within:border-slate-400 overflow-hidden">
                            <span class="flex items-center px-3 py-2 text-slate-600 bg-slate-100 border-r border-slate-300 text-sm font-medium select-none shrink-0">+880</span>
                            <input type="tel" x-model="passengerData.mobile_no" class="w-full px-4 py-2 outline-none border-0" placeholder="1833045104">
                        </div>
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Date of Birth *</label>
                        <input type="date" x-model="passengerData.date_of_birth" @change="calculatePassengerType()" required class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none">
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
                        <p x-show="passengerData.passenger_type" class="text-xs text-slate-500 mt-1">Auto-filled based on date of birth</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Passport Expiry Date</label>
                        <input type="date" x-model="passengerData.passport_expiry" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none">
                    </div>
                </div>
            </div>

            <div class="mb-6">
                <h4 class="text-sm font-medium text-slate-600 mb-3 pb-2 border-b border-slate-200">Service Information</h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Stay Duration *</label>
                        <select x-model="passengerData.stay_duration" @change="handleStayDurationChange()" required class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none bg-white">
                            <option value="">Select Stay Duration</option>
                            <option value="14">Group (14 Days)</option>
                            {{-- <option value="85">Family (85 Days)</option> --}}
                            <option value="Customize (Set Duration)">Customize (Set Duration)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Service Required *</label>
                        <select x-model="passengerData.service_required" @change="onServiceRequiredChange()" required class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none bg-white">
                            <option value="">Select Service</option>
                            <option value="all">Visa + Ticket</option>
                            <option value="visa_only">Visa Only</option>
                            <option value="ticket_only">Ticket Only</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="mb-6">
                <h4 class="text-sm font-medium text-slate-600 mb-3 pb-2 border-b border-slate-200">Travel Details</h4>

                {{-- Single Ticket Section --}}
                <div x-show="!isDoubleTicket">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Route Type *</label>
                            <select x-model="passengerData.route_type" disabled class="w-full px-4 py-2 border border-slate-200 rounded-lg bg-slate-50 text-slate-600 cursor-not-allowed">
                                <option value="">Select</option>
                                <option value="One Way-Inbound">One Way-Inbound</option>
                                <option value="One Way-Outbound">One Way-Outbound</option>
                                <option value="Round">Round</option>
                                <option value="Multi City">Multi City</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Flight Type *</label>
                            <select x-model="passengerData.flight_type" disabled class="w-full px-4 py-2 border border-slate-200 rounded-lg bg-slate-50 text-slate-600 cursor-not-allowed">
                                <option value="">Select</option>
                                <option value="Transit">Transit</option>
                                <option value="Direct">Direct</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Ticket *</label>
                            <select x-model="passengerData.ticket_fare_id" disabled class="w-full px-4 py-2 border border-slate-200 rounded-lg bg-slate-50 text-slate-600 cursor-not-allowed">
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
                            <select id="passengerFlightDateRange" x-model="passengerData.flight_date_range" @change="onFlightDateRangeChange()" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none bg-white">
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
                                   }">
                        </div>
                    </div>
                </div>

                {{-- Double Ticket Section --}}
                <div x-show="isDoubleTicket">
                    <h5 class="text-sm font-semibold text-slate-700 mb-2">Inbound Travel</h5>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Inbound Route</label>
                            <input type="text" x-model="passengerData.inbound_route" disabled class="w-full px-4 py-2 border border-slate-200 rounded-lg bg-slate-50 text-slate-600 cursor-not-allowed" placeholder="Inbound Route">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Inbound Airline</label>
                            <input type="text" x-model="passengerData.inbound_airline" disabled class="w-full px-4 py-2 border border-slate-200 rounded-lg bg-slate-50 text-slate-600 cursor-not-allowed" placeholder="Inbound Airline">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Inbound Class</label>
                            <input type="text" x-model="passengerData.inbound_class" disabled class="w-full px-4 py-2 border border-slate-200 rounded-lg bg-slate-50 text-slate-600 cursor-not-allowed" placeholder="Inbound Class">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Flight Date Range *</label>
                            <select id="passengerFlightDateRangeDouble" x-model="passengerData.flight_date_range" @change="onFlightDateRangeChange()" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none bg-white">
                                <option value="">Select Date Range</option>
                            </select>
                        </div>
                    </div>

                    <h5 class="text-sm font-semibold text-slate-700 mb-2 mt-4">Outbound Travel</h5>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Outbound Route</label>
                            <input type="text" x-model="passengerData.outbound_route" disabled class="w-full px-4 py-2 border border-slate-200 rounded-lg bg-slate-50 text-slate-600 cursor-not-allowed" placeholder="Outbound Route">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Outbound Airline</label>
                            <input type="text" x-model="passengerData.outbound_airline" disabled class="w-full px-4 py-2 border border-slate-200 rounded-lg bg-slate-50 text-slate-600 cursor-not-allowed" placeholder="Outbound Airline">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Outbound Class</label>
                            <input type="text" x-model="passengerData.outbound_class" disabled class="w-full px-4 py-2 border border-slate-200 rounded-lg bg-slate-50 text-slate-600 cursor-not-allowed" placeholder="Outbound Class">
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
                                   }">
                        </div>
                    </div>
                </div>
            </div>

            <div class="mb-6">
                <h4 class="text-sm font-medium text-slate-600 mb-3 pb-2 border-b border-slate-200">Location</h4>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Detailed Address (BD) <span class="text-red-500">*</span></label>
                    <input type="text" x-model="passengerData.address" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none" placeholder="Full address">
                </div>
            </div>

            <div class="flex gap-3 pt-4 border-t border-slate-200">
                <button type="submit" class="flex-1 px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition font-medium flex items-center justify-center gap-1.5">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    Save Changes
                </button>
                <a href="{{ route('passengers.show', $passenger->id) }}" class="flex-1 px-6 py-2 border border-slate-300 text-slate-700 rounded-lg hover:bg-slate-50 transition font-medium text-center">
                    Cancel
                </a>
            </div>
        </form>
    </div>

    {{-- Custom Duration Modal --}}
    <div x-show="customDurationModalVisible" x-cloak class="fixed inset-0 z-[60] flex items-center justify-center">
        <div class="fixed inset-0 bg-black/50" @click="closeCustomDurationModal()"></div>
        <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-md mx-4 p-6">
            <h3 class="text-xl font-semibold text-slate-800 mb-4">Customize Stay Duration</h3>
            <div class="mb-4">
                <label class="block text-sm font-medium text-slate-700 mb-1" x-text="`Number of Days (${window.__stayDurationLimits?.minDays ?? 1}-${window.__stayDurationLimits?.maxDays ?? 85})`">Number of Days</label>
                <input type="number" id="customDurationDays" x-model="passengerData.customDurationDays" :min="window.__stayDurationLimits?.minDays ?? 1" :max="window.__stayDurationLimits?.maxDays ?? 85" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none" :placeholder="`Enter days (${window.__stayDurationLimits?.minDays ?? 1}-${window.__stayDurationLimits?.maxDays ?? 85})`">
            </div>
            <div class="flex gap-3">
                <button type="button" @click="saveCustomDuration()" class="flex-1 px-6 py-2 bg-slate-700 text-white rounded-lg hover:bg-slate-800 transition font-medium">Save</button>
                <button type="button" @click="closeCustomDurationModal()" class="flex-1 px-6 py-2 border border-slate-300 text-slate-700 rounded-lg hover:bg-slate-50 transition font-medium">Cancel</button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
(function() {
    function registerEditPassengerApp() {
        Alpine.data('editPassengerApp', () => ({
            passengerData: {
                first_name: '',
                last_name: '',
                passport_no: '',
                date_of_birth: '',
                passenger_type: '',
                gender: '',
                mobile_no: '',
                passport_expiry: '',
                service_required: '',
                stay_duration: '',
                stay_duration_int: 0,
                stay_duration_display: '',
                route_type: '',
                flight_type: '',
                route: '',
                airline: '',
                class: '',
                ticket_fare_id: '',
                ticket_fare_inbound_id: '',
                ticket_fare_outbound_id: '',
                inbound_route: '',
                inbound_airline: '',
                inbound_class: '',
                outbound_route: '',
                outbound_airline: '',
                outbound_class: '',
                flight_date_range: '',
                flight_date_from: '',
                flight_date_to: '',
                baggage_weight: '',
                address: '',
                customDurationDays: ''
            },
            get isDoubleTicket() {
                return !!(this.passengerData.ticket_fare_inbound_id && this.passengerData.ticket_fare_outbound_id);
            },
        allTickets: [],
        filteredTickets: [],
        packages: [],
        allPackages: [],
        customDurationModalVisible: false,
        passenger: null,

        init() {
            const serverData = window.__bookingServerData || {};
            this.passenger = serverData.passenger || {};
            this.allTickets = serverData.ticketFares || [];
            this.filteredTickets = this.allTickets;
            this.packages = serverData.packages || [];
            this.allPackages = this.packages.map(p => ({
                ...p,
                id: String(p.id),
                ticket_fare_id: p.ticket_fare_id ? String(p.ticket_fare_id) : null,
            }));

            this.$nextTick(() => {
                this.loadPassengerData();
            });
        },

        loadPassengerData() {
            const p = this.passenger;
            if (!p) return;

            this.passengerData.first_name = p.first_name || '';
            this.passengerData.last_name = p.last_name || '';
            this.passengerData.passport_no = p.passport_no || '';
            this.passengerData.date_of_birth = this.toDateValue(p.date_of_birth);
            this.passengerData.mobile_no = p.mobile_no || '';
            this.passengerData.passport_expiry = this.toDateValue(p.passport_expiry);
            this.passengerData.gender = p.gender || '';

            const rawType = p.passenger_type || '';
            const typeMap = { 'adult': 'Adult', 'child': 'Child', 'infant': 'Infant' };
            this.passengerData.passenger_type = typeMap[rawType] || rawType;

            this.passengerData.service_required = p.service_required || '';

            const stayDuration = p.stay_duration;
            if (stayDuration !== null && stayDuration !== undefined && stayDuration !== '') {
                const sd = parseInt(stayDuration);
                if (!isNaN(sd)) {
                    if (sd === 14 || sd === 85) {
                        this.passengerData.stay_duration = String(sd);
                        this.passengerData.stay_duration_int = sd;
                    } else if (sd >= 1) {
                        this.passengerData.stay_duration_display = `Customized (${sd} Days)`;
                        this.passengerData.stay_duration_int = sd;
                        this.$nextTick(() => {
                            const select = document.querySelector('select[x-model="passengerData.stay_duration"]');
                            if (select) {
                                let customOption = Array.from(select.options).find(opt => opt.value.startsWith('Customized'));
                                if (!customOption) {
                                    customOption = document.createElement('option');
                                    select.appendChild(customOption);
                                }
                                customOption.value = `Customized (${sd} Days)`;
                                customOption.textContent = `Customized (${sd} Days)`;
                                select.value = `Customized (${sd} Days)`;
                            }
                        });
                    }
                }
            }

            this.passengerData.address = p.address || '';

            const inboundId = p.ticket_fare_inbound_id;
            const outboundId = p.ticket_fare_outbound_id;

            if (inboundId && outboundId) {
                this.passengerData.ticket_fare_inbound_id = String(inboundId);
                this.passengerData.ticket_fare_outbound_id = String(outboundId);

                const inboundTicket = this.allTickets.find(t => t.id == inboundId);
                const outboundTicket = this.allTickets.find(t => t.id == outboundId);

                if (inboundTicket) {
                    this.passengerData.inbound_route = inboundTicket.route || '';
                    this.passengerData.inbound_airline = inboundTicket.airline || '';
                    this.passengerData.inbound_class = inboundTicket.airline_class || '';
                }
                if (outboundTicket) {
                    this.passengerData.outbound_route = outboundTicket.route || '';
                    this.passengerData.outbound_airline = outboundTicket.airline || '';
                    this.passengerData.outbound_class = outboundTicket.airline_class || '';
                }

                if (p.flight_date_from || p.flight_date_to) {
                    this.passengerData.flight_date_from = this.toDateValue(p.flight_date_from);
                    this.passengerData.flight_date_to = this.toDateValue(p.flight_date_to);
                    this.generateDoubleTicketFlightDateRange(this.passengerData.flight_date_from, this.passengerData.flight_date_to);
                }
            } else {
                const ticketFareId = p.ticket_fare_id;
                if (ticketFareId) {
                    this.passengerData.ticket_fare_id = String(ticketFareId);

                    const ticket = this.allTickets.find(t => t.id == ticketFareId);
                    if (ticket) {
                        const reverseRouteTypeMap = {
                            'oneway_inbound': 'One Way-Inbound',
                            'oneway_outbound': 'One Way-Outbound',
                            'round': 'Round',
                            'multi_city': 'Multi City',
                        };
                        const reverseFlightTypeMap = {
                            'transit': 'Transit',
                            'direct': 'Direct',
                        };

                        this.passengerData.route_type = reverseRouteTypeMap[ticket.route_type] || '';
                        this.passengerData.flight_type = reverseFlightTypeMap[ticket.flight_type] || '';

                        this.filteredTickets = this.allTickets.filter(t2 =>
                            t2.route_type === ticket.route_type &&
                            t2.flight_type === ticket.flight_type
                        );

                        this.passengerData.route = ticket.route || '';
                        this.passengerData.airline = ticket.airline || '';
                        this.passengerData.class = ticket.airline_class || '';
                    } else {
                        this.filteredTickets = this.allTickets;
                    }

                    this.calculateFlightDateRange();
                } else {
                    this.filteredTickets = this.allTickets;
                    this.populateFlightDateRangeOptions([]);
                }

                if (p.flight_date_from || p.flight_date_to) {
                    this.passengerData.flight_date_from = this.toDateValue(p.flight_date_from);
                    this.passengerData.flight_date_to = this.toDateValue(p.flight_date_to);
                }
            }

            this.updateBaggageWeight();
        },

        toDateValue(value) {
            if (!value) return '';
            if (typeof value === 'string') {
                return value.split(' ')[0].split('T')[0];
            }
            return '';
        },

        parseStayDurationDays(stayDuration) {
            if (!stayDuration) return null;
            if (/^\d+$/.test(stayDuration)) {
                return parseInt(stayDuration, 10);
            }
            const match = stayDuration.match(/(\d+)\s*days?/i);
            return match ? parseInt(match[1], 10) : null;
        },

        getStayDurationValue() {
            return this.parseStayDurationDays(this.passengerData.stay_duration);
        },

        calculatePassengerType() {
            const dob = this.passengerData.date_of_birth;
            if (!dob) {
                this.passengerData.passenger_type = '';
                return;
            }
            const dobDate = new Date(dob);
            if (isNaN(dobDate.getTime())) {
                this.passengerData.passenger_type = '';
                return;
            }
            const today = new Date();
            let ageInMonths = (today.getFullYear() - dobDate.getFullYear()) * 12 + (today.getMonth() - dobDate.getMonth());
            const dobDay = dobDate.getDate();
            const todayDay = today.getDate();
            if (todayDay < dobDay) {
                ageInMonths -= 1;
            }

            // // Stay duration adjustment (no longer applied)
            // const stayDays = this.parseStayDurationDays(this.passengerData.stay_duration);
            // if (stayDays !== null) {
            //     const adjustmentDays = stayDays < 30 ? 30 : 90;
            //     const effectiveDate = new Date(dobDate);
            //     effectiveDate.setDate(effectiveDate.getDate() - adjustmentDays);
            //     const ageInMonthsWithDuration = (today.getFullYear() - effectiveDate.getFullYear()) * 12 + (today.getMonth() - effectiveDate.getMonth());
            //     const dayDiff = today.getDate() - effectiveDate.getDate();
            //     const finalAgeInMonths = dayDiff < 0 ? ageInMonthsWithDuration - 1 : ageInMonthsWithDuration;
            //     ageInMonths = Math.max(ageInMonths, finalAgeInMonths);
            // }

            let calculatedType = 'Adult';
            if (ageInMonths < 19) {
                calculatedType = 'Infant';
            } else if (ageInMonths < 139) {
                calculatedType = 'Child';
            }

            this.passengerData.passenger_type = calculatedType;
            this.updateBaggageWeight();
        },

        updateBaggageWeight() {
            const passengerType = this.passengerData.passenger_type;
            const inboundId = this.passengerData.ticket_fare_inbound_id;
            const outboundId = this.passengerData.ticket_fare_outbound_id;
            const ticketFareId = this.passengerData.ticket_fare_id;

            if (!ticketFareId && !inboundId && !outboundId && !passengerType) {
                this.passengerData.baggage_weight = 'Select a Ticket and Define Passenger Type';
                return;
            }
            if (!ticketFareId && !inboundId && !outboundId) {
                this.passengerData.baggage_weight = 'Select a Ticket';
                return;
            }
            if (!passengerType) {
                this.passengerData.baggage_weight = 'Define Passenger Type';
                return;
            }

            const lowerType = passengerType.toLowerCase();
            const getAllowance = (ticketId, direction) => {
                const ticket = this.allTickets.find(t => String(t.id) === String(ticketId));
                if (!ticket || !ticket.baggage_allowances || ticket.baggage_allowances.length === 0) return null;
                const allowance = ticket.baggage_allowances.find(
                    ba => ba.passenger_type === lowerType && ba.travel_direction === direction
                );
                return allowance ? allowance.allowance : null;
            };

            let display = '';
            if (inboundId && outboundId) {
                const inA = getAllowance(inboundId, 'inbound');
                const outA = getAllowance(outboundId, 'outbound');
                const parts = [];
                if (inA) parts.push(`Inbound: ${inA}`);
                if (outA) parts.push(`Outbound: ${outA}`);
                display = parts.length > 0 ? parts.join(' | ') : 'No baggage allowance defined';
            } else if (ticketFareId) {
                const routeType = this.passengerData.route_type;
                if (!routeType) {
                    this.passengerData.baggage_weight = 'Select Route Type';
                    return;
                }
                if (routeType === 'One Way-Inbound') {
                    const a = getAllowance(ticketFareId, 'inbound');
                    display = a ? `Inbound: ${a}` : 'No baggage allowance defined';
                } else if (routeType === 'One Way-Outbound') {
                    const a = getAllowance(ticketFareId, 'outbound');
                    display = a ? `Outbound: ${a}` : 'No baggage allowance defined';
                } else {
                    const inA = getAllowance(ticketFareId, 'inbound');
                    const outA = getAllowance(ticketFareId, 'outbound');
                    const parts = [];
                    if (inA) parts.push(`Inbound: ${inA}`);
                    if (outA) parts.push(`Outbound: ${outA}`);
                    display = parts.length > 0 ? parts.join(' | ') : 'No baggage allowance defined';
                }
            } else {
                display = 'No baggage allowance defined';
            }

            this.passengerData.baggage_weight = display;
        },

        filterTickets() {
            const routeTypeMap = {
                'One Way-Inbound': 'oneway_inbound',
                'One Way-Outbound': 'oneway_outbound',
                'Round': 'round',
                'Multi City': 'multi_city',
            };
            const flightTypeMap = {
                'Transit': 'transit',
                'Direct': 'direct',
            };
            const dbRouteType = routeTypeMap[this.passengerData.route_type];
            const dbFlightType = flightTypeMap[this.passengerData.flight_type];

            if (!dbRouteType || !dbFlightType) {
                this.filteredTickets = [];
                this.passengerData.ticket_fare_id = '';
                this.passengerData.route = '';
                this.passengerData.airline = '';
                this.passengerData.class = '';
                return;
            }

            this.filteredTickets = this.allTickets.filter(ticket => {
                return ticket.route_type === dbRouteType && ticket.flight_type === dbFlightType;
            });

            if (this.filteredTickets.length === 0) {
                this.passengerData.ticket_fare_id = '';
            }
            this.updateRouteAirlineClass();
        },

        onServiceRequiredChange() {
            if (this.passengerData.service_required === 'visa_only') {
                this.passengerData.route_type = '';
                this.passengerData.flight_type = '';
                this.passengerData.ticket_fare_id = '';
                this.passengerData.ticket_fare_inbound_id = '';
                this.passengerData.ticket_fare_outbound_id = '';
                this.passengerData.route = '';
                this.passengerData.airline = '';
                this.passengerData.class = '';
                this.passengerData.inbound_route = '';
                this.passengerData.inbound_airline = '';
                this.passengerData.inbound_class = '';
                this.passengerData.outbound_route = '';
                this.passengerData.outbound_airline = '';
                this.passengerData.outbound_class = '';
                this.passengerData.baggage_weight = 'Visa Only - No ticket required';
                this.filteredTickets = [];
            } else if (window.__bookingServerData?.preSelectedPackageId) {
                const pkg = this.allPackages.find(p => String(p.id) === String(window.__bookingServerData.preSelectedPackageId));
                if (pkg) {
                    if (pkg.is_double_ticket && pkg.ticket_fare_inbound_id && pkg.ticket_fare_outbound_id) {
                        const inboundTicket = this.allTickets.find(t => String(t.id) === String(pkg.ticket_fare_inbound_id));
                        const outboundTicket = this.allTickets.find(t => String(t.id) === String(pkg.ticket_fare_outbound_id));
                        if (inboundTicket) {
                            this.passengerData.inbound_route = inboundTicket.route || '';
                            this.passengerData.inbound_airline = inboundTicket.airline || '';
                            this.passengerData.inbound_class = inboundTicket.airline_class || '';
                        }
                        if (outboundTicket) {
                            this.passengerData.outbound_route = outboundTicket.route || '';
                            this.passengerData.outbound_airline = outboundTicket.airline || '';
                            this.passengerData.outbound_class = outboundTicket.airline_class || '';
                        }
                        this.passengerData.ticket_fare_inbound_id = String(pkg.ticket_fare_inbound_id);
                        this.passengerData.ticket_fare_outbound_id = String(pkg.ticket_fare_outbound_id);
                        this.passengerData.ticket_fare_id = '';
                        this.passengerData.route_type = '';
                        this.passengerData.flight_type = '';
                        this.passengerData.route = '';
                        this.passengerData.airline = '';
                        this.passengerData.class = '';
                        this.populateFlightDateRangeOptions([]);
                    } else if (pkg.ticket_fare_id) {
                        const ticket = this.allTickets.find(t => String(t.id) === String(pkg.ticket_fare_id));
                        if (ticket) {
                            const reverseRouteTypeMap = {
                                'oneway_inbound': 'One Way-Inbound',
                                'oneway_outbound': 'One Way-Outbound',
                                'round': 'Round',
                                'multi_city': 'Multi City',
                            };
                            const reverseFlightTypeMap = {
                                'transit': 'Transit',
                                'direct': 'Direct',
                            };
                            this.passengerData.route_type = reverseRouteTypeMap[ticket.route_type] || '';
                            this.passengerData.flight_type = reverseFlightTypeMap[ticket.flight_type] || '';
                            this.filteredTickets = this.allTickets.filter(t =>
                                t.route_type === ticket.route_type &&
                                t.flight_type === ticket.flight_type
                            );
                            this.passengerData.route = ticket.route;
                            this.passengerData.airline = ticket.airline || '';
                            this.passengerData.class = ticket.airline_class || '';
                            this.passengerData.ticket_fare_inbound_id = '';
                            this.passengerData.ticket_fare_outbound_id = '';
                            this.passengerData.inbound_route = '';
                            this.passengerData.inbound_airline = '';
                            this.passengerData.inbound_class = '';
                            this.passengerData.outbound_route = '';
                            this.passengerData.outbound_airline = '';
                            this.passengerData.outbound_class = '';
                            this.$nextTick(() => {
                                this.passengerData.ticket_fare_id = String(pkg.ticket_fare_id);
                                this.calculateFlightDateRange();
                                this.updateBaggageWeight();
                            });
                        }
                    }
                }
            }
        },

        onTicketChange() {
            this.updateRouteAirlineClass();
            this.updateBaggageWeight();
            this.calculateFlightDateRange();
        },

        updateRouteAirlineClass() {
            if (!this.passengerData.ticket_fare_id) {
                this.passengerData.route = '';
                this.passengerData.airline = '';
                this.passengerData.class = '';
                return;
            }
            let ticket = this.filteredTickets.find(t => t.id == this.passengerData.ticket_fare_id);
            if (!ticket) {
                ticket = this.allTickets.find(t => t.id == this.passengerData.ticket_fare_id);
            }
            if (ticket) {
                this.passengerData.route = ticket.route;
                this.passengerData.airline = ticket.airline || '';
                this.passengerData.class = ticket.airline_class || '';
            }
        },

        calculateFlightDateRange() {
            const route = this.passengerData.route;
            if (!route) {
                this.populateFlightDateRangeOptions([]);
                return;
            }
            const airline = this.passengerData.airline || '';
            const travelClass = this.passengerData.class || '';
            this.fetchFlightDateGapAndGenerateRange(route, airline, travelClass);
        },

        async fetchFlightDateGapAndGenerateRange(route, airline, travelClass) {
            try {
                const params = new URLSearchParams({ route, airline, travel_class: travelClass });
                const response = await fetch(`/api/ticket-fares/flight-date-gap?${params}`);
                const data = await response.json();
                if (data.default_gap !== undefined) {
                    const additionalGap = parseInt(data.additional_gap) || 0;
                    const defaultGap = parseInt(data.default_gap) || 30;
                    this.generateFlightDateRangeOptions(defaultGap, additionalGap);
                } else {
                    this.populateFlightDateRangeOptions([]);
                }
            } catch (e) {
                console.error('Error fetching flight date gap:', e);
                this.populateFlightDateRangeOptions([]);
            }
        },

        generateFlightDateRangeOptions(defaultGap, additionalGap) {
            const finalGap = defaultGap + additionalGap;
            const expectedDate = new Date();
            expectedDate.setDate(expectedDate.getDate() + finalGap);

            const day = expectedDate.getDate();
            let startMonthOffset = 0;
            let startSlot = 0;

            if (day >= 1 && day <= 5) { startMonthOffset = 0; startSlot = 0; }
            else if (day >= 6 && day <= 10) { startMonthOffset = 0; startSlot = 1; }
            else if (day >= 11 && day <= 15) { startMonthOffset = 0; startSlot = 1; }
            else if (day >= 16 && day <= 20) { startMonthOffset = 0; startSlot = 2; }
            else if (day >= 21 && day <= 25) { startMonthOffset = 0; startSlot = 2; }
            else if (day >= 26 && day <= 31) { startMonthOffset = 1; startSlot = 0; }

            const ranges = [];
            const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            const startYear = expectedDate.getFullYear();
            const startMonth = expectedDate.getMonth();

            for (let i = 0; i < 16; i++) {
                const slotIndex = (startSlot + i) % 3;
                const monthIndex = startMonth + startMonthOffset + Math.floor((startSlot + i) / 3);
                let year = startYear + Math.floor(monthIndex / 12);
                let month = monthIndex % 12;
                if (month < 0) month += 12;

                let rangeStart, rangeEnd;
                if (slotIndex === 0) {
                    rangeStart = new Date(year, month, 1);
                    rangeEnd = new Date(year, month, 10);
                } else if (slotIndex === 1) {
                    rangeStart = new Date(year, month, 11);
                    rangeEnd = new Date(year, month, 20);
                } else {
                    rangeStart = new Date(year, month, 21);
                    const lastDay = new Date(year, month + 1, 0).getDate();
                    rangeEnd = new Date(year, month, lastDay);
                }

                const startStr = `${months[rangeStart.getMonth()]} ${rangeStart.getDate()}, ${rangeStart.getFullYear()}`;
                const endStr = `${months[rangeEnd.getMonth()]} ${rangeEnd.getDate()}, ${rangeEnd.getFullYear()}`;
                ranges.push({
                    value: `${startStr} - ${endStr}`,
                    label: `${startStr} - ${endStr}`,
                    dayStart: rangeStart.getDate()
                });
            }

            this.populateFlightDateRangeOptions(ranges);
        },

        populateFlightDateRangeOptions(ranges) {
            const select = document.getElementById('passengerFlightDateRange');
            if (!select) return;
            select.innerHTML = '<option value="">Select Date Range</option>';
            ranges.forEach(range => {
                const option = document.createElement('option');
                option.value = range.value;
                option.textContent = range.label;
                select.appendChild(option);
            });

            if (ranges.length > 0 && this.passengerData.flight_date_from && this.passengerData.flight_date_to) {
                this.generateFlightDateRangeForEdit(this.passengerData.flight_date_from, this.passengerData.flight_date_to);
            }
        },

        generateFlightDateRangeForEdit(fromDate, toDate) {
            const fromParts = fromDate.split('-');
            const toParts = toDate.split('-');
            if (fromParts.length < 3 || toParts.length < 3) return;
            const from = new Date(parseInt(fromParts[0]), parseInt(fromParts[1]) - 1, parseInt(fromParts[2]));
            const to = new Date(parseInt(toParts[0]), parseInt(toParts[1]) - 1, parseInt(toParts[2]));
            const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            const rangeStr = `${months[from.getMonth()]} ${from.getDate()}, ${from.getFullYear()} - ${months[to.getMonth()]} ${to.getDate()}, ${to.getFullYear()}`;
            this.passengerData.flight_date_range = rangeStr;

            this.$nextTick(() => {
                const select = document.getElementById('passengerFlightDateRange');
                if (select) {
                    const existingOption = Array.from(select.options).find(opt => opt.value === rangeStr);
                    if (existingOption) {
                        select.value = rangeStr;
                    } else {
                        const option = document.createElement('option');
                        option.value = rangeStr;
                        option.textContent = rangeStr;
                        select.appendChild(option);
                        select.value = rangeStr;
                    }
                }
            });
        },

        generateDoubleTicketFlightDateRange(fromDate, toDate) {
            const fromParts = fromDate.split('-');
            const toParts = toDate.split('-');
            if (fromParts.length < 3 || toParts.length < 3) return;
            const from = new Date(parseInt(fromParts[0]), parseInt(fromParts[1]) - 1, parseInt(fromParts[2]));
            const to = new Date(parseInt(toParts[0]), parseInt(toParts[1]) - 1, parseInt(toParts[2]));
            const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            const rangeStr = `${months[from.getMonth()]} ${from.getDate()}, ${from.getFullYear()} - ${months[to.getMonth()]} ${to.getDate()}, ${to.getFullYear()}`;
            this.passengerData.flight_date_range = rangeStr;

            this.$nextTick(() => {
                const select = document.getElementById('passengerFlightDateRangeDouble');
                if (select) {
                    const existingOption = Array.from(select.options).find(opt => opt.value === rangeStr);
                    if (existingOption) {
                        select.value = rangeStr;
                    } else {
                        const option = document.createElement('option');
                        option.value = rangeStr;
                        option.textContent = rangeStr;
                        select.appendChild(option);
                        select.value = rangeStr;
                    }
                }
            });
        },

        onFlightDateRangeChange() {
            if (this.passengerData.flight_date_range) {
                const parsed = this.parseFlightDateRange(this.passengerData.flight_date_range);
                if (parsed) {
                    this.passengerData.flight_date_from = parsed.from;
                    this.passengerData.flight_date_to = parsed.to;
                }
            }
        },

        parseFlightDateRange(rangeString) {
            if (!rangeString) return null;
            const parts = rangeString.split(' - ');
            if (parts.length !== 2) return null;
            const months = {
                'Jan': 0, 'Feb': 1, 'Mar': 2, 'Apr': 3, 'May': 4, 'Jun': 5,
                'Jul': 6, 'Aug': 7, 'Sep': 8, 'Oct': 9, 'Nov': 10, 'Dec': 11
            };
            const parseDate = (dateStr) => {
                const match = dateStr.trim().match(/^(\w+)\s+(\d+),\s+(\d{4})$/);
                if (!match) return null;
                const month = months[match[1]];
                const day = parseInt(match[2]);
                const year = parseInt(match[3]);
                if (month === undefined) return null;
                return `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
            };
            const fromDate = parseDate(parts[0]);
            const toDate = parseDate(parts[1]);
            if (!fromDate || !toDate) return null;
            return { from: fromDate, to: toDate };
        },

        getTicketDisplayText(ticket) {
            const ticketType = ticket.ticket_type || 'standard';
            const type = ticketType.charAt(0).toUpperCase() + ticketType.slice(1);
            switch (ticketType) {
                case 'offer':
                    const offer = ticket.offer_price ? ' | ' + Alpine.store('currency').format(ticket.offer_price) : '';
                    return `${ticket.route || ''} | ${type}${offer}`;
                case 'group':
                    const seats = ticket.available_seats ? ' | ' + ticket.available_seats + ' seats' : '';
                    return `${ticket.route || ''} | ${type}${seats}`;
                default:
                    return `${ticket.route || ''} | ${type}`;
            }
        },

        handleStayDurationChange() {
            if (this.passengerData.stay_duration === 'Customize (Set Duration)') {
                this.openCustomDurationModal();
            }
        },

        openCustomDurationModal() {
            this.customDurationModalVisible = true;
            this.passengerData.customDurationDays = '';
            this.$nextTick(() => {
                const input = document.getElementById('customDurationDays');
                if (input) input.focus();
            });
        },

        closeCustomDurationModal() {
            this.customDurationModalVisible = false;
            this.passengerData.customDurationDays = '';
        },

        saveCustomDuration() {
            const days = parseInt(this.passengerData.customDurationDays);
            const minDays = window.__stayDurationLimits?.minDays ?? 1;
            const maxDays = window.__stayDurationLimits?.maxDays ?? 85;
            if (isNaN(days) || days < minDays || days > maxDays) {
                alert(`Please enter a valid duration between ${minDays} and ${maxDays} days`);
                return;
            }

            this.passengerData.stay_duration = `Customized (${days} Days)`;
            this.passengerData.stay_duration_int = days;
            this.passengerData.stay_duration_display = `Customized (${days} Days)`;

            const select = document.querySelector('select[x-model="passengerData.stay_duration"]');
            if (select) {
                let customOption = Array.from(select.options).find(opt => opt.value.startsWith('Customized'));
                if (!customOption) {
                    customOption = document.createElement('option');
                    select.appendChild(customOption);
                }
                customOption.value = `Customized (${days} Days)`;
                customOption.textContent = `Customized (${days} Days)`;
                select.value = `Customized (${days} Days)`;
            }

            this.closeCustomDurationModal();
            this.calculatePassengerType();
        },

        async savePassenger() {
            if (!this.passengerData.first_name || !this.passengerData.last_name || !this.passengerData.passport_no || !this.passengerData.date_of_birth) {
                alert('Please fill in all required fields');
                return;
            }

            if (this.passengerData.passenger_type?.toLowerCase() === 'adult' && !this.passengerData.gender) {
                alert('Please select gender for adult passenger');
                return;
            }

            let stayDurationValue = this.parseStayDurationDays(this.passengerData.stay_duration);
            if (stayDurationValue === null) {
                stayDurationValue = this.passengerData.stay_duration_int || null;
            }

            let flightDateFrom = this.passengerData.flight_date_from;
            let flightDateTo = this.passengerData.flight_date_to;

            if (this.passengerData.flight_date_range && !flightDateFrom) {
                const parsed = this.parseFlightDateRange(this.passengerData.flight_date_range);
                if (parsed) {
                    flightDateFrom = parsed.from;
                    flightDateTo = parsed.to;
                }
            }

            const payload = {
                first_name: this.passengerData.first_name,
                last_name: this.passengerData.last_name,
                passport_no: this.passengerData.passport_no,
                date_of_birth: this.passengerData.date_of_birth,
                mobile_no: this.passengerData.mobile_no,
                passport_expiry: this.passengerData.passport_expiry || null,
                service_required: this.passengerData.service_required || null,
                stay_duration: stayDurationValue,
                flight_date_from: flightDateFrom || null,
                flight_date_to: flightDateTo || null,
                address: this.passengerData.address || '',
                passenger_type: this.passengerData.passenger_type ? this.passengerData.passenger_type.toLowerCase() : null,
                gender: this.passengerData.gender || null,
                ticket_fare_id: this.isDoubleTicket ? null : (this.passengerData.ticket_fare_id || null),
                ticket_fare_inbound_id: this.passengerData.ticket_fare_inbound_id || null,
                ticket_fare_outbound_id: this.passengerData.ticket_fare_outbound_id || null,
            };

            try {
                const response = await fetch(window.__bookingServerData.updateRoute, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify(payload),
                });

                const data = await response.json();

                if (data.success) {
                    window.location.href = window.__bookingServerData.showRoute;
                } else {
                    alert(data.message || 'Failed to update passenger');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('An error occurred while updating the passenger.');
            }
        },
    }));
    }
    if (typeof Alpine !== 'undefined') {
        registerEditPassengerApp();
    } else {
        document.addEventListener('alpine:init', registerEditPassengerApp);
    }
})();

const editPassengerId = {{ $passenger->id }};

function handleEditPassengerDocUpload(input) {
    const files = input.files;
    if (!files || files.length === 0) return;

    Array.from(files).forEach(file => {
        const formData = new FormData();
        formData.append('files[]', file);

        fetch(`/passengers/${editPassengerId}/documents`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const list = document.getElementById('edit_passenger_docs_list');
                const emptyState = list.querySelector('p.text-slate-400');
                if (emptyState) emptyState.remove();

                const doc = data.documents[0];
                const item = document.createElement('div');
                item.className = 'flex items-center justify-between bg-white rounded px-3 py-2 border border-slate-200';
                item.innerHTML = `
                    <div class="flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        <span class="text-sm text-slate-700">${doc.display_name}</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <a href="/passengers/${editPassengerId}/documents/${doc.id}/download" class="text-blue-500 hover:text-blue-700">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                            </svg>
                        </a>
                        <button onclick="deleteEditPassengerDoc(${doc.id})" class="text-red-500 hover:text-red-700">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                        </button>
                    </div>
                `;
                list.appendChild(item);
                input.value = '';
            } else {
                alert(data.message || 'Failed to upload document');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Failed to upload document');
        });
    });
}

function deleteEditPassengerDoc(documentId) {
    if (!confirm('Are you sure you want to delete this document?')) return;

    fetch(`/passengers/${editPassengerId}/documents/${documentId}`, {
        method: 'DELETE',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const list = document.getElementById('edit_passenger_docs_list');
            const docItem = list.querySelector(`button[onclick*="${documentId}"]`)?.closest('.flex.items-center');
            if (docItem) docItem.remove();
            if (list.children.length === 1) {
                const onlyChild = list.querySelector('.flex.items-center');
                if (!onlyChild) {
                    list.innerHTML = '<p class="text-sm text-slate-400">No documents uploaded</p>';
                }
            }
            if (list.children.length === 0) {
                list.innerHTML = '<p class="text-sm text-slate-400">No documents uploaded</p>';
            }
        } else {
            alert(data.message || 'Failed to delete document');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to delete document');
    });
}
</script>
@endpush
@endsection