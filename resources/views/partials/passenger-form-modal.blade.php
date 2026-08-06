{{-- Passenger Modal --}}
<div x-show="passengerModalVisible" x-cloak class="fixed inset-0 z-[60] flex items-center justify-center">
    <div class="fixed inset-0 bg-black/50"></div>
    <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-2xl mx-4 p-6 max-h-[90vh] overflow-y-auto">
        <h3 class="text-xl font-semibold text-slate-800 mb-4" x-text="editingPassengerIndex !== null ? 'Edit Passenger' : 'Add Passenger'">Add Passenger</h3>
        <form @submit.prevent="savePassenger()">
            <div class="mb-4">
                <h4 class="text-sm font-medium text-slate-600 mb-3 pb-2 border-b border-slate-200">Basic Information</h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
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
                            <input type="tel" x-model="passengerData.mobile_no" class="w-full px-4 py-2 outline-none border-0" placeholder="+8801XXXXXXXXX">
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

            <div class="mb-4">
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
                        <select x-model="passengerData.service_required" @change="onServiceRequiredChange(); recalculateCurrentPassenger(editingPassengerIndex ?? passengers.length)" required class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none bg-white">
                            <option value="">Select Service</option>
                            <option value="all">Visa + Ticket</option>
                            <option value="visa_only">Visa Only</option>
                            <option value="ticket_only">Ticket Only</option>
                        </select>
                    </div>
                </div>
            </div>

            {{-- Single ticket travel details --}}
            <div class="mb-4" x-show="!isDoubleTicket">
                <h4 class="text-sm font-medium text-slate-600 mb-3 pb-2 border-b border-slate-200">Travel Details</h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Route Type *</label>
                        <select x-model="passengerData.route_type" disabled class="w-full px-4 py-2 border border-slate-200 rounded-lg bg-slate-50 text-slate-600 cursor-not-allowed" @change="updateBaggageWeight(); filterTickets()">
                            <option value="">Select</option>
                            <option value="One Way-Inbound">One Way-Inbound</option>
                            <option value="One Way-Outbound">One Way-Outbound</option>
                            <option value="Round">Round</option>
                            <option value="Multi City">Multi City</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Flight Type *</label>
                        <select x-model="passengerData.flight_type" disabled class="w-full px-4 py-2 border border-slate-200 rounded-lg bg-slate-50 text-slate-600 cursor-not-allowed" @change="filterTickets()">
                            <option value="">Select</option>
                            <option value="Transit">Transit</option>
                            <option value="Direct">Direct</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Ticket *</label>
                        <select x-model="passengerData.ticket_fare_id" disabled class="w-full px-4 py-2 border border-slate-200 rounded-lg bg-slate-50 text-slate-600 cursor-not-allowed" @change="onTicketChange()">
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
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Flight Date Range *</label>
                        <select id="passengerFlightDateRange" x-model="passengerData.flight_date_range" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none bg-white">
                            <option value="">Select Date Range</option>
                        </select>
                    </div>
                </div>
            </div>

            {{-- Double ticket travel details --}}
            <div class="mb-4" x-show="isDoubleTicket">
                <h4 class="text-sm font-medium text-slate-600 mb-3 pb-2 border-b border-slate-200">Inbound Travel</h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Route Type</label>
                        <input type="text" x-model="passengerData.inbound_route_type" disabled class="w-full px-4 py-2 border border-slate-200 rounded-lg bg-slate-50 text-slate-600 cursor-not-allowed">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Flight Type</label>
                        <input type="text" x-model="passengerData.inbound_flight_type" disabled class="w-full px-4 py-2 border border-slate-200 rounded-lg bg-slate-50 text-slate-600 cursor-not-allowed">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Route</label>
                        <input type="text" x-model="passengerData.inbound_route" disabled class="w-full px-4 py-2 border border-slate-200 rounded-lg bg-slate-50 text-slate-600 cursor-not-allowed" placeholder="Route">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Airline</label>
                        <input type="text" x-model="passengerData.inbound_airline" disabled class="w-full px-4 py-2 border border-slate-200 rounded-lg bg-slate-50 text-slate-600 cursor-not-allowed" placeholder="Airline">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Class</label>
                        <input type="text" x-model="passengerData.inbound_class" disabled class="w-full px-4 py-2 border border-slate-200 rounded-lg bg-slate-50 text-slate-600 cursor-not-allowed" placeholder="Class">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Baggage Allowance</label>
                        <input type="text" x-model="passengerData.inbound_baggage_weight" readonly class="w-full px-4 py-2 border border-slate-200 rounded-lg bg-slate-50 text-slate-600">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Flight Date Range *</label>
                        <select id="passengerFlightDateRangeDouble" x-model="passengerData.flight_date_range" @change="onFlightDateRangeChange()" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none bg-white">
                            <option value="">Select Date Range</option>
                        </select>
                    </div>
                </div>

                <h4 class="text-sm font-medium text-slate-600 mb-3 mt-4 pb-2 border-b border-slate-200">Outbound Travel</h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Route Type</label>
                        <input type="text" x-model="passengerData.outbound_route_type" disabled class="w-full px-4 py-2 border border-slate-200 rounded-lg bg-slate-50 text-slate-600 cursor-not-allowed">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Flight Type</label>
                        <input type="text" x-model="passengerData.outbound_flight_type" disabled class="w-full px-4 py-2 border border-slate-200 rounded-lg bg-slate-50 text-slate-600 cursor-not-allowed">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Route</label>
                        <input type="text" x-model="passengerData.outbound_route" disabled class="w-full px-4 py-2 border border-slate-200 rounded-lg bg-slate-50 text-slate-600 cursor-not-allowed" placeholder="Route">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Airline</label>
                        <input type="text" x-model="passengerData.outbound_airline" disabled class="w-full px-4 py-2 border border-slate-200 rounded-lg bg-slate-50 text-slate-600 cursor-not-allowed" placeholder="Airline">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Class</label>
                        <input type="text" x-model="passengerData.outbound_class" disabled class="w-full px-4 py-2 border border-slate-200 rounded-lg bg-slate-50 text-slate-600 cursor-not-allowed" placeholder="Class">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Baggage Allowance</label>
                        <input type="text" x-model="passengerData.outbound_baggage_weight" readonly class="w-full px-4 py-2 border border-slate-200 rounded-lg bg-slate-50 text-slate-600">
                    </div>
                </div>
            </div>

            <div class="mb-4">
                <h4 class="text-sm font-medium text-slate-600 mb-3 pb-2 border-b border-slate-200">Location</h4>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Detailed Address (BD) <span class="text-red-500">*</span></label>
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
                        <p class="text-xs text-slate-400">PDF, JPG, PNG. Max 5 MB per file, 20 MB total</p>
                    </div>
                </div>
                <div id="passenger_doc_list" class="mt-3 space-y-2"></div>
                <div id="passenger_doc_warnings" class="mt-1"></div>
            </div>

            <div class="flex gap-3 pt-4 border-t border-slate-200">
                <button type="submit" class="flex-1 px-6 py-2 bg-slate-700 text-white rounded-lg hover:bg-slate-800 transition font-medium">Add</button>
                <button type="button" @click="closePassengerModal()" class="flex-1 px-6 py-2 border border-slate-300 text-slate-700 rounded-lg hover:bg-slate-50 transition font-medium">Cancel</button>
            </div>
        </form>
    </div>
</div>
