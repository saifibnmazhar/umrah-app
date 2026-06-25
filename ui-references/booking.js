// ============================================
// Booking Page JavaScript
// ============================================

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
    passengerDocFiles: [],
    bookingDocFiles: [],
    customerDocFiles: [],
    editingBookingIndex: null,
    currentIndexTab: 'booking', // 'booking' or 'passenger'
    discountType: 'fixed', // 'fixed' or 'percentage'
    discountValue: 0,
};

const passengerIndexState = {
    passengerIndexRows: [],
    invoiceCounter: 1000,
    isTicketFareModalOpen: false,
    isVisaCostModalOpen: false,
    editingTicketFareRowIndex: null,
    editingVisaCostRowIndex: null,
    currentPassengerIndex: null,
};

const groupTickets = [
    { id: 'GRP001', pnr: 'BMUGRP001', date: '2026-05-15', route: 'DAC-JED-DAC', remainingSeats: 8 },
    { id: 'GRP002', pnr: 'BMUGRP002', date: '2026-05-20', route: 'DAC-RUH-DAC', remainingSeats: 12 },
    { id: 'GRP003', pnr: 'BMUGRP003', date: '2026-06-01', route: 'DAC-MED-DAC', remainingSeats: 5 },
];

function savePassengerIndexToStorage() {
    try {
        localStorage.setItem('bm_passengerIndexRows', JSON.stringify(passengerIndexState.passengerIndexRows));
    } catch (e) {
        console.error('Failed to save passenger index to localStorage:', e);
    }
}

function loadPassengerIndexFromStorage() {
    // Always load sample data for testing (3 buttons visible)
    passengerIndexState.passengerIndexRows = [
                {
                    date: '2026-04-01',
                    invoiceNo: 'INV-1001',
                    passengerName: 'Ahmed Mohammed',
                    passport: 'P123456',
                    passportExpiry: '2028-05-15',
                    guardianName: 'Ali Ahmed',
                    mobileNo: '0501234567',
                    route: 'DAC-JED-DAC',
                    status: 'None',
                    package: '5 Days',
                    due: '0 SAR',
                    ticketFare: { date: '2026-04-10', sellingFare: 2200, netFare: 2000 },
                    requiredFlightDate: '2026-04-15',
                    actualFlightDate: '2026-04-15',
                    fingerprintLocation: 'None',
                    fingerprintCost: 0,
                    documents: [],
                    passengerData: null
                },
                {
                    date: '2026-04-01',
                    invoiceNo: 'INV-1001',
                    passengerName: 'Fatima Ali',
                    passport: 'P654321',
                    passportExpiry: '2027-12-20',
                    guardianName: 'Ali Ahmed',
                    mobileNo: '0501234567',
                    route: 'DAC-JED-DAC',
                    status: 'Visa Application',
                    package: '5 Days',
                    due: '500 SAR',
                    ticketFare: { date: '2026-04-12', sellingFare: 2200, netFare: 2000 },
                    requiredFlightDate: '2026-04-15',
                    actualFlightDate: '2026-04-16',
                    visa: { 
                        agent: 'Visa Agent A',
                        commissionAgent: 'Commission Agent 1',
                        sellingPrice: 450,
                        agentCommission: 50,
                        netVisaCost: 100,
                        finalCost: 150,
                        issued: false
                    },
                    fingerprintLocation: 'BMT-DHK',
                    fingerprintCost: 200,
                    documents: [],
                    passengerData: null
                },
                {
                    date: '2026-04-02',
                    invoiceNo: 'INV-1002',
                    passengerName: 'Omar Hassan',
                    passport: 'P789012',
                    passportExpiry: '2029-01-10',
                    guardianName: 'Hassan Omar',
                    mobileNo: '0559876543',
                    route: 'DAC-RUH-DAC',
                    status: 'Visa Issued',
                    package: '7 Days',
                    due: '1500 SAR',
                    ticketFare: { date: '2026-04-15', sellingFare: 2800, netFare: 2500 },
                    requiredFlightDate: '2026-04-20',
                    actualFlightDate: '2026-04-21',
                    visa: { 
                        agent: 'Visa Agent B',
                        commissionAgent: 'Commission Agent 3',
                        sellingPrice: 500,
                        agentCommission: 75,
                        netVisaCost: 125,
                        additionalCost: 50,
                        remarks: 'Urgent processing',
                        finalCost: 250,
                        issued: true
                    },
                    fingerprintLocation: 'BMT-DHK',
                    fingerprintCost: 200,
                    documents: [],
                    passengerData: null
                },
                {
                    date: '2026-04-03',
                    invoiceNo: 'INV-1003',
                    passengerName: 'Aisha Rahman',
                    passport: 'P345678',
                    passportExpiry: '2028-08-25',
                    guardianName: 'Rahman Khan',
                    mobileNo: '0501112222',
                    route: 'DAC-MED-DAC',
                    status: 'Fingerprint Done',
                    package: '10 Days',
                    due: '0 SAR',
                    ticketFare: { date: '2026-04-20', sellingFare: 3200, netFare: 2900 },
                    requiredFlightDate: '2026-04-25',
                    actualFlightDate: '2026-04-25',
                    visa: { sellingPrice: 500, finalCost: 450 },
                    fingerprintLocation: 'Dhaka North',
                    fingerprintCost: 200,
                    documents: [],
                    passengerData: null
                },
{
                    date: '2026-04-03',
                    invoiceNo: 'INV-1003',
                    passengerName: 'Karim Ibrahim',
                    passport: 'P901234',
                    passportExpiry: '2027-11-30',
                    guardianName: 'Rahman Khan',
                    mobileNo: '0501112222',
                    route: 'DAC-MED-DAC',
                    status: 'Delivered',
                    package: '10 Days',
                    due: '0 SAR',
                    ticketFare: { date: '2026-04-20', sellingFare: 3200, netFare: 2900 },
                    requiredFlightDate: '2026-04-25',
                    actualFlightDate: '2026-04-26',
                    visa: { sellingPrice: 500, finalCost: 450 },
                    fingerprintLocation: 'Dhaka North',
                    fingerprintCost: 200,
                    documents: [],
                    passengerData: null
                }
            ];
        }

const bookingIndexState = {
    bookings: [
        {
            id: 1,
            invoiceNo: 'INV-1001',
            bookingDate: '2026-03-15',
            customerName: 'Ahmed Al-Rashid',
            customerMobile: '0501234567',
            fingerprintOffice: 'BMT-DHA',
            district: 'Dhaka',
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
            customerMobile: '0559876543',
            fingerprintOffice: 'BMT-CTG',
            district: 'Chattogram',
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
            customerMobile: '0501111111',
            fingerprintOffice: 'BMT-DHA',
            district: 'Dhaka',
            passengers: [
                { name: 'Mohammed Khan', passport: 'P222222', route: 'DAC-MED-DAC', airline: 'Emirates', class: 'Economy', passengerType: 'Child', ticketFare: 2000, visaCost: 500, fingerprintCost: 200, total: 2700 }
            ],
            payments: [],
            customerDocuments: []
        },
        {
            id: 4,
            invoiceNo: 'INV-1004',
            bookingDate: '2026-03-20',
            customerName: 'Rahim Ahmed',
            customerMobile: '0502222222',
            fingerprintOffice: 'BMT-SYL',
            district: 'Sylhet',
            passengers: [
                { name: 'Rahim Ahmed', passport: 'P333333', route: 'DAC-JED-DAC', airline: 'Saudia', class: 'Economy', passengerType: 'Adult', ticketFare: 2800, visaCost: 500, fingerprintCost: 200, total: 3500 }
            ],
            payments: [
                { id: 1, date: '2026-03-21', voucherNo: 'V004', paymentMethod: 'Cash', trxId: '-', amount: 3500 }
            ],
            customerDocuments: []
        },
        {
            id: 5,
            invoiceNo: 'INV-1005',
            bookingDate: '2026-03-22',
            customerName: 'Nadia Islam',
            customerMobile: '0553333333',
            fingerprintOffice: 'BMT-RNP',
            district: 'Rangpur',
            passengers: [
                { name: 'Nadia Islam', passport: 'P444444', route: 'DAC-RUH-DAC', airline: 'Flynas', class: 'Economy', passengerType: 'Adult', ticketFare: 2200, visaCost: 500, fingerprintCost: 200, total: 2900 }
            ],
            payments: [
                { id: 1, date: '2026-03-23', voucherNo: 'V005', paymentMethod: 'Bank Transfer', trxId: 'TXN999999', amount: 2000 }
            ],
            customerDocuments: []
        }
    ],
    selectedBookingId: null,
    isInvoiceDetailsOpen: false,
    isDeleteConfirmOpen: false,
    deletingPassengerIndex: null,
    editingBookingId: null,
};

const fareAdminState = {
    fareRecords: [
        { id: 1, date: '2026-03-15', airline: 'Saudia', travelClass: 'Economy', route: 'DAC-JED-DAC', fare: 2500, withOffer: true, offerPrice: 2200, effectiveFrom: '2026-03-01', effectiveTo: '2026-04-30' },
        { id: 2, date: '2026-03-10', airline: 'Biman Bangladesh', travelClass: 'Economy', route: 'DAC-JED-DAC', fare: 2100, withOffer: false, offerPrice: null, effectiveFrom: null, effectiveTo: null },
        { id: 3, date: '2026-03-12', airline: 'Emirates', travelClass: 'Business', route: 'DAC-JED-DAC', fare: 9500, withOffer: true, offerPrice: 8500, effectiveFrom: '2026-03-01', effectiveTo: '2026-05-31' },
    ],
};

const visaAdminState = {
    visaPriceRecords: [
        { id: 1, date: '2026-03-15', price: 500 },
        { id: 2, date: '2026-01-01', price: 450 },
    ],
};

const fingerprintLocations = ['None', 'BMT-DHK', 'BMT-CTG', 'Tabuk with DHK', 'Tabuk with CTG', 'Tabuk with DHK-BMT', 'Dhaka North', 'Dhaka South', 'Chittagong', 'Sylhet'];
const statusOptions = ['None', 'Underprocessing', 'Fingerprint Done', 'Ticket Booking', 'Visa Application', 'Visa Issued', 'Ticket Issued', 'Delivered', 'Hold', 'Cancel', 'Refund Done', 'Departure Done'];

// ============================================
// Document Storage Functions (localStorage)
// ============================================
const DOC_STORAGE_KEY = 'bm_umrah_documents';

function saveDocumentsToStorage() {
    const docsData = passengerIndexState.passengerIndexRows.map((row, index) => ({
        index: index,
        invoiceNo: row.invoiceNo,
        passport: row.passport,
        documents: row.documents || []
    }));
    localStorage.setItem(DOC_STORAGE_KEY, JSON.stringify(docsData));
    savePassengerIndexToStorage();
}

function loadDocumentsFromStorage() {
    const stored = localStorage.getItem(DOC_STORAGE_KEY);
    if (stored) {
        const docsData = JSON.parse(stored);
        docsData.forEach(docRecord => {
            const row = passengerIndexState.passengerIndexRows.find(r => r.invoiceNo === docRecord.invoiceNo && r.passport === docRecord.passport);
            if (row) {
                row.documents = docRecord.documents || [];
            }
        });
    }
}

function generateDocId() {
    return 'doc_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
}

function addDocumentToPassenger(passengerIndex, docData) {
    if (passengerIndexState.passengerIndexRows[passengerIndex]) {
        passengerIndexState.passengerIndexRows[passengerIndex].documents.push({
            id: generateDocId(),
            name: docData.name,
            type: docData.type,
            uploadDate: new Date().toISOString().split('T')[0],
            size: docData.size,
            data: docData.data
        });
        saveDocumentsToStorage();
    }
}

// Sample documents for demo (base64 encoded small PDF placeholder)
const sampleDocuments = [
    { name: 'Passport_Copy.pdf', type: 'application/pdf', size: 245000 },
    { name: 'Visa_Application.pdf', type: 'application/pdf', size: 180000 },
    { name: 'Photo_ID.jpg', type: 'image/jpeg', size: 125000 }
];

// Sample customer documents for demo
const sampleCustomerDocuments = [
    { name: 'Customer_ID.pdf', type: 'application/pdf', size: 180000 },
    { name: 'Customer_Photo.jpg', type: 'image/jpeg', size: 95000 }
];

function addSampleDocuments() {
    if (passengerIndexState.passengerIndexRows.length > 0 && passengerIndexState.passengerIndexRows[0].documents.length === 0) {
        passengerIndexState.passengerIndexRows[0].documents = sampleDocuments.map((doc, i) => ({
            id: 'sample_' + (i + 1),
            name: doc.name,
            type: doc.type,
            uploadDate: '2026-03-15',
            size: doc.size,
            data: null
        }));
    }
    if (passengerIndexState.passengerIndexRows.length > 1 && passengerIndexState.passengerIndexRows[1].documents.length === 0) {
        passengerIndexState.passengerIndexRows[1].documents = [
            { id: 'sample_4', name: 'Medical_Report.pdf', type: 'application/pdf', size: 320000, uploadDate: '2026-03-10', data: null }
        ];
    }
    if (bookingIndexState.bookings.length > 0 && bookingIndexState.bookings[0].customerDocuments.length === 0) {
        bookingIndexState.bookings[0].customerDocuments = sampleCustomerDocuments.map((doc, i) => ({
            id: 'cust_sample_' + (i + 1),
            name: doc.name,
            type: doc.type,
            uploadDate: '2026-03-15',
            size: doc.size,
            data: null
        }));
    }
    if (bookingIndexState.bookings.length > 1 && bookingIndexState.bookings[1].customerDocuments.length === 0) {
        bookingIndexState.bookings[1].customerDocuments = [
            { id: 'cust_sample_3', name: 'Customer_Agreement.pdf', type: 'application/pdf', size: 220000, uploadDate: '2026-03-10', data: null }
        ];
    }
}

// ============================================
// Initialize Passenger Index from Booking Index data
// ============================================
function initializePassengerIndexFromBookings() {
    bookingIndexState.bookings.forEach(booking => {
        booking.passengers.forEach(passenger => {
            const route = passenger.route || '';
            const airline = passenger.airline || '';
            const travelClass = passenger.class || '';
            
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
                documents: [],
            };
            passengerIndexState.passengerIndexRows.push(indexRow);
            savePassengerIndexToStorage();
        });
    });
}

// ============================================
// DOM Elements
// ============================================
const getElement = (id) => document.getElementById(id);

const elements = {
    addBookingButtonSection: getElement('addBookingButtonSection'),
    addBookingSection: getElement('addBookingSection'),
    indexSection: getElement('indexSection'),
    defaultView: getElement('defaultView'),
    bookingForm: getElement('bookingForm'),
    passengerIndexSection: getElement('passengerIndexSection'),
    passengerIndexTableBody: getElement('passengerIndexTableBody'),
    passengerIndexEmpty: getElement('passengerIndexEmpty'),
    customerSearch: getElement('customerSearch'),
    customerSuggestions: getElement('customerSuggestions'),
    selectedCustomer: getElement('selectedCustomer'),
    selectedCustomerName: getElement('selectedCustomerName'),
    selectedCustomerPassport: getElement('selectedCustomerPassport'),
    selectedCustomerIqama: getElement('selectedCustomerIqama'),
    selectedCustomerMobile: getElement('selectedCustomerMobile'),
    customerModal: getElement('customerModal'),
    customerForm: getElement('customerForm'),
    customerName: getElement('customerName'),
    customerIqama: getElement('customerIqama'),
    customerPassport: getElement('customerPassport'),
    customerMobile: getElement('customerMobile'),
    passengerModal: getElement('passengerModal'),
    passengerModalTitle: getElement('passengerModalTitle'),
    passengerForm: getElement('passengerForm'),
    passengerFirstName: getElement('passengerFirstName'),
    passengerLastName: getElement('passengerLastName'),
    passengerPassport: getElement('passengerPassport'),
    passengerPassportExpiry: getElement('passengerPassportExpiry'),
    passengerDateOfBirth: getElement('passengerDateOfBirth'),
    passengerTypeDisplay: getElement('passengerTypeDisplay'),
    passengerMobile: getElement('passengerMobile'),
    passengerPackage: getElement('passengerPackage'),
    passengerService: getElement('passengerService'),
    passengerRoute: getElement('passengerRoute'),
    passengerAirline: getElement('passengerAirline'),
    passengerClass: getElement('passengerClass'),
    passengerFlightDateRange: getElement('passengerFlightDateRange'),
    passengerRouteType: getElement('passengerRouteType'),
    passengerFlightType: getElement('passengerFlightType'),
    passengerType: getElement('passengerType'),
    bookingDistrict: document.getElementById('bookingDistrict'),
    bookingFingerprintLocation: document.getElementById('bookingFingerprintLocation'),
    bookingFingerprintOffice: document.getElementById('bookingFingerprintOffice'),
    bookingPackage: document.getElementById('bookingPackage'),
    passengerAddress: document.getElementById('passengerAddress'),
    passengerWithOffer: document.getElementById('passengerWithOffer'),
    passengerRefundable: document.getElementById('passengerRefundable'),
    passengerListContainer: document.getElementById('passengerListContainer'),
    passengerList: document.getElementById('passengerList'),
    addMoreButtonContainer: document.getElementById('addMoreButtonContainer'),
    toastContainer: document.getElementById('toastContainer'),
    bookingIndexSection: document.getElementById('bookingIndexSection'),
    bookingIndexTableBody: document.getElementById('bookingIndexTableBody'),
    bookingIndexEmpty: document.getElementById('bookingIndexEmpty'),
    bookingIndexSearch: document.getElementById('bookingIndexSearch'),
};

// ============================================
// Customer Search Functions
// ============================================
function handleCustomerSearch(e) {
    const term = e.target.value.trim().toLowerCase();
    state.customerSearchTerm = term;

    if (term === '') {
        state.filteredCustomers = [];
        hideSuggestions();
        return;
    }

    state.filteredCustomers = state.customers.filter(customer => {
        const passportMatch = customer.passport.toLowerCase().includes(term);
        const iqamaMatch = customer.iqama.toLowerCase().includes(term);
        return passportMatch || iqamaMatch;
    });

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
        <button type="button" onclick="openCustomerModal()" class="mt-2 text-slate-700 font-medium hover:underline flex items-center gap-1">
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

    elements.customerSearch.value = customer.passport || customer.iqama;
    elements.customerSearch.disabled = true;
    hideSuggestions();

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
    document.getElementById('customerIqamaType').value = '';
    document.getElementById('customerRefIqama').value = '';
    document.getElementById('customerRefMobile').value = '';
    document.getElementById('customerRefIqamaFile').value = '';
    document.getElementById('customerRefIqamaFileName').textContent = 'click to upload';
    document.getElementById('referralFields')?.classList.add('hidden');
    setTimeout(() => elements.customerName.focus(), 100);
}

function closeCustomerModal() {
    state.isCustomerModalOpen = false;
    elements.customerModal.classList.add('hidden');
    document.getElementById('referralFields')?.classList.add('hidden');
    document.getElementById('customerIqamaType').value = '';
    document.getElementById('customerRefIqama').value = '';
    document.getElementById('customerRefMobile').value = '';
    document.getElementById('customerRefIqamaFile').value = '';
    document.getElementById('customerRefIqamaFileName').textContent = 'click to upload';
}

function toggleReferralFields() {
    const iqamaType = document.getElementById('customerIqamaType');
    const referralFields = document.getElementById('referralFields');
    const refIqama = document.getElementById('customerRefIqama');
    const refMobile = document.getElementById('customerRefMobile');
    
    if (!iqamaType || !referralFields) {
        console.error('Elements not found');
        return;
    }
    
    if (iqamaType.value === 'Referral') {
        referralFields.classList.remove('hidden');
        referralFields.style.display = 'block';
        if (refIqama) refIqama.required = true;
        if (refMobile) refMobile.required = true;
    } else {
        referralFields.classList.add('hidden');
        referralFields.style.display = 'none';
        if (refIqama) {
            refIqama.required = false;
            refIqama.value = '';
        }
        if (refMobile) {
            refMobile.required = false;
            refMobile.value = '';
        }
    }
}

function toggleCustomerIqamaField() {
    const iqamaType = document.getElementById('customerIqamaType');
    const iqamaField = document.getElementById('customerIqamaField');
    
    if (!iqamaType || !iqamaField) return;
    
    if (iqamaType.value === 'None') {
        iqamaField.classList.add('hidden');
        iqamaField.style.display = 'none';
    } else {
        iqamaField.classList.remove('hidden');
        iqamaField.style.display = 'block';
    }
}

function handleRefIqamaFileUpload(input) {
    const file = input.files[0];
    if (file) {
        document.getElementById('customerRefIqamaFileName').textContent = file.name;
    }
}

function handleCustomerSubmit(e) {
    e.preventDefault();

    const name = elements.customerName.value.trim();
    const iqamaType = document.getElementById('customerIqamaType')?.value;
    const iqama = iqamaType === 'None' ? 'None' : elements.customerIqama.value.trim();
    const passport = elements.customerPassport.value.trim();
    const mobile = elements.customerMobile.value.trim();

    const newCustomer = {
        id: passport || iqama,
        name,
        iqama,
        passport,
        mobile,
    };

    state.customers.push(newCustomer);
    closeCustomerModal();
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
    
    // Show/hide Gender field based on passenger type
    const genderContainer = document.getElementById('passengerGenderContainer');
    if (genderContainer) {
        if (passengerType === 'Adult') {
            genderContainer.classList.remove('hidden');
        } else {
            genderContainer.classList.add('hidden');
            const genderSelect = document.getElementById('passengerGender');
            if (genderSelect) genderSelect.value = '';
        }
    }
    
    return passengerType;
}

// ============================================
// Passenger Modal Functions
// ============================================
function generateFlightDateRangeOptions(bookingDate = new Date(), targetSelect = null) {
    const target = targetSelect || elements.passengerFlightDateRange;
    if (!target) return;
    
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
        const passenger = state.passengers[passengerIndex];
        elements.passengerModalTitle.textContent = 'Edit Passenger';
        
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
        elements.passengerRouteType.value = passenger.routeType || '';
        elements.passengerFlightType.value = passenger.flightType || '';
        
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
        elements.passengerRouteType.value = '';
        elements.passengerFlightType.value = '';
        elements.passengerType.value = '';
        elements.passengerAddress.value = '';
        elements.passengerWithOffer.checked = false;
        elements.passengerRefundable.checked = false;
    }

    updateCheckboxState();
    setTimeout(() => document.getElementById('passengerFirstName')?.focus(), 100);
}

function closePassengerModal() {
    state.isPassengerModalOpen = false;
    state.editingPassengerIndex = null;
    elements.passengerModal.classList.add('hidden');
    
    // Clear gender field
    const genderSelect = document.getElementById('passengerGender');
    const genderContainer = document.getElementById('passengerGenderContainer');
    if (genderSelect) genderSelect.value = '';
    if (genderContainer) genderContainer.classList.add('hidden');
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
        showToast('Please enter a valid duration between 30 and 89 days', 'error');
        return;
    }
    const customValue = `Customized (${days} Days)`;
    
    const select = elements.passengerPackage;
    if (select) {
        let customOption = Array.from(select.options).find(opt => opt.value.startsWith('Customized'));
        if (!customOption) {
            customOption = document.createElement('option');
            select.appendChild(customOption);
        }
        customOption.value = customValue;
        customOption.text = customValue;
        select.value = customValue;
    }
    closeCustomDurationModal();
}

function updateCheckboxState() {
    const withOffer = elements.passengerWithOffer?.checked;
    
    if (withOffer) {
        if (elements.passengerRefundable) {
            elements.passengerRefundable.checked = false;
            elements.passengerRefundable.disabled = true;
        }
    } else {
        if (elements.passengerRefundable) {
            elements.passengerRefundable.disabled = false;
        }
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
    const routeType = elements.passengerRouteType?.value || '';
    const flightType = elements.passengerFlightType?.value || '';
    const gender = document.getElementById('passengerGender')?.value || '';

    if (!firstName || !lastName || !passport || !dateOfBirth || !package || !service || !route || !airline || !travelClass || !flightDateRange || !routeType || !flightType) {
        showToast('Please fill in all required fields', 'error');
        return;
    }

    // Gender required for Adult passengers
    if (passengerType === 'Adult' && !gender) {
        showToast('Please select gender for adult passenger', 'error');
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
        gender,
        mobileNo,
        package,
        service,
        route,
        airline,
        travelClass,
        flightDateRange,
        routeType,
        flightType,
        address,
        withOffer,
        refundable,
        documents: state.passengerDocFiles.map(doc => ({
            id: generateDocId(),
            name: doc.name,
            type: doc.type,
            size: doc.size,
            data: doc.data,
            uploadDate: new Date().toISOString().split('T')[0]
        }))
    };

    if (state.editingPassengerIndex !== null) {
        state.passengers[state.editingPassengerIndex] = passengerData;
        showToast('Passenger updated successfully');
    } else {
        state.passengers.push(passengerData);
        showToast('Passenger added successfully');
    }

    clearPassengerDocFiles();
    closePassengerModal();
    renderPassengerList();
    updateLiveSummary();
}

// ============================================
// Live Summary Functions
// ============================================
function updateLiveSummary() {
    const packageSelect = document.getElementById('bookingPackage');
    const districtSelect = document.getElementById('bookingDistrict');
    const fingerprintLocationSelect = document.getElementById('bookingFingerprintLocation');
    
    if (!packageSelect || !districtSelect || !fingerprintLocationSelect) return;
    
    const selectedPackage = packageSelect.options[packageSelect.selectedIndex];
    const packageName = selectedPackage?.value || '';
    const packageValue = parseInt(selectedPackage?.dataset.packageValue) || 0;
    
    const selectedDistrict = districtSelect.options[districtSelect.selectedIndex];
    const districtFingerprintCharge = parseInt(selectedDistrict?.dataset.fingerprintCharge) || 0;
    
    const fingerprintLocation = fingerprintLocationSelect.value || '';
    const fingerprintCharge = fingerprintLocation === 'Home' ? districtFingerprintCharge : 0;
    
    const paxQty = state.passengers.length;
    const totalValue = (packageValue * paxQty) + fingerprintCharge;
    
    // Calculate discount
    let discountAmount = 0;
    if (state.discountType === 'percentage') {
        discountAmount = totalValue * state.discountValue / 100;
    } else {
        discountAmount = state.discountValue;
    }
    
    const finalTotal = totalValue - discountAmount;
    
    document.getElementById('summaryPackage').textContent = packageName || '-';
    document.getElementById('summaryFingerprintCharge').textContent = fingerprintCharge > 0 ? `${fingerprintCharge} SAR` : '-';
    document.getElementById('summaryPaxQty').textContent = paxQty;
    document.getElementById('summaryDiscount').textContent = discountAmount > 0 ? `-${discountAmount} SAR` : '-';
    document.getElementById('summaryTotalBeforeDiscount').textContent = totalValue > 0 ? `${totalValue} SAR` : '0 SAR';
    document.getElementById('summaryTotalValue').textContent = `${finalTotal} SAR`;
}

// ============================================
// Discount Functions
// ============================================
function openDiscountModal() {
    const packageSelect = document.getElementById('bookingPackage');
    const districtSelect = document.getElementById('bookingDistrict');
    const fingerprintLocationSelect = document.getElementById('bookingFingerprintLocation');
    
    if (!packageSelect || !districtSelect || !fingerprintLocationSelect) return;
    
    const selectedPackage = packageSelect.options[packageSelect.selectedIndex];
    const packageValue = parseInt(selectedPackage?.dataset.packageValue) || 0;
    
    const selectedDistrict = districtSelect.options[districtSelect.selectedIndex];
    const districtFingerprintCharge = parseInt(selectedDistrict?.dataset.fingerprintCharge) || 0;
    
    const fingerprintLocation = fingerprintLocationSelect.value || '';
    const fingerprintCharge = fingerprintLocation === 'Home' ? districtFingerprintCharge : 0;
    
    const paxQty = state.passengers.length;
    const totalValue = (packageValue * paxQty) + fingerprintCharge;
    
    document.getElementById('discountOriginalTotal').value = totalValue;
    document.getElementById('discountType').value = state.discountType || 'fixed';
    document.getElementById('discountValue').value = state.discountValue || 0;
    
    calculateDiscount();
    document.getElementById('discountModal').classList.remove('hidden');
}

function calculateDiscount() {
    const originalTotal = parseFloat(document.getElementById('discountOriginalTotal').value) || 0;
    const discountType = document.getElementById('discountType').value;
    const discountValue = parseFloat(document.getElementById('discountValue').value) || 0;
    
    let discountAmount = 0;
    if (discountType === 'percentage') {
        discountAmount = originalTotal * discountValue / 100;
    } else {
        discountAmount = discountValue;
    }
    
    const newTotal = Math.max(0, originalTotal - discountAmount);
    
    document.getElementById('discountAmount').value = Math.round(discountAmount);
    document.getElementById('discountNewTotal').value = Math.round(newTotal);
}

function applyDiscount() {
    state.discountType = document.getElementById('discountType').value;
    state.discountValue = parseFloat(document.getElementById('discountValue').value) || 0;
    
    closeDiscountModal();
    updateLiveSummary();
    showToast('Discount applied');
}

function closeDiscountModal() {
    document.getElementById('discountModal').classList.add('hidden');
    document.getElementById('discountValue').value = 0;
}

// ============================================
// Passenger List Functions
// ============================================
function renderPassengerList() {
    const hasPassengers = state.passengers.length > 0;

    if (hasPassengers) {
        elements.passengerListContainer?.classList.remove('hidden');
        elements.addMoreButtonContainer?.classList.remove('hidden');
    } else {
        elements.passengerListContainer?.classList.add('hidden');
        elements.addMoreButtonContainer?.classList.add('hidden');
    }

    if (!elements.passengerList) return;
    
    elements.passengerList.innerHTML = '';
    state.passengers.forEach((passenger, index) => {
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
                        <div><span class="text-slate-500">Passport:</span> <span class="text-slate-700 ml-1">${passenger.passport}</span></div>
                        <div><span class="text-slate-500">Package:</span> <span class="text-slate-700 ml-1">${passenger.package}</span></div>
                        <div><span class="text-slate-500">Service:</span> <span class="text-slate-700 ml-1">${passenger.service}</span></div>
                        <div><span class="text-slate-500">Route:</span> <span class="text-slate-700 ml-1">${passenger.route || '-'}</span></div>
                        <div><span class="text-slate-500">Airline:</span> <span class="text-slate-700 ml-1">${passenger.airline || '-'}</span></div>
                        <div><span class="text-slate-500">Class:</span> <span class="text-slate-700 ml-1">${passenger.travelClass || '-'}</span></div>
                        <div><span class="text-slate-500">Flight Date:</span> <span class="text-slate-700 ml-1">${passenger.flightDateRange || '-'}</span></div>
                        <div><span class="text-slate-500">Type:</span> <span class="text-slate-700 ml-1">${passenger.passengerType || '-'}</span></div>
                    </div>
                    ${badgeHtml ? `<div class="mt-2">${badgeHtml}</div>` : ''}
                    ${passenger.address ? `<div class="mt-2 text-sm"><span class="text-slate-500">Address:</span> <span class="text-slate-700">${passenger.address}</span></div>` : ''}
                </div>
                <div class="flex items-center gap-2 ml-4">
                    <button type="button" onclick="openPassengerModal(${index})" class="px-3 py-1.5 text-sm border border-slate-300 text-slate-600 rounded hover:bg-slate-100 transition flex items-center gap-1">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                        </svg>
                        Edit
                    </button>
                    <button type="button" onclick="deletePassenger(${index})" class="px-3 py-1.5 text-sm border border-red-300 text-red-600 rounded hover:bg-red-100 transition flex items-center gap-1">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                        Delete
                    </button>
                </div>
            </div>
        `;
        elements.passengerList.appendChild(card);
    });
}

// ============================================
// Delete Passenger Function
// ============================================
function deletePassenger(index) {
    if (!confirm('Are you sure you want to remove this passenger?')) {
        return;
    }
    
    state.passengers.splice(index, 1);
    renderPassengerList();
    updateLiveSummary();
    
    if (state.editingBookingIndex !== null) {
        bookingIndexState.bookings[state.editingBookingIndex].passengers = state.passengers;
        saveBookingIndexToStorage();
    }
    
    showToast('Passenger removed');
}

// ============================================
// Form Control Functions
// ============================================
function showForm() {
    elements.addBookingButtonSection.classList.add('hidden');
    elements.addBookingSection.classList.remove('hidden');
    elements.indexSection.classList.add('hidden');
    elements.bookingForm.classList.remove('hidden');
    document.getElementById('bookingFormTitle').textContent = 'Booking Details';
    elements.bookingFingerprintLocation.value = 'Office';
    elements.bookingFingerprintOffice.value = '';
    state.editingBookingIndex = null;
    updateLiveSummary();

    // Check for package parameter in URL
    const params = new URLSearchParams(window.location.search);
    const packageIndex = params.get('package');

    if (packageIndex !== null) {
        prefillFromPackage(parseInt(packageIndex));
    }
}

function prefillFromPackage(packageIndex) {
    const adminSettings = JSON.parse(localStorage.getItem('adminSettings') || '{}');
    const packages = adminSettings.packageConfigs || [];

    if (!packages[packageIndex]) {
        return;
    }

    const pkg = packages[packageIndex];
    const ticketParts = (pkg.ticket || '').split(' - ');

    // Parse ticket details
    const route = ticketParts[0] || '';
    const airline = ticketParts[1] || '';
    const travelClass = ticketParts[2] || '';

    // Determine price (use offer price if available)
    const price = pkg.ticketType === 'Offer' && pkg.offerPrice > 0 ? pkg.offerPrice : pkg.regularPrice;

    // Set package in dropdown
    const packageSelect = elements.bookingPackage;
    if (packageSelect) {
        // Create option if not exists
        let option = Array.from(packageSelect.options).find(opt => opt.value === `package-${packageIndex}`);
        if (!option) {
            option = document.createElement('option');
            option.value = `package-${packageIndex}`;
            packageSelect.add(option);
        }
        option.textContent = pkg.packageName;
        option.dataset.packageValue = price;
        packageSelect.value = option.value;
    }

    // Set route in passenger modal fields (will be used when adding passenger)
    const passengerRoute = document.getElementById('passengerRoute');
    const passengerAirline = document.getElementById('passengerAirline');
    const passengerClass = document.getElementById('passengerClass');

    if (passengerRoute) {
        // Map route to dropdown option
        const routeOptions = Array.from(passengerRoute.options);
        const routeOption = routeOptions.find(opt => opt.value === route);
        if (routeOption) {
            passengerRoute.value = route;
        } else {
            passengerRoute.value = route;
        }
    }

    if (passengerAirline) {
        const airlineOptions = Array.from(passengerAirline.options);
        const airlineOption = airlineOptions.find(opt => opt.value === airline);
        if (airlineOption) {
            passengerAirline.value = airline;
        } else {
            passengerAirline.value = airline;
        }
    }

    if (passengerClass) {
        const classOptions = Array.from(passengerClass.options);
        const classOption = classOptions.find(opt => opt.value === travelClass);
        if (classOption) {
            passengerClass.value = travelClass;
        } else {
            passengerClass.value = travelClass;
        }
    }

    // Set route type based on route pattern
    const passengerRouteType = document.getElementById('passengerRouteType');
    const passengerFlightType = document.getElementById('passengerFlightType');

    if (passengerRouteType && route) {
        if (route.includes('MED') || route.split('-').length > 2) {
            // Multi-city or includes Medina
            const routeOption = Array.from(passengerRouteType.options).find(opt => opt.value === 'Multi City');
            if (routeOption) {
                passengerRouteType.value = 'Multi City';
            }
        } else if (route.split('-').length === 3 && route.endsWith('DAC')) {
            // Round trip
            const roundOption = Array.from(passengerRouteType.options).find(opt => opt.value === 'Round');
            if (roundOption) {
                passengerRouteType.value = 'Round';
            }
        }
    }

    if (passengerFlightType && route) {
        // Set flight type based on route
        if (route.includes('MED') && route.includes('JED')) {
            passengerFlightType.value = 'Transit';
        } else {
            passengerFlightType.value = 'Direct';
        }
    }
}

function hideForm() {
    elements.addBookingButtonSection.classList.remove('hidden');
    showIndexTab(state.currentIndexTab);
}

function openEditBooking(bookingIndex, addPassenger = false) {
    const booking = bookingIndexState.bookings[bookingIndex];
    if (!booking) {
        showToast('Booking not found', 'error');
        return;
    }

    // Show form
    elements.addBookingButtonSection.classList.add('hidden');
    elements.addBookingSection.classList.remove('hidden');
    elements.indexSection.classList.add('hidden');
    
    state.editingBookingIndex = bookingIndex;
    document.getElementById('bookingFormTitle').textContent = 'Edit Booking';

    // Set customer
    state.selectedCustomer = {
        name: booking.customerName,
        mobile: booking.customerMobile,
        passport: booking.passengers[0]?.passport || '',
        iqama: ''
    };
    elements.customerSearch.value = booking.customerName;
    elements.customerSearch.disabled = true;
    elements.selectedCustomer.classList.remove('hidden');
    document.getElementById('selectedCustomerName').textContent = booking.customerName;
    document.getElementById('selectedCustomerPassport').textContent = booking.passengers[0]?.passport || '-';
    document.getElementById('selectedCustomerMobile').textContent = booking.customerMobile;

    // Set booking fields
    elements.bookingDistrict.value = booking.district || '';
    elements.bookingFingerprintLocation.value = booking.fingerprintLocation || '';
    
    // Handle fingerprint office value
    elements.bookingFingerprintOffice.value = booking.fingerprintOffice || '';
    
    elements.bookingPackage.value = booking.package || '';
    
    // Load discount values
    state.discountType = booking.discountType || 'fixed';
    state.discountValue = booking.discountValue || 0;

    // Load passengers
    state.passengers = booking.passengers.map(p => ({
        name: p.name,
        firstName: p.firstName || p.name.split(' ').slice(0, -1).join(' '),
        lastName: p.lastName || p.name.split(' ').slice(-1)[0],
        passport: p.passport,
        passportExpiry: p.passportExpiry,
        dateOfBirth: p.dateOfBirth,
        route: p.route,
        airline: p.airline,
        travelClass: p.class,
        flightDateRange: p.flightDateRange,
        passengerType: p.passengerType,
        package: p.package,
        ticketFare: p.ticketFare > 0 ? { netFare: p.ticketFare } : null,
        visa: p.visaCost > 0 ? { finalCost: p.visaCost } : null,
    }));

    renderPassengerList();
    updateLiveSummary();

    // Load customer documents if available
    if (booking.customerDocuments) {
        state.bookingDocFiles = booking.customerDocuments;
        renderBookingDocList();
    } else {
        state.bookingDocFiles = [];
        renderBookingDocList();
    }

    // Show form
    elements.addBookingButtonSection.classList.add('hidden');
    elements.addBookingSection.classList.remove('hidden');
    elements.indexSection.classList.add('hidden');
    elements.bookingForm.classList.remove('hidden');

    // If addPassenger flag is set, open passenger modal
    if (addPassenger) {
        openPassengerModal();
    }
}

function clearForm() {
    state.selectedCustomer = null;
    state.customerSearchTerm = '';
    state.filteredCustomers = [];
    elements.customerSearch.value = '';
    elements.customerSearch.disabled = false;
    elements.selectedCustomer.classList.add('hidden');
    hideSuggestions();

    elements.bookingDistrict.value = '';
    elements.bookingFingerprintLocation.value = 'Office';
    elements.bookingFingerprintOffice.value = '';
    // document.getElementById('bookingServiceCharge').value = '0';
    document.getElementById('bookingRemarks').value = '';

    state.passengers = [];
    state.editingPassengerIndex = null;
    state.discountType = 'fixed';
    state.discountValue = 0;
    clearBookingDocFiles();
    renderPassengerList();
    updateLiveSummary();

    showToast('Form cleared');
}

function cancelForm() {
    clearForm();
    hideForm();
    showToast('Form cancelled');
}

function submitForm() {
    if (!state.selectedCustomer) {
        showToast('Please select a customer', 'error');
        return;
    }

    if (state.passengers.length === 0) {
        showToast('Please add at least one passenger', 'error');
        return;
    }

    const district = elements.bookingDistrict.value.trim();
    if (!district) {
        showToast('Please enter district', 'error');
        return;
    }

    const fingerprintLocation = elements.bookingFingerprintLocation.value;
    if (!fingerprintLocation) {
        showToast('Please select fingerprint location', 'error');
        return;
    }

    const fingerprintOffice = elements.bookingFingerprintOffice?.value;
    if (!fingerprintOffice) {
        showToast('Please select fingerprint office', 'error');
        return;
    }

    const package = elements.bookingPackage.value || '';
    // const serviceCharge = document.getElementById('bookingServiceCharge').value || 0;
    const remarks = document.getElementById('bookingRemarks').value || '';

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

    // Check if editing
    if (state.editingBookingIndex !== null) {
        const booking = bookingIndexState.bookings[state.editingBookingIndex];
        const oldInvoiceNo = booking.invoiceNo;

        // Update booking record
        booking.district = district;
        booking.fingerprintLocation = fingerprintLocation;
        booking.fingerprintOffice = fingerprintOffice;
        booking.package = package;
        booking.discountType = state.discountType;
        booking.discountValue = state.discountValue;
        // booking.serviceCharge = parseFloat(serviceCharge);
        booking.remarks = remarks;
        booking.passengers = bookingPassengers;
        booking.customerDocuments = state.bookingDocFiles.map(doc => ({
            name: doc.name,
            type: doc.type,
            size: doc.size,
            data: doc.data,
            uploadDate: new Date().toISOString().split('T')[0]
        }));

        // Remove old passenger index rows for this booking
        passengerIndexState.passengerIndexRows = passengerIndexState.passengerIndexRows.filter(row => row.invoiceNo !== oldInvoiceNo);

        // Add new passenger index rows
        bookingPassengers.forEach((passenger, idx) => {
            const indexRow = {
                date: booking.bookingDate,
                invoiceNo: oldInvoiceNo,
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
                passengerData: state.passengers[idx],
            };
            passengerIndexState.passengerIndexRows.push(indexRow);
        });

        savePassengerIndexToStorage();
        saveBookingIndexToStorage();
        
        showToast(`Booking ${oldInvoiceNo} updated successfully`);
        
        renderPassengerIndex();
        elements.passengerIndexSection.classList.remove('hidden');
        renderBookingIndex();
        
        clearForm();
        state.editingBookingIndex = null;
        showIndexTab(state.currentIndexTab);
        return;
    }

    // Create new booking
    const invoiceNo = 'INV-' + passengerIndexState.invoiceCounter++;
    const bookingDate = new Date().toLocaleDateString();

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
            passengerData: state.passengers[idx],
        };
        passengerIndexState.passengerIndexRows.push(indexRow);
    });
    savePassengerIndexToStorage();

    const bookingId = Date.now();
    const bookingRecord = {
        id: bookingId,
        invoiceNo: invoiceNo,
        bookingDate: bookingDate,
        customerName: state.selectedCustomer.name,
        customerMobile: state.selectedCustomer.mobile,
        district: district,
        fingerprintLocation: fingerprintLocation,
        fingerprintOffice: fingerprintOffice,
        package: package,
        discountType: state.discountType,
        discountValue: state.discountValue,
        // serviceCharge: parseFloat(serviceCharge),
        remarks: remarks,
        passengers: bookingPassengers,
        customerDocuments: state.bookingDocFiles.map(doc => ({
            name: doc.name,
            type: doc.type,
            size: doc.size,
            data: doc.data,
            uploadDate: new Date().toISOString().split('T')[0]
        })),
        payments: []
    };
    bookingIndexState.bookings.push(bookingRecord);

    const bookingRef = 'BK' + Date.now().toString().slice(-8);
    const bookingData = {
        reference: bookingRef,
        invoiceNo: invoiceNo,
        customer: state.selectedCustomer,
        district: district,
        fingerprintLocation: fingerprintLocation,
        fingerprintOffice: fingerprintOffice,
        package: package,
        passengers: state.passengers,
        totalPassengers: state.passengers.length,
        customerDocuments: state.bookingDocFiles.map(doc => ({
            name: doc.name,
            type: doc.type,
            size: doc.size,
            data: doc.data,
            uploadDate: new Date().toISOString().split('T')[0]
        })),
        bookingDate: new Date().toISOString(),
    };

    console.log('Booking submitted:', bookingData);

    showToast(`Booking ${bookingRef} submitted with ${state.passengers.length} passenger(s)`);
    
    renderPassengerIndex();
    renderBookingIndex();
    
    clearForm();
    showIndexTab(state.currentIndexTab);
}

// ============================================
// Booking Section Navigation
// ============================================
function showIndexTab(tab) {
    const subTabIndex = document.getElementById('subTabIndex');
    const subTabNew = document.getElementById('subTabNew');
    const bookingIndexSection = elements.bookingIndexSection;
    const passengerIndexSection = elements.passengerIndexSection;
    
    // Update state
    state.currentIndexTab = tab;
    
    // Show index section and button, hide add booking form
    if (elements.addBookingButtonSection) elements.addBookingButtonSection.classList.remove('hidden');
    if (elements.indexSection) elements.indexSection.classList.remove('hidden');
    if (elements.addBookingSection) elements.addBookingSection.classList.add('hidden');
    
    if (tab === 'booking') {
        if (subTabIndex) subTabIndex.className = 'px-4 py-2 rounded-lg font-medium bg-slate-700 text-white';
        if (subTabNew) subTabNew.className = 'px-4 py-2 rounded-lg font-medium bg-slate-200 text-slate-700 hover:bg-slate-300';
        if (bookingIndexSection) bookingIndexSection.classList.remove('hidden');
        if (passengerIndexSection) passengerIndexSection.classList.add('hidden');
        renderBookingIndex();
    } else {
        if (subTabIndex) subTabIndex.className = 'px-4 py-2 rounded-lg font-medium bg-slate-200 text-slate-700 hover:bg-slate-300';
        if (subTabNew) subTabNew.className = 'px-4 py-2 rounded-lg font-medium bg-slate-700 text-white';
        if (bookingIndexSection) bookingIndexSection.classList.add('hidden');
        if (passengerIndexSection) passengerIndexSection.classList.remove('hidden');
        renderPassengerIndex();
    }
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
        elements.bookingIndexEmpty?.classList.add('hidden');
    } else {
        elements.bookingIndexEmpty?.classList.remove('hidden');
    }

    if (!elements.bookingIndexTableBody) return;
    
    elements.bookingIndexTableBody.innerHTML = '';
    bookings.forEach((booking, index) => {
        const total = booking.passengers.reduce((sum, p) => sum + (p.total || 0), 0);
        const paid = booking.payments.reduce((sum, p) => sum + (p.amount || 0), 0);
        const due = total - paid;
        
        const firstPassenger = booking.passengers[0];
        const flightDate = firstPassenger?.flightDateRange || '-';
        
        const reIssueTickets = booking.reIssueTickets || 0;
        const reIssueCost = booking.reIssueCost || 0;
        const refundTickets = booking.refundTickets || 0;
        const refundAmount = booking.refundAmount || 0;

        const tr = document.createElement('tr');
        tr.className = 'hover:bg-slate-50';
        tr.innerHTML = `
            <td class="px-3 py-2 text-slate-800 font-medium">${booking.invoiceNo}</td>
            <td class="px-3 py-2 text-slate-600">${booking.bookingDate}</td>
            <!-- <td class="px-3 py-2 text-slate-600">${flightDate}</td> -->
            <td class="px-3 py-2 text-slate-800">${booking.customerName}</td>
            <td class="px-3 py-2 text-slate-600">${booking.customerMobile || '-'}</td>
            <td class="px-3 py-2 text-slate-600">${firstPassenger?.name || '-'}</td>
            <td class="px-3 py-2">
                <select onchange="updateFingerprintLocation(${index}, this.value)" class="text-xs border border-slate-300 rounded px-2 py-1 bg-white">
                    <option value="Home" ${(function() {
                        const passenger = passengerIndexState.passengerIndexRows.find(p => p.invoiceNo === booking.invoiceNo);
                        return passenger?.fingerprintLocation === 'Home' ? 'selected' : '';
                    })()} >Home</option>
                    <option value="Office" ${(function() {
                        const passenger = passengerIndexState.passengerIndexRows.find(p => p.invoiceNo === booking.invoiceNo);
                        return passenger?.fingerprintLocation === 'Office' ? 'selected' : '';
                    })()} >Office</option>
                </select>
            </td>
            <td class="px-3 py-2 text-slate-600">${booking.fingerprintOffice || '-'}</td>
            <td class="px-3 py-2 text-slate-600">${booking.district || '-'}</td>
            <td class="px-3 py-2 text-slate-600">${booking.passengers.length}</td>
            <td class="px-3 py-2 text-slate-600">${reIssueTickets}</td>
            <td class="px-3 py-2 text-slate-600">${reIssueCost}</td>
            <td class="px-3 py-2 text-slate-600">${refundTickets}</td>
            <td class="px-3 py-2 text-slate-600">${refundAmount}</td>
            <td class="px-3 py-2 text-slate-800 font-medium">${total}</td>
            <td class="px-3 py-2 text-green-600 font-medium">${paid}</td>
            <td class="px-3 py-2 text-red-600 font-medium">${due}</td>
            <td class="px-3 py-2">
                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium ${due > 0 ? 'bg-yellow-100 text-yellow-700' : 'bg-green-100 text-green-700'}">
                    ${due > 0 ? 'Due' : 'Paid'}
                </span>
            </td>
            <td class="px-3 py-2">
                <div class="flex gap-2">
                    <button onclick="window.location.href='invoice-details.html?index=${index}'" class="text-xs bg-slate-100 hover:bg-slate-200 text-slate-600 px-2 py-1 rounded">View</button>
                    <div class="relative" id="docsDropdown-${index}">
                        <button onclick="toggleDocsDropdown(${index})" class="text-xs bg-green-100 hover:bg-green-200 text-green-600 px-2 py-1 rounded flex items-center gap-1">Download Docs <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg></button>
                        <div id="docsDropdownMenu-${index}" class="hidden absolute right-0 mt-1 w-40 bg-white border border-gray-200 rounded-md shadow-lg z-50">
                            <button onclick="downloadCustomerDocs(${index}); hideDocsDropdown(${index})" class="block w-full text-left px-3 py-2 text-xs text-slate-700 hover:bg-gray-100 rounded-t-md">Customer</button>
                            <button onclick="downloadPassengerDocs(${index}); hideDocsDropdown(${index})" class="block w-full text-left px-3 py-2 text-xs text-slate-700 hover:bg-gray-100 rounded-b-md">Passengers</button>
                        </div>
                    </div>
                </div>
            </td>
        `;
        elements.bookingIndexTableBody.appendChild(tr);
    });
}

// ============================================
// Passenger Index Functions
// ============================================
function renderPassengerIndex() {
    if (!elements.passengerIndexSection) return;
    
    elements.passengerIndexSection.classList.remove('hidden');
    
    const rows = passengerIndexState.passengerIndexRows;
    
    if (rows.length > 0) {
        elements.passengerIndexEmpty?.classList.add('hidden');
    } else {
        elements.passengerIndexEmpty?.classList.remove('hidden');
    }

    if (!elements.passengerIndexTableBody) return;
    
    elements.passengerIndexTableBody.innerHTML = '';
    
    const invoiceCounts = {};
    rows.forEach(row => {
        invoiceCounts[row.invoiceNo] = (invoiceCounts[row.invoiceNo] || 0) + 1;
    });
    
    let prevInvoiceNo = null;
    rows.forEach((row, index) => {
        const ticketSellingPrice = row.ticketFare ? row.ticketFare.sellingFare || 0 : 0;
        const visaSellingPrice = row.visa ? row.visa.sellingPrice || 0 : 0;
        const fingerprintCost = row.fingerprintLocation !== 'None' ? 200 : 0;
        const packageValue = ticketSellingPrice + visaSellingPrice + fingerprintCost;
        
        const ticketNetFare = row.ticketFare ? row.ticketFare.netFare || 0 : 0;
        const finalVisaCost = row.visa ? row.visa.finalCost || 0 : 0;
        const totalCost = ticketNetFare + finalVisaCost + fingerprintCost;
        const markup = ticketSellingPrice - ticketNetFare;

        const isFirstInInvoice = prevInvoiceNo !== row.invoiceNo;
        const paxQty = isFirstInInvoice ? invoiceCounts[row.invoiceNo] : '';
        prevInvoiceNo = row.invoiceNo;

        const tr = document.createElement('tr');
        tr.className = 'hover:bg-slate-50';
        tr.innerHTML = `
            <td class="px-3 py-2 text-slate-600">${row.date}</td>
            <!-- <td class="px-3 py-2 text-slate-600">${row.ticketFare?.date || '-'}</td> -->
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
                    ? `<span class="text-slate-600">${row.visa.agent}</span>`
                    : '-'
                }
            </td>
            <td class="px-3 py-2">
                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium ${row.visa?.issued ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700'}">
                    ${row.visa?.issued ? 'Issued' : 'Pending'}
                </span>
            </td>
            <td class="px-3 py-2 text-slate-600">${row.requiredFlightDate || '-'}</td>
            <td class="px-3 py-2 text-slate-600">${row.actualFlightDate || '-'}</td>
            <td class="px-3 py-2 text-slate-600">${row.fingerprintLocation !== 'None' ? row.fingerprintCost : '-'}</td>
            <td class="px-3 py-2 text-slate-600">${row.package}</td>
            <td class="px-3 py-2 text-slate-800 font-medium">${packageValue}</td>
            <td class="px-3 py-2 text-slate-800 font-medium">${totalCost}</td>
            <td class="px-3 py-2 text-slate-600">${markup > 0 ? markup : '-'}</td>
            <td class="px-3 py-2 text-slate-600">${row.due}</td>
            <td class="px-3 py-2">
                <div class="flex gap-1 flex-wrap">
                    <button onclick="viewPassengerDetails(${index})" class="text-xs bg-slate-100 hover:bg-slate-200 text-slate-600 px-2 py-1 rounded">View</button>
                    <button onclick="openDocUploadModal(${index})" class="text-xs bg-blue-50 hover:bg-blue-100 text-blue-600 px-2 py-1 rounded">Upload</button>
                    ${row.documents && row.documents.length > 0 ? `<button onclick="downloadSinglePassengerDocs(${index})" class="text-xs bg-green-50 hover:bg-green-100 text-green-600 px-2 py-1 rounded">Download</button>` : ''}
                </div>
            </td>
        `;
        elements.passengerIndexTableBody.appendChild(tr);
    });
}

function updateStatus(index, value) {
    passengerIndexState.passengerIndexRows[index].status = value;
    savePassengerIndexToStorage();
    showToast('Status updated');
}

function updateFingerprintLocation(bookingIndex, value) {
    const booking = bookingIndexState.bookings[bookingIndex];
    if (!booking) return;
    
    const invoiceNo = booking.invoiceNo;
    const passengerIndex = passengerIndexState.passengerIndexRows.findIndex(p => p.invoiceNo === invoiceNo);
    
    if (passengerIndex !== -1) {
        passengerIndexState.passengerIndexRows[passengerIndex].fingerprintLocation = value;
        savePassengerIndexToStorage();
        renderBookingIndex();
        showToast('Fingerprint location updated');
    }
}

function viewPassengerDetails(index) {
    window.location.href = `passenger-details.html?index=${index}`;
}

// ============================================
// Document Upload Functions
// ============================================
let currentUploadPassengerIndex = null;
let pendingDocFiles = [];

function openDocUploadModal(index) {
    currentUploadPassengerIndex = index;
    pendingDocFiles = [];
    document.getElementById('docFileList').innerHTML = '';
    document.getElementById('docUploadModal').classList.remove('hidden');
}

function closeDocUploadModal() {
    document.getElementById('docUploadModal').classList.add('hidden');
    currentUploadPassengerIndex = null;
    pendingDocFiles = [];
}

function handleDocFileSelect(event) {
    const files = event.target.files;
    processDocFiles(files);
}

function processDocFiles(files) {
    const maxSize = 5 * 1024 * 1024; // 5MB
    const totalMaxSize = 20 * 1024 * 1024; // 20MB
    const allowedTypes = ['application/pdf', 'image/jpeg', 'image/png'];
    
    const currentTotal = pendingDocFiles.reduce((sum, f) => sum + f.size, 0);
    
    Array.from(files).forEach(file => {
        if (!allowedTypes.includes(file.type)) {
            showToast('Invalid file type: ' + file.name);
            return;
        }
        if (file.size > maxSize) {
            showToast('File must not exceed 5 MB: ' + file.name);
            return;
        }
        if (currentTotal + file.size > totalMaxSize) {
            showToast('Total file size must not exceed 20 MB.');
            return;
        }
        
        const reader = new FileReader();
        reader.onload = function(e) {
            const fileData = {
                name: file.name,
                type: file.type,
                size: file.size,
                data: e.target.result
            };
            pendingDocFiles.push(fileData);
            renderDocFileList();
        };
        reader.readAsDataURL(file);
    });
}

function renderDocFileList() {
    const container = document.getElementById('docFileList');
    container.innerHTML = pendingDocFiles.map((file, i) => `
        <div class="flex items-center justify-between bg-slate-50 rounded px-3 py-2">
            <span class="text-sm text-slate-700 truncate">${file.name}</span>
            <button onclick="removePendingDoc(${i})" class="text-red-500 hover:text-red-700">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
    `).join('');
}

function removePendingDoc(index) {
    pendingDocFiles.splice(index, 1);
    renderDocFileList();
}

function saveDocuments() {
    if (currentUploadPassengerIndex === null) return;
    
    pendingDocFiles.forEach(docData => {
        addDocumentToPassenger(currentUploadPassengerIndex, docData);
    });
    
    showToast('Documents uploaded successfully');
    closeDocUploadModal();
    renderPassengerIndex();
}

function downloadSinglePassengerDocs(index) {
    const row = passengerIndexState.passengerIndexRows[index];
    if (!row || !row.documents || row.documents.length === 0) {
        showToast('No documents to download');
        return;
    }
    
    if (typeof jspdf === 'undefined') {
        showToast('PDF library not loaded');
        return;
    }
    
    const { jsPDF } = window.jspdf;
    const pdf = new jsPDF();
    
    let hasContent = false;
    
    row.documents.forEach((doc, i) => {
        if (doc.data) {
            hasContent = true;
            if (i > 0) pdf.addPage();
            
            if (doc.type === 'application/pdf') {
                try {
                    const pdfData = atob(doc.data.split(',')[1]);
                    const pdfDoc = new jsPDF({});
                    pdfDoc.addFileToV('doc.pdf', doc.data);
                } catch (e) {
                    pdf.text(`Document: ${doc.name}`, 10, 10 + (i * 20));
                }
            } else if (doc.type.startsWith('image/')) {
                pdf.addImage(doc.data, 'JPEG', 10, 10, 180, 250);
            }
        } else {
            if (i > 0) pdf.addPage();
            pdf.setFontSize(12);
            pdf.text(`Sample Document: ${doc.name}`, 10, 20);
            pdf.setFontSize(10);
            pdf.text(`Type: ${doc.type}`, 10, 30);
            pdf.text(`Upload Date: ${doc.uploadDate || 'N/A'}`, 10, 40);
            pdf.text(`(Sample document for demo)`, 10, 50);
            hasContent = true;
        }
    });
    
    if (hasContent) {
        pdf.save(`${row.passengerName}_documents.pdf`);
        showToast('Documents downloaded');
    } else {
        showToast('No downloadable content');
    }
}

// ============================================
// Passenger Form Document Upload Functions
// ============================================
function handlePassengerDocSelect(event) {
    const files = event.target.files;
    const maxSize = 5 * 1024 * 1024;
    const totalMaxSize = 20 * 1024 * 1024;
    const allowedTypes = ['application/pdf', 'image/jpeg', 'image/png'];
    
    const currentTotal = state.passengerDocFiles.reduce((sum, f) => sum + f.size, 0);
    
    Array.from(files).forEach(file => {
        if (!allowedTypes.includes(file.type)) {
            showToast('Invalid file type: ' + file.name);
            return;
        }
        if (file.size > maxSize) {
            showToast('File must not exceed 5 MB: ' + file.name);
            return;
        }
        if (currentTotal + file.size > totalMaxSize) {
            showToast('Total file size must not exceed 20 MB.');
            return;
        }
        
        const reader = new FileReader();
        reader.onload = function(e) {
            state.passengerDocFiles.push({
                name: file.name,
                type: file.type,
                size: file.size,
                data: e.target.result
            });
            renderPassengerDocList();
        };
        reader.readAsDataURL(file);
    });
}

function renderPassengerDocList() {
    const container = document.getElementById('passengerDocList');
    container.innerHTML = state.passengerDocFiles.map((file, i) => `
        <div class="flex items-center justify-between bg-slate-50 rounded px-3 py-2">
            <span class="text-sm text-slate-700 truncate">${file.name}</span>
            <button type="button" onclick="removePassengerDoc(${i})" class="text-red-500 hover:text-red-700">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
    `).join('');
}

function removePassengerDoc(index) {
    state.passengerDocFiles.splice(index, 1);
    renderPassengerDocList();
}

function clearPassengerDocFiles() {
    state.passengerDocFiles = [];
    renderPassengerDocList();
}

// ============================================
// Booking Form Document Upload Functions
// ============================================
function handleBookingDocSelect(event) {
    const files = event.target.files;
    const maxSize = 5 * 1024 * 1024;
    const totalMaxSize = 20 * 1024 * 1024;
    const allowedTypes = ['application/pdf', 'image/jpeg', 'image/png'];
    
    const currentTotal = state.bookingDocFiles.reduce((sum, f) => sum + f.size, 0);
    
    Array.from(files).forEach(file => {
        if (!allowedTypes.includes(file.type)) {
            showToast('Invalid file type: ' + file.name);
            return;
        }
        if (file.size > maxSize) {
            showToast('File must not exceed 5 MB: ' + file.name);
            return;
        }
        if (currentTotal + file.size > totalMaxSize) {
            showToast('Total file size must not exceed 20 MB.');
            return;
        }
        
        const reader = new FileReader();
        reader.onload = function(e) {
            state.bookingDocFiles.push({
                name: file.name,
                type: file.type,
                size: file.size,
                data: e.target.result
            });
            renderBookingDocList();
        };
        reader.readAsDataURL(file);
    });
}

function renderBookingDocList() {
    const container = document.getElementById('bookingDocList');
    container.innerHTML = state.bookingDocFiles.map((file, i) => `
        <div class="flex items-center justify-between bg-slate-50 rounded px-3 py-2">
            <span class="text-sm text-slate-700 truncate">${file.name}</span>
            <button type="button" onclick="removeBookingDoc(${i})" class="text-red-500 hover:text-red-700">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
    `).join('');
}

function removeBookingDoc(index) {
    state.bookingDocFiles.splice(index, 1);
    renderBookingDocList();
}

// Customer Form Document Upload Functions
// ============================================
function handleCustomerDocSelect(event) {
    const files = event.target.files;
    const maxSize = 5 * 1024 * 1024;
    const totalMaxSize = 20 * 1024 * 1024;
    const allowedTypes = ['application/pdf', 'image/jpeg', 'image/png'];
    
    const currentTotal = state.customerDocFiles.reduce((sum, f) => sum + f.size, 0);
    
    Array.from(files).forEach(file => {
        if (!allowedTypes.includes(file.type)) {
            showToast('Invalid file type: ' + file.name);
            return;
        }
        if (file.size > maxSize) {
            showToast('File must not exceed 5 MB: ' + file.name);
            return;
        }
        if (currentTotal + file.size > totalMaxSize) {
            showToast('Total file size must not exceed 20 MB.');
            return;
        }
        
        const reader = new FileReader();
        reader.onload = function(e) {
            state.customerDocFiles.push({
                name: file.name,
                type: file.type,
                size: file.size,
                data: e.target.result
            });
            renderCustomerDocList();
        };
        reader.readAsDataURL(file);
    });
}

function renderCustomerDocList() {
    const container = document.getElementById('customerDocList');
    container.innerHTML = state.customerDocFiles.map((file, i) => `
        <div class="flex items-center justify-between bg-slate-50 rounded px-3 py-2">
            <span class="text-sm text-slate-700 truncate">${file.name}</span>
            <button type="button" onclick="removeCustomerDoc(${i})" class="text-red-500 hover:text-red-700">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
    `).join('');
}

function removeCustomerDoc(index) {
    state.customerDocFiles.splice(index, 1);
    renderCustomerDocList();
}

function clearBookingDocFiles() {
    state.bookingDocFiles = [];
    renderBookingDocList();
}

// ============================================
// Ticket Fare Modal Functions
// ============================================
function openTicketFareModal(rowIndex) {
    passengerIndexState.editingTicketFareRowIndex = rowIndex;
    passengerIndexState.isTicketFareModalOpen = true;
    document.getElementById('ticketFareModal')?.classList.remove('hidden');

    const row = passengerIndexState.passengerIndexRows[rowIndex];
    const passenger = row.passengerData;

    generateFlightDateRangeOptions(new Date(), document.getElementById('ticketFareFlightDateRange'));

    document.getElementById('ticketFareRoute').value = passenger?.route || '';
    document.getElementById('ticketFareAirline').value = passenger?.airline || '';
    document.getElementById('ticketFareClass').value = passenger?.travelClass || '';
    document.getElementById('ticketFarePassengerType').value = row.ticketFare?.passengerType || passenger?.passengerType || '';
    document.getElementById('ticketFareUpDate').value = row.ticketFare?.upDate || '';
    document.getElementById('ticketFareDownDate').value = row.ticketFare?.downDate || '';
    document.getElementById('ticketFarePNR').value = row.ticketFare?.pnr || '';
    document.getElementById('ticketFareTicketNumber').value = row.ticketFare?.ticketNumber || '';
    document.getElementById('ticketFareDate').value = new Date().toISOString().split('T')[0];
    document.getElementById('ticketFareAgent').value = '';
    document.getElementById('ticketFareSellingFare').value = row.ticketFare?.sellingFare || 0;
    document.getElementById('ticketFareNet').value = row.ticketFare?.netFare || 0;
    document.getElementById('ticketFareWithOffer').checked = row.ticketFare?.withOffer || false;
    document.getElementById('ticketFareRefundable').checked = row.ticketFare?.refundable || false;
    
    document.getElementById('ticketFareType').value = row.ticketFare?.ticketType || 'regular';
    handleTicketTypeChange();
    document.getElementById('ticketFareGroupTicket').value = row.ticketFare?.groupTicketId || '';
}

function handleTicketTypeChange() {
    const ticketType = document.getElementById('ticketFareType').value;
    const groupTicketSection = document.getElementById('groupTicketSection');
    const groupTicketSelect = document.getElementById('ticketFareGroupTicket');
    
    if (ticketType === 'group') {
        groupTicketSection.classList.remove('hidden');
        groupTicketSelect.innerHTML = '<option value="">Select Ticket</option>' + 
            groupTickets.map(gt => 
                `<option value="${gt.id}">${gt.pnr} • ${gt.date} • ${gt.remainingSeats} seats</option>`
            ).join('');
    } else {
        groupTicketSection.classList.add('hidden');
        groupTicketSelect.innerHTML = '<option value="">Select Ticket</option>';
    }
}

function handleGroupTicketSelect() {
    const selectedId = document.getElementById('ticketFareGroupTicket').value;
    const selected = groupTickets.find(gt => gt.id === selectedId);
    
    if (selected) {
        document.getElementById('ticketFarePNR').value = selected.pnr;
        document.getElementById('ticketFareDate').value = selected.date;
        document.getElementById('ticketFareRoute').value = selected.route;
    }
}

function closeTicketFareModal() {
    passengerIndexState.editingTicketFareRowIndex = null;
    passengerIndexState.isTicketFareModalOpen = false;
    document.getElementById('ticketFareModal')?.classList.add('hidden');
}

function handleTicketFareSubmit(e) {
    e.preventDefault();
    const rowIndex = passengerIndexState.editingTicketFareRowIndex;
    if (rowIndex === null) return;

    const ticketFareData = {
        ticketType: document.getElementById('ticketFareType').value,
        groupTicketId: document.getElementById('ticketFareGroupTicket').value,
        upDate: document.getElementById('ticketFareUpDate').value,
        downDate: document.getElementById('ticketFareDownDate').value,
        pnr: document.getElementById('ticketFarePNR').value,
        ticketNumber: document.getElementById('ticketFareTicketNumber').value,
        date: document.getElementById('ticketFareDate').value,
        ticketAgent: document.getElementById('ticketFareAgent').value,
        route: document.getElementById('ticketFareRoute').value,
        airline: document.getElementById('ticketFareAirline').value,
        travelClass: document.getElementById('ticketFareClass').value,
        flightDateRange: document.getElementById('ticketFareFlightDateRange').value,
        passengerType: document.getElementById('ticketFarePassengerType').value,
        finalFlightDate: document.getElementById('ticketFareFinalFlightDate').value,
        sellingFare: parseFloat(document.getElementById('ticketFareSellingFare').value) || 0,
        netFare: parseFloat(document.getElementById('ticketFareNet').value) || 0,
        withOffer: document.getElementById('ticketFareWithOffer').checked,
        refundable: document.getElementById('ticketFareRefundable').checked,
    };

    passengerIndexState.passengerIndexRows[rowIndex].ticketFare = ticketFareData;
    savePassengerIndexToStorage();
    
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
    const modal = document.getElementById('visaCostModal');
    if (modal) {
        document.body.appendChild(modal);
        modal.classList.remove('hidden');
        modal.style.cssText = 'display:flex !important;visibility:visible !important;position:fixed !important;z-index:99999 !important;';
    }

    const latestVisa = visaAdminState.visaPriceRecords[0];
    document.getElementById('visaCostSellingPrice').value = latestVisa?.price || 500;
    document.getElementById('visaCostAgent').value = '';
    document.getElementById('visaCostCommissionAgent').value = '';
    document.getElementById('visaCostAgentCommission').value = 0;
    document.getElementById('visaCostNetVisaCost').value = 0;
    document.getElementById('visaCostFinal').value = 0;

    updateVisaCostFinal();
}

function closeVisaCostModal() {
    passengerIndexState.editingVisaCostRowIndex = null;
    passengerIndexState.isVisaCostModalOpen = false;
    const modal = document.getElementById('visaCostModal');
    if (modal) {
        modal.classList.add('hidden');
        modal.style.cssText = 'display:none !important;';
    }
}

function updateCommissionAgentOptions() {
    const agent = document.getElementById('visaCostAgent').value;
    const commissionSelect = document.getElementById('visaCostCommissionAgent');
    
    const agents = {
        "Visa Agent A": ["Commission Agent 1", "Commission Agent 2"],
        "Visa Agent B": ["Commission Agent 3", "Commission Agent 4"]
    };

    commissionSelect.innerHTML = '<option value="">Select Commission Agent</option>';
    if (agents[agent]) {
        agents[agent].forEach(a => {
            const option = document.createElement('option');
            option.value = a;
            option.textContent = a;
            commissionSelect.appendChild(option);
        });
    }
}

function updateVisaCostFinal() {
    const sellingPrice = parseFloat(document.getElementById('visaCostSellingPrice').value) || 0;
    const commission = parseFloat(document.getElementById('visaCostAgentCommission').value) || 0;
    const netCost = parseFloat(document.getElementById('visaCostNetVisaCost').value) || 0;
    document.getElementById('visaCostFinal').value = sellingPrice + commission + netCost;
}

function handleVisaCostSubmit(e) {
    e.preventDefault();
    const rowIndex = passengerIndexState.editingVisaCostRowIndex;
    if (rowIndex === null) return;

    const agentCommission = parseFloat(document.getElementById('visaCostAgentCommission').value) || 0;
    const netVisaCost = parseFloat(document.getElementById('visaCostNetVisaCost').value) || 0;

    const visaData = {
        agent: document.getElementById('visaCostAgent').value,
        commissionAgent: document.getElementById('visaCostCommissionAgent').value,
        sellingPrice: parseFloat(document.getElementById('visaCostSellingPrice').value) || 0,
        agentCommission: agentCommission,
        netVisaCost: netVisaCost,
        finalCost: agentCommission + netVisaCost,
    };

    passengerIndexState.passengerIndexRows[rowIndex].visa = visaData;
    savePassengerIndexToStorage();
    
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
    const modal = document.getElementById('visaIssueModal');
    if (modal) {
        document.body.appendChild(modal);
        modal.classList.remove('hidden');
        modal.style.cssText = 'display:flex !important;visibility:visible !important;position:fixed !important;z-index:99999 !important;';
    }

    const row = passengerIndexState.passengerIndexRows[rowIndex];
    const visa = row.visa;

    if (visa) {
        document.getElementById('visaIssueAgent').value = visa.agent || '';
        document.getElementById('visaIssueVisaNumber').value = visa.visaNumber || '';
        document.getElementById('visaIssueSellingPrice').value = visa.sellingPrice || 0;
        document.getElementById('visaIssueAdditionalCost').value = visa.additionalCost || 0;
        document.getElementById('visaIssueRemarks').value = visa.remarks || '';
        
        const sellingPrice = visa.sellingPrice || 0;
        const additionalCost = visa.additionalCost || 0;
        document.getElementById('visaIssueTotalCost').value = sellingPrice + additionalCost;
    }
}

function closeVisaIssueModal() {
    editingVisaIssueRowIndex = null;
    const modal = document.getElementById('visaIssueModal');
    if (modal) {
        modal.classList.add('hidden');
        modal.style.cssText = 'display:none !important;';
    }
}

function calculateVisaIssueTotal() {
    const sellingPrice = parseFloat(document.getElementById('visaIssueSellingPrice').value) || 0;
    const additionalCost = parseFloat(document.getElementById('visaIssueAdditionalCost').value) || 0;
    document.getElementById('visaIssueTotalCost').value = sellingPrice + additionalCost;
}

function handleVisaIssueSubmit(e) {
    e.preventDefault();

    const rowIndex = editingVisaIssueRowIndex;
    if (rowIndex === null) return;

    const visa = passengerIndexState.passengerIndexRows[rowIndex].visa;
    const additionalCost = parseFloat(document.getElementById('visaIssueAdditionalCost').value) || 0;
    const remarks = document.getElementById('visaIssueRemarks').value;
    const visaNumber = document.getElementById('visaIssueVisaNumber').value;

    visa.visaNumber = visaNumber;
    visa.additionalCost = additionalCost;
    visa.remarks = remarks;
    visa.issued = true;

    passengerIndexState.passengerIndexRows[rowIndex].visa = visa;
    savePassengerIndexToStorage();
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
    const modal = document.getElementById('visaEditModal');
    if (modal) {
        document.body.appendChild(modal);
        modal.classList.remove('hidden');
        modal.style.cssText = 'display:flex !important;visibility:visible !important;position:fixed !important;z-index:99999 !important;';
    }

    const row = passengerIndexState.passengerIndexRows[rowIndex];
    const visa = row.visa;

    if (visa) {
        document.getElementById('visaEditAgent').value = visa.agent || '';
        
        const commissionAgents = {
            "Visa Agent A": ["Commission Agent 1", "Commission Agent 2"],
            "Visa Agent B": ["Commission Agent 3", "Commission Agent 4"]
        };
        const commissionSelect = document.getElementById('visaEditCommissionAgent');
        commissionSelect.innerHTML = '<option value="">Select Commission Agent</option>';
        const agents = commissionAgents[visa.agent] || [];
        agents.forEach(a => {
            const option = document.createElement('option');
            option.value = a;
            option.textContent = a;
            commissionSelect.appendChild(option);
        });
        
        setTimeout(() => {
            document.getElementById('visaEditCommissionAgent').value = visa.commissionAgent || '';
        }, 50);
        
        document.getElementById('visaEditSellingPrice').value = visa.sellingPrice || 0;
        document.getElementById('visaEditVisaNumber').value = visa.visaNumber || '';
        document.getElementById('visaEditAgentCommission').value = visa.agentCommission || 0;
        document.getElementById('visaEditNetVisaCost').value = visa.netVisaCost || 0;
        document.getElementById('visaEditAdditionalCost').value = visa.additionalCost || 0;
        document.getElementById('visaEditRemarks').value = visa.remarks || '';
        
        const statusEl = document.getElementById('visaEditStatus');
        statusEl.textContent = visa.issued ? 'Issued' : 'Pending';
        statusEl.className = `inline-flex items-center px-2 py-1 rounded text-xs font-medium ${visa.issued ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700'}`;
        
        calculateVisaEditFinal();
    }
}

function closeVisaEditModal() {
    editingVisaEditRowIndex = null;
    document.getElementById('visaEditModal')?.classList.add('hidden');
}

function calculateVisaEditFinal() {
    const agentCommission = parseFloat(document.getElementById('visaEditAgentCommission').value) || 0;
    const netVisaCost = parseFloat(document.getElementById('visaEditNetVisaCost').value) || 0;
    const additionalCost = parseFloat(document.getElementById('visaEditAdditionalCost').value) || 0;
    document.getElementById('visaEditFinalCost').value = agentCommission + netVisaCost + additionalCost;
}

function handleVisaEditSubmit(e) {
    e.preventDefault();

    const rowIndex = editingVisaEditRowIndex;
    if (rowIndex === null) return;

    const row = passengerIndexState.passengerIndexRows[rowIndex];
    const visa = row.visa || {};

    visa.agent = document.getElementById('visaEditAgent').value;
    visa.visaNumber = document.getElementById('visaEditVisaNumber').value;
    visa.commissionAgent = document.getElementById('visaEditCommissionAgent').value;
    visa.sellingPrice = parseFloat(document.getElementById('visaEditSellingPrice').value) || 0;
    visa.agentCommission = parseFloat(document.getElementById('visaEditAgentCommission').value) || 0;
    visa.netVisaCost = parseFloat(document.getElementById('visaEditNetVisaCost').value) || 0;
    visa.additionalCost = parseFloat(document.getElementById('visaEditAdditionalCost').value) || 0;
    visa.remarks = document.getElementById('visaEditRemarks').value;
    visa.finalCost = parseFloat(document.getElementById('visaEditFinalCost').value) || 0;

    passengerIndexState.passengerIndexRows[rowIndex].visa = visa;
    savePassengerIndexToStorage();
    closeVisaEditModal();
    renderPassengerIndex();
    showToast('Visa updated successfully');
}

// ============================================
// Invoice Details Functions (Deprecated - now using invoice-details.html page)
// ============================================
// function openInvoiceDetails(bookingId) {
//     const booking = bookingIndexState.bookings.find(b => b.id === bookingId);
//     if (!booking) return;

//     bookingIndexState.selectedBookingId = bookingId;
//     const modal = document.getElementById('invoiceDetailsModal');
//     if (!modal) return;

//     modal.classList.remove('hidden');
//     document.getElementById('invoiceDetailsId').textContent = `ID: ${booking.id}`;
//     document.getElementById('invoiceNo').textContent = booking.invoiceNo;
//     document.getElementById('invoiceDate').textContent = booking.bookingDate;
//     document.getElementById('invoiceCustomer').textContent = booking.customerName;

//     const total = booking.passengers.reduce((sum, p) => sum + (p.total || 0), 0);
//     const paid = booking.payments.reduce((sum, p) => sum + (p.amount || 0), 0);
//     const due = total - paid;

//     document.getElementById('invoiceTotal').textContent = total;
//     document.getElementById('invoicePaid').textContent = paid;
//     document.getElementById('invoiceDue').textContent = due;

//     const statusEl = document.getElementById('invoiceStatus');
//     statusEl.textContent = due > 0 ? 'Due' : 'Paid';
//     statusEl.className = `inline-flex items-center px-2 py-0.5 rounded text-xs font-medium ${due > 0 ? 'bg-yellow-100 text-yellow-700' : 'bg-green-100 text-green-700'}`;

//     const passengersEl = document.getElementById('invoicePassengers');
//     passengersEl.innerHTML = booking.passengers.map((p, pIndex) => {
//         const passengerIndex = passengerIndexState.passengerIndexRows.findIndex(row => row.passengerName === p.name && row.invoiceNo === booking.invoiceNo);
//         return `
//             <div class="flex justify-between items-center p-3 bg-slate-50 rounded-lg">
//                 <div>
//                     <span class="font-medium text-slate-800">${p.name}</span>
//                     <span class="text-slate-500 text-sm ml-2">(${p.passport})</span>
//                 </div>
//                 <div class="flex items-center gap-3">
//                     <span class="text-slate-800 font-medium">${p.total} SAR</span>
//                     ${passengerIndex !== -1 ? `<button onclick="window.location.href='passenger-details.html?index=' + ${passengerIndex} + '&source=invoice'" class="text-xs bg-slate-200 hover:bg-slate-300 text-slate-600 px-2 py-1 rounded">View</button>` : ''}
//                 </div>
//             </div>
//         `;
//     }).join('');

//     const paymentsBody = document.getElementById('invoicePaymentsBody');
//     paymentsBody.innerHTML = booking.payments.map(p => `
//         <tr>
//             <td class="px-3 py-2 text-slate-600">${p.date}</td>
//             <td class="px-3 py-2 text-slate-600">${p.voucherNo}</td>
//             <td class="px-3 py-2 text-slate-600">${p.paymentMethod}</td>
//             <td class="px-3 py-2 text-slate-600">${p.trxId}</td>
//             <td class="px-3 py-2 text-right text-slate-800 font-medium">${p.amount}</td>
//         </tr>
//     `).join('');

//     document.getElementById('invoicePaymentsEmpty').classList.toggle('hidden', booking.payments.length > 0);
// }

// function closeInvoiceDetails() {
//     bookingIndexState.selectedBookingId = null;
//     document.getElementById('invoiceDetailsModal')?.classList.add('hidden');
// }

// ============================================
// Initialize
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    // Set up customer search listener
    if (elements.customerSearch) {
        elements.customerSearch.addEventListener('input', handleCustomerSearch);
        elements.customerSearch.addEventListener('blur', () => {
            setTimeout(hideSuggestions, 200);
        });
    }

    // Fingerprint location change listener for live summary
    if (elements.bookingFingerprintLocation) {
        elements.bookingFingerprintLocation.addEventListener('change', function() {
            updateLiveSummary();
        });
    }

    // Package change listener for live summary
    const bookingPackage = document.getElementById('bookingPackage');
    if (bookingPackage) {
        bookingPackage.addEventListener('change', updateLiveSummary);
    }

    // District change listener for live summary
    const bookingDistrict = document.getElementById('bookingDistrict');
    if (bookingDistrict) {
        bookingDistrict.addEventListener('change', updateLiveSummary);
    }

    // Package change listener for custom option
    if (elements.passengerPackage) {
        elements.passengerPackage.addEventListener('change', function() {
            if (this.value === 'customize') {
                openCustomDurationModal();
            }
        });
    }

    // Checkbox listeners
    if (elements.passengerWithOffer) {
        elements.passengerWithOffer.addEventListener('change', updateCheckboxState);
    }

    // Visa cost listeners
    const visaCostSellingPrice = document.getElementById('visaCostSellingPrice');
    const visaCostAgentCommission = document.getElementById('visaCostAgentCommission');
    const visaCostNetVisaCost = document.getElementById('visaCostNetVisaCost');

    if (visaCostSellingPrice) visaCostSellingPrice.addEventListener('input', updateVisaCostFinal);
    if (visaCostAgentCommission) visaCostAgentCommission.addEventListener('input', updateVisaCostFinal);
    if (visaCostNetVisaCost) visaCostNetVisaCost.addEventListener('input', updateVisaCostFinal);

    // Initialize view
    initializePassengerIndexFromBookings();
    loadPassengerIndexFromStorage();
    loadDocumentsFromStorage();
    addSampleDocuments();
    renderBookingIndex();
    
    // Show index section by default (Booking Index tab active)
    showIndexTab('booking');

    // Check if opening in edit mode or with package
    const params = new URLSearchParams(window.location.search);
    const editIndex = params.get('edit');
    const addPassenger = params.get('addPassenger');
    const packageIndex = params.get('package');

    if (editIndex !== null) {
        openEditBooking(parseInt(editIndex), addPassenger === 'true');
    } else if (packageIndex !== null) {
        // Open new booking form with package prefill
        showForm();
    }
});

// ============================================
// Booking Index Combined Docs Download
// ============================================
function downloadBookingDocs(bookingIndex) {
    const booking = bookingIndexState.bookings[bookingIndex];
    if (!booking) {
        showToast('Booking not found');
        return;
    }

    let allDocs = [];
    booking.passengers.forEach(pax => {
        const paxRow = passengerIndexState.passengerIndexRows.find(r => r.invoiceNo === booking.invoiceNo && r.passport === pax.passport);
        if (paxRow && paxRow.documents && paxRow.documents.length > 0) {
            allDocs.push(...paxRow.documents.map(d => ({ ...d, paxName: pax.name })));
        }
    });

    if (allDocs.length === 0) {
        showToast('No documents found for this booking');
        return;
    }

    if (typeof jspdf === 'undefined') {
        showToast('PDF library not loaded');
        return;
    }

    const { jsPDF } = window.jspdf;
    const pdf = new jsPDF();
    let hasContent = false;

    allDocs.forEach((doc, i) => {
        if (doc.data) {
            hasContent = true;
            if (i > 0) pdf.addPage();

            if (doc.type.startsWith('image/')) {
                try {
                    pdf.addImage(doc.data, 'JPEG', 10, 10, 180, 250);
                } catch (e) {
                    pdf.text(`Image: ${doc.name} (${doc.paxName})`, 10, 20);
                }
            } else {
                pdf.text(`Document: ${doc.name}`, 10, 20);
                pdf.text(`Passenger: ${doc.paxName}`, 10, 30);
                pdf.text('(PDF content cannot be previewed in demo)', 10, 40);
            }
        } else {
            if (i > 0) pdf.addPage();
            pdf.setFontSize(12);
            pdf.text(`Sample Document: ${doc.name}`, 10, 20);
            pdf.setFontSize(10);
            pdf.text(`Passenger: ${doc.paxName}`, 10, 30);
            pdf.text(`Type: ${doc.type}`, 10, 40);
            pdf.text(`Upload Date: ${doc.uploadDate || 'N/A'}`, 10, 50);
            pdf.text('(Sample document for demo)', 10, 60);
            hasContent = true;
        }
    });

    if (hasContent) {
        pdf.save(`${booking.invoiceNo}_all_documents.pdf`);
        showToast('All documents downloaded');
    } else {
        showToast('No downloadable content');
    }
}

function toggleDocsDropdown(bookingIndex) {
    const dropdown = document.getElementById(`docsDropdownMenu-${bookingIndex}`);
    if (dropdown) {
        dropdown.classList.toggle('hidden');
    }
}

function hideDocsDropdown(bookingIndex) {
    const dropdown = document.getElementById(`docsDropdownMenu-${bookingIndex}`);
    if (dropdown) {
        dropdown.classList.add('hidden');
    }
}

function downloadCustomerDocs(bookingIndex) {
    const booking = bookingIndexState.bookings[bookingIndex];
    if (!booking) {
        showToast('Booking not found');
        return;
    }

    const customerDocs = booking.customerDocuments || [];
    if (customerDocs.length === 0) {
        showToast('No customer documents found for this booking');
        return;
    }

    if (typeof jspdf === 'undefined') {
        showToast('PDF library not loaded');
        return;
    }

    const { jsPDF } = window.jspdf;
    const pdf = new jsPDF();
    let hasContent = false;

    customerDocs.forEach((doc, i) => {
        if (doc.data) {
            hasContent = true;
            if (i > 0) pdf.addPage();

            if (doc.type.startsWith('image/')) {
                try {
                    pdf.addImage(doc.data, 'JPEG', 10, 10, 180, 250);
                } catch (e) {
                    pdf.text(`Image: ${doc.name}`, 10, 20);
                }
            } else {
                pdf.text(`Document: ${doc.name}`, 10, 20);
                pdf.text('(PDF content cannot be previewed in demo)', 10, 30);
            }
        } else {
            if (i > 0) pdf.addPage();
            pdf.setFontSize(12);
            pdf.text(`Customer Document: ${doc.name}`, 10, 20);
            pdf.setFontSize(10);
            pdf.text(`Type: ${doc.type}`, 10, 30);
            pdf.text(`Upload Date: ${doc.uploadDate || 'N/A'}`, 10, 40);
            pdf.text('(Sample document for demo)', 10, 50);
            hasContent = true;
        }
    });

    if (hasContent) {
        pdf.save(`${booking.invoiceNo}_customer_documents.pdf`);
        showToast('Customer documents downloaded');
    } else {
        showToast('No downloadable content');
    }
}

function downloadPassengerDocs(bookingIndex) {
    const booking = bookingIndexState.bookings[bookingIndex];
    if (!booking) {
        showToast('Booking not found');
        return;
    }

    let allPaxDocs = [];
    booking.passengers.forEach(pax => {
        const paxRow = passengerIndexState.passengerIndexRows.find(r => r.invoiceNo === booking.invoiceNo && r.passport === pax.passport);
        if (paxRow && paxRow.documents && paxRow.documents.length > 0) {
            allPaxDocs.push(...paxRow.documents.map(d => ({ ...d, paxName: pax.name })));
        }
    });

    if (allPaxDocs.length === 0) {
        showToast('No passenger documents found for this booking');
        return;
    }

    if (typeof jspdf === 'undefined') {
        showToast('PDF library not loaded');
        return;
    }

    const { jsPDF } = window.jspdf;
    const pdf = new jsPDF();
    let hasContent = false;

    allPaxDocs.forEach((doc, i) => {
        if (doc.data) {
            hasContent = true;
            if (i > 0) pdf.addPage();

            if (doc.type.startsWith('image/')) {
                try {
                    pdf.addImage(doc.data, 'JPEG', 10, 10, 180, 250);
                } catch (e) {
                    pdf.text(`Image: ${doc.name} (${doc.paxName})`, 10, 20);
                }
            } else {
                pdf.text(`Document: ${doc.name}`, 10, 20);
                pdf.text(`Passenger: ${doc.paxName}`, 10, 30);
                pdf.text('(PDF content cannot be previewed in demo)', 10, 40);
            }
        } else {
            if (i > 0) pdf.addPage();
            pdf.setFontSize(12);
            pdf.text(`Sample Document: ${doc.name}`, 10, 20);
            pdf.setFontSize(10);
            pdf.text(`Passenger: ${doc.paxName}`, 10, 30);
            pdf.text(`Type: ${doc.type}`, 10, 40);
            pdf.text(`Upload Date: ${doc.uploadDate || 'N/A'}`, 10, 50);
            pdf.text('(Sample document for demo)', 10, 60);
            hasContent = true;
        }
    });

    if (hasContent) {
        pdf.save(`${booking.invoiceNo}_passengers_documents.pdf`);
        showToast('Passenger documents downloaded');
    } else {
        showToast('No downloadable content');
    }
}

// ============================================
// Visa Payment Modal Functions
// ============================================
let editingVisaPaymentRowIndex = null;

function openVisaPaymentModal(rowIndex) {
    editingVisaPaymentRowIndex = rowIndex;
    document.getElementById('visaPaymentModal')?.classList.remove('hidden');
    
    document.getElementById('visaPaymentPayTo').value = '';
    document.getElementById('visaPaymentMethod').value = '';
    document.getElementById('visaPaymentAmount').value = '';
    
    setTimeout(() => document.getElementById('visaPaymentPayTo').focus(), 100);
}

function closeVisaPaymentModal() {
    editingVisaPaymentRowIndex = null;
    document.getElementById('visaPaymentModal')?.classList.add('hidden');
}

function handleVisaPaymentSubmit(e) {
    e.preventDefault();
    
    const rowIndex = editingVisaPaymentRowIndex;
    if (rowIndex === null) return;
    
    const payTo = document.getElementById('visaPaymentPayTo').value;
    const paymentMethod = document.getElementById('visaPaymentMethod').value;
    const amount = parseFloat(document.getElementById('visaPaymentAmount').value) || 0;
    
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
    savePassengerIndexToStorage();
    closeVisaPaymentModal();
    renderPassengerIndex();
    showToast('Payment saved successfully');
}

// Close docs dropdowns when clicking outside
document.addEventListener('click', (e) => {
    document.querySelectorAll('[id^="docsDropdownMenu-"]').forEach(dropdown => {
        if (!dropdown.classList.contains('hidden') && !dropdown.parentElement.contains(e.target)) {
            dropdown.classList.add('hidden');
        }
    });
});
