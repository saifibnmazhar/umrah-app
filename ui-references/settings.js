// ============================================
// Settings Page JavaScript
// ============================================

const allDistricts = [
    "Dhaka", "Faridpur", "Gazipur", "Gopalganj", "Kishoreganj", "Madaripur", "Manikganj", "Munshiganj", "Narayanganj", "Narsingdi", "Rajbari", "Shariatpur", "Tangail",
    "Bandarban", "Brahmanbaria", "Chandpur", "Chattogram", "Cumilla", "Cox's Bazar", "Feni", "Khagrachhari", "Lakshmipur", "Noakhali", "Rangamati",
    "Bogura", "Joypurhat", "Naogaon", "Natore", "Chapainawabganj", "Pabna", "Rajshahi", "Sirajganj",
    "Bagerhat", "Chuadanga", "Jashore", "Jhenaidah", "Khulna", "Kushtia", "Magura", "Meherpur", "Narail", "Satkhira",
    "Barguna", "Barishal", "Bhola", "Jhalokathi", "Patuakhali", "Pirojpur",
    "Habiganj", "Moulvibazar", "Sunamganj", "Sylhet",
    "Dinajpur", "Gaibandha", "Kurigram", "Lalmonirhat", "Nilphamari", "Panchagarh", "Rangpur", "Thakurgaon",
    "Jamalpur", "Mymensingh", "Netrokona", "Sherpur"
];

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
    fingerprintCharges: {},
    packageConfigs: []
};

const ticketOptions = {
    routes: ['DAC-JED-DAC', 'DAC-RUH-DAC', 'DAC-MED-DAC', 'DAC-JED-MED-DAC'],
    airlines: ['Saudia', 'Biman Bangladesh', 'Emirates', 'Qatar Airways', 'Flynas'],
    travelClasses: ['Economy', 'Business'],
    fares: [
        { id: 1, route: 'DAC-JED-DAC', airline: 'Saudia', travelClass: 'Economy', ticketType: 'regular', price: 2500 },
        { id: 2, route: 'DAC-JED-DAC', airline: 'Saudia', travelClass: 'Economy', ticketType: 'offer', offerPrice: 2400 },
        { id: 3, route: 'DAC-JED-DAC', airline: 'Saudia', travelClass: 'Economy', ticketType: 'offer', offerPrice: 2200 },
        { id: 4, route: 'DAC-RUH-DAC', airline: 'Saudia', travelClass: 'Economy', ticketType: 'group', groupAvailable: 10 },
        { id: 5, route: 'DAC-RUH-DAC', airline: 'Flynas', travelClass: 'Economy', ticketType: 'regular', price: 1800 },
        { id: 6, route: 'DAC-MED-DAC', airline: 'Biman Bangladesh', travelClass: 'Economy', ticketType: 'offer', offerPrice: 2100 },
        { id: 7, route: 'DAC-MED-DAC', airline: 'Biman Bangladesh', travelClass: 'Economy', ticketType: 'group', groupAvailable: 15 },
        { id: 8, route: 'DAC-JED-MED-DAC', airline: 'Qatar Airways', travelClass: 'Economy', ticketType: 'regular', price: 3200 },
    ]
};

function generateTicketOptions(ticketType) {
    const options = [];
    const fares = ticketOptions.fares;
    
    fares.forEach(fare => {
        if (ticketType && fare.ticketType !== ticketType.toLowerCase()) return;
        
        let suffix = '';
        let type = fare.ticketType;
        
        if (fare.ticketType === 'offer' && fare.offerPrice) {
            suffix = ` (Offer Price ${fare.offerPrice})`;
        } else if (fare.ticketType === 'group' && fare.groupAvailable) {
            suffix = ` (Available ${fare.groupAvailable})`;
        }
        
        const text = `${fare.route} - ${fare.airline} - ${fare.travelClass}${suffix} ${type}`;
        options.push({
            value: fare.id,
            text: text,
            ticketType: type
        });
    });
    
    return options;
}

let adminSettings = { ...defaultAdminSettings };

function loadAdminSettings() {
    const saved = localStorage.getItem('adminSettings');
    if (saved) {
        adminSettings = JSON.parse(saved);
    }
    
    if (!adminSettings.packageConfigs || adminSettings.packageConfigs.length === 0) {
        adminSettings.packageConfigs = [
            {
                packageName: 'Umrah Basic',
                ticketType: 'Regular',
                ticket: 'DAC-JED-DAC - Saudia - Economy',
                availableTicket: 50,
                effectiveFrom: '2026-01-01',
                effectiveTo: '2026-04-30',
                regularPrice: 2500,
                offerPrice: 0
            },
            {
                packageName: 'Umrah Premium',
                ticketType: 'Regular',
                ticket: 'DAC-RUH-DAC - Saudia - Economy',
                availableTicket: 30,
                effectiveFrom: '',
                effectiveTo: '',
                regularPrice: 2800,
                offerPrice: 0
            },
            {
                packageName: 'Ramadan Umrah',
                ticketType: 'Offer',
                ticket: 'DAC-MED-DAC - Biman Bangladesh - Economy',
                availableTicket: 25,
                effectiveFrom: '2026-03-01',
                effectiveTo: '2026-05-31',
                regularPrice: 3100,
                offerPrice: 2800
            },
            {
                packageName: 'VIP Umrah',
                ticketType: 'Offer',
                ticket: 'DAC-JED-DAC - Saudia - Economy',
                availableTicket: 10,
                effectiveFrom: '2026-03-01',
                effectiveTo: '2026-05-31',
                regularPrice: 5500,
                offerPrice: 4900
            },
            {
                packageName: 'Hajj Package',
                ticketType: 'Group',
                ticket: 'DAC-JED-DAC - Saudia - Business',
                availableTicket: 15,
                effectiveFrom: '2026-04-01',
                effectiveTo: '2026-06-30',
                regularPrice: 12000,
                offerPrice: 0
            }
        ];
        localStorage.setItem('adminSettings', JSON.stringify(adminSettings));
    }
    
    return adminSettings;
}

function saveAdminSettingsToStorage() {
    localStorage.setItem('adminSettings', JSON.stringify(adminSettings));
}

const elements = {
    settingsSection: document.getElementById('settingsSection'),
    flightDateGapInput: document.getElementById('flightDateGapInput'),
    divisionSelect: document.getElementById('divisionSelect'),
    districtSelect: document.getElementById('districtSelect'),
    fingerprintChargeInput: document.getElementById('fingerprintChargeInput'),
    fingerprintChargesTableBody: document.getElementById('fingerprintChargesTableBody'),
    fingerprintChargesEmpty: document.getElementById('fingerprintChargesEmpty'),
    filterDivisionSelect: document.getElementById('filterDivisionSelect'),
    addUpdateChargeBtn: document.getElementById('addUpdateChargeBtn'),
    packageNameInput: document.getElementById('packageNameInput'),
    packageOfferSelect: document.getElementById('packageOfferSelect'),
    packageTicketSelect: document.getElementById('packageTicketSelect'),
    packageAvailableTicket: document.getElementById('packageAvailableTicket'),
    packageEffectiveFrom: document.getElementById('packageEffectiveFrom'),
    packageEffectiveTo: document.getElementById('packageEffectiveTo'),
    packageRegularPriceInput: document.getElementById('packageRegularPriceInput'),
    packageOfferPriceInput: document.getElementById('packageOfferPriceInput'),
    packageOfferPriceField: document.getElementById('packageOfferPriceField'),
    packageConfigTableBody: document.getElementById('packageConfigTableBody'),
    packageConfigEmpty: document.getElementById('packageConfigEmpty'),
};

function showSettingsTab() {
    loadAdminSettings();
    renderDivisionOptions();
    renderFilterDivisionOptions();
    renderFingerprintChargesTable();
    renderPackageTicketOptions();
    renderPackageConfigTable();
    
    if (elements.flightDateGapInput) {
        elements.flightDateGapInput.value = adminSettings.defaultFlightDateGap || 30;
    }
}

function renderPackageTicketOptions() {
    if (!elements.packageTicketSelect) return;
    
    const ticketType = elements.packageOfferSelect?.value || '';
    const options = generateTicketOptions(ticketType);
    elements.packageTicketSelect.innerHTML = '<option value="">Select Ticket</option>';
    options.forEach(opt => {
        const option = document.createElement('option');
        option.value = opt.value;
        option.textContent = opt.text;
        option.dataset.ticketType = opt.ticketType;
        elements.packageTicketSelect.appendChild(option);
    });
}

function togglePackageOfferPrice() {
    if (elements.packageOfferPriceField) {
        if (elements.packageOfferSelect.value === 'Offer') {
            elements.packageOfferPriceField.classList.remove('hidden');
        } else {
            elements.packageOfferPriceField.classList.add('hidden');
            elements.packageOfferPriceInput.value = 0;
        }
    }
}

function addPackageConfig() {
    const packageName = elements.packageNameInput.value.trim();
    const ticketType = elements.packageOfferSelect.value;
    const ticket = elements.packageTicketSelect.value;
    const availableTicket = parseInt(elements.packageAvailableTicket.value) || 0;
    const effectiveFrom = elements.packageEffectiveFrom ? elements.packageEffectiveFrom.value : '';
    const effectiveTo = elements.packageEffectiveTo ? elements.packageEffectiveTo.value : '';
    const regularPrice = parseFloat(elements.packageRegularPriceInput.value) || 0;
    const offerPrice = ticketType === 'Offer' ? parseFloat(elements.packageOfferPriceInput.value) || 0 : 0;

    if (!packageName) {
        showToast('Please enter package name', 'error');
        return;
    }

    if (!ticket) {
        showToast('Please select ticket', 'error');
        return;
    }

    if (!adminSettings.packageConfigs) {
        adminSettings.packageConfigs = [];
    }

    const existingIndex = adminSettings.packageConfigs.findIndex(p => 
        p.packageName === packageName && p.ticket === ticket
    );

    if (existingIndex !== -1) {
        adminSettings.packageConfigs[existingIndex] = {
            packageName,
            ticketType: ticketType,
            ticket,
            availableTicket,
            effectiveFrom,
            effectiveTo,
            regularPrice,
            offerPrice
        };
        showToast('Package configuration updated');
    } else {
        adminSettings.packageConfigs.push({
            packageName,
            ticketType: ticketType,
            ticket,
            availableTicket,
            effectiveFrom,
            effectiveTo,
            regularPrice,
            offerPrice
        });
        showToast('Package configuration added');
    }

    saveAdminSettingsToStorage();
    renderPackageConfigTable();
    clearPackageConfigInputs();
}

function clearPackageConfigInputs() {
    if (elements.packageNameInput) elements.packageNameInput.value = '';
    if (elements.packageOfferSelect) elements.packageOfferSelect.value = '';
    if (elements.packageTicketSelect) elements.packageTicketSelect.value = '';
    if (elements.packageAvailableTicket) elements.packageAvailableTicket.value = 0;
    if (elements.packageEffectiveFrom) elements.packageEffectiveFrom.value = '';
    if (elements.packageEffectiveTo) elements.packageEffectiveTo.value = '';
    if (elements.packageRegularPriceInput) elements.packageRegularPriceInput.value = 0;
    if (elements.packageOfferPriceInput) elements.packageOfferPriceInput.value = 0;
    if (elements.packageOfferPriceField) elements.packageOfferPriceField.classList.add('hidden');
}

function renderPackageConfigTable() {
    if (!elements.packageConfigTableBody) return;
    
    const configs = adminSettings.packageConfigs || [];

    if (configs.length > 0) {
        elements.packageConfigEmpty?.classList.add('hidden');
    } else {
        elements.packageConfigEmpty?.classList.remove('hidden');
    }

    elements.packageConfigTableBody.innerHTML = '';
    configs.forEach((config, index) => {
        const tr = document.createElement('tr');
        tr.className = 'hover:bg-slate-50';
        tr.innerHTML = `
            <td class="px-3 py-2 text-slate-600">${config.packageName}</td>
            <td class="px-3 py-2 text-slate-600">${config.ticket}</td>
            <td class="px-3 py-2">
                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium ${config.ticketType === 'Offer' ? 'bg-green-100 text-green-700' : config.ticketType === 'Group' ? 'bg-purple-100 text-purple-700' : 'bg-slate-100 text-slate-600'}">
                    ${config.ticketType || '-'}
                </span>
            </td>
            <td class="px-3 py-2 text-slate-600">${config.ticketType === 'Group' ? (config.availableTicket || 0) : '-'}</td>
            <td class="px-3 py-2 text-slate-600">${config.effectiveFrom || '-'}</td>
            <td class="px-3 py-2 text-slate-600">${config.effectiveTo || '-'}</td>
            <td class="px-3 py-2 text-right text-slate-800 font-medium">${config.regularPrice} SAR</td>
            <td class="px-3 py-2 text-right text-slate-800 font-medium">${config.ticketType === 'Offer' ? config.offerPrice + ' SAR' : '-'}</td>
            <td class="px-3 py-2 text-center">
                <button onclick="deletePackageConfig(${index})" class="text-xs text-red-500 hover:text-red-700">Delete</button>
            </td>
        `;
        elements.packageConfigTableBody.appendChild(tr);
    });
}

function deletePackageConfig(index) {
    if (adminSettings.packageConfigs && adminSettings.packageConfigs[index]) {
        adminSettings.packageConfigs.splice(index, 1);
        saveAdminSettingsToStorage();
        renderPackageConfigTable();
        showToast('Package configuration deleted');
    }
}

function saveFlightDateGap() {
    const gap = parseInt(elements.flightDateGapInput.value) || 30;
    adminSettings.defaultFlightDateGap = gap;
    saveAdminSettingsToStorage();
    showToast('Flight date gap saved');
}

function renderDivisionOptions() {
    if (!elements.divisionSelect) return;
    
    elements.divisionSelect.innerHTML = '<option value="">Select Division</option>';
    Object.keys(divisionsData).forEach(division => {
        const option = document.createElement('option');
        option.value = division;
        option.textContent = division;
        elements.divisionSelect.appendChild(option);
    });
}

function renderDistrictOptions() {
    if (!elements.districtSelect) return;
    
    const division = elements.divisionSelect.value;
    elements.districtSelect.innerHTML = '<option value="">Select District</option>';
    
    if (division && divisionsData[division]) {
        divisionsData[division].forEach(district => {
            const option = document.createElement('option');
            option.value = district;
            option.textContent = district;
            elements.districtSelect.appendChild(option);
        });
    }
}

function addFingerprintCharge() {
    const division = elements.divisionSelect.value;
    const district = elements.districtSelect.value;
    const charge = parseInt(elements.fingerprintChargeInput.value) || 0;

    if (!division || !district) {
        showToast('Please select division and district', 'error');
        return;
    }

    if (!adminSettings.fingerprintCharges) {
        adminSettings.fingerprintCharges = {};
    }

    if (!adminSettings.fingerprintCharges[division]) {
        adminSettings.fingerprintCharges[division] = {};
    }

    adminSettings.fingerprintCharges[division][district] = charge;
    saveAdminSettingsToStorage();

    elements.fingerprintChargeInput.value = 0;
    elements.districtSelect.value = '';
    
    renderFingerprintChargesTable();
    showToast('Fingerprint charge added/updated');
}

function renderFingerprintChargesTable() {
    if (!elements.fingerprintChargesTableBody) return;
    
    const charges = adminSettings.fingerprintCharges || {};
    const filterDivision = elements.filterDivisionSelect?.value || '';
    const chargeEntries = [];
    
    Object.keys(charges).forEach(district => {
        const entry = charges[district];
        if (!filterDivision || entry.division === filterDivision) {
            chargeEntries.push({
                division: entry.division,
                district,
                charge: entry.charge
            });
        }
    });

    if (chargeEntries.length > 0) {
        elements.fingerprintChargesEmpty?.classList.add('hidden');
    } else {
        elements.fingerprintChargesEmpty?.classList.remove('hidden');
    }

    elements.fingerprintChargesTableBody.innerHTML = '';
    chargeEntries.forEach(entry => {
        const tr = document.createElement('tr');
        tr.className = 'hover:bg-slate-50';
        tr.innerHTML = `
            <td class="px-3 py-2 text-slate-600">${entry.division}</td>
            <td class="px-3 py-2 text-slate-600">${entry.district}</td>
            <td class="px-3 py-2 text-right text-slate-800 font-medium">${entry.charge} SAR</td>
            <td class="px-3 py-2 text-center">
                <button onclick="deleteFingerprintCharge('${entry.division}', '${entry.district}')" class="text-xs text-red-500 hover:text-red-700">Delete</button>
            </td>
        `;
        elements.fingerprintChargesTableBody.appendChild(tr);
    });
}

function deleteFingerprintCharge(division, district) {
    if (adminSettings.fingerprintCharges && adminSettings.fingerprintCharges[district]) {
        delete adminSettings.fingerprintCharges[district];
        saveAdminSettingsToStorage();
        renderFingerprintChargesTable();
        showToast('Fingerprint charge deleted');
    }
}

function renderFilterDivisionOptions() {
    if (!elements.filterDivisionSelect) return;
    
    elements.filterDivisionSelect.innerHTML = '<option value="">Select Division</option>';
    Object.keys(divisionsData).forEach(division => {
        const option = document.createElement('option');
        option.value = division;
        option.textContent = division;
        elements.filterDivisionSelect.appendChild(option);
    });
}

function onDivisionFilterChange() {
    const division = elements.filterDivisionSelect?.value || '';
    const btn = elements.addUpdateChargeBtn;
    
    if (btn) {
        if (division) {
            btn.disabled = false;
            btn.classList.remove('opacity-50', 'cursor-not-allowed');
            btn.classList.add('hover:bg-slate-800');
        } else {
            btn.disabled = true;
            btn.classList.add('opacity-50', 'cursor-not-allowed');
            btn.classList.remove('hover:bg-slate-800');
        }
    }
    
    renderFingerprintChargesTable();
}

// ============================================
// Modal Functions
// ============================================
function showFingerprintChargeModal() {
    const modal = document.getElementById('fingerprintChargeModal');
    const districtSelect = document.getElementById('modalDistrictSelect');
    const chargeInput = document.getElementById('modalFingerprintChargeInput');
    const selectedDivision = elements.filterDivisionSelect?.value || '';
    
    if (modal && districtSelect) {
        districtSelect.innerHTML = '<option value="">Select District</option>';
        
        const districtsToShow = selectedDivision && divisionsData[selectedDivision] 
            ? divisionsData[selectedDivision] 
            : allDistricts;
            
        districtsToShow.forEach(district => {
            const option = document.createElement('option');
            option.value = district;
            option.textContent = district;
            districtSelect.appendChild(option);
        });
        
        chargeInput.value = 0;
        modal.classList.remove('hidden');
    }
}

function hideFingerprintChargeModal() {
    const modal = document.getElementById('fingerprintChargeModal');
    if (modal) {
        modal.classList.add('hidden');
    }
}

function submitFingerprintCharge() {
    const division = elements.filterDivisionSelect?.value;
    const district = document.getElementById('modalDistrictSelect').value;
    const charge = parseInt(document.getElementById('modalFingerprintChargeInput').value) || 0;

    if (!division || !district) {
        showToast('Please select a division and district', 'error');
        return;
    }

    if (!adminSettings.fingerprintCharges) {
        adminSettings.fingerprintCharges = {};
    }

    adminSettings.fingerprintCharges[district] = { division, charge };
    saveAdminSettingsToStorage();
    renderFingerprintChargesTable();
    hideFingerprintChargeModal();
    showToast('Fingerprint charge added/updated');
}

// ============================================
// Tab Switching
// ============================================
function showTab(tabId) {
    const tabs = ['flightDateGapSection', 'fingerprintChargeSection', 'packageConfigSection'];
    const tabButtons = ['tab-flightDateGap', 'tab-fingerprintCharge', 'tab-packageConfig'];
    
    tabs.forEach(t => {
        document.getElementById(t)?.classList.add('hidden');
    });
    
    tabButtons.forEach(t => {
        const btn = document.getElementById(t);
        if (btn) {
            btn.classList.remove('border-slate-800', 'text-slate-800');
            btn.classList.add('border-transparent', 'text-slate-500');
        }
    });
    
    document.getElementById(tabId)?.classList.remove('hidden');
    
    const tabKey = tabId.replace('Section', '');
    const activeBtn = document.getElementById('tab-' + tabKey);
    if (activeBtn) {
        activeBtn.classList.remove('border-transparent', 'text-slate-500');
        activeBtn.classList.add('border-slate-800', 'text-slate-800');
    }
}

// ============================================
// Initialize
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    showSettingsTab();
    showTab('flightDateGapSection');
});
