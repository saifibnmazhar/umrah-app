@extends('layouts.app')
@section('title', 'Fingerprint Report')
@section('content')
<div class="w-full mx-auto pt-6">
    <div class="sticky top-0 z-30 bg-white py-2 mb-3">
        <span class="text-sm text-gray-500 font-medium">Report</span>
        <span class="text-sm text-gray-400 mx-1">></span>
        <span class="text-sm text-gray-700 font-semibold">Fingerprint Report</span>
    </div>

    @livewire('report.fingerprint-report-table')

    <div x-data="fingerprintDetailsModal()"
         x-show="showDetailsModal"
         x-cloak
         class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4"
         @@show-fingerprint-details.window="loadDetails($event.detail)">
        <div class="bg-white rounded-xl shadow-2xl max-w-4xl w-full max-h-[90vh] overflow-hidden">
            <div class="flex justify-between items-center px-6 py-4 border-b border-gray-200 bg-gray-50">
                <h3 class="text-lg font-bold text-gray-800">Passenger Details</h3>
                <button @click="closeDetailsModal()" class="text-gray-500 hover:text-gray-700 transition-colors">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <div class="p-6 overflow-y-auto max-h-[calc(90vh-80px)]">
                <template x-if="loadingDetails">
                    <div class="text-center py-8 text-gray-500">Loading...</div>
                </template>
                <template x-if="!loadingDetails && details">
                    <div class="space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="text-xs font-medium text-gray-500">Invoice ID</label>
                                <p class="text-sm font-semibold" x-text="details.invoice_id"></p>
                            </div>
                            <div>
                                <label class="text-xs font-medium text-gray-500">Customer Name</label>
                                <p class="text-sm" x-text="details.customer_name"></p>
                            </div>
                            <div>
                                <label class="text-xs font-medium text-gray-500">Booking Date</label>
                                <p class="text-sm" x-text="details.booking_date"></p>
                            </div>
                            <div>
                                <label class="text-xs font-medium text-gray-500">Fingerprint Deadline</label>
                                <p class="text-sm" x-text="details.fingerprint_deadline"></p>
                            </div>
                        </div>

                        <div class="border-t border-gray-200 pt-4">
                            <h4 class="text-sm font-bold text-gray-700 mb-3">Passenger Information</h4>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="text-xs font-medium text-gray-500">Passenger Name</label>
                                    <p class="text-sm" x-text="details.passenger.name"></p>
                                </div>
                                <div>
                                    <label class="text-xs font-medium text-gray-500">Passport No</label>
                                    <p class="text-sm" x-text="details.passenger.passport_no"></p>
                                </div>
                                <div>
                                    <label class="text-xs font-medium text-gray-500">Mobile</label>
                                    <p class="text-sm whitespace-pre-line" x-text="details.customer_mobile + '\\n' + details.passenger.mobile"></p>
                                </div>
                                <div>
                                    <label class="text-xs font-medium text-gray-500">Address</label>
                                    <p class="text-sm" x-text="details.passenger.address || '-'"></p>
                                </div>
                            </div>
                        </div>

                        <div class="border-t border-gray-200 pt-4">
                            <h4 class="text-sm font-bold text-gray-700 mb-3">Fingerprint Status</h4>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="text-xs font-medium text-gray-500">Completed Date</label>
                                    <p class="text-sm" x-text="details.completed_date || '-'"></p>
                                </div>
                                <div>
                                    <label class="text-xs font-medium text-gray-500">Fingerprint Status</label>
                                    <p class="text-sm font-medium" x-text="details.status_display"></p>
                                </div>
                            </div>
                        </div>

                        <div class="border-t border-gray-200 pt-4">
                            <h4 class="text-sm font-bold text-gray-700 mb-3">Flight Information</h4>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="text-xs font-medium text-gray-500">Required Flight</label>
                                    <p class="text-sm" x-text="details.required_flight || '-'"></p>
                                </div>
                                <div>
                                    <label class="text-xs font-medium text-gray-500">Actual Flight</label>
                                    <p class="text-sm" x-text="details.actual_flight || '-'"></p>
                                </div>
                            </div>
                        </div>

                        <template x-if="details.can_view_financials">
                            <div class="border-t border-gray-200 pt-4">
                                <h4 class="text-sm font-bold text-gray-700 mb-3">Financial Summary</h4>
                                <div class="grid grid-cols-3 gap-4">
                                    <div>
                                        <label class="text-xs font-medium text-gray-500">Fingerprint Charge</label>
                                        <p class="text-sm font-semibold text-green-700" x-text="details.fingerprint_charge ? formatCurrency(details.fingerprint_charge, details.rate) : '-'"></p>
                                    </div>
                                    <div>
                                        <label class="text-xs font-medium text-gray-500">Fingerprint Cost</label>
                                        <p class="text-sm" x-text="formatCurrency(details.fingerprint_cost, details.rate)"></p>
                                    </div>
                                    <div>
                                        <label class="text-xs font-medium text-gray-500">Profit/Loss</label>
                                        <p class="text-sm font-semibold">
                                            <span x-show="details.profit > 0" class="text-green-700" x-text="'Profit: ' + formatCurrency(details.profit, details.rate)"></span>
                                            <span x-show="details.loss > 0" class="text-red-700" x-text="'Loss: ' + formatCurrency(details.loss, details.rate)"></span>
                                            <span x-show="!details.profit && !details.loss">-</span>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </template>

                        <div class="border-t border-gray-200 pt-4">
                            <h4 class="text-sm font-bold text-gray-700 mb-3">Reschedule History</h4>
                            <template x-if="details.reschedule_history && details.reschedule_history.length > 0">
                                <div class="overflow-x-auto">
                                    <table class="w-full text-xs border border-gray-300">
                                        <thead class="bg-gray-100">
                                            <tr>
                                                <th class="px-3 py-2 text-left font-medium border-b border-r border-gray-300">Previous Date</th>
                                                <th class="px-3 py-2 text-left font-medium border-b border-r border-gray-300">New Date</th>
                                                <th class="px-3 py-2 text-left font-medium border-b border-r border-gray-300">Rescheduled By</th>
                                                <th class="px-3 py-2 text-left font-medium border-b border-r border-gray-300">Rescheduled At</th>
                                                <th class="px-3 py-2 text-left font-medium border-b">Notes/Reason</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <template x-for="(item, idx) in details.reschedule_history" :key="idx">
                                                <tr class="border-b border-gray-200 hover:bg-gray-50">
                                                    <td class="px-3 py-2 border-r border-gray-200" x-text="item.previous_date"></td>
                                                    <td class="px-3 py-2 border-r border-gray-200" x-text="item.new_date"></td>
                                                    <td class="px-3 py-2 border-r border-gray-200" x-text="item.rescheduled_by"></td>
                                                    <td class="px-3 py-2 border-r border-gray-200" x-text="item.rescheduled_at"></td>
                                                    <td class="px-3 py-2" x-text="item.reason + (item.remarks !== '-' ? ' - ' + item.remarks : '')"></td>
                                                </tr>
                                            </template>
                                        </tbody>
                                    </table>
                                </div>
                            </template>
                            <template x-if="!details.reschedule_history || details.reschedule_history.length === 0">
                                <p class="text-sm text-gray-500 text-center py-4">No reschedule history for this passenger</p>
                            </template>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function fingerprintDetailsModal() {
    return {
        showDetailsModal: false,
        loadingDetails: false,
        details: null,
        canViewFinancials: true, // @json(auth()->user()->hasRole('Super Admin') || auth()->user()->hasRole('Co Admin') || auth()->user()->hasRole('Auditor')),

        async loadDetails(fingerprintDetailId) {
            if (!fingerprintDetailId) {
                window.showToast('No detail record available', 'info');
                return;
            }
            this.loadingDetails = true;
            this.showDetailsModal = true;
            this.details = null;
            try {
                const response = await fetch(`/api/reports/fingerprint/details/${fingerprintDetailId}`);
                const result = await response.json();
                this.details = result;
            } catch (error) {
                console.error('Failed to load details:', error);
                window.showToast('Failed to load details', 'error');
            } finally {
                this.loadingDetails = false;
            }
        },

        closeDetailsModal() {
            this.showDetailsModal = false;
            this.details = null;
        },

        formatCurrency(amount, rate) {
            if (!amount) return '-';
            const num = parseFloat(amount);
            if (isNaN(num)) return '-';
            const formatted = num.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            return rate && rate !== 1 ? formatted + ' × ' + rate : formatted;
        }
    };
}
</script>
@endpush
