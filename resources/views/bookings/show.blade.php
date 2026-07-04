@extends('layouts.app')
@section('title', 'Invoice Details')
@section('content')
@php $rateVal = $currentCurrencyRate?->rate ?? 0; @endphp
<script>window.__bookingServerData = {
    ticketFares: @json($ticketFares ?? []),
    packages: @json($packages ?? []),
    preSelectedPackageId: {{ $booking->package_id ?? 'null' }},
    currentCurrencyRate: {{ $currentCurrencyRate?->rate ?? 0 }},
    bookingId: {{ $booking->id }},
    firstPassengerMobile: '{{ $booking->passengers->first()?->mobile_no ?? '' }}',
    firstPassenger: @json($booking->passengers->sortBy('id')->first()?->toArray() ?? null),
    lastPassenger: @json($booking->passengers->sortByDesc('id')->first()?->toArray() ?? null),
    userBranchLocation: @json($userBranchLocation ?? null),
    banks: @json($banks ?? [])
};</script>
<div class="max-w-5xl mx-auto" x-data="showBookingApp()" x-init="init()">
    <div id="invoiceDetailsContent" class="space-y-6">
        {{-- Header Section --}}
        <div class="bg-white rounded-xl shadow-lg p-6">
            @php
                $isFingerprintOnlyViewer = auth()->user()->branch_id
                    && auth()->user()->branch_id === $booking->fingerprint_branch_id
                    && auth()->user()->branch_id !== $booking->booking_branch_id;

                $isCrossBranchViewer = auth()->user()->branch_id
                    && auth()->user()->branch_id !== $booking->booking_branch_id
                    && auth()->user()->branch_id !== $booking->fingerprint_branch_id;

                $canEditBooking = !$isFingerprintOnlyViewer && !$isCrossBranchViewer
                    && (auth()->user()->hasRole('Super Admin')
                    || auth()->user()->hasRole('Co Admin')
                    || ($booking->created_at->diffInHours(now()) < 12
                        && (auth()->user()->branch_id || $booking->user_id === auth()->id())));
                $canViewRequestButtons = !$isFingerprintOnlyViewer && !$isCrossBranchViewer && (auth()->user()->branch_id || auth()->user()->roles->pluck('name')->intersect(['Super Admin', 'Co Admin', 'Ticket Admin', 'Ticket Staff'])->isNotEmpty());
                $canAddPassenger = !$isFingerprintOnlyViewer && !$isCrossBranchViewer
                    && (auth()->user()->hasRole('Super Admin')
                    || auth()->user()->hasRole('Co Admin')
                    || auth()->user()->branch_id
                    || $booking->user_id === auth()->id());
                $canDeleteDocument = auth()->user()->roles->pluck('name')->intersect(['Super Admin', 'Co Admin'])->isNotEmpty();
                $canDeletePassengerDocument = auth()->user()->roles->pluck('name')->intersect(['Super Admin', 'Co Admin', 'Fingerprint Admin'])->isNotEmpty();
                $canApplyDiscount = auth()->user()->roles->pluck('name')->intersect(['Super Admin', 'Co Admin'])->isNotEmpty();
            @endphp
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

            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-6 pt-4 border-t border-slate-200">
                <div>
                    <span class="text-slate-500 text-sm">Original Total</span>
                    <p id="financialOriginalTotal" class="text-xl font-bold text-slate-800">@currency($originalTotal, 2, $rateVal)</p>
                    <p id="financialDiscountIndicator" class="text-xs text-orange-600 mt-1 {{ ($booking->discount_amount ?? 0) > 0 ? '' : 'hidden' }}">
                        −@currency($booking->discount_amount ?? 0, 2, $rateVal) discount
                        @if($booking->discount_type?->value === 'percentage')
                            ({{ rtrim(rtrim(number_format((float) $booking->discount_value, 2), '0'), '.') }}%)
                        @endif
                    </p>
                </div>
                <div>
                    <span class="text-slate-500 text-sm">Discounted Total</span>
                    <p id="financialTotalValue" class="text-xl font-bold text-slate-800">@currency($booking->invoice?->total_amount ?? 0, 2, $rateVal)</p>
                </div>
                <div>
                    <span class="text-slate-500 text-sm">Total Paid</span>
                    <p id="financialTotalPaid" class="text-xl font-bold text-green-600">@currency($booking->invoice?->paid_amount ?? 0, 2, $rateVal)</p>
                </div>
                <div>
                    <span class="text-slate-500 text-sm">Due</span>
                    <p id="financialDue" class="text-xl font-bold text-red-600">@currency($booking->invoice?->balance ?? 0, 2, $rateVal)</p>
                </div>
            </div>
            <div class="mt-6 pt-4 border-t border-slate-200 flex justify-end">
                @if($canApplyDiscount)
                <button type="button" onclick="openDiscountModal()" class="text-sm bg-slate-200 hover:bg-slate-300 text-slate-600 px-3 py-1 rounded">
                    Discount
                </button>
                @endif
</div>
        </div>

        @if(!$isFingerprintOnlyViewer && !$isCrossBranchViewer)
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
                    <span class="text-slate-800 font-medium">@currency($passengerTotal, 2, $rateVal)</span>
                    <button onclick="viewPassengerDetails({{ $passenger->id }})" class="text-xs bg-slate-200 hover:bg-slate-300 text-slate-600 px-2 py-1 rounded">View</button>
                </div>
            </div>
            @empty
            <p class="text-sm text-slate-400 text-center py-4">No passengers found.</p>
            @endforelse
            </div>
            
            @if($canAddPassenger)
            <div class="flex justify-end mt-4">
                <button @click="openPassengerModal()" class="px-4 py-2 border-2 border-slate-700 text-slate-700 rounded-lg hover:bg-slate-50 transition font-medium text-sm">+ Add Passenger</button>
            </div>
            @endif
        </div>
        @endif

        @if(!$isFingerprintOnlyViewer && !$isCrossBranchViewer)
        {{-- Documents Section --}}
        <div class="grid grid-cols-2 gap-5">
            <div class="bg-slate-50 rounded-lg p-5">
                <div class="flex justify-between items-center mb-3 pb-2 border-b border-slate-200">
                    <h3 class="text-sm font-medium text-slate-500">Customer Documents</h3>
                    <div class="flex gap-2">
                        <input type="file" id="customerDocInput" class="hidden" accept=".pdf,image/*" multiple onchange="handleCustomerDocSelect(event)">
                        <p class="text-xs text-slate-400 mr-auto self-end">Max 5 MB per file, 20 MB total</p>
                        <button onclick="document.getElementById('customerDocInput').click()" class="px-3 py-1 bg-blue-600 text-white rounded hover:bg-blue-700 text-xs font-medium">Upload</button>
                        <button onclick="downloadAllCustomerDocs()" class="px-3 py-1 bg-green-600 text-white rounded hover:bg-green-700 text-xs font-medium">Download All</button>
                    </div>
                </div>
                <div id="customerDocumentsList" class="space-y-3 overflow-y-auto" style="max-height: 16rem;">
                    @php
                        $allCustomerDocs = collect($booking->documents)
                            ->merge($booking->customer?->documents ?? collect())
                            ->sortBy(fn($d) => (int) preg_replace('/.*\s(\d+)$/', '$1', $d->display_name ?? '0'));
                    @endphp
                    @forelse($allCustomerDocs as $doc)
                    <div class="flex justify-between items-center bg-white p-3 rounded-lg border border-slate-200">
                        <span class="text-sm text-slate-700 truncate">{{ $doc->display_name ?? 'Document' }}</span>
                        <div class="flex gap-2">
                            @if($canDeleteDocument)
                            <button onclick="deleteDocument({{ $doc->id }})" class="text-red-500 hover:text-red-700 text-xs">Delete</button>
                            @endif
                            <button onclick="downloadDoc({{ $doc->id }})" class="text-blue-600 hover:text-blue-800 text-xs">Download</button>
                        </div>
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
                        <div class="flex gap-2">
                            @if($canDeletePassengerDocument)
                            <button onclick="deleteDocument({{ $doc->id }})" class="text-red-500 hover:text-red-700 text-xs">Delete</button>
                            @endif
                            <button onclick="downloadDoc({{ $doc->id }})" class="text-blue-600 hover:text-blue-800 text-xs">Download</button>
                        </div>
                    </div>
                    @empty
                    <p class="text-sm text-slate-400">No passenger documents</p>
                    @endforelse
                </div>
            </div>
        </div>
        @endif

        {{-- Action Buttons Row --}}
        <div class="flex justify-end gap-3 mt-8">
            @if($canViewRequestButtons)
            <button onclick="openReIssueModal()" class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-medium">
                Request Re-Issue
            </button>
            <button onclick="openAddTicketModal()" class="px-6 py-3 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition font-medium">
                Request Add. Tkt
            </button>
            <button onclick="openRefundModal()" class="px-6 py-3 bg-orange-500 text-white rounded-lg hover:bg-orange-600 transition font-medium">
                Request Ticket Refund
            </button>
            @endif
            @if(!$isFingerprintOnlyViewer && !$isCrossBranchViewer)
            <button onclick="downloadAllDocs()" class="px-6 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 transition font-medium">
                Download All Docs
            </button>
            @endif
            <button @click="openPaymentModal()" class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-medium">
                Payment
            </button>
        </div>

        {{-- Tab Navigation --}}
        <div class="bg-white rounded-xl shadow-lg mb-6">
            <div class="flex border-b border-slate-200">
                <button onclick="switchTab('payment')" id="tab-payment" class="tab-btn px-6 py-3 text-sm font-medium border-b-2 border-blue-600 text-blue-600">
                    Payment History
                </button>
                @if($canViewRequestButtons)
                <button onclick="switchTab('reissue')" id="tab-reissue" class="tab-btn px-6 py-3 text-sm font-medium border-b-2 border-transparent text-slate-500 hover:text-slate-700">
                    Re-issue History
                </button>
                <button onclick="switchTab('addticket')" id="tab-addticket" class="tab-btn px-6 py-3 text-sm font-medium border-b-2 border-transparent text-slate-500 hover:text-slate-700">
                    Additional Ticket
                </button>
                <button onclick="switchTab('refund')" id="tab-refund" class="tab-btn px-6 py-3 text-sm font-medium border-b-2 border-transparent text-slate-500 hover:text-slate-700">
                    Refund History
                </button>
                @endif
            </div>
        </div>

        {{-- Payment History Tab --}}
        <div id="content-payment" class="tab-content block bg-white rounded-xl shadow-lg p-6">
            <h3 class="text-lg font-semibold text-slate-700 mb-4">Payment History</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-slate-600">
                        <tr>
                            <th class="px-3 py-2 text-left font-medium">Date</th>
                            <th class="px-3 py-2 text-left font-medium">Voucher No</th>
                            <th class="px-3 py-2 text-left font-medium">Method</th>
                            <th class="px-3 py-2 text-left font-medium">Trx ID</th>
                            <th class="px-3 py-2 text-left font-medium">Receive By</th>
                            <th class="px-3 py-2 text-left font-medium">Receive At</th>
                            <th class="px-3 py-2 text-right font-medium">Amount</th>
                            <th class="px-3 py-2 text-center font-medium">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        @forelse($booking->payments as $payment)
                        <tr>
                            <td class="px-3 py-2 text-slate-600">{{ $payment->created_at->format('Y-m-d') }}</td>
                            <td class="px-3 py-2 text-slate-600">{{ $payment->vouchers->first()?->voucher_id ?? 'N/A' }}</td>
                            <td class="px-3 py-2 text-slate-600">{{ $payment->payment_method?->value ?? 'Cash' }}</td>
                            <td class="px-3 py-2 text-slate-600">{{ $payment->transaction_id ?? '-' }}</td>
                            <td class="px-3 py-2 text-slate-600">{{ $payment->user?->name ?? '-' }}</td>
                            <td class="px-3 py-2 text-slate-600">{{ $payment->user?->branch?->name ?? 'Central' }}</td>
                            <td class="px-3 py-2 text-right text-slate-800 font-medium">@currency($payment->amount, 2, $rateVal)</td>
                            <td class="px-3 py-2 text-center">
                                @if(auth()->user()->hasRole('Super Admin'))
                                <button @click="openEditPaymentModal({
                                    id: {{ $payment->id }},
                                    amount: '{{ $payment->amount }}',
                                    bdt_amount: '{{ $payment->bdt_amount }}',
                                    payment_method: '{{ $payment->payment_method?->value }}',
                                    transaction_id: '{{ $payment->transaction_id }}',
                                    bank_name: '{{ $payment->bank?->name ?? '' }}',
                                    currency: '{{ $payment->currency ?? 'SAR' }}'
                                })" class="text-xs bg-amber-100 hover:bg-amber-200 text-amber-600 px-2 py-1 rounded">Edit</button>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" class="px-3 py-4 text-center text-slate-500">No payments recorded</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if($canViewRequestButtons)
        {{-- Re-issue History Tab --}}
        <div id="content-reissue" class="tab-content hidden bg-white rounded-xl shadow-lg p-6">
            <h3 class="text-lg font-semibold text-slate-700 mb-4">Re-issue History</h3>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[1100px] text-sm">
                    <thead class="bg-slate-50 text-slate-600">
                        <tr>
                            <th class="px-3 py-2 text-left font-medium">Date</th>
                            <th class="px-3 py-2 text-left font-medium">Passenger Name</th>
                            <th class="px-3 py-2 text-left font-medium">Passport No.</th>
                            <th class="px-3 py-2 text-left font-medium">PNR</th>
                            <th class="px-3 py-2 text-left font-medium">Agent</th>
                            <th class="px-3 py-2 text-right font-medium">Total Reissue Cost</th>
                            <th class="px-3 py-2 text-right font-medium">Total Customer Payment</th>
                            <th class="px-3 py-2 text-right font-medium">Profit</th>
                            <th class="px-3 py-2 text-left font-medium">Payment Method</th>
                            <th class="px-3 py-2 text-left font-medium">Status</th>
                            <th class="px-3 py-2 text-left font-medium">Action</th>
                        </tr>
                    </thead>
                    <tbody id="reissueHistoryBody" class="divide-y divide-slate-200"></tbody>
                </table>
                <div id="reissueHistoryEmpty" class="text-center py-4 text-slate-500">No re-issue requests found</div>
            </div>
        </div>

        {{-- Additional Ticket Tab --}}
        <div id="content-addticket" class="tab-content hidden bg-white rounded-xl shadow-lg p-6">
            <h3 class="text-lg font-semibold text-slate-700 mb-4">Additional Ticket History</h3>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[1100px] text-sm">
                    <thead class="bg-slate-50 text-slate-600">
                        <tr>
                            <th class="px-3 py-2 text-left font-medium">Date</th>
                            <th class="px-3 py-2 text-left font-medium">Passenger Name</th>
                            <th class="px-3 py-2 text-left font-medium">Passport No.</th>
                            <th class="px-3 py-2 text-left font-medium">PNR</th>
                            <th class="px-3 py-2 text-left font-medium">Agent</th>
                            <th class="px-3 py-2 text-right font-medium">Additional Ticket Cost</th>
                            <th class="px-3 py-2 text-right font-medium">Total Customer Payment</th>
                            <th class="px-3 py-2 text-right font-medium">Profit</th>
                            <th class="px-3 py-2 text-left font-medium">Payment Method</th>
                            <th class="px-3 py-2 text-left font-medium">Status</th>
                            <th class="px-3 py-2 text-left font-medium">Action</th>
                        </tr>
                    </thead>
                    <tbody id="additionalTicketHistoryBody" class="divide-y divide-slate-200"></tbody>
                </table>
                <div id="additionalTicketHistoryEmpty" class="text-center py-4 text-slate-500">No additional ticket requests found</div>
            </div>
        </div>

        {{-- Refund Tab --}}
        <div id="content-refund" class="tab-content hidden bg-white rounded-xl shadow-lg p-6">
            <h3 class="text-lg font-semibold text-slate-700 mb-4">Refund History</h3>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[1100px] text-sm">
                    <thead class="bg-slate-50 text-slate-600">
                        <tr>
                            <th class="px-3 py-2 text-left font-medium">Date</th>
                            <th class="px-3 py-2 text-left font-medium">Passenger Name</th>
                            <th class="px-3 py-2 text-left font-medium">Passport No.</th>
                            <th class="px-3 py-2 text-left font-medium">PNR</th>
                            <th class="px-3 py-2 text-left font-medium">Agent</th>
                            <th class="px-3 py-2 text-right font-medium">Agent Refund Amount</th>
                            <th class="px-3 py-2 text-right font-medium">Customer Refund Amount</th>
                            <th class="px-3 py-2 text-right font-medium">Profit</th>
                            <th class="px-3 py-2 text-left font-medium">Payment Method</th>
                            <th class="px-3 py-2 text-left font-medium">Status</th>
                            <th class="px-3 py-2 text-left font-medium">Action</th>
                        </tr>
                    </thead>
                    <tbody id="refundHistoryBody" class="divide-y divide-slate-200"></tbody>
                </table>
                <div id="refundHistoryEmpty" class="text-center py-4 text-slate-500">No refund requests found</div>
            </div>
        </div>
        @endif

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
                <input type="number" id="customDurationDays" x-model="passengerData.customDurationDays" min="1" max="80" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 outline-none" placeholder="Enter days (1-80)">
                <p class="text-xs text-slate-500 mt-1">Enter a value between 1 and 80 days</p>
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
            <h3 class="text-xl font-semibold text-slate-800" x-text="editingPaymentId ? 'Edit Payment' : 'Payment Interface'"></h3>
            <p class="text-sm text-slate-500 mb-4" x-text="editingPaymentId ? 'Update payment details' : 'Booking Summary'"></p>

            <div x-show="!editingPaymentId" class="mb-4">
                <div class="grid grid-cols-2 gap-3 text-sm">
                    <div class="flex justify-between">
                        <span class="text-slate-500">Total Package Value:</span>
                        <span id="paymentTotalPackageValue" class="text-slate-800 font-medium text-right">@currency($booking->invoice?->total_amount ?? 0, 2, $rateVal)</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-500">Paid:</span>
                        <span id="paymentPaid" class="text-slate-800 font-medium text-right">@currency($booking->invoice?->paid_amount ?? 0, 2, $rateVal)</span>
                    </div>
                    <div class="flex justify-between col-span-2">
                        <span class="text-slate-600 font-medium">Due:</span>
                        <span id="paymentDue" class="text-slate-800 font-bold text-right">@currency($booking->invoice?->balance ?? 0, 2, $rateVal)</span>
                    </div>
                </div>
            </div>

            <div class="mb-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Currency</label>
                        <select x-model="paymentData.currency" @change="handlePaymentCurrencyChange()" :disabled="isCurrencyLocked" :class="{'bg-slate-100 cursor-not-allowed': isCurrencyLocked}" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none bg-white">
                            <option value="SAR">SAR</option>
                            <option value="BDT">BDT</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Method</label>
                    <select x-model="paymentData.method" @change="handlePaymentMethodChange()" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none bg-white">
                        <option value="cash">Cash</option>
                        <option value="bank">Bank</option>
                    </select>
                    </div>

                    <div x-show="paymentData.method === 'bank'" x-cloak class="col-span-2">
                        <label class="block text-sm font-medium text-slate-700 mb-1">Bank Method</label>
                        <select x-model="paymentData.bank_method" @change="handleBankMethodChange()" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none bg-white">
                            <option value="">Select Bank</option>
                            <template x-for="bank in filteredBanks" :key="bank.id">
                                <option :value="bank.name" x-text="bank.name"></option>
                            </template>
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
                <button type="button" @click="savePayment()" class="flex-1 px-6 py-2 bg-slate-700 text-white rounded-lg hover:bg-slate-800 transition font-medium" x-text="editingPaymentId ? 'Update' : 'Save'"></button>
                <button type="button" @click="closePaymentModal()" class="flex-1 px-6 py-2 border border-slate-300 text-slate-700 rounded-lg hover:bg-slate-50 transition font-medium">Cancel</button>
            </div>
        </div>
    </div>
</div>

@if($canViewRequestButtons)
{{-- Request Re-Issue Modal --}}
<div id="reIssueModal" class="hidden fixed inset-0 z-50 flex items-center justify-center">
    <div class="fixed inset-0 bg-black/50" onclick="closeReIssueModal()"></div>
    <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-2xl mx-4 p-6 max-h-[90vh] overflow-y-auto">
        <div class="flex justify-between items-start mb-4">
            <div>
                <h3 class="text-xl font-semibold text-slate-800">Request Re-Issue</h3>
                <p class="text-slate-500 text-sm mt-1">Select passengers and enter dates</p>
            </div>
            <button onclick="closeReIssueModal()" class="text-slate-400 hover:text-slate-600">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
        <div id="reIssuePassengers" class="space-y-4 mb-6 max-h-80 overflow-y-auto">
            @foreach($booking->passengers as $index => $passenger)
            <div class="border border-slate-200 rounded-lg p-4">
                <div class="flex items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <input type="checkbox" id="reIssue_{{ $index }}" data-name="{{ $passenger->first_name }} {{ $passenger->last_name }}" data-passport="{{ $passenger->passport_no }}" class="w-4 h-4 text-slate-600 rounded" onchange="toggleReIssueFields('reIssue_{{ $index }}', 'reIssueFields_{{ $index }}')">
                        <label for="reIssue_{{ $index }}" class="font-medium text-slate-800 whitespace-nowrap">{{ $passenger->first_name }} {{ $passenger->last_name }} <span class="text-slate-500 text-sm">({{ $passenger->passport_no }})</span></label>
                    </div>
                    <div id="reIssueFields_{{ $index }}" class="hidden flex items-center gap-3">
                        <label for="ticketOption_{{ $index }}" class="text-sm font-medium text-slate-700">Ticket Option</label>
                        <select id="ticketOption_{{ $index }}" onchange="toggleTicketOptionFields('{{ $index }}')" class="px-3 py-1.5 text-sm border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none bg-white">
                            <option value="">Select</option>
                            <option value="up">Inbound</option>
                            <option value="down">Outbound</option>
                            <option value="both">Both</option>
                        </select>
                    </div>
                </div>
                <div id="reIssueDateFields_{{ $index }}" class="hidden mt-3 pl-7">
                    <div class="flex gap-6 mb-2">
                        <div id="probableDateUp_{{ $index }}" class="hidden flex flex-col">
                            <label class="text-xs font-medium text-slate-700 whitespace-nowrap">Probable Re-issue Date (Inbound):</label>
                            <input type="date" id="probableDateUp_{{ $index }}" class="mt-1 px-2 py-1 text-sm border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none">
                        </div>
                        <div id="probableDateDown_{{ $index }}" class="hidden flex flex-col">
                            <label class="text-xs font-medium text-slate-700 whitespace-nowrap">Probable Re-issue Date (Outbound):</label>
                            <input type="date" id="probableDateDown_{{ $index }}" class="mt-1 px-2 py-1 text-sm border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none">
                        </div>
                        <div id="visaExpiry_{{ $index }}" class="flex flex-col">
                            <label class="text-xs font-medium text-slate-700 whitespace-nowrap">Visa Expiry Date:</label>
                            <input type="date" id="visaExpiry_{{ $index }}" class="mt-1 px-2 py-1 text-sm border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none">
                        </div>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
        <div class="flex gap-3">
            <button onclick="submitReIssueRequest()" class="flex-1 px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-medium">Submit Request</button>
            <button onclick="closeReIssueModal()" class="flex-1 px-6 py-2 border border-slate-300 text-slate-700 rounded-lg hover:bg-slate-50 transition font-medium">Cancel</button>
        </div>
    </div>
</div>

{{-- Re-Issue Details Modal --}}
<div id="reissueDetailsModal" class="hidden fixed inset-0 z-50 flex items-center justify-center">
    <div class="fixed inset-0 bg-black/50" onclick="closeReissueDetailsModal()"></div>
    <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-2xl mx-4 p-6 max-h-[90vh] overflow-y-auto">
        <div class="flex justify-between items-start mb-4">
            <h3 class="text-xl font-semibold text-slate-800">Re-Issue Details</h3>
            <button onclick="closeReissueDetailsModal()" class="text-slate-400 hover:text-slate-600">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
        <div id="reissueDetailsContent"></div>
    </div>
</div>

{{-- Request Refund Modal --}}
<div id="refundModal" class="hidden fixed inset-0 z-50 flex items-center justify-center">
    <div class="fixed inset-0 bg-black/50" onclick="closeRefundModal()"></div>
    <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-2xl mx-4 p-6 max-h-[90vh] overflow-y-auto">
        <div class="flex justify-between items-start mb-4">
            <div>
                <h3 class="text-xl font-semibold text-slate-800">Request Refund</h3>
                <p class="text-slate-500 text-sm mt-1">Select passengers for refund</p>
            </div>
            <button onclick="closeRefundModal()" class="text-slate-400 hover:text-slate-600">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
        <div id="refundPassengers" class="space-y-4 mb-6 max-h-80 overflow-y-auto">
            @foreach($booking->passengers as $index => $passenger)
            <div class="border border-slate-200 rounded-lg p-4">
                <div class="flex items-center gap-3">
                    <input type="checkbox" id="refund_{{ $index }}" data-name="{{ $passenger->first_name }} {{ $passenger->last_name }}" data-passport="{{ $passenger->passport_no }}" class="w-4 h-4 text-slate-600 rounded">
                    <label for="refund_{{ $index }}" class="font-medium text-slate-800">{{ $passenger->first_name }} {{ $passenger->last_name }} <span class="text-slate-500 text-sm">({{ $passenger->passport_no }})</span></label>
                </div>
            </div>
            @endforeach
        </div>
        <div class="flex gap-3">
            <button onclick="submitRefundRequest()" class="flex-1 px-6 py-2 bg-orange-500 text-white rounded-lg hover:bg-orange-600 transition font-medium">Submit Request</button>
            <button onclick="closeRefundModal()" class="flex-1 px-6 py-2 border border-slate-300 text-slate-700 rounded-lg hover:bg-slate-50 transition font-medium">Cancel</button>
        </div>
    </div>
</div>

{{-- Refund Details Modal --}}
<div id="refundDetailsModal" class="hidden fixed inset-0 z-50 flex items-center justify-center">
    <div class="fixed inset-0 bg-black/50" onclick="closeRefundDetailsModal()"></div>
    <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-2xl mx-4 p-6 max-h-[90vh] overflow-y-auto">
        <div class="flex justify-between items-start mb-4">
            <h3 class="text-xl font-semibold text-slate-800">Refund Details</h3>
            <button onclick="closeRefundDetailsModal()" class="text-slate-400 hover:text-slate-600">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
        <div id="refundDetailsContent"></div>
    </div>
</div>

{{-- Request Additional Ticket Modal --}}
<div id="addTicketModal" class="hidden fixed inset-0 z-50 flex items-center justify-center">
    <div class="fixed inset-0 bg-black/50" onclick="closeAddTicketModal()"></div>
    <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-2xl mx-4 p-6 max-h-[90vh] overflow-y-auto">
        <div class="flex justify-between items-start mb-4">
            <div>
                <h3 class="text-xl font-semibold text-slate-800">Request Additional Ticket</h3>
                <p class="text-slate-500 text-sm mt-1">Select passengers and enter dates</p>
            </div>
            <button onclick="closeAddTicketModal()" class="text-slate-400 hover:text-slate-600">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
        <div id="addTicketPassengers" class="space-y-4 mb-6 max-h-80 overflow-y-auto">
            @foreach($booking->passengers as $index => $passenger)
            <div class="border border-slate-200 rounded-lg p-4">
                <div class="flex items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <input type="checkbox" id="addTicket_{{ $index }}" data-name="{{ $passenger->first_name }} {{ $passenger->last_name }}" data-passport="{{ $passenger->passport_no }}" class="w-4 h-4 text-slate-600 rounded" onchange="toggleReIssueFields('addTicket_{{ $index }}', 'addTicketFields_{{ $index }}')">
                        <label for="addTicket_{{ $index }}" class="font-medium text-slate-800 whitespace-nowrap">{{ $passenger->first_name }} {{ $passenger->last_name }} <span class="text-slate-500 text-sm">({{ $passenger->passport_no }})</span></label>
                    </div>
                    <div id="addTicketFields_{{ $index }}" class="hidden flex items-center gap-3">
                        <label for="addTicketOption_{{ $index }}" class="text-sm font-medium text-slate-700">Ticket Option</label>
                        <select id="addTicketOption_{{ $index }}" onchange="toggleAddTicketOptionFields('{{ $index }}')" class="px-3 py-1.5 text-sm border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none bg-white">
                            <option value="">Select</option>
                            <option value="up">Inbound</option>
                            <option value="down">Outbound</option>
                            <option value="both">Both</option>
                        </select>
                    </div>
                </div>
                <div id="addTicketDateFields_{{ $index }}" class="hidden mt-3 pl-7">
                    <div class="flex gap-6 mb-2">
                        <div id="addTicketProbableDateUp_{{ $index }}" class="hidden flex flex-col">
                            <label class="text-xs font-medium text-slate-700 whitespace-nowrap">Probable Date (Inbound):</label>
                            <input type="date" id="addTicketProbableDateUp_{{ $index }}" class="mt-1 px-2 py-1 text-sm border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none">
                        </div>
                        <div id="addTicketProbableDateDown_{{ $index }}" class="hidden flex flex-col">
                            <label class="text-xs font-medium text-slate-700 whitespace-nowrap">Probable Date (Outbound):</label>
                            <input type="date" id="addTicketProbableDateDown_{{ $index }}" class="mt-1 px-2 py-1 text-sm border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none">
                        </div>
                        <div id="addTicketVisaExpiry_{{ $index }}" class="flex flex-col">
                            <label class="text-xs font-medium text-slate-700 whitespace-nowrap">Visa Expiry Date:</label>
                            <input type="date" id="addTicketVisaExpiry_{{ $index }}" class="mt-1 px-2 py-1 text-sm border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none">
                        </div>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
        <div class="flex gap-3">
            <button onclick="submitAddTicketRequest()" class="flex-1 px-6 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition font-medium">Submit Request</button>
            <button onclick="closeAddTicketModal()" class="flex-1 px-6 py-2 border border-slate-300 text-slate-700 rounded-lg hover:bg-slate-50 transition font-medium">Cancel</button>
        </div>
    </div>
</div>

{{-- Additional Ticket Details Modal --}}
<div id="addTicketDetailsModal" class="hidden fixed inset-0 z-50 flex items-center justify-center">
    <div class="fixed inset-0 bg-black/50" onclick="closeAddTicketDetailsModal()"></div>
    <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-2xl mx-4 p-6 max-h-[90vh] overflow-y-auto">
        <div class="flex justify-between items-start mb-4">
            <h3 class="text-xl font-semibold text-slate-800">Additional Ticket Details</h3>
            <button onclick="closeAddTicketDetailsModal()" class="text-slate-400 hover:text-slate-600">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
        <div id="addTicketDetailsContent"></div>
    </div>
</div>
@endif

@if($canApplyDiscount)
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
            <select id="discountType" onchange="onDiscountTypeChange()"
                class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none bg-white">
                <option value="fixed" data-current="{{ in_array($booking->discount_type?->value, ['fixed', 'fixed_amount', null]) ? 'true' : 'false' }}" {{ in_array($booking->discount_type?->value, ['fixed', 'fixed_amount', null]) ? 'selected' : '' }}>Fixed</option>
                <option value="percentage" data-current="{{ $booking->discount_type?->value === 'percentage' ? 'true' : 'false' }}" {{ $booking->discount_type?->value === 'percentage' ? 'selected' : '' }}>Percentage (%)</option>
            </select>
        </div>

        <div id="fixedDiscountFields" class="mb-4">
            <div id="fixedBdtField" class="mb-3">
                <label class="block text-sm font-medium text-slate-600 mb-1">Fixed (BDT)</label>
                <input type="number" id="discountValueBdt"
                    min="0" step="0.01"
                    oninput="onFixedBdtInput()"
                    class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-600 mb-1">Fixed (SAR)</label>
                <input type="number" id="discountValueSar"
                    value="{{ number_format($booking->discount_value ?? 0, 6, '.', '') }}"
                    min="0" step="any"
                    data-current="{{ $booking->discount_value ?? 0 }}"
                    oninput="onFixedSarInput()"
                    class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none">
            </div>
        </div>

        <div id="percentageDiscountField" class="mb-4">
            <label class="block text-sm font-medium text-slate-600 mb-1">Discount Value (%)</label>
            <input type="number" id="discountValuePct"
                value="{{ number_format($booking->discount_type?->value === 'percentage' ? ($booking->discount_value ?? 0) : 0, 2, '.', '') }}"
                min="0" max="100" step="0.01"
                oninput="onPercentageInput()"
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
@endif

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
    value: {{ round($booking->discount_value ?? 0, 6) }}
};

function round2(n) {
    return Math.round(n * 100) / 100;
}

function showToast(message, type = 'success') {
    const container = document.getElementById('toastContainer');
    if (!container) return;

    const toast = document.createElement('div');
    toast.className = `px-4 py-2 rounded shadow text-white ${type === 'error' ? 'bg-red-600' : 'bg-slate-700'}`;
    toast.textContent = message;
    container.appendChild(toast);

    setTimeout(() => toast.remove(), 3000);
}

function getCurrencyMode() {
    return Alpine.store('currency')?.mode || 'SAR';
}

function getCurrencyRate() {
    return Alpine.store('currency')?.rate || window.__bookingServerData?.currentCurrencyRate || 0;
}

function updateDiscountFieldsVisibility() {
    const type = document.getElementById('discountType').value;
    const mode = getCurrencyMode();

    const fixedFields = document.getElementById('fixedDiscountFields');
    const pctField = document.getElementById('percentageDiscountField');
    const bdtField = document.getElementById('fixedBdtField');
    const sarInput = document.getElementById('discountValueSar');

    fixedFields.classList.toggle('hidden', type !== 'fixed');
    pctField.classList.toggle('hidden', type !== 'percentage');

    if (type === 'fixed') {
        const showBdt = mode === 'BDT';
        bdtField.classList.toggle('hidden', !showBdt);
        sarInput.readOnly = showBdt;
        if (showBdt) {
            sarInput.classList.add('bg-slate-100', 'cursor-not-allowed');
        } else {
            sarInput.classList.remove('bg-slate-100', 'cursor-not-allowed');
        }
    }
}

function openDiscountModal() {
    const type = currentDiscountState.type;
    const mode = getCurrencyMode();
    const rate = getCurrencyRate();

    document.getElementById('discountType').value = type;
    document.getElementById('discountValueSar').value = parseFloat(currentDiscountState.value).toFixed(6);

    if (type === 'fixed' && mode === 'BDT' && rate > 0) {
        document.getElementById('discountValueBdt').value = round2(currentDiscountState.value * rate).toFixed(2);
    } else {
        document.getElementById('discountValueBdt').value = '';
    }

    if (type === 'percentage') {
        document.getElementById('discountValuePct').value = round2(currentDiscountState.value);
    } else {
        document.getElementById('discountValuePct').value = '';
    }

    updateDiscountFieldsVisibility();
    document.getElementById('discountModal').classList.remove('hidden');
}

function closeDiscountModal() {
    document.getElementById('discountModal').classList.add('hidden');
}

function onDiscountTypeChange() {
    const type = document.getElementById('discountType').value;
    const mode = getCurrencyMode();
    const rate = getCurrencyRate();

    document.getElementById('discountValueSar').value = '';
    document.getElementById('discountValueBdt').value = '';
    document.getElementById('discountValuePct').value = '';

    if (type === 'fixed') {
        if (mode === 'BDT' && rate > 0) {
            document.getElementById('discountValueBdt').focus();
        } else {
            document.getElementById('discountValueSar').focus();
        }
    } else {
        document.getElementById('discountValuePct').focus();
    }

    updateDiscountFieldsVisibility();
    calculateInvoiceDiscount();
}

function onFixedBdtInput() {
    const rate = getCurrencyRate();
    const bdtValue = parseFloat(document.getElementById('discountValueBdt').value) || 0;
    const sarValue = rate > 0 ? bdtValue / rate : 0;
    document.getElementById('discountValueSar').value = sarValue ? sarValue.toFixed(6) : '';
    validateNumericInput('discountValueBdt');
    calculateInvoiceDiscount();
}

function onFixedSarInput() {
    validateNumericInput('discountValueSar');
    const mode = getCurrencyMode();
    const rate = getCurrencyRate();
    if (mode === 'BDT' && rate > 0) {
        const sarValue = parseFloat(document.getElementById('discountValueSar').value) || 0;
        document.getElementById('discountValueBdt').value = sarValue ? round2(sarValue * rate).toFixed(2) : '';
    }
    calculateInvoiceDiscount();
}

function onPercentageInput() {
    validateNumericInput('discountValuePct');
    calculateInvoiceDiscount();
}

function validateNumericInput(inputId) {
    const input = document.getElementById(inputId);
    if (!input) return;
    const val = parseFloat(input.value);
    if (!isNaN(val) && val < 0) {
        input.value = 0;
        showToast('Value cannot be negative', 'error');
    }
    if (inputId === 'discountValuePct' && !isNaN(val) && val > 100) {
        input.value = 100;
        showToast('Percentage cannot exceed 100%', 'error');
    }
}

function getDiscountValue() {
    const type = document.getElementById('discountType').value;
    if (type === 'percentage') {
        return parseFloat(document.getElementById('discountValuePct').value) || 0;
    }
    return parseFloat(document.getElementById('discountValueSar').value) || 0;
}

function calculateInvoiceDiscount() {
    const type = document.getElementById('discountType').value;
    const value = getDiscountValue();
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
    const discountValue = getDiscountValue();

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

document.addEventListener('currency-toggled', function () {
    const modal = document.getElementById('discountModal');
    if (modal && !modal.classList.contains('hidden')) {
        updateDiscountFieldsVisibility();
        const type = document.getElementById('discountType').value;
        if (type === 'fixed') {
            const mode = getCurrencyMode();
            const rate = getCurrencyRate();
            if (mode === 'BDT' && rate > 0) {
                const sarValue = parseFloat(document.getElementById('discountValueSar').value) || 0;
                document.getElementById('discountValueBdt').value = sarValue ? round2(sarValue * rate).toFixed(2) : '';
            }
        }
    }
});

function updateFinancialSummary(invoice) {
    const totalEl = document.getElementById('financialTotalValue');
    const paidEl = document.getElementById('financialTotalPaid');
    const dueEl = document.getElementById('financialDue');
    const totalBdtEl = document.getElementById('financialTotalValueBdt');
    const paidBdtEl = document.getElementById('financialTotalPaidBdt');
    const dueBdtEl = document.getElementById('financialDueBdt');
    const originalEl = document.getElementById('financialOriginalTotal');
    const originalBdtEl = document.getElementById('financialOriginalTotalBdt');
    const discountIndicatorEl = document.getElementById('financialDiscountIndicator');
    const rate = window.__bookingServerData?.currentCurrencyRate || 0;

    const fmt = (n) => Number(n).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    if (originalEl && invoice.original_total != null) {
        originalEl.innerHTML = Alpine.store('currency').format(invoice.original_total, 2, rate);
    }
    if (discountIndicatorEl) {
        if (invoice.discount_amount > 0) {
            const type = invoice.discount_type === 'fixed_amount' ? 'fixed' : invoice.discount_type;
            const pct = (type === 'percentage') ? ' (' + Number(invoice.discount_value).toString().replace(/\.?0+$/, '') + '%)' : '';
            discountIndicatorEl.innerHTML = '−' + Alpine.store('currency').format(invoice.discount_amount, 2, rate) + ' discount' + pct;
            discountIndicatorEl.classList.remove('hidden');
        } else {
            discountIndicatorEl.classList.add('hidden');
        }
    }

    if (totalEl) totalEl.innerHTML = Alpine.store('currency').format(invoice.total_amount, 2, rate);
    if (paidEl) paidEl.innerHTML = Alpine.store('currency').format(invoice.paid_amount, 2, rate);
    if (dueEl) {
        dueEl.innerHTML = Alpine.store('currency').format(invoice.balance, 2, rate);
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
            '<span class="text-slate-800 font-medium">' + Alpine.store('currency').format(total, 2, window.__bookingServerData?.currentCurrencyRate || 0) + '</span>' +
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
    window.location.href = '{{ route('bookings.download-all-docs', $booking->id) }}';
}

function viewPassengerDetails(passengerId) {
    window.location.href = '/passengers/' + passengerId;
}

function switchTab(tabName) {
    document.querySelectorAll('.tab-content').forEach(el => {
        el.classList.remove('block');
        el.classList.add('hidden');
    });
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.remove('border-blue-600', 'text-blue-600');
        btn.classList.add('border-transparent', 'text-slate-500');
    });
    document.getElementById('content-' + tabName).classList.remove('hidden');
    document.getElementById('content-' + tabName).classList.add('block');
    const activeTab = document.getElementById('tab-' + tabName);
    activeTab.classList.remove('border-transparent', 'text-slate-500');
    activeTab.classList.add('border-blue-600', 'text-blue-600');
}

function toggleReIssueFields(checkboxId, fieldsId) {
    const checkbox = document.getElementById(checkboxId);
    const fields = document.getElementById(fieldsId);
    if (checkbox.checked) {
        fields.classList.remove('hidden');
    } else {
        fields.classList.add('hidden');
        const idx = checkboxId.replace('reIssue_', '');
        document.getElementById('reIssueDateFields_' + idx)?.classList.add('hidden');
    }
}

function toggleTicketOptionFields(pIndex) {
    const ticketOption = document.getElementById('ticketOption_' + pIndex);
    const dateFields = document.getElementById('reIssueDateFields_' + pIndex);
    const probableDateUp = document.getElementById('probableDateUp_' + pIndex);
    const probableDateDown = document.getElementById('probableDateDown_' + pIndex);
    const visaExpiry = document.getElementById('visaExpiry_' + pIndex);

    if (!ticketOption || !dateFields) return;

    dateFields.classList.remove('hidden');
    probableDateUp.classList.add('hidden');
    probableDateDown.classList.add('hidden');
    visaExpiry.classList.add('hidden');

    if (ticketOption.value === 'up') {
        probableDateUp.classList.remove('hidden');
        visaExpiry.classList.remove('hidden');
    } else if (ticketOption.value === 'down') {
        probableDateDown.classList.remove('hidden');
        visaExpiry.classList.remove('hidden');
    } else if (ticketOption.value === 'both') {
        probableDateUp.classList.remove('hidden');
        probableDateDown.classList.remove('hidden');
        visaExpiry.classList.remove('hidden');
    }
}

function openReIssueModal() {
    document.getElementById('reIssueModal').classList.remove('hidden');
    const checkboxes = document.querySelectorAll('#reIssuePassengers input[type="checkbox"]');
    checkboxes.forEach((cb, idx) => {
        cb.checked = false;
        document.getElementById('reIssueFields_' + idx)?.classList.add('hidden');
        document.getElementById('reIssueDateFields_' + idx)?.classList.add('hidden');
        document.getElementById('probableDateUp_' + idx)?.classList.add('hidden');
        document.getElementById('probableDateDown_' + idx)?.classList.add('hidden');
    });
}

function closeReIssueModal() {
    document.getElementById('reIssueModal').classList.add('hidden');
}

function submitReIssueRequest() {
    const passengerRows = document.querySelectorAll('#reIssuePassengers > div');
    const selectedPassengers = [];
    let foundChecked = false;

    passengerRows.forEach((row, pIndex) => {
        const checkbox = document.getElementById('reIssue_' + pIndex);
        if (checkbox && checkbox.checked) {
            foundChecked = true;
            selectedPassengers.push({
                name: checkbox.dataset.name,
                passport: checkbox.dataset.passport,
                ticketOption: document.getElementById('ticketOption_' + pIndex)?.value || '',
                probableDateUp: document.getElementById('probableDateUp_' + pIndex)?.value || '',
                probableDateDown: document.getElementById('probableDateDown_' + pIndex)?.value || '',
                visaExpiry: document.getElementById('visaExpiry_' + pIndex)?.value || '',
            });
        }
    });

    if (!foundChecked) {
        showToast('Please select at least one passenger', 'error');
        return;
    }

    const requests = JSON.parse(localStorage.getItem('reIssueRequests') || '[]');
    requests.push({
        id: Date.now(),
        invoiceId: {{ $booking->id }},
        invoiceNo: @json($booking->invoice_id ?? ''),
        customerName: @json($booking->customer->name ?? ''),
        passengers: selectedPassengers,
        status: 'Pending',
        requestedAt: new Date().toISOString(),
    });
    localStorage.setItem('reIssueRequests', JSON.stringify(requests));

    showToast('Re-issue request submitted successfully!', 'success');
    closeReIssueModal();
    renderReissueHistory();
}

function openReissueDetails(requestId) {
    const requests = JSON.parse(localStorage.getItem('reIssueRequests') || '[]');
    const request = requests.find(r => r.id === requestId);
    if (!request) return;

    const content = document.getElementById('reissueDetailsContent');
    content.innerHTML = `
        <div class="space-y-4">
            <div class="bg-slate-50 rounded-lg p-4">
                <h4 class="text-sm font-medium text-slate-500 mb-3 pb-2 border-b border-slate-200">Request Summary</h4>
                <div class="grid grid-cols-2 gap-3">
                    <div><span class="text-xs text-slate-400">Invoice No</span><p class="text-slate-800">${escapeHtml(request.invoiceNo)}</p></div>
                    <div><span class="text-xs text-slate-400">Customer</span><p class="text-slate-800">${escapeHtml(request.customerName)}</p></div>
                    <div><span class="text-xs text-slate-400">Status</span><p class="text-slate-800">${request.status}</p></div>
                    <div><span class="text-xs text-slate-400">Requested At</span><p class="text-slate-800">${new Date(request.requestedAt).toLocaleString()}</p></div>
                </div>
            </div>
            <div class="bg-slate-50 rounded-lg p-4">
                <h4 class="text-sm font-medium text-slate-500 mb-3 pb-2 border-b border-slate-200">Passengers</h4>
                ${request.passengers.map((p, i) => `
                    <div class="flex justify-between py-2 ${i < request.passengers.length - 1 ? 'border-b border-slate-200' : ''}">
                        <div>
                            <span class="text-slate-800 font-medium">${escapeHtml(p.name)}</span>
                            <span class="text-slate-500 text-sm ml-2">(${escapeHtml(p.passport)})</span>
                        </div>
                        <span class="text-slate-600 text-sm">${p.ticketOption ? 'Ticket: ' + p.ticketOption : '-'}</span>
                    </div>
                `).join('')}
            </div>
        </div>
    `;
    document.getElementById('reissueDetailsModal').classList.remove('hidden');
}

function closeReissueDetailsModal() {
    document.getElementById('reissueDetailsModal').classList.add('hidden');
}

function renderReissueHistory() {
    const tbody = document.getElementById('reissueHistoryBody');
    const emptyEl = document.getElementById('reissueHistoryEmpty');
    if (!tbody) return;

    const allRequests = JSON.parse(localStorage.getItem('reIssueRequests') || '[]');
    const bookingRequests = allRequests.filter(r => r.invoiceId === {{ $booking->id }});

    if (bookingRequests.length === 0) {
        tbody.innerHTML = '';
        if (emptyEl) emptyEl.classList.remove('hidden');
        return;
    }

    if (emptyEl) emptyEl.classList.add('hidden');
    tbody.innerHTML = '';

    bookingRequests.forEach((request) => {
        request.passengers.forEach((p) => {
            let statusBadge = '';
            switch(request.status) {
                case 'Pending': statusBadge = '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-700">Pending</span>'; break;
                case 'Approved': statusBadge = '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-700">Approved</span>'; break;
                case 'Rejected': statusBadge = '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-700">Rejected</span>'; break;
                default: statusBadge = '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-700">Pending</span>';
            }

            const tr = document.createElement('tr');
            tr.className = 'hover:bg-slate-50';
            tr.innerHTML = `
                <td class="px-3 py-2 text-slate-600">${new Date(request.requestedAt).toLocaleDateString('en-CA')}</td>
                <td class="px-3 py-2 text-slate-800">${escapeHtml(p.name)}</td>
                <td class="px-3 py-2 text-slate-600">${escapeHtml(p.passport)}</td>
                <td class="px-3 py-2 text-slate-600">-</td>
                <td class="px-3 py-2 text-slate-800">-</td>
                <td class="px-3 py-2 text-slate-800 text-right font-medium">-</td>
                <td class="px-3 py-2 text-slate-800 text-right font-medium">-</td>
                <td class="px-3 py-2 text-green-600 text-right font-medium">-</td>
                <td class="px-3 py-2 text-slate-600">-</td>
                <td class="px-3 py-2">${statusBadge}</td>
                <td class="px-3 py-2">
                    <button onclick="openReissueDetails(${request.id})" class="text-xs bg-slate-100 hover:bg-slate-200 text-slate-600 px-2 py-1 rounded">View</button>
                </td>
            `;
            tbody.appendChild(tr);
        });
    });
}

function openAddTicketModal() {
    document.getElementById('addTicketModal').classList.remove('hidden');
    const checkboxes = document.querySelectorAll('#addTicketPassengers input[type="checkbox"]');
    checkboxes.forEach((cb, idx) => {
        cb.checked = false;
        document.getElementById('addTicketFields_' + idx)?.classList.add('hidden');
        document.getElementById('addTicketDateFields_' + idx)?.classList.add('hidden');
        document.getElementById('addTicketProbableDateUp_' + idx)?.classList.add('hidden');
        document.getElementById('addTicketProbableDateDown_' + idx)?.classList.add('hidden');
    });
}

function closeAddTicketModal() {
    document.getElementById('addTicketModal').classList.add('hidden');
}

function toggleAddTicketOptionFields(pIndex) {
    const ticketOption = document.getElementById('addTicketOption_' + pIndex);
    const dateFields = document.getElementById('addTicketDateFields_' + pIndex);
    const probableDateUp = document.getElementById('addTicketProbableDateUp_' + pIndex);
    const probableDateDown = document.getElementById('addTicketProbableDateDown_' + pIndex);
    const visaExpiry = document.getElementById('addTicketVisaExpiry_' + pIndex);

    if (!ticketOption || !dateFields) return;

    dateFields.classList.remove('hidden');
    probableDateUp.classList.add('hidden');
    probableDateDown.classList.add('hidden');
    visaExpiry.classList.add('hidden');

    if (ticketOption.value === 'up') {
        probableDateUp.classList.remove('hidden');
        visaExpiry.classList.remove('hidden');
    } else if (ticketOption.value === 'down') {
        probableDateDown.classList.remove('hidden');
        visaExpiry.classList.remove('hidden');
    } else if (ticketOption.value === 'both') {
        probableDateUp.classList.remove('hidden');
        probableDateDown.classList.remove('hidden');
        visaExpiry.classList.remove('hidden');
    }
}

function submitAddTicketRequest() {
    const passengerRows = document.querySelectorAll('#addTicketPassengers > div');
    const selectedPassengers = [];
    let foundChecked = false;

    passengerRows.forEach((row, pIndex) => {
        const checkbox = document.getElementById('addTicket_' + pIndex);
        if (checkbox && checkbox.checked) {
            foundChecked = true;
            selectedPassengers.push({
                name: checkbox.dataset.name,
                passport: checkbox.dataset.passport,
                ticketOption: document.getElementById('addTicketOption_' + pIndex)?.value || '',
                probableDateUp: document.getElementById('addTicketProbableDateUp_' + pIndex)?.value || '',
                probableDateDown: document.getElementById('addTicketProbableDateDown_' + pIndex)?.value || '',
                visaExpiry: document.getElementById('addTicketVisaExpiry_' + pIndex)?.value || '',
            });
        }
    });

    if (!foundChecked) {
        showToast('Please select at least one passenger', 'error');
        return;
    }

    const requests = JSON.parse(localStorage.getItem('addTicketRequests') || '[]');
    requests.push({
        id: Date.now(),
        invoiceId: {{ $booking->id }},
        invoiceNo: @json($booking->invoice_id ?? ''),
        customerName: @json($booking->customer->name ?? ''),
        passengers: selectedPassengers,
        status: 'Pending',
        paymentMethod: '-',
        additionalTicketCost: 0,
        customerPayment: 0,
        profit: 0,
        pnr: '-',
        agent: '-',
        requestedAt: new Date().toISOString(),
    });
    localStorage.setItem('addTicketRequests', JSON.stringify(requests));

    showToast('Additional ticket request submitted successfully!', 'success');
    closeAddTicketModal();
    renderAdditionalTicketHistory();
}

function openRefundModal() {
    document.getElementById('refundModal').classList.remove('hidden');
    const checkboxes = document.querySelectorAll('#refundPassengers input[type="checkbox"]');
    checkboxes.forEach(cb => cb.checked = false);
}

function closeRefundModal() {
    document.getElementById('refundModal').classList.add('hidden');
}

function submitRefundRequest() {
    const selectedPassengers = [];
    let foundChecked = false;

    document.querySelectorAll('#refundPassengers > div').forEach((row, pIndex) => {
        const checkbox = document.getElementById('refund_' + pIndex);
        if (checkbox && checkbox.checked) {
            foundChecked = true;
            selectedPassengers.push({
                name: checkbox.dataset.name,
                passport: checkbox.dataset.passport,
            });
        }
    });

    if (!foundChecked) {
        showToast('Please select at least one passenger', 'error');
        return;
    }

    const requests = JSON.parse(localStorage.getItem('refundRequests') || '[]');
    requests.push({
        id: Date.now(),
        invoiceId: {{ $booking->id }},
        invoiceNo: @json($booking->invoice_id ?? ''),
        customerName: @json($booking->customer->name ?? ''),
        passengers: selectedPassengers,
        status: 'Pending',
        paymentMethod: '-',
        agentRefund: 0,
        customerRefund: 0,
        profit: 0,
        pnr: '-',
        agent: '-',
        requestedAt: new Date().toISOString(),
    });
    localStorage.setItem('refundRequests', JSON.stringify(requests));

    showToast('Refund request submitted successfully!', 'success');
    closeRefundModal();
    renderRefundHistory();
}

function renderRefundHistory() {
    const tbody = document.getElementById('refundHistoryBody');
    const emptyEl = document.getElementById('refundHistoryEmpty');
    if (!tbody) return;

    let allRequests = JSON.parse(localStorage.getItem('refundRequests') || '[]');

    if (allRequests.length === 0) {
        const seedData = [
            { id: 'seed_1', date: '2026-03-22', passengerName: 'Rahim Uddin', passport: 'P3344556', pnr: 'STU901', agent: 'Al-Reem', agentRefund: 400, customerRefund: 450, profit: 50, paymentMethod: 'Bank', status: 'Approved' },
            { id: 'seed_2', date: '2026-03-19', passengerName: 'Nadia Islam', passport: 'P7788990', pnr: 'VWX234', agent: 'Nasser', agentRefund: 550, customerRefund: 600, profit: 50, paymentMethod: 'Cash', status: 'Pending' },
            { id: 'seed_3', date: '2026-03-16', passengerName: 'Karim Hussein', passport: 'P1122445', pnr: 'YZA567', agent: 'Al-Masria', agentRefund: 300, customerRefund: 300, profit: 0, paymentMethod: 'Bank', status: 'Rejected' },
            { id: 'seed_4', date: '2026-03-12', passengerName: 'Laila Mohamed', passport: 'P6677889', pnr: 'BCD890', agent: 'Umrah Plus', agentRefund: 700, customerRefund: 800, profit: 100, paymentMethod: 'Bank', status: 'Approved' },
            { id: 'seed_5', date: '2026-03-08', passengerName: 'Tariq Ahmed', passport: 'P9900112', pnr: 'EFG123', agent: 'Al-Reem', agentRefund: 500, customerRefund: 550, profit: 50, paymentMethod: 'Cash', status: 'Approved' },
            { id: 'seed_6', date: '2026-03-01', passengerName: 'Sabrina Khan', passport: 'P2233445', pnr: 'HIJ456', agent: 'Nasser', agentRefund: 650, customerRefund: 650, profit: 0, paymentMethod: 'Bank', status: 'Pending' },
        ];

        const refundRequests = JSON.parse(localStorage.getItem('refundRequests') || '[]');
        if (refundRequests.length === 0) {
            allRequests = seedData;
            localStorage.setItem('refundRequests_seed', JSON.stringify(seedData));
        }
    }

    const bookingRequests = allRequests.filter(r => r.invoiceId === {{ $booking->id }} || !r.invoiceId);

    if (bookingRequests.length === 0) {
        tbody.innerHTML = '';
        if (emptyEl) emptyEl.classList.remove('hidden');
        return;
    }

    if (emptyEl) emptyEl.classList.add('hidden');
    tbody.innerHTML = '';

    bookingRequests.forEach((item, index) => {
        let statusBadge = '';
        switch(item.status) {
            case 'Pending': statusBadge = '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-700">Pending</span>'; break;
            case 'Approved': statusBadge = '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-700">Approved</span>'; break;
            case 'Rejected': statusBadge = '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-700">Rejected</span>'; break;
            default: statusBadge = '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-700">' + item.status + '</span>';
        }

        const tr = document.createElement('tr');
        tr.className = 'hover:bg-slate-50';
        tr.innerHTML = `
            <td class="px-3 py-2 text-slate-600">${item.date || new Date(item.requestedAt).toLocaleDateString('en-CA')}</td>
            <td class="px-3 py-2 text-slate-800">${escapeHtml(item.passengerName || item.passengers?.[0]?.name || '-')}</td>
            <td class="px-3 py-2 text-slate-600">${escapeHtml(item.passport || item.passengers?.[0]?.passport || '-')}</td>
            <td class="px-3 py-2 text-slate-600">${item.pnr || '-'}</td>
            <td class="px-3 py-2 text-slate-800">${escapeHtml(item.agent || '-')}</td>
            <td class="px-3 py-2 text-slate-800 text-right font-medium">${Alpine.store('currency').format(item.agentRefund || 0, 2, window.__bookingServerData?.currentCurrencyRate || 0)}</td>
            <td class="px-3 py-2 text-slate-800 text-right font-medium">${Alpine.store('currency').format(item.customerRefund || 0, 2, window.__bookingServerData?.currentCurrencyRate || 0)}</td>
            <td class="px-3 py-2 text-green-600 text-right font-medium">${Alpine.store('currency').format(item.profit || 0, 2, window.__bookingServerData?.currentCurrencyRate || 0)}</td>
            <td class="px-3 py-2 text-slate-600">${item.paymentMethod || '-'}</td>
            <td class="px-3 py-2">${statusBadge}</td>
            <td class="px-3 py-2">
                <button onclick="openRefundDetails(${item.id || index})" class="text-xs bg-slate-100 hover:bg-slate-200 text-slate-600 px-2 py-1 rounded">View</button>
            </td>
        `;
        tbody.appendChild(tr);
    });
}

function openRefundDetails(id) {
    const allRequests = JSON.parse(localStorage.getItem('refundRequests') || '[]');
    const seedData = JSON.parse(localStorage.getItem('refundRequests_seed') || '[]');
    const allItems = [...allRequests, ...seedData];
    const item = allItems.find(r => r.id === id);
    if (!item) return;

    const content = document.getElementById('refundDetailsContent');
    content.innerHTML = generateRefundDetailsHTML(item);
    document.getElementById('refundDetailsModal').classList.remove('hidden');
}

function generateRefundDetailsHTML(item) {
    return `
        <div class="space-y-4">
            <div class="bg-slate-50 rounded-lg p-4">
                <h4 class="text-sm font-medium text-slate-500 mb-3 pb-2 border-b border-slate-200">Summary</h4>
                <div class="grid grid-cols-2 gap-3">
                    <div><span class="text-xs text-slate-400">Passenger Name</span><p class="text-slate-800">${escapeHtml(item.passengerName || item.passengers?.[0]?.name || '-')}</p></div>
                    <div><span class="text-xs text-slate-400">Passport No.</span><p class="text-slate-800">${escapeHtml(item.passport || item.passengers?.[0]?.passport || '-')}</p></div>
                    <div><span class="text-xs text-slate-400">PNR</span><p class="text-slate-800">${item.pnr || '-'}</p></div>
                    <div><span class="text-xs text-slate-400">Agent</span><p class="text-slate-800">${escapeHtml(item.agent || '-')}</p></div>
                    <div><span class="text-xs text-slate-400">Agent Refund Amount</span><p class="text-slate-800 font-medium">${Alpine.store('currency').format(item.agentRefund || 0, 2, window.__bookingServerData?.currentCurrencyRate || 0)}</p></div>
                    <div><span class="text-xs text-slate-400">Customer Refund Amount</span><p class="text-slate-800 font-medium">${Alpine.store('currency').format(item.customerRefund || 0, 2, window.__bookingServerData?.currentCurrencyRate || 0)}</p></div>
                    <div><span class="text-xs text-slate-400">Profit</span><p class="text-green-600 font-medium">${Alpine.store('currency').format(item.profit || 0, 2, window.__bookingServerData?.currentCurrencyRate || 0)}</p></div>
                    <div><span class="text-xs text-slate-400">Payment Method</span><p class="text-slate-800">${item.paymentMethod || '-'}</p></div>
                </div>
            </div>
            <div class="bg-slate-50 rounded-lg p-4">
                <h4 class="text-sm font-medium text-slate-500 mb-3 pb-2 border-b border-slate-200">Refund Breakdown</h4>
                <div class="space-y-3">
                    <div class="flex justify-between">
                        <span class="text-slate-500">Original Ticket Cost</span>
                        <span class="text-slate-800 font-medium">2,500 SAR</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-500">Cancellation Charges</span>
                        <span class="text-red-600 font-medium">-500 SAR</span>
                    </div>
                    <div class="flex justify-between pt-2 border-t border-slate-200">
                        <span class="text-slate-500">Refund to Customer</span>
                        <span class="text-blue-600 font-medium">2,000 SAR</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-500">Agent Refund to Company</span>
                        <span class="text-green-600 font-medium">1,800 SAR</span>
                    </div>
                </div>
            </div>
        </div>
    `;
}

function closeRefundDetailsModal() {
    document.getElementById('refundDetailsModal').classList.add('hidden');
}

function renderAdditionalTicketHistory() {
    const tbody = document.getElementById('additionalTicketHistoryBody');
    const emptyEl = document.getElementById('additionalTicketHistoryEmpty');
    if (!tbody) return;

    let allRequests = JSON.parse(localStorage.getItem('addTicketRequests') || '[]');

    if (allRequests.length === 0) {
        const seedData = [
            { id: 'at_seed_1', date: '2026-03-25', passengerName: 'Ahmed Hassan', passport: 'P1234567', pnr: 'ABC123', agent: 'Al-Reem', additionalTicketCost: 450, customerPayment: 500, profit: 50, paymentMethod: 'Bank', status: 'Approved' },
            { id: 'at_seed_2', date: '2026-03-22', passengerName: 'Fatima Rahman', passport: 'P7654321', pnr: 'DEF456', agent: 'Nasser', additionalTicketCost: 600, customerPayment: 650, profit: 50, paymentMethod: 'Cash', status: 'Pending' },
            { id: 'at_seed_3', date: '2026-03-18', passengerName: 'Mohammed Ali', passport: 'P1122334', pnr: 'GHI789', agent: 'Al-Masria', additionalTicketCost: 350, customerPayment: 350, profit: 0, paymentMethod: 'Bank', status: 'Approved' },
            { id: 'at_seed_4', date: '2026-03-14', passengerName: 'Sara Ahmed', passport: 'P9988776', pnr: 'JKL012', agent: 'Umrah Plus', additionalTicketCost: 800, customerPayment: 900, profit: 100, paymentMethod: 'Bank', status: 'Pending' },
        ];

        const addTicketRequests = JSON.parse(localStorage.getItem('addTicketRequests') || '[]');
        if (addTicketRequests.length === 0) {
            allRequests = seedData;
            localStorage.setItem('addTicketRequests_seed', JSON.stringify(seedData));
        }
    }

    const bookingRequests = allRequests.filter(r => r.invoiceId === {{ $booking->id }} || !r.invoiceId);

    if (bookingRequests.length === 0) {
        tbody.innerHTML = '';
        if (emptyEl) emptyEl.classList.remove('hidden');
        return;
    }

    if (emptyEl) emptyEl.classList.add('hidden');
    tbody.innerHTML = '';

    bookingRequests.forEach((item, index) => {
        let statusBadge = '';
        switch(item.status) {
            case 'Pending': statusBadge = '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-700">Pending</span>'; break;
            case 'Approved': statusBadge = '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-700">Approved</span>'; break;
            case 'Rejected': statusBadge = '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-700">Rejected</span>'; break;
            default: statusBadge = '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-700">' + item.status + '</span>';
        }

        const tr = document.createElement('tr');
        tr.className = 'hover:bg-slate-50';
        tr.innerHTML = `
            <td class="px-3 py-2 text-slate-600">${item.date || new Date(item.requestedAt).toLocaleDateString('en-CA')}</td>
            <td class="px-3 py-2 text-slate-800">${escapeHtml(item.passengerName || item.passengers?.[0]?.name || '-')}</td>
            <td class="px-3 py-2 text-slate-600">${escapeHtml(item.passport || item.passengers?.[0]?.passport || '-')}</td>
            <td class="px-3 py-2 text-slate-600">${item.pnr || '-'}</td>
            <td class="px-3 py-2 text-slate-800">${escapeHtml(item.agent || '-')}</td>
            <td class="px-3 py-2 text-slate-800 text-right font-medium">${Alpine.store('currency').format(item.additionalTicketCost || 0, 2, window.__bookingServerData?.currentCurrencyRate || 0)}</td>
            <td class="px-3 py-2 text-slate-800 text-right font-medium">${Alpine.store('currency').format(item.customerPayment || 0, 2, window.__bookingServerData?.currentCurrencyRate || 0)}</td>
            <td class="px-3 py-2 text-green-600 text-right font-medium">${Alpine.store('currency').format(item.profit || 0, 2, window.__bookingServerData?.currentCurrencyRate || 0)}</td>
            <td class="px-3 py-2 text-slate-600">${item.paymentMethod || '-'}</td>
            <td class="px-3 py-2">${statusBadge}</td>
            <td class="px-3 py-2">
                <button onclick="openAddTicketDetails(${item.id || index})" class="text-xs bg-slate-100 hover:bg-slate-200 text-slate-600 px-2 py-1 rounded">View</button>
            </td>
        `;
        tbody.appendChild(tr);
    });
}

function openAddTicketDetails(id) {
    const allRequests = JSON.parse(localStorage.getItem('addTicketRequests') || '[]');
    const seedData = JSON.parse(localStorage.getItem('addTicketRequests_seed') || '[]');
    const allItems = [...allRequests, ...seedData];
    const item = allItems.find(r => r.id === id);
    if (!item) return;

    const content = document.getElementById('addTicketDetailsContent');
    content.innerHTML = generateAddTicketDetailsHTML(item);
    document.getElementById('addTicketDetailsModal').classList.remove('hidden');
}

function generateAddTicketDetailsHTML(item) {
    return `
        <div class="space-y-4">
            <div class="bg-slate-50 rounded-lg p-4">
                <h4 class="text-sm font-medium text-slate-500 mb-3 pb-2 border-b border-slate-200">Summary</h4>
                <div class="grid grid-cols-2 gap-3">
                    <div><span class="text-xs text-slate-400">Passenger Name</span><p class="text-slate-800">${escapeHtml(item.passengerName || item.passengers?.[0]?.name || '-')}</p></div>
                    <div><span class="text-xs text-slate-400">Passport No.</span><p class="text-slate-800">${escapeHtml(item.passport || item.passengers?.[0]?.passport || '-')}</p></div>
                    <div><span class="text-xs text-slate-400">PNR</span><p class="text-slate-800">${item.pnr || '-'}</p></div>
                    <div><span class="text-xs text-slate-400">Agent</span><p class="text-slate-800">${escapeHtml(item.agent || '-')}</p></div>
                    <div><span class="text-xs text-slate-400">Additional Ticket Cost</span><p class="text-slate-800 font-medium">${Alpine.store('currency').format(item.additionalTicketCost || 0, 2, window.__bookingServerData?.currentCurrencyRate || 0)}</p></div>
                    <div><span class="text-xs text-slate-400">Customer Payment</span><p class="text-slate-800 font-medium">${Alpine.store('currency').format(item.customerPayment || 0, 2, window.__bookingServerData?.currentCurrencyRate || 0)}</p></div>
                    <div><span class="text-xs text-slate-400">Profit</span><p class="text-green-600 font-medium">${Alpine.store('currency').format(item.profit || 0, 2, window.__bookingServerData?.currentCurrencyRate || 0)}</p></div>
                    <div><span class="text-xs text-slate-400">Payment Method</span><p class="text-slate-800">${item.paymentMethod || '-'}</p></div>
                </div>
            </div>
            <div class="bg-slate-50 rounded-lg p-4">
                <h4 class="text-sm font-medium text-slate-500 mb-3 pb-2 border-b border-slate-200">Ticket Details</h4>
                <div class="space-y-3">
                    <div class="flex justify-between">
                        <span class="text-slate-500">Route</span>
                        <span class="text-slate-800 font-medium">DAC-JED-DAC</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-500">Travel Date</span>
                        <span class="text-blue-600 font-medium">${item.probableDateUp || item.date || '-'}</span>
                    </div>
                    <div class="flex justify-between pt-2 border-t border-slate-200">
                        <span class="text-slate-500">Ticket Option</span>
                        <span class="text-slate-800 font-medium">${item.passengers?.[0]?.ticketOption || '-'}</span>
                    </div>
                </div>
            </div>
        </div>
    `;
}

function closeAddTicketDetailsModal() {
    document.getElementById('addTicketDetailsModal').classList.add('hidden');
}

renderReissueHistory();
renderRefundHistory();
renderAdditionalTicketHistory();

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
                var deleteBtn = {{ $canDeleteDocument ? 'true' : 'false' }} ? '<button onclick="deleteDocument(' + doc.id + ')" class="text-red-500 hover:text-red-700 text-xs mr-2">Delete</button>' : '';
                item.innerHTML = '<span class="text-sm text-slate-700 truncate">' + (doc.display_name || 'Document') + '</span><div class="flex gap-2">' + deleteBtn + '<button onclick="downloadDoc(' + doc.id + ')" class="text-blue-600 hover:text-blue-800 text-xs">Download</button></div>';
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
    window.location.href = '{{ route('bookings.download-all-docs', ['booking' => $booking->id, 'scope' => 'customer']) }}';
}

function downloadAllPassengerDocs() {
    window.location.href = '{{ route('bookings.download-all-docs', ['booking' => $booking->id, 'scope' => 'passenger']) }}';
}

function appendPassengerDocToList(doc) {
    const list = document.getElementById('passengerDocumentsList');
    if (!list) return;
    const emptyState = list.querySelector('p');
    if (emptyState) emptyState.remove();
    const item = document.createElement('div');
    item.className = 'flex justify-between items-center bg-white p-3 rounded-lg border border-slate-200';
    var deleteBtn = {{ $canDeletePassengerDocument ? 'true' : 'false' }} ? '<button onclick="deleteDocument(' + doc.id + ')" class="text-red-500 hover:text-red-700 text-xs mr-2">Delete</button>' : '';
    item.innerHTML = '<span class="text-sm text-slate-700 truncate">' + (doc.display_name || 'Document') + '</span><div class="flex gap-2">' + deleteBtn + '<button onclick="downloadDoc(' + doc.id + ')" class="text-blue-600 hover:text-blue-800 text-xs">Download</button></div>';
    list.appendChild(item);
}

function deleteDocument(docId) {
    if (!confirm('Are you sure you want to delete this document?')) return;

    fetch('/documents/' + docId, {
        method: 'DELETE',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Document deleted successfully');
            const list = document.getElementById('customerDocumentsList');
            const docItem = document.querySelector(`button[onclick*="deleteDocument(${docId})"]`)?.closest('.flex.justify-between');
            if (docItem) docItem.remove();
        } else {
            showToast('Failed to delete document', 'error');
        }
    })
    .catch(() => showToast('Failed to delete document', 'error'));
}

function downloadDoc(docId) {
    window.open('/documents/' + docId + '/download', '_blank');
}
</script>
@endpush

@endsection