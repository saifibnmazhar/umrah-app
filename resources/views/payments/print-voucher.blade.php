<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-Payment Voucher - BM Umrah</title>
    <script>window.__currencyRate = {{ $currencyRate }};</script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', 'Segoe UI', Arial, sans-serif; background: #f1f5f9; color: #1e293b; font-size: 14px; }
        .voucher-wrap { max-width: 1100px; margin: 24px auto; background: #fff; border: 1px solid #cbd5e1; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); padding: 28px 32px; }
        .header { text-align: center; border-bottom: 2px solid #1e293b; padding-bottom: 14px; margin-bottom: 20px; }
        .header h1 { font-size: 22px; font-weight: 800; text-transform: uppercase; letter-spacing: 1.5px; color: #0f172a; }
        .header .title { font-size: 17px; font-weight: 600; margin-top: 4px; letter-spacing: 3px; color: #334155; }
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 6px 40px; margin-bottom: 18px; }
        .info-row { display: flex; align-items: baseline; }
        .info-row .label { font-weight: 700; min-width: 140px; font-size: 13px; color: #475569; }
        .info-row .value { font-weight: 600; color: #0f172a; }
        .info-row .value.blank { border-bottom: 1px dashed #94a3b8; min-width: 120px; min-height: 20px; display: inline-block; }
        .section-title { font-weight: 700; font-size: 13px; color: #475569; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px; padding-bottom: 4px; border-bottom: 1px solid #e2e8f0; }
        .table-wrap { border: 1px solid #cbd5e1; border-radius: 6px; overflow: hidden; margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; font-size: 14px; }
        th { background: #f1f5f9; color: #334155; font-weight: 700; text-align: center; padding: 10px 12px; border-bottom: 2px solid #cbd5e1; border-right: 1px solid #e2e8f0; font-size: 13px; text-transform: uppercase; letter-spacing: 0.3px; }
        th:last-child { border-right: none; }
        td { padding: 8px 12px; border-bottom: 1px solid #e2e8f0; border-right: 1px solid #f1f5f9; vertical-align: middle; }
        td:last-child { border-right: none; }
        tr:last-child td { border-bottom: none; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .font-medium { font-weight: 600; }
        .summary-row td { font-weight: 700; border-top: 2px solid #cbd5e1; background: #f8fafc; }
        .summary-row:first-child td { border-top: 2px solid #94a3b8; }
        .due-positive { color: #dc2626; }
        .due-negative { color: #16a34a; }
        .footer { border-top: 2px solid #e2e8f0; padding-top: 20px; margin-top: 8px; }
        .signatures { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; }
        .received-section { margin-bottom: 18px; }
        .form-block { display: grid; grid-template-columns: 1fr 1fr; gap: 20px 40px; }
        .form-group { }
        .form-label { font-weight: 700; font-size: 13px; color: #475569; margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.5px; }
        .form-field { display: flex; align-items: baseline; margin-bottom: 4px; }
        .form-field .label { font-weight: 600; min-width: 150px; font-size: 13px; color: #475569; }
        .form-field .blank { border-bottom: 1px dashed #94a3b8; flex: 1; min-height: 20px; display: inline-block; }
        .sig-block { text-align: center; }
        .sig-block .sig-label { font-weight: 700; font-size: 13px; color: #475569; margin-bottom: 2px; }
        .sig-line { border-top: 1px solid #1e293b; margin-top: 48px; padding-top: 6px; font-size: 12px; color: #475569; }
        .sig-line.filled { border-top-style: solid; }
        .disclaimer { text-align: center; font-size: 11px; color: #94a3b8; margin-top: 16px; font-style: italic; }
        .toolbar { display: flex; justify-content: center; gap: 12px; margin-bottom: 20px; }
        .btn { padding: 10px 28px; font-size: 14px; font-weight: 700; border: none; border-radius: 6px; cursor: pointer; transition: background 0.15s; }
        .btn-primary { background: #1e293b; color: #fff; }
        .btn-primary:hover { background: #0f172a; }
        .btn-secondary { background: #e2e8f0; color: #334155; }
        .btn-secondary:hover { background: #cbd5e1; }
        .btn-outline { display: inline-flex; align-items: center; gap: 6px; border: 1px solid #cbd5e1; padding: 8px 16px; border-radius: 6px; background: #fff; cursor: pointer; font-size: 13px; font-weight: 700; color: #334155; }
        .btn-outline:hover { background: #f8fafc; }
        .btn-outline .active { color: #0f172a; }
        .btn-outline .inactive { color: #94a3b8; }
        .no-print { display: block; }
        @media print {
            body { background: #fff; }
            .voucher-wrap { max-width: none; margin: 0; border: none; border-radius: 0; box-shadow: none; padding: 8px 12px; }
            .no-print { display: none !important; }
            @page { size: landscape; margin: 5mm; }
            th { background: #e2e8f0 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .summary-row td { background: #f1f5f9 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .info-grid { gap: 4px 24px; }
            .table-wrap { margin-bottom: 8px; }
            .received-section { margin-bottom: 8px; }
            .form-field { margin-bottom: 3px; }
            .form-block { gap: 12px 24px; }
            .form-label { margin-bottom: 3px; }
            .section-title { margin-bottom: 4px; }
            .sig-line { margin-top: 24px; }
            .footer { padding-top: 10px; }
            .info-grid[style*="margin-bottom"] { margin-bottom: 8px !important; }
        }
    </style>
</head>
<body>
    <div x-data="voucherData()" class="voucher-wrap">
        <div class="toolbar no-print">
            <button @click="window.print()" class="btn btn-primary">
                Print Voucher
            </button>
        </div>

        <div class="header">
            <h1>BIN MISHAL GLOBAL SERVICES LTD.</h1>
            <div class="title">E-PAYMENT VOUCHER</div>
        </div>

        <div class="info-grid">
            <div class="info-row">
                <span class="label">Voucher No.:</span>
                <span class="value">{{ $payment->voucher?->voucher_id ?? 'N/A' }}</span>
            </div>
            <div class="info-row">
                <span class="label">Date:</span>
                <span class="value">{{ $payment->payment_date->format('d-M-Y') }} ({{ $payment->created_at->format('h:i A') }})</span>
            </div>
            <div class="info-row">
                <span class="label">Ref:</span>
                <span class="value blank">&nbsp;</span>
            </div>
            <div class="info-row">
                <span class="label">Creator Invoice:</span>
                <span class="value">{{ $loggedUser }}</span>
            </div>
        </div>

        <div class="section-title">Payment Information</div>
        <div class="info-grid" style="margin-bottom: 18px;">
            <div class="info-row">
                <span class="label">Payment Type:</span>
                <span class="value">{{ $paymentType }}</span>
            </div>
            <div class="info-row">
                <span class="label">Agent Name:</span>
                <span class="value">
                    @if($payment->visaAgent)
                        {{ $payment->visaAgent->name }}
                    @elseif($payment->ticketAgent)
                        {{ $payment->ticketAgent->name }}
                    @elseif($payment->commissionAgent)
                        {{ $payment->commissionAgent->name }}
                    @else
                        N/A
                    @endif
                </span>
            </div>
            <div class="info-row">
                <span class="label">Currency:</span>
                <span class="value" x-text="$store.currency.mode">SAR</span>
            </div>
            <div class="info-row">
                <span class="label">Payment Method:</span>
                <span class="value">{{ ucfirst($payment->payment_method->value) }}</span>
            </div>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th style="width: 40%;">Description</th>
                        <th style="width: 25%;">Payment</th>
                        <th style="width: 35%;">Remarks</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="(item, index) in items" :key="index">
                        <tr>
                            <td x-text="item.description"></td>
                            <td class="text-right font-medium" x-text="$currency(item.amount, 2)"></td>
                            <td>&nbsp;</td>
                        </tr>
                    </template>
                    <tr class="summary-row">
                        <td>Total Amount</td>
                        <td class="text-right font-medium" x-text="$currency(totalAmount, 2)"></td>
                        <td>&nbsp;</td>
                    </tr>
                    <tr>
                        <td>Paid Amount</td>
                        <td class="text-right font-medium" x-text="$currency(paidAmount, 2)"></td>
                        <td>&nbsp;</td>
                    </tr>
                    <tr class="summary-row">
                        <td x-text="dueAmount >= 0 ? 'Due' : 'Advance'"></td>
                        <td class="text-right font-medium" :class="dueAmount >= 0 ? 'due-positive' : 'due-negative'" x-text="$currency(Math.abs(dueAmount), 2)"></td>
                        <td>&nbsp;</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="received-section">
            <div class="section-title">Received By</div>
            <div class="form-block">
                <div class="form-group">
                    <div class="form-label">If Cash</div>
                    <div class="form-field">
                        <span class="label">Name:</span>
                        <span class="blank"></span>
                    </div>
                    <div class="form-field">
                        <span class="label">Passport:</span>
                        <span class="blank"></span>
                    </div>
                    <div class="form-field">
                        <span class="label">Iqama:</span>
                        <span class="blank"></span>
                    </div>
                    <div class="form-field">
                        <span class="label">Mobile:</span>
                        <span class="blank"></span>
                    </div>
                </div>
                <div class="form-group">
                    <div class="form-label">If Bank</div>
                    <div class="form-field">
                        <span class="label">Bank Details:</span>
                        <span class="blank"></span>
                    </div>
                    <div class="form-field">
                        <span class="label">Beneficiary Name:</span>
                        <span class="blank"></span>
                    </div>
                    <div class="form-field">
                        <span class="label">Bank Name:</span>
                        <span class="blank"></span>
                    </div>
                    <div class="form-field">
                        <span class="label">Account Number/IBAN:</span>
                        <span class="blank"></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="footer">
            <div class="signatures">
                <div class="sig-block">
                    <div class="sig-label">Prepared By</div>
                    <div class="sig-line filled">{{ $loggedUser }}</div>
                </div>
                <div class="sig-block">
                    <div class="sig-label">Authorized By</div>
                    <div class="sig-line"></div>
                </div>
            </div>
        </div>

        <div class="disclaimer">
            This is a system-generated E-Payment Voucher and does not require physical signatures or company stamp.
        </div>
    </div>

    <script>
        function voucherData() {
            return {
                items: @json($lineItemsBdt ?? $lineItems),
                totalAmount: {{ $totalAmount }},
                paidAmount: {{ $paidAmount }},
                dueAmount: {{ $dueAmount }},
            };
        }


    </script>
</body>
</html>
