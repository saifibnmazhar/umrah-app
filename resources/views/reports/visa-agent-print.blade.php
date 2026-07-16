<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visa Agent Report - {{ $visaAgent->name }}</title>
    <script>window.__currencyRate = {{ $currencyRate }};</script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', 'Segoe UI', Arial, sans-serif; background: #f8fafc; color: #1e293b; }
        .print-container { max-width: 1200px; margin: 0 auto; padding: 30px 40px; }
        .report-header { text-align: center; border-bottom: 3px double #1e293b; padding-bottom: 20px; margin-bottom: 24px; }
        .report-header h1 { font-size: 26px; font-weight: 900; letter-spacing: 1px; text-transform: uppercase; color: #0f172a; }
        .report-header .subtitle { font-size: 14px; color: #475569; margin-top: 4px; }
        .report-header .meta { font-size: 12px; color: #64748b; margin-top: 8px; display: flex; justify-content: center; gap: 32px; }
        .agent-title { font-size: 20px; font-weight: 700; margin-bottom: 16px; color: #1e293b; padding-bottom: 8px; border-bottom: 1px solid #e2e8f0; }
        .table-wrap { border: 2px solid #cbd5e1; border-radius: 6px; overflow: hidden; margin-bottom: 24px; }
        table { width: 100%; border-collapse: collapse; font-size: 12px; }
        th { background: #f1f5f9; color: #334155; font-weight: 700; text-align: left; padding: 10px 8px; border-bottom: 2px solid #cbd5e1; border-right: 1px solid #e2e8f0; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; white-space: nowrap; }
        th:last-child { border-right: none; }
        td { padding: 8px; border-bottom: 1px solid #e2e8f0; border-right: 1px solid #f1f5f9; vertical-align: middle; }
        td:last-child { border-right: none; }
        tr:last-child td { border-bottom: none; }
        tr:nth-child(even) { background: #f8fafc; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .font-medium { font-weight: 600; }
        .status-badge { display: inline-block; padding: 2px 8px; border-radius: 3px; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.3px; }
        .status-submitted { background: #dbeafe; color: #1e40af; }
        .status-issued { background: #dcfce7; color: #166534; }
        .status-cancelled { background: #fee2e2; color: #991b1b; }
        .status-payment { background: #f1f5f9; color: #475569; }
        .text-green { color: #16a34a; }
        .text-red { color: #dc2626; }
        .text-gray { color: #6b7280; }
        .summary-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-bottom: 24px; }
        .summary-card { border: 1px solid #e2e8f0; border-radius: 6px; padding: 12px 16px; background: #f8fafc; }
        .summary-card .label { font-size: 11px; color: #64748b; text-transform: uppercase; letter-spacing: 0.3px; font-weight: 600; }
        .summary-card .value { font-size: 18px; font-weight: 800; margin-top: 2px; }
        .report-footer { border-top: 2px solid #e2e8f0; padding-top: 20px; margin-top: 8px; display: flex; justify-content: space-between; font-size: 12px; color: #64748b; }
        .signature-area { display: flex; justify-content: space-between; margin-top: 32px; padding-top: 24px; }
        .signature-line { width: 220px; }
        .signature-line .line { border-top: 1px solid #1e293b; margin-bottom: 4px; padding-top: 4px; font-size: 12px; text-align: center; color: #475569; }
        .currency-toggle-wrap { display: flex; justify-content: flex-end; margin-bottom: 12px; }
        .currency-btn { display: flex; align-items: center; gap: 6px; border: 1px solid #cbd5e1; padding: 4px 12px; border-radius: 4px; background: white; cursor: pointer; font-size: 13px; font-weight: 600; }
        .currency-btn:hover { background: #f1f5f9; }
        .currency-btn .active { color: #0f172a; }
        .currency-btn .inactive { color: #94a3b8; }
        .no-print { display: block; }
        @media print {
            @page { size: A4 landscape; margin: 12mm 10mm; }
            body { background: white; }
            .print-container { max-width: none; padding: 0; }
            .no-print { display: none !important; }
            .summary-grid { break-inside: avoid; }
            .table-wrap { break-inside: auto; }
            tr { break-inside: avoid; }
        }
    </style>
</head>
<body>
    <div x-data="printReportData()" class="print-container">
        <div class="currency-toggle-wrap no-print">
            <button @click="$store.currency.toggle()" class="currency-btn">
                <span :class="$store.currency.mode === 'SAR' ? 'active' : 'inactive'">SAR</span>
                <span class="text-slate-400">|</span>
                <span :class="$store.currency.mode === 'BDT' ? 'active' : 'inactive'">BDT</span>
            </button>
        </div>

        <div class="report-header">
            <h1>Visa Agent Report</h1>
            <p class="subtitle">Agent-wise Combined Statement</p>
            <div class="meta">
                <span>Generated: <span x-text="new Date().toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })"></span></span>
                <span>Currency: <span x-text="$store.currency.mode"></span></span>
            </div>
        </div>

        <h2 class="agent-title" x-text="agentName"></h2>

        <div class="summary-grid">
            <div class="summary-card">
                <div class="label">Total Submitted</div>
                <div class="value" style="color: #1d4ed8;" x-text="totalSubmitted"></div>
            </div>
            <div class="summary-card">
                <div class="label">Total Issued</div>
                <div class="value" style="color: #16a34a;" x-text="totalIssued"></div>
            </div>
            <div class="summary-card">
                <div class="label">Total Payable</div>
                <div class="value" x-text="$currency(payable, 2)"></div>
            </div>
            <div class="summary-card">
                <div class="label">Total Paid</div>
                <div class="value" style="color: #16a34a;" x-text="$currency(paid, 2)"></div>
            </div>
            <div class="summary-card">
                <div class="label">Total Balance</div>
                <div class="value" :style="balance >= 0 ? 'color: #16a34a;' : 'color: #dc2626;'" x-text="balance >= 0 ? $currency(balance, 2) : '- ' + $currency(Math.abs(balance), 2)"></div>
            </div>
            <div class="summary-card">
                <div class="label">Cancellation Fee</div>
                <div class="value" style="color: #dc2626;" x-text="cancellationFee > 0 ? $currency(cancellationFee, 2) : '-'"></div>
            </div>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Invoice ID</th>
                        <th>Passenger Name</th>
                        <th>Passport No</th>
                        <th class="text-center">Status</th>
                        <th class="text-right">Est. Cost</th>
                        <th class="text-right">Payable</th>
                        <th class="text-right">Paid</th>
                        <th class="text-right">Balance</th>
                        <th class="text-right">Cancel Fee</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="(row, idx) in rows" :key="idx">
                        <tr>
                            <td x-text="row.date"></td>
                            <td class="font-medium" x-text="row.invoice_id || '-'"></td>
                            <td x-text="row.passenger_name || '-'"></td>
                            <td x-text="row.passport_no || '-'"></td>
                            <td class="text-center">
                                <span class="status-badge"
                                    :class="{
                                        'status-submitted': row.status === 'Submitted',
                                        'status-issued': row.status === 'Issued',
                                        'status-cancelled': row.status === 'Cancelled',
                                        'status-payment': row.status === 'Payment'
                                    }"
                                    x-text="row.status"></span>
                            </td>
                            <td class="text-right font-medium" x-text="row.estimated_cost > 0 ? $currency(row.estimated_cost, 2) : '-'"></td>
                            <td class="text-right font-medium" x-text="row.payable > 0 ? $currency(row.payable, 2) : '-'"></td>
                            <td class="text-right" :class="row.paid > 0 ? 'text-green' : 'text-gray'" x-text="row.paid > 0 ? $currency(row.paid, 2) : '-'"></td>
                            <td class="text-right font-semibold"
                                :class="row.balance > 0 ? 'text-green' : (row.balance < 0 ? 'text-red' : 'text-gray')"
                                x-text="$currency(Math.abs(row.balance), 2)"></td>
                            <td class="text-right" :class="row.cancellation_fee > 0 ? 'text-red' : 'text-gray'" x-text="row.cancellation_fee > 0 ? $currency(row.cancellation_fee, 2) : '-'"></td>
                        </tr>
                    </template>
                    <template x-if="rows.length === 0">
                        <tr>
                            <td colspan="10" class="text-center" style="padding: 24px; color: #94a3b8;">No data found</td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        <div class="signature-area no-print">
            <div class="signature-line">
                <div class="line">Authorized Signature</div>
            </div>
            <div class="signature-line">
                <div class="line">Date</div>
            </div>
            <div class="signature-line">
                <div class="line">Agency Stamp</div>
            </div>
        </div>

        <div class="report-footer">
            <span>Generated by BM Umrah System</span>
            <span>Page 1</span>
        </div>
    </div>

    <script>
        function printReportData() {
            return {
                rows: @json($rows->values()->toArray()),
                agentName: '{{ $visaAgent->name }}',
                totalSubmitted: {{ $rows->filter(fn($r) => $r['status'] === 'Submitted')->count() }},
                totalIssued: {{ $rows->filter(fn($r) => $r['status'] === 'Issued')->count() }},
                payable: {{ $rows->sum('payable') }},
                paid: {{ $rows->sum('paid') }},
                balance: {{ $rows->sum('paid') - $rows->sum('payable') - $rows->sum('cancellation_fee') }},
                cancellationFee: {{ $rows->sum('cancellation_fee') }},
            };
        }
    </script>
</body>
</html>
