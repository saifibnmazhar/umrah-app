// ============================================
// Fingerprint Admin Page JavaScript
// ============================================

const fingerprintStaffOptions = ["Select", "Staff A", "Staff B", "Staff C", "Staff D"];

const fingerprintAdminIndex = [
    {
        invoiceNo: "INV-1001",
        bookingDate: "2026-03-15",
        customerName: "Ahmed Al-Rashid",
        customerMobile: "0501234567",
        division: "Dhaka Division",
        district: "Dhaka",
        fingerDeadline: "2026-03-25",
        fingerCharge: 150,
        fingerCost: 100,
        assignedStaff: "Select",
        fingerprintOffice: "Office",
        passengers: [
            { name: "Ahmed Al-Rashid", mobile: "0501234567", bangladeshiMobile: "01763225376", status: "Pending", approvedVisa: "None", requiredFlightDate: "2026-04-01", actualFlightDate: "2026-04-05" },
            { name: "Sara Khan", mobile: "0559876543", bangladeshiMobile: "01781088228", status: "Pending", approvedVisa: "None", requiredFlightDate: "2026-04-01", actualFlightDate: "2026-04-05" }
        ]
    },
    {
        invoiceNo: "INV-1002",
        bookingDate: "2026-03-10",
        customerName: "Fatima Al-Saud",
        customerMobile: "0559876543",
        division: "Chattogram Division",
        district: "Chattogram",
        fingerDeadline: "2026-03-20",
        fingerCharge: 200,
        fingerCost: 150,
        assignedStaff: "Select",
        fingerprintOffice: "Office",
        passengers: [
            { name: "Fatima Al-Saud", mobile: "0559876543", bangladeshiMobile: "01836466464", status: "Processing", approvedVisa: "None", requiredFlightDate: "2026-03-30", actualFlightDate: "2026-04-02" },
            { name: "Yusuf Mohammed", mobile: "0507777777", bangladeshiMobile: "01616373373", status: "Processing", approvedVisa: "None", requiredFlightDate: "2026-03-30", actualFlightDate: "2026-04-02" },
            { name: "Khalid Rahman", mobile: "0558888888", bangladeshiMobile: "01746858593", status: "Processing", approvedVisa: "None", requiredFlightDate: "2026-03-30", actualFlightDate: "2026-04-02" }
        ]
    },
    {
        invoiceNo: "INV-1003",
        bookingDate: "2026-03-05",
        customerName: "Mohammad Ali",
        customerMobile: "0501111111",
        division: "Rajshahi Division",
        district: "Rajshahi",
        fingerDeadline: "2026-03-15",
        fingerCharge: 175,
        fingerCost: 120,
        assignedStaff: "Select",
        fingerprintOffice: "Home",
        passengers: [
            { name: "Mohammad Ali", mobile: "0501111111", bangladeshiMobile: "01911223344", status: "Done", approvedVisa: "None", requiredFlightDate: "2026-03-25", actualFlightDate: "2026-03-28" },
            { name: "Aisha Rahman", mobile: "0552222222", bangladeshiMobile: "01922334455", status: "Done", approvedVisa: "None", requiredFlightDate: "2026-03-25", actualFlightDate: "2026-03-28" },
            { name: "Omar Hassan", mobile: "0503333333", bangladeshiMobile: "01933445566", status: "Done", approvedVisa: "None", requiredFlightDate: "2026-03-25", actualFlightDate: "2026-03-28" }
        ]
    },
    {
        invoiceNo: "INV-1004",
        bookingDate: "2026-03-08",
        customerName: "Fatema Begum",
        customerMobile: "0554444444",
        division: "Khulna Division",
        district: "Khulna",
        fingerDeadline: "2026-03-18",
        fingerCharge: 180,
        fingerCost: 130,
        assignedStaff: "Select",
        fingerprintOffice: "Office",
        passengers: [
            { name: "Fatema Begum", mobile: "0554444444", bangladeshiMobile: "01944556677", status: "Pending", approvedVisa: "None", requiredFlightDate: "2026-03-28", actualFlightDate: "2026-04-01" },
            { name: "Mahmood Hasan", mobile: "0509999999", bangladeshiMobile: "01955667788", status: "Pending", approvedVisa: "None", requiredFlightDate: "2026-03-28", actualFlightDate: "2026-04-01" }
        ]
    },
    {
        invoiceNo: "INV-1005",
        bookingDate: "2026-03-12",
        customerName: "Hussain Ahmed",
        customerMobile: "0505555555",
        division: "Sylhet Division",
        district: "Sylhet",
        fingerDeadline: "2026-03-22",
        fingerCharge: 160,
        fingerCost: 110,
        assignedStaff: "Select",
        fingerprintOffice: "Home",
        passengers: [
            { name: "Hussain Ahmed", mobile: "0505555555", bangladeshiMobile: "01811112222", status: "Processing", approvedVisa: "None", requiredFlightDate: "2026-04-02", actualFlightDate: "2026-04-06" },
            { name: "Nadia Islam", mobile: "0556666666", bangladeshiMobile: "01822223333", status: "Processing", approvedVisa: "None", requiredFlightDate: "2026-04-02", actualFlightDate: "2026-04-06" }
        ]
    }
];

const fingerStatusOptions = ["Select", "Pending", "Done", "NFC Problem"];
const approvedVisaOptions = ["Approved", "Partially Approved", "None", "Processing", "Cancel"];
const holdReasonOptions = ["Reschedule by Client", "Reschedule by BMT", "NFC Problem"];

let currentHoldInvoiceIndex = null;
let currentHoldPassengerIndex = null;

const elements = {
    fingerprintAdminSection: document.getElementById('fingerprintAdminSection'),
    fingerprintAdminTableBody: document.getElementById('fingerprintAdminTableBody'),
    fingerprintAdminEmpty: document.getElementById('fingerprintAdminEmpty'),
};

function renderFingerprintAdminIndex() {
    if (!elements.fingerprintAdminTableBody) return;
    
    elements.fingerprintAdminTableBody.innerHTML = '';
    
    if (!fingerprintAdminIndex || fingerprintAdminIndex.length === 0) {
        if (elements.fingerprintAdminEmpty) elements.fingerprintAdminEmpty.style.display = 'block';
        return;
    }
    
    if (elements.fingerprintAdminEmpty) elements.fingerprintAdminEmpty.style.display = 'none';
    
    fingerprintAdminIndex.forEach((invoice, invoiceIndex) => {
        const passengerCount = invoice.passengers ? invoice.passengers.length : 0;
        
        invoice.passengers.forEach((passenger, passengerIndex) => {
            const tr = document.createElement('tr');
            tr.className = 'border-b border-slate-200';
            
            const isFirstPassenger = passengerIndex === 0;
            const isLastPassenger = passengerIndex === passengerCount - 1;
            const isOddInvoice = invoiceIndex % 2 === 1;
            
            tr.style.borderLeftWidth = '3px';
            tr.style.borderLeftStyle = 'solid';
            tr.classList.add(isOddInvoice ? 'border-l-blue-500' : 'border-l-orange-500');
            if (isLastPassenger) {
                tr.style.borderBottomWidth = '2px';
                tr.style.borderBottomColor = '#94a3b8';
            }
            
            let html = '';
            
            if (isFirstPassenger) {
                html += `
                    <td class="px-3 py-2 text-slate-800 font-medium">${invoice.invoiceNo}</td>
                    <td class="px-3 py-2 text-slate-600">${invoice.bookingDate}</td>
                    <td class="px-3 py-2 text-slate-600">${invoice.customerName}</td>
                    <td class="px-3 py-2 text-slate-600">${passengerCount}</td>
                    <td class="px-3 py-2 text-slate-600 whitespace-pre-line">${invoice.customerMobile}${passenger.bangladeshiMobile ? '\n' + passenger.bangladeshiMobile : ''}</td>
                    <td class="px-3 py-2 text-slate-600">${invoice.district}</td>
                    <td class="px-3 py-2 text-slate-600">${invoice.fingerDeadline}</td>
                    <td class="px-3 py-2 text-right text-slate-800 font-medium">
                        ${invoice.fingerprintOffice === 'Office' ? 'N/A' : invoice.fingerCost + ' SAR'}
                    </td>
                    <td class="px-3 py-2 text-slate-600">${invoice.fingerprintOffice || '-'}</td>
                    <td class="px-3 py-2">
                        <select onchange="updateAssignStaff(${invoiceIndex}, this.value)" ${invoice.fingerprintOffice === 'Office' ? 'disabled' : ''} class="text-xs border border-slate-300 rounded px-2 py-1 bg-white">
                            ${fingerprintStaffOptions.map(opt => `<option value="${opt}" ${invoice.assignedStaff === opt ? 'selected' : ''}>${opt}</option>`).join('')}
                        </select>
                    </td>
                `;
            } else {
                html += `
                    <td class="px-3 py-2"></td>
                    <td class="px-3 py-2"></td>
                    <td class="px-3 py-2"></td>
                    <td class="px-3 py-2"></td>
                    <td class="px-3 py-2"></td>
                    <td class="px-3 py-2"></td>
                    <td class="px-3 py-2"></td>
                    <td class="px-3 py-2"></td>
                    <td class="px-3 py-2"></td>
                    <td class="px-3 py-2"></td>
                `;
            }
            
            html += `
                <td class="px-3 py-2 text-slate-600">${passenger.name}</td>
                <td class="px-3 py-2 text-slate-600">${passenger.approvedVisa || '-'}</td>
                <td class="px-3 py-2 text-slate-600">${passenger.requiredFlightDate || '-'}</td>
                <td class="px-3 py-2 text-slate-600">${passenger.actualFlightDate || '-'}</td>
            `;
            
            tr.innerHTML = html;
            elements.fingerprintAdminTableBody.appendChild(tr);
        });
    });
}

function updateAssignStaff(invoiceIndex, staff) {
    fingerprintAdminIndex[invoiceIndex].assignedStaff = staff;
    showToast('Staff assigned');
}

function updateFingerprintOffice(invoiceIndex, value) {
    fingerprintAdminIndex[invoiceIndex].fingerprintOffice = value;
    showToast('Fingerprint office updated');
    renderFingerprintAdminIndex();
}

function updateFingerprintStatus(invoiceIndex, passengerIndex, status) {
    fingerprintAdminIndex[invoiceIndex].passengers[passengerIndex].status = status;
    showToast('Status updated');
}

function updateFingerprintCost(invoiceIndex, cost) {
    fingerprintAdminIndex[invoiceIndex].fingerCost = parseFloat(cost) || 0;
    showToast('Cost updated');
}

function updateFingerprintCharge(invoiceIndex, charge) {
    fingerprintAdminIndex[invoiceIndex].fingerCharge = parseFloat(charge) || 0;
    showToast('Fingerprint charge updated');
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    renderFingerprintAdminIndex();
});