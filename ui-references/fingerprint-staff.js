// ============================================
// Fingerprint Staff Page JavaScript
// ============================================

const fingerprintStaffIndex = [
    {
        invoiceNo: "INV-1001",
        customerName: "Ahmed Al-Rashid",
        customerMobile: "0501234567",
        division: "Dhaka Division",
        district: "Dhaka",
        cost: 150,
        fingerprintOffice: "BMT-Dhaka",
        fingerDeadline: "2026-03-25",
        passengers: [
            { name: "Ahmed Al-Rashid", mobile: "0501234567", bangladeshiMobile: "01763225376", status: "Pending", approvedVisa: "None" },
            { name: "Sara Khan", mobile: "0559876543", bangladeshiMobile: "01781088228", status: "Pending", approvedVisa: "None" }
        ]
    },
    {
        invoiceNo: "INV-1002",
        customerName: "Fatima Al-Saud",
        customerMobile: "0559876543",
        division: "Chattogram Division",
        district: "Chattogram",
        cost: 200,
        fingerprintOffice: "BMT-Chattogram",
        fingerDeadline: "2026-03-20",
        passengers: [
            { name: "Fatima Al-Saud", mobile: "0559876543", bangladeshiMobile: "01836466464", status: "Processing", approvedVisa: "None" },
            { name: "Yusuf Mohammed", mobile: "0507777777", bangladeshiMobile: "01616373373", status: "Processing", approvedVisa: "None" },
            { name: "Khalid Rahman", mobile: "0558888888", bangladeshiMobile: "01746858593", status: "Processing", approvedVisa: "None" }
        ]
    },
    {
        invoiceNo: "INV-1003",
        customerName: "Mohammad Ali",
        customerMobile: "0501111111",
        division: "Rajshahi Division",
        district: "Rajshahi",
        cost: 175,
        fingerprintOffice: "BMT-Rangpur",
        fingerDeadline: "2026-03-15",
        passengers: [
            { name: "Mohammad Ali", mobile: "0501111111", bangladeshiMobile: "01911223344", status: "Done", approvedVisa: "None" },
            { name: "Aisha Rahman", mobile: "0552222222", bangladeshiMobile: "01922334455", status: "Done", approvedVisa: "None" },
            { name: "Omar Hassan", mobile: "0503333333", bangladeshiMobile: "01933445566", status: "Done", approvedVisa: "None" }
        ]
    },
    {
        invoiceNo: "INV-1004",
        customerName: "Fatema Begum",
        customerMobile: "0554444444",
        division: "Khulna Division",
        district: "Khulna",
        cost: 180,
        fingerprintOffice: "BMT-Dhaka",
        fingerDeadline: "2026-03-18",
        passengers: [
            { name: "Fatema Begum", mobile: "0554444444", bangladeshiMobile: "01944556677", status: "Pending", approvedVisa: "None" },
            { name: "Mahmood Hasan", mobile: "0509999999", bangladeshiMobile: "01955667788", status: "Pending", approvedVisa: "None" },
            { name: "Rashida Khatun", mobile: "0551010101", bangladeshiMobile: "01966778899", status: "Pending", approvedVisa: "None" },
            { name: "Abdul Wahab", mobile: "0502020202", bangladeshiMobile: "01977889900", status: "Pending", approvedVisa: "None" },
            { name: "Sumon Mia", mobile: "0553030303", bangladeshiMobile: "01988990011", status: "Pending", approvedVisa: "None" }
        ]
    },
    {
        invoiceNo: "INV-1005",
        customerName: "Hussain Ahmed",
        customerMobile: "0505555555",
        division: "Sylhet Division",
        district: "Sylhet",
        cost: 160,
        fingerprintOffice: "BMT-Sylhet",
        fingerDeadline: "2026-03-22",
        passengers: [
            { name: "Hussain Ahmed", mobile: "0505555555", bangladeshiMobile: "01811112222", status: "Processing", approvedVisa: "None" },
            { name: "Nadia Islam", mobile: "0556666666", bangladeshiMobile: "01822223333", status: "Processing", approvedVisa: "None" }
        ]
    },
    {
        invoiceNo: "INV-1006",
        customerName: "Aisha Rahman",
        customerMobile: "0552222222",
        division: "Rangpur Division",
        district: "Dinajpur",
        cost: 190,
        fingerprintOffice: "BMT-Chattogram",
        fingerDeadline: "2026-03-28",
        passengers: [
            { name: "Aisha Rahman", mobile: "0552222222", bangladeshiMobile: "01711112222", status: "Pending", approvedVisa: "None" },
            { name: "Tariq Mahmud", mobile: "0504040404", bangladeshiMobile: "01722223333", status: "Pending", approvedVisa: "None" },
            { name: "Zahirul Islam", mobile: "0555050505", bangladeshiMobile: "01733334444", status: "Pending", approvedVisa: "None" },
            { name: "Rabbani Khan", mobile: "0506060606", bangladeshiMobile: "01744445555", status: "Pending", approvedVisa: "None" }
        ]
    }
];

const fingerprintStatusOptions = ["Pending", "Processing", "Done"];

const approvedVisaOptions = [/* "Select", */ "Approved", "Partially Approved", "None", "Processing", "Cancel", "Hold & Ask for next Finger date?"];
const holdReasonOptions = ["Reschedule by Client", "Reschedule by BMT", "NFC Problem"];

let currentHoldInvoiceIndex = null;
let currentHoldPassengerIndex = null;

const elements = {
    fingerprintStaffSection: document.getElementById('fingerprintStaffSection'),
    fingerprintStaffTableBody: document.getElementById('fingerprintStaffTableBody'),
    fingerprintStaffEmpty: document.getElementById('fingerprintStaffEmpty'),
};

function renderFingerprintStaffIndex() {
    const data = fingerprintStaffIndex;
    
    if (data.length > 0) {
        elements.fingerprintStaffEmpty?.classList.add('hidden');
    } else {
        elements.fingerprintStaffEmpty?.classList.remove('hidden');
    }

    if (!elements.fingerprintStaffTableBody) return;
    
    elements.fingerprintStaffTableBody.innerHTML = '';
    
    data.forEach((invoice, invoiceIndex) => {
        invoice.passengers.forEach((passenger, passengerIndex) => {
            const isFirstPassenger = passengerIndex === 0;
            const isLastPassenger = passengerIndex === invoice.passengers.length - 1;
            const rowCount = invoice.passengers.length;
            
            const tr = document.createElement('tr');
            const isOddInvoice = invoiceIndex % 2 !== 0;
            tr.className = 'hover:bg-slate-100 ' + 
                (isOddInvoice ? 'bg-slate-50 ' : 'bg-white ') +
                'border-l-4 ' + 
                (isOddInvoice ? 'border-l-blue-500' : 'border-l-orange-500') +
                (isLastPassenger ? ' border-b-2 border-slate-400' : '');
            
            let html = '';
            
            if (isFirstPassenger) {
                html += `
                    <td class="px-3 py-2 text-slate-800 font-medium">${invoice.invoiceNo}</td>
                    <td class="px-3 py-2 text-slate-600">${invoice.customerName}</td>
                    <td class="px-3 py-2 text-slate-600">${invoice.passengers.length}</td>
                    <td class="px-3 py-2 text-slate-600 whitespace-pre-line">${invoice.customerMobile}${passenger.bangladeshiMobile ? '\n' + passenger.bangladeshiMobile : ''}</td>
                    <!-- <td class="px-3 py-2 text-slate-600">${invoice.division}</td> -->
                    <td class="px-3 py-2 text-slate-600">${invoice.fingerprintOffice || '-'}</td>
                    <td class="px-3 py-2 text-slate-600">${invoice.district}</td>
                    <td class="px-3 py-2 text-slate-600">${invoice.fingerDeadline || '-'}</td>
                `;
            } else {
                html += `
                    <td class="px-3 py-2"></td>
                    <td class="px-3 py-2"></td>
                    <td class="px-3 py-2"></td>
                    <td class="px-3 py-2 text-slate-600 whitespace-pre-line">${invoice.customerMobile}${passenger.bangladeshiMobile ? '\n' + passenger.bangladeshiMobile : ''}</td>
                    <td class="px-3 py-2"></td>
                    <td class="px-3 py-2"></td>
                    <td class="px-3 py-2"></td>
                `;
            }
            
html += `
                <td class="px-3 py-2 text-slate-600">${passenger.name}</td>
            `;
            
            if (isFirstPassenger) {
                html += `
                    <td class="px-3 py-2 text-right">
                        <input type="number" value="${invoice.cost}" onchange="updateFingerprintCost(${invoiceIndex}, this.value)" class="w-20 text-right text-sm border border-slate-300 rounded px-2 py-1">
                    </td>
                `;
            } else {
                html += `<td class="px-3 py-2"></td>`;
            }
            
            html += `
                <td class="px-3 py-2">
                    <select onchange="updateApprovedVisa(${invoiceIndex}, ${passengerIndex}, this.value)" class="text-xs border border-slate-300 rounded px-2 py-1 bg-white">
                        ${approvedVisaOptions.map(opt => `<option value="${opt}" ${passenger.approvedVisa === opt ? 'selected' : ''}>${opt}</option>`).join('')}
                    </select>
                </td>
            `;
            
            tr.innerHTML = html;
            elements.fingerprintStaffTableBody.appendChild(tr);
        });
    });
}

function updateFingerprintStatus(invoiceIndex, passengerIndex, status) {
    fingerprintStaffIndex[invoiceIndex].passengers[passengerIndex].status = status;
    showToast('Status updated');
}

function updateFingerprintCost(invoiceIndex, cost) {
    fingerprintStaffIndex[invoiceIndex].cost = parseFloat(cost) || 0;
    showToast('Cost updated');
}

function updateApprovedVisa(invoiceIndex, passengerIndex, value) {
    if (value === "Hold & Ask for next Finger date?") {
        currentHoldInvoiceIndex = invoiceIndex;
        currentHoldPassengerIndex = passengerIndex;
        showHoldModal();
    } else {
        fingerprintStaffIndex[invoiceIndex].passengers[passengerIndex].approvedVisa = value;
        showToast('Approved for Visa/Ticket updated');
    }
}

function showHoldModal() {
    const modal = document.getElementById('holdModal');
    const reasonSelect = document.getElementById('holdReason');
    const dateInput = document.getElementById('nextFingerDate');
    
    if (reasonSelect) reasonSelect.value = '';
    if (dateInput) dateInput.value = '';
    
    if (modal) modal.classList.remove('hidden');
}

function hideHoldModal() {
    const modal = document.getElementById('holdModal');
    if (modal) modal.classList.add('hidden');
    
    currentHoldInvoiceIndex = null;
    currentHoldPassengerIndex = null;
}

function saveHoldDetails() {
    const reasonSelect = document.getElementById('holdReason');
    const dateInput = document.getElementById('nextFingerDate');
    const remarksInput = document.getElementById('holdRemarks');
    
    const reason = reasonSelect ? reasonSelect.value : '';
    const nextDate = dateInput ? dateInput.value : '';
    const remarks = remarksInput ? remarksInput.value : '';
    
    if (!reason) {
        showToast('Please select a reason');
        return;
    }
    if (!nextDate) {
        showToast('Please select next finger date');
        return;
    }
    
    if (currentHoldInvoiceIndex !== null && currentHoldPassengerIndex !== null) {
        fingerprintStaffIndex[currentHoldInvoiceIndex].passengers[currentHoldPassengerIndex].approvedVisa = "Processing";
        fingerprintStaffIndex[currentHoldInvoiceIndex].passengers[currentHoldPassengerIndex].holdReason = reason;
        fingerprintStaffIndex[currentHoldInvoiceIndex].passengers[currentHoldPassengerIndex].nextFingerDate = nextDate;
        fingerprintStaffIndex[currentHoldInvoiceIndex].passengers[currentHoldPassengerIndex].holdRemarks = remarks;
        
        showToast('Hold details saved');
        hideHoldModal();
        renderFingerprintStaffIndex();
    }
}

// ============================================
// Initialize
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    renderFingerprintStaffIndex();
});
