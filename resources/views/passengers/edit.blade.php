@extends('layouts.app')
@section('title', 'Edit Passenger')
@section('content')
<script>window.__bookingServerData = {
    passenger: @json($passenger->toArray()),
    ticketFares: @json($ticketFares ?? []),
    packages: @json($packages ?? []),
    isEditMode: true,
    updateRoute: '{{ route('passengers.update', $passenger->id) }}',
    showRoute: '{{ route('passengers.show', $passenger->id) }}'
};</script>
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

            <div class="mb-6">
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
                        <select x-model="passengerData.service_required" required class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none bg-white">
                            <option value="">Select Service</option>
                            <option value="All">All</option>
                            <option value="Visa Only">Visa Only</option>
                            <option value="Ticket Only">Ticket Only</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="mb-6">
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

            <div class="mb-6">
                <h4 class="text-sm font-medium text-slate-600 mb-3 pb-2 border-b border-slate-200">Location</h4>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Detailed Address <span class="text-red-500">*</span></label>
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
                <label class="block text-sm font-medium text-slate-700 mb-1">Number of Days (30-89)</label>
                <input type="number" id="customDurationDays" x-model="passengerData.customDurationDays" min="30" max="89" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none" placeholder="Enter days">
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
document.addEventListener('alpine:init', () => {
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
            flight_date_range: '',
            flight_date_from: '',
            flight_date_to: '',
            baggage_weight: '',
            address: '',
            customDurationDays: ''
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

            const rawService = p.service_required || '';
            const serviceMap = { 'all': 'All', 'visa_only': 'Visa Only', 'ticket_only': 'Ticket Only' };
            this.passengerData.service_required = serviceMap[rawService] || rawService;

            const stayDuration = p.stay_duration;
            if (stayDuration !== null && stayDuration !== undefined && stayDuration !== '') {
                const sd = parseInt(stayDuration);
                if (!isNaN(sd)) {
                    if (sd === 14 || sd === 85) {
                        this.passengerData.stay_duration = String(sd);
                        this.passengerData.stay_duration_int = sd;
                    } else if (sd >= 30 && sd <= 89) {
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

            const ticketFare = p.ticket_fare || p.ticketFare || null;

            if (ticketFare) {
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

                const route = ticketFare.route || null;
                if (route) {
                    this.passengerData.route_type = reverseRouteTypeMap[route.route_type] || '';
                    this.passengerData.flight_type = reverseFlightTypeMap[route.flight_type] || '';
                }

                this.filteredTickets = this.allTickets.filter(t =>
                    t.route_type === (route ? route.route_type : '') &&
                    t.flight_type === (route ? route.flight_type : '')
                );

                this.passengerData.ticket_fare_id = String(ticketFare.id);
                this.passengerData.route = ticketFare.route || '';
                this.passengerData.airline = ticketFare.airline || '';
                this.passengerData.class = ticketFare.airline_class || '';

                if (p.flight_date_from && p.flight_date_to) {
                    this.generateFlightDateRangeForEdit(p.flight_date_from, p.flight_date_to);
                } else {
                    this.calculateFlightDateRange();
                }
            } else {
                this.filteredTickets = [];
                this.populateFlightDateRangeOptions([]);
            }

            if (p.flight_date_from || p.flight_date_to) {
                this.passengerData.flight_date_from = this.toDateValue(p.flight_date_from);
                this.passengerData.flight_date_to = this.toDateValue(p.flight_date_to);
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

            const stayDays = this.parseStayDurationDays(this.passengerData.stay_duration);

            if (stayDays !== null) {
                const adjustmentDays = stayDays < 30 ? 30 : 90;
                const effectiveDate = new Date(dobDate);
                effectiveDate.setDate(effectiveDate.getDate() - adjustmentDays);
                const ageInMonthsWithDuration = (today.getFullYear() - effectiveDate.getFullYear()) * 12 + (today.getMonth() - effectiveDate.getMonth());
                const dayDiff = today.getDate() - effectiveDate.getDate();
                const finalAgeInMonths = dayDiff < 0 ? ageInMonthsWithDuration - 1 : ageInMonthsWithDuration;
                ageInMonths = Math.max(ageInMonths, finalAgeInMonths);
            }

            let calculatedType = 'Adult';
            if (ageInMonths < 24) {
                calculatedType = 'Infant';
            } else if (ageInMonths < 144) {
                calculatedType = 'Child';
            }

            this.passengerData.passenger_type = calculatedType;
            this.updateBaggageWeight();
        },

        updateBaggageWeight() {
            const ticketFareId = this.passengerData.ticket_fare_id;
            const passengerType = this.passengerData.passenger_type;
            const routeType = this.passengerData.route_type;

            if (!ticketFareId && !passengerType) {
                this.passengerData.baggage_weight = 'Select a Ticket and Define Passenger Type';
                return;
            }
            if (!ticketFareId) {
                this.passengerData.baggage_weight = 'Select a Ticket';
                return;
            }
            if (!passengerType) {
                this.passengerData.baggage_weight = 'Define Passenger Type';
                return;
            }
            if (!routeType) {
                this.passengerData.baggage_weight = 'Select Route Type';
                return;
            }

            const ticket = this.allTickets.find(t => String(t.id) === String(ticketFareId));
            if (!ticket || !ticket.baggage_allowances || ticket.baggage_allowances.length === 0) {
                this.passengerData.baggage_weight = 'No baggage allowance defined';
                return;
            }

            const lowerType = passengerType.toLowerCase();
            const allowances = ticket.baggage_allowances.filter(
                ba => ba.passenger_type === lowerType
            );

            const getAllowance = (direction) => {
                const found = allowances.find(ba => ba.travel_direction === direction);
                return found ? found.allowance : null;
            };

            let display = '';
            if (routeType === 'One Way-Inbound') {
                const a = getAllowance('inbound');
                display = a ? `Inbound: ${a}` : '';
            } else if (routeType === 'One Way-Outbound') {
                const a = getAllowance('outbound');
                display = a ? `Outbound: ${a}` : '';
            } else {
                const inA = getAllowance('inbound');
                const outA = getAllowance('outbound');
                if (inA && outA) {
                    display = `Inbound: ${inA} | Outbound: ${outA}`;
                } else if (inA) {
                    display = `Inbound: ${inA}`;
                } else if (outA) {
                    display = `Outbound: ${outA}`;
                }
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
            const price = ticket.selling_fare ? ticket.selling_fare + ' SAR' : '';
            const type = (ticket.ticket_type || 'standard').charAt(0).toUpperCase() + (ticket.ticket_type || 'standard').slice(1);
            switch (ticket.ticket_type) {
                case 'offer':
                    const offer = ticket.offer_price ? ' | ' + ticket.offer_price + ' SAR' : '';
                    return `${ticket.route || ''} | ${type} | ${price}${offer}`;
                case 'group':
                    const seats = ticket.available_seats ? ' | ' + ticket.available_seats + ' seats' : '';
                    return `${ticket.route || ''} | ${type} | ${price}${seats}`;
                default:
                    return `${ticket.route || ''} | ${type} | ${price}`;
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
            if (isNaN(days) || days < 30 || days > 89) {
                alert('Please enter a valid duration between 30 and 89 days');
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
                ticket_fare_id: this.passengerData.ticket_fare_id || null,
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
});
</script>
@endpush
@endsection