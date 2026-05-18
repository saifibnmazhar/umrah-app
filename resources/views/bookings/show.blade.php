@extends('layouts.app')
@section('title', 'Invoice Details')
@section('content')
<div class="max-w-5xl mx-auto">
    <div id="invoiceDetailsContent" class="space-y-6">
        {{-- Header Section --}}
        <div class="bg-white rounded-xl shadow-lg p-6">
            <div class="flex justify-between items-start mb-6 pb-4 border-b border-slate-200">
                <div class="flex items-center gap-3">
                    <a href="{{ route('bookings.index') }}" class="text-slate-400 hover:text-slate-600">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                        </svg>
                    </a>
                    <div>
                        <h2 class="text-xl font-semibold text-slate-800">Invoice Details</h2>
                        <p class="text-slate-500 text-sm mt-1">ID: {{ $booking->id }}</p>
                    </div>
                </div>
                <div class="flex gap-2">
                    <button onclick="window.print()" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition font-medium text-sm">
                        Print
                    </button>
                    <a href="{{ route('bookings.edit', $booking->id) }}" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-medium text-sm">
                        Edit
                    </a>
                </div>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                <div>
                    <span class="text-slate-500 text-sm">Invoice No</span>
                    <p class="text-slate-800 font-medium">{{ $booking->invoice_id ?? 'N/A' }}</p>
                </div>
                <div>
                    <span class="text-slate-500 text-sm">Booking Date</span>
                    <p class="text-slate-800 font-medium">{{ $booking->created_at->format('Y-m-d') }}</p>
                </div>
                <div>
                    <span class="text-slate-500 text-sm">Customer</span>
                    <p class="text-slate-800 font-medium">{{ $booking->customer->name ?? 'N/A' }}</p>
                </div>
                <div>
                    <span class="text-slate-500 text-sm">Status</span>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $booking->invoice && $booking->invoice->balance <= 0 ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-700' }}">
                        {{ $booking->invoice && $booking->invoice->balance <= 0 ? 'Paid' : 'Due' }}
                    </span>
                </div>
            </div>

            <div class="grid grid-cols-3 gap-4 pt-4 border-t border-slate-200">
                <div>
                    <span class="text-slate-500 text-sm">Total Value</span>
                    <p class="text-xl font-bold text-slate-800">{{ number_format($booking->invoice?->total_amount ?? 0) }} SAR</p>
                </div>
                <div>
                    <span class="text-slate-500 text-sm">Total Paid</span>
                    <p class="text-xl font-bold text-green-600">{{ number_format($booking->invoice?->paid_amount ?? 0) }} SAR</p>
                </div>
                <div>
                    <span class="text-slate-500 text-sm">Due</span>
                    <p class="text-xl font-bold text-red-600">{{ number_format($booking->invoice?->balance ?? 0) }} SAR</p>
                </div>
            </div>
            <div class="mt-4 pt-4 border-t border-slate-200 flex justify-end">
                <button type="button" onclick="openDiscountModal()" class="text-sm bg-slate-200 hover:bg-slate-300 text-slate-600 px-3 py-1 rounded">
                    Discount
                </button>
            </div>
        </div>

        {{-- Passengers Section --}}
        <div class="bg-white rounded-xl shadow-lg p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold text-slate-700">Passengers</h3>
            </div>
            
            <div id="invoicePassengers" class="space-y-3">
            @forelse($booking->passengers as $index => $passenger)
            @php
                $passengerTotal = ($passenger->ticketFare?->fare ?? 0) + ($passenger->package_value ?? 0);
            @endphp
            <div class="flex justify-between items-center p-3 bg-slate-50 rounded-lg">
                <div>
                    <span class="font-medium text-slate-800">{{ $passenger->first_name ?? '' }} {{ $passenger->last_name ?? '' }}</span>
                    <span class="text-slate-500 text-sm ml-2">({{ $passenger->passport_no ?? 'N/A' }})</span>
                </div>
                <div class="flex items-center gap-3">
                    <span class="text-slate-800 font-medium">{{ number_format($passengerTotal) }} SAR</span>
                    <button onclick="viewPassengerDetails({{ $passenger->id }})" class="text-xs bg-slate-200 hover:bg-slate-300 text-slate-600 px-2 py-1 rounded">View</button>
                </div>
            </div>
            @empty
            <p class="text-sm text-slate-400 text-center py-4">No passengers found.</p>
            @endforelse
            </div>
            
            <div class="flex justify-end mt-4">
                <button onclick="addPassenger()" class="px-4 py-2 border-2 border-slate-700 text-slate-700 rounded-lg hover:bg-slate-50 transition font-medium text-sm">+ Add Passenger</button>
            </div>
        </div>

        {{-- Documents Section --}}
        <div class="grid grid-cols-2 gap-4">
            <div class="bg-slate-50 rounded-lg p-4">
                <div class="flex justify-between items-center mb-3 pb-2 border-b border-slate-200">
                    <h3 class="text-sm font-medium text-slate-500">Customer Documents</h3>
                    <div class="flex gap-2">
                        <input type="file" id="customerDocInput" class="hidden" accept=".pdf,image/*" multiple onchange="handleCustomerDocSelect(event)">
                        <button onclick="document.getElementById('customerDocInput').click()" class="px-3 py-1 bg-blue-600 text-white rounded hover:bg-blue-700 text-xs font-medium">Upload</button>
                        <button onclick="downloadAllCustomerDocs()" class="px-3 py-1 bg-green-600 text-white rounded hover:bg-green-700 text-xs font-medium">Download All</button>
                    </div>
                </div>
                <div id="customerDocumentsList" class="space-y-2 overflow-y-auto" style="max-height: 16rem;">
                    @forelse($booking->documents as $doc)
                    <div class="flex justify-between items-center bg-white p-2 rounded border border-slate-200">
                        <span class="text-sm text-slate-700 truncate">{{ $doc->display_name ?? 'Document' }}</span>
                        <button onclick="downloadDoc({{ $doc->id }})" class="text-blue-600 hover:text-blue-800 text-xs">Download</button>
                    </div>
                    @empty
                    <p class="text-sm text-slate-400">No customer documents</p>
                    @endforelse
                </div>
            </div>

            <div class="bg-slate-50 rounded-lg p-4">
                <div class="flex justify-between items-center mb-3 pb-2 border-b border-slate-200">
                    <h3 class="text-sm font-medium text-slate-500">Passenger Documents</h3>
                    <div class="flex gap-2">
                        <button onclick="downloadAllPassengerDocs()" class="px-3 py-1 bg-green-600 text-white rounded hover:bg-green-700 text-xs font-medium">Download All</button>
                    </div>
                </div>
                <div id="passengerDocumentsList" class="space-y-2 overflow-y-auto" style="max-height: 16rem;">
                    @forelse($booking->passengers->flatMap->documents() as $doc)
                    <div class="flex justify-between items-center bg-white p-2 rounded border border-slate-200">
                        <span class="text-sm text-slate-700 truncate">{{ $doc->display_name ?? 'Document' }}</span>
                        <button onclick="downloadDoc({{ $doc->id }})" class="text-blue-600 hover:text-blue-800 text-xs">Download</button>
                    </div>
                    @empty
                    <p class="text-sm text-slate-400">No passenger documents</p>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Action Buttons Row --}}
        <div class="flex justify-end gap-3">
            <button onclick="openReIssueModal()" class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-medium">
                Request Re-Issue
            </button>
            <button onclick="openAddTicketModal()" class="px-6 py-3 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition font-medium">
                Request Add. Tkt
            </button>
            <button onclick="openRefundModal()" class="px-6 py-3 bg-orange-500 text-white rounded-lg hover:bg-orange-600 transition font-medium">
                Request Refund
            </button>
            <button onclick="downloadAllDocs()" class="px-6 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 transition font-medium">
                Download All Docs
            </button>
            <button onclick="openPaymentModal()" class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-medium">
                Payment
            </button>
        </div>

        {{-- Payment History Tab --}}
        <div class="bg-white rounded-xl shadow-lg p-6">
            <h3 class="text-lg font-semibold text-slate-700 mb-4">Payment History</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-slate-600">
                        <tr>
                            <th class="px-3 py-2 text-left font-medium">Date</th>
                            <th class="px-3 py-2 text-left font-medium">Voucher No</th>
                            <th class="px-3 py-2 text-left font-medium">Method</th>
                            <th class="px-3 py-2 text-left font-medium">Trx ID</th>
                            <th class="px-3 py-2 text-right font-medium">Amount</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        @forelse($booking->payments as $payment)
                        <tr>
                            <td class="px-3 py-2 text-slate-600">{{ $payment->created_at->format('Y-m-d') }}</td>
                            <td class="px-3 py-2 text-slate-600">{{ $payment->voucher_no ?? '-' }}</td>
                            <td class="px-3 py-2 text-slate-600">{{ $payment->payment_method?->value ?? 'Cash' }}</td>
                            <td class="px-3 py-2 text-slate-600">{{ $payment->transaction_id ?? '-' }}</td>
                            <td class="px-3 py-2 text-right text-slate-800 font-medium">{{ number_format($payment->amount) }} SAR</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="px-3 py-4 text-center text-slate-500">No payments recorded</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Back Button --}}
        <div class="mt-6 pt-4 border-t border-slate-200">
            <a href="{{ route('bookings.index') }}" class="px-6 py-2 border border-slate-300 text-slate-600 rounded-lg hover:bg-slate-50 transition font-medium">
                Back to List
            </a>
        </div>
    </div>
</div>

{{-- Discount Modal --}}
<div id="discountModal" class="hidden fixed inset-0 z-50 flex items-center justify-center">
    <div class="modal-overlay absolute inset-0 bg-black/50" onclick="closeDiscountModal()"></div>
    <div class="modal-content relative bg-white rounded-xl shadow-2xl w-full max-w-md mx-4 p-6">
        <h3 class="text-xl font-semibold text-slate-800 mb-4">Apply Discount</h3>
        
        <div class="mb-4">
            <label class="block text-sm font-medium text-slate-700 mb-1">Original Total</label>
            <input type="text" id="discountOriginalTotal" readonly 
                value="{{ number_format($booking->invoice?->total_amount ?? 0) }}" 
                class="w-full px-4 py-2 border border-slate-200 rounded-lg bg-slate-50 text-slate-600">
        </div>
        
        <div class="mb-4">
            <label class="block text-sm font-medium text-slate-700 mb-1">Discount Type</label>
            <select id="discountType" onchange="calculateInvoiceDiscount()" 
                class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none bg-white">
                <option value="percentage" {{ $booking->discount_type?->value === 'percentage' ? 'selected' : '' }}>Percentage (%)</option>
                <option value="fixed" {{ $booking->discount_type?->value === 'fixed' ? 'selected' : '' }}>Fixed Amount (SAR)</option>
            </select>
        </div>
        
        <div class="mb-4">
            <label class="block text-sm font-medium text-slate-700 mb-1">Discount Value</label>
            <input type="number" id="discountValue" 
                value="{{ $booking->discount_value ?? 0 }}" 
                min="0" oninput="calculateInvoiceDiscount()" 
                class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none">
        </div>
        
        <div class="mb-4">
            <label class="block text-sm font-medium text-slate-700 mb-1">Discount Amount (SAR)</label>
            <input type="text" id="discountAmount" readonly value="0" 
                class="w-full px-4 py-2 border border-slate-200 rounded-lg bg-slate-50 text-slate-600">
        </div>
        
        <div class="mb-4">
            <label class="block text-sm font-medium text-slate-700 mb-1">New Total (SAR)</label>
            <input type="text" id="discountNewTotal" readonly 
                value="{{ number_format($booking->invoice?->total_amount ?? 0) }}" 
                class="w-full px-4 py-2 border border-slate-200 rounded-lg bg-slate-100 text-slate-800 font-semibold">
        </div>
        
        <div class="flex gap-3">
            <button type="button" onclick="applyInvoiceDiscount()" 
                class="flex-1 px-6 py-2 bg-slate-700 text-white rounded-lg hover:bg-slate-800 transition font-medium">
                Apply
            </button>
            <button type="button" onclick="closeDiscountModal()" 
                class="flex-1 px-6 py-2 border border-slate-300 text-slate-700 rounded-lg hover:bg-slate-50 transition font-medium">
                Cancel
            </button>
        </div>
    </div>
</div>

{{-- Payment Modal --}}
<div id="paymentModal" class="hidden fixed inset-0 z-50 flex items-center justify-center">
    <div class="modal-overlay absolute inset-0 bg-black/50" onclick="closePaymentModal()"></div>
    <div class="modal-content relative bg-white rounded-xl shadow-2xl w-full max-w-lg mx-4 p-6 max-h-[90vh] overflow-y-auto">
        <h3 class="text-xl font-semibold text-slate-800 mb-4">Payment Interface</h3>
        
        <div class="mb-6">
            <h4 class="text-sm font-medium text-slate-600 mb-3 pb-2 border-b border-slate-200">Booking Information</h4>
            <div class="grid grid-cols-3 gap-4 text-sm">
                <div class="flex justify-between">
                    <span class="text-slate-500">Total Package Value:</span>
                    <span id="paymentTotalPackageValue" class="text-slate-800 font-medium text-right">{{ number_format($booking->invoice?->total_amount ?? 0) }} SAR</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-500">Paid:</span>
                    <span id="paymentPaid" class="text-slate-800 font-medium text-right">{{ number_format($booking->invoice?->paid_amount ?? 0) }} SAR</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-500">Due:</span>
                    <span id="paymentDue" class="text-slate-800 font-medium text-right">{{ number_format($booking->invoice?->balance ?? 0) }} SAR</span>
                </div>
            </div>
            
            <div class="grid grid-cols-2 gap-4 mt-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Currency</label>
                    <select id="paymentCurrency" onchange="handlePaymentCurrencyChange()" 
                        class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none bg-white">
                        <option value="SAR" selected>SAR</option>
                        <option value="BDT">BDT</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Method</label>
                    <select id="paymentMethod" onchange="handlePaymentMethodChange()" 
                        class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none bg-white">
                        <option value="Cash" selected>Cash</option>
                        <option value="Bank">Bank</option>
                    </select>
                </div>
            </div>
            
            <div id="paymentBankMethod" class="hidden mt-4">
                <label class="block text-sm font-medium text-slate-700 mb-1">Bank Method</label>
                <select id="paymentBankMethod" 
                    class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none bg-white">
                    <option value="">Select Bank</option>
                    <option value="AL-Raji">AL-Raji</option>
                    <option value="SNB">SNB</option>
                    <option value="Bkash-BMT">Bkash-BMT</option>
                    <option value="IBBL-BMT">IBBL-BMT</option>
                </select>
            </div>
            
            <div id="paymentTRXID" class="hidden mt-4">
                <label class="block text-sm font-medium text-slate-700 mb-1">TRX ID</label>
                <input type="text" id="paymentTRXID" 
                    placeholder="Enter TRX ID" 
                    class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none">
            </div>
            
            <div id="paymentAmountSAR" class="mt-4">
                <label class="block text-sm font-medium text-slate-700 mb-1">Amount (SAR)</label>
                <input type="number" id="paymentAmountSAR" 
                    placeholder="Enter SAR amount" min="0" step="0.01"
                    class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none">
            </div>
            
            <div id="paymentAmountBDT" class="hidden mt-4">
                <label class="block text-sm font-medium text-slate-700 mb-1">Amount (BDT)</label>
                <input type="number" id="paymentAmountBDT" 
                    placeholder="Enter BDT amount" min="0" step="0.01"
                    class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none">
            </div>
        </div>
        
        <div class="flex gap-3">
            <button type="button" onclick="savePayment()" 
                class="flex-1 px-6 py-2 bg-slate-700 text-white rounded-lg hover:bg-slate-800 transition font-medium">
                Save
            </button>
            <button type="button" onclick="closePaymentModal()" 
                class="flex-1 px-6 py-2 border border-slate-300 text-slate-700 rounded-lg hover:bg-slate-50 transition font-medium">
                Cancel
            </button>
        </div>
    </div>
</div>

{{-- Toast Container --}}
<div id="toastContainer" class="fixed top-4 right-4 z-50 space-y-2"></div>

<style>
.modal-overlay { transition: opacity 0.2s ease; }
.modal-content { transition: transform 0.2s ease, opacity 0.2s ease; }
.toast { transition: transform 0.3s ease, opacity 0.3s ease; }

@media print {
    body { background: white; }
    nav, .no-print { display: none !important; }
    .bg-slate-100 { background: white; }
    .shadow-lg, .shadow-xl { box-shadow: none; }
    .bg-white { border: 1px solid #e2e8f0; }
    a[href]:after { content: none !important; }
    #discountModal, #paymentModal { display: none !important; }
}
</style>

@push('scripts')
<script>
function showToast(message, type = 'success') {
    const container = document.getElementById('toastContainer');
    if (!container) return;
    
    const toast = document.createElement('div');
    toast.className = `px-4 py-2 rounded shadow text-white ${type === 'error' ? 'bg-red-600' : 'bg-slate-700'}`;
    toast.textContent = message;
    container.appendChild(toast);
    
    setTimeout(() => toast.remove(), 3000);
}

function openDiscountModal() {
    document.getElementById('discountModal').classList.remove('hidden');
    calculateInvoiceDiscount();
}

function closeDiscountModal() {
    document.getElementById('discountModal').classList.add('hidden');
}

function calculateInvoiceDiscount() {
    const originalTotal = {{ $booking->invoice?->total_amount ?? 0 }};
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

function applyInvoiceDiscount() {
    const discountType = document.getElementById('discountType').value;
    const discountValue = parseFloat(document.getElementById('discountValue').value) || 0;
    
    fetch('{{ route('bookings.update', $booking->id) }}', {
        method: 'PATCH',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify({
            discount_type: discountType,
            discount_value: discountValue
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success || data.message) {
            showToast('Discount applied successfully');
            closeDiscountModal();
            setTimeout(() => location.reload(), 500);
        } else {
            showToast('Failed to apply discount', 'error');
        }
    })
    .catch(error => {
        showToast('Error: ' + error.message, 'error');
    });
}

function openPaymentModal() {
    document.getElementById('paymentModal').classList.remove('hidden');
    resetPaymentForm();
}

function closePaymentModal() {
    document.getElementById('paymentModal').classList.add('hidden');
}

function resetPaymentForm() {
    document.getElementById('paymentCurrency').value = 'SAR';
    document.getElementById('paymentMethod').value = 'Cash';
    document.getElementById('paymentBankMethod').value = '';
    document.getElementById('paymentTRXID').value = '';
    document.getElementById('paymentAmountSAR').value = '';
    document.getElementById('paymentAmountBDT').value = '';
    handlePaymentCurrencyChange();
    handlePaymentMethodChange();
}

function handlePaymentCurrencyChange() {
    const currency = document.getElementById('paymentCurrency').value;
    document.getElementById('paymentAmountSAR').classList.toggle('hidden', currency !== 'SAR');
    document.getElementById('paymentAmountBDT').classList.toggle('hidden', currency !== 'BDT');
}

function handlePaymentMethodChange() {
    const method = document.getElementById('paymentMethod').value;
    document.getElementById('paymentBankMethod').classList.toggle('hidden', method !== 'Bank');
    document.getElementById('paymentTRXID').classList.toggle('hidden', method !== 'Bank');
}

function savePayment() {
    const currency = document.getElementById('paymentCurrency').value;
    const method = document.getElementById('paymentMethod').value;
    const amountSAR = currency === 'SAR' ? parseFloat(document.getElementById('paymentAmountSAR').value) || 0 : 0;
    const amountBDT = currency === 'BDT' ? parseFloat(document.getElementById('paymentAmountBDT').value) || 0 : 0;
    const bankMethod = document.getElementById('paymentBankMethod').value;
    const trxID = document.getElementById('paymentTRXID').value;
    
    if (amountSAR === 0 && amountBDT === 0) {
        showToast('Please enter payment amount', 'error');
        return;
    }
    
    fetch('{{ route('bookings.payment.store', $booking->id) }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify({
            amount: amountSAR,
            amount_bdt: amountBDT,
            currency: currency,
            payment_method: method,
            bank_method: bankMethod,
            transaction_id: trxID
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success || data.message) {
            showToast('Payment saved successfully');
            closePaymentModal();
            setTimeout(() => location.reload(), 500);
        } else {
            showToast('Failed to save payment', 'error');
        }
    })
    .catch(error => {
        showToast('Error: ' + error.message, 'error');
    });
}

function downloadAllDocs() {
    showToast('Downloading all documents...');
    setTimeout(() => showToast('No documents available for download'), 1500);
}

function viewPassengerDetails(passengerId) {
    window.location.href = '/passengers/' + passengerId;
}

function addPassenger() {
    window.location.href = '{{ route('bookings.edit', $booking->id) }}?add_passenger=true';
}

function openReIssueModal() {
    showToast('Re-Issue request feature coming soon');
}

function closeReIssueModal() {
    // Modal close placeholder
}

function openAddTicketModal() {
    showToast('Additional ticket request feature coming soon');
}

function closeAddTicketModal() {
    // Modal close placeholder
}

function openRefundModal() {
    showToast('Refund request feature coming soon');
}

function closeRefundModal() {
    // Modal close placeholder
}

function handleCustomerDocSelect(event) {
    const files = event.target.files;
    if (files.length > 0) {
        showToast('Uploading ' + files.length + ' file(s)...');
        const formData = new FormData();
        for (let i = 0; i < files.length; i++) {
            formData.append('documents[]', files[i]);
        }
        formData.append('booking_id', {{ $booking->id }});
        
        fetch('{{ route('documents.upload') }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast('Documents uploaded successfully');
                setTimeout(() => location.reload(), 500);
            } else {
                showToast('Upload failed', 'error');
            }
        })
        .catch(error => {
            showToast('Upload error: ' + error.message, 'error');
        });
    }
}

function downloadAllCustomerDocs() {
    const docs = document.querySelectorAll('#customerDocumentsList .text-blue-600');
    if (docs.length === 0) {
        showToast('No customer documents to download', 'error');
        return;
    }
    docs.forEach(doc => doc.click());
    showToast('Downloading customer documents...');
}

function downloadAllPassengerDocs() {
    const docs = document.querySelectorAll('#passengerDocumentsList .text-blue-600');
    if (docs.length === 0) {
        showToast('No passenger documents to download', 'error');
        return;
    }
    docs.forEach(doc => doc.click());
    showToast('Downloading passenger documents...');
}

function downloadDoc(docId) {
    window.open('/documents/' + docId + '/download', '_blank');
}
</script>
@endpush

@endsection