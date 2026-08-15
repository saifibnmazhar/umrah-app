@extends('layouts.app')
@section('title', 'Re-Issue Confirmation')
@section('content')
<div class="max-w-4xl mx-auto py-6">
    <div id="confirmationContent" class="space-y-6">
        <div class="bg-white rounded-xl shadow-lg p-6">
            <div class="mb-6 pb-4 border-b border-slate-200">
                <div class="flex justify-between items-start">
                    <div>
                        <h2 class="text-xl font-semibold text-slate-800">Re-Issue Confirmation</h2>
                        <p class="text-slate-500 text-sm mt-1">Invoice ID: <span id="invoiceId">-</span> (<span id="invoiceNo">-</span>)</p>
                    </div>
                    <span id="statusBadge" class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-700">Pending</span>
                </div>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-3 gap-4 mb-6">
                <div><span class="text-slate-500 text-sm">Customer</span><p class="text-slate-800 font-medium" id="customerName">-</p></div>
                <div><span class="text-slate-500 text-sm">Mobile</span><p class="text-slate-800 font-medium" id="customerMobile">-</p></div>
                <div><span class="text-slate-500 text-sm">Branch</span><p class="text-slate-800 font-medium" id="branch">-</p></div>
                <div><span class="text-slate-500 text-sm">Passengers</span><p class="text-slate-800 font-medium" id="passengerCount">-</p></div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-lg p-6">
            <h3 class="text-lg font-semibold text-slate-700 mb-4">Passengers</h3>
            <div id="passengerList" class="space-y-3"></div>
        </div>

        <div class="mt-6 pt-4 border-t border-slate-200 flex gap-3">
            <a href="{{ route('dashboard') }}" class="px-6 py-2 border border-slate-300 text-slate-600 rounded-lg hover:bg-slate-50 transition font-medium inline-block">Back to Dashboard</a>
        </div>
    </div>

    <div id="notFound" class="hidden bg-white rounded-xl shadow-lg p-12 text-center">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 mx-auto text-slate-300 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <h2 class="text-xl font-semibold text-slate-800 mb-2">Request Not Found</h2>
        <p class="text-slate-500 mb-6">The re-issue confirmation request you're looking for could not be found.</p>
        <a href="{{ route('dashboard') }}" class="px-6 py-2 bg-slate-700 text-white rounded-lg hover:bg-slate-800 transition font-medium inline-block">Go to Dashboard</a>
    </div>

    <div id="toastContainer" class="fixed top-4 right-4 z-[70] space-y-2"></div>

    <div id="processConfirmationModal" class="hidden fixed inset-0 z-50 flex items-center justify-center">
        <div class="fixed inset-0 bg-black/50" onclick="closeProcessConfirmationModal()"></div>
        <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-3xl mx-4 p-6 max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-start mb-6">
                <div>
                    <h3 class="text-xl font-semibold text-slate-800">Process Confirmation</h3>
                    <p class="text-slate-500 text-sm mt-1" id="modalPassengerName"></p>
                </div>
                <button onclick="closeProcessConfirmationModal()" class="text-slate-400 hover:text-slate-600">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <div class="mb-6">
                <h4 class="text-sm font-medium text-slate-700 mb-3 pb-2 border-b border-slate-200">Passenger Info</h4>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div><span class="text-slate-500 text-xs">Passport No.</span><p class="text-slate-800 font-medium" id="infoPassport">-</p></div>
                    <div><span class="text-slate-500 text-xs">Mobile</span><p class="text-slate-800 font-medium" id="infoMobile">-</p></div>
                    <div><span class="text-slate-500 text-xs">PNR</span><p class="text-slate-800 font-medium" id="infoPnr">ABCD1234</p></div>
                    <div><span class="text-slate-500 text-xs">Flight Date</span><p class="text-slate-800 font-medium" id="infoFlightDate">2026-05-15</p></div>
                    <div><span class="text-slate-500 text-xs">Route</span><p class="text-slate-800 font-medium" id="infoRoute">DAC-JED-DAC</p></div>
                    <div><span class="text-slate-500 text-xs">Airline</span><p class="text-slate-800 font-medium" id="infoAirline">Saudi Arabian Airlines</p></div>
                    <div><span class="text-slate-500 text-xs">Class</span><p class="text-slate-800 font-medium" id="infoClass">Economy</p></div>
                    <div><span class="text-slate-500 text-xs">Type</span><p class="text-slate-800 font-medium" id="infoType">Adult</p></div>
                </div>
            </div>

            <div class="mb-6">
                <div class="flex items-center justify-between rounded-lg bg-emerald-50 border border-emerald-200 px-4 py-3">
                    <span class="text-sm font-medium text-emerald-700">Refund Payable (SAR)</span>
                    <span class="text-lg font-semibold text-emerald-700" id="infoRefundPayable">0.00</span>
                </div>
            </div>

            <div class="mb-6">
                <h4 class="text-sm font-medium text-slate-700 mb-3 pb-2 border-b border-slate-200">Re-Issue Details</h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Reason</label>
                        <select id="inputReason" onchange="handleReasonChange()" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none bg-white">
                            <option value="">Select Reason</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Payment By</label>
                        <select id="inputPaymentBy" onchange="handlePaymentByChange()" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none bg-white">
                            <option value="">Select</option>
                            <option value="customer">Customer</option>
                            <option value="airline">Airline</option>
                            <option value="employee">Employee</option>
                        </select>
                    </div>
                    <div id="fieldPaymentOption" class="hidden">
                        <label class="block text-sm font-medium text-slate-700 mb-1">Payment Option</label>
                        <select id="inputPaymentOption" onchange="handlePaymentOptionChange()" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none bg-white">
                            <option value="customer_payment">Customer Payment</option>
                            <option value="refund_adjustment">Refund Adjustment</option>
                        </select>
                    </div>
                    <div id="fieldRefundAdjustment" class="hidden">
                        <div id="fieldRefundAdjustmentSar">
                            <label class="block text-sm font-medium text-slate-700 mb-1">Refund Adjustment Amount (SAR)</label>
                            <input type="number" min="0" step="0.000001" id="inputRefundAdjustment" oninput="updateTotals()" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none" placeholder="0">
                        </div>
                        <div id="fieldRefundAdjustmentBdt" class="hidden">
                            <label class="block text-sm font-medium text-slate-700 mb-1">Refund Adjustment Amount (BDT)</label>
                            <input type="number" min="0" step="0.000001" id="inputRefundAdjustmentBdt" oninput="handleFieldBdtInput('inputRefundAdjustment','inputRefundAdjustmentBdt'); updateTotals()" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none" placeholder="0">
                            <input type="number" id="inputRefundAdjustmentBdtSar" readonly class="w-full mt-1 px-3 py-2 border border-slate-200 rounded-lg bg-slate-50 text-slate-500 text-sm" placeholder="0">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Route Type</label>
                        <select id="inputRouteType" onchange="handleFilterChange()" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none bg-white">
                            <option value="">Select</option>
                            <option value="oneway_inbound">One Way-Inbound</option>
                            <option value="oneway_outbound">One Way-Outbound</option>
                            <option value="round">Round</option>
                            <option value="multi_city">Multi City</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Ticket Type</label>
                        <select id="inputTicketType" onchange="handleFilterChange()" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none bg-white">
                            <option value="">All</option>
                            <option value="regular">Regular</option>
                            <option value="offer">Offer</option>
                            <option value="group">Group</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Flight Type</label>
                        <select id="inputFlightType" onchange="handleFilterChange()" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none bg-white">
                            <option value="">All</option>
                            <option value="direct">Direct</option>
                            <option value="transit">Transit</option>
                        </select>
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-slate-700 mb-1">Ticket</label>
                        <select id="inputTicketFare" onchange="handleTicketSelect()" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none bg-white">
                            <option value="">Select Ticket</option>
                        </select>
                    </div>
                    <div>
                        <div id="fieldSellingFareSar">
                            <label class="block text-sm font-medium text-slate-700 mb-1">Selling Fare (SAR)</label>
                            <input type="number" id="inputSellingFare" oninput="handleFieldSarInput('inputSellingFare','inputSellingFareBdt')" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none" placeholder="0">
                        </div>
                        <div id="fieldSellingFareBdt" class="hidden">
                            <label class="block text-sm font-medium text-slate-700 mb-1">Selling Fare (BDT)</label>
                            <input type="number" id="inputSellingFareBdt" oninput="handleFieldBdtInput('inputSellingFare','inputSellingFareBdt')" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none" placeholder="0">
                            <input type="number" id="inputSellingFareBdtSar" readonly class="w-full mt-1 px-3 py-2 border border-slate-200 rounded-lg bg-slate-50 text-slate-500 text-sm" placeholder="0">
                        </div>
                    </div>
                    <div>
                        <div id="fieldNetFareSar">
                            <label class="block text-sm font-medium text-slate-700 mb-1">Net Fare (SAR)</label>
                            <input type="number" id="inputNetFare" oninput="handleFieldSarInput('inputNetFare','inputNetFareBdt')" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none" placeholder="0">
                        </div>
                        <div id="fieldNetFareBdt" class="hidden">
                            <label class="block text-sm font-medium text-slate-700 mb-1">Net Fare (BDT)</label>
                            <input type="number" id="inputNetFareBdt" oninput="handleFieldBdtInput('inputNetFare','inputNetFareBdt')" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none" placeholder="0">
                            <input type="number" id="inputNetFareBdtSar" readonly class="w-full mt-1 px-3 py-2 border border-slate-200 rounded-lg bg-slate-50 text-slate-500 text-sm" placeholder="0">
                        </div>
                    </div>
                    <div id="fieldOfferPrice" class="hidden">
                        <div id="fieldOfferPriceSar">
                            <label class="block text-sm font-medium text-slate-700 mb-1">Offer Price (SAR)</label>
                            <input type="number" id="inputOfferPrice" oninput="handleFieldSarInput('inputOfferPrice','inputOfferPriceBdt')" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none" placeholder="0">
                        </div>
                        <div id="fieldOfferPriceBdt" class="hidden">
                            <label class="block text-sm font-medium text-slate-700 mb-1">Offer Price (BDT)</label>
                            <input type="number" id="inputOfferPriceBdt" oninput="handleFieldBdtInput('inputOfferPrice','inputOfferPriceBdt')" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none" placeholder="0">
                            <input type="number" id="inputOfferPriceBdtSar" readonly class="w-full mt-1 px-3 py-2 border border-slate-200 rounded-lg bg-slate-50 text-slate-500 text-sm" placeholder="0">
                        </div>
                    </div>
                    <div id="fieldUpDate">
                        <label class="block text-sm font-medium text-slate-700 mb-1">Inbound Date</label>
                        <input type="text" id="inputUpDate" placeholder="DD-MMM-YY" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none">
                    </div>
                    <div id="fieldDownDate">
                        <label class="block text-sm font-medium text-slate-700 mb-1">Outbound Date</label>
                        <input type="text" id="inputDownDate" placeholder="DD-MMM-YY" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Re-issue Date</label>
                        <input type="text" id="inputTravelDate" placeholder="DD-MMM-YY" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Agent</label>
                        <select id="inputAgent" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none bg-white">
                            <option value="">Select Agent</option>
                        </select>
                    </div>
                    <div>
                        <div id="fieldReIssueChargeSar">
                            <label class="block text-sm font-medium text-slate-700 mb-1">Re-Issue Charge (SAR)</label>
                            <input type="number" id="inputReIssueCharge" oninput="handleFieldSarInput('inputReIssueCharge','inputReIssueChargeBdt'); updateTotals()" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none" placeholder="0">
                        </div>
                        <div id="fieldReIssueChargeBdt" class="hidden">
                            <label class="block text-sm font-medium text-slate-700 mb-1">Re-Issue Charge (BDT)</label>
                            <input type="number" id="inputReIssueChargeBdt" oninput="handleFieldBdtInput('inputReIssueCharge','inputReIssueChargeBdt'); updateTotals()" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none" placeholder="0">
                            <input type="number" id="inputReIssueChargeBdtSar" readonly class="w-full mt-1 px-3 py-2 border border-slate-200 rounded-lg bg-slate-50 text-slate-500 text-sm" placeholder="0">
                        </div>
                    </div>
                    <div>
                        <div id="fieldFareDifferenceSar">
                            <label class="block text-sm font-medium text-slate-700 mb-1">Fare Difference (SAR)</label>
                            <input type="number" id="inputFareDifference" oninput="handleFieldSarInput('inputFareDifference','inputFareDifferenceBdt'); updateTotals()" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none" placeholder="0">
                        </div>
                        <div id="fieldFareDifferenceBdt" class="hidden">
                            <label class="block text-sm font-medium text-slate-700 mb-1">Fare Difference (BDT)</label>
                            <input type="number" id="inputFareDifferenceBdt" oninput="handleFieldBdtInput('inputFareDifference','inputFareDifferenceBdt'); updateTotals()" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none" placeholder="0">
                            <input type="number" id="inputFareDifferenceBdtSar" readonly class="w-full mt-1 px-3 py-2 border border-slate-200 rounded-lg bg-slate-50 text-slate-500 text-sm" placeholder="0">
                        </div>
                    </div>
                    <div>
                        <div id="fieldOtherCostsSar">
                            <label class="block text-sm font-medium text-slate-700 mb-1">Other Costs (SAR)</label>
                            <input type="number" id="inputOtherCosts" oninput="handleFieldSarInput('inputOtherCosts','inputOtherCostsBdt'); updateTotals()" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none" placeholder="0">
                        </div>
                        <div id="fieldOtherCostsBdt" class="hidden">
                            <label class="block text-sm font-medium text-slate-700 mb-1">Other Costs (BDT)</label>
                            <input type="number" id="inputOtherCostsBdt" oninput="handleFieldBdtInput('inputOtherCosts','inputOtherCostsBdt'); updateTotals()" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none" placeholder="0">
                            <input type="number" id="inputOtherCostsBdtSar" readonly class="w-full mt-1 px-3 py-2 border border-slate-200 rounded-lg bg-slate-50 text-slate-500 text-sm" placeholder="0">
                        </div>
                    </div>
                    <div>
                        <div id="fieldTotalCostSar">
                            <label class="block text-sm font-medium text-slate-700 mb-1">Total Cost (SAR)</label>
                            <input type="number" id="inputTotalCost" readonly class="w-full px-3 py-2 border border-slate-200 rounded-lg bg-slate-50 text-slate-500" placeholder="0">
                        </div>
                        <div id="fieldTotalCostBdt" class="hidden">
                            <label class="block text-sm font-medium text-slate-700 mb-1">Total Cost (BDT)</label>
                            <input type="number" id="inputTotalCostBdt" readonly class="w-full px-3 py-2 border border-slate-200 rounded-lg bg-slate-50 text-slate-500" placeholder="0">
                            <input type="number" id="inputTotalCostBdtSar" readonly class="w-full mt-1 px-3 py-2 border border-slate-200 rounded-lg bg-slate-50 text-slate-500 text-sm" placeholder="0">
                        </div>
                    </div>
                    <div id="fieldServiceCharge">
                        <div id="fieldServiceChargeSar">
                            <label class="block text-sm font-medium text-slate-700 mb-1">Service Charge (SAR)</label>
                            <input type="number" id="inputServiceCharge" oninput="handleFieldSarInput('inputServiceCharge','inputServiceChargeBdt'); updateTotals()" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none" placeholder="0">
                        </div>
                        <div id="fieldServiceChargeBdt" class="hidden">
                            <label class="block text-sm font-medium text-slate-700 mb-1">Service Charge (BDT)</label>
                            <input type="number" id="inputServiceChargeBdt" oninput="handleFieldBdtInput('inputServiceCharge','inputServiceChargeBdt'); updateTotals()" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none" placeholder="0">
                            <input type="number" id="inputServiceChargeBdtSar" readonly class="w-full mt-1 px-3 py-2 border border-slate-200 rounded-lg bg-slate-50 text-slate-500 text-sm" placeholder="0">
                        </div>
                    </div>
                    <div id="fieldTotalPayment">
                        <div id="fieldTotalPaymentSar">
                            <label class="block text-sm font-medium text-slate-700 mb-1">Total Customer Payment (SAR)</label>
                            <input type="number" id="inputTotalPayment" readonly class="w-full px-3 py-2 border border-slate-200 rounded-lg bg-slate-50 text-slate-500" placeholder="0">
                        </div>
                        <div id="fieldTotalPaymentBdt" class="hidden">
                            <label class="block text-sm font-medium text-slate-700 mb-1">Total Customer Payment (BDT)</label>
                            <input type="number" id="inputTotalPaymentBdt" readonly class="w-full px-3 py-2 border border-slate-200 rounded-lg bg-slate-50 text-slate-500" placeholder="0">
                            <input type="number" id="inputTotalPaymentBdtSar" readonly class="w-full mt-1 px-3 py-2 border border-slate-200 rounded-lg bg-slate-50 text-slate-500 text-sm" placeholder="0">
                        </div>
                    </div>
                    {{--
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Payment Method</label>
                        <select id="inputPaymentMethod" onchange="handlePaymentMethodChange()" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none bg-white">
                            <option value="">Select Payment Method</option>
                        </select>
                    </div>
                    --}}
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-slate-700 mb-1">Remarks</label>
                        <textarea id="inputRemarks" rows="3" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none resize-none" placeholder="Enter remarks..."></textarea>
                    </div>
                </div>
            </div>

            {{--<div id="bankMethodSection" class="hidden mb-4">
                <label class="block text-sm font-medium text-slate-700 mb-1">Bank Method</label>
                <select id="inputBankMethod" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none bg-white">
                    <option value="">Select Bank Method</option>
                    <option value="Al Rajhi Bank">Al Rajhi Bank</option>
                    <option value="National Commercial Bank">National Commercial Bank</option>
                    <option value="Riyadh Bank">Riyadh Bank</option>
                    <option value="Alinma Bank">Alinma Bank</option>
                </select>
            </div>

            <div id="branchSection" class="hidden mb-4">
                <label class="block text-sm font-medium text-slate-700 mb-1">Select Branch</label>
                <select id="inputBranch" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none bg-white">
                    <option value="">Select Branch</option>
                    <option value="Riyadh Branch">Riyadh Branch</option>
                    <option value="Jeddah Branch">Jeddah Branch</option>
                    <option value="Madinah Branch">Madinah Branch</option>
                </select>
            </div>--}}

            <div class="flex gap-3">
                <button onclick="confirmProcess()" id="btnConfirm" class="flex-1 px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-medium">Confirm</button>
                <button onclick="closeProcessConfirmationModal()" class="flex-1 px-6 py-2 border border-slate-300 text-slate-700 rounded-lg hover:bg-slate-50 transition font-medium">Cancel</button>
            </div>
        </div>
    </div>
</div>

<style>
.modal-overlay { transition: opacity 0.2s ease; }
.modal-content { transition: transform 0.2s ease, opacity 0.2s ease; }
.toast { transition: transform 0.3s ease, opacity 0.3s ease; }
</style>

@push('scripts')
<script>
const bookingId = {{ $id }};
let allRequests = [];
let currentTicketRequestId = null;
let currentRefundPayable = 0;
let allTicketFares = [];
let selectedTicketFareId = null;
let sourceFares = { selling_fare: 0, net_fare: 0, offer_price: 0 };

function getCurrencyMode() {
    return (typeof Alpine !== 'undefined' && Alpine.store('currency')) ? Alpine.store('currency').mode : 'SAR';
}
function sarToBdt(sar) {
    var r = window.__currencyRate || 0;
    return r > 0 ? Math.round(sar * r) : '';
}
function bdtToSar(bdt) {
    var r = window.__currencyRate || 0;
    return r > 0 ? (Math.round(bdt / r * 1e6) / 1e6) : '';
}
function handleFieldSarInput(sarId, bdtId) {
    var sar = parseFloat(document.getElementById(sarId).value) || 0;
    var bdtEl = document.getElementById(bdtId);
    if (bdtEl) bdtEl.value = sarToBdt(sar);
    var mirror = document.getElementById(bdtId + 'Sar');
    if (mirror) mirror.value = sar || '';
}
function handleFieldBdtInput(sarId, bdtId) {
    var bdt = parseFloat(document.getElementById(bdtId).value) || 0;
    var sarEl = document.getElementById(sarId);
    if (sarEl && sarEl.hasAttribute('readonly')) return;
    var sarVal = bdt > 0 ? bdtToSar(bdt) : '';
    if (sarEl) sarEl.value = sarVal;
    var mirror = document.getElementById(bdtId + 'Sar');
    if (mirror) mirror.value = sarVal;
}
function syncCurrencyFields() {
    var mode = getCurrencyMode();
    var isBdt = mode === 'BDT';
    var wrappers = [
        ['fieldSellingFareSar', 'fieldSellingFareBdt'],
        ['fieldNetFareSar', 'fieldNetFareBdt'],
        ['fieldOfferPriceSar', 'fieldOfferPriceBdt'],
        ['fieldReIssueChargeSar', 'fieldReIssueChargeBdt'],
        ['fieldFareDifferenceSar', 'fieldFareDifferenceBdt'],
        ['fieldOtherCostsSar', 'fieldOtherCostsBdt'],
        ['fieldTotalCostSar', 'fieldTotalCostBdt'],
        ['fieldServiceChargeSar', 'fieldServiceChargeBdt'],
        ['fieldTotalPaymentSar', 'fieldTotalPaymentBdt'],
        ['fieldRefundAdjustmentSar', 'fieldRefundAdjustmentBdt'],
    ];
    wrappers.forEach(function(w) {
        var sarEl = document.getElementById(w[0]);
        var bdtEl = document.getElementById(w[1]);
        if (sarEl) sarEl.classList.toggle('hidden', isBdt);
        if (bdtEl) bdtEl.classList.toggle('hidden', !isBdt);
    });
    updateTotals();
    syncReadonlyMirrors();
}
function syncReadonlyMirrors() {
    var rate = window.__currencyRate || 0;
    var pairs = [
        ['inputSellingFare', 'inputSellingFareBdtSar'],
        ['inputNetFare', 'inputNetFareBdtSar'],
        ['inputOfferPrice', 'inputOfferPriceBdtSar'],
        ['inputReIssueCharge', 'inputReIssueChargeBdtSar'],
        ['inputFareDifference', 'inputFareDifferenceBdtSar'],
        ['inputOtherCosts', 'inputOtherCostsBdtSar'],
        ['inputServiceCharge', 'inputServiceChargeBdtSar'],
        ['inputTotalCost', 'inputTotalCostBdtSar'],
        ['inputTotalPayment', 'inputTotalPaymentBdtSar'],
        ['inputRefundAdjustment', 'inputRefundAdjustmentBdtSar'],
    ];
    pairs.forEach(function(p) {
        var sarEl = document.getElementById(p[0]);
        var mirrorEl = document.getElementById(p[1]);
        if (sarEl && mirrorEl) mirrorEl.value = sarEl.value;
    });
}

function getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]').getAttribute('content');
}

function loadConfirmation() {
    fetch('/bookings/' + bookingId + '/ticket-requests?type=re_issue', {
        headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': getCsrfToken() }
    })
    .then(res => res.json())
    .then(requests => {
        allRequests = requests;
        if (!requests.length) {
            showNotFound();
            return;
        }
        const first = requests[0];
        const booking = first.booking || {};
        const customer = booking.customer || {};
        const branch = booking.booking_branch || booking.bookingBranch || {};

        document.getElementById('invoiceId').textContent = booking.id || '-';
        document.getElementById('invoiceNo').textContent = booking.invoice_id || '-';
        document.getElementById('customerName').textContent = customer.name || '-';
        document.getElementById('customerMobile').textContent = customer.mobile_no || '-';
        document.getElementById('branch').textContent = branch.name || '-';
        document.getElementById('passengerCount').textContent = [...new Set(requests.map(r => r.passenger_id))].length;

        renderConfirmation(requests);
        loadReasons();
        loadAgents();
        // loadPaymentMethods();
    });
}

function loadReasons() {
    fetch('/ticket-requests/reasons?type=re_issue', {
        headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': getCsrfToken() }
    })
    .then(res => res.json())
    .then(reasons => {
        const select = document.getElementById('inputReason');
        select.innerHTML = '<option value="">Select Reason</option>' +
            reasons.map(r => '<option value="' + r.id + '" data-default-payment-by="' + (r.default_payment_by || '') + '">' + escapeHtml(r.name) + '</option>').join('');
    });
}

function handleReasonChange() {
    var opt = document.getElementById('inputReason').selectedOptions[0];
    var val = opt ? opt.getAttribute('data-default-payment-by') || '' : '';
    document.getElementById('inputPaymentBy').value = val;
    handlePaymentByChange();
}

function loadAgents() {
    fetch('/ticket-requests/agents', {
        headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': getCsrfToken() }
    })
    .then(res => res.json())
    .then(agents => {
        const select = document.getElementById('inputAgent');
        select.innerHTML = '<option value="">Select Agent</option>' +
            agents.map(a => '<option value="' + a.id + '">' + escapeHtml(a.name) + '</option>').join('');
    });
}

/*
function loadPaymentMethods() {
    fetch('/ticket-requests/payment-methods', {
        headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': getCsrfToken() }
    })
    .then(res => res.json())
    .then(methods => {
        const select = document.getElementById('inputPaymentMethod');
        select.innerHTML = '<option value="">Select Payment Method</option>' +
            methods.map(m => '<option value="' + m.value + '">' + escapeHtml(m.label) + '</option>').join('');
    });
}
*/

function loadTicketFares(filters = {}) {
    const params = new URLSearchParams();
    if (filters.route_type) params.append('route_type', filters.route_type);
    if (filters.ticket_type) params.append('ticket_type', filters.ticket_type);
    if (filters.flight_type) params.append('flight_type', filters.flight_type);

    fetch('/ticket-fares/options?' + params.toString(), {
        headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': getCsrfToken() }
    })
    .then(res => res.json())
    .then(fares => {
        allTicketFares = fares;
        const select = document.getElementById('inputTicketFare');
        const currentVal = select.value || selectedTicketFareId;
        select.innerHTML = '<option value="">Select Ticket</option>' +
            fares.map(f => {
                const route = f.route || {};
                const airline = f.airline || {};
                const cls = f.airline_class?.class || {};
                const routeLabel = formatRoute(route);
                return '<option value="' + f.id + '">' +
                    escapeHtml(f.ticket_type || '') + ' - ' +
                    escapeHtml(routeLabel) + ' - ' +
                    escapeHtml(airline.name || '') + ' - ' +
                    escapeHtml(cls.name || '') +
                    '</option>';
            }).join('');
        if (currentVal) select.value = currentVal;
    });
}

function handleFilterChange() {
    const filters = {
        route_type: document.getElementById('inputRouteType').value,
        ticket_type: document.getElementById('inputTicketType').value,
        flight_type: document.getElementById('inputFlightType').value,
    };
    loadTicketFares(filters);
    applyRouteType();
    applyFareType();
}

function handleTicketSelect() {
    const fareId = document.getElementById('inputTicketFare').value;
    selectedTicketFareId = fareId || null;
    syncFareFields();
}

function syncFareFields() {
    var fareId = document.getElementById('inputTicketFare').value;
    var f = allTicketFares.find(function(x) { return String(x.id) === String(fareId); });
    var sf = f ? (f.selling_fare ?? sourceFares.selling_fare) : sourceFares.selling_fare;
    var nf = f ? (f.net_fare ?? sourceFares.net_fare) : sourceFares.net_fare;
    var ofp = f ? (f.offer_price ?? sourceFares.offer_price) : sourceFares.offer_price;
    document.getElementById('inputSellingFare').value = sf;
    document.getElementById('inputNetFare').value = nf;
    document.getElementById('inputOfferPrice').value = ofp;
    var rate = window.__currencyRate || 0;
    if (rate > 0) {
        document.getElementById('inputSellingFareBdt').value = sarToBdt(parseFloat(sf) || 0);
        document.getElementById('inputNetFareBdt').value = sarToBdt(parseFloat(nf) || 0);
        document.getElementById('inputOfferPriceBdt').value = ofp ? sarToBdt(parseFloat(ofp) || 0) : '';
    } else {
        document.getElementById('inputSellingFareBdt').value = '';
        document.getElementById('inputNetFareBdt').value = '';
        document.getElementById('inputOfferPriceBdt').value = '';
    }
    syncReadonlyMirrors();
}

function applyFareType() {
    const tt = document.getElementById('inputTicketType').value;
    document.getElementById('fieldOfferPrice').classList.toggle('hidden', tt !== 'offer');
}

function renderConfirmation(requests) {
    const passengerListEl = document.getElementById('passengerList');
    const grouped = {};
    requests.forEach(r => {
        if (!grouped[r.passenger_id]) {
            grouped[r.passenger_id] = { passenger: r.passenger, tickets: [] };
        }
        grouped[r.passenger_id].tickets.push(r);
    });

    const statusCounts = { pending: 0, processed: 0, rejected: 0 };
    requests.forEach(r => statusCounts[r.status] = (statusCounts[r.status] || 0) + 1);
    const statusBadge = document.getElementById('statusBadge');
    if (statusCounts.pending === 0 && statusCounts.processed > 0) {
        statusBadge.textContent = 'Processed';
        statusBadge.className = 'inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-700';
    } else if (statusCounts.pending === 0 && statusCounts.rejected > 0) {
        statusBadge.textContent = 'Rejected';
        statusBadge.className = 'inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-700';
    } else {
        statusBadge.textContent = 'Pending';
        statusBadge.className = 'inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-700';
    }

    passengerListEl.innerHTML = Object.values(grouped).map(g => {
        const p = g.passenger || {};
        return `
            <div class="bg-slate-50 rounded-lg p-4">
                <div class="flex items-center justify-between mb-3">
                    <div>
                        <span class="font-medium text-slate-800">${escapeHtml(p.first_name ? p.first_name + ' ' + p.last_name : '-')}</span>
                        <span class="text-slate-500 text-sm ml-2">(${escapeHtml(p.passport_no || '-')})</span>
                    </div>
                    <span class="text-sm text-slate-500">${g.tickets.length} ticket(s)</span>
                </div>
                <div class="space-y-3">
                    ${g.tickets.map(r => {
                        const t = r.issued_ticket || {};
                        const route = t.ticket_fare?.route || {};
                        const isProcessed = r.status === 'processed';
                        const isRejected = r.status === 'rejected';
                        const badgeClass = isProcessed ? 'bg-green-100 text-green-700' : isRejected ? 'bg-red-100 text-red-700' : 'bg-yellow-100 text-yellow-700';
                        const statusLabel = isProcessed ? 'Processed' : isRejected ? 'Rejected' : 'Pending';
                        return `
                        <div class="bg-white rounded-lg border border-slate-200 p-4">
                            <div class="flex items-center justify-between mb-3">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium ${badgeClass}">${statusLabel}</span>
                            </div>
                            <div class="grid grid-cols-2 md:grid-cols-3 gap-3 mb-3">
                                <div class="text-sm"><span class="text-slate-500">Ticket No: </span><span class="text-slate-800 font-medium">${escapeHtml(t.ticket_number) || '-'}</span></div>
                                <div class="text-sm"><span class="text-slate-500">PNR: </span><span class="text-slate-800 font-medium">${escapeHtml(t.pnr) || '-'}</span></div>
                                <div class="text-sm"><span class="text-slate-500">Probable (Inbound): </span><span class="text-slate-800 font-medium">${formatDate(r.probable_date_up)}</span></div>
                                <div class="text-sm"><span class="text-slate-500">Probable (Outbound): </span><span class="text-slate-800 font-medium">${formatDate(r.probable_date_down)}</span></div>
                                <div class="text-sm"><span class="text-slate-500">Visa Expiry: </span><span class="text-slate-800 font-medium">${formatDate(r.visa_expiry_date)}</span></div>
                                <div class="text-sm"><span class="text-slate-500">Requested: </span><span class="text-slate-800 font-medium">${formatDate(r.requested_at)}</span></div>
                            </div>
                            <div class="flex gap-3">
                                <button onclick="rejectReIssue(${r.id})" class="px-4 py-2 bg-red-600 text-white text-sm rounded-lg hover:bg-red-700 transition font-medium" ${isProcessed || isRejected ? 'disabled style="opacity:50;cursor:not-allowed"' : ''}>Reject</button>
                                <button onclick="processConfirmation(${r.id})" class="px-4 py-2 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700 transition font-medium" ${isProcessed || isRejected ? 'disabled style="opacity:50;cursor:not-allowed"' : ''}>Process Confirmation</button>
                            </div>
                        </div>
                        `;
                    }).join('')}
                </div>
            </div>
        `;
    }).join('');
}

function processConfirmation(ticketRequestId) {
    currentTicketRequestId = ticketRequestId;
    const r = allRequests.find(req => req.id === ticketRequestId);
    if (!r) return;

    const p = r.passenger || {};
    const t = r.issued_ticket || {};

    document.getElementById('modalPassengerName').textContent = (p.first_name || '') + ' ' + (p.last_name || '') + ' (' + (p.passport_no || '-') + ')';
    document.getElementById('infoPassport').textContent = p.passport_no || '-';
    document.getElementById('infoMobile').textContent = p.mobile_no || '-';
    document.getElementById('infoPnr').textContent = t.pnr || '-';
    document.getElementById('infoFlightDate').textContent = formatDate(t.outbound_date || t.inbound_date) || '-';
    document.getElementById('infoRoute').textContent = formatRoute(t.ticket_fare?.route) || '-';
    document.getElementById('infoAirline').textContent = t.ticket_fare?.airline?.name || '-';
    document.getElementById('infoClass').textContent = t.ticket_fare?.airline_class?.class?.name || '-';
    document.getElementById('infoType').textContent = ({ adult: 'Adult', child: 'Child', infant: 'Infant' })[p.passenger_type] || '-';

    currentRefundPayable = parseFloat(p.refund_payable) || 0;
    document.getElementById('infoRefundPayable').textContent = currentRefundPayable.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 6 });

    document.getElementById('inputUpDate').value = formatToDDMMMYY(r.probable_date_up) || '';
    document.getElementById('inputDownDate').value = formatToDDMMMYY(r.probable_date_down) || '';
    document.getElementById('inputReason').value = '';
    const todayDD = (() => { const d = new Date(); const ms = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec']; return d.getDate() + '-' + ms[d.getMonth()] + '-' + String(d.getFullYear()).slice(-2); })();
    document.getElementById('inputTravelDate').value = todayDD;
    document.getElementById('inputReIssueCharge').value = '';
    document.getElementById('inputFareDifference').value = '';
    document.getElementById('inputOtherCosts').value = '';
    document.getElementById('inputServiceCharge').value = '';
    // document.getElementById('inputPaymentMethod').value = '';
    document.getElementById('inputAgent').value = t.ticket_agent_id || '';
    // document.getElementById('bankMethodSection').classList.add('hidden');
    // document.getElementById('branchSection').classList.add('hidden');
    // document.getElementById('confirmButtons').classList.remove('hidden');
    // document.getElementById('holdButtons').classList.add('hidden');
    document.getElementById('inputRemarks').value = '';
    document.getElementById('inputPaymentBy').value = '';
    document.getElementById('inputPaymentOption').value = 'customer_payment';
    document.getElementById('inputRefundAdjustment').value = '';
    document.getElementById('inputRefundAdjustmentBdt').value = '';
    document.getElementById('inputRefundAdjustmentBdtSar').value = '';
    handlePaymentByChange();
    handlePaymentOptionChange();
    updateTotals();

    sourceFares = {
        selling_fare: t.selling_fare ?? 0,
        net_fare: t.net_fare ?? 0,
        offer_price: t.offer_price ?? 0,
    };
    document.getElementById('inputSellingFare').value = sourceFares.selling_fare;
    document.getElementById('inputNetFare').value = sourceFares.net_fare;
    document.getElementById('inputOfferPrice').value = sourceFares.offer_price;
    var rate = window.__currencyRate || 0;
    document.getElementById('inputSellingFareBdt').value = rate > 0 ? sarToBdt(sourceFares.selling_fare) : '';
    document.getElementById('inputNetFareBdt').value = rate > 0 ? sarToBdt(sourceFares.net_fare) : '';
    document.getElementById('inputOfferPriceBdt').value = rate > 0 ? sarToBdt(sourceFares.offer_price) : '';

    const originalRt = t.ticket_fare?.route?.route_type || '';
    const originalTt = t.ticket_fare?.ticket_type || '';
    const originalFt = t.ticket_fare?.route?.flight_type || '';

    const rtSelect = document.getElementById('inputRouteType');
    rtSelect.value = originalRt;
    rtSelect.disabled = originalRt === 'oneway_outbound';

    const ttSelect = document.getElementById('inputTicketType');
    ttSelect.value = originalTt;

    const ftSelect = document.getElementById('inputFlightType');
    ftSelect.value = originalFt;

    selectedTicketFareId = t.ticket_fare_id || null;

    loadTicketFares({
        route_type: originalRt,
        ticket_type: originalTt,
        flight_type: originalFt,
    });

    applyRouteType();
    applyFareType();
    syncCurrencyFields();

    document.getElementById('processConfirmationModal').classList.remove('hidden');
}

function closeProcessConfirmationModal() {
    document.getElementById('processConfirmationModal').classList.add('hidden');
    currentTicketRequestId = null;
}

function applyRouteType() {
    const rt = document.getElementById('inputRouteType').value;
    document.getElementById('fieldUpDate').classList.toggle('hidden', rt === 'oneway_outbound');
    document.getElementById('fieldDownDate').classList.toggle('hidden', rt === 'oneway_inbound');
}

function updateTotals() {
    var reIssue = parseFloat(document.getElementById('inputReIssueCharge').value) || 0;
    var difference = parseFloat(document.getElementById('inputFareDifference').value) || 0;
    var other = parseFloat(document.getElementById('inputOtherCosts').value) || 0;
    var service = parseFloat(document.getElementById('inputServiceCharge').value) || 0;

    var totalCost = reIssue + difference + other;
    document.getElementById('inputTotalCost').value = totalCost;
    document.getElementById('inputTotalPayment').value = totalCost + service;

    var rate = window.__currencyRate || 0;
    if (rate > 0) {
        document.getElementById('inputTotalCostBdt').value = sarToBdt(totalCost);
        document.getElementById('inputTotalPaymentBdt').value = sarToBdt(totalCost + service);
    } else {
        document.getElementById('inputTotalCostBdt').value = '';
        document.getElementById('inputTotalPaymentBdt').value = '';
    }

    var refundAdj = parseFloat(document.getElementById('inputRefundAdjustment').value) || 0;
    var totalPayment = totalCost + service;
    var refundAdjEl = document.getElementById('inputRefundAdjustment');
    if (refundAdj > 0) {
        if (refundAdj > totalPayment) {
            refundAdjEl.setCustomValidity('Refund adjustment amount exceeds the total customer payment.');
        } else if (refundAdj > currentRefundPayable) {
            refundAdjEl.setCustomValidity('Refund adjustment amount exceeds the available refund payable.');
        } else {
            refundAdjEl.setCustomValidity('');
        }
    } else {
        refundAdjEl.setCustomValidity('');
    }

    syncReadonlyMirrors();
}

function handlePaymentByChange() {
    var isCustomer = document.getElementById('inputPaymentBy').value === 'customer';
    document.getElementById('fieldServiceCharge').classList.toggle('hidden', !isCustomer);
    document.getElementById('fieldTotalPayment').classList.toggle('hidden', !isCustomer);
    document.getElementById('fieldPaymentOption').classList.toggle('hidden', !isCustomer);
    document.getElementById('fieldRefundAdjustment').classList.toggle('hidden', !isCustomer);

    if (!isCustomer) {
        document.getElementById('inputServiceCharge').value = '';
        document.getElementById('inputServiceChargeBdt').value = '';
        document.getElementById('inputPaymentOption').value = 'customer_payment';
        document.getElementById('inputRefundAdjustment').value = '';
        document.getElementById('inputRefundAdjustmentBdt').value = '';
        document.getElementById('inputRefundAdjustmentBdtSar').value = '';
        updateTotals();
    }
}

function handlePaymentOptionChange() {
    var isAdjustment = document.getElementById('inputPaymentOption').value === 'refund_adjustment';
    var isCustomer = document.getElementById('inputPaymentBy').value === 'customer';
    document.getElementById('fieldRefundAdjustment').classList.toggle('hidden', !(isCustomer && isAdjustment));

    if (!isAdjustment) {
        document.getElementById('inputRefundAdjustment').value = '';
        document.getElementById('inputRefundAdjustmentBdt').value = '';
        document.getElementById('inputRefundAdjustmentBdtSar').value = '';
        updateTotals();
    }
}

/*
function handlePaymentMethodChange() {
    const paymentMethod = document.getElementById('inputPaymentMethod').value;
    const bankMethodSection = document.getElementById('bankMethodSection');
    const branchSection = document.getElementById('branchSection');
    const confirmButtons = document.getElementById('confirmButtons');
    const holdButtons = document.getElementById('holdButtons');

    bankMethodSection.classList.add('hidden');
    branchSection.classList.add('hidden');
    confirmButtons.classList.remove('hidden');
    holdButtons.classList.add('hidden');

    if (paymentMethod === 'Bank Transfer') {
        bankMethodSection.classList.remove('hidden');
    } else if (paymentMethod === 'Pay to Branch') {
        branchSection.classList.remove('hidden');
        confirmButtons.classList.add('hidden');
        holdButtons.classList.remove('hidden');
    }
}

function holdProcess() {
    showToast('Process held successfully!', 'info');
    closeProcessConfirmationModal();
}
*/

function confirmProcess() {
    if (!currentTicketRequestId) return;

    const payload = {
        reason_id: document.getElementById('inputReason').value,
        ticket_fare_id: document.getElementById('inputTicketFare').value || selectedTicketFareId,
        re_issue_charge: parseFloat(document.getElementById('inputReIssueCharge').value) || 0,
        fare_difference: parseFloat(document.getElementById('inputFareDifference').value) || 0,
        other_costs: parseFloat(document.getElementById('inputOtherCosts').value) || 0,
        service_charge: parseFloat(document.getElementById('inputServiceCharge').value) || 0,
        total_customer_payment: parseFloat(document.getElementById('inputTotalPayment').value) || 0,
        travel_date: parseDDMMMYY(document.getElementById('inputTravelDate').value) || null,
        inbound_date: parseDDMMMYY(document.getElementById('inputUpDate').value) || null,
        outbound_date: parseDDMMMYY(document.getElementById('inputDownDate').value) || null,
        ticket_agent_id: document.getElementById('inputAgent').value || null,
        remarks: document.getElementById('inputRemarks').value || null,
        payment_by: document.getElementById('inputPaymentBy').value || null,
        payment_option: document.getElementById('inputPaymentBy').value === 'customer' ? document.getElementById('inputPaymentOption').value : undefined,
        refund_adjustment_amount: document.getElementById('inputPaymentBy').value === 'customer' && document.getElementById('inputPaymentOption').value === 'refund_adjustment' ? (parseFloat(document.getElementById('inputRefundAdjustment').value) || 0) : 0,
        selling_fare: parseFloat(document.getElementById('inputSellingFare').value) || null,
        net_fare: parseFloat(document.getElementById('inputNetFare').value) || null,
        offer_price: parseFloat(document.getElementById('inputOfferPrice').value) || null,
    };

    if (!payload.reason_id) {
        showToast('Please select a reason', 'error');
        return;
    }

    if (!payload.ticket_fare_id) {
        showToast('Please select a ticket', 'error');
        return;
    }

    if (payload.payment_by === 'customer' && payload.payment_option === 'refund_adjustment') {
        if (payload.refund_adjustment_amount > payload.re_issue_charge + payload.fare_difference + payload.other_costs + payload.service_charge) {
            showToast('Refund adjustment amount exceeds the total customer payment.', 'error');
            return;
        }
        if (payload.refund_adjustment_amount > currentRefundPayable) {
            showToast('Refund adjustment amount exceeds the available refund payable.', 'error');
            return;
        }
    }

    fetch('/ticket-requests/' + currentTicketRequestId + '/process-reissue', {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': getCsrfToken(),
            'Accept': 'application/json',
        },
        body: JSON.stringify(payload),
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showToast('Re-issue processed successfully!', 'success');
            closeProcessConfirmationModal();
            loadConfirmation();
        } else {
            showToast(data.message || 'Failed to process', 'error');
        }
    })
    .catch(err => {
        showToast('Error processing request', 'error');
    });
}

function rejectReIssue(ticketRequestId) {
    if (!confirm('Are you sure you want to reject this ticket\'s re-issue request?')) return;

    fetch('/ticket-requests/' + ticketRequestId + '/reject', {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': getCsrfToken(),
            'Accept': 'application/json',
        },
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showToast('Re-issue request rejected', 'info');
            loadConfirmation();
        } else {
            showToast(data.message || 'Failed to reject', 'error');
        }
    });
}

function showNotFound() {
    document.getElementById('confirmationContent').classList.add('hidden');
    document.getElementById('notFound').classList.remove('hidden');
}

function formatToDDMMMYY(dateStr) {
    if (!dateStr) return '';
    const parts = dateStr.split('T')[0].split('-');
    if (parts.length !== 3) return dateStr;
    const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    const y = parseInt(parts[0]), m = parseInt(parts[1]), d = parseInt(parts[2]);
    if (isNaN(d) || isNaN(m) || m < 1 || m > 12) return dateStr;
    return d + '-' + months[m - 1] + '-' + String(y).slice(-2);
}

function parseDDMMMYY(input) {
    if (!input) return '';
    if (/^\d{4}-\d{2}-\d{2}$/.test(input)) return input;
    const parts = input.split('-');
    if (parts.length !== 3) return null;
    const months = ['jan','feb','mar','apr','may','jun','jul','aug','sep','oct','nov','dec'];
    const d = parseInt(parts[0]), mmm = parts[1].toLowerCase().slice(0, 3), yy = parts[2];
    const mi = months.indexOf(mmm);
    if (isNaN(d) || mi === -1 || !/^\d{2}$/.test(yy)) return null;
    const year = 2000 + parseInt(yy), month = mi + 1;
    if (d < 1 || d > new Date(year, month, 0).getDate()) return null;
    return year + '-' + String(month).padStart(2, '0') + '-' + String(d).padStart(2, '0');
}

function formatDate(val) {
    if (!val) return '-';
    const parts = val.split('T')[0].split('-');
    if (parts.length === 3) {
        const d = new Date(parseInt(parts[0]), parseInt(parts[1]) - 1, parseInt(parts[2]));
        if (!isNaN(d.getTime())) return d.toLocaleDateString();
    }
    const d = new Date(val);
    if (!isNaN(d.getTime())) return d.toLocaleDateString();
    return val;
}

function formatRoute(route) {
    if (!route) return '-';
    const rt = route.route_type || '';
    if (rt === 'multi_city' && route.multi_segments?.length) {
        return route.multi_segments.map(s => (s.from_city?.code || '?') + '-' + (s.to_city?.code || '?')).join(', ');
    }
    const from = route.from_city?.code || '?';
    const to = route.to_city?.code || '?';
    const ret = route.return_city?.code || '';
    if (rt === 'round' && ret) return from + '-' + to + '-' + ret;
    return from + '-' + to;
}

function escapeHtml(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

function showToast(message, type = 'info') {
    const container = document.getElementById('toastContainer');
    if (!container) return;
    const toast = document.createElement('div');
    toast.className = 'toast px-4 py-3 rounded-lg shadow-lg text-white ' + (
        type === 'success' ? 'bg-green-600' :
        type === 'error' ? 'bg-red-600' :
        'bg-slate-700'
    );
    toast.textContent = message;
    container.appendChild(toast);
    setTimeout(() => { toast.style.opacity = '0'; setTimeout(() => toast.remove(), 300); }, 3000);
}

window.addEventListener('currency-toggled', function() { syncCurrencyFields(); });
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function() { syncCurrencyFields(); });
} else {
    syncCurrencyFields();
}

loadConfirmation();
</script>
@endpush
@endsection
