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
    paymentSaved: false,
    exchangeRate: window.__bookingServerData?.currentCurrencyRate || 0,

    hasPaymentData() {
        const amountSar = parseFloat(this.paymentData.amount_sar) || 0;
        const amountBdt = parseFloat(this.paymentData.amount_bdt) || 0;
        return this.paymentSaved && (amountSar > 0 || amountBdt > 0);
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
        fingerprint_charge_id: '',
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
        this.paymentData = {
            currency: 'SAR',
            method: 'Cash',
            bank_method: '',
            trx_id: '',
            amount_sar: '',
            amount_bdt: ''
        };
        this.paymentSaved = false;
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
        // Only recalculate if editing an existing passenger (not adding new one)
        if (this.editingPassengerIndex !== null && this.editingPassengerIndex !== undefined) {
            this.recalculateCurrentPassenger(this.editingPassengerIndex);
        }
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
            this.bookingData.fingerprint_charge_id = '';
            return;
        }
        try {
            const response = await fetch(`/api/bookings/fingerprint-charge?district_id=${this.bookingData.district_id}&location=${this.bookingData.fingerprint_location}`);
            const data = await response.json();
            if (data.error) {
                alert(data.error);
                this.fingerprintCharge = 0;
                this.bookingData.fingerprint_charge_id = '';
                return;
            }
            this.fingerprintCharge = data.charge || 0;
            this.bookingData.fingerprint_charge_id = data.fingerprint_charge_id || '';
        } catch (e) {
            console.error('Fingerprint charge error:', e);
            this.fingerprintCharge = 0;
            this.bookingData.fingerprint_charge_id = '';
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
        if (summaryTotalBeforeDiscount) summaryTotalBeforeDiscount.textContent = totalBeforeDiscount > 0 ? `${totalBeforeDiscount} SAR` : '-';
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
            this.passengers[this.editingPassengerIndex] = { ...this.passengerData };
            this.recalculateCurrentPassenger(this.editingPassengerIndex);
        } else {
            this.passengers.push({ ...this.passengerData });
            this.recalculateAllPassengerValues();
        }
        this.passengerCount = this.passengers.length;
        this.closePassengerModal();
        return true;
    },

    removePassenger(index) {
        if (confirm('Are you sure you want to remove this passenger?')) {
            this.passengers.splice(index, 1);
            this.passengerCount = this.passengers.length;
            this.recalculateAllPassengerValues();
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
        if (this.paymentData.currency === 'BDT') {
            if (this.paymentData.amount_sar) {
                this.handleSarAmountInput();
            }
        } else {
            if (this.paymentData.amount_bdt) {
                this.handleBdtAmountInput();
            } else {
                this.paymentData.amount_bdt = '';
                if (this.paymentData.amount_sar) {
                    this.handleSarAmountInput();
                }
            }
        }
    },

    handleSarAmountInput() {
        const sarAmount = parseFloat(this.paymentData.amount_sar) || 0;
        
        if (sarAmount > 0 && this.exchangeRate > 0) {
            const convertedBdt = (sarAmount * this.exchangeRate).toFixed(2);
            this.paymentData.amount_bdt = convertedBdt;
        } else {
            this.paymentData.amount_bdt = '';
        }
    },

    handleBdtAmountInput() {
        const bdtAmount = parseFloat(this.paymentData.amount_bdt) || 0;
        
        if (bdtAmount > 0 && this.exchangeRate > 0) {
            const convertedSar = (bdtAmount / this.exchangeRate).toFixed(2);
            this.paymentData.amount_sar = convertedSar;
        } else if (bdtAmount > 0 && this.exchangeRate <= 0) {
            this.paymentData.amount_sar = '';
        }
    },

    convertSarToBdt() {
        if (this.paymentData.currency === 'SAR' && this.paymentData.amount_sar && this.exchangeRate > 0) {
            this.paymentData.amount_bdt = (parseFloat(this.paymentData.amount_sar) * this.exchangeRate).toFixed(2);
        }
    },

    convertBdtToSar() {
        if (this.paymentData.currency === 'BDT' && this.paymentData.amount_bdt && this.exchangeRate > 0) {
            this.paymentData.amount_sar = (parseFloat(this.paymentData.amount_bdt) / this.exchangeRate).toFixed(2);
        }
    },

    handlePaymentMethodChange() {
        // Alpine reactivity handles the visibility via x-show
    },

    savePayment() {
        const amountSAR = parseFloat(this.paymentData.amount_sar) || 0;
        const amountBDT = parseFloat(this.paymentData.amount_bdt) || 0;
        
        if (amountSAR === 0) {
            alert('Please enter payment amount');
            return;
        }
        
        if (this.paymentData.currency === 'BDT' && amountBDT > 0 && this.exchangeRate <= 0) {
            alert('Cannot process BDT payment. Exchange rate not available.');
            return;
        }
        
        this.paymentSaved = true;
        
        console.log('Payment saved:', {
            currency: this.paymentData.currency,
            method: this.paymentData.method,
            bank_method: this.paymentData.bank_method,
            trx_id: this.paymentData.trx_id,
            amount_sar: amountSAR,
            amount_bdt: amountBDT
        });
        
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
    paymentSaved: false,
    exchangeRate: window.__bookingServerData?.currentCurrencyRate || 0,

    hasPaymentData() {
        const amountSar = parseFloat(this.paymentData.amount_sar) || 0;
        const amountBdt = parseFloat(this.paymentData.amount_bdt) || 0;
        return this.paymentSaved && (amountSar > 0 || amountBdt > 0);
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
        fingerprint_charge_id: '',
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
        flight_date_from: '',
        flight_date_to: '',
        baggage_weight: '',
        address: '',
        with_offer: false,
        refundable: false,
        customDurationDays: ''
    },
    allTickets: [],
    filteredTickets: [],
    allPackages: [],
    passengerPackageValues: {},

    get totalPackageValue() {
        return Object.values(this.passengerPackageValues).reduce(
            (sum, v) => sum + (parseFloat(v) || 0), 0
        );
    },
    get grandTotalValue() {
        return this.totalPackageValue + (parseFloat(this.fingerprintCharge) || 0);
    },
    get discountedTotal() {
        const disc = parseFloat(this.bookingData.discount_value) || 0;
        if (disc <= 0) return null;
        const grand = this.grandTotalValue;
        const discType = this.bookingData.discount_type;
        const discAmt = discType === 'percentage' ? grand * disc / 100 : disc;
        return grand - discAmt;
    },

    init() {
        const serverData = window.__bookingServerData || {};
        this.packages = serverData.packages || [];
        this.allPackages = this.packages.map(p => ({
            ...p,
            id: String(p.id),
            ticket_fare_id: p.ticket_fare_id ? String(p.ticket_fare_id) : null,
        }));
        this.allTickets = serverData.ticketFares || [];
        this.filteredTickets = this.allTickets;

        this.$nextTick(() => {
            if (serverData.preSelectedPackageId) {
                this.bookingData.package_id = String(serverData.preSelectedPackageId);
            }
            this.recalculateAllPassengerValues();
        });
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
        this.paymentData = {
            currency: 'SAR',
            method: 'Cash',
            bank_method: '',
            trx_id: '',
            amount_sar: '',
            amount_bdt: ''
        };
        this.paymentSaved = false;
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
        // Only recalculate if editing an existing passenger (not adding new one)
        if (this.editingPassengerIndex !== null && this.editingPassengerIndex !== undefined) {
            this.recalculateCurrentPassenger(this.editingPassengerIndex);
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
        if (!this.bookingData.district_id) {
            this.fingerprintCharge = 0;
            this.bookingData.fingerprint_charge_id = '';
            return;
        }
        try {
            const response = await fetch(`/api/bookings/fingerprint-charge?district_id=${this.bookingData.district_id}&location=${this.bookingData.fingerprint_location}`);
            const data = await response.json();
            if (data.error) {
                alert(data.error);
                this.fingerprintCharge = 0;
                this.bookingData.fingerprint_charge_id = '';
                return;
            }
            this.fingerprintCharge = data.charge || 0;
            this.bookingData.fingerprint_charge_id = data.fingerprint_charge_id || '';
        } catch (e) {
            console.error('Fingerprint charge error:', e);
            this.fingerprintCharge = 0;
            this.bookingData.fingerprint_charge_id = '';
        }
    },

    calculatePackageValue(passenger, selectedPackage) {
        const ticketFareId = passenger.ticket_fare_id;
        const serviceRequired = passenger.service_required || 'all';
        const passengerType = (passenger.passenger_type || 'adult').toLowerCase();

        const ticket = this.allTickets.find(t => String(t.id) === String(ticketFareId));
        if (!ticket) return 0;

        const sellingFare = parseFloat(ticket.selling_fare) || 0;
        let ticketAmount = sellingFare;
        if (passengerType === 'child') {
            const pct = parseFloat(ticket.child_fare_percentage) || 0;
            ticketAmount = sellingFare * pct / 100;
        } else if (passengerType === 'infant') {
            const pct = parseFloat(ticket.infant_fare_percentage) || 0;
            ticketAmount = sellingFare * pct / 100;
        }

        const visaPrice = parseFloat(selectedPackage?.visa_selling_price) || 0;
        const serviceCharge = parseFloat(selectedPackage?.service_charge) || 0;

        let visaAmount = 0;
        let scAmount = 0;
        if (serviceRequired !== 'ticket_only') {
            visaAmount = visaPrice;
            scAmount = serviceCharge;
        } else {
            scAmount = serviceCharge;
        }

        return ticketAmount + visaAmount + scAmount;
    },

    recalculateAllPassengerValues() {
        const pkg = this.allPackages.find(p => String(p.id) === String(this.bookingData.package_id));
        this.passengers.forEach((p, index) => {
            this.passengerPackageValues[index] = this.calculatePackageValue(p, pkg);
        });
    },

    recalculateCurrentPassenger(index) {
        // Skip recalculation if passenger doesn't exist yet (adding new passenger)
        if (index === null || index === undefined || !this.passengers[index]) {
            return;
        }
        const pkg = this.allPackages.find(p => String(p.id) === String(this.bookingData.package_id));
        this.passengerPackageValues[index] = this.calculatePackageValue(this.passengers[index], pkg);
    },

    onPackageChange() {
        this.recalculateAllPassengerValues();
    },

    openPassengerModal() {
        this.editingPassengerIndex = null;
        let packageTicketFareId = null;
        if (this.bookingData.package_id) {
            const pkg = this.allPackages.find(p => String(p.id) === String(this.bookingData.package_id));
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
            flight_date_from: '',
            flight_date_to: '',
            baggage_weight: '',
            address: '',
            with_offer: false,
            refundable: false,
            customDurationDays: ''
        };
        if (packageTicketFareId) {
            this.passengerData.baggage_weight = 'Define Passenger Type';
        } else {
            this.passengerData.baggage_weight = 'Select a Ticket and Define Passenger Type';
        }
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
                    this.calculateFlightDateRange();
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

                if (this.passengerData.flight_date_from && this.passengerData.flight_date_to) {
                    this.generateFlightDateRangeForEdit(this.passengerData.flight_date_from, this.passengerData.flight_date_to);
                }
            }
        } else {
            this.filteredTickets = [];
        }
        this.passengerModalVisible = true;
    },

    generateFlightDateRangeForEdit(fromDate, toDate) {
        const fromParts = fromDate.split('-');
        const toParts = toDate.split('-');
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
                }
            }
        });
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

        if (this.passengerData.flight_date_range) {
            const parsedDates = this.parseFlightDateRange(this.passengerData.flight_date_range);
            if (parsedDates) {
                passengerCopy.flight_date_from = parsedDates.from;
                passengerCopy.flight_date_to = parsedDates.to;
            }
        }

        if (this.editingPassengerIndex !== null) {
            this.passengers[this.editingPassengerIndex] = { ...passengerCopy };
            this.recalculateCurrentPassenger(this.editingPassengerIndex);
        } else {
            this.passengers.push({ ...passengerCopy });
            this.recalculateAllPassengerValues();
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

    calculateFlightDateRange() {
        const route = this.passengerData.route;
        const airline = this.passengerData.airline;
        const travelClass = this.passengerData.class;

        console.log('[DateRange] calculateFlightDateRange called:', { route, airline, travelClass });

        if (!route || !airline || !travelClass) {
            console.log('[DateRange] Missing required data, skipping');
            this.populateFlightDateRangeOptions([]);
            return;
        }

        this.fetchFlightDateGapAndGenerateRange(route, airline, travelClass);
    },

    async fetchFlightDateGapAndGenerateRange(route, airline, travelClass) {
        try {
            const params = new URLSearchParams({ route, airline, travel_class: travelClass });
            console.log('[DateRange] Calling API:', `/api/ticket-fares/flight-date-gap?${params}`);
            const response = await fetch(`/api/ticket-fares/flight-date-gap?${params}`);
            const data = await response.json();

            console.log('[DateRange] API Response:', data);

            if (data.default_gap !== undefined) {
                const additionalGap = parseInt(data.additional_gap) || 0;
                const defaultGap = parseInt(data.default_gap) || 30;
                console.log('[DateRange] Generating ranges with:', { defaultGap, additionalGap });
                this.generateFlightDateRangeOptions(defaultGap, additionalGap);
            } else {
                console.log('[DateRange] No default_gap in response, using fallback');
                this.generateFlightDateRangeOptions(30, 0);
            }
        } catch (e) {
            console.error('[DateRange] Error fetching flight date gap:', e);
            this.generateFlightDateRangeOptions(30, 0);
        }
    },

    generateFlightDateRangeOptions(defaultGap, additionalGap) {
        const finalGap = defaultGap + additionalGap;
        const expectedDate = new Date();
        expectedDate.setDate(expectedDate.getDate() + finalGap);

        const day = expectedDate.getDate();
        let startMonthOffset = 0;
        let startSlot = 0;

        if (day >= 1 && day <= 5) {
            startMonthOffset = 0;
            startSlot = 0;
        } else if (day >= 6 && day <= 10) {
            startMonthOffset = 0;
            startSlot = 1;
        } else if (day >= 11 && day <= 15) {
            startMonthOffset = 0;
            startSlot = 1;
        } else if (day >= 16 && day <= 20) {
            startMonthOffset = 0;
            startSlot = 2;
        } else if (day >= 21 && day <= 25) {
            startMonthOffset = 0;
            startSlot = 2;
        } else if (day >= 26 && day <= 31) {
            startMonthOffset = 1;
            startSlot = 0;
        }

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

    onTicketChange() {
        this.updateRouteAirlineClass();
        this.updateBaggageWeight();
        this.calculateFlightDateRange();
        // Only recalculate if editing an existing passenger
        if (this.editingPassengerIndex !== null && this.editingPassengerIndex !== undefined) {
            this.recalculateCurrentPassenger(this.editingPassengerIndex);
        }
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
            console.log('[DateRange] updateRouteAirlineClass found ticket:', ticket.route, ticket.airline, ticket.airline_class);
        } else {
            console.log('[DateRange] updateRouteAirlineClass: ticket not found for id:', this.passengerData.ticket_fare_id);
        }
    },

    removePassenger(index) {
        if (confirm('Are you sure you want to remove this passenger?')) {
            this.passengers.splice(index, 1);
            this.passengerCount = this.passengers.length;
            this.recalculateAllPassengerValues();
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
        const totalPkgVal = this.totalPackageValue;
        const fpCharge = parseFloat(this.fingerprintCharge) || 0;
        const discount = parseFloat(this.bookingData.discount_value) || 0;
        const discountType = this.bookingData.discount_type;
        const grand = totalPkgVal + fpCharge;
        const discountAmount = discountType === 'percentage'
            ? grand * discount / 100
            : discount;
        const due = grand - discountAmount;
        const totalEl = document.getElementById('paymentTotalPackageValue');
        const paidEl = document.getElementById('paymentPaid');
        const dueEl = document.getElementById('paymentDue');
        if (totalEl) totalEl.textContent = grand.toFixed(2) + ' SAR';
        if (paidEl) paidEl.textContent = '0 SAR';
        if (dueEl) dueEl.textContent = due.toFixed(2) + ' SAR';
        this.paymentMaxAmount = due;
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
        if (this.paymentData.currency === 'BDT') {
            if (this.paymentData.amount_sar) {
                this.handleSarAmountInput();
            }
        } else {
            if (this.paymentData.amount_bdt) {
                this.handleBdtAmountInput();
            } else {
                this.paymentData.amount_bdt = '';
                if (this.paymentData.amount_sar) {
                    this.handleSarAmountInput();
                }
            }
        }
    },

    handleSarAmountInput() {
        const sarAmount = parseFloat(this.paymentData.amount_sar) || 0;
        
        if (sarAmount > 0 && this.exchangeRate > 0) {
            const convertedBdt = (sarAmount * this.exchangeRate).toFixed(2);
            this.paymentData.amount_bdt = convertedBdt;
        } else {
            this.paymentData.amount_bdt = '';
        }
    },

    handleBdtAmountInput() {
        const bdtAmount = parseFloat(this.paymentData.amount_bdt) || 0;
        
        if (bdtAmount > 0 && this.exchangeRate > 0) {
            const convertedSar = (bdtAmount / this.exchangeRate).toFixed(2);
            this.paymentData.amount_sar = convertedSar;
        } else if (bdtAmount > 0 && this.exchangeRate <= 0) {
            this.paymentData.amount_sar = '';
        }
    },

    handlePaymentMethodChange() {},
    convertSarToBdt() {
        if (this.paymentData.currency === 'SAR' && this.paymentData.amount_sar && this.exchangeRate > 0) {
            this.paymentData.amount_bdt = (parseFloat(this.paymentData.amount_sar) * this.exchangeRate).toFixed(2);
        }
    },

    convertBdtToSar() {
        if (this.paymentData.currency === 'BDT' && this.paymentData.amount_bdt && this.exchangeRate > 0) {
            this.paymentData.amount_sar = (parseFloat(this.paymentData.amount_bdt) / this.exchangeRate).toFixed(2);
        }
    },

    savePayment() {
        const amountSAR = parseFloat(this.paymentData.amount_sar) || 0;
        const amountBDT = parseFloat(this.paymentData.amount_bdt) || 0;
        
        if (amountSAR === 0) {
            alert('Please enter payment amount');
            return;
        }

        if (amountSAR > this.paymentMaxAmount) {
            alert('Payment amount cannot exceed the total booking value of ' + this.paymentMaxAmount.toFixed(2) + ' SAR');
            return;
        }
        
        if (this.paymentData.currency === 'BDT' && amountBDT > 0 && this.exchangeRate <= 0) {
            alert('Cannot process BDT payment. Exchange rate not available.');
            return;
        }
        
        this.paymentSaved = true;
        
        console.log('Payment saved:', {
            currency: this.paymentData.currency,
            method: this.paymentData.method,
            bank_method: this.paymentData.bank_method,
            trx_id: this.paymentData.trx_id,
            amount_sar: amountSAR,
            amount_bdt: amountBDT
        });
        
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
        if (!this.paymentSaved || (parseFloat(this.paymentData.amount_sar) || 0) <= 0) {
            alert('Please save a payment before submitting the booking');
            return;
        }
        // Validate required fields
        if (!this.bookingData.district_id) {
            alert('Please select a district');
            return;
        }
        if (!this.bookingData.fingerprint_office) {
            alert('Please select an office');
            return;
        }
        if (!this.bookingData.fingerprint_charge_id) {
            alert('Fingerprint charge not configured for selected district');
            return;
        }

        const formData = new FormData(e.target);
        const bookingDocsInput = document.getElementById('booking_customer_docs');
        if (bookingDocsInput) {
            Array.from(bookingDocsInput.files).forEach(file => {
                formData.append('booking_customer_docs[]', file);
            });
        }

        fetch(e.target.action, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            },
            body: formData
        })
        .then(resp => {
            if (resp.redirected) {
                window.location.href = resp.url;
                return;
            }
            if (!resp.ok) {
                return resp.json().then(data => {
                    if (data.errors) {
                        const firstError = Object.values(data.errors)[0];
                        alert(firstError[0] || 'Validation failed');
                    } else {
                        alert(data.message || 'An error occurred');
                    }
                    console.error('Submit failed:', resp.status, data);
                }).catch(() => {
                    return resp.text().then(text => {
                        alert('An error occurred while submitting the form.');
                        console.error('Submit failed:', resp.status, text);
                    });
                });
            }
            return resp.json().then(data => {
                if (data.success || resp.ok) {
                    window.location.href = data.url || '/bookings';
                } else {
                    alert(data.message || 'Failed to create booking');
                }
            });
        })
        .catch(err => {
            console.error('Submit error:', err);
            alert('An error occurred while submitting the form. Please check the console for details.');
        });
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
    },

    handleBookingCustomerDocsUpload(input) {
        const list = document.getElementById('booking_customer_docs_list');
        if (!list) return;
        list.innerHTML = '';
        Array.from(input.files).forEach(file => {
            const item = document.createElement('div');
            item.className = 'flex items-center justify-between text-sm text-slate-600 bg-slate-50 px-3 py-2 rounded';
            item.innerHTML = '<span class="truncate">' + file.name + '</span><button type="button" onclick="removeBookingCustomerDoc(this)" class="text-red-500 hover:text-red-700 ml-2 flex-shrink-0">×</button>';
            list.appendChild(item);
        });
    },

    removeBookingCustomerDoc(btn) {
        btn.parentElement.remove();
    },

    handlePassengerDocUpload(input) {
        const list = document.getElementById('passenger_doc_list');
        if (!list) return;
        list.innerHTML = '';
        Array.from(input.files).forEach(file => {
            const item = document.createElement('div');
            item.className = 'flex items-center justify-between text-sm text-slate-600 bg-slate-50 px-3 py-2 rounded';
            item.innerHTML = '<span class="truncate">' + file.name + '</span><button type="button" onclick="removePassengerDoc(this)" class="text-red-500 hover:text-red-700 ml-2 flex-shrink-0">×</button>';
            list.appendChild(item);
        });
    },

    removePassengerDoc(btn) {
        btn.parentElement.remove();
    },

    handleRefIqamaFileUpload(input) {
        const file = input.files[0];
        const display = document.getElementById('ref_iqama_doc_filename');
        if (file && display) display.textContent = file.name;
    },

    handleCustomerDocUpload(input) {
        const list = document.getElementById('customer_docs_list');
        if (!list) return;
        list.innerHTML = '';
        Array.from(input.files).forEach(file => {
            const item = document.createElement('div');
            item.className = 'flex items-center justify-between text-sm text-slate-600 bg-slate-50 px-3 py-2 rounded';
            item.innerHTML = '<span class="truncate">' + file.name + '</span><button type="button" onclick="removeCustomerDoc(this)" class="text-red-500 hover:text-red-700 ml-2 flex-shrink-0">×</button>';
            list.appendChild(item);
        });
    },

    removeCustomerDoc(btn) {
        btn.parentElement.remove();
    }
}));

Alpine.data('editBookingApp', () => ({
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
    paymentSaved: false,
    exchangeRate: window.__bookingServerData?.currentCurrencyRate || 0,

    hasPaymentData() {
        const amountSar = parseFloat(this.paymentData.amount_sar) || 0;
        const amountBdt = parseFloat(this.paymentData.amount_bdt) || 0;
        return this.paymentSaved && (amountSar > 0 || amountBdt > 0);
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
        fingerprint_charge_id: '',
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
        flight_date_from: '',
        flight_date_to: '',
        baggage_weight: '',
        address: '',
        with_offer: false,
        refundable: false,
        customDurationDays: ''
    },
    allTickets: [],
    filteredTickets: [],
    allPackages: [],
    passengerPackageValues: {},
    isEditMode: false,
    existingBooking: null,
    existingPassengers: [],
    existingCustomer: null,

    get totalPackageValue() {
        return Object.values(this.passengerPackageValues).reduce(
            (sum, v) => sum + (parseFloat(v) || 0), 0
        );
    },
    get grandTotalValue() {
        return this.totalPackageValue + (parseFloat(this.fingerprintCharge) || 0);
    },
    get discountedTotal() {
        const disc = parseFloat(this.bookingData.discount_value) || 0;
        if (disc <= 0) return null;
        const grand = this.grandTotalValue;
        const discType = this.bookingData.discount_type;
        const discAmt = discType === 'percentage' ? grand * disc / 100 : disc;
        return grand - discAmt;
    },

    init() {
        const serverData = window.__bookingServerData || {};
        this.packages = serverData.packages || [];
        this.allPackages = this.packages.map(p => ({
            ...p,
            id: String(p.id),
            ticket_fare_id: p.ticket_fare_id ? String(p.ticket_fare_id) : null,
        }));
        this.allTickets = serverData.ticketFares || [];
        this.filteredTickets = this.allTickets;

        this.isEditMode = serverData.isEditMode || false;
        this.existingBooking = serverData.existingBooking || null;
        this.existingPassengers = serverData.existingPassengers || [];
        this.existingCustomer = serverData.existingCustomer || null;

        if (this.isEditMode && this.existingBooking) {
            this.loadExistingBooking();
        }

        this.$nextTick(() => {
            if (serverData.preSelectedPackageId) {
                this.bookingData.package_id = String(serverData.preSelectedPackageId);
            }
            this.recalculateAllPassengerValues();
        });
    },

    loadExistingBooking() {
        const booking = this.existingBooking;
        if (!booking) return;

        if (this.existingCustomer) {
            this.selectedCustomer = this.existingCustomer;
            this.customerSearch = this.existingCustomer.passport_no || '';
        }

        const fpLocation = booking.fingerprint_location;
        if (typeof fpLocation === 'object' && fpLocation !== null) {
            this.bookingData.fingerprint_location = fpLocation.value === 'home' ? 'home' : 'office';
        } else if (typeof fpLocation === 'string') {
            this.bookingData.fingerprint_location = fpLocation === 'home' ? 'home' : 'office';
        } else {
            this.bookingData.fingerprint_location = 'office';
        }

        this.bookingData.fingerprint_office = booking.office_id ? String(booking.office_id) : '';
        this.bookingData.district_id = booking.district_id ? String(booking.district_id) : '';
        this.bookingData.package_id = booking.package_id ? String(booking.package_id) : '';
        this.bookingData.remarks = booking.remarks || '';

        const discountType = booking.discount_type;
        if (typeof discountType === 'object' && discountType !== null) {
            this.bookingData.discount_type = discountType.value === 'percentage' ? 'percentage' : 'fixed';
        } else if (typeof discountType === 'string') {
            this.bookingData.discount_type = discountType === 'percentage' ? 'percentage' : 'fixed';
        } else {
            this.bookingData.discount_type = 'fixed';
        }
        this.bookingData.discount_value = parseFloat(booking.discount_value) || 0;

        console.log('Loading booking data:', {
            fingerprint_location: this.bookingData.fingerprint_location,
            fingerprint_office: this.bookingData.fingerprint_office,
            district_id: this.bookingData.district_id,
            package_id: this.bookingData.package_id,
            passengers_count: this.existingPassengers ? this.existingPassengers.length : 0
        });

        if (this.existingPassengers && this.existingPassengers.length > 0) {
            this.passengers = this.existingPassengers.map(p => ({
                id: p.id,
                first_name: p.first_name || '',
                last_name: p.last_name || '',
                passport_no: p.passport_no || '',
                date_of_birth: p.date_of_birth ? p.date_of_birth.split('T')[0] : '',
                passenger_type: p.passenger_type || '',
                gender: p.gender || '',
                mobile_no: p.mobile_no || '',
                passport_expiry: p.passport_expiry ? p.passport_expiry.split('T')[0] : '',
                service_required: p.service_required || 'all',
                stay_duration: p.stay_duration ? String(p.stay_duration) : '',
                stay_duration_int: p.stay_duration ? parseInt(p.stay_duration) : 0,
                route: p.route || '',
                airline: p.airline || '',
                class: p.class || p.travel_class || '',
                route_type: p.route_type || '',
                flight_type: p.flight_type || '',
                ticket_fare_id: p.ticket_fare_id ? String(p.ticket_fare_id) : '',
                flight_date_from: p.flight_date_from ? p.flight_date_from.split('T')[0] : '',
                flight_date_to: p.flight_date_to ? p.flight_date_to.split('T')[0] : '',
                address: p.address || '',
                baggage_weight: '',
            }));
            this.passengerCount = this.passengers.length;
        }

        this.updateFingerprintCharge();
        this.updateGrandTotal();
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
        this.paymentData = {
            currency: 'SAR',
            method: 'Cash',
            bank_method: '',
            trx_id: '',
            amount_sar: '',
            amount_bdt: ''
        };
        this.paymentSaved = false;
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
        if (this.editingPassengerIndex !== null && this.editingPassengerIndex !== undefined) {
            this.recalculateCurrentPassenger(this.editingPassengerIndex);
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
        if (!this.bookingData.district_id) {
            this.fingerprintCharge = 0;
            this.bookingData.fingerprint_charge_id = '';
            return;
        }
        try {
            const response = await fetch(`/api/bookings/fingerprint-charge?district_id=${this.bookingData.district_id}&location=${this.bookingData.fingerprint_location}`);
            const data = await response.json();
            if (data.error) {
                alert(data.error);
                this.fingerprintCharge = 0;
                this.bookingData.fingerprint_charge_id = '';
                return;
            }
            this.fingerprintCharge = data.charge || 0;
            this.bookingData.fingerprint_charge_id = data.fingerprint_charge_id || '';
        } catch (e) {
            console.error('Fingerprint charge error:', e);
            this.fingerprintCharge = 0;
            this.bookingData.fingerprint_charge_id = '';
        }
    },

    calculatePackageValue(passenger, selectedPackage) {
        const ticketFareId = passenger.ticket_fare_id;
        const serviceRequired = passenger.service_required || 'all';
        const passengerType = (passenger.passenger_type || 'adult').toLowerCase();

        const ticket = this.allTickets.find(t => String(t.id) === String(ticketFareId));
        if (!ticket) return 0;

        const sellingFare = parseFloat(ticket.selling_fare) || 0;
        let ticketAmount = sellingFare;
        if (passengerType === 'child') {
            const pct = parseFloat(ticket.child_fare_percentage) || 0;
            ticketAmount = sellingFare * pct / 100;
        } else if (passengerType === 'infant') {
            const pct = parseFloat(ticket.infant_fare_percentage) || 0;
            ticketAmount = sellingFare * pct / 100;
        }

        const visaPrice = parseFloat(selectedPackage?.visa_selling_price) || 0;
        const serviceCharge = parseFloat(selectedPackage?.service_charge) || 0;

        let visaAmount = 0;
        let scAmount = 0;
        if (serviceRequired !== 'ticket_only') {
            visaAmount = visaPrice;
            scAmount = serviceCharge;
        } else {
            scAmount = serviceCharge;
        }

        return ticketAmount + visaAmount + scAmount;
    },

    recalculateAllPassengerValues() {
        const pkg = this.allPackages.find(p => String(p.id) === String(this.bookingData.package_id));
        this.passengers.forEach((p, index) => {
            this.passengerPackageValues[index] = this.calculatePackageValue(p, pkg);
        });
    },

    recalculateCurrentPassenger(index) {
        if (index === null || index === undefined || !this.passengers[index]) {
            return;
        }
        const pkg = this.allPackages.find(p => String(p.id) === String(this.bookingData.package_id));
        this.passengerPackageValues[index] = this.calculatePackageValue(this.passengers[index], pkg);
    },

    onPackageChange() {
        this.recalculateAllPassengerValues();
    },

    openPassengerModal() {
        this.editingPassengerIndex = null;
        let packageTicketFareId = null;
        if (this.bookingData.package_id) {
            const pkg = this.allPackages.find(p => String(p.id) === String(this.bookingData.package_id));
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
            flight_date_from: '',
            flight_date_to: '',
            baggage_weight: '',
            address: '',
            with_offer: false,
            refundable: false,
            customDurationDays: ''
        };
        if (packageTicketFareId) {
            this.passengerData.baggage_weight = 'Define Passenger Type';
        } else {
            this.passengerData.baggage_weight = 'Select a Ticket and Define Passenger Type';
        }
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
                    this.calculateFlightDateRange();
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

        if (this.passengerData.stay_duration_int && this.passengerData.stay_duration_int >= 30 && this.passengerData.stay_duration_int <= 89) {
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

                if (this.passengerData.flight_date_from && this.passengerData.flight_date_to) {
                    this.generateFlightDateRangeForEdit(this.passengerData.flight_date_from, this.passengerData.flight_date_to);
                }
            }
        }
        this.$nextTick(() => {
            this.updateBaggageWeight();
        });
        this.passengerModalVisible = true;
    },

    generateFlightDateRangeForEdit(dateFrom, dateTo) {
        const select = document.getElementById('passengerFlightDateRange');
        if (!select) return;

        const fromParts = dateFrom.split('-');
        const toParts = dateTo.split('-');
        const startDate = new Date(parseInt(fromParts[0]), parseInt(fromParts[1]) - 1, parseInt(fromParts[2]));
        const endDate = new Date(parseInt(toParts[0]), parseInt(toParts[1]) - 1, parseInt(toParts[2]));

        const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

        const startStr = `${months[startDate.getMonth()]} ${startDate.getDate()}, ${startDate.getFullYear()}`;
        const endStr = `${months[endDate.getMonth()]} ${endDate.getDate()}, ${endDate.getFullYear()}`;
        const displayText = `${startStr} - ${endStr}`;

        let found = false;
        Array.from(select.options).forEach(opt => {
            if (opt.value === displayText) {
                this.passengerData.flight_date_range = displayText;
                found = true;
            }
        });

        if (!found) {
            this.passengerData.flight_date_range = displayText;
            const option = document.createElement('option');
            option.value = displayText;
            option.textContent = displayText;
            select.appendChild(option);
            select.value = displayText;
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

    closePassengerModal() {
        this.passengerModalVisible = false;
    },

    removePassenger(index) {
        if (confirm('Are you sure you want to remove this passenger?')) {
            this.passengers.splice(index, 1);
            this.passengerCount = this.passengers.length;
            this.recalculateAllPassengerValues();
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

    savePassenger() {
        if (!this.passengerData.first_name || !this.passengerData.last_name || !this.passengerData.passport_no || !this.passengerData.date_of_birth) {
            alert('Please fill in all required fields');
            return false;
        }

        if (this.passengerData.passenger_type?.toLowerCase() === 'adult' && !this.passengerData.gender) {
            alert('Please select gender for adult passenger');
            return false;
        }

        if (this.passengerData.flight_date_range) {
            const parsedDates = this.parseFlightDateRange(this.passengerData.flight_date_range);
            if (parsedDates) {
                this.passengerData.flight_date_from = parsedDates.from;
                this.passengerData.flight_date_to = parsedDates.to;
            }
        }

        if (this.editingPassengerIndex !== null) {
            this.passengers[this.editingPassengerIndex] = { ...this.passengerData };
            this.recalculateCurrentPassenger(this.editingPassengerIndex);
        } else {
            this.passengers.push({ ...this.passengerData });
            this.recalculateAllPassengerValues();
        }
        this.passengerCount = this.passengers.length;
        this.closePassengerModal();
        return true;
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
        if (this.paymentData.currency === 'BDT' && this.paymentData.amount_sar && this.exchangeRate > 0) {
            this.paymentData.amount_bdt = (parseFloat(this.paymentData.amount_sar) * this.exchangeRate).toFixed(2);
        } else if (this.paymentData.currency === 'SAR' && this.paymentData.amount_bdt && this.exchangeRate > 0) {
            this.paymentData.amount_sar = (parseFloat(this.paymentData.amount_bdt) / this.exchangeRate).toFixed(2);
        }
    },

    convertSarToBdt() {
        if (this.paymentData.currency === 'SAR' && this.paymentData.amount_sar && this.exchangeRate > 0) {
            this.paymentData.amount_bdt = (parseFloat(this.paymentData.amount_sar) * this.exchangeRate).toFixed(2);
        }
    },

    convertBdtToSar() {
        if (this.paymentData.currency === 'BDT' && this.paymentData.amount_bdt && this.exchangeRate > 0) {
            this.paymentData.amount_sar = (parseFloat(this.paymentData.amount_bdt) / this.exchangeRate).toFixed(2);
        }
    },

    savePayment() {
        const amountSAR = parseFloat(this.paymentData.amount_sar) || 0;
        const amountBDT = parseFloat(this.paymentData.amount_bdt) || 0;
        
        if (amountSAR === 0) {
            alert('Please enter payment amount');
            return;
        }

        if (amountSAR > this.paymentMaxAmount) {
            alert('Payment amount cannot exceed the total booking value of ' + this.paymentMaxAmount.toFixed(2) + ' SAR');
            return;
        }

        this.paymentSaved = true;

        console.log('Payment saved:', {
            currency: this.paymentData.currency,
            method: this.paymentData.method,
            bank_method: this.paymentData.bank_method,
            trx_id: this.paymentData.trx_id,
            amount_sar: amountSAR,
            amount_bdt: amountBDT
        });

        this.closePaymentModal();
    },

    applyDiscount() {
        this.bookingData.discount_type = document.getElementById('discountType')?.value || 'fixed';
        this.bookingData.discount_value = parseFloat(document.getElementById('discountValue')?.value) || 0;
        this.closeDiscountModal();
    },

    updateGrandTotal() {
        this.recalculateAllPassengerValues();
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

    filterTickets() {
        const routeType = this.passengerData.route_type || '';
        const flightType = this.passengerData.flight_type || '';

        const reverseRouteTypeMap = {
            'One Way-Inbound': 'oneway_inbound',
            'One Way-Outbound': 'oneway_outbound',
            'Round': 'round',
            'Multi City': 'multi_city',
        };
        const reverseFlightTypeMap = {
            'Transit': 'transit',
            'Direct': 'direct',
        };

        const mappedRouteType = reverseRouteTypeMap[routeType] || routeType;
        const mappedFlightType = reverseFlightTypeMap[flightType] || flightType;

        this.filteredTickets = this.allTickets.filter(t =>
            t.route_type === mappedRouteType &&
            t.flight_type === mappedFlightType
        );
    },

    onTicketChange() {
        const ticketFareId = this.passengerData.ticket_fare_id;
        if (!ticketFareId) {
            this.passengerData.route = '';
            this.passengerData.airline = '';
            this.passengerData.class = '';
            return;
        }

        const ticket = this.allTickets.find(t => String(t.id) === String(ticketFareId));
        if (ticket) {
            this.passengerData.route = ticket.route || '';
            this.passengerData.airline = ticket.airline || '';
            this.passengerData.class = ticket.airline_class || '';

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

            this.calculateFlightDateRange();
            this.updateBaggageWeight();
        }
    },

    handlePassengerDocUpload(input) {
        const list = document.getElementById('passenger_docs_list');
        if (!list) return;
        list.innerHTML = '';
        Array.from(input.files).forEach(file => {
            const item = document.createElement('div');
            item.className = 'flex items-center justify-between text-sm text-slate-600 bg-slate-50 px-3 py-2 rounded';
            item.innerHTML = '<span class="truncate">' + file.name + '</span><button type="button" onclick="removePassengerDoc(this)" class="text-red-500 hover:text-red-700 ml-2 flex-shrink-0">×</button>';
            list.appendChild(item);
        });
    },

    removePassengerDoc(btn) {
        btn.parentElement.remove();
    },

    getTicketDisplayText(ticket) {
        const price = ticket.selling_fare ? ticket.selling_fare + ' SAR' : '';
        const ticketType = ticket.ticket_type || 'standard';
        const type = ticketType.charAt(0).toUpperCase() + ticketType.slice(1);
        switch (ticketType) {
            case 'offer':
                const offer = ticket.offer_price ? ' | ' + ticket.offer_price + ' SAR' : '';
                return `${ticket.route || ''} | ${type} | ${price}${offer}`;
            case 'group':
                const seats = ticket.available_seats ? ' | ' + ticket.available_seats + ' seats' : '';
                return `${ticket.route || ''} | ${type} | ${price}${seats}`;
            default:
                return `${ticket.route || ''} | ${type} | ${price}`;
        }
    }
}));

Alpine.data('showBookingApp', () => ({
    passengerModalVisible: false,
    editingPassengerIndex: null,
    customDurationModalVisible: false,
    passengers: [],
    passengerPackageValues: {},
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
        with_offer: false,
        refundable: false,
        customDurationDays: ''
    },
    allTickets: [],
    filteredTickets: [],
    allPackages: [],

    init() {
        const data = window.__bookingServerData || {};
        this.allPackages = (data.packages || []).map(p => ({
            ...p,
            id: String(p.id),
            ticket_fare_id: p.ticket_fare_id ? String(p.ticket_fare_id) : null,
        }));
        this.allTickets = data.ticketFares || [];
        this.filteredTickets = this.allTickets;
    },

    openPassengerModal() {
        this.editingPassengerIndex = null;
        let packageTicketFareId = null;
        if (window.__bookingServerData?.preSelectedPackageId) {
            const pkg = this.allPackages.find(p => String(p.id) === String(window.__bookingServerData.preSelectedPackageId));
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
            flight_date_from: '',
            flight_date_to: '',
            baggage_weight: '',
            address: '',
            with_offer: false,
            refundable: false,
            customDurationDays: ''
        };
        if (packageTicketFareId) {
            this.passengerData.baggage_weight = 'Define Passenger Type';
        } else {
            this.passengerData.baggage_weight = 'Select a Ticket and Define Passenger Type';
        }
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
                    this.calculateFlightDateRange();
                });
            }
        } else {
            this.filteredTickets = [];
        }
        this.passengerModalVisible = true;
    },

    closePassengerModal() {
        this.passengerModalVisible = false;
    },

    savePassenger() {
        if (!this.passengerData.first_name || !this.passengerData.last_name || !this.passengerData.passport_no || !this.passengerData.date_of_birth) {
            alert('Please fill in all required fields');
            return;
        }
        if (this.passengerData.passenger_type?.toLowerCase() === 'adult' && !this.passengerData.gender) {
            alert('Please select gender for adult passenger');
            return;
        }

        const bookingId = window.__bookingServerData?.bookingId;
        if (!bookingId) {
            alert('Invalid booking');
            return;
        }

        const flightDates = this.passengerData.flight_date_range
            ? this.parseFlightDateRange(this.passengerData.flight_date_range)
            : null;

        fetch('/bookings/' + bookingId + '/passengers', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                first_name: this.passengerData.first_name,
                last_name: this.passengerData.last_name,
                passport_no: this.passengerData.passport_no,
                date_of_birth: this.passengerData.date_of_birth,
                mobile_no: this.passengerData.mobile_no || null,
                passport_expiry: this.passengerData.passport_expiry || null,
                service_required: this.passengerData.service_required || null,
                stay_duration: this.parseStayDurationDays(this.passengerData.stay_duration),
                gender: this.passengerData.gender || null,
                ticket_fare_id: this.passengerData.ticket_fare_id || null,
                flight_date_from: flightDates?.from || null,
                flight_date_to: flightDates?.to || null,
                address: this.passengerData.address || null,
            })
        })
        .then(response => {
            if (!response.ok) {
                return response.json().then(err => {
                    if (err.errors) {
                        const firstError = Object.values(err.errors)[0];
                        throw new Error(firstError[0] || 'Validation failed');
                    }
                    throw new Error(err.message || 'Failed to add passenger');
                });
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                this.closePassengerModal();
                if (typeof showToast === 'function') {
                    showToast('Passenger added successfully');
                }
                setTimeout(() => location.reload(), 500);
            } else {
                alert(data.message || 'Failed to add passenger');
            }
        })
        .catch(error => {
            alert('Error: ' + error.message);
        });
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
        const allowances = ticket.baggage_allowances.filter(ba => ba.passenger_type === lowerType);
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
        const routeType = this.passengerData.route_type;
        const flightType = this.passengerData.flight_type;
        if (!routeType && !flightType) {
            this.filteredTickets = this.allTickets;
            return;
        }
        this.filteredTickets = this.allTickets.filter(ticket => {
            let match = true;
            if (routeType) {
                const reverseMap = {
                    'One Way-Inbound': 'oneway_inbound',
                    'One Way-Outbound': 'oneway_outbound',
                    'Round': 'round',
                    'Multi City': 'multi_city',
                };
                match = match && ticket.route_type === reverseMap[routeType];
            }
            if (flightType) {
                const reverseMap = {
                    'Transit': 'transit',
                    'Direct': 'direct',
                };
                match = match && ticket.flight_type === reverseMap[flightType];
            }
            return match;
        });
    },

    onTicketChange() {
        const ticketFareId = this.passengerData.ticket_fare_id;
        if (!ticketFareId) {
            this.passengerData.route = '';
            this.passengerData.airline = '';
            this.passengerData.class = '';
            this.passengerData.flight_date_range = '';
            return;
        }
        const ticket = this.allTickets.find(t => String(t.id) === String(ticketFareId));
        if (ticket) {
            this.passengerData.route = ticket.route || '';
            this.passengerData.airline = ticket.airline || '';
            this.passengerData.class = ticket.airline_class || '';
            this.calculateFlightDateRange();
            this.updateBaggageWeight();
        }
    },

    calculateFlightDateRange() {
        const route = this.passengerData.route;
        const airline = this.passengerData.airline;
        const travelClass = this.passengerData.class;

        if (!route || !airline || !travelClass) {
            this.populateFlightDateRangeOptions([]);
            return;
        }

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
                this.generateFlightDateRangeOptions(30, 0);
            }
        } catch (e) {
            console.error('Error fetching flight date gap:', e);
            this.generateFlightDateRangeOptions(30, 0);
        }
    },

    generateFlightDateRangeOptions(defaultGap, additionalGap) {
        const finalGap = defaultGap + additionalGap;
        const expectedDate = new Date();
        expectedDate.setDate(expectedDate.getDate() + finalGap);

        const day = expectedDate.getDate();
        let startMonthOffset = 0;
        let startSlot = 0;

        if (day >= 1 && day <= 5) {
            startMonthOffset = 0;
            startSlot = 0;
        } else if (day >= 6 && day <= 10) {
            startMonthOffset = 0;
            startSlot = 1;
        } else if (day >= 11 && day <= 15) {
            startMonthOffset = 0;
            startSlot = 1;
        } else if (day >= 16 && day <= 20) {
            startMonthOffset = 0;
            startSlot = 2;
        } else if (day >= 21 && day <= 25) {
            startMonthOffset = 0;
            startSlot = 2;
        } else if (day >= 26 && day <= 31) {
            startMonthOffset = 1;
            startSlot = 0;
        }

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

    getTicketDisplayText(ticket) {
        const price = ticket.selling_fare ? ticket.selling_fare + ' SAR' : '';
        const ticketType = ticket.ticket_type || 'standard';
        const type = ticketType.charAt(0).toUpperCase() + ticketType.slice(1);
        switch (ticketType) {
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

    recalculateCurrentPassenger(index) {
        if (index === null || index === undefined || !this.passengers[index]) return;
        const pkg = this.allPackages.find(p => String(p.id) === String(window.__bookingServerData?.preSelectedPackageId));
        this.passengerPackageValues[index] = this.calculatePackageValue(this.passengers[index], pkg);
    },

    calculatePackageValue(passenger, selectedPackage) {
        const ticketFareId = passenger.ticket_fare_id;
        const serviceRequired = passenger.service_required || 'all';
        const passengerType = (passenger.passenger_type || 'adult').toLowerCase();
        const ticket = this.allTickets.find(t => String(t.id) === String(ticketFareId));
        if (!ticket) return 0;
        const sellingFare = parseFloat(ticket.selling_fare) || 0;
        let ticketAmount = sellingFare;
        if (passengerType === 'child') {
            const pct = parseFloat(ticket.child_fare_percentage) || 0;
            ticketAmount = sellingFare * pct / 100;
        } else if (passengerType === 'infant') {
            const pct = parseFloat(ticket.infant_fare_percentage) || 0;
            ticketAmount = sellingFare * pct / 100;
        }
        const visaPrice = parseFloat(selectedPackage?.visa_selling_price) || 0;
        const serviceCharge = parseFloat(selectedPackage?.service_charge) || 0;
        let total = 0;
        if (serviceRequired === 'all') {
            total = ticketAmount + visaPrice + serviceCharge;
        } else if (serviceRequired === 'visa_only') {
            total = visaPrice;
        } else if (serviceRequired === 'ticket_only') {
            total = ticketAmount;
        }
        return total;
    },
}));

window.handleBookingCustomerDocsUpload = function(input) {
    const list = document.getElementById('booking_customer_docs_list');
    if (!list) return;
    list.innerHTML = '';
    Array.from(input.files).forEach(file => {
        const item = document.createElement('div');
        item.className = 'flex items-center justify-between text-sm text-slate-600 bg-slate-50 px-3 py-2 rounded';
        item.innerHTML = '<span class="truncate">' + file.name + '</span><button type="button" onclick="removeBookingCustomerDoc(this)" class="text-red-500 hover:text-red-700 ml-2 flex-shrink-0">×</button>';
        list.appendChild(item);
    });
};

window.removeBookingCustomerDoc = function(btn) {
    btn.parentElement.remove();
};

window.handlePassengerDocUpload = function(input) {
    const list = document.getElementById('passenger_doc_list');
    if (!list) return;
    list.innerHTML = '';
    Array.from(input.files).forEach(file => {
        const item = document.createElement('div');
        item.className = 'flex items-center justify-between text-sm text-slate-600 bg-slate-50 px-3 py-2 rounded';
        item.innerHTML = '<span class="truncate">' + file.name + '</span><button type="button" onclick="removePassengerDoc(this)" class="text-red-500 hover:text-red-700 ml-2 flex-shrink-0">×</button>';
        list.appendChild(item);
    });
};

window.removePassengerDoc = function(btn) {
    btn.parentElement.remove();
};

window.handleRefIqamaFileUpload = function(input) {
    const file = input.files[0];
    const display = document.getElementById('ref_iqama_doc_filename');
    if (file && display) display.textContent = file.name;
};

window.handleCustomerDocUpload = function(input) {
    const list = document.getElementById('customer_docs_list');
    if (!list) return;
    list.innerHTML = '';
    Array.from(input.files).forEach(file => {
        const item = document.createElement('div');
        item.className = 'flex items-center justify-between text-sm text-slate-600 bg-slate-50 px-3 py-2 rounded';
        item.innerHTML = '<span class="truncate">' + file.name + '</span><button type="button" onclick="removeCustomerDoc(this)" class="text-red-500 hover:text-red-700 ml-2 flex-shrink-0">×</button>';
        list.appendChild(item);
    });
};

window.removeCustomerDoc = function(btn) {
    btn.parentElement.remove();
};