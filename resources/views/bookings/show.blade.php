@extends('layouts.app')
@section('title', 'Invoice Details')
@section('content')
<script>window.__bookingServerData = {
    ticketFares: @json($ticketFares ?? []),
    packages: @json($packages ?? []),
    preSelectedPackageId: {{ $booking->package_id ?? 'null' }},
    currentCurrencyRate: {{ $currentCurrencyRate?->rate ?? 0 }},
    bookingId: {{ $booking->id }}
};</script>
<div class="max-w-5xl mx-auto" x-data="showBookingApp()" x-init="init()">
    <div id="invoiceDetailsContent" class="space-y-6">
        {{-- Header Section --}}
        <div class="bg-white rounded-xl shadow-lg p-6">
            @php $canEditBooking = auth()->user()->roles->pluck('name')->intersect(['Super Admin', 'Co Admin', 'Branch Manager', 'Branch Staff'])->isNotEmpty(); @endphp
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
                    <a href="{{ route('bookings.print', $booking->id) }}" target="_blank" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition font-medium text-sm inline-block">
                        Print
                    </a>
                    @if($canEditBooking)
                    <button onclick="window.location.href='{{ route('bookings.edit', $booking->id) }}'" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-medium text-sm">
                        Edit
                    </button>
                    @endif
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

            <div class="grid grid-cols-3 gap-4 mt-6 pt-4 border-t border-slate-200">
                <div>
                    <span class="text-slate-500 text-sm">Total Value</span>
                    <p id="financialTotalValue" class="text-xl font-bold text-slate-800">{{ number_format($booking->invoice?->total_amount ?? 0) }} SAR</p>
                </div>
                <div>
                    <span class="text-slate-500 text-sm">Total Paid</span>
                    <p id="financialTotalPaid" class="text-xl font-bold text-green-600">{{ number_format($booking->invoice?->paid_amount ?? 0) }} SAR</p>
                </div>
                <div>
                    <span class="text-slate-500 text-sm">Due</span>
                    <p id="financialDue" class="text-xl font-bold text-red-600">{{ number_format($booking->invoice?->balance ?? 0) }} SAR</p>
                </div>
            </div>
            <div class="mt-6 pt-4 border-t border-slate-200 flex justify-end">
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
                @if($canEditBooking)
                <button @click="openPassengerModal()" class="px-4 py-2 border-2 border-slate-700 text-slate-700 rounded-lg hover:bg-slate-50 transition font-medium text-sm">+ Add Passenger</button>
                @endif
            </div>
        </div>

        {{-- Documents Section --}}
        <div class="grid grid-cols-2 gap-5">
            <div class="bg-slate-50 rounded-lg p-5">
                <div class="flex justify-between items-center mb-3 pb-2 border-b border-slate-200">
                    <h3 class="text-sm font-medium text-slate-500">Customer Documents</h3>
                    <div class="flex gap-2">
                        <input type="file" id="customerDocInput" class="hidden" accept=".pdf,image/*" multiple onchange="handleCustomerDocSelect(event)">
                        <button onclick="document.getElementById('customerDocInput').click()" class="px-3 py-1 bg-blue-600 text-white rounded hover:bg-blue-700 text-xs font-medium">Upload</button>
                        <button onclick="downloadAllCustomerDocs()" class="px-3 py-1 bg-green-600 text-white rounded hover:bg-green-700 text-xs font-medium">Download All</button>
                    </div>
                </div>
                <div id="customerDocumentsList" class="space-y-3 overflow-y-auto" style="max-height: 16rem;">
                    @forelse($booking->documents as $doc)
                    <div class="flex justify-between items-center bg-white p-3 rounded-lg border border-slate-200">
                        <span class="text-sm text-slate-700 truncate">{{ $doc->display_name ?? 'Document' }}</span>
                        <button onclick="downloadDoc({{ $doc->id }})" class="text-blue-600 hover:text-blue-800 text-xs">Download</button>
                    </div>
                    @empty
                    <p class="text-sm text-slate-400">No customer documents</p>
                    @endforelse
                </div>
            </div>

            <div class="bg-slate-50 rounded-lg p-5">
                <div class="flex justify-between items-center mb-4 pb-3 border-b border-slate-200">
                    <h3 class="text-sm font-medium text-slate-500">Passenger Documents</h3>
                    <div class="flex gap-2">
                        <button onclick="downloadAllPassengerDocs()" class="px-3 py-1 bg-green-600 text-white rounded hover:bg-green-700 text-xs font-medium">Download All</button>
                    </div>
                </div>
                <div id="passengerDocumentsList" class="space-y-3 overflow-y-auto" style="max-height: 16rem;">
                    @forelse($booking->passengers->flatMap->documents as $doc)
                    <div class="flex justify-between items-center bg-white p-3 rounded-lg border border-slate-200">
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
        <div class="flex justify-end gap-3 mt-8">
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
            <button @click="openPaymentModal()" class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-medium">
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
                            <td class="px-3 py-2 text-slate-600">{{ $payment->vouchers->first()?->voucher_id ?? 'N/A' }}</td>
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

    @include('partials.passenger-form-modal')

    {{-- Custom Duration Modal --}}
    <div x-show="customDurationModalVisible" x-cloak class="fixed inset-0 z-[60] flex items-center justify-center" @keydown.escape="closeCustomDurationModal()">
        <div class="fixed inset-0 bg-black/50" @click="closeCustomDurationModal()"></div>
        <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-sm mx-4 p-6">
            <h3 class="text-xl font-semibold text-slate-800 mb-4">Set Custom Duration</h3>
            <div class="mb-4">
                <label class="block text-sm font-medium text-slate-700 mb-1">Duration (days)</label>
                <input type="number" id="customDurationDays" x-model="passengerData.customDurationDays" min="30" max="89" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 outline-none" placeholder="Enter days (30-89)">
                <p class="text-xs text-slate-500 mt-1">Enter a value between 30 and 89 days</p>
            </div>
            <div class="flex gap-3">
                <button type="button" @click="saveCustomDuration()" class="flex-1 px-6 py-2 bg-slate-700 text-white rounded-lg hover:bg-slate-800 transition font-medium">Save</button>
                <button type="button" @click="closeCustomDurationModal()" class="flex-1 px-6 py-2 border border-slate-300 text-slate-700 rounded-lg hover:bg-slate-50 transition font-medium">Cancel</button>
            </div>
        </div>
    </div>

    {{-- Payment Modal --}}
    <div x-show="paymentModalVisible" x-cloak class="fixed inset-0 z-[60] flex items-center justify-center">
        <div class="fixed inset-0 bg-black/50" @click="closePaymentModal()"></div>
        <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-lg mx-4 p-6 max-h-[90vh] overflow-y-auto">
            <h3 class="text-xl font-semibold text-slate-800">Payment Interface</h3>
            <p class="text-sm text-slate-500 mb-4">Booking Summary</p>

            <div class="mb-4">
                <div class="grid grid-cols-2 gap-3 text-sm">
                    <div class="flex justify-between">
                        <span class="text-slate-500">Total Package Value:</span>
                        <span id="paymentTotalPackageValue" class="text-slate-800 font-medium text-right">{{ number_format($booking->invoice?->total_amount ?? 0) }} SAR</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-500">Paid:</span>
                        <span id="paymentPaid" class="text-slate-800 font-medium text-right">{{ number_format($booking->invoice?->paid_amount ?? 0) }} SAR</span>
                    </div>
                    <div class="flex justify-between col-span-2">
                        <span class="text-slate-600 font-medium">Due:</span>
                        <span id="paymentDue" class="text-slate-800 font-bold text-right">{{ number_format($booking->invoice?->balance ?? 0) }} SAR</span>
                    </div>
                </div>
            </div>

            <div class="mb-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Currency</label>
                        <select x-model="paymentData.currency" @change="handlePaymentCurrencyChange()" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none bg-white">
                            <option value="SAR">SAR</option>
                            <option value="BDT">BDT</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Method</label>
                    <select x-model="paymentData.method" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none bg-white">
                        <option value="cash">Cash</option>
                        <option value="bank">Bank</option>
                    </select>
                    </div>

                    <div x-show="paymentData.method === 'bank'" x-cloak class="col-span-2">
                        <label class="block text-sm font-medium text-slate-700 mb-1">Bank Method</label>
                        <select x-model="paymentData.bank_method" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none bg-white">
                            <option value="">Select Bank</option>
                            <option value="AL-Raji">AL-Raji</option>
                            <option value="SNB">SNB</option>
                            <option value="Bkash-BMT">Bkash-BMT</option>
                            <option value="IBBL-BMT">IBBL-BMT</option>
                        </select>
                    </div>

                    <div x-show="paymentData.method === 'bank'" x-cloak>
                        <label class="block text-sm font-medium text-slate-700 mb-1">TRX ID</label>
                        <input type="text" x-model="paymentData.trx_id" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none" placeholder="Enter TRX ID">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Amount (SAR)</label>
                        <input type="number" x-model="paymentData.amount_sar" :disabled="paymentData.currency === 'BDT'" @input="handleSarAmountInput()" :max="paymentMaxAmount" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none" :class="{'bg-slate-100 cursor-not-allowed': paymentData.currency === 'BDT'}" placeholder="Enter SAR amount">
                    </div>

                    <div x-show="paymentData.currency === 'BDT'" x-cloak>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Amount (BDT)</label>
                        <input type="number" x-model="paymentData.amount_bdt" @input="handleBdtAmountInput()" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none" placeholder="Enter BDT amount">
                    </div>

                    <div x-show="paymentData.currency === 'BDT'" class="col-span-2 mt-2">
                        <template x-if="exchangeRate > 0">
                            <p class="text-sm text-slate-500">1 SAR = <span x-text="exchangeRate"></span> BDT</p>
                        </template>
                        <template x-if="exchangeRate <= 0">
                            <p class="text-sm text-red-500">Exchange rate not available. Cannot process BDT payment.</p>
                        </template>
                    </div>
                </div>
            </div>

            <div class="flex gap-3">
                <button type="button" @click="savePayment()" class="flex-1 px-6 py-2 bg-slate-700 text-white rounded-lg hover:bg-slate-800 transition font-medium">Save</button>
                <button type="button" @click="closePaymentModal()" class="flex-1 px-6 py-2 border border-slate-300 text-slate-700 rounded-lg hover:bg-slate-50 transition font-medium">Cancel</button>
            </div>
        </div>
    </div>
</div>

{{-- Discount Modal --}}
<div id="discountModal" class="hidden fixed inset-0 z-50 flex items-center justify-center"
    data-discount-type="{{ $booking->discount_type?->value ?? 'fixed' }}"
    data-discount-value="{{ $booking->discount_value ?? 0 }}">
    <div class="fixed inset-0 bg-black/50" onclick="closeDiscountModal()"></div>
    <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-md mx-4 p-6">
        <div class="flex justify-between items-start mb-4">
            <h3 class="text-xl font-semibold text-slate-800">Apply Discount</h3>
            <button onclick="closeDiscountModal()" class="text-slate-400 hover:text-slate-600">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
        <div class="mb-4">
            <label class="block text-sm font-medium text-slate-600 mb-1">Discount Type</label>
            <select id="discountType" onchange="calculateInvoiceDiscount()" 
                class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none bg-white">
                <option value="fixed" data-current="{{ in_array($booking->discount_type?->value, ['fixed', 'fixed_amount', null]) ? 'true' : 'false' }}" {{ in_array($booking->discount_type?->value, ['fixed', 'fixed_amount', null]) ? 'selected' : '' }}>Fixed (SAR)</option>
                <option value="percentage" data-current="{{ $booking->discount_type?->value === 'percentage' ? 'true' : 'false' }}" {{ $booking->discount_type?->value === 'percentage' ? 'selected' : '' }}>Percentage (%)</option>
            </select>
        </div>
        <div class="mb-4">
            <label class="block text-sm font-medium text-slate-600 mb-1">Discount Value</label>
            <input type="number" id="discountValue" 
                value="{{ $booking->discount_value ?? 0 }}" 
                min="0" step="0.01"
                data-current="{{ $booking->discount_value ?? 0 }}"
                oninput="validateDiscountValue(); calculateInvoiceDiscount()" 
                class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none">
        </div>
        <div class="flex gap-3 pt-4 border-t border-slate-200">
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

{{-- Toast Container --}}
<div id="toastContainer" class="fixed top-4 right-4 z-[70] space-y-2"></div>

<style>
.modal-overlay { transition: opacity 0.2s ease; }
.modal-content { transition: transform 0.2s ease, opacity 0.2s ease; }
.toast { transition: transform 0.3s ease, opacity 0.3s ease; }
</style>

@push('scripts')
<script>
var currentDiscountState = {
    type: '{{ $booking->discount_type?->value === 'fixed_amount' ? 'fixed' : ($booking->discount_type?->value ?? 'fixed') }}',
    value: {{ $booking->discount_value ?? 0 }}
};

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
    document.getElementById('discountType').value = currentDiscountState.type;
    document.getElementById('discountValue').value = currentDiscountState.value;
    
    document.getElementById('discountModal').classList.remove('hidden');
}

function validateDiscountValue() {
    const input = document.getElementById('discountValue');
    const discountType = document.getElementById('discountType').value;
    
    if (input.value < 0) {
        input.value = 0;
        showToast('Discount value cannot be negative', 'error');
    }
    
    if (discountType === 'percentage' && input.value > 100) {
        input.value = 100;
        showToast('Percentage cannot exceed 100%', 'error');
    }
}

function closeDiscountModal() {
    document.getElementById('discountModal').classList.add('hidden');
}

function calculateInvoiceDiscount() {
    const type = document.getElementById('discountType').value;
    const value = parseFloat(document.getElementById('discountValue').value) || 0;
    const totalEl = document.getElementById('financialTotalValue');
    const totalText = totalEl?.textContent?.replace(/[^0-9.]/g, '') || '0';
    const total = parseFloat(totalText) || 0;

    let discountAmount = 0;
    if (type === 'percentage') {
        discountAmount = total * value / 100;
    } else {
        discountAmount = value;
    }

    const dueEl = document.getElementById('financialDue');
    const paidEl = document.getElementById('financialTotalPaid');
    const paidText = paidEl?.textContent?.replace(/[^0-9.]/g, '') || '0';
    const paid = parseFloat(paidText) || 0;
    const newDue = Math.max(0, total - discountAmount - paid);

    if (dueEl) {
        dueEl.textContent = newDue.toLocaleString() + ' SAR';
        if (newDue <= 0) {
            dueEl.className = 'text-xl font-bold text-green-600';
        } else {
            dueEl.className = 'text-xl font-bold text-red-600';
        }
    }
}

function applyInvoiceDiscount() {
    const discountType = document.getElementById('discountType').value;
    const discountValue = parseFloat(document.getElementById('discountValue').value) || 0;
    
    fetch('{{ route('bookings.update', $booking->id) }}', {
        method: 'PATCH',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify({
            discount_type: discountType,
            discount_value: discountValue
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Discount applied successfully');
            currentDiscountState.type = discountType;
            currentDiscountState.value = discountValue;
            closeDiscountModal();
            if (data.invoice) {
                updateFinancialSummary(data.invoice);
            }
            if (data.discount) {
                currentDiscountState.type = data.discount.type === 'fixed_amount' ? 'fixed' : data.discount.type;
                currentDiscountState.value = data.discount.value;
            }
        } else {
            showToast(data.message || 'Failed to apply discount', 'error');
        }
    })
    .catch(error => {
        showToast('Error: ' + error.message, 'error');
    });
}

function updateFinancialSummary(invoice) {
    const totalEl = document.getElementById('financialTotalValue');
    const paidEl = document.getElementById('financialTotalPaid');
    const dueEl = document.getElementById('financialDue');

    if (totalEl) totalEl.textContent = Number(invoice.total_amount).toLocaleString() + ' SAR';
    if (paidEl) paidEl.textContent = Number(invoice.paid_amount).toLocaleString() + ' SAR';
    if (dueEl) {
        dueEl.textContent = Number(invoice.balance).toLocaleString() + ' SAR';
        dueEl.className = 'text-xl font-bold ' + (invoice.balance <= 0 ? 'text-green-600' : 'text-red-600');
    }
}

function appendPassengerRow(passenger, displayTotal) {
    const container = document.getElementById('invoicePassengers');
    if (!container) return;

    const emptyState = container.querySelector('p');
    if (emptyState) emptyState.remove();

    const total = displayTotal || 0;
    const name = [passenger.first_name, passenger.last_name].filter(Boolean).join(' ') || 'N/A';
    const passport = passenger.passport_no || 'N/A';

    const div = document.createElement('div');
    div.className = 'flex justify-between items-center p-3 bg-slate-50 rounded-lg';
    div.innerHTML =
        '<div>' +
            '<span class="font-medium text-slate-800">' + escapeHtml(name) + '</span>' +
            '<span class="text-slate-500 text-sm ml-2">(' + escapeHtml(passport) + ')</span>' +
        '</div>' +
        '<div class="flex items-center gap-3">' +
            '<span class="text-slate-800 font-medium">' + Number(total).toLocaleString() + ' SAR</span>' +
            '<button onclick="viewPassengerDetails(' + passenger.id + ')" class="text-xs bg-slate-200 hover:bg-slate-300 text-slate-600 px-2 py-1 rounded">View</button>' +
        '</div>';

    container.appendChild(div);
}

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

function downloadAllDocs() {
    showToast('Downloading all documents...');
    setTimeout(() => showToast('No documents available for download'), 1500);
}

function viewPassengerDetails(passengerId) {
    window.location.href = '/passengers/' + passengerId;
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
    const input = event.target;
    const files = input.files;
    if (files.length === 0) return;

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
    .then(response => {
        if (!response.ok) {
            return response.json().then(err => { throw new Error(err.message || 'Upload failed'); });
        }
        return response.json();
    })
    .then(data => {
        if (data.success && data.documents && data.documents.length > 0) {
            showToast('Documents uploaded successfully');
            const list = document.getElementById('customerDocumentsList');
            if (!list) return;
            const emptyState = list.querySelector('p');
            if (emptyState) emptyState.remove();
            data.documents.forEach(doc => {
                const item = document.createElement('div');
                item.className = 'flex justify-between items-center bg-white p-3 rounded-lg border border-slate-200';
                item.innerHTML = '<span class="text-sm text-slate-700 truncate">' + (doc.display_name || 'Document') + '</span><button onclick="downloadDoc(' + doc.id + ')" class="text-blue-600 hover:text-blue-800 text-xs">Download</button>';
                list.appendChild(item);
            });
            input.value = '';
        } else {
            showToast('Upload failed: no documents returned', 'error');
        }
    })
    .catch(error => {
        showToast('Upload error: ' + error.message, 'error');
    });
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