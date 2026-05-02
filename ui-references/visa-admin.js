// ============================================
// Visa Admin Page JavaScript
// ============================================

const passengerIndexState = {
    passengerIndexRows: [],
};

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
                    status: 'None',
                    package: '5 Days',
                    due: '0 SAR',
                    ticketFare: { date: '2026-04-10', sellingFare: 2200, netFare: 2000 },
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
                    visa: { sellingPrice: 500, finalCost: 450 },
                    fingerprintLocation: 'Dhaka North',
                    fingerprintCost: 200,
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
        const ticketSellingPrice = row.ticketFare ? row.ticketFare.sellingFare || 0 : 0;
        const visaSellingPrice = row.visa ? row.visa.sellingPrice || 0 : 0;
        const fingerprintCost = row.fingerprintLocation !== 'None' ? 200 : 0;
        const packageValue = ticketSellingPrice + visaSellingPrice + fingerprintCost;
        
        const isFirstInInvoice = prevInvoiceNo !== row.invoiceNo;
        const paxQty = isFirstInInvoice ? invoiceCounts[row.invoiceNo] : '';
        prevInvoiceNo = row.invoiceNo;

        const tr = document.createElement('tr');
        tr.className = 'hover:bg-slate-50';
        
        const requiredFlightDates = ['2026-04-15', '2026-04-20', '2026-04-25'];
        const actualFlightDates = ['2026-04-16', '-', '2026-04-25'];
        
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
            <td class="px-3 py-2 text-slate-600">${requiredFlightDates[index] || '-'}</td>
            <td class="px-3 py-2 text-slate-600">${actualFlightDates[index] || '-'}</td>
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
            <td class="px-3 py-2">
                <button onclick="window.location.href='visa-passenger-details.html?index=${index}'" class="text-xs bg-slate-100 hover:bg-slate-200 text-slate-600 px-2 py-1 rounded">View</button>
            </td>
        `;
        tableBody.appendChild(tr);
    });
}

const visaAdminState = {
    visaPriceRecords: [
        { id: 1, date: '2026-03-15', price: 500 },
        { id: 2, date: '2026-01-01', price: 450 },
    ],
    editingVisaPriceId: null,
    editingAgentPriceId: null,
};

const elements = {
    visaAdminSection: document.getElementById('visaAdminSection'),
    visaPriceTableBody: document.getElementById('visaPriceTableBody'),
    visaPriceEmpty: document.getElementById('visaPriceEmpty'),
    visaPriceModal: document.getElementById('visaPriceModal'),
    visaPriceModalTitle: document.getElementById('visaPriceModalTitle'),
    visaPriceDate: document.getElementById('visaPriceDate'),
    visaPriceAmount: document.getElementById('visaPriceAmount'),
    agentPriceModal: document.getElementById('agentPriceModal'),
    agentPriceModalTitle: document.getElementById('agentPriceModalTitle'),
    agentPriceName: document.getElementById('agentPriceName'),
    agentPriceAddress: document.getElementById('agentPriceAddress'),
    agentPriceAmount: document.getElementById('agentPriceAmount'),
    visaCostModal: document.getElementById('visaCostModal'),
    visaCostModalTitle: document.getElementById('visaCostModalTitle'),
    visaCostAgent: document.getElementById('visaCostAgent'),
    visaCostCommissionAgent: document.getElementById('visaCostCommissionAgent'),
    visaCostSellingPrice: document.getElementById('visaCostSellingPrice'),
    visaCostAgentCommission: document.getElementById('visaCostAgentCommission'),
    visaCostNetVisaCost: document.getElementById('visaCostNetVisaCost'),
    visaCostFinal: document.getElementById('visaCostFinal'),
};

function renderVisaPriceIndex() {
    const records = visaAdminState.visaPriceRecords;
    
    if (records.length > 0) {
        elements.visaPriceEmpty?.classList.add('hidden');
    } else {
        elements.visaPriceEmpty?.classList.remove('hidden');
    }

    if (!elements.visaPriceTableBody) return;
    
    elements.visaPriceTableBody.innerHTML = '';
    records.forEach((record, index) => {
        const tr = document.createElement('tr');
        tr.className = 'hover:bg-slate-50';
        tr.innerHTML = `
            <td class="px-3 py-2 text-slate-600">${record.date}</td>
            <td class="px-3 py-2 text-slate-800 font-medium">${record.price}</td>
            <td class="px-3 py-2">
                <div class="flex gap-2">
                    <button onclick="openEditVisaPriceModal(${record.id})" class="text-xs bg-slate-100 hover:bg-slate-200 text-slate-600 px-2 py-1 rounded">Edit</button>
                    <button onclick="openDeleteVisaPriceModal(${record.id})" class="text-xs bg-red-100 hover:bg-red-200 text-red-600 px-2 py-1 rounded">Delete</button>
                </div>
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

    const visaPriceData = {
        date: elements.visaPriceDate.value,
        price: parseFloat(elements.visaPriceAmount.value) || 0,
    };

    if (visaAdminState.editingVisaPriceId) {
        const index = visaAdminState.visaPriceRecords.findIndex(v => v.id === visaAdminState.editingVisaPriceId);
        if (index !== -1) {
            visaAdminState.visaPriceRecords[index] = { ...visaAdminState.visaPriceRecords[index], ...visaPriceData };
        }
        showToast('Visa price updated successfully');
    } else {
        visaPriceData.id = Date.now();
        visaAdminState.visaPriceRecords.push(visaPriceData);
        showToast('Visa price added successfully');
    }

    closeVisaPriceModal();
    renderVisaPriceIndex();
}

function openDeleteVisaPriceModal(visaPriceId) {
    if (confirm('Are you sure you want to delete this visa price record?')) {
        visaAdminState.visaPriceRecords = visaAdminState.visaPriceRecords.filter(v => v.id !== visaPriceId);
        showToast('Visa price deleted successfully');
        renderVisaPriceIndex();
    }
}

// ============================================
// Agent Price Modal Functions
// ============================================

function openAddAgentPriceModal() {
    visaAdminState.editingAgentPriceId = null;
    elements.agentPriceModalTitle.textContent = 'Agent Visa Price';
    elements.agentPriceName.value = '';
    elements.agentPriceAddress.value = '';
    elements.agentPriceAmount.value = '';
    elements.agentPriceModal.classList.remove('hidden');
}

function openEditAgentPriceModal(agentPriceId) {
    const record = agentVisaPricingState.records.find(r => r.id === agentPriceId);
    if (!record) return;
    
    visaAdminState.editingAgentPriceId = agentPriceId;
    elements.agentPriceModalTitle.textContent = 'Edit Agent Visa Price';
    elements.agentPriceName.value = record.agentName;
    elements.agentPriceAddress.value = record.agentAddress;
    elements.agentPriceAmount.value = record.visaPrice;
    elements.agentPriceModal.classList.remove('hidden');
}

function closeAgentPriceModal() {
    elements.agentPriceModal.classList.add('hidden');
}

function handleAgentPriceSubmit(e) {
    e.preventDefault();
    
    const agentPriceData = {
        agentName: elements.agentPriceName.value,
        agentAddress: elements.agentPriceAddress.value,
        visaPrice: parseFloat(elements.agentPriceAmount.value) || 0
    };
    
    if (visaAdminState.editingAgentPriceId) {
        const index = agentVisaPricingState.records.findIndex(r => r.id === visaAdminState.editingAgentPriceId);
        if (index !== -1) {
            agentVisaPricingState.records[index] = { ...agentVisaPricingState.records[index], ...agentPriceData };
        }
        showToast('Agent price updated successfully');
    } else {
        agentPriceData.id = Date.now();
        agentVisaPricingState.records.push(agentPriceData);
        showToast('Agent price added successfully');
    }
    
    closeAgentPriceModal();
    renderAgentWiseVisaPricing();
}

function openDeleteAgentVisaPriceModal(agentPriceId) {
    if (confirm('Are you sure you want to delete this agent price record?')) {
        agentVisaPricingState.records = agentVisaPricingState.records.filter(r => r.id !== agentPriceId);
        showToast('Agent price deleted successfully');
        renderAgentWiseVisaPricing();
    }
}

// ============================================
// Agent Wise Visa Pricing Functions
// ============================================
const agentVisaPricingState = {
    records: [
        { id: 1, agentName: "Al-Reem", agentAddress: "Riyadh, Saudi Arabia", visaPrice: 300 },
        { id: 2, agentName: "Nasser", agentAddress: "Jeddah, Saudi Arabia", visaPrice: 350 },
        { id: 3, agentName: "Umrah Plus", agentAddress: "Makkah, Saudi Arabia", visaPrice: 320 }
    ]
};

function renderAgentWiseVisaPricing() {
    const records = agentVisaPricingState.records;
    const tableBody = document.getElementById('agentWiseVisaPriceTableBody');
    const emptyMsg = document.getElementById('agentWiseVisaPriceEmpty');
    
    if (!tableBody) return;
    
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
            <td class="px-3 py-2 text-slate-800 font-medium">${record.agentName}</td>
            <td class="px-3 py-2 text-slate-600">${record.agentAddress}</td>
            <td class="px-3 py-2 text-slate-800 font-medium">${record.visaPrice}</td>
            <td class="px-3 py-2">
                <div class="flex gap-2">
                    <button onclick="openEditAgentPriceModal(${record.id})" class="text-xs bg-slate-100 hover:bg-slate-200 text-slate-600 px-2 py-1 rounded">Edit</button>
                    <button onclick="openDeleteAgentVisaPriceModal(${record.id})" class="text-xs bg-red-100 hover:bg-red-200 text-red-600 px-2 py-1 rounded">Delete</button>
                </div>
            </td>
        `;
        tableBody.appendChild(tr);
    });
}

function switchVisaTab(tab) {
    const visaPriceSection = document.getElementById('visaPriceSection');
    const agentWiseSection = document.getElementById('agentWiseSection');
    const visaPriceTabBtn = document.getElementById('visaPriceTabBtn');
    const agentWiseTabBtn = document.getElementById('agentWiseTabBtn');
    
    if (tab === 'visaPrice') {
        visaPriceSection?.classList.remove('hidden');
        agentWiseSection?.classList.add('hidden');
        visaPriceTabBtn?.classList.add('border-slate-700', 'text-slate-700');
        visaPriceTabBtn?.classList.remove('border-transparent', 'text-slate-500');
        agentWiseTabBtn?.classList.add('border-transparent', 'text-slate-500');
        agentWiseTabBtn?.classList.remove('border-slate-700', 'text-slate-700');
    } else {
        visaPriceSection?.classList.add('hidden');
        agentWiseSection?.classList.remove('hidden');
        agentWiseTabBtn?.classList.add('border-slate-700', 'text-slate-700');
        agentWiseTabBtn?.classList.remove('border-transparent', 'text-slate-500');
        visaPriceTabBtn?.classList.add('border-transparent', 'text-slate-500');
        visaPriceTabBtn?.classList.remove('border-slate-700', 'text-slate-700');
        renderAgentWiseVisaPricing();
    }
}

function openEditAgentVisaPriceModal(id) {
    alert('Edit agent price: ' + id);
}

function openDeleteAgentVisaPriceModal(id) {
    if (confirm('Are you sure you want to delete this agent price record?')) {
        agentVisaPricingState.records = agentVisaPricingState.records.filter(r => r.id !== id);
        showToast('Agent price deleted successfully');
        renderAgentWiseVisaPricing();
    }
}

// ============================================
// Visa Cost Modal Functions
// ============================================
let editingVisaCostRowIndex = null;

function openVisaCostModal(rowIndex) {
    editingVisaCostRowIndex = rowIndex;
    elements.visaCostModal.classList.remove('hidden');

    const row = passengerIndexState.passengerIndexRows[rowIndex];

    if (row.visa) {
        elements.visaCostModalTitle.textContent = 'Visa Submit Form';
        elements.visaCostAgent.value = row.visa.agent || '';
        elements.visaCostAgentCommission.value = row.visa.agentCommission || 0;
        elements.visaCostNetVisaCost.value = row.visa.netVisaCost || 0;
        elements.visaCostSellingPrice.value = row.visa.sellingPrice || 0;
        
        updateCommissionAgentOptions(row.visa.agent);
        
        setTimeout(() => {
            elements.visaCostCommissionAgent.value = row.visa.commissionAgent || '';
        }, 50);
    } else {
        elements.visaCostModalTitle.textContent = 'Visa Submit Form';
        elements.visaCostAgent.value = '';
        elements.visaCostCommissionAgent.innerHTML = '<option value="">Select Commission Agent</option>';
        elements.visaCostAgentCommission.value = 0;
        elements.visaCostNetVisaCost.value = 0;
        
        const latestVisa = visaAdminState.visaPriceRecords[0];
        elements.visaCostSellingPrice.value = latestVisa?.price || 500;
    }

    calculateVisaCost();
    setTimeout(() => elements.visaCostAgent.focus(), 100);
}

function updateCommissionAgentOptions(visaAgent) {
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
    editingVisaCostRowIndex = null;
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

    const rowIndex = editingVisaCostRowIndex;
    if (rowIndex === null) return;

    const agentCommission = parseFloat(elements.visaCostAgentCommission.value) || 0;
    const netVisaCost = parseFloat(elements.visaCostNetVisaCost.value) || 0;

    const visaData = {
        agent: elements.visaCostAgent.value,
        commissionAgent: elements.visaCostCommissionAgent.value,
        sellingPrice: parseFloat(elements.visaCostSellingPrice.value) || 0,
        agentCommission: agentCommission,
        netVisaCost: netVisaCost,
        finalCost: parseFloat(elements.visaCostFinal.value) || 0,
    };

    passengerIndexState.passengerIndexRows[rowIndex].visa = visaData;
    savePassengerIndexToStorage();
    closeVisaCostModal();
    renderPassengerIndex();
    showToast('Visa submitted successfully');
}

// ============================================
// Visa Issue Modal Functions
// ============================================
let editingVisaIssueRowIndex = null;

function openVisaIssueModal(rowIndex) {
    editingVisaIssueRowIndex = rowIndex;
    document.getElementById('visaIssueModal')?.classList.remove('hidden');

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
    document.getElementById('visaIssueModal')?.classList.add('hidden');
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
    document.getElementById('visaEditModal')?.classList.remove('hidden');

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

const visaAgentCommissionAgents = {
    "Visa Agent A": ["Commission Agent 1", "Commission Agent 2"],
    "Visa Agent B": ["Commission Agent 3", "Commission Agent 4"]
};

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

// ============================================
// Initialize
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    renderVisaPriceIndex();
    loadPassengerIndexFromStorage();
    renderPassengerIndex();
});
