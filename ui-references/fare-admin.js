// ============================================
// Fare Admin Page JavaScript
// ============================================

const statusOptions = ['None', 'Underprocessing', 'Fingerprint Done', 'Ticket Booking', 'Visa Application', 'Visa Issued', 'Ticket Issued', 'Delivered', 'Hold', 'Cancel', 'Return Done'];
const fingerprintLocations = ['None', 'BMT-DHK', 'BMT-CTG', 'Tabuk with DHK', 'Tabuk with CTG', 'Tabuk with DHK-BMT', 'Dhaka North', 'Dhaka South', 'Chittagong', 'Sylhet'];

// ============================================
// Tab Functions
// ============================================
function showFareAdminTab(tabId) {
    const tabs = ['fareIndexSection', 'passengerIndexSection', 'routeIndexSection'];
    const tabButtons = ['tab-fareIndex', 'tab-passengerIndex', 'tab-routeIndex'];
    
    tabs.forEach(t => {
        const section = document.getElementById(t);
        if (section) {
            section.classList.add('hidden');
        }
    });
    
    tabButtons.forEach(t => {
        const btn = document.getElementById(t);
        if (btn) {
            btn.classList.remove('border-slate-800', 'text-slate-800');
            btn.classList.add('border-transparent', 'text-slate-500');
        }
    });
    
    const activeSection = document.getElementById(tabId);
    if (activeSection) {
        activeSection.classList.remove('hidden');
    }
    
    const tabKey = tabId.replace('Section', '');
    const activeBtn = document.getElementById('tab-' + tabKey);
    if (activeBtn) {
        activeBtn.classList.remove('border-transparent', 'text-slate-500');
        activeBtn.classList.add('border-slate-800', 'text-slate-800');
    }
}

// ============================================
// Route State
// ============================================
const routeAdminState = {
    routeRecords: [
        { id: 1, ticketType: 'Oneway - Inbound', airline: 'Saudia', flightType: 'Direct', from: 'JED', to: 'DAC', returnTo: '', multiInboundFrom: '', multiInboundTo: '', multiOutboundFrom: '', multiOutboundTo: '', transitCity: '', transitTime: '' },
        { id: 2, ticketType: 'Round', airline: 'Emirates', flightType: 'Transit', from: 'DAC', to: 'JED', returnTo: 'DAC', multiInboundFrom: '', multiInboundTo: '', multiOutboundFrom: '', multiOutboundTo: '', transitCity: 'DXB', transitTime: '2h' },
        { id: 3, ticketType: 'Multi City', airline: 'Biman Bangladesh', flightType: 'Direct', from: '', to: '', returnTo: '', multiInboundFrom: 'DAC', multiInboundTo: 'JED', multiOutboundFrom: 'MED', multiOutboundTo: 'DAC', transitCity: '', transitTime: '' },
        { id: 4, ticketType: 'Oneway - Outbound', airline: 'Flynas', flightType: 'Transit', from: 'DAC', to: 'RUH', returnTo: '', multiInboundFrom: '', multiInboundTo: '', multiOutboundFrom: '', multiOutboundTo: '', transitCity: 'DOH', transitTime: '1h 30m' },
        { id: 5, ticketType: 'Round', airline: 'Qatar Airways', flightType: 'Direct', from: 'DAC', to: 'MED', returnTo: 'DAC', multiInboundFrom: '', multiInboundTo: '', multiOutboundFrom: '', multiOutboundTo: '', transitCity: '', transitTime: '' },
    ],
    editingRouteId: null,
    selectedRouteId: null,
    viewingRouteId: null,
};

function openAddRouteModal() {
    routeAdminState.editingRouteId = null;
    document.getElementById('routeModalTitle').textContent = 'Add Route';
    
    document.getElementById('routeTicketType').value = '';
    document.getElementById('routeAirline').value = '';
    document.getElementById('routeFlightType').value = '';
    document.getElementById('routeFrom').value = '';
    document.getElementById('routeTo').value = '';
    document.getElementById('routeRoundFrom').value = '';
    document.getElementById('routeRoundTo').value = '';
    document.getElementById('routeRoundReturnTo').value = '';
    document.getElementById('routeMultiInboundFrom').value = '';
    document.getElementById('routeMultiInboundTo').value = '';
    document.getElementById('routeMultiOutboundFrom').value = '';
    document.getElementById('routeMultiOutboundTo').value = '';
    document.getElementById('routeTransitCity').value = '';
    document.getElementById('routeTransitHours').value = '';
    document.getElementById('routeTransitMinutes').value = '';
    document.getElementById('routeFrom').removeAttribute('required');
    document.getElementById('routeTo').removeAttribute('required');
    document.getElementById('routeOnewayFields').classList.add('hidden');
    document.getElementById('routeRoundFields').classList.add('hidden');
    document.getElementById('routeMultiCityFields').classList.add('hidden');
    document.getElementById('routeTransitFields').classList.add('hidden');
    
    document.getElementById('routeModal').classList.remove('hidden');
}

function openEditRouteModal(routeId) {
    const record = routeAdminState.routeRecords.find(r => r.id === routeId);
    if (!record) return;
    
    routeAdminState.editingRouteId = routeId;
    document.getElementById('routeModalTitle').textContent = 'Edit Route';
    
    document.getElementById('routeTicketType').value = record.ticketType || '';
    document.getElementById('routeAirline').value = record.airline || '';
    document.getElementById('routeFlightType').value = record.flightType || '';
    document.getElementById('routeFrom').value = record.from || '';
    document.getElementById('routeTo').value = record.to || '';
    document.getElementById('routeRoundFrom').value = record.from || '';
    document.getElementById('routeRoundTo').value = record.to || '';
    document.getElementById('routeRoundReturnTo').value = record.returnTo || '';
    document.getElementById('routeMultiInboundFrom').value = record.multiInboundFrom || '';
    document.getElementById('routeMultiInboundTo').value = record.multiInboundTo || '';
    document.getElementById('routeMultiOutboundFrom').value = record.multiOutboundFrom || '';
    document.getElementById('routeMultiOutboundTo').value = record.multiOutboundTo || '';
    document.getElementById('routeTransitCity').value = record.transitCity || '';
    if (record.transitTime) {
        const parts = record.transitTime.split(':');
        document.getElementById('routeTransitHours').value = parts[0] || '';
        document.getElementById('routeTransitMinutes').value = parts[1] || '';
    } else {
        document.getElementById('routeTransitHours').value = '';
        document.getElementById('routeTransitMinutes').value = '';
    }
    
    handleRouteTicketTypeChange();
    handleRouteFlightTypeChange();
    
    document.getElementById('routeModal').classList.remove('hidden');
}

function closeRouteModal() {
    routeAdminState.editingRouteId = null;
    document.getElementById('routeModal').classList.add('hidden');
}

function handleRouteSubmit(e) {
    e.preventDefault();
    
    const ticketType = document.getElementById('routeTicketType').value;
    const flightType = document.getElementById('routeFlightType').value;
    const isOneway = ticketType === 'Oneway - Inbound' || ticketType === 'Oneway - Outbound';
    const isRound = ticketType === 'Round';
    const isMultiCity = ticketType === 'Multi City';
    const isTransit = flightType === 'Transit';
    
    if (isOneway) {
        const from = document.getElementById('routeFrom').value;
        const to = document.getElementById('routeTo').value;
        if (!from || !to) {
            showToast('Please select From and To cities for Oneway ticket type');
            return;
        }
    }
    
    if (isRound) {
        const from = document.getElementById('routeRoundFrom').value;
        const to = document.getElementById('routeRoundTo').value;
        const returnTo = document.getElementById('routeRoundReturnTo').value;
        if (!from || !to || !returnTo) {
            showToast('Please select From, To, and Return To cities for Round ticket type');
            return;
        }
    }
    
    if (isMultiCity) {
        const inboundFrom = document.getElementById('routeMultiInboundFrom').value;
        const inboundTo = document.getElementById('routeMultiInboundTo').value;
        const outboundFrom = document.getElementById('routeMultiOutboundFrom').value;
        const outboundTo = document.getElementById('routeMultiOutboundTo').value;
        if (!inboundFrom || !inboundTo || !outboundFrom || !outboundTo) {
            showToast('Please select all Inbound and Outbound cities for Multi City ticket type');
            return;
        }
    }
    
    if (isTransit) {
        const transitCity = document.getElementById('routeTransitCity').value;
        const transitHours = document.getElementById('routeTransitHours').value;
        const transitMinutes = document.getElementById('routeTransitMinutes').value;
        if (!transitCity || !transitHours || !transitMinutes) {
            showToast('Please select Transit City and enter Transit Time');
            return;
        }
    }
    
    const routeData = {
        ticketType: ticketType,
        airline: document.getElementById('routeAirline').value,
        flightType: flightType,
        from: isOneway ? document.getElementById('routeFrom').value : (isRound ? document.getElementById('routeRoundFrom').value : ''),
        to: isOneway ? document.getElementById('routeTo').value : (isRound ? document.getElementById('routeRoundTo').value : ''),
        returnTo: isRound ? document.getElementById('routeRoundReturnTo').value : '',
        multiInboundFrom: isMultiCity ? document.getElementById('routeMultiInboundFrom').value : '',
        multiInboundTo: isMultiCity ? document.getElementById('routeMultiInboundTo').value : '',
        multiOutboundFrom: isMultiCity ? document.getElementById('routeMultiOutboundFrom').value : '',
        multiOutboundTo: isMultiCity ? document.getElementById('routeMultiOutboundTo').value : '',
        transitCity: isTransit ? document.getElementById('routeTransitCity').value : '',
        transitTime: isTransit ? `${document.getElementById('routeTransitHours').value.padStart(2, '0')}:${document.getElementById('routeTransitMinutes').value.padStart(2, '0')}` : '',
    };
    
    if (routeAdminState.editingRouteId) {
        const index = routeAdminState.routeRecords.findIndex(r => r.id === routeAdminState.editingRouteId);
        if (index !== -1) {
            routeAdminState.routeRecords[index] = { ...routeAdminState.routeRecords[index], ...routeData };
        }
        showToast('Route updated successfully');
    } else {
        routeData.id = Date.now();
        routeAdminState.routeRecords.push(routeData);
        showToast('Route added successfully');
    }
    
    closeRouteModal();
    renderRouteIndex();
}

function handleRouteTicketTypeChange() {
    const ticketType = document.getElementById('routeTicketType').value;
    const isOneway = ticketType === 'Oneway - Inbound' || ticketType === 'Oneway - Outbound';
    const isRound = ticketType === 'Round';
    const isMultiCity = ticketType === 'Multi City';
    
    const newayFields = document.getElementById('routeOnewayFields');
    const roundFields = document.getElementById('routeRoundFields');
    const multiCityFields = document.getElementById('routeMultiCityFields');
    const routeFrom = document.getElementById('routeFrom');
    const routeTo = document.getElementById('routeTo');
    const routeRoundFrom = document.getElementById('routeRoundFrom');
    const routeRoundTo = document.getElementById('routeRoundTo');
    const routeRoundReturnTo = document.getElementById('routeRoundReturnTo');
    const routeMultiInboundFrom = document.getElementById('routeMultiInboundFrom');
    const routeMultiInboundTo = document.getElementById('routeMultiInboundTo');
    const routeMultiOutboundFrom = document.getElementById('routeMultiOutboundFrom');
    const routeMultiOutboundTo = document.getElementById('routeMultiOutboundTo');
    
    newayFields.classList.add('hidden');
    roundFields.classList.add('hidden');
    multiCityFields.classList.add('hidden');
    routeFrom.removeAttribute('required');
    routeTo.removeAttribute('required');
    routeRoundFrom.removeAttribute('required');
    routeRoundTo.removeAttribute('required');
    routeRoundReturnTo.removeAttribute('required');
    routeMultiInboundFrom.removeAttribute('required');
    routeMultiInboundTo.removeAttribute('required');
    routeMultiOutboundFrom.removeAttribute('required');
    routeMultiOutboundTo.removeAttribute('required');
    
    if (isOneway) {
        newayFields.classList.remove('hidden');
        routeFrom.setAttribute('required', 'required');
        routeTo.setAttribute('required', 'required');
    } else if (isRound) {
        roundFields.classList.remove('hidden');
        routeRoundFrom.setAttribute('required', 'required');
        routeRoundTo.setAttribute('required', 'required');
        routeRoundReturnTo.setAttribute('required', 'required');
    } else if (isMultiCity) {
        multiCityFields.classList.remove('hidden');
        routeMultiInboundFrom.setAttribute('required', 'required');
        routeMultiInboundTo.setAttribute('required', 'required');
        routeMultiOutboundFrom.setAttribute('required', 'required');
        routeMultiOutboundTo.setAttribute('required', 'required');
    }
}

function handleRouteRoundFromChange() {
    const fromValue = document.getElementById('routeRoundFrom').value;
    document.getElementById('routeRoundReturnTo').value = fromValue;
}

function handleRouteFlightTypeChange() {
    const flightType = document.getElementById('routeFlightType').value;
    const isTransit = flightType === 'Transit';
    
    const transitFields = document.getElementById('routeTransitFields');
    const transitCity = document.getElementById('routeTransitCity');
    const transitHours = document.getElementById('routeTransitHours');
    const transitMinutes = document.getElementById('routeTransitMinutes');
    
    if (isTransit) {
        transitFields.classList.remove('hidden');
        transitCity.setAttribute('required', 'required');
        transitHours.setAttribute('required', 'required');
        transitMinutes.setAttribute('required', 'required');
    } else {
        transitFields.classList.add('hidden');
        transitCity.removeAttribute('required');
        transitHours.removeAttribute('required');
        transitMinutes.removeAttribute('required');
        transitCity.value = '';
        transitHours.value = '';
        transitMinutes.value = '';
    }
}

function formatRouteDisplay(record) {
    switch (record.ticketType) {
        case 'Oneway - Inbound':
        case 'Oneway - Outbound':
            return `${record.from}-${record.to}`;
        case 'Round':
            return `${record.from}-${record.to}-${record.returnTo}`;
        case 'Multi City':
            return `${record.multiInboundFrom}-${record.multiInboundTo} | ${record.multiOutboundFrom}-${record.multiOutboundTo}`;
        default:
            return '-';
    }
}

function formatTransitDisplay(record) {
    if (record.flightType === 'Transit' && record.transitCity) {
        return `${record.transitCity} - ${record.transitTime}`;
    }
    return '-';
}

function renderRouteIndex() {
    const tableBody = document.getElementById('routeIndexTableBody');
    const emptyMsg = document.getElementById('routeIndexEmpty');
    
    if (!tableBody) return;
    
    const records = routeAdminState.routeRecords;
    
    if (records.length > 0) {
        emptyMsg?.classList.add('hidden');
    } else {
        emptyMsg?.classList.remove('hidden');
    }
    
    tableBody.innerHTML = '';
    
    records.forEach((record) => {
        const tr = document.createElement('tr');
        tr.className = 'hover:bg-slate-50';
        tr.innerHTML = `
            <td class="px-3 py-2 text-slate-600">${record.ticketType}</td>
            <td class="px-3 py-2 text-slate-800 font-medium">${record.airline}</td>
            <td class="px-3 py-2">
                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium ${record.flightType === 'Transit' ? 'bg-yellow-100 text-yellow-700' : 'bg-slate-100 text-slate-600'}">
                    ${record.flightType}
                </span>
            </td>
            <td class="px-3 py-2 text-slate-800 font-medium">${formatRouteDisplay(record)}</td>
            <td class="px-3 py-2 text-slate-600">${formatTransitDisplay(record)}</td>
            <td class="px-3 py-2">
                <div class="flex gap-2">
                    <button onclick="openViewRouteModal(${record.id})" class="text-xs bg-slate-100 hover:bg-slate-200 text-slate-600 px-2 py-1 rounded">View</button>
                    <button onclick="openEditRouteModal(${record.id})" class="text-xs bg-slate-100 hover:bg-slate-200 text-slate-600 px-2 py-1 rounded">Edit</button>
                    <button onclick="openDeleteRouteModal(${record.id})" class="text-xs bg-red-100 hover:bg-red-200 text-red-600 px-2 py-1 rounded">Delete</button>
                </div>
            </td>
        `;
        tableBody.appendChild(tr);
    });
}

function openViewRouteModal(routeId) {
    const record = routeAdminState.routeRecords.find(r => r.id === routeId);
    if (!record) return;
    
    routeAdminState.viewingRouteId = routeId;
    const content = document.getElementById('viewRouteContent');
    content.innerHTML = `
        <div class="grid grid-cols-2 gap-2">
            <div><span class="text-slate-500">Ticket Type:</span> <span class="text-slate-800">${record.ticketType}</span></div>
            <div><span class="text-slate-500">Airline:</span> <span class="text-slate-800">${record.airline}</span></div>
            <div><span class="text-slate-500">Flight Type:</span> <span class="text-slate-800">${record.flightType}</span></div>
            <div><span class="text-slate-500">Route:</span> <span class="text-slate-800 font-medium">${formatRouteDisplay(record)}</span></div>
            ${record.flightType === 'Transit' ? `<div><span class="text-slate-500">Transit:</span> <span class="text-slate-800">${record.transitCity} - ${record.transitTime}</span></div>` : ''}
        </div>
    `;
    document.getElementById('viewRouteModal').classList.remove('hidden');
}

function closeViewRouteModal() {
    routeAdminState.viewingRouteId = null;
    document.getElementById('viewRouteModal').classList.add('hidden');
}

function openDeleteRouteModal(routeId) {
    const record = routeAdminState.routeRecords.find(r => r.id === routeId);
    if (!record) return;
    
    routeAdminState.selectedRouteId = routeId;
    document.getElementById('deleteRouteInfo').innerHTML = `
        <p><strong>${record.ticketType}</strong></p>
        <p class="text-slate-500">${record.airline} - ${formatRouteDisplay(record)}</p>
    `;
    document.getElementById('deleteRouteModal').classList.remove('hidden');
}

function closeDeleteRouteModal() {
    routeAdminState.selectedRouteId = null;
    document.getElementById('deleteRouteModal').classList.add('hidden');
}

function confirmDeleteRoute() {
    if (routeAdminState.selectedRouteId) {
        routeAdminState.routeRecords = routeAdminState.routeRecords.filter(r => r.id !== routeAdminState.selectedRouteId);
        showToast('Route deleted successfully');
        closeDeleteRouteModal();
        renderRouteIndex();
    }
}

const passengerIndexState = {
    passengerIndexRows: [],
};

let editingPassengerRowIndex = null;

const groupTickets = [
    { id: 'GRP001', pnr: 'BMUGRP001', date: '2026-05-15', route: 'DAC-JED-DAC', remainingSeats: 8 },
    { id: 'GRP002', pnr: 'BMUGRP002', date: '2026-05-20', route: 'DAC-RUH-DAC', remainingSeats: 12 },
    { id: 'GRP003', pnr: 'BMUGRP003', date: '2026-06-01', route: 'DAC-MED-DAC', remainingSeats: 5 },
];

function savePassengerIndexToStorage() {
    try {
        localStorage.setItem('bm_passengerIndexRows', JSON.stringify(passengerIndexState.passengerIndexRows));
    } catch (e) {
        console.error('Failed to save passenger index:', e);
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
                    airline: 'Saudia',
                    travelClass: 'Economy',
                    passengerType: 'Adult',
                    status: 'None',
                    due: '0 SAR',
                    ticketStatus: 'Pending',
                    requiredFlightDate: '2026-05-15',
                    actualFlightDate: '2026-05-15',
                    fingerprintLocation: 'None',
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
                    airline: 'Saudia',
                    travelClass: 'Economy',
                    passengerType: 'Adult',
                    status: 'Visa Application',
                    due: '500 SAR',
                    ticketStatus: 'Issued',
                    ticketFare: { date: '2026-04-12', sellingFare: 2200, netFare: 2000 },
                    requiredFlightDate: '2026-05-20',
                    actualFlightDate: '-',
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
                    airline: 'Biman Bangladesh',
                    travelClass: 'Economy',
                    passengerType: 'Adult',
                    status: 'Visa Issued',
                    due: '1500 SAR',
                    ticketStatus: 'Re-Issued',
                    ticketFare: { date: '2026-04-15', sellingFare: 2800, netFare: 2500 },
                    requiredFlightDate: '2026-06-01',
                    actualFlightDate: '-',
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
                    airline: 'Emirates',
                    travelClass: 'Economy',
                    passengerType: 'Child',
                    status: 'Delivered',
                    due: '0 SAR',
                    ticketStatus: 'Refunded',
                    requiredFlightDate: '2026-06-10',
                    actualFlightDate: '2026-06-10',
                    visa: { sellingPrice: 500, finalCost: 450 },
                    fingerprintLocation: 'Dhaka North',
                    documents: [],
                    passengerData: null
                }
            ];
}

function renderPassengerIndex() {
    const tableBody = document.getElementById('passengerIndexTableBody');
    const emptyMsg = document.getElementById('passengerIndexEmpty');
    
    if (!tableBody) return;
    
    const rows = passengerIndexState.passengerIndexRows;
    
    if (rows.length > 0) {
        emptyMsg?.classList.add('hidden');
    } else {
        emptyMsg?.classList.remove('hidden');
    }
    
    tableBody.innerHTML = '';
    
    const invoiceCounts = {};
    rows.forEach(row => {
        invoiceCounts[row.invoiceNo] = (invoiceCounts[row.invoiceNo] || 0) + 1;
    });
    
    let prevInvoiceNo = null;
    rows.forEach((row, index) => {
        const isFirstInInvoice = prevInvoiceNo !== row.invoiceNo;
        const paxQty = isFirstInInvoice ? invoiceCounts[row.invoiceNo] : '';
        prevInvoiceNo = row.invoiceNo;

        const tr = document.createElement('tr');
        tr.className = 'hover:bg-slate-50';
        tr.innerHTML = `
            <td class="px-3 py-2 text-slate-600">${row.date}</td>
            <td class="px-3 py-2 text-slate-800 font-medium">${row.invoiceNo}</td>
            <td class="px-3 py-2 text-slate-800 font-medium">${paxQty}</td>
            <td class="px-3 py-2 text-slate-600">${row.guardianName}</td>
            <td class="px-3 py-2 text-slate-600">${row.mobileNo}</td>
            <td class="px-3 py-2 text-slate-800">${row.passengerName}</td>
            <td class="px-3 py-2 text-slate-600">${row.passport}</td>
            <td class="px-3 py-2 text-slate-600">${row.route}</td>
            <td class="px-3 py-2">
                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-slate-100 text-slate-600">
                    ${row.status || 'None'}
                </span>
            </td>
            <td class="px-3 py-2 text-slate-600">${row.due}</td>
            <td class="px-3 py-2">
                ${(row.ticketStatus === 'Issued' || row.ticketStatus === 'Re-Issued') && row.ticketFare 
                    ? `<div class="flex items-center gap-1"><span class="text-slate-800 font-medium">${row.ticketFare.sellingFare || row.ticketFare.netFare}</span><button onclick="openTicketFareModal(${index})" class="text-xs text-slate-500 hover:text-slate-700">Edit</button></div>`
                    : row.ticketStatus === 'Refunded'
                    ? `<div class="flex items-center gap-1"><span class="text-slate-400">-</span><button onclick="openTicketFareModal(${index})" class="text-xs text-slate-500 hover:text-slate-700">Issue</button></div>`
                    : row.ticketFare 
                    ? `<div class="flex items-center gap-1"><span class="text-slate-800 font-medium">${row.ticketFare.sellingFare || row.ticketFare.netFare}</span><button onclick="openTicketFareModal(${index})" class="text-xs text-slate-500 hover:text-slate-700">Issue</button></div>`
                    : `<div class="flex items-center gap-1"><span class="text-slate-400">-</span><button onclick="openTicketFareModal(${index})" class="text-xs text-slate-500 hover:text-slate-700">Issue</button></div>`
                }
            </td>
            <td class="px-3 py-2">
                ${row.ticketStatus === 'Refunded' 
                    ? `<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-700">Refunded</span>`
                    : row.ticketStatus === 'Re-Issued'
                    ? `<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-700">Re-Issued</span>`
                    : row.ticketStatus === 'Issued'
                    ? `<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-700">Issued</span>`
                    : `<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-700">Pending</span>`
                }
            </td>
            <td class="px-3 py-2">
                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium ${row.visa?.sellingPrice > 0 ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700'}">
                    ${row.visa?.sellingPrice > 0 ? 'Issued' : 'Pending'}
                </span>
            </td>
            <td class="px-3 py-2 text-slate-600">${row.requiredFlightDate || '-'}</td>
            <td class="px-3 py-2 text-slate-600">${row.actualFlightDate || '-'}</td>
            <td class="px-3 py-2">
                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium ${row.fingerprintLocation !== 'None' && row.fingerprintLocation !== undefined ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700'}">
                    ${row.fingerprintLocation !== 'None' && row.fingerprintLocation !== undefined ? 'Done' : 'Pending'}
                </span>
            </td>
            <td class="px-3 py-2">
                <button onclick="window.location.href='fare-passenger-details.html?index=${index}'" class="text-xs bg-slate-100 hover:bg-slate-200 text-slate-600 px-2 py-1 rounded">View</button>
            </td>
        `;
        tableBody.appendChild(tr);
    });
}

const fareAdminState = {
    fareRecords: [
        { id: 1, date: '2026-03-15', airline: 'Saudia', travelClass: 'Economy', route: 'DAC-JED-DAC', passengerType: 'Adult', netFare: 2500, sellingFare: 2500, ticketType: 'regular' },
        { id: 2, date: '2026-03-10', airline: 'Biman Bangladesh', travelClass: 'Economy', route: 'DAC-JED-DAC', passengerType: 'Adult', netFare: 2100, sellingFare: 2100, ticketType: 'regular' },
        { id: 3, date: '2026-03-12', airline: 'Emirates', travelClass: 'Business', route: 'DAC-JED-DAC', passengerType: 'Adult', netFare: 9500, sellingFare: 9500, ticketType: 'offer', offerPrice: 8500, effectiveFrom: '2026-03-01', effectiveTo: '2026-05-31' },
        { id: 4, date: '2026-04-01', airline: 'Saudia', travelClass: 'Economy', route: 'DAC-JED-DAC', passengerType: 'Adult', netFare: 2000, sellingFare: 2200, ticketType: 'group', groupTicketData: { count: 15, date: '2026-05-10', pnr: 'SAGRP001' } },
        { id: 5, date: '2026-04-05', airline: 'Flynas', travelClass: 'Economy', route: 'DAC-RUH-DAC', passengerType: 'Adult', netFare: 1800, sellingFare: 2000, ticketType: 'group', groupTicketData: { count: 20, date: '2026-05-15', pnr: 'FNGP002' } },
        { id: 6, date: '2026-04-10', airline: 'Biman Bangladesh', travelClass: 'Economy', route: 'DAC-MED-DAC', passengerType: 'Child', netFare: 1500, sellingFare: 1700, ticketType: 'group', groupTicketData: { count: 8, date: '2026-05-20', pnr: 'BMGRP003' } },
    ],
    selectedFareId: null,
    editingFareId: null,
    viewingFareId: null,
    isAddFareModalOpen: false,
    isEditFareModalOpen: false,
    isViewFareModalOpen: false,
    isDeleteConfirmOpen: false,
};

const elements = {
    fareAdminSection: document.getElementById('fareAdminSection'),
    fareIndexTableBody: document.getElementById('fareIndexTableBody'),
    fareIndexEmpty: document.getElementById('fareIndexEmpty'),
    fareModal: document.getElementById('fareModal'),
    fareModalTitle: document.getElementById('fareModalTitle'),
    fareCoreFields: document.getElementById('fareCoreFields'),
    fareDate: document.getElementById('fareDate'),
    fareRouteType: document.getElementById('fareRouteType'),
    fareFlightType: document.getElementById('fareFlightType'),
    fareEffectiveFrom: document.getElementById('fareEffectiveFrom'),
    fareEffectiveTo: document.getElementById('fareEffectiveTo'),
    fareAirline: document.getElementById('fareAirline'),
    fareClass: document.getElementById('fareClass'),
    fareRoute: document.getElementById('fareRoute'),
    // farePassengerType: document.getElementById('farePassengerType'),
    fareNetFare: document.getElementById('fareNetFare'),
    fareSellingFare: document.getElementById('fareSellingFare'),
    fareWithOffer: document.getElementById('fareWithOffer'),
    fareGroupTicket: document.getElementById('fareGroupTicket'),
    fareOfferFields: document.getElementById('fareOfferFields'),
    fareOfferPrice: document.getElementById('fareOfferPrice'),
    fareChildFare: document.getElementById('fareChildFare'),
    fareInfantFare: document.getElementById('fareInfantFare'),
    groupTicketForm: document.getElementById('groupTicketForm'),
    groupTicketCount: document.getElementById('groupTicketCount'),
    groupTicketDate: document.getElementById('groupTicketDate') || { value: '' },
    groupTicketPNR: document.getElementById('groupTicketPNR'),
    groupTicketInboundDate: document.getElementById('groupTicketInboundDate'),
    groupTicketOutboundDate: document.getElementById('groupTicketOutboundDate'),
    // groupTicketNumber: document.getElementById('groupTicketNumber'),
    viewFareModal: document.getElementById('viewFareModal'),
    viewFareContent: document.getElementById('viewFareContent'),
    deleteFareModal: document.getElementById('deleteFareModal'),
    deleteFareInfo: document.getElementById('deleteFareInfo'),
    ticketFareModal: document.getElementById('ticketFareModal'),
    ticketFareForm: document.getElementById('ticketFareForm'),
    ticketFareType: document.getElementById('ticketFareType'),
    ticketFareRouteType: document.getElementById('ticketFareRouteType'),
    ticketFareFlightType: document.getElementById('ticketFareFlightType'),
    ticketFareGroupTicket: document.getElementById('ticketFareGroupTicket'),
    groupTicketSection: document.getElementById('groupTicketSection'),
    ticketFareUpDate: document.getElementById('ticketFareUpDate'),
    ticketFareDownDate: document.getElementById('ticketFareDownDate'),
    ticketFarePNR: document.getElementById('ticketFarePNR'),
    ticketFareTicketNumber: document.getElementById('ticketFareTicketNumber'),
    ticketFareDate: document.getElementById('ticketFareDate'),
    ticketFareAgent: document.getElementById('ticketFareAgent'),
    ticketFareRoute: document.getElementById('ticketFareRoute'),
    ticketFareAirline: document.getElementById('ticketFareAirline'),
    ticketFareClass: document.getElementById('ticketFareClass'),
    ticketFarePassengerType: document.getElementById('ticketFarePassengerType'),
    ticketFareSellingFare: document.getElementById('ticketFareSellingFare'),
    ticketFareNet: document.getElementById('ticketFareNet'),
    ticketFareWithOffer: document.getElementById('ticketFareWithOffer'),
    ticketFareRefundable: document.getElementById('ticketFareRefundable'),
    ticketFareInboundAdult: document.getElementById('ticketFareInboundAdult'),
    ticketFareInboundChild: document.getElementById('ticketFareInboundChild'),
    ticketFareInboundInfant: document.getElementById('ticketFareInboundInfant'),
    ticketFareOutboundAdult: document.getElementById('ticketFareOutboundAdult'),
    ticketFareOutboundChild: document.getElementById('ticketFareOutboundChild'),
    ticketFareOutboundInfant: document.getElementById('ticketFareOutboundInfant'),
    ticketFareNonRefundable: document.getElementById('ticketFareNonRefundable'),
    ticketFareNonExchangeable: document.getElementById('ticketFareNonExchangeable'),
};

function renderFareIndex() {
    const records = fareAdminState.fareRecords;
    
    if (records.length > 0) {
        elements.fareIndexEmpty?.classList.add('hidden');
    } else {
        elements.fareIndexEmpty?.classList.remove('hidden');
    }

    if (!elements.fareIndexTableBody) return;
    
    elements.fareIndexTableBody.innerHTML = '';
    records.forEach((record, index) => {
        const tr = document.createElement('tr');
        tr.className = 'hover:bg-slate-50';
        tr.innerHTML = `
            <td class="px-3 py-2 text-slate-600">${record.date}</td>
            <td class="px-3 py-2 text-slate-800 font-medium">${record.airline}</td>
            <td class="px-3 py-2 text-slate-600">${record.travelClass}</td>
            <td class="px-3 py-2 text-slate-600">${record.route}</td>
            <!-- <td class="px-3 py-2 text-slate-600">${record.passengerType || '-'}</td> -->
            <td class="px-3 py-2 text-slate-800 font-medium">${record.netFare || 0}</td>
            <td class="px-3 py-2 text-slate-800 font-medium">${record.sellingFare || 0}</td>
            <td class="px-3 py-2">
                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium ${record.ticketType === 'group' ? 'bg-purple-100 text-purple-700' : record.ticketType === 'offer' ? 'bg-green-100 text-green-700' : 'bg-slate-100 text-slate-600'}">
                    ${record.ticketType === 'group' ? 'Group' : record.ticketType === 'offer' ? 'Offer' : 'Regular'}
                </span>
            </td>
            <td class="px-3 py-2 text-slate-600">${record.offerPrice || '-'}</td>
            <td class="px-3 py-2 text-slate-600">${record.effectiveFrom || '-'}</td>
            <td class="px-3 py-2 text-slate-600">${record.effectiveTo || '-'}</td>
            <td class="px-3 py-2 text-slate-600">${record.ticketType === 'group' && record.groupTicketData?.pnr ? record.groupTicketData.pnr : '-'}</td>
            <td class="px-3 py-2 text-slate-600">${record.ticketType === 'group' && record.groupTicketData?.count ? record.groupTicketData.count : '-'}</td>
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
    
    if (elements.fareCoreFields) {
        elements.fareCoreFields.querySelectorAll('input, select').forEach(el => el.disabled = false);
    }
    
    elements.fareDate.value = new Date().toISOString().split('T')[0];
    elements.fareRouteType.value = '';
    elements.fareFlightType.value = '';
    elements.fareEffectiveFrom.value = '';
    elements.fareEffectiveTo.value = '';
    elements.fareAirline.value = '';
    elements.fareClass.value = '';
    elements.fareRoute.value = '';
    // elements.farePassengerType.value = '';
    elements.fareNetFare.value = '';
    elements.fareSellingFare.value = '';
    elements.fareWithOffer.checked = false;
    elements.fareGroupTicket.checked = false;
    elements.fareOfferPrice.value = '';
    elements.fareChildFare.value = '';
    elements.fareInfantFare.value = '';
    elements.groupTicketCount.value = '';
    elements.groupTicketDate.value = '';
    elements.groupTicketPNR.value = '';
    elements.groupTicketInboundDate.value = '';
    elements.groupTicketOutboundDate.value = '';
    // elements.groupTicketNumber.value = '';
    
    updateFareOfferFields();
    updateGroupTicketForm();
    updateGroupTicketDateFields();
    elements.fareModal.classList.remove('hidden');
}

function openEditFareModal(fareId) {
    const record = fareAdminState.fareRecords.find(f => f.id === fareId);
    if (!record) return;
    
    fareAdminState.editingFareId = fareId;
    elements.fareModalTitle.textContent = 'Edit Fare';
    
    elements.fareDate.disabled = true;
    elements.fareAirline.disabled = true;
    elements.fareClass.disabled = true;
    elements.fareRoute.disabled = true;
    // elements.farePassengerType.disabled = true;
    
    elements.fareDate.value = record.date;
    elements.fareRouteType.value = record.routeType || '';
    elements.fareFlightType.value = record.flightType || '';
    elements.fareEffectiveFrom.value = record.effectiveFrom || '';
    elements.fareEffectiveTo.value = record.effectiveTo || '';
    elements.fareAirline.value = record.airline;
    elements.fareClass.value = record.travelClass;
    elements.fareRoute.value = record.route;
    // elements.farePassengerType.value = record.passengerType || '';
    elements.fareNetFare.value = record.netFare || '';
    elements.fareSellingFare.value = record.sellingFare || '';
    elements.fareWithOffer.checked = record.withOffer || false;
    elements.fareGroupTicket.checked = record.groupTicket || false;
    elements.fareOfferPrice.value = record.offerPrice || '';
    elements.fareChildFare.value = record.childFare || '';
    elements.fareInfantFare.value = record.infantFare || '';
    elements.groupTicketCount.value = record.groupTicketData?.count || '';
    if (elements.groupTicketDate) elements.groupTicketDate.value = record.groupTicketData?.date || '';
    elements.groupTicketPNR.value = record.groupTicketData?.pnr || '';
    elements.groupTicketInboundDate.value = record.groupTicketData?.inboundDate || '';
    elements.groupTicketOutboundDate.value = record.groupTicketData?.outboundDate || '';
    // elements.groupTicketNumber.value = record.groupTicketData?.ticketNumber || '';
    
    updateFareOfferFields();
    updateGroupTicketForm();
    updateGroupTicketDateFields();
    elements.fareModal.classList.remove('hidden');
}

function closeFareModal() {
    fareAdminState.editingFareId = null;
    elements.fareModal.classList.add('hidden');
    elements.fareDate.disabled = false;
    elements.fareAirline.disabled = false;
    elements.fareClass.disabled = false;
    elements.fareRoute.disabled = false;
    // elements.farePassengerType.disabled = false;
}

function updateFareOfferFields() {
    if (elements.fareWithOffer?.checked) {
        elements.fareOfferFields?.classList.remove('hidden');
        // Uncheck Group Ticket if With Offer is selected
        if (elements.fareGroupTicket?.checked) {
            elements.fareGroupTicket.checked = false;
            elements.groupTicketForm?.classList.add('hidden');
            // Clear Group Ticket fields
            elements.groupTicketCount.value = '';
if (elements.groupTicketDate) elements.groupTicketDate.value = '';
            elements.groupTicketPNR.value = '';
        }
    } else {
        elements.fareOfferFields?.classList.add('hidden');
    }
}

function updateGroupTicketForm() {
    if (elements.fareGroupTicket?.checked) {
        elements.groupTicketForm?.classList.remove('hidden');
        // Uncheck With Offer if Group Ticket is selected
        if (elements.fareWithOffer?.checked) {
            elements.fareWithOffer.checked = false;
            elements.fareOfferFields?.classList.add('hidden');
            // Clear Offer fields
            elements.fareOfferPrice.value = '';
        }
    } else {
        elements.groupTicketForm?.classList.add('hidden');
    }
    updateGroupTicketDateFields();
}

function updateGroupTicketDateFields() {
    const routeType = elements.fareRouteType?.value;
    const isGroupTicket = elements.fareGroupTicket?.checked;
    
    const inboundDiv = document.getElementById('groupTicketInboundField');
    const outboundDiv = document.getElementById('groupTicketOutboundField');
    const baggageSection = document.getElementById('baggageInfoSection');
    
    if (!inboundDiv || !outboundDiv) return;
    
    // Always hide both first
    inboundDiv.classList.add('hidden');
    outboundDiv.classList.add('hidden');
    
    if (!isGroupTicket) {
        if (baggageSection) baggageSection.classList.add('hidden');
        return;
    }
    
    switch(routeType) {
        case 'One Way-Inbound':
            inboundDiv.classList.remove('hidden');
            if (baggageSection) baggageSection.classList.add('hidden');
            break;
        case 'One Way-Outbound':
            outboundDiv.classList.remove('hidden');
            if (baggageSection) baggageSection.classList.add('hidden');
            break;
        case 'Multi City':
        case 'Round':
            inboundDiv.classList.remove('hidden');
            outboundDiv.classList.remove('hidden');
            if (baggageSection) baggageSection.classList.remove('hidden');
            break;
        default:
            break;
    }
}

function handleRouteTypeChange() {
    const routeType = elements.fareRouteType?.value;
    const baggageSection = document.getElementById('baggageInfoSection');
    const inboundSection = document.getElementById('inboundBaggageSection');
    const outboundSection = document.getElementById('outboundBaggageSection');
    
    if (!baggageSection) return;
    
    // Hide all first
    baggageSection.classList.add('hidden');
    if (inboundSection) inboundSection.classList.add('hidden');
    if (outboundSection) outboundSection.classList.add('hidden');
    
    switch(routeType) {
        case 'One Way-Inbound':
            baggageSection.classList.remove('hidden');
            if (inboundSection) inboundSection.classList.remove('hidden');
            break;
        case 'One Way-Outbound':
            baggageSection.classList.remove('hidden');
            if (outboundSection) outboundSection.classList.remove('hidden');
            break;
        case 'Round':
        case 'Multi City':
            baggageSection.classList.remove('hidden');
            if (inboundSection) inboundSection.classList.remove('hidden');
            if (outboundSection) outboundSection.classList.remove('hidden');
            break;
    }
}

function handleFareSubmit(e) {
    e.preventDefault();

    const groupTicketData = elements.fareGroupTicket.checked ? {
        count: parseInt(elements.groupTicketCount.value) || 0,
        date: elements.groupTicketDate?.value || null,
        pnr: elements.groupTicketPNR.value || null,
        inboundDate: elements.groupTicketInboundDate?.value || null,
        outboundDate: elements.groupTicketOutboundDate?.value || null,
        // ticketNumber: elements.groupTicketNumber.value || null,
    } : null;

    const fareData = {
        date: elements.fareDate.value,
        routeType: elements.fareRouteType.value,
        flightType: elements.fareFlightType.value,
        effectiveFrom: elements.fareEffectiveFrom.value,
        effectiveTo: elements.fareEffectiveTo.value,
        airline: elements.fareAirline.value,
        travelClass: elements.fareClass.value,
        route: elements.fareRoute.value,
        // passengerType: elements.farePassengerType.value,
        netFare: parseFloat(elements.fareNetFare.value) || 0,
        sellingFare: parseFloat(elements.fareSellingFare.value) || 0,
        withOffer: elements.fareWithOffer.checked,
        groupTicket: elements.fareGroupTicket.checked || false,
        groupTicketData: groupTicketData,
        offerPrice: elements.fareWithOffer.checked ? (parseFloat(elements.fareOfferPrice.value) || 0) : null,
        childFare: parseFloat(elements.fareChildFare.value) || 0,
        infantFare: parseFloat(elements.fareInfantFare.value) || 0,
    };

    if (fareAdminState.editingFareId) {
        const index = fareAdminState.fareRecords.findIndex(f => f.id === fareAdminState.editingFareId);
        if (index !== -1) {
            fareAdminState.fareRecords[index] = { ...fareAdminState.fareRecords[index], ...fareData };
        }
        showToast('Fare updated successfully');
    } else {
        fareData.id = Date.now();
        fareAdminState.fareRecords.push(fareData);
        showToast('Fare added successfully');
    }

    closeFareModal();
    renderFareIndex();
}

function openViewFareModal(fareId) {
    const record = fareAdminState.fareRecords.find(f => f.id === fareId);
    if (!record) return;

    fareAdminState.viewingFareId = fareId;
    elements.viewFareContent.innerHTML = `
        <div class="grid grid-cols-2 gap-2">
            <div><span class="text-slate-500">Date:</span> <span class="text-slate-800">${record.date}</span></div>
            <div><span class="text-slate-500">Airline:</span> <span class="text-slate-800">${record.airline}</span></div>
            <div><span class="text-slate-500">Class:</span> <span class="text-slate-800">${record.travelClass}</span></div>
            <div><span class="text-slate-500">Route:</span> <span class="text-slate-800">${record.route}</span></div>
            <div><span class="text-slate-500">Passenger Type:</span> <span class="text-slate-800">${record.passengerType || '-'}</span></div>
            <div><span class="text-slate-500">Net Fare:</span> <span class="text-slate-800 font-medium">${record.netFare} SAR</span></div>
            <div><span class="text-slate-500">Selling Fare:</span> <span class="text-slate-800 font-medium">${record.sellingFare} SAR</span></div>
            <div><span class="text-slate-500">With Offer:</span> <span class="text-slate-800">${record.withOffer ? 'Yes' : 'No'}</span></div>
            ${record.withOffer ? `<div><span class="text-slate-500">Offer Price:</span> <span class="text-slate-800 font-medium">${record.offerPrice} SAR</span></div>` : ''}
            ${record.withOffer ? `<div><span class="text-slate-500">Effective From:</span> <span class="text-slate-800">${record.effectiveFrom || '-'}</span></div>` : ''}
            ${record.withOffer ? `<div><span class="text-slate-500">Effective To:</span> <span class="text-slate-800">${record.effectiveTo || '-'}</span></div>` : ''}
            ${record.groupTicket ? `<div><span class="text-slate-500">Group Ticket:</span> <span class="text-slate-800">Yes (${record.groupTicketData?.count || 0} tickets)</span></div>` : ''}
            ${record.groupTicket && record.groupTicketData?.date ? `<div><span class="text-slate-500">Date:</span> <span class="text-slate-800">${record.groupTicketData.date}</span></div>` : ''}
            ${record.groupTicket && record.groupTicketData?.pnr ? `<div><span class="text-slate-500">PNR:</span> <span class="text-slate-800">${record.groupTicketData.pnr}</span></div>` : ''}
            // ${record.groupTicket && record.groupTicketData?.ticketNumber ? `<div><span class="text-slate-500">Ticket Number:</span> <span class="text-slate-800">${record.groupTicketData.ticketNumber}</span></div>` : ''}
        </div>
    `;
    elements.viewFareModal.classList.remove('hidden');
}

function closeViewFareModal() {
    fareAdminState.viewingFareId = null;
    elements.viewFareModal.classList.add('hidden');
}

function openDeleteFareModal(fareId) {
    const record = fareAdminState.fareRecords.find(f => f.id === fareId);
    if (!record) return;

    fareAdminState.selectedFareId = fareId;
    elements.deleteFareInfo.innerHTML = `
        <p><strong>${record.airline}</strong> - ${record.travelClass}</p>
        <p class="text-slate-500">${record.route} (${record.passengerType || 'All Types'})</p>
        <p class="text-slate-500">Net: ${record.netFare} SAR | Selling: ${record.sellingFare} SAR</p>
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
        showToast('Fare deleted successfully');
        closeDeleteFareModal();
        renderFareIndex();
    }
}

// ============================================
// Ticket Fare Modal Functions
// ============================================
function openTicketFareModal(rowIndex) {
    editingPassengerRowIndex = rowIndex;
    const row = passengerIndexState.passengerIndexRows[rowIndex];
    if (!row) return;
    
    const isAlreadyIssued = row.ticketStatus === 'Issued' || row.ticketStatus === 'Re-Issued';
    document.getElementById('ticketFareModalTitle').textContent = isAlreadyIssued ? 'Edit Ticket' : 'Issue Ticket';
    
    elements.ticketFareModal?.classList.remove('hidden');
    
    elements.ticketFareRoute.value = row.route || '';
    elements.ticketFareAirline.value = row.airline || '';
    elements.ticketFareClass.value = row.travelClass || '';
    elements.ticketFarePassengerType.value = row.passengerType || '';
    elements.ticketFareRouteType.value = row.ticketFare?.routeType || '';
    elements.ticketFareFlightType.value = row.ticketFare?.flightType || '';
    
    // Hide conditional sections on modal open
    handleTicketFareRouteTypeChange();
    elements.ticketFareUpDate.value = row.ticketFare?.upDate || '';
    elements.ticketFareDownDate.value = row.ticketFare?.downDate || '';
    elements.ticketFarePNR.value = row.ticketFare?.pnr || '';
    elements.ticketFareTicketNumber.value = row.ticketFare?.ticketNumber || '';
    elements.ticketFareDate.value = row.ticketFare?.date || '';
    elements.ticketFareAgent.value = '';
    elements.ticketFareSellingFare.value = row.ticketFare?.sellingFare || 0;
    elements.ticketFareNet.value = row.ticketFare?.netFare || 0;
    elements.ticketFareWithOffer.checked = row.ticketFare?.withOffer || false;
    elements.ticketFareRefundable.checked = row.ticketFare?.refundable || false;
    elements.ticketFareNonRefundable.checked = row.ticketFare?.nonRefundable || false;
    elements.ticketFareNonExchangeable.checked = row.ticketFare?.nonExchangeable || false;
    
    elements.ticketFareType.value = row.ticketFare?.ticketType || '';
    handleTicketTypeChange();
    elements.ticketFareGroupTicket.value = row.ticketFare?.groupTicketId || '';
    
    elements.ticketFareInboundAdult.value = row.ticketFare?.baggage?.inbound?.adult || 30;
    elements.ticketFareInboundChild.value = row.ticketFare?.baggage?.inbound?.child || 30;
    elements.ticketFareInboundInfant.value = row.ticketFare?.baggage?.inbound?.infant || 0;
    elements.ticketFareOutboundAdult.value = row.ticketFare?.baggage?.outbound?.adult || 50;
    elements.ticketFareOutboundChild.value = row.ticketFare?.baggage?.outbound?.child || 50;
    elements.ticketFareOutboundInfant.value = row.ticketFare?.baggage?.outbound?.infant || 0;
}

function closeTicketFareModal() {
    editingPassengerRowIndex = null;
    elements.ticketFareModal?.classList.add('hidden');
}

function handleTicketFareRouteTypeChange() {
    const routeType = elements.ticketFareRouteType?.value;
    const baggageSection = document.getElementById('ticketFareBaggageSection');
    const inboundBaggage = document.getElementById('ticketFareInboundBaggage');
    const outboundBaggage = document.getElementById('ticketFareOutboundBaggage');
    const inboundDateField = document.getElementById('ticketFareInboundDateField');
    const outboundDateField = document.getElementById('ticketFareOutboundDateField');
    
    // Hide all first
    if (baggageSection) baggageSection.classList.add('hidden');
    if (inboundBaggage) inboundBaggage.classList.add('hidden');
    if (outboundBaggage) outboundBaggage.classList.add('hidden');
    if (inboundDateField) inboundDateField.classList.add('hidden');
    if (outboundDateField) outboundDateField.classList.add('hidden');
    
    switch(routeType) {
        case 'One Way-Inbound':
            if (baggageSection) baggageSection.classList.remove('hidden');
            if (inboundBaggage) inboundBaggage.classList.remove('hidden');
            if (inboundDateField) inboundDateField.classList.remove('hidden');
            break;
        case 'One Way-Outbound':
            if (baggageSection) baggageSection.classList.remove('hidden');
            if (outboundBaggage) outboundBaggage.classList.remove('hidden');
            if (outboundDateField) outboundDateField.classList.remove('hidden');
            break;
        case 'Round':
        case 'Multi City':
            if (baggageSection) baggageSection.classList.remove('hidden');
            if (inboundBaggage) inboundBaggage.classList.remove('hidden');
            if (outboundBaggage) outboundBaggage.classList.remove('hidden');
            if (inboundDateField) inboundDateField.classList.remove('hidden');
            if (outboundDateField) outboundDateField.classList.remove('hidden');
            break;
    }
}

function handleTicketTypeChange() {
    const ticketType = elements.ticketFareType?.value;
    const groupTicketSection = elements.groupTicketSection;
    const groupTicketSelect = elements.ticketFareGroupTicket;
    
    if (ticketType === 'group') {
        groupTicketSection?.classList.remove('hidden');
        groupTicketSelect.innerHTML = '<option value="">Select Ticket</option>' + 
            groupTickets.map(gt => 
                `<option value="${gt.id}">${gt.pnr} • ${gt.date} • ${gt.remainingSeats} seats</option>`
            ).join('');
    } else {
        groupTicketSection?.classList.add('hidden');
        groupTicketSelect.innerHTML = '<option value="">Select Ticket</option>';
    }
}

function handleGroupTicketSelect() {
    const selectedId = elements.ticketFareGroupTicket?.value;
    const selected = groupTickets.find(gt => gt.id === selectedId);
    
    if (selected) {
        elements.ticketFarePNR.value = selected.pnr;
        elements.ticketFareDate.value = selected.date;
        elements.ticketFareRoute.value = selected.route;
    }
}

function handleTicketFareSubmit(e) {
    e.preventDefault();
    if (editingPassengerRowIndex === null) return;
    
    const ticketFareData = {
        ticketType: elements.ticketFareType?.value || 'regular',
        routeType: elements.ticketFareRouteType?.value || '',
        flightType: elements.ticketFareFlightType?.value || '',
        groupTicketId: elements.ticketFareGroupTicket?.value || '',
        upDate: elements.ticketFareUpDate?.value || '',
        downDate: elements.ticketFareDownDate?.value || '',
        pnr: elements.ticketFarePNR?.value || '',
        ticketNumber: elements.ticketFareTicketNumber?.value || '',
        date: elements.ticketFareDate?.value || '',
        ticketAgent: elements.ticketFareAgent?.value || '',
        sellingFare: parseFloat(elements.ticketFareSellingFare?.value) || 0,
        netFare: parseFloat(elements.ticketFareNet?.value) || 0,
        withOffer: elements.ticketFareWithOffer?.checked || false,
        refundable: elements.ticketFareRefundable?.checked || false,
        nonRefundable: elements.ticketFareNonRefundable?.checked || false,
        nonExchangeable: elements.ticketFareNonExchangeable?.checked || false,
        baggage: {
            inbound: {
                adult: parseInt(elements.ticketFareInboundAdult?.value) || 30,
                child: parseInt(elements.ticketFareInboundChild?.value) || 30,
                infant: parseInt(elements.ticketFareInboundInfant?.value) || 0,
            },
            outbound: {
                adult: parseInt(elements.ticketFareOutboundAdult?.value) || 50,
                child: parseInt(elements.ticketFareOutboundChild?.value) || 50,
                infant: parseInt(elements.ticketFareOutboundInfant?.value) || 0,
            }
        }
    };
    
    passengerIndexState.passengerIndexRows[editingPassengerRowIndex].ticketFare = ticketFareData;
    passengerIndexState.passengerIndexRows[editingPassengerRowIndex].ticketStatus = 'Issued';
    savePassengerIndexToStorage();
    
    closeTicketFareModal();
    renderPassengerIndex();
    showToast('Ticket fare saved successfully');
}

// ============================================
// Initialize
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    // Route ticket type change event
    const routeTicketType = document.getElementById('routeTicketType');
    if (routeTicketType) {
        routeTicketType.addEventListener('change', handleRouteTicketTypeChange);
    }
    
    // Route Flight Type change event
    const routeFlightType = document.getElementById('routeFlightType');
    if (routeFlightType) {
        routeFlightType.addEventListener('change', handleRouteFlightTypeChange);
    }
    
    // Route Round From change event - auto-populate Return To
    const routeRoundFrom = document.getElementById('routeRoundFrom');
    if (routeRoundFrom) {
        routeRoundFrom.addEventListener('change', handleRouteRoundFromChange);
    }
    
    // Fare with offer checkbox
    if (elements.fareWithOffer) {
        elements.fareWithOffer.addEventListener('change', updateFareOfferFields);
    }
    
    // Group ticket checkbox
    if (elements.fareGroupTicket) {
        elements.fareGroupTicket.addEventListener('change', updateGroupTicketForm);
        elements.fareGroupTicket.addEventListener('change', updateGroupTicketDateFields);
    }
    
    // Route Type dropdown - for conditional date fields in Group Ticket
    if (elements.fareRouteType) {
        elements.fareRouteType.addEventListener('change', updateGroupTicketDateFields);
        elements.fareRouteType.addEventListener('change', handleRouteTypeChange);
    }

    // Ticket fare modal events
    elements.ticketFareType?.addEventListener('change', handleTicketTypeChange);
    elements.ticketFareGroupTicket?.addEventListener('change', handleGroupTicketSelect);
    elements.ticketFareForm?.addEventListener('submit', handleTicketFareSubmit);

    // Initialize fare index
    renderFareIndex();
    
    // Initialize passenger index
    loadPassengerIndexFromStorage();
    renderPassengerIndex();
    
    // Initialize route index
    renderRouteIndex();
});
