import Alpine from 'alpinejs';

Alpine.data('bookingApp', () => ({
    activeTab: 'booking',
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
customerModalVisible: false,
        discountModalVisible: false,
        paymentModalVisible: false,
        customDurationModalVisible: false,
    paymentData: {
        currency: 'SAR',
        method: 'Cash',
        bank_method: '',
        trx_id: '',
        amount_sar: '',
        amount_bdt: ''
    },
    newCustomer: {
        name: '',
        passport_no: '',
        mobile_no: '',
        address: '',
        iqama_type: '',
        iqama_no: '',
        ref_iqama_no: '',
        ref_mobile_no: ''
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
        gender: '',
        mobile_no: '',
        passport_expiry: '',
        service_required: 'All',
        stay_duration: '14',
        stay_duration_int: 14,
        stay_duration_display: '',
        route: '',
        airline: '',
        travel_class: '',
        route_type: '',
        flight_type: '',
        flight_date_from: '',
        flight_date_to: '',
        address: '',
        baggage_weight: '',
        with_offer: false,
        refundable: false,
        customDurationDays: ''
    },
    
    init() {
        const urlParams = new URLSearchParams(window.location.search);
        const tab = urlParams.get('tab');
        if (tab) {
            this.activeTab = tab;
        }
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
        this.bookingData = {
            fingerprint_location: 'Office',
            fingerprint_office: '',
            district_id: '',
            package_id: '',
            discount_type: 'fixed',
            discount_value: 0,
            remarks: ''
        };
    },

    showIndexTab(tab) {
        this.activeTab = tab;
        this.formVisible = false;
    },

    async searchCustomers() {
        if (this.customerSearch.length < 2) {
            this.customerSuggestions = [];
            return;
        }
        try {
            const response = await fetch(`/api/customers/search?q=${encodeURIComponent(this.customerSearch)}`);
            this.customerSuggestions = await response.json();
        } catch (e) {
            console.error('Customer search error:', e);
            this.customerSuggestions = [];
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

    openCustomerModal() {
        this.customerModalVisible = true;
    },

    closeCustomerModal() {
        this.customerModalVisible = false;
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

    parseStayDurationDays(stayDuration) {
        if (!stayDuration) return null;
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

        console.log('=== Passenger Type Calculation (bookingApp) ===');
        console.log('DOB:', dob);
        console.log('Stay Duration:', this.passengerData.stay_duration);
        
        const stayDays = this.parseStayDurationDays(this.passengerData.stay_duration);
        console.log('Stay Days (parsed):', stayDays);
        
        if (stayDays !== null) {
            const adjustmentDays = stayDays < 30 ? 30 : 90;
            console.log('Adjustment Days:', adjustmentDays);
            
            const effectiveDate = new Date(dobDate);
            effectiveDate.setDate(effectiveDate.getDate() - adjustmentDays);
            console.log('Effective DOB (after -days):', effectiveDate.toISOString().split('T')[0]);
            
            const ageInMonthsWithDuration = (today.getFullYear() - effectiveDate.getFullYear()) * 12 + (today.getMonth() - effectiveDate.getMonth());
            const dayDiff = today.getDate() - effectiveDate.getDate();
            const finalAgeInMonths = dayDiff < 0 ? ageInMonthsWithDuration - 1 : ageInMonthsWithDuration;
            
            console.log('Base Age (months):', ageInMonths);
            console.log('Effective Age (months):', finalAgeInMonths);
            
            ageInMonths = Math.max(ageInMonths, finalAgeInMonths);
        }
        
        console.log('Final Age (months):', ageInMonths);
        
        let calculatedType = 'Adult';
        if (ageInMonths < 24) {
            calculatedType = 'Infant';
        } else if (ageInMonths < 144) {
            calculatedType = 'Child';
        }
        
        console.log('Calculated Type:', calculatedType);
        console.log('===========================================');
        
        this.passengerData.passenger_type = calculatedType;
        this.updateBaggageWeight();
    },

    updateBaggageWeight() {
        const route = this.passengerData.route;
        const airline = this.passengerData.airline;
        const travelClass = this.passengerData.travel_class;
        const passengerType = this.passengerData.passenger_type;
        const routeType = this.passengerData.route_type;
        
        if (!route || !airline || !travelClass || !passengerType || !routeType) {
            this.passengerData.baggage_weight = '';
            return;
        }
        
        let direction = 'Outbound';
        if (routeType === 'One Way-Inbound') direction = 'Inbound';
        else if (routeType === 'One Way-Outbound') direction = 'Outbound';
        else if (routeType === 'Round') direction = 'Round';
        else if (routeType === 'Multi City') direction = 'MultiCity';
        
        this.fetchBaggageAllowance(route, airline, travelClass, passengerType, direction);
    },

    async fetchBaggageAllowance(route, airline, travelClass, passengerType, direction) {
        try {
            const params = new URLSearchParams({
                route,
                airline,
                travel_class: travelClass,
                passenger_type: passengerType,
                direction
            });
            const response = await fetch(`/api/ticket-fares/baggage?${params}`);
            const data = await response.json();
            
            if (data.allowance) {
                this.passengerData.baggage_weight = data.allowance + 'kg';
            } else {
                this.passengerData.baggage_weight = '';
            }
        } catch (e) {
            console.error('Error fetching baggage allowance:', e);
            this.passengerData.baggage_weight = '';
        }
    },

    calculateFlightDateRange() {
        const route = this.passengerData.route;
        
        if (!route) {
            this.passengerData.flight_date_range = '';
            return;
        }
        
        const airline = this.passengerData.airline || '';
        const travelClass = this.passengerData.travel_class || '';
        
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
                this.generateFlightDateRangeWithGap(defaultGap, additionalGap);
            } else {
                this.passengerData.flight_date_range = '';
            }
        } catch (e) {
            console.error('Error fetching flight date gap:', e);
            this.passengerData.flight_date_range = '';
        }
    },

    generateFlightDateRangeWithGap(defaultGap, additionalGap) {
        const finalGap = defaultGap + additionalGap;
        const bookingDate = new Date();
        const calculatedDate = new Date(bookingDate);
        calculatedDate.setDate(calculatedDate.getDate() + finalGap);
        
        const day = calculatedDate.getDate();
        let selectedRange = '';
        
        if (day >= 1 && day <= 5) {
            selectedRange = '1-10';
        } else if (day >= 6 && day <= 15) {
            selectedRange = '11-20';
        } else if (day >= 16 && day <= 31) {
            selectedRange = '21-31';
        }
        
        this.generateFlightDateRangeOptions(selectedRange);
    },

    generateFlightDateRangeOptions(preSelectRange = null) {
        const select = document.getElementById('passengerFlightDateRange');
        if (!select) return;
        
        select.innerHTML = '<option value="">Select Date Range</option>';
        
        const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        
        const ranges = [];
        
        const startDate = new Date();
        startDate.setDate(startDate.getDate() + 30);
        
        for (let i = 0; i < 4; i++) {
            for (let week = 0; week < 4; week++) {
                const rangeStart = new Date(startDate);
                rangeStart.setDate(rangeStart.getDate() + (i * 40) + (week * 10));
                
                const rangeEnd = new Date(rangeStart);
                rangeEnd.setDate(rangeEnd.getDate() + 9);
                
                const startStr = `${months[rangeStart.getMonth()]} ${rangeStart.getDate()}, ${rangeStart.getFullYear()}`;
                const endStr = `${months[rangeEnd.getMonth()]} ${rangeEnd.getDate()}, ${rangeEnd.getFullYear()}`;
                const displayText = `${startStr} - ${endStr}`;
                
                ranges.push({
                    value: displayText,
                    label: displayText,
                    dayStart: rangeStart.getDate()
                });
            }
        }
        
        ranges.forEach(range => {
            const option = document.createElement('option');
            option.value = range.value;
            option.textContent = range.label;
            select.appendChild(option);
        });
        
        if (preSelectRange) {
            const preStart = parseInt(preSelectRange.split('-')[0]);
            const foundRange = ranges.find(r => r.dayStart === preStart);
            if (foundRange) {
                this.passengerData.flight_date_range = foundRange.value;
            } else {
                this.passengerData.flight_date_range = '';
            }
        } else {
            this.passengerData.flight_date_range = '';
        }
    },

    async updateFingerprintCharge() {
        if (!this.bookingData.district_id) {
            this.fingerprintCharge = 0;
            return;
        }
        try {
            const response = await fetch(`/api/bookings/fingerprint-charge?district_id=${this.bookingData.district_id}&location=${this.bookingData.fingerprint_location}`);
            const data = await response.json();
            this.fingerprintCharge = data.charge || 0;
        } catch (e) {
            console.error('Fingerprint charge error:', e);
            this.fingerprintCharge = 0;
        }
    },

    updateLiveSummary() {
        const packageSelect = document.querySelector('#bookingPackage');
        const districtSelect = document.querySelector('#bookingDistrict');
        const fingerprintLocationSelect = document.querySelector('#bookingFingerprintLocation');
        
        if (!packageSelect || !districtSelect || !fingerprintLocationSelect) return;
        
        const selectedPackage = packageSelect.options[packageSelect.selectedIndex];
        const packageValue = parseInt(selectedPackage?.dataset?.packageValue) || 0;
        
        const selectedDistrict = districtSelect.options[districtSelect.selectedIndex];
        const districtFingerprintCharge = parseInt(selectedDistrict?.dataset?.fingerprintCharge) || 0;
        
        const fingerprintLocation = fingerprintLocationSelect.value || '';
        const fingerprintCharge = fingerprintLocation === 'Home' ? districtFingerprintCharge : 0;
        
        const paxQty = this.passengers.length;
        const totalValue = (packageValue * paxQty) + fingerprintCharge;
        
        let discountAmount = 0;
        if (this.bookingData.discount_type === 'percentage') {
            discountAmount = totalValue * this.bookingData.discount_value / 100;
        } else {
            discountAmount = this.bookingData.discount_value;
        }
        
        const finalTotal = totalValue - discountAmount;
        
        this.updateSummaryDisplay(packageValue, fingerprintCharge, paxQty, discountAmount, totalValue, finalTotal);
    },

    updateSummaryDisplay(packageValue, fingerprintCharge, paxQty, discountAmount, totalBeforeDiscount, totalValue) {
        const summaryPackage = document.getElementById('summaryPackage');
        const summaryFingerprintCharge = document.getElementById('summaryFingerprintCharge');
        const summaryPaxQty = document.getElementById('summaryPaxQty');
        const summaryDiscount = document.getElementById('summaryDiscount');
        const summaryTotalBeforeDiscount = document.getElementById('summaryTotalBeforeDiscount');
        const summaryTotalValue = document.getElementById('summaryTotalValue');
        
        if (summaryPackage) summaryPackage.textContent = packageValue > 0 ? `${packageValue} SAR` : '-';
        if (summaryFingerprintCharge) summaryFingerprintCharge.textContent = fingerprintCharge > 0 ? `${fingerprintCharge} SAR` : '-';
        if (summaryPaxQty) summaryPaxQty.textContent = paxQty;
        if (summaryDiscount) summaryDiscount.textContent = discountAmount > 0 ? `-${discountAmount} SAR` : '-';
        if (summaryTotalBeforeDiscount) summaryTotalBeforeDiscount.textContent = totalBeforeDiscount > 0 ? `${totalBeforeDiscount} SAR` : '0 SAR';
        if (summaryTotalValue) summaryTotalValue.textContent = `${totalValue} SAR`;
    },

    openPassengerModal(index = null) {
        if (index !== null) {
            this.editingPassengerIndex = index;
            this.passengerData = {
                ...this.passengers[index],
                route_type: this.passengers[index].route_type || '',
                flight_type: this.passengers[index].flight_type || '',
                baggage_weight: this.passengers[index].baggage_weight || ''
            };

            if (this.passengerData.stay_duration_int && this.passengerData.stay_duration_int >= 30 && this.passengerData.stay_duration_int <= 89) {
                this.passengerData.stay_duration = `Customized (${this.passengerData.stay_duration_int} Days)`;
                this.passengerData.stay_duration_display = `Customized (${this.passengerData.stay_duration_int} Days)`;
                this.$nextTick(() => {
                    const select = document.querySelector('select[x-model="passengerData.stay_duration"]');
                    if (select) {
                        let customOption = Array.from(select.options).find(opt => opt.value.startsWith('Customized'));
                        if (!customOption) {
                            customOption = document.createElement('option');
                            select.appendChild(customOption);
                        }
                        customOption.value = `Customized (${this.passengerData.stay_duration_int} Days)`;
                        customOption.textContent = `Customized (${this.passengerData.stay_duration_int} Days)`;
                        select.value = `Customized (${this.passengerData.stay_duration_int} Days)`;
                    }
                });
            } else if (this.passengerData.stay_duration && typeof this.passengerData.stay_duration === 'string' && this.passengerData.stay_duration.startsWith('Customized')) {
                this.passengerData.stay_duration_display = this.passengerData.stay_duration;
            }

            setTimeout(() => this.updateBaggageWeight(), 100);
        } else {
            this.editingPassengerIndex = null;
            this.passengerData = {
                first_name: '',
                last_name: '',
                passport_no: '',
                date_of_birth: '',
                passenger_type: '',
                gender: '',
                mobile_no: '',
                passport_expiry: '',
                service_required: 'All',
                stay_duration: '14',
                stay_duration_int: 14,
                stay_duration_display: '',
                route: '',
                airline: '',
                travel_class: '',
                route_type: '',
                flight_type: '',
                flight_date_from: '',
                flight_date_to: '',
                address: '',
                baggage_weight: '',
                with_offer: false,
                refundable: false
            };
        }
        this.generateFlightDateRangeOptions();
        this.passengerModalVisible = true;
    },

    closePassengerModal() {
        this.passengerModalVisible = false;
    },

    editPassenger(index) {
        this.openPassengerModal(index);
    },

    savePassenger() {
        if (!this.passengerData.first_name || !this.passengerData.last_name || !this.passengerData.passport_no || !this.passengerData.date_of_birth) {
            alert('Please fill in all required fields');
            return false;
        }

        if (this.passengerData.passenger_type?.toLowerCase() === 'adult' && !this.passengerData.gender) {
            alert('Please select gender for adult passenger');
            return false;
        }

        const passengerCopy = { ...this.passengerData };
        passengerCopy.stay_duration = this.parseStayDurationDays(this.passengerData.stay_duration) || this.passengerData.stay_duration;
        
        if (this.editingPassengerIndex !== null) {
            this.passengers[this.editingPassengerIndex] = passengerCopy;
        } else {
            this.passengers.push(passengerCopy);
        }
        this.passengerCount = this.passengers.length;
        this.closePassengerModal();
        return true;
    },

    removePassenger(index) {
        if (confirm('Are you sure you want to remove this passenger?')) {
            this.passengers.splice(index, 1);
            this.passengerCount = this.passengers.length;
        }
    },

    openDiscountModal() {
        this.discountModalVisible = true;
    },

    closeDiscountModal() {
        this.discountModalVisible = false;
    },

    openPaymentModal() {
        const packageSelect = document.querySelector('#bookingPackage');
        const packageValue = parseInt(packageSelect?.selectedOptions[0]?.dataset?.packageValue) || 0;
        const totalPackageValue = (packageValue * this.passengers.length) + this.fingerprintCharge;
        const due = totalPackageValue - this.bookingData.discount_value;
        
        const totalEl = document.getElementById('paymentTotalPackageValue');
        const paidEl = document.getElementById('paymentPaid');
        const dueEl = document.getElementById('paymentDue');
        
        if (totalEl) totalEl.textContent = totalPackageValue + ' SAR';
        if (paidEl) paidEl.textContent = '0 SAR';
        if (dueEl) dueEl.textContent = due + ' SAR';
        
        this.paymentData = {
            currency: 'SAR',
            method: 'Cash',
            bank_method: '',
            trx_id: '',
            amount_sar: '',
            amount_bdt: ''
        };
        
        this.paymentModalVisible = true;
    },

    closePaymentModal() {
        this.paymentModalVisible = false;
    },

    handlePaymentCurrencyChange() {
        // Alpine reactivity handles the visibility via x-show
    },

    handlePaymentMethodChange() {
        // Alpine reactivity handles the visibility via x-show
    },

    savePayment() {
        const amountSAR = parseFloat(this.paymentData.amount_sar) || 0;
        const amountBDT = parseFloat(this.paymentData.amount_bdt) || 0;
        
        if (amountSAR === 0 && amountBDT === 0) {
            alert('Please enter payment amount');
            return;
        }
        
        console.log('Payment saved:', {
            currency: this.paymentData.currency,
            method: this.paymentData.method,
            bank_method: this.paymentData.bank_method,
            trx_id: this.paymentData.trx_id,
            amount_sar: amountSAR,
            amount_bdt: amountBDT
        });
        
        alert('Payment saved successfully!');
        this.closePaymentModal();
    },

    calculateDiscount() {
        const discountType = document.getElementById('discountType')?.value || 'fixed';
        const discountValue = parseFloat(document.getElementById('discountValue')?.value) || 0;
        
        const originalTotal = parseFloat(document.getElementById('discountOriginalTotal')?.value) || 0;
        
        let discountAmount = 0;
        if (discountType === 'percentage') {
            discountAmount = originalTotal * discountValue / 100;
        } else {
            discountAmount = discountValue;
        }
        
        const newTotal = Math.max(0, originalTotal - discountAmount);
        
        const discountAmountEl = document.getElementById('discountAmount');
        const newTotalEl = document.getElementById('discountNewTotal');
        
        if (discountAmountEl) discountAmountEl.value = Math.round(discountAmount);
        if (newTotalEl) newTotalEl.value = Math.round(newTotal);
    },

    applyDiscount() {
        this.bookingData.discount_type = document.getElementById('discountType')?.value || 'fixed';
        this.bookingData.discount_value = parseFloat(document.getElementById('discountValue')?.value) || 0;
        this.closeDiscountModal();
    },

    searchBookings() {
        console.log('Searching bookings:', this.searchTerm);
    },

    submitForm(e) {
        if (!this.selectedCustomer) {
            alert('Please select a customer');
            e.preventDefault();
            return false;
        }
        if (this.passengers.length === 0) {
            alert('Please add at least one passenger');
            e.preventDefault();
            return false;
        }
        if (!this.bookingData.district_id) {
            alert('Please select a district');
            e.preventDefault();
            return false;
        }
        if (!this.bookingData.fingerprint_location) {
            alert('Please select fingerprint location');
            e.preventDefault();
            return false;
        }
        if (!this.bookingData.fingerprint_office) {
            alert('Please select fingerprint office');
            e.preventDefault();
            return false;
        }
        return true;
    },

    toggleReferralFields() {
        const iqamaType = document.getElementById('customerIqamaType');
        const referralFields = document.getElementById('referralFields');
        
        if (!iqamaType || !referralFields) return;
        
        if (iqamaType.value === 'Referral') {
            referralFields.classList.remove('hidden');
        } else {
            referralFields.classList.add('hidden');
        }
    },

    toggleCustomerIqamaField() {
        const iqamaType = document.getElementById('customerIqamaType');
        const iqamaField = document.getElementById('customerIqamaField');
        
        if (!iqamaType || !iqamaField) return;
        
        if (iqamaType.value === 'None') {
            iqamaField.classList.add('hidden');
        } else {
            iqamaField.classList.remove('hidden');
        }
    },

    generateFlightDateRangeOptions() {
        const select = document.getElementById('passengerFlightDateRange');
        if (!select) return;
        
        select.innerHTML = '<option value="">Select Date Range</option>';
        
        const startDate = new Date();
        startDate.setDate(startDate.getDate() + 30);
        
        const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        
        for (let i = 0; i < 4; i++) {
            for (let week = 0; week < 4; week++) {
                const rangeStart = new Date(startDate);
                rangeStart.setDate(rangeStart.getDate() + (i * 40) + (week * 10));
                
                const rangeEnd = new Date(rangeStart);
                rangeEnd.setDate(rangeEnd.getDate() + 9);
                
                const startStr = `${months[rangeStart.getMonth()]} ${rangeStart.getDate()}, ${rangeStart.getFullYear()}`;
                const endStr = `${months[rangeEnd.getMonth()]} ${rangeEnd.getDate()}, ${rangeEnd.getFullYear()}`;
                const displayText = `${startStr} - ${endStr}`;
                
                const option = document.createElement('option');
                option.value = displayText;
                option.textContent = displayText;
                select.appendChild(option);
            }
        }
    },

    updateCheckboxState() {
        const withOffer = document.getElementById('passengerWithOffer');
        const refundable = document.getElementById('passengerRefundable');

        if (withOffer && refundable) {
            if (withOffer.checked) {
                refundable.checked = false;
                refundable.disabled = true;
            } else {
                refundable.disabled = false;
            }
        }
    }
}));

Alpine.data('createBookingApp', () => ({
    activeTab: 'booking',
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
    customerModalVisible: false,
    discountModalVisible: false,
    paymentModalVisible: false,
    customDurationModalVisible: false,
    paymentData: {
        currency: 'SAR',
        method: 'Cash',
        bank_method: '',
        trx_id: '',
        amount_sar: '',
        amount_bdt: ''
    },
    newCustomer: {
        name: '',
        iqama_type: '',
        iqama_no: '',
        passport_no: '',
        mobile_no: '',
        ref_iqama_no: '',
        ref_mobile_no: '',
        ref_iqama_doc: null,
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
        baggage_weight: '',
        address: '',
        with_offer: false,
        refundable: false,
        customDurationDays: ''
    },
    allTickets: [],
    filteredTickets: [],
    packages: [],

    init() {
        const urlParams = new URLSearchParams(window.location.search);
        const tab = urlParams.get('tab');
        if (tab) {
            this.activeTab = tab;
        }
        if (typeof window.__bookingServerData !== 'undefined') {
            if (window.__bookingServerData.ticketFares) {
                this.allTickets = window.__bookingServerData.ticketFares;
            }
            if (window.__bookingServerData.packages) {
                this.packages = window.__bookingServerData.packages;
            }
            if (window.__bookingServerData.preSelectedPackageId) {
                this.bookingData.package_id = window.__bookingServerData.preSelectedPackageId;
            }
        }
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
            ref_iqama_doc: null,
            address: ''
        };
        const fileInput = document.getElementById('ref_iqama_doc');
        if (fileInput) fileInput.value = '';
        const fileName = document.getElementById('ref_iqama_doc_filename');
        if (fileName) fileName.textContent = 'click to upload';
        const docsList = document.getElementById('customer_docs_list');
        if (docsList) docsList.innerHTML = '';
        const docsInput = document.getElementById('customer_docs');
        if (docsInput) docsInput.value = '';
        const bookingDocsList = document.getElementById('booking_customer_docs_list');
        if (bookingDocsList) bookingDocsList.innerHTML = '';
        const bookingDocsInput = document.getElementById('booking_customer_docs');
        if (bookingDocsInput) bookingDocsInput.value = '';
        this.bookingData = {
            fingerprint_location: 'Office',
            fingerprint_office: '',
            district_id: '',
            package_id: '',
            discount_type: 'fixed',
            discount_value: 0,
            remarks: ''
        };
    },

    async searchCustomers() {
        if (this.customerSearch.length < 2) {
            this.customerSuggestions = [];
            return;
        }
        try {
            const response = await fetch(`/api/customers/search?q=${encodeURIComponent(this.customerSearch)}`);
            this.customerSuggestions = await response.json();
        } catch (e) {
            console.error('Customer search error:', e);
            this.customerSuggestions = [];
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

    parseStayDurationDays(stayDuration) {
        if (!stayDuration) return null;
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
        
        console.log('=== Passenger Type Calculation ===');
        console.log('DOB:', dob);
        console.log('Stay Duration:', this.passengerData.stay_duration);
        
        const stayDays = this.parseStayDurationDays(this.passengerData.stay_duration);
        console.log('Stay Days (parsed):', stayDays);
        
        if (stayDays !== null) {
            const adjustmentDays = stayDays < 30 ? 30 : 90;
            console.log('Adjustment Days:', adjustmentDays);
            
            const effectiveDate = new Date(dobDate);
            effectiveDate.setDate(effectiveDate.getDate() - adjustmentDays);
            console.log('Effective DOB (after -days):', effectiveDate.toISOString().split('T')[0]);
            
            const ageInMonthsWithDuration = (today.getFullYear() - effectiveDate.getFullYear()) * 12 + (today.getMonth() - effectiveDate.getMonth());
            const dayDiff = today.getDate() - effectiveDate.getDate();
            const finalAgeInMonths = dayDiff < 0 ? ageInMonthsWithDuration - 1 : ageInMonthsWithDuration;
            
            console.log('Base Age (months):', ageInMonths);
            console.log('Effective Age (months):', finalAgeInMonths);
            
            ageInMonths = Math.max(ageInMonths, finalAgeInMonths);
        }
        
        console.log('Final Age (months):', ageInMonths);
        
        let calculatedType = 'Adult';
        if (ageInMonths < 24) {
            calculatedType = 'Infant';
        } else if (ageInMonths < 144) {
            calculatedType = 'Child';
        }
        
        console.log('Calculated Type:', calculatedType);
        console.log('================================');
        
        this.passengerData.passenger_type = calculatedType;
        this.updateBaggageWeight();
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

    updateBaggageWeight() {
        const ticketFareId = this.passengerData.ticket_fare_id;
        const passengerType = this.passengerData.passenger_type;
        const routeType = this.passengerData.route_type;

        if (!ticketFareId) {
            this.passengerData.baggage_weight = '';
            return;
        }
        if (!passengerType || !routeType) {
            this.passengerData.baggage_weight = 'Select passenger type to see baggage';
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

    async fetchBaggageAllowance(route, airline, travelClass, passengerType, direction) {
        try {
            const params = new URLSearchParams({ route, airline, travel_class: travelClass, passenger_type: passengerType, direction });
            const response = await fetch(`/api/ticket-fares/baggage?${params}`);
            const data = await response.json();
            if (data.allowance) {
                this.passengerData.baggage_weight = data.allowance + 'kg';
            } else {
                this.passengerData.baggage_weight = '';
            }
        } catch (e) {
            console.error('Error fetching baggage allowance:', e);
            this.passengerData.baggage_weight = '';
        }
    },

    async updateFingerprintCharge() {
        if (!this.bookingData.district_id) return;
        try {
            const response = await fetch(`/api/bookings/fingerprint-charge?district_id=${this.bookingData.district_id}&location=${this.bookingData.fingerprint_location}`);
            const data = await response.json();
            if (data.charge !== undefined) {
                this.fingerprintCharge = data.charge;
            }
        } catch (e) {
            console.error('Fingerprint charge error:', e);
        }
    },

    openPassengerModal() {
        this.editingPassengerIndex = null;
        let packageTicketFareId = null;
        if (this.bookingData.package_id) {
            const pkg = this.packages.find(p => p.id == this.bookingData.package_id);
            if (pkg && pkg.ticket_fare_id) {
                packageTicketFareId = pkg.ticket_fare_id;
            }
        }
        this.passengerData = {
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
            stay_duration_display: '',
            route_type: '',
            flight_type: '',
            route: '',
            airline: '',
            class: '',
            ticket_fare_id: '',
            flight_date_range: '',
            baggage_weight: '',
            address: '',
            with_offer: false,
            refundable: false,
            customDurationDays: ''
        };
        if (packageTicketFareId) {
            const ticket = this.allTickets.find(t => t.id == packageTicketFareId);
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
                this.$nextTick(() => {
                    this.passengerData.ticket_fare_id = String(packageTicketFareId);
                });
            }
        } else {
            this.filteredTickets = [];
        }
        this.passengerModalVisible = true;
    },

    editPassenger(index) {
        this.editingPassengerIndex = index;
        const passenger = this.passengers[index];
        this.passengerData = { ...passenger };
        this.passengerData.ticket_fare_id = this.passengerData.ticket_fare_id ? String(this.passengerData.ticket_fare_id) : '';

        if (typeof this.passengerData.stay_duration === 'number' && this.passengerData.stay_duration >= 30 && this.passengerData.stay_duration <= 89) {
            this.passengerData.stay_duration_display = `Customized (${this.passengerData.stay_duration} Days)`;
            this.$nextTick(() => {
                const select = document.querySelector('select[x-model="passengerData.stay_duration"]');
                if (select) {
                    let customOption = Array.from(select.options).find(opt => opt.value.startsWith('Customized'));
                    if (!customOption) {
                        customOption = document.createElement('option');
                        select.appendChild(customOption);
                    }
                    customOption.value = `Customized (${this.passengerData.stay_duration} Days)`;
                    customOption.textContent = `Customized (${this.passengerData.stay_duration} Days)`;
                    select.value = `Customized (${this.passengerData.stay_duration} Days)`;
                }
            });
        }
        if (this.passengerData.ticket_fare_id) {
            const ticket = this.allTickets.find(t => t.id == this.passengerData.ticket_fare_id);
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
            }
        } else {
            this.filteredTickets = [];
        }
        this.passengerModalVisible = true;
    },

    closePassengerModal() {
        this.passengerModalVisible = false;
        this.editingPassengerIndex = null;
    },

    savePassenger() {
        if (!this.passengerData.first_name || !this.passengerData.last_name || !this.passengerData.passport_no || !this.passengerData.date_of_birth) {
            alert('Please fill in all required fields');
            return;
        }
        const passengerCopy = { ...this.passengerData };
        passengerCopy.stay_duration = this.parseStayDurationDays(this.passengerData.stay_duration) || this.passengerData.stay_duration;
        if (this.editingPassengerIndex !== null) {
            this.passengers[this.editingPassengerIndex] = passengerCopy;
        } else {
            this.passengers.push(passengerCopy);
        }
        this.passengerCount = this.passengers.length;
        this.closePassengerModal();
    },

    getTicketDisplayText(ticket) {
        const price = ticket.selling_fare ? ticket.selling_fare + ' SAR' : '';
        const type = ticket.ticket_type.charAt(0).toUpperCase() + ticket.ticket_type.slice(1);
        switch (ticket.ticket_type) {
            case 'offer':
                const offer = ticket.offer_price ? ' | ' + ticket.offer_price + ' SAR' : '';
                return `${ticket.route} | ${type} | ${price}${offer}`;
            case 'group':
                const seats = ticket.available_seats ? ' | ' + ticket.available_seats + ' seats' : '';
                return `${ticket.route} | ${type} | ${price}${seats}`;
            default:
                return `${ticket.route} | ${type} | ${price}`;
        }
    },

    filterTickets() {
        if (!this.passengerData.route_type || !this.passengerData.flight_type) {
            this.filteredTickets = [];
            this.passengerData.ticket_fare_id = '';
            this.passengerData.route = '';
            this.passengerData.airline = '';
            this.passengerData.class = '';
            return;
        }
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
    },

    updateRouteAirlineClass() {
        if (!this.passengerData.ticket_fare_id) {
            this.passengerData.route = '';
            this.passengerData.airline = '';
            this.passengerData.class = '';
            return;
        }
        const ticket = this.filteredTickets.find(t => t.id == this.passengerData.ticket_fare_id);
        if (ticket) {
            this.passengerData.route = ticket.route;
            this.passengerData.airline = ticket.airline || '';
            this.passengerData.class = ticket.airline_class || '';
        }
    },

    removePassenger(index) {
        if (confirm('Are you sure you want to remove this passenger?')) {
            this.passengers.splice(index, 1);
            this.passengerCount = this.passengers.length;
        }
    },

    openCustomerModal() {
        this.newCustomer = {
            name: '',
            iqama_type: '',
            iqama_no: '',
            passport_no: this.customerSearch,
            mobile_no: '',
            ref_iqama_no: '',
            ref_mobile_no: '',
            ref_iqama_doc: null,
            address: ''
        };
        const fileInput = document.getElementById('ref_iqama_doc');
        if (fileInput) fileInput.value = '';
        const fileName = document.getElementById('ref_iqama_doc_filename');
        if (fileName) fileName.textContent = 'click to upload';
        const docsList = document.getElementById('customer_docs_list');
        if (docsList) docsList.innerHTML = '';
        const docsInput = document.getElementById('customer_docs');
        if (docsInput) docsInput.value = '';
        this.customerModalVisible = true;
        this.customerSuggestions = [];
    },

    closeCustomerModal() {
        this.customerModalVisible = false;
    },

    async submitNewCustomer() {
        try {
            const formData = new FormData();
            Object.keys(this.newCustomer).forEach(key => {
                if (this.newCustomer[key] !== null) {
                    formData.append(key, this.newCustomer[key]);
                }
            });
            const fileInput = document.getElementById('ref_iqama_doc');
            if (fileInput && fileInput.files[0]) {
                formData.append('ref_iqama_doc', fileInput.files[0]);
            }
            const docsInput = document.getElementById('customer_docs');
            if (docsInput) {
                Array.from(docsInput.files).forEach(file => {
                    formData.append('customer_docs[]', file);
                });
            }
            const response = await fetch('/customers', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                },
                body: formData
            });
            const text = await response.text();
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
    },

    openPaymentModal() {
        const packageSelect = document.querySelector('#bookingPackage');
        const packageValue = parseInt(packageSelect?.selectedOptions[0]?.dataset?.packageValue) || 0;
        const totalPackageValue = (packageValue * this.passengers.length) + this.fingerprintCharge;
        const due = totalPackageValue - this.bookingData.discount_value;
        const totalEl = document.getElementById('paymentTotalPackageValue');
        const paidEl = document.getElementById('paymentPaid');
        const dueEl = document.getElementById('paymentDue');
        if (totalEl) totalEl.textContent = totalPackageValue + ' SAR';
        if (paidEl) paidEl.textContent = '0 SAR';
        if (dueEl) dueEl.textContent = due + ' SAR';
        this.paymentData = {
            currency: 'SAR',
            method: 'Cash',
            bank_method: '',
            trx_id: '',
            amount_sar: '',
            amount_bdt: ''
        };
        this.paymentModalVisible = true;
    },

    closePaymentModal() {
        this.paymentModalVisible = false;
    },

    handlePaymentCurrencyChange() {},
    handlePaymentMethodChange() {},

    savePayment() {
        const amountSAR = parseFloat(this.paymentData.amount_sar) || 0;
        const amountBDT = parseFloat(this.paymentData.amount_bdt) || 0;
        if (amountSAR === 0 && amountBDT === 0) {
            alert('Please enter payment amount');
            return;
        }
        console.log('Payment saved:', {
            currency: this.paymentData.currency,
            method: this.paymentData.method,
            bank_method: this.paymentData.bank_method,
            trx_id: this.paymentData.trx_id,
            amount_sar: amountSAR,
            amount_bdt: amountBDT
        });
        alert('Payment saved successfully!');
        this.closePaymentModal();
    },

    openDiscountModal() {
        this.discountModalVisible = true;
    },

    closeDiscountModal() {
        this.discountModalVisible = false;
    },

    submitForm(e) {
        e.preventDefault();
        if (!this.selectedCustomer) {
            alert('Please select a customer');
            return;
        }
        if (this.passengers.length === 0) {
            alert('Please add at least one passenger');
            return;
        }
        const formData = new FormData(e.target);
        const bookingDocsInput = document.getElementById('booking_customer_docs');
        if (bookingDocsInput) {
            Array.from(bookingDocsInput.files).forEach(file => {
                formData.append('booking_customer_docs[]', file);
            });
        }
        try {
            const response = fetch(e.target.action, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            }).then(resp => {
                if (resp.redirected) {
                    window.location.href = resp.url;
                    return;
                }
                return resp.json().then(data => {
                    if (data.success || resp.ok) {
                        window.location.href = '/bookings';
                    } else {
                        alert(data.message || 'Failed to create booking');
                    }
                });
            });
        } catch (err) {
            console.error(err);
            alert('Failed to create booking');
        }
    },

    toggleReferralFields() {
        const iqamaType = document.getElementById('customerIqamaType');
        const referralFields = document.getElementById('referralFields');
        if (!iqamaType || !referralFields) return;
        if (iqamaType.value === 'Referral') {
            referralFields.classList.remove('hidden');
        } else {
            referralFields.classList.add('hidden');
        }
    },

    toggleCustomerIqamaField() {
        const iqamaType = document.getElementById('customerIqamaType');
        const iqamaField = document.getElementById('customerIqamaField');
        if (!iqamaType || !iqamaField) return;
        if (iqamaType.value === 'None') {
            iqamaField.classList.add('hidden');
        } else {
            iqamaField.classList.remove('hidden');
        }
    },

    updateCheckboxState() {
        const withOffer = document.getElementById('passengerWithOffer');
        const refundable = document.getElementById('passengerRefundable');
        if (withOffer && refundable) {
            if (withOffer.checked) {
                refundable.checked = false;
                refundable.disabled = true;
            } else {
                refundable.disabled = false;
            }
        }
    }
}));