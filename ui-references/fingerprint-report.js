// ============================================
// Fingerprint Report Page JavaScript
// ============================================

// Excel date serial to JS Date converter
function excelDateToJS(serial) {
    if (!serial) return '-';
    const excelEpoch = new Date(Date.UTC(1899, 11, 30));
    const date = new Date(excelEpoch.getTime() + serial * 86400000);
    const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    const day = String(date.getUTCDate()).padStart(2, '0');
    const month = months[date.getUTCMonth()];
    const year = date.getUTCFullYear();
    return `${day}-${month}-${year}`;
}

// Format currency
function formatCurrency(amount, currency = 'SAR') {
    if (amount === null || amount === undefined || amount === '') return '-';
    return `${parseFloat(amount).toFixed(2)} ${currency}`;
}

// Calculate Profit/Loss
function calculateProfitLoss(fingerCharge, costing) {
    return fingerCharge - costing;
}

// ============================================
// Dropdown Options
// ============================================
// REMOVED: const locationOptions = ["Select", "BMT-DHAKA", "Brahmanbaria", "Sylet", "DO", "Cumilla", "Chattogram", "BMT-CTG"];
// REMOVED: const fingerStatusOptions = ["Select", "Pending", "Done", "NFC Problem"];
// REMOVED: const approvedVisaOptions = ["Select", "Approved", "Partially Approved", "None", "Processing", "Cancel", "Hold & Ask for next Finger date?"];
const statusOptions = ["Select", "Done", "Hold By BMT", "Hold By Client", "Cancel", "Reschedule by Client", "Reschedule by BMT", "NFC Problem", "Approved", "Partially Approved", "Processing"];
const requiredFlightOptions = ["Select", "Booking Deadline", "As Per Visa", "Flexible"];

// ============================================
// Report Data Structure
// ============================================
const fingerprintReportData = [
    {
        invoiceNo: "13921120",
        bookingDate: 46028,
        guardianName: "ALHAZ MIAH",
        fingerCharge: 0,
        costing: 0,
        fingerDeadline: 46038,
        passengers: [
            {
                passengerName: "MST KHUSHNAHER",
                passportNo: "A21229687",
                mobile: "05318095334\n01763225376",
                location: "BMT-DHAKA",
                address: "House 12, Road 5, Dhanmondi, Dhaka 1205, Bangladesh",
                completedDate: 46038,
                fingerStatus: "Done",
                approvedVisa: "Approved",
                requiredFlight: "Booking Deadline",
                actualFlight: "Same as Booking",
                status: "Done",
                remarks: "-",
                rescheduleHistory: []
            }
        ]
    },
    {
        invoiceNo: "13972121",
        bookingDate: 46033,
        guardianName: "KABIR NURU MIAH",
        fingerCharge: 6000,
        costing: 5000,
        fingerDeadline: 46043,
        passengers: [
            {
                passengerName: "CHADNE AKTER",
                passportNo: "A20837081",
                mobile: "05364497244\n01781088228",
                location: "Brahmanbaria",
                address: "Village: K sadar, Thana: Brahmanbaria, District: Brahmanbaria",
                completedDate: 46043,
                fingerStatus: "Done",
                approvedVisa: "Partially Approved",
                requiredFlight: "Booking Deadline",
                actualFlight: "Same as Booking",
                status: "Hold By BMT",
                remarks: "-",
                rescheduleHistory: []
            },
            {
                passengerName: "RAIYAN AL RAFI",
                passportNo: "A20837083",
                mobile: "05364497244\n01781088228",
                location: "DO",
                address: "House 25, Block B, Bashundhara R/A, Dhaka 1229",
                completedDate: "-",
                fingerStatus: "Done",
                approvedVisa: "Partially Approved",
                requiredFlight: "Booking Deadline",
                actualFlight: "Same as Booking",
                status: "Hold By Client",
                remarks: "-",
                rescheduleHistory: [
                    {
                        step: "Step 2",
                        nextFingerDeadline: "20 Days delayed",
                        completedDate: "-",
                        fingerStatus: "Reschedule by Client",
                        approvedVisa: "None",
                        requiredFlight: "Booking Deadline",
                        actualFlight: "-",
                        remarks: "-"
                    }
                ]
            },
            {
                passengerName: "UMME AYMAN RAFIHA",
                passportNo: "A20837082",
                mobile: "05364497244\n01781088228",
                location: "DO",
                address: "House 25, Block B, Bashundhara R/A, Dhaka 1229",
                completedDate: "-",
                fingerStatus: "NFC Problem",
                approvedVisa: "Hold & Ask for next Finger date?",
                requiredFlight: "Booking Deadline",
                actualFlight: "Deadline should be Change",
                status: "Reschedule by BMT",
                remarks: "-",
                rescheduleHistory: []
            },
            {
                passengerName: "RADIFA BINTA KABIR",
                passportNo: "A20837084",
                mobile: "05364497244\n01781088228",
                location: "DO",
                address: "House 25, Block B, Bashundhara R/A, Dhaka 1229",
                completedDate: "-",
                fingerStatus: "NFC Problem",
                approvedVisa: "Hold & Ask for next Finger date?",
                requiredFlight: "Booking Deadline",
                actualFlight: "Deadline should be Change",
                status: "Reschedule by BMT",
                remarks: "-",
                rescheduleHistory: [
                    {
                        step: "Step 2",
                        nextFingerDeadline: "10 Days delayed",
                        completedDate: "-",
                        fingerStatus: "Reschedule by Client",
                        approvedVisa: "None",
                        requiredFlight: "Booking Deadline",
                        actualFlight: "-",
                        remarks: "-"
                    },
                    {
                        step: "Step 3",
                        nextFingerDeadline: "15 Days delayed",
                        completedDate: "-",
                        fingerStatus: "Reschedule by Client",
                        approvedVisa: "None",
                        requiredFlight: "Booking Deadline",
                        actualFlight: "-",
                        remarks: "-"
                    }
                ]
            }
        ]
    },
    {
        invoiceNo: "13972123",
        bookingDate: 46034,
        guardianName: "Mamun Bepari",
        fingerCharge: 5500,
        costing: 4500,
        fingerDeadline: 46044,
        passengers: [
            {
                passengerName: "Morium",
                passportNo: "A20837099",
                mobile: "05647438393\n01746858593",
                location: "Sylet",
                address: "Zinda Bazar, Sylhet Sadar, Sylhet 3100",
                completedDate: 46044,
                fingerStatus: "Done",
                approvedVisa: "Approved",
                requiredFlight: "Booking Deadline",
                actualFlight: "Same as Booking",
                status: "NFC Problem",
                remarks: "-",
                rescheduleHistory: []
            },
            {
                passengerName: "Munira",
                passportNo: "A20643846",
                mobile: "05464748484\n01781088228",
                location: "DO",
                address: "Plot 45, Road 8, Gulshan 1, Dhaka 1212",
                completedDate: 46044,
                fingerStatus: "Done",
                approvedVisa: "Approved",
                requiredFlight: "Booking Deadline",
                actualFlight: "Same as Booking",
                status: "Cancel",
                remarks: "-",
                rescheduleHistory: []
            }
        ]
    },
    {
        invoiceNo: "13972124",
        bookingDate: 46034,
        guardianName: "Arif Hossain",
        fingerCharge: 0,
        costing: 0,
        fingerDeadline: 46044,
        passengers: [
            {
                passengerName: "Bilkis Begum",
                passportNo: "BN4648563",
                mobile: "05364497244\n01836466464",
                location: "DO",
                address: "House 8, Lane 3, Mirpur 10, Dhaka 1216",
                completedDate: 46044,
                fingerStatus: "Done",
                approvedVisa: "Approved",
                requiredFlight: "Booking Deadline",
                actualFlight: "Same as Booking",
                status: "-",
                remarks: "-",
                rescheduleHistory: []
            }
        ]
    },
    {
        invoiceNo: "13972125",
        bookingDate: 46035,
        guardianName: "Sahin Mia",
        fingerCharge: 0,
        costing: 0,
        fingerDeadline: 46045,
        passengers: [
            {
                passengerName: "Faiza",
                passportNo: "AA4657956",
                mobile: "05647484844\n01616373373",
                location: "DO",
                address: "Flat 3B, House 50, Road 12, Dhanmondi, Dhaka 1209",
                completedDate: "-",
                fingerStatus: "NFC Problem",
                approvedVisa: "Cancel",
                requiredFlight: "Booking Deadline",
                actualFlight: "-",
                status: "Approved",
                remarks: "-",
                rescheduleHistory: [
                    {
                        step: "Step 2",
                        nextFingerDeadline: "Next Finger Deadline: 15-Mar-2026",
                        completedDate: "-",
                        fingerStatus: "NFC Problem",
                        approvedVisa: "Cancel",
                        requiredFlight: "Booking Deadline",
                        actualFlight: "-",
                        remarks: "-"
                    },
                    {
                        step: "Step 3",
                        nextFingerDeadline: "Completed Date: 20-Mar-2026",
                        completedDate: "20-Mar-2026",
                        fingerStatus: "Done",
                        approvedVisa: "Approved",
                        requiredFlight: "Booking Deadline",
                        actualFlight: "Same as Booking",
                        remarks: "-"
                    }
                ]
            }
        ]
    },
    {
        invoiceNo: "13972126",
        bookingDate: 46037,
        guardianName: "Fahim",
        fingerCharge: 5000,
        costing: 4000,
        fingerDeadline: 46047,
        passengers: [
            {
                passengerName: "Sanjida",
                passportNo: "A20837099",
                mobile: "05647477393\n01746858593",
                location: "Cumilla",
                address: "College Road, Cumilla Sadar, Cumilla 3500",
                completedDate: "-",
                fingerStatus: "Reschedule by Client",
                approvedVisa: "None",
                requiredFlight: "Booking Deadline",
                actualFlight: "Deadline should be Change",
                status: "Partially Approved",
                remarks: "20 Days delayed",
                rescheduleHistory: [
                    {
                        step: "Step 2",
                        nextFingerDeadline: "20 Days delayed",
                        completedDate: "-",
                        fingerStatus: "Reschedule by Client",
                        approvedVisa: "None",
                        requiredFlight: "Booking Deadline",
                        actualFlight: "-",
                        remarks: "-"
                    },
                    {
                        step: "Step 3",
                        nextFingerDeadline: "20 Days delayed",
                        completedDate: "-",
                        fingerStatus: "Reschedule by Client",
                        approvedVisa: "None",
                        requiredFlight: "Booking Deadline",
                        actualFlight: "-",
                        remarks: "-"
                    }
                ]
            }
        ]
    },
    {
        invoiceNo: "13972127",
        bookingDate: 46040,
        guardianName: "Rahman Khan",
        fingerCharge: 8000,
        costing: 6500,
        fingerDeadline: 46050,
        passengers: [
            {
                passengerName: "Jamal Ahmed",
                passportNo: "A20837100",
                mobile: "05111111111\n01711111111",
                location: "BMT-DHAKA",
                address: "Block C, House 15, Mirpur DOHS, Dhaka 1216",
                completedDate: 46050,
                fingerStatus: "Done",
                approvedVisa: "Approved",
                requiredFlight: "Booking Deadline",
                actualFlight: "Same as Booking",
                status: "Done",
                remarks: "-",
                rescheduleHistory: []
            },
            {
                passengerName: "Nasrin Begum",
                passportNo: "A20837101",
                mobile: "05222222222\n01722222222",
                location: "BMT-DHAKA",
                address: "Flat 5A, Road 7, Banani, Dhaka 1213",
                completedDate: 46050,
                fingerStatus: "Done",
                approvedVisa: "Approved",
                requiredFlight: "Booking Deadline",
                actualFlight: "Same as Booking",
                status: "Done",
                remarks: "-",
                rescheduleHistory: []
            },
            {
                passengerName: "Hasan Ali",
                passportNo: "A20837102",
                mobile: "05333333333\n01733333333",
                location: "Chattogram",
                address: "House 22, GEC Circle, Chittagong 4000",
                completedDate: "-",
                fingerStatus: "Pending",
                approvedVisa: "Processing",
                requiredFlight: "Booking Deadline",
                actualFlight: "-",
                status: "Processing",
                remarks: "Awaiting finger appointment",
                rescheduleHistory: []
            }
        ]
    },
    {
        invoiceNo: "13972128",
        bookingDate: 46042,
        guardianName: "Abdul Karim",
        fingerCharge: 3500,
        costing: 3000,
        fingerDeadline: 46052,
        passengers: [
            {
                passengerName: "Rashida Parvin",
                passportNo: "A20837103",
                mobile: "05444444444\n01744444444",
                location: "Sylhet",
                address: "Uposhohor, Sylhet Sadar, Sylhet 3100",
                completedDate: 46052,
                fingerStatus: "Done",
                approvedVisa: "Approved",
                requiredFlight: "Booking Deadline",
                actualFlight: "Same as Booking",
                status: "Done",
                remarks: "-",
                rescheduleHistory: []
            },
            {
                passengerName: "Abdul Momen",
                passportNo: "A20837104",
                mobile: "05555555555\n01755555555",
                location: "Sylhet",
                address: "Tower Hill, Sylhet 3100",
                completedDate: 46052,
                fingerStatus: "Done",
                approvedVisa: "Approved",
                requiredFlight: "Booking Deadline",
                actualFlight: "Same as Booking",
                status: "Done",
                remarks: "-",
                rescheduleHistory: []
            }
        ]
    }
];

// ============================================
// DOM Elements
// ============================================
const elements = {
    reportTableBody: document.getElementById('reportTableBody'),
    summaryTotalInvoices: document.getElementById('summaryTotalInvoices'),
    summaryTotalPAX: document.getElementById('summaryTotalPAX'),
    summaryTotalFingerCharge: document.getElementById('summaryTotalFingerCharge'),
    summaryTotalCosting: document.getElementById('summaryTotalCosting'),
    summaryTotalProfit: document.getElementById('summaryTotalProfit'),
    summaryTotalLoss: document.getElementById('summaryTotalLoss'),
    summaryNetProfitLoss: document.getElementById('summaryNetProfitLoss'),
    detailsModal: document.getElementById('detailsModal'),
    detailsModalContent: document.getElementById('detailsModalContent'),
};

// ============================================
// Render Report Table
// ============================================
function renderFingerprintReport() {
    if (!elements.reportTableBody) return;
    
    elements.reportTableBody.innerHTML = '';
    
    let totalFingerCharge = 0;
    let totalCosting = 0;
    let totalPAX = 0;
    
    fingerprintReportData.forEach((invoice, invoiceIndex) => {
        const isOddInvoice = invoiceIndex % 2 !== 0;
        
        totalFingerCharge += invoice.fingerCharge || 0;
        totalCosting += invoice.costing || 0;
        totalPAX += invoice.passengers.length;
        
        invoice.passengers.forEach((passenger, passengerIndex) => {
            const isFirstPassenger = passengerIndex === 0;
            const isLastPassenger = passengerIndex === invoice.passengers.length - 1;
            
            const tr = document.createElement('tr');
            tr.className = 'table-row-fp cursor-pointer hover:bg-yellow-50 ' + 
                (isOddInvoice ? 'bg-slate-50 ' : 'bg-white ') +
                'border-l-4 ' + 
                (isOddInvoice ? 'border-l-blue-600' : 'border-l-orange-500') +
                (isLastPassenger ? ' border-b-2 border-slate-400' : '');
            
            tr.onclick = () => showPassengerDetails(invoice, passenger, invoiceIndex, passengerIndex);
            
            let html = '';
            
            // Invoice-level columns (first passenger row only)
            if (isFirstPassenger) {
                html += `
                    <td class="px-2 py-2 text-xs text-center border-r border-gray-200 font-medium">${invoice.invoiceNo}</td>
                    <td class="px-2 py-2 text-xs text-left border-r border-gray-200">${invoice.guardianName || '-'}</td>
                    <td class="px-2 py-2 text-xs text-center border-r border-gray-200">${excelDateToJS(invoice.bookingDate)}</td>
                `;
            } else {
                html += `
                    <td class="px-2 py-2 border-r border-gray-200"></td>
                    <td class="px-2 py-2 border-r border-gray-200"></td>
                    <td class="px-2 py-2 border-r border-gray-200"></td>
                `;
            }
            
            // Passenger columns (every row) - with dropdowns
            html += `
                <td class="px-2 py-2 text-xs text-left border-r border-gray-200">${passenger.passengerName || '-'}</td>
                <td class="px-2 py-2 text-xs text-center border-r border-gray-200">${passenger.passportNo || '-'}</td>
                <td class="px-2 py-2 text-xs text-left border-r border-gray-200 whitespace-pre-line">${passenger.mobile || '-'}</td>
            `;
            
            // Invoice-level columns (first passenger row only)
            if (isFirstPassenger) {
                html += `
                    <td class="px-2 py-2 text-xs text-right border-r border-gray-200 font-medium text-green-700">${formatCurrency(invoice.fingerCharge)}</td>
                    <td class="px-2 py-2 text-xs text-right border-r border-gray-200">${formatCurrency(invoice.costing)}</td>
                    <td class="px-2 py-2 text-xs text-center border-r border-gray-200">${excelDateToJS(invoice.fingerDeadline)}</td>
                `;
            } else {
                html += `
                    <td class="px-2 py-2 border-r border-gray-200"></td>
                    <td class="px-2 py-2 border-r border-gray-200"></td>
                    <td class="px-2 py-2 border-r border-gray-200"></td>
                `;
            }
            
            // REMOVED: Location dropdown
            /*
            html += `<td class="px-2 py-2 text-xs text-left border-r border-gray-200">
                <select onchange="updateLocation(${invoiceIndex}, ${passengerIndex}, this.value)" class="w-full text-xs border border-gray-300 rounded px-1 py-1 bg-white cursor-pointer">
                    ${locationOptions.map(opt => `<option value="${opt}" ${passenger.location === opt ? 'selected' : ''}>${opt}</option>`).join('')}
                </select>
            </td>`;
            */
            
            html += `
                <td class="px-2 py-2 text-xs text-center border-r border-gray-200">${passenger.completedDate === '-' ? '-' : excelDateToJS(passenger.completedDate)}</td>
            `;
            
            // REMOVED: Finger Status dropdown
            /*
            html += `<td class="px-2 py-2 text-xs text-center border-r border-gray-200">
                <select onchange="updateFingerStatus(${invoiceIndex}, ${passengerIndex}, this.value)" class="w-full text-xs border border-gray-300 rounded px-1 py-1 bg-white cursor-pointer">
                    ${fingerStatusOptions.map(opt => `<option value="${opt}" ${passenger.fingerStatus === opt ? 'selected' : ''}>${opt}</option>`).join('')}
                </select>
            </td>`;
            */
            
            // REMOVED: Approved VISA/Ticket dropdown
            /*
            html += `<td class="px-2 py-2 text-xs text-center border-r border-gray-200">
                <select onchange="updateApprovedVisa(${invoiceIndex}, ${passengerIndex}, this.value)" class="w-full text-xs border border-gray-300 rounded px-1 py-1 bg-white cursor-pointer">
                    ${approvedVisaOptions.map(opt => `<option value="${opt}" ${passenger.approvedVisa === opt ? 'selected' : ''}>${opt}</option>`).join('')}
                </select>
            </td>`;
            */
            
            // REMOVED: Required Flight dropdown (now read-only)
            /*
            html += `<td class="px-2 py-2 text-xs text-left border-r border-gray-200">
                <select onchange="updateRequiredFlight(${invoiceIndex}, ${passengerIndex}, this.value)" class="w-full text-xs border border-gray-300 rounded px-1 py-1 bg-white cursor-pointer">
                    ${requiredFlightOptions.map(opt => `<option value="${opt}" ${passenger.requiredFlight === opt ? 'selected' : ''}>${opt}</option>`).join('')}
                </select>
            </td>`;
            */
            html += `<td class="px-2 py-2 text-xs text-left border-r border-gray-200">${passenger.requiredFlight || '-'}</td>`;
            
            html += `
                <td class="px-2 py-2 text-xs text-left border-r border-gray-200">${passenger.actualFlight || '-'}</td>
            `;
            
            // REMOVED: Status dropdown (now read-only)
            /*
            html += `<td class="px-2 py-2 text-xs text-center border-r border-gray-200">
                <select onchange="updateStatus(${invoiceIndex}, ${passengerIndex}, this.value)" class="w-full text-xs border border-gray-300 rounded px-1 py-1 bg-white cursor-pointer">
                    ${statusOptions.map(opt => `<option value="${opt}" ${passenger.status === opt ? 'selected' : ''}>${opt}</option>`).join('')}
                </select>
            </td>`;
            */
            html += `<td class="px-2 py-2 text-xs text-center border-r border-gray-200 font-medium">${passenger.status || '-'}</td>`;
            
            html += `
                <td class="px-2 py-2 text-xs text-left border-r border-gray-200">${passenger.remarks || '-'}</td>
            `;
            
            // Profit/Loss (first passenger row only)
            if (isFirstPassenger) {
                const profitLoss = calculateProfitLoss(invoice.fingerCharge, invoice.costing);
                const profitLossClass = profitLoss >= 0 ? 'text-green-600' : 'text-red-600';
                html += `
                    <td class="px-2 py-2 text-xs text-right font-semibold ${profitLossClass}">${formatCurrency(profitLoss)}</td>
                `;
            } else {
                html += `<td class="px-2 py-2"></td>`;
            }
            
            tr.innerHTML = html;
            elements.reportTableBody.appendChild(tr);
        });
    });
    
    // Update summary
    updateSummary(totalFingerCharge, totalCosting, totalPAX);
}

// ============================================
// Update Summary
// ============================================
function updateSummary(totalFingerCharge, totalCosting, totalPAX) {
    const profitLoss = totalFingerCharge - totalCosting;
    
    if (elements.summaryTotalInvoices) elements.summaryTotalInvoices.textContent = fingerprintReportData.length;
    if (elements.summaryTotalPAX) elements.summaryTotalPAX.textContent = totalPAX;
    if (elements.summaryTotalFingerCharge) elements.summaryTotalFingerCharge.textContent = formatCurrency(totalFingerCharge);
    if (elements.summaryTotalCosting) elements.summaryTotalCosting.textContent = formatCurrency(totalCosting);
    
    if (profitLoss >= 0) {
        if (elements.summaryTotalProfit) elements.summaryTotalProfit.textContent = formatCurrency(profitLoss);
        if (elements.summaryTotalLoss) elements.summaryTotalLoss.textContent = '-';
    } else {
        if (elements.summaryTotalProfit) elements.summaryTotalProfit.textContent = '-';
        if (elements.summaryTotalLoss) elements.summaryTotalLoss.textContent = formatCurrency(Math.abs(profitLoss));
    }
    
    if (elements.summaryNetProfitLoss) {
        elements.summaryNetProfitLoss.textContent = formatCurrency(profitLoss);
        elements.summaryNetProfitLoss.className = profitLoss >= 0 ? 'text-xs font-bold text-green-700' : 'text-xs font-bold text-red-700';
    }
}

// ============================================
// Update Functions for Dropdowns
// ============================================
// REMOVED: function updateLocation(invoiceIndex, passengerIndex, value) {
// REMOVED:     fingerprintReportData[invoiceIndex].passengers[passengerIndex].location = value;
// REMOVED:     showToast('Location updated');
// REMOVED: }

// REMOVED: function updateFingerStatus(invoiceIndex, passengerIndex, value) {
// REMOVED:     fingerprintReportData[invoiceIndex].passengers[passengerIndex].fingerStatus = value;
// REMOVED:     showToast('Finger Status updated');
// REMOVED: }

// REMOVED: function updateApprovedVisa(invoiceIndex, passengerIndex, value) {
// REMOVED:     fingerprintReportData[invoiceIndex].passengers[passengerIndex].approvedVisa = value;
// REMOVED:     showToast('Approved VISA/Ticket updated');
// REMOVED: }

// REMOVED: function updateRequiredFlight(invoiceIndex, passengerIndex, value) {
// REMOVED:     fingerprintReportData[invoiceIndex].passengers[passengerIndex].requiredFlight = value;
// REMOVED:     showToast('Required Flight updated');
// REMOVED: }

// REMOVED: function updateStatus(invoiceIndex, passengerIndex, value) {
// REMOVED:     fingerprintReportData[invoiceIndex].passengers[passengerIndex].status = value;
// REMOVED:     showToast('Status updated');
// REMOVED: }

// ============================================
// Show Passenger Details Modal
// ============================================
function showPassengerDetails(invoice, passenger, invoiceIndex, passengerIndex) {
    const profitLoss = calculateProfitLoss(invoice.fingerCharge, invoice.costing);
    
    let rescheduleHtml = '';
    if (passenger.rescheduleHistory && passenger.rescheduleHistory.length > 0) {
        rescheduleHtml = `
            <div class="mt-6">
                <h4 class="text-sm font-bold text-gray-700 mb-3">Reschedule History</h4>
                <div class="overflow-x-auto">
                    <table class="w-full text-xs border border-gray-300">
                        <thead class="bg-gray-100">
                            <tr>
                                <th class="px-3 py-2 text-left font-medium border-b border-r border-gray-300">Step</th>
                                <th class="px-3 py-2 text-left font-medium border-b border-r border-gray-300">Next Finger Deadline</th>
                                <th class="px-3 py-2 text-left font-medium border-b border-r border-gray-300">Completed Date</th>
                                <th class="px-3 py-2 text-left font-medium border-b border-r border-gray-300">Finger Status</th>
                                <th class="px-3 py-2 text-left font-medium border-b border-r border-gray-300">Approved VISA/Ticket</th>
                                <th class="px-3 py-2 text-left font-medium border-b border-r border-gray-300">Required Flight</th>
                                <th class="px-3 py-2 text-left font-medium border-b">Remarks</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${passenger.rescheduleHistory.map(step => `
                                <tr class="border-b border-gray-200 hover:bg-gray-50">
                                    <td class="px-3 py-2 border-r border-gray-200 font-medium">${step.step}</td>
                                    <td class="px-3 py-2 border-r border-gray-200">${step.nextFingerDeadline}</td>
                                    <td class="px-3 py-2 border-r border-gray-200">${step.completedDate}</td>
                                    <td class="px-3 py-2 border-r border-gray-200">${step.fingerStatus}</td>
                                    <td class="px-3 py-2 border-r border-gray-200">${step.approvedVisa}</td>
                                    <td class="px-3 py-2 border-r border-gray-200">${step.requiredFlight}</td>
                                    <td class="px-3 py-2">${step.remarks}</td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
            </div>
        `;
    } else {
        rescheduleHtml = `
            <div class="mt-6 p-4 bg-gray-50 rounded-lg">
                <p class="text-sm text-gray-500 text-center">No reschedule history for this passenger</p>
            </div>
        `;
    }
    
    const modalContent = `
        <div class="space-y-4">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="text-xs font-medium text-gray-500">Invoice No</label>
                    <p class="text-sm font-semibold">${invoice.invoiceNo}</p>
                </div>
                <div>
                    <label class="text-xs font-medium text-gray-500">Guardian Name</label>
                    <p class="text-sm">${invoice.guardianName || '-'}</p>
                </div>
                <div>
                    <label class="text-xs font-medium text-gray-500">Booking Date</label>
                    <p class="text-sm">${excelDateToJS(invoice.bookingDate)}</p>
                </div>
                <div>
                    <label class="text-xs font-medium text-gray-500">Finger Deadline</label>
                    <p class="text-sm">${excelDateToJS(invoice.fingerDeadline)}</p>
                </div>
            </div>
            
            <div class="border-t border-gray-200 pt-4">
                <h4 class="text-sm font-bold text-gray-700 mb-3">Passenger Information</h4>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="text-xs font-medium text-gray-500">Passenger Name</label>
                        <p class="text-sm">${passenger.passengerName}</p>
                    </div>
                    <div>
                        <label class="text-xs font-medium text-gray-500">Passport No</label>
                        <p class="text-sm">${passenger.passportNo}</p>
                    </div>
                    <div>
                        <label class="text-xs font-medium text-gray-500">Mobile</label>
                        <p class="text-sm whitespace-pre-line">${passenger.mobile}</p>
                    </div>
                    <div>
                        <label class="text-xs font-medium text-gray-500">Location</label>
                        <p class="text-sm">${passenger.location}</p>
                    </div>
                    <div class="col-span-2">
                        <label class="text-xs font-medium text-gray-500">Detailed Address</label>
                        <p class="text-sm">${passenger.address || '-'}</p>
                    </div>
                </div>
            </div>
            
            <div class="border-t border-gray-200 pt-4">
                <h4 class="text-sm font-bold text-gray-700 mb-3">Fingerprint Status</h4>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="text-xs font-medium text-gray-500">Completed Date</label>
                        <p class="text-sm">${passenger.completedDate === '-' ? '-' : excelDateToJS(passenger.completedDate)}</p>
                    </div>
                    <div>
                        <label class="text-xs font-medium text-gray-500">Finger Status</label>
                        <p class="text-sm">${passenger.fingerStatus}</p>
                    </div>
                    <div>
                        <label class="text-xs font-medium text-gray-500">Approved VISA/Ticket</label>
                        <p class="text-sm">${passenger.approvedVisa}</p>
                    </div>
                    <div>
                        <label class="text-xs font-medium text-gray-500">Status</label>
                        <p class="text-sm font-medium">${passenger.status}</p>
                    </div>
                </div>
            </div>
            
            <div class="border-t border-gray-200 pt-4">
                <h4 class="text-sm font-bold text-gray-700 mb-3">Flight Information</h4>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="text-xs font-medium text-gray-500">Required Flight</label>
                        <p class="text-sm">${passenger.requiredFlight}</p>
                    </div>
                    <div>
                        <label class="text-xs font-medium text-gray-500">Actual Flight</label>
                        <p class="text-sm">${passenger.actualFlight}</p>
                    </div>
                </div>
            </div>
            
            <div class="border-t border-gray-200 pt-4">
                <h4 class="text-sm font-bold text-gray-700 mb-3">Financial Summary</h4>
                <div class="grid grid-cols-3 gap-4">
                    <div>
                        <label class="text-xs font-medium text-gray-500">Finger Charge</label>
                        <p class="text-sm font-semibold text-green-700">${formatCurrency(invoice.fingerCharge)}</p>
                    </div>
                    <div>
                        <label class="text-xs font-medium text-gray-500">Costing</label>
                        <p class="text-sm">${formatCurrency(invoice.costing)}</p>
                    </div>
                    <div>
                        <label class="text-xs font-medium text-gray-500">Profit/Loss</label>
                        <p class="text-sm font-semibold ${profitLoss >= 0 ? 'text-green-700' : 'text-red-700'}">${formatCurrency(profitLoss)}</p>
                    </div>
                </div>
            </div>
            
            ${rescheduleHtml}
        </div>
    `;
    
    if (elements.detailsModalContent) {
        elements.detailsModalContent.innerHTML = modalContent;
    }
    
    if (elements.detailsModal) {
        elements.detailsModal.classList.remove('hidden');
    }
}

// ============================================
// Close Modal
// ============================================
function closeDetailsModal() {
    if (elements.detailsModal) {
        elements.detailsModal.classList.add('hidden');
    }
}

// ============================================
// Initialize
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    renderFingerprintReport();
});
