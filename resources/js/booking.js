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
        passenger_type: 'adult',
        gender: '',
        mobile_no: '',
        passport_expiry: '',
        service_required: 'All',
        stay_duration: '14',
        route: '',
        airline: '',
        travel_class: '',
        flight_date_from: '',
        flight_date_to: '',
        address: '',
        with_offer: false,
        refundable: false
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

    async calculatePassengerType() {
        if (!this.passengerData.date_of_birth) {
            this.passengerData.passenger_type = 'adult';
            return;
        }
        try {
            const response = await fetch('/api/bookings/calculate-type', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                body: JSON.stringify({ date_of_birth: this.passengerData.date_of_birth })
            });
            const data = await response.json();
            this.passengerData.passenger_type = data.passenger_type || 'adult';
        } catch (e) {
            console.error('Calculate type error:', e);
            this.passengerData.passenger_type = 'adult';
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
            this.passengerData = { ...this.passengers[index] };
        } else {
            this.editingPassengerIndex = null;
            this.passengerData = {
                first_name: '',
                last_name: '',
                passport_no: '',
                date_of_birth: '',
                passenger_type: 'adult',
                gender: '',
                mobile_no: '',
                passport_expiry: '',
                service_required: 'All',
                stay_duration: '14',
                route: '',
                airline: '',
                travel_class: '',
                flight_date_from: '',
                flight_date_to: '',
                address: '',
                with_offer: false,
                refundable: false
            };
        }
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

        if (this.passengerData.passenger_type === 'adult' && !this.passengerData.gender) {
            alert('Please select gender for adult passenger');
            return false;
        }

        if (this.editingPassengerIndex !== null) {
            this.passengers[this.editingPassengerIndex] = { ...this.passengerData };
        } else {
            this.passengers.push({ ...this.passengerData });
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