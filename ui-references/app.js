// ============================================
// Application State
// ============================================
const state = {
    customers: [
        { id: '1234567890', name: 'Ahmed Al-Rashid', iqama: '1234567890', passport: 'P123456', mobile: '0501234567' },
        { id: '0987654321', name: 'Fatima Al-Saud', iqama: '0987654321', passport: 'P654321', mobile: '0559876543' },
        { id: 'P111111', name: 'Mohammed Khan', iqama: '1111111111', passport: 'P111111', mobile: '0501111111' },
        { id: '2222222222', name: 'Aisha Abdullah', iqama: '2222222222', passport: 'P222222', mobile: '0552222222' },
        { id: 'P333333', name: 'Ibrahim Hassan', iqama: '3333333333', passport: 'P333333', mobile: '0503333333' },
    ],
    customerSearchTerm: '',
    filteredCustomers: [],
    selectedCustomer: null,
    passengers: [],
    editingPassengerIndex: null,
    isCustomerModalOpen: false,
    isPassengerModalOpen: false,
};

// Passenger Index State
const passengerIndexState = {
    passengerIndexRows: [],
    invoiceCounter: 1000,
    isTicketFareModalOpen: false,
    isVisaCostModalOpen: false,
    editingTicketFareRowIndex: null,
    editingVisaCostRowIndex: null,
    currentPassengerIndex: null,
};

// Initialize Passenger Index from Booking Index data
function initializePassengerIndexFromBookings() {
    bookingIndexState.bookings.forEach(booking => {
        booking.passengers.forEach(passenger => {
            const route = passenger.route || '';
            const airline = passenger.airline || '';
            const travelClass = passenger.travelClass || '';
            
            const matchedFare = fareAdminState.fareRecords.find(fare => 
                fare.route === route && 
                fare.airline === airline && 
                fare.travelClass === travelClass
            );
            
            const sortedVisaRecords = [...visaAdminState.visaPriceRecords].sort((a, b) => 
                new Date(b.date) - new Date(a.date)
            );
            const latestVisa = sortedVisaRecords[0];
            
            const indexRow = {
                date: booking.bookingDate,
                invoiceNo: booking.invoiceNo,
                guardianName: booking.customerName,
                mobileNo: '',
                passengerName: passenger.name,
                passport: passenger.passport,
                route: route,
                status: 'None',
                package: '',
                due: passenger.total || 0,
                ticketFare: matchedFare ? { 
                    netFare: matchedFare.netFare || matchedFare.fare || 0,
                    sellingFare: matchedFare.sellingFare || 0,
                    date: matchedFare.date,
                    ticketAgent: '',
                    route: matchedFare.route,
                    airline: matchedFare.airline,
                    travelClass: matchedFare.travelClass,
                    passengerType: matchedFare.passengerType || '',
                    discountType: 'amount',
                    discountValue: 0,
                    withOffer: matchedFare.withOffer || false,
                    refundable: false
                } : null,
                visa: latestVisa ? { 
                    finalCost: latestVisa.price,
                    sellingPrice: latestVisa.price,
                    date: latestVisa.date
                } : null,
                fingerprintLocation: 'None',
                fingerprintCost: passenger.fingerprintCost || 0,
                passengerData: passenger,
            };
            passengerIndexState.passengerIndexRows.push(indexRow);
        });
    });
}

// Initialize on load
// Note: This will be called after bookingIndexState is defined

// Fare Admin State
const fareAdminState = {
    fareRecords: [
        { id: 1, date: '2026-03-15', airline: 'Saudia', travelClass: 'Economy', route: 'DAC-JED-DAC', fare: 2500, withOffer: true, offerPrice: 2200, effectiveFrom: '2026-03-01', effectiveTo: '2026-04-30' },
        { id: 2, date: '2026-03-10', airline: 'Biman Bangladesh', travelClass: 'Economy', route: 'DAC-JED-DAC', fare: 2100, withOffer: false, offerPrice: null, effectiveFrom: null, effectiveTo: null },
        { id: 3, date: '2026-03-12', airline: 'Emirates', travelClass: 'Business', route: 'DAC-JED-DAC', fare: 9500, withOffer: true, offerPrice: 8500, effectiveFrom: '2026-03-01', effectiveTo: '2026-05-31' },
    ],
    selectedFareId: null,
    editingFareId: null,
    viewingFareId: null,
    isAddFareModalOpen: false,
    isEditFareModalOpen: false,
    isViewFareModalOpen: false,
    isDeleteConfirmOpen: false,
};

// Visa Admin State
const visaAdminState = {
    visaPriceRecords: [
        { id: 1, date: '2026-03-15', price: 500 },
        { id: 2, date: '2026-01-01', price: 450 },
    ],
    editingVisaPriceId: null,
};

// Booking Index State
const bookingIndexState = {
    bookings: [
        {
            id: 1,
            invoiceNo: 'INV-1001',
            bookingDate: '2026-03-15',
            customerName: 'Ahmed Al-Rashid',
            passengers: [
                { name: 'Ahmed Al-Rashid', passport: 'P123456', route: 'DAC-JED-DAC', airline: 'Saudia', class: 'Economy', passengerType: 'Adult', ticketFare: 2500, visaCost: 500, fingerprintCost: 200, total: 3200 },
                { name: 'Sara Khan', passport: 'P654321', route: 'DAC-JED-DAC', airline: 'Saudia', class: 'Economy', passengerType: 'Adult', ticketFare: 2500, visaCost: 500, fingerprintCost: 200, total: 3200 }
            ],
            payments: [
                { id: 1, date: '2026-03-16', voucherNo: 'V001', paymentMethod: 'Cash', trxId: '-', amount: 5000 }
            ],
            customerDocuments: []
        },
        {
            id: 2,
            invoiceNo: 'INV-1002',
            bookingDate: '2026-03-10',
            customerName: 'Fatima Al-Saud',
            passengers: [
                { name: 'Fatima Al-Saud', passport: 'P111111', route: 'DAC-RUH-DAC', airline: 'Biman Bangladesh', class: 'Business', passengerType: 'Adult', ticketFare: 7000, visaCost: 500, fingerprintCost: 200, total: 7700 }
            ],
            payments: [
                { id: 1, date: '2026-03-11', voucherNo: 'V002', paymentMethod: 'Bank Transfer', trxId: 'TXN123456', amount: 7700 }
            ],
            customerDocuments: []
        },
        {
            id: 3,
            invoiceNo: 'INV-1003',
            bookingDate: '2026-03-05',
            customerName: 'Mohammed Khan',
            passengers: [
                { name: 'Mohammed Khan', passport: 'P222222', route: 'DAC-MED-DAC', airline: 'Emirates', class: 'Economy', passengerType: 'Child', ticketFare: 2000, visaCost: 500, fingerprintCost: 200, total: 2700 }
            ],
            payments: [],
            customerDocuments: []
        }
    ],
    selectedBookingId: null,
    isInvoiceDetailsOpen: false,
    isDeleteConfirmOpen: false,
    deletingPassengerIndex: null,
    editingBookingId: null,
};

// Initialize Passenger Index from booking data
initializePassengerIndexFromBookings();

// Sample customer documents for demo
const appSampleCustomerDocuments = [
    { name: 'Customer_ID.pdf', type: 'application/pdf', size: 180000 },
    { name: 'Customer_Photo.jpg', type: 'image/jpeg', size: 95000 }
];

// Initialize sample customer documents for first bookings if empty
if (bookingIndexState.bookings.length > 0 && (!bookingIndexState.bookings[0].customerDocuments || bookingIndexState.bookings[0].customerDocuments.length === 0)) {
    bookingIndexState.bookings[0].customerDocuments = appSampleCustomerDocuments.map((doc, i) => ({
        id: 'cust_sample_' + (i + 1),
        name: doc.name,
        type: doc.type,
        uploadDate: '2026-03-15',
        size: doc.size,
        data: null
    }));
}
if (bookingIndexState.bookings.length > 1 && (!bookingIndexState.bookings[1].customerDocuments || bookingIndexState.bookings[1].customerDocuments.length === 0)) {
    bookingIndexState.bookings[1].customerDocuments = [
        { id: 'cust_sample_3', name: 'Customer_Agreement.pdf', type: 'application/pdf', size: 220000, uploadDate: '2026-03-10', data: null }
    ];
}

// Mock Data
const ticketPricing = {
    'DAC-JED-DAC': {
        'Saudia': { 'Economy': { 'Adult': 2500, 'Child': 1800, 'Infant': 500, 'offerAdult': 2200, 'offerChild': 1600, 'offerInfant': 400 }, 'Business': { 'Adult': 8500, 'Child': 6400, 'Infant': 2100 } },
        'Biman Bangladesh': { 'Economy': { 'Adult': 2100, 'Child': 1500, 'Infant': 400, 'offerAdult': 1800, 'offerChild': 1300, 'offerInfant': 300 }, 'Business': { 'Adult': 7000, 'Child': 5250, 'Infant': 1750 } },
        'Emirates': { 'Economy': { 'Adult': 2800, 'Child': 2000, 'Infant': 600, 'offerAdult': 2400, 'offerChild': 1700, 'offerInfant': 500 }, 'Business': { 'Adult': 9500, 'Child': 7125, 'Infant': 2375 } },
        'Qatar Airways': { 'Economy': { 'Adult': 2700, 'Child': 1900, 'Infant': 550 }, 'Business': { 'Adult': 9000, 'Child': 6750, 'Infant': 2250 } },
        'Flynas': { 'Economy': { 'Adult': 1900, 'Child': 1350, 'Infant': 350 }, 'Business': { 'Adult': 6000, 'Child': 4500, 'Infant': 1500 } },
    },
    'DAC-RUH-DAC': {
        'Saudia': { 'Economy': { 'Adult': 2300, 'Child': 1650, 'Infant': 450 }, 'Business': { 'Adult': 8000, 'Child': 6000, 'Infant': 2000 } },
        'Biman Bangladesh': { 'Economy': { 'Adult': 1900, 'Child': 1350, 'Infant': 350 }, 'Business': { 'Adult': 6500, 'Child': 4875, 'Infant': 1625 } },
    },
    'DAC-MED-DAC': {
        'Saudia': { 'Economy': { 'Adult': 2400, 'Child': 1700, 'Infant': 480 }, 'Business': { 'Adult': 8200, 'Child': 6150, 'Infant': 2050 } },
    },
    'DAC-JED-MED-DAC': {
        'Saudia': { 'Economy': { 'Adult': 2800, 'Child': 2000, 'Infant': 550 }, 'Business': { 'Adult': 9500, 'Child': 7125, 'Infant': 2375 } },
    },
};

const visaPackageCosts = { 'default': 500 };

const visaAgentCommissionAgents = {
    "Visa Agent A": ["Commission Agent 1", "Commission Agent 2"],
    "Visa Agent B": ["Commission Agent 3", "Commission Agent 4"]
};

const fingerprintLocations = ['None', 'BMT-DHK', 'BMT-CTG', 'Tabuk with DHK', 'Tabuk with CTG', 'Tabuk with DHK-BMT', 'Dhaka North', 'Dhaka South', 'Chittagong', 'Sylhet'];

const statusOptions = ['None', 'Underprocessing', 'Fingerprint Done', 'Ticket Booking', 'Visa Application', 'Visa Issued', 'Ticket Issued', 'Delivered', 'Hold', 'Cancel', 'Return Done'];

// Admin Settings Data
const divisionsData = {
    "Dhaka Division": ["Dhaka", "Faridpur", "Gazipur", "Gopalganj", "Kishoreganj", "Madaripur", "Manikganj", "Munshiganj", "Narayanganj", "Narsingdi", "Rajbari", "Shariatpur", "Tangail"],
    "Chattogram Division": ["Bandarban", "Brahmanbaria", "Chandpur", "Chattogram", "Cumilla", "Cox's Bazar", "Feni", "Khagrachhari", "Lakshmipur", "Noakhali", "Rangamati"],
    "Rajshahi Division": ["Bogura", "Joypurhat", "Naogaon", "Natore", "Chapainawabganj", "Pabna", "Rajshahi", "Sirajganj"],
    "Khulna Division": ["Bagerhat", "Chuadanga", "Jashore", "Jhenaidah", "Khulna", "Kushtia", "Magura", "Meherpur", "Narail", "Satkhira"],
    "Barishal Division": ["Barguna", "Barishal", "Bhola", "Jhalokathi", "Patuakhali", "Pirojpur"],
    "Sylhet Division": ["Habiganj", "Moulvibazar", "Sunamganj", "Sylhet"],
    "Rangpur Division": ["Dinajpur", "Gaibandha", "Kurigram", "Lalmonirhat", "Nilphamari", "Panchagarh", "Rangpur", "Thakurgaon"],
    "Mymensingh Division": ["Jamalpur", "Mymensingh", "Netrokona", "Sherpur"]
};

const defaultAdminSettings = {
    defaultFlightDateGap: 30,
    fingerprintCharges: {}
};

let adminSettings = { ...defaultAdminSettings };

function loadAdminSettings() {
    const saved = localStorage.getItem('adminSettings');
    if (saved) {
        adminSettings = JSON.parse(saved);
    }
    return adminSettings;
}

function saveAdminSettingsToStorage() {
    localStorage.setItem('adminSettings', JSON.stringify(adminSettings));
}

// ============================================
// DOM Elements
// ============================================
const elements = {
    defaultView: document.getElementById('defaultView'),
    bookingForm: document.getElementById('bookingForm'),
    passengerIndexSection: document.getElementById('passengerIndexSection'),
    passengerIndexTableBody: document.getElementById('passengerIndexTableBody'),
    passengerIndexEmpty: document.getElementById('passengerIndexEmpty'),
    customerSearch: document.getElementById('customerSearch'),
    customerSuggestions: document.getElementById('customerSuggestions'),
    selectedCustomer: document.getElementById('selectedCustomer'),
    selectedCustomerName: document.getElementById('selectedCustomerName'),
    selectedCustomerPassport: document.getElementById('selectedCustomerPassport'),
    selectedCustomerIqama: document.getElementById('selectedCustomerIqama'),
    selectedCustomerMobile: document.getElementById('selectedCustomerMobile'),
    customerModal: document.getElementById('customerModal'),
    customerForm: document.getElementById('customerForm'),
    customerName: document.getElementById('customerName'),
    customerIqama: document.getElementById('customerIqama'),
    customerPassport: document.getElementById('customerPassport'),
    customerMobile: document.getElementById('customerMobile'),
    passengerModal: document.getElementById('passengerModal'),
    passengerModalTitle: document.getElementById('passengerModalTitle'),
    passengerForm: document.getElementById('passengerForm'),
    passengerFirstName: document.getElementById('passengerFirstName'),
    passengerLastName: document.getElementById('passengerLastName'),
    passengerPassport: document.getElementById('passengerPassport'),
    passengerPassportExpiry: document.getElementById('passengerPassportExpiry'),
    passengerDateOfBirth: document.getElementById('passengerDateOfBirth'),
    passengerTypeDisplay: document.getElementById('passengerTypeDisplay'),
    passengerMobile: document.getElementById('passengerMobile'),
    passengerPackage: document.getElementById('passengerPackage'),
    passengerService: document.getElementById('passengerService'),
    passengerRoute: document.getElementById('passengerRoute'),
    passengerAirline: document.getElementById('passengerAirline'),
    passengerClass: document.getElementById('passengerClass'),
    passengerFlightDateRange: document.getElementById('passengerFlightDateRange'),
    passengerType: document.getElementById('passengerType'),
    bookingDistrict: document.getElementById('bookingDistrict'),
    bookingFingerprintLocation: document.getElementById('bookingFingerprintLocation'),
    bookingPackage: document.getElementById('bookingPackage'),
    passengerAddress: document.getElementById('passengerAddress'),
    passengerWithOffer: document.getElementById('passengerWithOffer'),
    passengerRefundable: document.getElementById('passengerRefundable'),
    passengerListContainer: document.getElementById('passengerListContainer'),
    passengerList: document.getElementById('passengerList'),
    addMoreButtonContainer: document.getElementById('addMoreButtonContainer'),
    toastContainer: document.getElementById('toastContainer'),
    // Ticket Fare Modal
    ticketFareModal: document.getElementById('ticketFareModal'),
    ticketFareModalTitle: document.getElementById('ticketFareModalTitle'),
    ticketFareForm: document.getElementById('ticketFareForm'),
    ticketFareDate: document.getElementById('ticketFareDate'),
    ticketFareAgent: document.getElementById('ticketFareAgent'),
    ticketFareRoute: document.getElementById('ticketFareRoute'),
    ticketFareAirline: document.getElementById('ticketFareAirline'),
    ticketFareClass: document.getElementById('ticketFareClass'),
    ticketFareFlightDateRange: document.getElementById('ticketFareFlightDateRange'),
    ticketFareFinalFlightDate: document.getElementById('ticketFareFinalFlightDate'),
    ticketFareSellingFare: document.getElementById('ticketFareSellingFare'),
    ticketFarePassengerType: document.getElementById('ticketFarePassengerType'),
    ticketFareGross: document.getElementById('ticketFareGross'),
    ticketFareDiscountType: document.getElementById('ticketFareDiscountType'),
    ticketFareDiscountValue: document.getElementById('ticketFareDiscountValue'),
    ticketFareNet: document.getElementById('ticketFareNet'),
    ticketFareWithOffer: document.getElementById('ticketFareWithOffer'),
    ticketFareRefundable: document.getElementById('ticketFareRefundable'),
    // Visa Cost Modal
    visaCostModal: document.getElementById('visaCostModal'),
    visaCostModalTitle: document.getElementById('visaCostModalTitle'),
    visaCostForm: document.getElementById('visaCostForm'),
    visaCostAgent: document.getElementById('visaCostAgent'),
    visaCostCommissionAgent: document.getElementById('visaCostCommissionAgent'),
    visaCostSellingPrice: document.getElementById('visaCostSellingPrice'),
    visaCostAgentCommission: document.getElementById('visaCostAgentCommission'),
    visaCostNetVisaCost: document.getElementById('visaCostNetVisaCost'),
    visaCostFinal: document.getElementById('visaCostFinal'),
    // Visa Issue Modal
    visaIssueModal: document.getElementById('visaIssueModal'),
    visaIssueAgent: document.getElementById('visaIssueAgent'),
    visaIssueVisaNumber: document.getElementById('visaIssueVisaNumber'),
    visaIssueSellingPrice: document.getElementById('visaIssueSellingPrice'),
    visaIssueTotalCost: document.getElementById('visaIssueTotalCost'),
    visaIssueAdditionalCost: document.getElementById('visaIssueAdditionalCost'),
    visaIssueRemarks: document.getElementById('visaIssueRemarks'),
    // Visa Edit Modal
    visaEditModal: document.getElementById('visaEditModal'),
    visaEditAgent: document.getElementById('visaEditAgent'),
    visaEditVisaNumber: document.getElementById('visaEditVisaNumber'),
    visaEditCommissionAgent: document.getElementById('visaEditCommissionAgent'),
    visaEditSellingPrice: document.getElementById('visaEditSellingPrice'),
    visaEditAgentCommission: document.getElementById('visaEditAgentCommission'),
    visaEditNetVisaCost: document.getElementById('visaEditNetVisaCost'),
    visaEditAdditionalCost: document.getElementById('visaEditAdditionalCost'),
    visaEditRemarks: document.getElementById('visaEditRemarks'),
    visaEditFinalCost: document.getElementById('visaEditFinalCost'),
    visaEditStatus: document.getElementById('visaEditStatus'),
    // Visa Payment Modal
    visaPaymentModal: document.getElementById('visaPaymentModal'),
    visaPaymentPayTo: document.getElementById('visaPaymentPayTo'),
    visaPaymentMethod: document.getElementById('visaPaymentMethod'),
    visaPaymentAmount: document.getElementById('visaPaymentAmount'),
    // Fare Admin
    fareAdminSection: document.getElementById('fareAdminSection'),
    fareIndexTableBody: document.getElementById('fareIndexTableBody'),
    fareIndexEmpty: document.getElementById('fareIndexEmpty'),
    fareModal: document.getElementById('fareModal'),
    fareModalTitle: document.getElementById('fareModalTitle'),
    fareCoreFields: document.getElementById('fareCoreFields'),
    fareDate: document.getElementById('fareDate'),
    fareAirline: document.getElementById('fareAirline'),
    fareClass: document.getElementById('fareClass'),
    fareRoute: document.getElementById('fareRoute'),
    farePassengerType: document.getElementById('farePassengerType'),
    fareNetFare: document.getElementById('fareNetFare'),
    fareSellingFare: document.getElementById('fareSellingFare'),
    fareWithOffer: document.getElementById('fareWithOffer'),
    fareOfferFields: document.getElementById('fareOfferFields'),
    fareOfferPrice: document.getElementById('fareOfferPrice'),
    fareEffectiveFrom: document.getElementById('fareEffectiveFrom'),
    fareEffectiveTo: document.getElementById('fareEffectiveTo'),
    viewFareModal: document.getElementById('viewFareModal'),
    viewFareContent: document.getElementById('viewFareContent'),
    deleteFareModal: document.getElementById('deleteFareModal'),
    deleteFareInfo: document.getElementById('deleteFareInfo'),
    // Visa Admin
    visaAdminSection: document.getElementById('visaAdminSection'),
    visaPriceTableBody: document.getElementById('visaPriceTableBody'),
    visaPriceEmpty: document.getElementById('visaPriceEmpty'),
    visaPriceModal: document.getElementById('visaPriceModal'),
    visaPriceModalTitle: document.getElementById('visaPriceModalTitle'),
    visaPriceDate: document.getElementById('visaPriceDate'),
    visaPriceAmount: document.getElementById('visaPriceAmount'),
    // Settings
    settingsSection: document.getElementById('settingsSection'),
    flightDateGapInput: document.getElementById('flightDateGapInput'),
    divisionSelect: document.getElementById('divisionSelect'),
    districtSelect: document.getElementById('districtSelect'),
    fingerprintChargeInput: document.getElementById('fingerprintChargeInput'),
    fingerprintChargesTableBody: document.getElementById('fingerprintChargesTableBody'),
    fingerprintChargesEmpty: document.getElementById('fingerprintChargesEmpty'),
    // Fingerprint Staff
    fingerprintStaffSection: document.getElementById('fingerprintStaffSection'),
    fingerprintStaffTableBody: document.getElementById('fingerprintStaffTableBody'),
    fingerprintStaffEmpty: document.getElementById('fingerprintStaffEmpty'),
    // Booking Index
    bookingIndexSection: document.getElementById('bookingIndexSection'),
    bookingIndexTableBody: document.getElementById('bookingIndexTableBody'),
    bookingIndexEmpty: document.getElementById('bookingIndexEmpty'),
    bookingIndexSearch: document.getElementById('bookingIndexSearch'),
    invoiceDetailsModal: document.getElementById('invoiceDetailsModal'),
    invoiceNo: document.getElementById('invoiceNo'),
    invoiceDate: document.getElementById('invoiceDate'),
    invoiceCustomer: document.getElementById('invoiceCustomer'),
    invoiceStatus: document.getElementById('invoiceStatus'),
    invoiceTotal: document.getElementById('invoiceTotal'),
    invoicePaid: document.getElementById('invoicePaid'),
    invoiceDue: document.getElementById('invoiceDue'),
    invoicePassengers: document.getElementById('invoicePassengers'),
    invoicePaymentsBody: document.getElementById('invoicePaymentsBody'),
    invoicePaymentsEmpty: document.getElementById('invoicePaymentsEmpty'),
    deletePassengerModal: document.getElementById('deletePassengerModal'),
    deletePassengerInfo: document.getElementById('deletePassengerInfo'),
    discountModal: document.getElementById('discountModal'),
    discountOriginalTotal: document.getElementById('discountOriginalTotal'),
    discountType: document.getElementById('discountType'),
    discountValue: document.getElementById('discountValue'),
    discountAmount: document.getElementById('discountAmount'),
    discountNewTotal: document.getElementById('discountNewTotal'),
};

// ============================================
// Toast Notifications
// ============================================
function showToast(message, type = 'success') {
    const toast = document.createElement('div');
    toast.className = `toast px-4 py-3 rounded-lg shadow-lg text-white font-medium transform translate-x-full opacity-0 ${
        type === 'success' ? 'bg-slate-700' : 'bg-red-500'
    }`;
    toast.textContent = message;
    elements.toastContainer.appendChild(toast);

    // Animate in
    requestAnimationFrame(() => {
        toast.classList.remove('translate-x-full', 'opacity-0');
    });

    // Remove after delay
    setTimeout(() => {
        toast.classList.add('translate-x-full', 'opacity-0');
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

// ============================================
// Mobile Menu Toggle
// ============================================
function toggleMobileMenu() {
    const mobileMenu = document.getElementById('mobileMenu');
    mobileMenu.classList.toggle('hidden');
}

// ============================================
// Customer Search Functions
// ============================================
function handleCustomerSearch(e) {
    const term = e.target.value.trim().toLowerCase();
    state.customerSearchTerm = term;

    // Clear if empty
    if (term === '') {
        state.filteredCustomers = [];
        hideSuggestions();
        return;
    }

    // Filter customers by passport OR iqama (partial match, case-insensitive)
    state.filteredCustomers = state.customers.filter(customer => {
        const passportMatch = customer.passport.toLowerCase().includes(term);
        const iqamaMatch = customer.iqama.toLowerCase().includes(term);
        return passportMatch || iqamaMatch;
    });

    // Show suggestions or "Add New Customer" link
    if (state.filteredCustomers.length > 0) {
        renderSuggestions(state.filteredCustomers);
    } else {
        showAddNewCustomerLink();
    }
}

function renderSuggestions(customers) {
    elements.customerSuggestions.innerHTML = '';
    elements.customerSuggestions.classList.remove('hidden');

    customers.forEach(customer => {
        const div = document.createElement('div');
        div.className = 'px-4 py-3 hover:bg-slate-50 cursor-pointer border-b border-slate-100 last:border-b-0 transition';
        div.innerHTML = `
            <div class="font-medium text-slate-800">${customer.name}</div>
            <div class="text-sm text-slate-500">
                Passport: ${customer.passport} | Iqama: ${customer.iqama} | Mobile: ${customer.mobile}
            </div>
        `;
        div.onclick = () => selectCustomer(customer);
        elements.customerSuggestions.appendChild(div);
    });
}

function showAddNewCustomerLink() {
    elements.customerSuggestions.innerHTML = '';
    elements.customerSuggestions.classList.remove('hidden');

    const div = document.createElement('div');
    div.className = 'px-4 py-3 hover:bg-slate-50 cursor-pointer border-b border-slate-100 transition';
    div.innerHTML = `
        <div class="text-slate-600">
            No customer found for "<span class="font-medium">${state.customerSearchTerm}</span>"
        </div>
        <button
            type="button"
            onclick="openCustomerModal()"
            class="mt-2 text-slate-700 font-medium hover:underline flex items-center gap-1"
        >
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
            </svg>
            Add New Customer
        </button>
    `;
    elements.customerSuggestions.appendChild(div);
}

function hideSuggestions() {
    elements.customerSuggestions.classList.add('hidden');
    elements.customerSuggestions.innerHTML = '';
}

function selectCustomer(customer) {
    state.selectedCustomer = customer;
    state.customerSearchTerm = '';
    state.filteredCustomers = [];

    // Update UI
    elements.customerSearch.value = customer.passport || customer.iqama;
    elements.customerSearch.disabled = true;
    hideSuggestions();

    // Show selected customer
    elements.selectedCustomer.classList.remove('hidden');
    elements.selectedCustomerName.textContent = customer.name;
    elements.selectedCustomerPassport.textContent = customer.passport;
    elements.selectedCustomerIqama.textContent = customer.iqama;
    elements.selectedCustomerMobile.textContent = customer.mobile;

    showToast(`Customer "${customer.name}" selected`);
}

function clearSelectedCustomer() {
    state.selectedCustomer = null;
    elements.customerSearch.value = '';
    elements.customerSearch.disabled = false;
    elements.selectedCustomer.classList.add('hidden');
}

// ============================================
// Customer Modal Functions
// ============================================
function openCustomerModal() {
    hideSuggestions();
    state.isCustomerModalOpen = true;
    elements.customerModal.classList.remove('hidden');
    elements.customerName.value = '';
    elements.customerIqama.value = '';
    elements.customerPassport.value = '';
    elements.customerMobile.value = '';
    setTimeout(() => elements.customerName.focus(), 100);
}

function closeCustomerModal() {
    state.isCustomerModalOpen = false;
    elements.customerModal.classList.add('hidden');
}

function handleCustomerSubmit(e) {
    e.preventDefault();

    const name = elements.customerName.value.trim();
    const iqama = elements.customerIqama.value.trim();
    const passport = elements.customerPassport.value.trim();
    const mobile = elements.customerMobile.value.trim();

    // Create new customer - use passport or iqama as ID
    const newCustomer = {
        id: passport || iqama,
        name,
        iqama,
        passport,
        mobile,
    };

    // Add to state
    state.customers.push(newCustomer);

    // Close modal
    closeCustomerModal();

    // Auto-select the new customer
    selectCustomer(newCustomer);

    showToast(`Customer "${name}" added successfully`);
}

function calculatePassengerType(dateOfBirth = null) {
    const dobInput = dateOfBirth || elements.passengerDateOfBirth.value;
    if (!dobInput) {
        elements.passengerType.value = '';
        elements.passengerTypeDisplay.value = '';
        return '';
    }
    
    const dob = new Date(dobInput);
    const today = new Date();
    const ageInMonths = (today - dob) / (1000 * 60 * 60 * 24 * 30.44);
    
    let passengerType;
    if (ageInMonths < 24) {
        passengerType = 'Infant';
    } else if (ageInMonths < 144) {
        passengerType = 'Child';
    } else {
        passengerType = 'Adult';
    }
    
    elements.passengerType.value = passengerType;
    elements.passengerTypeDisplay.value = passengerType;
    return passengerType;
}

// ============================================
// Passenger Modal Functions
// ============================================
function generateFlightDateRangeOptions(bookingDate = new Date(), targetSelect = null) {
    const target = targetSelect || elements.passengerFlightDateRange;
    target.innerHTML = '<option value="">Select Date Range</option>';
    
    const startDate = new Date(bookingDate);
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
            target.appendChild(option);
        }
    }
}

function openPassengerModal(passengerIndex = null) {
    state.editingPassengerIndex = passengerIndex;
    state.isPassengerModalOpen = true;
    elements.passengerModal.classList.remove('hidden');

    generateFlightDateRangeOptions();

    if (passengerIndex !== null) {
        // Editing mode
        const passenger = state.passengers[passengerIndex];
        elements.passengerModalTitle.textContent = 'Edit Passenger';
        
        // Split name into first and last
        const nameParts = (passenger.name || '').split(' ');
        const lastName = nameParts.length > 1 ? nameParts.pop() : '';
        const firstName = nameParts.join(' ');
        
        elements.passengerFirstName.value = firstName;
        elements.passengerLastName.value = lastName;
        elements.passengerPassport.value = passenger.passport || '';
        elements.passengerPassportExpiry.value = passenger.passportExpiry || '';
        elements.passengerDateOfBirth.value = passenger.dateOfBirth || '';
        elements.passengerMobile.value = passenger.mobileNo || '';
        elements.passengerPackage.value = passenger.package || '';
        elements.passengerService.value = passenger.service || '';
        elements.passengerRoute.value = passenger.route || '';
        elements.passengerAirline.value = passenger.airline || '';
        elements.passengerClass.value = passenger.travelClass || '';
        elements.passengerFlightDateRange.value = passenger.flightDateRange || '';
        
        // Calculate and set passenger type from DOB
        if (passenger.dateOfBirth) {
            calculatePassengerType(passenger.dateOfBirth);
        } else {
            elements.passengerType.value = passenger.passengerType || '';
            elements.passengerTypeDisplay.value = passenger.passengerType || '';
        }
        
        elements.passengerAddress.value = passenger.address || '';
        elements.passengerWithOffer.checked = passenger.withOffer || false;
        elements.passengerRefundable.checked = passenger.refundable || false;
    } else {
        // Adding mode
        elements.passengerModalTitle.textContent = 'Add Passenger';
        elements.passengerFirstName.value = '';
        elements.passengerLastName.value = '';
        elements.passengerPassport.value = '';
        elements.passengerPassportExpiry.value = '';
        elements.passengerDateOfBirth.value = '';
        elements.passengerType.value = '';
        elements.passengerTypeDisplay.value = '';
        elements.passengerMobile.value = '';
        elements.passengerPackage.value = '';
        elements.passengerService.value = '';
        elements.passengerRoute.value = '';
        elements.passengerAirline.value = '';
        elements.passengerClass.value = '';
        elements.passengerFlightDateRange.value = '';
        elements.passengerType.value = '';
        elements.passengerAddress.value = '';
        elements.passengerWithOffer.checked = false;
        elements.passengerRefundable.checked = false;
    }

    // Apply checkbox conditional logic
    updateCheckboxState();

    setTimeout(() => elements.passengerName.focus(), 100);
}

function closePassengerModal() {
    state.isPassengerModalOpen = false;
    state.editingPassengerIndex = null;
    elements.passengerModal.classList.add('hidden');
}

function openCustomDurationModal() {
    document.getElementById('customDurationModal').classList.remove('hidden');
    document.getElementById('customDurationDays').value = '';
    setTimeout(() => document.getElementById('customDurationDays').focus(), 100);
}

function closeCustomDurationModal() {
    document.getElementById('customDurationModal').classList.add('hidden');
    document.getElementById('customDurationDays').value = '';
}

function saveCustomDuration() {
    const days = parseInt(document.getElementById('customDurationDays').value);
    if (isNaN(days) || days < 30 || days > 89) {
        alert('Please enter a valid duration between 30 and 89 days');
        return;
    }
    const customValue = `Customized (${days} Days)`;
    
    const select = elements.passengerPackage;
    let customOption = Array.from(select.options).find(opt => opt.value.startsWith('Customized'));
    if (!customOption) {
        customOption = document.createElement('option');
        select.appendChild(customOption);
    }
    customOption.value = customValue;
    customOption.text = customValue;
    
    select.value = customValue;
    closeCustomDurationModal();
}

// Checkbox conditional logic
function updateCheckboxState() {
    const withOffer = elements.passengerWithOffer.checked;
    
    if (withOffer) {
        elements.passengerRefundable.checked = false;
        elements.passengerRefundable.disabled = true;
    } else {
        elements.passengerRefundable.disabled = false;
    }
}

function handlePassengerSubmit(e) {
    e.preventDefault();

    const firstName = elements.passengerFirstName.value.trim();
    const lastName = elements.passengerLastName.value.trim();
    const name = (firstName + ' ' + lastName).trim();
    const passport = elements.passengerPassport.value.trim();
    const passportExpiry = elements.passengerPassportExpiry.value;
    const dateOfBirth = elements.passengerDateOfBirth.value;
    const passengerType = calculatePassengerType(dateOfBirth);
    const mobileNo = elements.passengerMobile.value.trim();
    const package = elements.passengerPackage.value;
    const service = elements.passengerService.value;
    const route = elements.passengerRoute.value;
    const airline = elements.passengerAirline.value;
    const travelClass = elements.passengerClass.value;
    const address = elements.passengerAddress.value.trim();
    const withOffer = elements.passengerWithOffer.checked;
    const refundable = elements.passengerRefundable.checked;
    const flightDateRange = elements.passengerFlightDateRange.value;

    // Validate required fields
    if (!firstName || !lastName || !passport || !dateOfBirth || !package || !service || !route || !airline || !travelClass || !flightDateRange) {
        showToast('Please fill in all required fields', 'error');
        return;
    }

    const passengerData = {
        name,
        firstName,
        lastName,
        passport,
        passportExpiry,
        dateOfBirth,
        passengerType,
        mobileNo,
        package,
        service,
        route,
        airline,
        travelClass,
        flightDateRange,
        address,
        withOffer,
        refundable,
    };

    if (state.editingPassengerIndex !== null) {
        // Update existing passenger
        state.passengers[state.editingPassengerIndex] = passengerData;
        showToast('Passenger updated successfully');
    } else {
        // Add new passenger
        state.passengers.push(passengerData);
        showToast('Passenger added successfully');
    }

    // If editing from Invoice Details, update the booking passenger too
    if (bookingIndexState.editingBookingId) {
        const booking = bookingIndexState.bookings.find(b => b.id === bookingIndexState.editingBookingId);
        if (booking && booking.passengers.length > 0) {
            // Get existing passenger costs or use defaults
            const existingPassenger = booking.passengers[0];
            const ticketFare = existingPassenger.ticketFare || 0;
            const visaCost = existingPassenger.visaCost || 0;
            const fingerprintCost = existingPassenger.fingerprintCost || 200;
            const total = ticketFare + visaCost + fingerprintCost;
            
            // Update passenger in booking with new basic info but preserve costs
            booking.passengers[0] = {
                name: passengerData.name,
                firstName: passengerData.firstName,
                lastName: passengerData.lastName,
                passport: passengerData.passport,
                passportExpiry: passengerData.passportExpiry,
                dateOfBirth: passengerData.dateOfBirth,
                route: passengerData.route,
                airline: passengerData.airline,
                class: passengerData.travelClass,
                flightDateRange: passengerData.flightDateRange,
                passengerType: passengerData.passengerType,
                ticketFare: ticketFare,
                visaCost: visaCost,
                fingerprintCost: fingerprintCost,
                total: total
            };
            
            // Clear editing context
            bookingIndexState.editingBookingId = null;
            
            // Re-render booking index and re-open invoice details
            renderBookingIndex();
            openInvoiceDetails(booking.id);
            showToast('Passenger updated in booking');
        }
    }

    // Close modal and render list
    closePassengerModal();
    renderPassengerList();
}

// ============================================
// Passenger List Functions
// ============================================
function renderPassengerList() {
    const hasPassengers = state.passengers.length > 0;

    // Toggle container visibility
    if (hasPassengers) {
        elements.passengerListContainer.classList.remove('hidden');
        elements.addMoreButtonContainer.classList.remove('hidden');
    } else {
        elements.passengerListContainer.classList.add('hidden');
        elements.addMoreButtonContainer.classList.add('hidden');
    }

    // Render passengers
    elements.passengerList.innerHTML = '';
    state.passengers.forEach((passenger, index) => {
        // Build badge HTML for checkbox states
        let badgeHtml = '';
        if (passenger.withOffer) {
            badgeHtml += '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-700 mr-1">Offer</span>';
        }
        if (passenger.refundable) {
            badgeHtml += '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-700 mr-1">Refundable</span>';
        }

        const card = document.createElement('div');
        card.className = 'bg-slate-50 border border-slate-200 rounded-lg p-4 hover:shadow-md transition';
        card.innerHTML = `
            <div class="flex items-start justify-between">
                <div class="flex-1">
                    <div class="flex items-center gap-3 mb-2">
                        <span class="bg-slate-700 text-white text-xs font-medium px-2 py-1 rounded">P${String(index + 1).padStart(3, '0')}</span>
                        <h4 class="font-semibold text-slate-800">${passenger.name}</h4>
                        ${passenger.mobileNo ? `<span class="text-sm text-slate-500">${passenger.mobileNo}</span>` : ''}
                    </div>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-2 text-sm mb-2">
                        <div>
                            <span class="text-slate-500">Passport:</span>
                            <span class="text-slate-700 ml-1">${passenger.passport}</span>
                        </div>
                        <div>
                            <span class="text-slate-500">Package:</span>
                            <span class="text-slate-700 ml-1">${passenger.package}</span>
                        </div>
                        <div>
                            <span class="text-slate-500">Service:</span>
                            <span class="text-slate-700 ml-1">${passenger.service}</span>
                        </div>
                        <div>
                            <span class="text-slate-500">Route:</span>
                            <span class="text-slate-700 ml-1">${passenger.route || '-'}</span>
                        </div>
                        <div>
                            <span class="text-slate-500">Airline:</span>
                            <span class="text-slate-700 ml-1">${passenger.airline || '-'}</span>
                        </div>
                        <div>
                            <span class="text-slate-500">Class:</span>
                            <span class="text-slate-700 ml-1">${passenger.travelClass || '-'}</span>
                        </div>
                        <div>
                            <span class="text-slate-500">Flight Date:</span>
                            <span class="text-slate-700 ml-1">${passenger.flightDateRange || '-'}</span>
                        </div>
                        <div>
                            <span class="text-slate-500">Type:</span>
                            <span class="text-slate-700 ml-1">${passenger.passengerType || '-'}</span>
                        </div>
                    </div>
                    ${badgeHtml ? `<div class="mt-2">${badgeHtml}</div>` : ''}
                    ${passenger.address ? `<div class="mt-2 text-sm"><span class="text-slate-500">Address:</span> <span class="text-slate-700">${passenger.address}</span></div>` : ''}
                </div>
                <button
                    type="button"
                    onclick="openPassengerModal(${index})"
                    class="ml-4 px-3 py-1.5 text-sm border border-slate-300 text-slate-600 rounded hover:bg-slate-100 transition flex items-center gap-1"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                    </svg>
                    Edit
                </button>
            </div>
        `;
        elements.passengerList.appendChild(card);
    });
}

// ============================================
// Form Control Functions
// ============================================
function showForm() {
    showBookingSection('new');
    elements.defaultView.classList.add('hidden');
    elements.bookingForm.classList.remove('hidden');
}

function hideForm() {
    showBookingSection('index');
}

function clearForm() {
    // Clear customer
    state.selectedCustomer = null;
    state.customerSearchTerm = '';
    state.filteredCustomers = [];
    elements.customerSearch.value = '';
    elements.customerSearch.disabled = false;
    elements.selectedCustomer.classList.add('hidden');
    hideSuggestions();

    // Clear district
    elements.bookingDistrict.value = '';
    elements.bookingFingerprintLocation.value = '';

    // Clear passengers
    state.passengers = [];
    state.editingPassengerIndex = null;
    renderPassengerList();

    showToast('Form cleared');
}

function cancelForm() {
    clearForm();
    hideForm();
    showToast('Form cancelled');
}

function submitForm() {
    // Validate customer selected
    if (!state.selectedCustomer) {
        showToast('Please select a customer', 'error');
        return;
    }

    // Validate at least one passenger
    if (state.passengers.length === 0) {
        showToast('Please add at least one passenger', 'error');
        return;
    }

    // Validate district
    const district = elements.bookingDistrict.value.trim();
    if (!district) {
        showToast('Please enter district', 'error');
        return;
    }

    // Validate fingerprint location
    const fingerprintLocation = elements.bookingFingerprintLocation.value;
    if (!fingerprintLocation) {
        showToast('Please select fingerprint location', 'error');
        return;
    }

    const package = elements.bookingPackage.value || '';

    // Generate invoice number
    const invoiceNo = 'INV-' + passengerIndexState.invoiceCounter++;
    const bookingDate = new Date().toLocaleDateString();

    // Build passengers with calculated totals
    const bookingPassengers = state.passengers.map(passenger => {
        const ticketFare = passenger.ticketFare ? passenger.ticketFare.netFare : 0;
        const visaCost = passenger.visa ? passenger.visa.finalCost : 0;
        const fingerprintCost = 200;
        const total = ticketFare + visaCost + fingerprintCost;
        
        return {
            name: passenger.name,
            firstName: passenger.firstName,
            lastName: passenger.lastName,
            passport: passenger.passport,
            passportExpiry: passenger.passportExpiry,
            dateOfBirth: passenger.dateOfBirth,
            route: passenger.route,
            airline: passenger.airline,
            class: passenger.travelClass,
            flightDateRange: passenger.flightDateRange,
            passengerType: passenger.passengerType,
            ticketFare: ticketFare,
            visaCost: visaCost,
            fingerprintCost: fingerprintCost,
            total: total
        };
    });

    // Add each passenger to passenger index
    bookingPassengers.forEach((passenger, idx) => {
        const indexRow = {
            date: bookingDate,
            invoiceNo: invoiceNo,
            guardianName: state.selectedCustomer.name,
            mobileNo: state.selectedCustomer.mobile,
            passengerName: passenger.name,
            passport: passenger.passport,
            passportExpiry: passenger.passportExpiry,
            dateOfBirth: passenger.dateOfBirth,
            route: passenger.route || '-',
            status: 'None',
            package: state.passengers[idx].package,
            due: '1000 SAR',
            ticketFare: passenger.ticketFare > 0 ? { netFare: passenger.ticketFare } : null,
            visa: passenger.visaCost > 0 ? { finalCost: passenger.visaCost } : null,
            fingerprintLocation: 'None',
            fingerprintCost: '200 SAR',
            individualCost: passenger.total,
            // Store passenger data for ticket fare modal
            passengerData: state.passengers[idx],
        };
        passengerIndexState.passengerIndexRows.push(indexRow);
    });

    // Create booking record for Booking Index
    const bookingId = Date.now();
    const bookingRecord = {
        id: bookingId,
        invoiceNo: invoiceNo,
        bookingDate: bookingDate,
        customerName: state.selectedCustomer.name,
        customerMobile: state.selectedCustomer.mobile,
        district: district,
        fingerprintLocation: fingerprintLocation,
        package: package,
        passengers: bookingPassengers,
        payments: []
    };
    bookingIndexState.bookings.push(bookingRecord);

    // Build booking data
    const bookingRef = 'BK' + Date.now().toString().slice(-8);
    const bookingData = {
        reference: bookingRef,
        invoiceNo: invoiceNo,
        customer: state.selectedCustomer,
        district: district,
        fingerprintLocation: fingerprintLocation,
        package: package,
        passengers: state.passengers,
        totalPassengers: state.passengers.length,
        bookingDate: new Date().toISOString(),
    };

    // Log booking data (in real app, this would send to backend)
    console.log('Booking submitted:', bookingData);

    showToast(`Booking ${bookingRef} submitted with ${state.passengers.length} passenger(s)`);
    
    // Show passenger index
    renderPassengerIndex();
    elements.passengerIndexSection.classList.remove('hidden');
    renderBookingIndex();
    
    // Clear form after successful submission
    clearForm();
    showBookingSection('index');
}

// ============================================
// Passenger Index Functions
// ============================================
function renderPassengerIndex() {
    // Always show the section
    elements.passengerIndexSection.classList.remove('hidden');
    
    const rows = passengerIndexState.passengerIndexRows;
    
    if (rows.length > 0) {
        elements.passengerIndexEmpty.classList.add('hidden');
    } else {
        elements.passengerIndexEmpty.classList.remove('hidden');
    }

    elements.passengerIndexTableBody.innerHTML = '';
    
    // Pre-calculate passenger counts per invoice
    const invoiceCounts = {};
    rows.forEach(row => {
        invoiceCounts[row.invoiceNo] = (invoiceCounts[row.invoiceNo] || 0) + 1;
    });
    
    let prevInvoiceNo = null;
    rows.forEach((row, index) => {
        // Calculate Package Value and Total Cost
        const ticketSellingPrice = row.ticketFare ? row.ticketFare.sellingFare || 0 : 0;
        const visaSellingPrice = row.visa ? row.visa.sellingPrice || 0 : 0;
        const fingerprintCost = row.fingerprintLocation !== 'None' ? 200 : 0;
        const packageValue = ticketSellingPrice + visaSellingPrice + fingerprintCost;
        
        const ticketNetFare = row.ticketFare ? row.ticketFare.netFare || 0 : 0;
        const finalVisaCost = row.visa ? row.visa.finalCost || 0 : 0;
        const totalCost = ticketNetFare + finalVisaCost + fingerprintCost;

        // Determine if this is the first row for this invoice
        const isFirstInInvoice = prevInvoiceNo !== row.invoiceNo;
        const paxQty = isFirstInInvoice ? invoiceCounts[row.invoiceNo] : '';
        prevInvoiceNo = row.invoiceNo;

        const tr = document.createElement('tr');
        tr.className = 'hover:bg-slate-50';
        tr.innerHTML = `
            <td class="px-3 py-2 text-slate-600">${row.date}</td>
            <td class="px-3 py-2 text-slate-600">${row.ticketFare?.date || '-'}</td>
            <td class="px-3 py-2 text-slate-800 font-medium">${row.invoiceNo}</td>
            <td class="px-3 py-2 text-slate-800 font-medium">${paxQty}</td>
            <td class="px-3 py-2 text-slate-600">${row.guardianName}</td>
            <td class="px-3 py-2 text-slate-600">${row.mobileNo}</td>
            <td class="px-3 py-2 text-slate-800">${row.passengerName}</td>
            <td class="px-3 py-2 text-slate-600">${row.passport}</td>
            <td class="px-3 py-2 text-slate-600">${row.route}</td>
            <td class="px-3 py-2">
                <select onchange="updateStatus(${index}, this.value)" class="text-xs border border-slate-300 rounded px-2 py-1 bg-white">
                    ${statusOptions.map(opt => `<option value="${opt}" ${row.status === opt ? 'selected' : ''}>${opt}</option>`).join('')}
                </select>
            </td>
            <td class="px-3 py-2 text-slate-600">${row.package}</td>
            <td class="px-3 py-2 text-slate-600">${row.due}</td>
            <td class="px-3 py-2">
                ${row.ticketFare 
                    ? `<div class="flex items-center gap-1"><span class="text-slate-800 font-medium">${row.ticketFare.sellingFare}</span><button onclick="openTicketFareModal(${index})" class="text-xs text-slate-500 hover:text-slate-700">Issue</button></div>`
                    : `<div class="flex items-center gap-1"><span class="text-slate-400">-</span><button onclick="openTicketFareModal(${index})" class="text-xs text-slate-500 hover:text-slate-700">Issue</button></div>`
                }
            </td>
            <td class="px-3 py-2">
                ${!row.visa 
                    ? `<div class="flex items-center gap-1"><span class="text-slate-400">-</span><button onclick="openVisaCostModal(${index})" class="text-xs text-slate-500 hover:text-slate-700">Submit</button></div>`
                    : row.visa.issued 
                        ? `<div class="flex items-center gap-1"><span class="text-slate-800 font-medium">${row.visa.sellingPrice}</span><button onclick="openVisaEditModal(${index})" class="text-xs text-blue-500 hover:text-blue-700">Edit</button></div>`
                        : `<div class="flex items-center gap-1"><span class="text-slate-800 font-medium">${row.visa.sellingPrice}</span><button onclick="openVisaIssueModal(${index})" class="text-xs text-slate-500 hover:text-slate-700">Issue</button></div>`
                }
            </td>
            <td class="px-3 py-2">
                ${row.visa?.agent 
                    ? `<div class="flex items-center gap-1"><span class="text-slate-600">${row.visa.agent}</span><button onclick="openVisaPaymentModal(${index})" class="text-xs text-blue-500 hover:text-blue-700">Payment</button></div>`
                    : '-'
                }
            </td>
            <td class="px-3 py-2">
                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium ${row.visa?.issued ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700'}">
                    ${row.visa?.issued ? 'Issued' : 'Pending'}
                </span>
            </td>
            <td class="px-3 py-2">
                <select onchange="updateFingerprintLocation(${index}, this.value)" class="text-xs border border-slate-300 rounded px-2 py-1 bg-white">
                    ${fingerprintLocations.map(loc => `<option value="${loc}" ${row.fingerprintLocation === loc ? 'selected' : ''}>${loc}</option>`).join('')}
                </select>
            </td>
            <td class="px-3 py-2">
                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium ${row.fingerprintLocation !== 'None' ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700'}">
                    ${row.fingerprintLocation !== 'None' ? 'Done' : 'Pending'}
                </span>
            </td>
            <td class="px-3 py-2 text-slate-600">${row.fingerprintLocation !== 'None' ? row.fingerprintCost : '-'}</td>
            <td class="px-3 py-2 text-slate-800 font-medium">${packageValue}</td>
            <td class="px-3 py-2 text-slate-800 font-medium">${totalCost}</td>
        `;
        elements.passengerIndexTableBody.appendChild(tr);
    });
}

function updateStatus(index, value) {
    passengerIndexState.passengerIndexRows[index].status = value;
    showToast('Status updated');
}

function updateFingerprintLocation(index, value) {
    passengerIndexState.passengerIndexRows[index].fingerprintLocation = value;
    renderPassengerIndex();
    showToast('Fingerprint location updated');
}

// ============================================
// Ticket Fare Modal Functions
// ============================================
function openTicketFareModal(rowIndex) {
    passengerIndexState.editingTicketFareRowIndex = rowIndex;
    passengerIndexState.isTicketFareModalOpen = true;
    elements.ticketFareModal.classList.remove('hidden');

    const row = passengerIndexState.passengerIndexRows[rowIndex];
    const passenger = row.passengerData;

    generateFlightDateRangeOptions(new Date(), elements.ticketFareFlightDateRange);

    if (row.ticketFare) {
        // Edit mode
        elements.ticketFareModalTitle.textContent = 'Issue Ticket';
        elements.ticketFareDate.value = row.ticketFare.date || '';
        elements.ticketFareAgent.value = row.ticketFare.ticketAgent || '';
        elements.ticketFareRoute.value = row.ticketFare.route || '';
        elements.ticketFareAirline.value = row.ticketFare.airline || '';
        elements.ticketFareClass.value = row.ticketFare.travelClass || '';
        elements.ticketFareFlightDateRange.value = row.ticketFare.flightDateRange || '';
        elements.ticketFarePassengerType.value = row.ticketFare.passengerType || '';
        elements.ticketFareFinalFlightDate.value = row.ticketFare.finalFlightDate || '';
        elements.ticketFareSellingFare.value = row.ticketFare.sellingFare || 0;
        elements.ticketFareNet.value = row.ticketFare.netFare || 0;
    } else {
        // Add mode - prefill from passenger data
        elements.ticketFareModalTitle.textContent = 'Issue Ticket';
        elements.ticketFareDate.value = new Date().toISOString().split('T')[0];
        elements.ticketFareAgent.value = '';
        elements.ticketFareRoute.value = passenger.route || '';
        elements.ticketFareAirline.value = passenger.airline || '';
        elements.ticketFareClass.value = passenger.travelClass || '';
        elements.ticketFareFlightDateRange.value = passenger.flightDateRange || '';
        elements.ticketFarePassengerType.value = passenger.passengerType || '';
        elements.ticketFareFinalFlightDate.value = '';
        elements.ticketFareSellingFare.value = 0;
        elements.ticketFareNet.value = 0;
    }

    setTimeout(() => elements.ticketFareDate.focus(), 100);
}

function closeTicketFareModal() {
    passengerIndexState.isTicketFareModalOpen = false;
    passengerIndexState.editingTicketFareRowIndex = null;
    elements.ticketFareModal.classList.add('hidden');
}

function updateTicketFareCheckboxState() {
    const withOffer = elements.ticketFareWithOffer.checked;
    
    if (withOffer) {
        elements.ticketFareRefundable.checked = false;
        elements.ticketFareRefundable.disabled = true;
    } else {
        elements.ticketFareRefundable.disabled = false;
    }
}

function calculateTicketFare() {
    const route = elements.ticketFareRoute.value;
    const airline = elements.ticketFareAirline.value;
    const travelClass = elements.ticketFareClass.value;
    const passengerType = elements.ticketFarePassengerType.value;
    const withOffer = elements.ticketFareWithOffer.checked;
    const discountType = elements.ticketFareDiscountType.value;
    const discountValue = parseFloat(elements.ticketFareDiscountValue.value) || 0;

    let grossFare = 0;

    // Get pricing from matrix
    if (ticketPricing[route] && ticketPricing[route][airline] && ticketPricing[route][airline][travelClass]) {
        const pricing = ticketPricing[route][airline][travelClass];
        const typeKey = passengerType || 'Adult';
        
        if (withOffer && pricing['offer' + typeKey]) {
            grossFare = pricing['offer' + typeKey];
        } else {
            grossFare = pricing[typeKey] || 0;
        }
    }

    // Calculate net fare
    let netFare = grossFare;
    if (discountType === 'percentage') {
        netFare = grossFare - (grossFare * discountValue / 100);
    } else {
        netFare = grossFare - discountValue;
    }
    netFare = Math.max(0, netFare);

    elements.ticketFareGross.value = grossFare;
    elements.ticketFareNet.value = Math.round(netFare);
}

function handleTicketFareSubmit(e) {
    e.preventDefault();

    const rowIndex = passengerIndexState.editingTicketFareRowIndex;
    if (rowIndex === null) return;

    const ticketFareData = {
        date: elements.ticketFareDate.value,
        ticketAgent: elements.ticketFareAgent.value,
        route: elements.ticketFareRoute.value,
        airline: elements.ticketFareAirline.value,
        travelClass: elements.ticketFareClass.value,
        flightDateRange: elements.ticketFareFlightDateRange.value,
        finalFlightDate: elements.ticketFareFinalFlightDate.value,
        passengerType: elements.ticketFarePassengerType.value,
        sellingFare: parseFloat(elements.ticketFareSellingFare.value) || 0,
        netFare: parseFloat(elements.ticketFareNet.value) || 0,
    };

    passengerIndexState.passengerIndexRows[rowIndex].ticketFare = ticketFareData;
    
    closeTicketFareModal();
    renderPassengerIndex();
    showToast('Ticket fare saved');
}

// ============================================
// Visa Cost Modal Functions
// ============================================
function openVisaCostModal(rowIndex) {
    passengerIndexState.editingVisaCostRowIndex = rowIndex;
    passengerIndexState.isVisaCostModalOpen = true;
    elements.visaCostModal.classList.remove('hidden');

    const row = passengerIndexState.passengerIndexRows[rowIndex];

    if (row.visa) {
        // Edit mode
        elements.visaCostModalTitle.textContent = 'Visa Submit Form';
        elements.visaCostAgent.value = row.visa.agent || '';
        elements.visaCostAgentCommission.value = row.visa.agentCommission || 0;
        elements.visaCostNetVisaCost.value = row.visa.netVisaCost || 0;
        elements.visaCostSellingPrice.value = row.visa.sellingPrice || 0;
        
        // Update commission agent options based on visa agent
        updateCommissionAgentOptions(row.visa.agent);
        
        // Set commission agent after options are populated
        setTimeout(() => {
            elements.visaCostCommissionAgent.value = row.visa.commissionAgent || '';
        }, 50);
    } else {
        // Add mode - pre-populate selling price from visa index
        elements.visaCostModalTitle.textContent = 'Visa Submit Form';
        elements.visaCostAgent.value = '';
        elements.visaCostCommissionAgent.innerHTML = '<option value="">Select Commission Agent</option>';
        elements.visaCostAgentCommission.value = 0;
        elements.visaCostNetVisaCost.value = 0;
        elements.visaCostSellingPrice.value = row.visa ? (row.visa.sellingPrice || 0) : 0;
    }

    calculateVisaCost();
    setTimeout(() => elements.visaCostAgent.focus(), 100);
}

function updateCommissionAgentOptions() {
    const visaAgent = elements.visaCostAgent.value;
    const commissionAgents = visaAgentCommissionAgents[visaAgent] || [];
    
    elements.visaCostCommissionAgent.innerHTML = '<option value="">Select Commission Agent</option>';
    commissionAgents.forEach(agent => {
        const option = document.createElement('option');
        option.value = agent;
        option.textContent = agent;
        elements.visaCostCommissionAgent.appendChild(option);
    });
}

function closeVisaCostModal() {
    passengerIndexState.isVisaCostModalOpen = false;
    passengerIndexState.editingVisaCostRowIndex = null;
    elements.visaCostModal.classList.add('hidden');
}

function calculateVisaCost() {
    const agentCommission = parseFloat(elements.visaCostAgentCommission.value) || 0;
    const netVisaCost = parseFloat(elements.visaCostNetVisaCost.value) || 0;
    
    const finalCost = agentCommission + netVisaCost;
    
    elements.visaCostFinal.value = finalCost;
}

function handleVisaCostSubmit(e) {
    e.preventDefault();

    const rowIndex = passengerIndexState.editingVisaCostRowIndex;
    if (rowIndex === null) return;

    const agentCommission = parseFloat(elements.visaCostAgentCommission.value) || 0;
    const netVisaCost = parseFloat(elements.visaCostNetVisaCost.value) || 0;

    const visaData = {
        agent: elements.visaCostAgent.value,
        commissionAgent: elements.visaCostCommissionAgent.value,
        sellingPrice: parseFloat(elements.visaCostSellingPrice.value) || 0,
        agentCommission: agentCommission,
        netVisaCost: netVisaCost,
        finalCost: agentCommission + netVisaCost,
    };

    passengerIndexState.passengerIndexRows[rowIndex].visa = visaData;
    
    closeVisaCostModal();
    renderPassengerIndex();
    showToast('Visa cost saved');
}

// ============================================
// Visa Issue Modal Functions
// ============================================
let editingVisaIssueRowIndex = null;

function openVisaIssueModal(rowIndex) {
    editingVisaIssueRowIndex = rowIndex;
    elements.visaIssueModal.classList.remove('hidden');

    const row = passengerIndexState.passengerIndexRows[rowIndex];
    const visa = row.visa;

    if (visa) {
        elements.visaIssueAgent.value = visa.agent || '';
        elements.visaIssueVisaNumber.value = visa.visaNumber || '';
        elements.visaIssueSellingPrice.value = visa.sellingPrice || 0;
        elements.visaIssueAdditionalCost.value = visa.additionalCost || 0;
        elements.visaIssueRemarks.value = visa.remarks || '';
        
        const sellingPrice = visa.sellingPrice || 0;
        const additionalCost = visa.additionalCost || 0;
        elements.visaIssueTotalCost.value = sellingPrice + additionalCost;
    }
}

function closeVisaIssueModal() {
    editingVisaIssueRowIndex = null;
    elements.visaIssueModal.classList.add('hidden');
}

function calculateVisaIssueTotal() {
    const sellingPrice = parseFloat(elements.visaIssueSellingPrice.value) || 0;
    const additionalCost = parseFloat(elements.visaIssueAdditionalCost.value) || 0;
    elements.visaIssueTotalCost.value = sellingPrice + additionalCost;
}

function handleVisaIssueSubmit(e) {
    e.preventDefault();

    const rowIndex = editingVisaIssueRowIndex;
    if (rowIndex === null) return;

    const visa = passengerIndexState.passengerIndexRows[rowIndex].visa;
    const additionalCost = parseFloat(elements.visaIssueAdditionalCost.value) || 0;
    const remarks = elements.visaIssueRemarks.value;
    const visaNumber = elements.visaIssueVisaNumber.value;

    visa.visaNumber = visaNumber;
    visa.additionalCost = additionalCost;
    visa.remarks = remarks;
    visa.issued = true;

    passengerIndexState.passengerIndexRows[rowIndex].visa = visa;
    
    closeVisaIssueModal();
    renderPassengerIndex();
    showToast('Visa issued successfully');
}

// ============================================
// Visa Edit Modal Functions
// ============================================
let editingVisaEditRowIndex = null;

function openVisaEditModal(rowIndex) {
    editingVisaEditRowIndex = rowIndex;
    elements.visaEditModal.classList.remove('hidden');

    const row = passengerIndexState.passengerIndexRows[rowIndex];
    const visa = row.visa;

    if (visa) {
        elements.visaEditAgent.value = visa.agent || '';
        
        const commissionAgents = {
            "Visa Agent A": ["Commission Agent 1", "Commission Agent 2"],
            "Visa Agent B": ["Commission Agent 3", "Commission Agent 4"]
        };
        const commissionSelect = elements.visaEditCommissionAgent;
        commissionSelect.innerHTML = '<option value="">Select Commission Agent</option>';
        const agents = commissionAgents[visa.agent] || [];
        agents.forEach(a => {
            const option = document.createElement('option');
            option.value = a;
            option.textContent = a;
            commissionSelect.appendChild(option);
        });
        
        setTimeout(() => {
            elements.visaEditCommissionAgent.value = visa.commissionAgent || '';
        }, 50);
        
        elements.visaEditSellingPrice.value = visa.sellingPrice || 0;
        elements.visaEditVisaNumber.value = visa.visaNumber || '';
        elements.visaEditAgentCommission.value = visa.agentCommission || 0;
        elements.visaEditNetVisaCost.value = visa.netVisaCost || 0;
        elements.visaEditAdditionalCost.value = visa.additionalCost || 0;
        elements.visaEditRemarks.value = visa.remarks || '';
        
        const statusEl = elements.visaEditStatus;
        statusEl.textContent = visa.issued ? 'Issued' : 'Pending';
        statusEl.className = `inline-flex items-center px-2 py-1 rounded text-xs font-medium ${visa.issued ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700'}`;
        
        calculateVisaEditFinal();
    }
}

function closeVisaEditModal() {
    editingVisaEditRowIndex = null;
    elements.visaEditModal.classList.add('hidden');
}

function calculateVisaEditFinal() {
    const agentCommission = parseFloat(elements.visaEditAgentCommission.value) || 0;
    const netVisaCost = parseFloat(elements.visaEditNetVisaCost.value) || 0;
    const additionalCost = parseFloat(elements.visaEditAdditionalCost.value) || 0;
    elements.visaEditFinalCost.value = agentCommission + netVisaCost + additionalCost;
}

function handleVisaEditSubmit(e) {
    e.preventDefault();

    const rowIndex = editingVisaEditRowIndex;
    if (rowIndex === null) return;

    const row = passengerIndexState.passengerIndexRows[rowIndex];
    const visa = row.visa || {};

    visa.agent = elements.visaEditAgent.value;
    visa.visaNumber = elements.visaEditVisaNumber.value;
    visa.commissionAgent = elements.visaEditCommissionAgent.value;
    visa.sellingPrice = parseFloat(elements.visaEditSellingPrice.value) || 0;
    visa.agentCommission = parseFloat(elements.visaEditAgentCommission.value) || 0;
    visa.netVisaCost = parseFloat(elements.visaEditNetVisaCost.value) || 0;
    visa.additionalCost = parseFloat(elements.visaEditAdditionalCost.value) || 0;
    visa.remarks = elements.visaEditRemarks.value;
    visa.finalCost = parseFloat(elements.visaEditFinalCost.value) || 0;

    passengerIndexState.passengerIndexRows[rowIndex].visa = visa;
    
    closeVisaEditModal();
    renderPassengerIndex();
    showToast('Visa updated successfully');
}

// ============================================
// Visa Payment Modal Functions
// ============================================
let editingVisaPaymentRowIndex = null;

function openVisaPaymentModal(rowIndex) {
    editingVisaPaymentRowIndex = rowIndex;
    elements.visaPaymentModal.classList.remove('hidden');
    
    elements.visaPaymentPayTo.value = '';
    elements.visaPaymentMethod.value = '';
    elements.visaPaymentAmount.value = '';
    
    setTimeout(() => elements.visaPaymentPayTo.focus(), 100);
}

function closeVisaPaymentModal() {
    editingVisaPaymentRowIndex = null;
    elements.visaPaymentModal.classList.add('hidden');
}

function handleVisaPaymentSubmit(e) {
    e.preventDefault();
    
    const rowIndex = editingVisaPaymentRowIndex;
    if (rowIndex === null) return;
    
    const payTo = elements.visaPaymentPayTo.value;
    const paymentMethod = elements.visaPaymentMethod.value;
    const amount = parseFloat(elements.visaPaymentAmount.value) || 0;
    
    if (!payTo || !paymentMethod || amount <= 0) {
        showToast('Please fill all fields correctly');
        return;
    }
    
    const payment = {
        payTo: payTo,
        paymentMethod: paymentMethod,
        amount: amount,
        date: new Date().toISOString().split('T')[0]
    };
    
    if (!passengerIndexState.passengerIndexRows[rowIndex].visa.payments) {
        passengerIndexState.passengerIndexRows[rowIndex].visa.payments = [];
    }
    
    passengerIndexState.passengerIndexRows[rowIndex].visa.payments.push(payment);
    
    closeVisaPaymentModal();
    renderPassengerIndex();
    showToast('Payment saved successfully');
}

// ============================================
// Tab Navigation
// ============================================
function switchTab(tab) {
    const fareAdminSection = elements.fareAdminSection;
    const visaAdminSection = elements.visaAdminSection;
    const settingsSection = elements.settingsSection;
    const fingerprintStaffSection = elements.fingerprintStaffSection;
    const bookingSection = document.getElementById('bookingSection');
    const bookingIndexSection = elements.bookingIndexSection;
    const passengerIndexSection = elements.passengerIndexSection;
    
    // Update nav-item active states
    document.querySelectorAll('.nav-item').forEach(item => {
        item.classList.remove('active');
    });
    document.querySelector(`.nav-item[data-tab="${tab}"]`)?.classList.add('active');
    
    // Hide all sections
    fareAdminSection.classList.add('hidden');
    visaAdminSection.classList.add('hidden');
    settingsSection.classList.add('hidden');
    fingerprintStaffSection.classList.add('hidden');
    bookingSection.classList.add('hidden');
    bookingIndexSection.classList.add('hidden');
    passengerIndexSection.classList.add('hidden');
    
    if (tab === 'fareAdmin') {
        fareAdminSection.classList.remove('hidden');
    } else if (tab === 'visaAdmin') {
        visaAdminSection.classList.remove('hidden');
    } else if (tab === 'settings') {
        settingsSection.classList.remove('hidden');
        showSettingsTab();
    } else if (tab === 'fingerprintStaff') {
        fingerprintStaffSection.classList.remove('hidden');
        renderFingerprintStaffIndex();
    } else if (tab === 'bookingIndex') {
        bookingSection.classList.remove('hidden');
        showBookingSection('index');
    }
    
    // Close mobile menu
    document.getElementById('mobileMenu').classList.add('hidden');
}

function showBookingSection(section) {
    const subTabIndex = document.getElementById('subTabIndex');
    const subTabNew = document.getElementById('subTabNew');
    const bookingIndexSection = elements.bookingIndexSection;
    const defaultView = elements.defaultView;
    const bookingForm = elements.bookingForm;
    const passengerIndexSection = elements.passengerIndexSection;
    
    if (section === 'index') {
        // Booking Index - show table + Add Booking button
        subTabIndex.className = 'px-4 py-2 rounded-lg font-medium bg-slate-700 text-white';
        subTabNew.className = 'px-4 py-2 rounded-lg font-medium bg-slate-200 text-slate-700 hover:bg-slate-300';
        bookingIndexSection.classList.remove('hidden');
        defaultView.classList.remove('hidden');  // Show Add Booking button
        bookingForm.classList.add('hidden');
        passengerIndexSection.classList.add('hidden');
        renderBookingIndex();
    } else {
        // Passenger Index - show table + Add Booking button
        subTabIndex.className = 'px-4 py-2 rounded-lg font-medium bg-slate-200 text-slate-700 hover:bg-slate-300';
        subTabNew.className = 'px-4 py-2 rounded-lg font-medium bg-slate-700 text-white';
        bookingIndexSection.classList.add('hidden');
        defaultView.classList.remove('hidden');  // Show Add Booking button
        bookingForm.classList.add('hidden');
        passengerIndexSection.classList.remove('hidden');  // Show Passenger Index
        renderPassengerIndex();
    }
}

// ============================================
// Fare Admin Functions
// ============================================
function renderFareIndex() {
    const records = fareAdminState.fareRecords;
    
    if (records.length > 0) {
        elements.fareIndexEmpty.classList.add('hidden');
    } else {
        elements.fareIndexEmpty.classList.remove('hidden');
    }

    elements.fareIndexTableBody.innerHTML = '';
    records.forEach((record, index) => {
        const tr = document.createElement('tr');
        tr.className = 'hover:bg-slate-50';
        tr.innerHTML = `
            <td class="px-3 py-2 text-slate-600">${record.date}</td>
            <td class="px-3 py-2 text-slate-800 font-medium">${record.airline}</td>
            <td class="px-3 py-2 text-slate-600">${record.travelClass}</td>
            <td class="px-3 py-2 text-slate-600">${record.route}</td>
            <td class="px-3 py-2 text-slate-600">${record.passengerType || '-'}</td>
            <td class="px-3 py-2 text-slate-800 font-medium">${record.netFare || 0}</td>
            <td class="px-3 py-2 text-slate-800 font-medium">${record.sellingFare || 0}</td>
            <td class="px-3 py-2">
                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium ${record.withOffer ? 'bg-green-100 text-green-700' : 'bg-slate-100 text-slate-600'}">
                    ${record.withOffer ? 'Yes' : 'No'}
                </span>
            </td>
            <td class="px-3 py-2 text-slate-600">${record.offerPrice || '-'}</td>
            <td class="px-3 py-2 text-slate-600">${record.effectiveFrom || '-'}</td>
            <td class="px-3 py-2 text-slate-600">${record.effectiveTo || '-'}</td>
            <td class="px-3 py-2">
                <div class="flex gap-2">
                    <button onclick="openViewFareModal(${record.id})" class="text-xs bg-slate-100 hover:bg-slate-200 text-slate-600 px-2 py-1 rounded">View</button>
                    <button onclick="openEditFareModal(${record.id})" class="text-xs bg-slate-100 hover:bg-slate-200 text-slate-600 px-2 py-1 rounded">Edit</button>
                    <button onclick="openDeleteFareModal(${record.id})" class="text-xs bg-red-100 hover:bg-red-200 text-red-600 px-2 py-1 rounded">Delete</button>
                </div>
            </td>
        `;
        elements.fareIndexTableBody.appendChild(tr);
    });
}

function openAddFareModal() {
    fareAdminState.editingFareId = null;
    elements.fareModalTitle.textContent = 'Add Fare';
    elements.fareCoreFields.querySelectorAll('input, select').forEach(el => el.disabled = false);
    
    elements.fareDate.value = new Date().toISOString().split('T')[0];
    elements.fareAirline.value = '';
    elements.fareClass.value = '';
    elements.fareRoute.value = '';
    elements.farePassengerType.value = '';
    elements.fareNetFare.value = '';
    elements.fareSellingFare.value = '';
    elements.fareWithOffer.checked = false;
    elements.fareOfferPrice.value = '';
    elements.fareEffectiveFrom.value = '';
    elements.fareEffectiveTo.value = '';
    
    updateFareOfferFields();
    elements.fareModal.classList.remove('hidden');
}

function openEditFareModal(fareId) {
    const record = fareAdminState.fareRecords.find(f => f.id === fareId);
    if (!record) return;
    
    fareAdminState.editingFareId = fareId;
    elements.fareModalTitle.textContent = 'Edit Fare';
    
    // Disable core fields (read-only)
    elements.fareDate.disabled = true;
    elements.fareAirline.disabled = true;
    elements.fareClass.disabled = true;
    elements.fareRoute.disabled = true;
    elements.farePassengerType.disabled = true;
    
    elements.fareDate.value = record.date;
    elements.fareAirline.value = record.airline;
    elements.fareClass.value = record.travelClass;
    elements.fareRoute.value = record.route;
    elements.farePassengerType.value = record.passengerType || '';
    elements.fareNetFare.value = record.netFare || '';
    elements.fareSellingFare.value = record.sellingFare || '';
    elements.fareWithOffer.checked = record.withOffer;
    elements.fareOfferPrice.value = record.offerPrice || '';
    elements.fareEffectiveFrom.value = record.effectiveFrom || '';
    elements.fareEffectiveTo.value = record.effectiveTo || '';
    
    updateFareOfferFields();
    elements.fareModal.classList.remove('hidden');
}

function closeFareModal() {
    fareAdminState.editingFareId = null;
    elements.fareModal.classList.add('hidden');
}

function updateFareOfferFields() {
    if (elements.fareWithOffer.checked) {
        elements.fareOfferFields.classList.remove('hidden');
        if (!elements.fareEffectiveFrom.value) {
            elements.fareEffectiveFrom.value = new Date().toISOString().split('T')[0];
        }
    } else {
        elements.fareOfferFields.classList.add('hidden');
        elements.fareOfferPrice.value = '';
        elements.fareEffectiveFrom.value = '';
        elements.fareEffectiveTo.value = '';
    }
}

function handleFareSubmit(e) {
    e.preventDefault();
    
    const fareData = {
        date: elements.fareDate.value,
        airline: elements.fareAirline.value,
        travelClass: elements.fareClass.value,
        route: elements.fareRoute.value,
        passengerType: elements.farePassengerType.value,
        netFare: parseFloat(elements.fareNetFare.value) || 0,
        sellingFare: parseFloat(elements.fareSellingFare.value) || 0,
        withOffer: elements.fareWithOffer.checked,
        offerPrice: elements.fareWithOffer.checked ? (parseFloat(elements.fareOfferPrice.value) || null) : null,
        effectiveFrom: elements.fareWithOffer.checked ? (elements.fareEffectiveFrom.value || null) : null,
        effectiveTo: elements.fareWithOffer.checked ? (elements.fareEffectiveTo.value || null) : null,
    };
    
    if (fareAdminState.editingFareId) {
        // Update existing
        const index = fareAdminState.fareRecords.findIndex(f => f.id === fareAdminState.editingFareId);
        if (index !== -1) {
            fareAdminState.fareRecords[index] = { ...fareAdminState.fareRecords[index], ...fareData };
        }
        showToast('Fare updated successfully');
    } else {
        // Add new
        const newId = Math.max(...fareAdminState.fareRecords.map(f => f.id), 0) + 1;
        fareAdminState.fareRecords.push({ id: newId, ...fareData });
        showToast('Fare added successfully');
    }
    
    closeFareModal();
    renderFareIndex();
}

function openViewFareModal(fareId) {
    const record = fareAdminState.fareRecords.find(f => f.id === fareId);
    if (!record) return;
    
    elements.viewFareContent.innerHTML = `
        <div class="grid grid-cols-2 gap-2">
            <span class="text-slate-500">Date:</span>
            <span class="text-slate-800">${record.date}</span>
            <span class="text-slate-500">Airline:</span>
            <span class="text-slate-800">${record.airline}</span>
            <span class="text-slate-500">Class:</span>
            <span class="text-slate-800">${record.travelClass}</span>
            <span class="text-slate-500">Route:</span>
            <span class="text-slate-800">${record.route}</span>
            <span class="text-slate-500">Fare:</span>
            <span class="text-slate-800 font-medium">${record.fare} SAR</span>
            <span class="text-slate-500">With Offer:</span>
            <span class="text-slate-800">${record.withOffer ? 'Yes' : 'No'}</span>
            <span class="text-slate-500">Offer Price:</span>
            <span class="text-slate-800">${record.offerPrice || '-'}</span>
            <span class="text-slate-500">Effective From:</span>
            <span class="text-slate-800">${record.effectiveFrom || '-'}</span>
            <span class="text-slate-500">Effective To:</span>
            <span class="text-slate-800">${record.effectiveTo || '-'}</span>
        </div>
    `;
    elements.viewFareModal.classList.remove('hidden');
}

function closeViewFareModal() {
    elements.viewFareModal.classList.add('hidden');
}

function openDeleteFareModal(fareId) {
    const record = fareAdminState.fareRecords.find(f => f.id === fareId);
    if (!record) return;
    
    fareAdminState.selectedFareId = fareId;
    elements.deleteFareInfo.innerHTML = `
        <strong>${record.airline}</strong> - ${record.travelClass}<br>
        <span class="text-slate-500">${record.route}</span> | 
        <span class="text-slate-800 font-medium">${record.fare} SAR</span>
    `;
    elements.deleteFareModal.classList.remove('hidden');
}

function closeDeleteFareModal() {
    fareAdminState.selectedFareId = null;
    elements.deleteFareModal.classList.add('hidden');
}

function confirmDeleteFare() {
    if (fareAdminState.selectedFareId) {
        fareAdminState.fareRecords = fareAdminState.fareRecords.filter(f => f.id !== fareAdminState.selectedFareId);
        renderFareIndex();
        showToast('Fare deleted successfully');
    }
    closeDeleteFareModal();
}

// ============================================
// Visa Admin Functions
// ============================================
function sortVisaPriceRecords() {
    visaAdminState.visaPriceRecords.sort((a, b) => new Date(b.date) - new Date(a.date));
}

function renderVisaPriceIndex() {
    const records = visaAdminState.visaPriceRecords;
    
    if (records.length > 0) {
        elements.visaPriceEmpty.classList.add('hidden');
    } else {
        elements.visaPriceEmpty.classList.remove('hidden');
    }

    elements.visaPriceTableBody.innerHTML = '';
    records.forEach((record) => {
        const tr = document.createElement('tr');
        tr.className = 'hover:bg-slate-50';
        tr.innerHTML = `
            <td class="px-3 py-2 text-slate-600">${record.date}</td>
            <td class="px-3 py-2 text-slate-800 font-medium">${record.price} SAR</td>
            <td class="px-3 py-2">
                <button onclick="openEditVisaPriceModal(${record.id})" class="text-xs bg-slate-100 hover:bg-slate-200 text-slate-600 px-2 py-1 rounded">Edit</button>
            </td>
        `;
        elements.visaPriceTableBody.appendChild(tr);
    });
}

function openAddVisaPriceModal() {
    visaAdminState.editingVisaPriceId = null;
    elements.visaPriceModalTitle.textContent = 'Add Visa Price';
    elements.visaPriceDate.value = new Date().toISOString().split('T')[0];
    elements.visaPriceAmount.value = '';
    elements.visaPriceModal.classList.remove('hidden');
}

function openEditVisaPriceModal(visaPriceId) {
    const record = visaAdminState.visaPriceRecords.find(v => v.id === visaPriceId);
    if (!record) return;
    
    visaAdminState.editingVisaPriceId = visaPriceId;
    elements.visaPriceModalTitle.textContent = 'Edit Visa Price';
    elements.visaPriceDate.value = record.date;
    elements.visaPriceAmount.value = record.price;
    elements.visaPriceModal.classList.remove('hidden');
}

function closeVisaPriceModal() {
    visaAdminState.editingVisaPriceId = null;
    elements.visaPriceModal.classList.add('hidden');
}

function handleVisaPriceSubmit(e) {
    e.preventDefault();
    
    const date = elements.visaPriceDate.value;
    const price = parseFloat(elements.visaPriceAmount.value) || 0;
    
    if (visaAdminState.editingVisaPriceId) {
        // Update existing
        const index = visaAdminState.visaPriceRecords.findIndex(v => v.id === visaAdminState.editingVisaPriceId);
        if (index !== -1) {
            visaAdminState.visaPriceRecords[index].date = date;
            visaAdminState.visaPriceRecords[index].price = price;
        }
        showToast('Visa price updated successfully');
    } else {
        // Add new
        const newId = Math.max(...visaAdminState.visaPriceRecords.map(v => v.id), 0) + 1;
        visaAdminState.visaPriceRecords.push({ id: newId, date, price });
        showToast('Visa price added successfully');
    }
    
    sortVisaPriceRecords();
    closeVisaPriceModal();
    renderVisaPriceIndex();
}

// ============================================
// Booking Index Functions
// ============================================
function searchBookingIndex() {
    const searchTerm = document.getElementById('bookingIndexSearch')?.value.toLowerCase() || '';
    const filteredBookings = bookingIndexState.bookings.filter(booking => {
        const mobile = (booking.customerMobile || '').toLowerCase();
        const invoiceNo = (booking.invoiceNo || '').toLowerCase();
        return mobile.includes(searchTerm) || invoiceNo.includes(searchTerm);
    });
    renderBookingIndex(filteredBookings);
}

function renderBookingIndex(bookingsToRender) {
    const bookings = bookingsToRender || bookingIndexState.bookings;
    
    if (bookings.length > 0) {
        elements.bookingIndexEmpty.classList.add('hidden');
    } else {
        elements.bookingIndexEmpty.classList.remove('hidden');
    }

    elements.bookingIndexTableBody.innerHTML = '';
    bookings.forEach((booking) => {
        const totalValue = booking.passengers.reduce((sum, p) => sum + (p.total || 0), 0);
        const totalPaid = booking.payments.reduce((sum, p) => sum + (p.amount || 0), 0);
        const due = totalValue - totalPaid;
        
        const firstPassenger = booking.passengers[0];
        const flightDate = firstPassenger?.flightDateRange || '-';
        
        const reIssueTickets = booking.reIssueTickets || 0;
        const reIssueCost = booking.reIssueCost || 0;
        const refundTickets = booking.refundTickets || 0;
        const refundAmount = booking.refundAmount || 0;
        
        let status = 'Unpaid';
        let statusClass = 'bg-red-100 text-red-700';
        if (totalPaid >= totalValue) {
            status = 'Paid';
            statusClass = 'bg-green-100 text-green-700';
        } else if (totalPaid > 0) {
            status = 'Partial';
            statusClass = 'bg-yellow-100 text-yellow-700';
        }

        const tr = document.createElement('tr');
        tr.className = 'hover:bg-slate-50';
        tr.innerHTML = `
            <td class="px-3 py-2 text-slate-800 font-medium">${booking.invoiceNo}</td>
            <td class="px-3 py-2 text-slate-600">${booking.bookingDate}</td>
            <td class="px-3 py-2 text-slate-600">${flightDate}</td>
            <td class="px-3 py-2 text-slate-600">${booking.customerName}</td>
            <td class="px-3 py-2 text-slate-600">${booking.customerMobile || '-'}</td>
            <td class="px-3 py-2 text-slate-600">${booking.passengers.length}</td>
            <td class="px-3 py-2 text-slate-600">${reIssueTickets}</td>
            <td class="px-3 py-2 text-slate-600">${reIssueCost}</td>
            <td class="px-3 py-2 text-slate-600">${refundTickets}</td>
            <td class="px-3 py-2 text-slate-600">${refundAmount}</td>
            <td class="px-3 py-2 text-slate-800 font-medium">${totalValue} SAR</td>
            <td class="px-3 py-2 text-green-600 font-medium">${totalPaid} SAR</td>
            <td class="px-3 py-2 text-red-600 font-medium">${due} SAR</td>
            <td class="px-3 py-2">
                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium ${statusClass}">${status}</span>
            </td>
            <td class="px-3 py-2">
                <button onclick="openInvoiceDetails(${booking.id})" class="text-xs bg-slate-100 hover:bg-slate-200 text-slate-600 px-2 py-1 rounded">View Invoice</button>
            </td>
        `;
        elements.bookingIndexTableBody.appendChild(tr);
    });
}

function openInvoiceDetails(bookingId) {
    const booking = bookingIndexState.bookings.find(b => b.id === bookingId);
    if (!booking) return;
    
    bookingIndexState.selectedBookingId = bookingId;
    elements.invoiceDetailsModal.classList.remove('hidden');
    
    // Calculate totals
    const originalTotal = booking.passengers.reduce((sum, p) => sum + (p.total || 0), 0);
    const totalPaid = booking.payments.reduce((sum, p) => sum + (p.amount || 0), 0);
    
    // Calculate discount if exists
    let discountAmount = 0;
    let discountedTotal = originalTotal;
    if (booking.discountType && booking.discountValue > 0) {
        if (booking.discountType === 'percentage') {
            discountAmount = originalTotal * booking.discountValue / 100;
        } else {
            discountAmount = booking.discountValue;
        }
        discountedTotal = originalTotal - discountAmount;
    }
    
    const due = discountedTotal - totalPaid;
    
    // Status
    let status = 'Unpaid';
    let statusClass = 'bg-red-100 text-red-700';
    if (totalPaid >= discountedTotal) {
        status = 'Paid';
        statusClass = 'bg-green-100 text-green-700';
    } else if (totalPaid > 0) {
        status = 'Partial';
        statusClass = 'bg-yellow-100 text-yellow-700';
    }
    
    // Fill header
    document.getElementById('invoiceDetailsId').textContent = booking.invoiceNo;
    elements.invoiceNo.textContent = booking.invoiceNo;
    elements.invoiceDate.textContent = booking.bookingDate;
    elements.invoiceCustomer.textContent = booking.customerName;
    document.getElementById('invoiceCustomer').innerHTML = `<div>${booking.customerName}</div><div class="text-sm text-slate-500 font-normal">District: ${booking.district || '-'}</div><div class="text-sm text-slate-500 font-normal">Fingerprint: ${booking.fingerprintLocation || '-'}</div>`;
    elements.invoiceStatus.textContent = status;
    elements.invoiceStatus.className = `inline-flex items-center px-2 py-0.5 rounded text-xs font-medium ${statusClass}`;
    
    // Show original and discounted total
    let totalDisplay = discountedTotal + ' SAR';
    if (discountAmount > 0) {
        totalDisplay = `<span class="line-through text-slate-400">${originalTotal} SAR</span> <span class="text-green-600">${discountedTotal} SAR</span>`;
    }
    elements.invoiceTotal.innerHTML = totalDisplay;
    elements.invoicePaid.textContent = totalPaid + ' SAR';
    elements.invoiceDue.textContent = due + ' SAR';
    
    // Render passengers
    elements.invoicePassengers.innerHTML = '';
    booking.passengers.forEach((passenger, index) => {
        const card = document.createElement('div');
        card.className = 'bg-slate-50 border border-slate-200 rounded-lg p-3';
        card.innerHTML = `
            <div class="flex justify-between items-start">
                <div>
                    <div class="font-medium text-slate-800">${passenger.name}</div>
                    <div class="text-sm text-slate-500">
                        ${passenger.route} | ${passenger.airline} | ${passenger.class} | ${passenger.flightDateRange || '-'}
                    </div>
                    <div class="text-sm mt-1">
                        <span class="text-slate-500">Ticket:</span> ${passenger.ticketFare || 0} SAR | 
                        <span class="text-slate-500">Visa:</span> ${passenger.visaCost || 0} SAR | 
                        <span class="text-slate-500">Fingerprint:</span> ${passenger.fingerprintCost || 0} SAR
                    </div>
                </div>
                <div class="text-right">
                    <div class="font-bold text-slate-800">${passenger.total || 0} SAR</div>
                    <div class="flex gap-2 mt-2">
                        <button onclick="editPassengerInBooking(${index})" class="text-xs bg-slate-200 hover:bg-slate-300 text-slate-600 px-2 py-1 rounded">Edit</button>
                        <button onclick="openDeletePassengerModal(${index})" class="text-xs bg-red-100 hover:bg-red-200 text-red-600 px-2 py-1 rounded">Delete</button>
                    </div>
                </div>
            </div>
        `;
        elements.invoicePassengers.appendChild(card);
    });
    
    // Render payments
    if (booking.payments.length > 0) {
        elements.invoicePaymentsEmpty.classList.add('hidden');
        elements.invoicePaymentsBody.innerHTML = '';
        booking.payments.forEach(payment => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td class="px-3 py-2 text-slate-600">${payment.date}</td>
                <td class="px-3 py-2 text-slate-600">${payment.voucherNo}</td>
                <td class="px-3 py-2 text-slate-600">${payment.paymentMethod}</td>
                <td class="px-3 py-2 text-slate-600">${payment.trxId}</td>
                <td class="px-3 py-2 text-right font-medium text-green-600">${payment.amount} SAR</td>
            `;
            elements.invoicePaymentsBody.appendChild(tr);
        });
    } else {
        elements.invoicePaymentsEmpty.classList.remove('hidden');
        elements.invoicePaymentsBody.innerHTML = '';
    }
}

function closeInvoiceDetails() {
    bookingIndexState.selectedBookingId = null;
    elements.invoiceDetailsModal.classList.add('hidden');
}

// ============================================
// Discount Modal Functions
// ============================================
function openDiscountModal() {
    const booking = bookingIndexState.bookings.find(b => b.id === bookingIndexState.selectedBookingId);
    if (!booking) return;
    
    const totalValue = booking.passengers.reduce((sum, p) => sum + (p.total || 0), 0);
    
    elements.discountOriginalTotal.value = totalValue;
    elements.discountType.value = booking.discountType || 'amount';
    elements.discountValue.value = booking.discountValue || 0;
    
    calculateDiscount();
    elements.discountModal.classList.remove('hidden');
}

function calculateDiscount() {
    const originalTotal = parseFloat(elements.discountOriginalTotal.value) || 0;
    const discountType = elements.discountType.value;
    const discountValue = parseFloat(elements.discountValue.value) || 0;
    
    let discountAmount = 0;
    if (discountType === 'percentage') {
        discountAmount = originalTotal * discountValue / 100;
    } else {
        discountAmount = discountValue;
    }
    
    const newTotal = Math.max(0, originalTotal - discountAmount);
    
    elements.discountAmount.value = Math.round(discountAmount);
    elements.discountNewTotal.value = Math.round(newTotal);
}

function applyDiscount() {
    const booking = bookingIndexState.bookings.find(b => b.id === bookingIndexState.selectedBookingId);
    if (!booking) return;
    
    booking.discountType = elements.discountType.value;
    booking.discountValue = parseFloat(elements.discountValue.value) || 0;
    
    closeDiscountModal();
    openInvoiceDetails(booking.id);
    showToast('Discount applied');
}

function closeDiscountModal() {
    elements.discountModal.classList.add('hidden');
    elements.discountValue.value = 0;
}

// ============================================
// Settings Functions
// ============================================
function renderDivisionOptions() {
    if (!elements.divisionSelect || !divisionsData) return;
    
    const divisions = Object.keys(divisionsData);
    elements.divisionSelect.innerHTML = '<option value="">Select Division</option>';
    divisions.forEach(division => {
        const option = document.createElement('option');
        option.value = division;
        option.textContent = division;
        elements.divisionSelect.appendChild(option);
    });
}

function renderDistrictOptions() {
    const selectedDivision = elements.divisionSelect.value;
    const districts = divisionsData[selectedDivision] || [];
    
    elements.districtSelect.innerHTML = '<option value="">Select District</option>';
    districts.forEach(district => {
        const option = document.createElement('option');
        option.value = district;
        option.textContent = district;
        elements.districtSelect.appendChild(option);
    });
}

function addFingerprintCharge() {
    const division = elements.divisionSelect.value;
    const district = elements.districtSelect.value;
    const charge = parseFloat(elements.fingerprintChargeInput.value) || 0;
    
    if (!division || !district) {
        showToast('Please select division and district', 'error');
        return;
    }
    
    adminSettings.fingerprintCharges[district] = {
        division: division,
        charge: charge
    };
    
    saveAdminSettingsToStorage();
    renderFingerprintChargesTable();
    elements.fingerprintChargeInput.value = 0;
    showToast('Fingerprint charge added/updated');
}

function deleteFingerprintCharge(district) {
    delete adminSettings.fingerprintCharges[district];
    saveAdminSettingsToStorage();
    renderFingerprintChargesTable();
    showToast('Fingerprint charge deleted');
}

function renderFingerprintChargesTable() {
    const charges = adminSettings.fingerprintCharges;
    const chargeKeys = Object.keys(charges);
    
    if (chargeKeys.length > 0) {
        elements.fingerprintChargesEmpty.classList.add('hidden');
        elements.fingerprintChargesTableBody.innerHTML = '';
        
        chargeKeys.forEach(district => {
            const data = charges[district];
            const tr = document.createElement('tr');
            tr.className = 'hover:bg-slate-50';
            tr.innerHTML = `
                <td class="px-3 py-2 text-slate-600">${data.division}</td>
                <td class="px-3 py-2 text-slate-800 font-medium">${district}</td>
                <td class="px-3 py-2 text-right text-slate-800 font-medium">${data.charge} SAR</td>
                <td class="px-3 py-2 text-center">
                    <button onclick="deleteFingerprintCharge('${district}')" class="text-xs bg-red-100 hover:bg-red-200 text-red-600 px-2 py-1 rounded">Delete</button>
                </td>
            `;
            elements.fingerprintChargesTableBody.appendChild(tr);
        });
    } else {
        elements.fingerprintChargesEmpty.classList.remove('hidden');
        elements.fingerprintChargesTableBody.innerHTML = '';
    }
}

function saveFlightDateGap() {
    const gap = parseInt(elements.flightDateGapInput.value) || 30;
    adminSettings.defaultFlightDateGap = gap;
    saveAdminSettingsToStorage();
    showToast('Flight date gap saved');
}

function showSettingsTab() {
    elements.settingsSection.classList.remove('hidden');
    document.getElementById('bookingContent').classList.add('hidden');
    elements.fareAdminSection.classList.add('hidden');
    elements.visaAdminSection.classList.add('hidden');
    
    document.getElementById('tabSettings').classList.add('bg-slate-700', 'text-white');
    document.getElementById('tabSettings').classList.remove('text-slate-600');
    document.getElementById('tabBooking').classList.remove('bg-slate-700', 'text-white');
    document.getElementById('tabBooking').classList.add('text-slate-600');
    document.getElementById('tabFareAdmin').classList.remove('bg-slate-700', 'text-white');
    document.getElementById('tabFareAdmin').classList.add('text-slate-600');
    document.getElementById('tabVisaAdmin').classList.remove('bg-slate-700', 'text-white');
    document.getElementById('tabVisaAdmin').classList.add('text-slate-600');
    
    elements.flightDateGapInput.value = adminSettings.defaultFlightDateGap;
    renderDivisionOptions();
    renderFingerprintChargesTable();
}

function initSettings() {
    loadAdminSettings();
    if (elements.flightDateGapInput) {
        elements.flightDateGapInput.value = adminSettings.defaultFlightDateGap;
    }
    if (elements.divisionSelect) {
        renderDivisionOptions();
    }
    if (elements.fingerprintChargesTableBody) {
        renderFingerprintChargesTable();
    }
}

// ============================================
// Fingerprint Staff Functions
// ============================================
const fingerprintStaffIndex = [
    { invoiceNo: "INV-1001", customerName: "Ahmed Al-Rashid", division: "Dhaka Division", district: "Dhaka", status: "Pending", cost: 150 },
    { invoiceNo: "INV-1002", customerName: "Sara Khan", division: "Chattogram Division", district: "Chattogram", status: "Processing", cost: 200 },
    { invoiceNo: "INV-1003", customerName: "Mohammad Ali", division: "Rajshahi Division", district: "Rajshahi", status: "Done", cost: 175 },
    { invoiceNo: "INV-1004", customerName: "Fatema Begum", division: "Khulna Division", district: "Khulna", status: "Pending", cost: 180 },
    { invoiceNo: "INV-1005", customerName: "Hussain Ahmed", division: "Sylhet Division", district: "Sylhet", status: "Processing", cost: 160 },
    { invoiceNo: "INV-1006", customerName: "Aisha Rahman", division: "Rangpur Division", district: "Dinajpur", status: "Pending", cost: 190 },
    { invoiceNo: "INV-1007", customerName: "Omar Hassan", division: "Barishal Division", district: "Barishal", status: "Done", cost: 170 },
    { invoiceNo: "INV-1008", customerName: "Nadia Islam", division: "Mymensingh Division", district: "Mymensingh", status: "Processing", cost: 185 },
];

const fingerprintStatusOptions = ["Pending", "Processing", "Done"];

function renderFingerprintStaffIndex() {
    const data = fingerprintStaffIndex;
    
    if (data.length > 0) {
        elements.fingerprintStaffEmpty.classList.add('hidden');
        elements.fingerprintStaffTableBody.innerHTML = '';
        
        data.forEach((row, index) => {
            const tr = document.createElement('tr');
            tr.className = 'hover:bg-slate-50';
            tr.innerHTML = `
                <td class="px-3 py-2 text-slate-800 font-medium">${row.invoiceNo}</td>
                <td class="px-3 py-2 text-slate-600">${row.customerName}</td>
                <td class="px-3 py-2 text-slate-600">${row.division}</td>
                <td class="px-3 py-2 text-slate-600">${row.district}</td>
                <td class="px-3 py-2">
                    <select onchange="updateFingerprintStatus(${index}, this.value)" class="text-xs border border-slate-300 rounded px-2 py-1 bg-white">
                        ${fingerprintStatusOptions.map(opt => `<option value="${opt}" ${row.status === opt ? 'selected' : ''}>${opt}</option>`).join('')}
                    </select>
                </td>
                <td class="px-3 py-2 text-right">
                    <input type="number" value="${row.cost}" onchange="updateFingerprintCost(${index}, this.value)" class="w-20 text-right text-sm border border-slate-300 rounded px-2 py-1">
                </td>
            `;
            elements.fingerprintStaffTableBody.appendChild(tr);
        });
    } else {
        elements.fingerprintStaffEmpty.classList.remove('hidden');
        elements.fingerprintStaffTableBody.innerHTML = '';
    }
}

function updateFingerprintStatus(index, status) {
    fingerprintStaffIndex[index].status = status;
    showToast('Status updated');
}

function updateFingerprintCost(index, cost) {
    fingerprintStaffIndex[index].cost = parseFloat(cost) || 0;
    showToast('Cost updated');
}

function editPassengerInBooking(index) {
    const booking = bookingIndexState.bookings.find(b => b.id === bookingIndexState.selectedBookingId);
    if (!booking) return;
    
    const passenger = booking.passengers[index];
    
    // Close invoice details temporarily
    closeInvoiceDetails();
    
    // Set editing context - we're editing from a booking
    bookingIndexState.editingBookingId = bookingIndexState.selectedBookingId;
    
    // Copy passenger data to state.passengers for editing
    state.passengers = [passenger];
    state.selectedCustomer = { name: booking.customerName };
    
    // Open the passenger modal in edit mode
    openPassengerModal(0);
}

function openDeletePassengerModal(passengerIndex) {
    const booking = bookingIndexState.bookings.find(b => b.id === bookingIndexState.selectedBookingId);
    if (!booking) return;
    
    bookingIndexState.deletingPassengerIndex = passengerIndex;
    const passenger = booking.passengers[passengerIndex];
    
    elements.deletePassengerInfo.innerHTML = `
        <strong>${passenger.name}</strong><br>
        <span class="text-slate-500">${passenger.route} | ${passenger.airline}</span><br>
        <span class="text-slate-800 font-medium">Total: ${passenger.total} SAR</span>
    `;
    
    elements.deletePassengerModal.classList.remove('hidden');
}

function closeDeletePassengerModal() {
    bookingIndexState.deletingPassengerIndex = null;
    elements.deletePassengerModal.classList.add('hidden');
}

function confirmDeletePassenger() {
    const booking = bookingIndexState.bookings.find(b => b.id === bookingIndexState.selectedBookingId);
    if (!booking || bookingIndexState.deletingPassengerIndex === null) return;
    
    // Remove passenger
    booking.passengers.splice(bookingIndexState.deletingPassengerIndex, 1);
    
    // Close modal and refresh
    closeDeletePassengerModal();
    openInvoiceDetails(bookingIndexState.selectedBookingId);
    renderBookingIndex();
    showToast('Passenger removed from booking');
}

// ============================================
// Event Listeners
// ============================================
elements.customerSearch.addEventListener('input', handleCustomerSearch);

// Checkbox conditional logic listener
elements.passengerWithOffer.addEventListener('change', updateCheckboxState);

// Package dropdown - open custom duration modal
elements.passengerPackage.addEventListener('change', function() {
    if (this.value === 'customize') {
        openCustomDurationModal();
    }
});

// Ticket Fare modal listeners
elements.ticketFareWithOffer.addEventListener('change', updateTicketFareCheckboxState);
elements.ticketFareRoute.addEventListener('change', calculateTicketFare);
elements.ticketFareAirline.addEventListener('change', calculateTicketFare);
elements.ticketFareClass.addEventListener('change', calculateTicketFare);
elements.ticketFareDiscountType.addEventListener('change', calculateTicketFare);
elements.ticketFareDiscountValue.addEventListener('input', calculateTicketFare);
elements.ticketFareWithOffer.addEventListener('change', calculateTicketFare);

// Visa cost listeners
elements.visaCostAgent.addEventListener('change', updateCommissionAgentOptions);
elements.visaCostAgentCommission.addEventListener('input', calculateVisaCost);
elements.visaCostNetVisaCost.addEventListener('input', calculateVisaCost);

// Discount modal listeners
elements.discountType.addEventListener('change', calculateDiscount);
elements.discountValue.addEventListener('input', calculateDiscount);

// Fare admin listeners
elements.fareWithOffer.addEventListener('change', updateFareOfferFields);

// Close suggestions when clicking outside
document.addEventListener('click', (e) => {
    if (!elements.customerSearch.contains(e.target) && !elements.customerSuggestions.contains(e.target)) {
        hideSuggestions();
    }
});

// Close modals on escape key
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        if (state.isCustomerModalOpen) closeCustomerModal();
        if (state.isPassengerModalOpen) closePassengerModal();
        if (passengerIndexState.isTicketFareModalOpen) closeTicketFareModal();
        if (passengerIndexState.isVisaCostModalOpen) closeVisaCostModal();
        if (elements.fareModal.classList.contains('hidden') === false) closeFareModal();
        if (elements.viewFareModal.classList.contains('hidden') === false) closeViewFareModal();
        if (elements.deleteFareModal.classList.contains('hidden') === false) closeDeleteFareModal();
        if (elements.visaPriceModal.classList.contains('hidden') === false) closeVisaPriceModal();
        if (elements.invoiceDetailsModal.classList.contains('hidden') === false) closeInvoiceDetails();
        if (elements.deletePassengerModal.classList.contains('hidden') === false) closeDeletePassengerModal();
    }
});

// ============================================
// Initialize
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    renderPassengerIndex();
    renderFareIndex();
    sortVisaPriceRecords();
    renderVisaPriceIndex();
    renderBookingIndex();
    initSettings();
    switchTab('bookingIndex');
    // Set initial active state for Booking nav item
    document.querySelector('.nav-item[data-tab="bookingIndex"]')?.classList.add('active');
    console.log('BM Umrah Booking Form initialized');
    console.log('Sample customer IDs to test:', state.customers.map(c => c.passport).join(', '));
});
