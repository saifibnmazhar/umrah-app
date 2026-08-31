<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Refund Voucher - BM Umrah</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', 'Segoe UI', Arial, sans-serif; background: #f1f5f9; color: #1e293b; font-size: 14px; }
        .voucher-wrap { max-width: 1100px; margin: 24px auto; background: #fff; border: 1px solid #cbd5e1; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); padding: 28px 32px; }
        .header { text-align: center; border-bottom: 2px solid #1e293b; padding-bottom: 14px; margin-bottom: 20px; }
        .header h1 { font-size: 22px; font-weight: 800; text-transform: uppercase; letter-spacing: 1.5px; color: #0f172a; }
        .header .title { font-size: 17px; font-weight: 700; margin-top: 4px; letter-spacing: 3px; color: #dc2626; }
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 6px 40px; margin-bottom: 18px; }
        .info-row { display: flex; align-items: baseline; }
        .info-row .label { font-weight: 700; min-width: 140px; font-size: 13px; color: #475569; }
        .info-row .value { font-weight: 600; color: #0f172a; }
        .section-title { font-weight: 700; font-size: 13px; color: #475569; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px; padding-bottom: 4px; border-bottom: 1px solid #e2e8f0; }
        .table-wrap { border: 1px solid #cbd5e1; border-radius: 6px; overflow: hidden; margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; font-size: 14px; }
        th { background: #f1f5f9; color: #334155; font-weight: 700; text-align: left; padding: 8px 12px; border-bottom: 2px solid #cbd5e1; }
        td { padding: 7px 12px; border-bottom: 1px solid #e2e8f0; }
        tr:last-child td { border-bottom: none; }
        .text-right { text-align: right; }
        .summary-row td { font-weight: 700; border-top: 2px solid #94a3b8; background: #f8fafc; }
        .refund-cell { color: #16a34a; font-weight: 800; }
        .received-section { margin-bottom: 18px; }
        .form-block { display: grid; grid-template-columns: 1fr 1fr; gap: 20px 40px; }
        .form-label { font-weight: 700; font-size: 13px; color: #475569; margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.5px; }
        .form-field { display: flex; align-items: baseline; margin-bottom: 4px; }
        .form-field .label { font-weight: 600; min-width: 150px; font-size: 13px; color: #475569; }
        .form-field .blank { border-bottom: 1px dashed #94a3b8; flex: 1; min-height: 20px; display: inline-block; }
        .footer { border-top: 2px solid #e2e8f0; padding-top: 20px; margin-top: 8px; }
        .signatures { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; }
        .sig-block { text-align: center; }
        .sig-block .sig-label { font-weight: 700; font-size: 13px; color: #475569; margin-bottom: 2px; }
        .sig-line { border-top: 1px solid #1e293b; margin-top: 48px; padding-top: 6px; font-size: 12px; color: #475569; }
        .sig-line.filled { border-top-style: solid; }
        .disclaimer { text-align: center; font-size: 11px; color: #94a3b8; margin-top: 16px; font-style: italic; }
        .toolbar { display: flex; justify-content: center; gap: 12px; margin-bottom: 20px; }
        .btn { padding: 10px 28px; font-size: 14px; font-weight: 700; border: none; border-radius: 6px; cursor: pointer; transition: background 0.15s; }
        .btn-primary { background: #1e293b; color: #fff; }
        .btn-primary:hover { background: #0f172a; }
        .no-print { display: block; }
        @media print {
            body { background: #fff; }
            .voucher-wrap { max-width: none; margin: 0; border: none; border-radius: 0; box-shadow: none; padding: 4px 8px; }
            .no-print { display: none !important; }
            @page { size: landscape; margin: 3mm; }
            .header { padding-bottom: 6px; margin-bottom: 8px; }
            .info-grid { gap: 2px 16px; margin-bottom: 6px; }
            .section-title { margin-bottom: 4px; }
            th { background: #e2e8f0 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            td { padding: 4px 8px; }
            .table-wrap { margin-bottom: 8px; }
            .received-section { margin-bottom: 4px; }
            .form-field { margin-bottom: 4px; }
            .form-field .blank { min-height: 12px; }
            .form-block { gap: 6px 16px; }
            .footer { padding-top: 4px; margin-top: 0; }
            .sig-line { margin-top: 10px; }
            .disclaimer { margin-top: 6px; }
        }
    </style>
</head>
<body>
    @php $cb = $cancelledBooking; @endphp
    <div x-data="voucherData()" class="voucher-wrap">
        <div class="toolbar no-print">
            <button @click="window.print()" class="btn btn-primary">Print Voucher</button>
        </div>

        <div class="header">
            <h1>BIN MISHAL GLOBAL SERVICES LTD.</h1>
            <div class="title">REFUND VOUCHER</div>
        </div>

        <div class="info-grid">
            <div class="info-row">
                <span class="label">Voucher No:</span>
                <span class="value">{{ $cb->refundVoucher?->voucher_id ?? 'N/A' }}</span>
            </div>
            <div class="info-row">
                <span class="label">Date:</span>
                <span class="value">{{ ($cb->refundVoucher?->payment_date ?? $cb->created_at)?->format('d-M-Y') }}</span>
            </div>
            <div class="info-row">
                <span class="label">Invoice No:</span>
                <span class="value">{{ $cb->booking?->invoice_id ?? '—' }}</span>
            </div>
        </div>

        <div class="section-title">Cancellation Information</div>
        <div class="info-grid" style="margin-bottom: 18px;">
            <div class="info-row">
                <span class="label">Cancelled By:</span>
                <span class="value">{{ $cb->user?->name ?? '—' }}</span>
                <span class="label" style="margin-left: 30px;">Cancellation Branch:</span>
                <span class="value">{{ $cb->cancellationBranch?->name ?? '—' }}</span>
            </div>
            <div class="info-row">
                <span class="label">Cancel Date:</span>
                <span class="value">{{ $cb->created_at?->format('d-M-Y') ?? '—' }}</span>
                <span class="label" style="margin-left: 30px;">Booking Branch:</span>
                <span class="value">{{ $cb->booking?->bookingBranch?->name ?? '—' }}</span>
            </div>
            <div class="info-row">
                <span class="label">Confirmed By:</span>
                <span class="value">{{ $cb->confirmedBy?->name ?? '—' }}</span>
            </div>
        </div>

        <div class="section-title">Financial Summary</div>
        <div class="table-wrap">
            <table>
                <tbody>
                    <tr>
                        <td>Total Amount</td>
                        <td class="text-right font-medium" x-text="$currency(totalAmount, 2)"></td>
                    </tr>
                    <tr>
                        <td>Total Paid</td>
                        <td class="text-right font-medium" x-text="$currency(totalPaid, 2)"></td>
                    </tr>
                    <tr>
                        <td>Service Charge Deduction</td>
                        <td class="text-right font-medium" x-text="$currency(serviceCharge, 2)"></td>
                    </tr>
                    <tr class="summary-row">
                        <td>Refund Amount</td>
                        <td class="text-right refund-cell" x-text="$currency(refundAmount, 2)"></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="section-title">Payment Details</div>
        <div class="info-grid" style="margin-bottom: 18px;">
            <div class="info-row">
                <span class="label">Payment Method:</span>
                <span class="value">{{ ucfirst($cb->refundPayment?->payment_method?->value ?? '—') }}</span>
            </div>
            <div class="info-row">
                <span class="label">Transaction ID:</span>
                <span class="value">{{ $cb->refundPayment?->transaction_id ?? '—' }}</span>
            </div>
        </div>

        <div class="received-section">
            <div class="section-title">Received By</div>
            <div class="form-block">
                <div class="form-group">
                    <div class="form-label">If Cash</div>
                    <div class="form-field"><span class="label">Name:</span><span class="blank"></span></div>
                    <div class="form-field"><span class="label">Passport:</span><span class="blank"></span></div>
                    <div class="form-field"><span class="label">Iqama:</span><span class="blank"></span></div>
                    <div class="form-field"><span class="label">Mobile:</span><span class="blank"></span></div>
                </div>
                <div class="form-group">
                    <div class="form-label">If Bank</div>
                    <div class="form-field"><span class="label">Bank Details:</span><span class="blank"></span></div>
                    <div class="form-field"><span class="label">Beneficiary:</span><span class="blank"></span></div>
                    <div class="form-field"><span class="label">Account/IBAN:</span><span class="blank"></span></div>
                </div>
            </div>
        </div>

        <div class="footer">
            <div class="signatures">
                <div class="sig-block">
                    <div class="sig-label">Prepared By</div>
                    <div class="sig-line filled">{{ auth()->user()->name }}</div>
                </div>
                <div class="sig-block">
                    <div class="sig-label">Authorized By</div>
                    <div class="sig-line"></div>
                </div>
            </div>
        </div>

        <div class="disclaimer">
            This is a system-generated Refund Voucher and does not require physical signatures or company stamp.
        </div>
    </div>

    <script>
        function voucherData() {
            return {
                totalAmount: {{ (float) ($cb->booking?->invoice?->total_amount ?? 0) }},
                totalPaid: {{ (float) ($cb->total_paid ?? 0) }},
                serviceCharge: {{ (float) ($cb->service_charge_deduction ?? 0) }},
                refundAmount: {{ (float) ($cb->refund_amount ?? 0) }},
            };
        }
    </script>
</body>
</html>
