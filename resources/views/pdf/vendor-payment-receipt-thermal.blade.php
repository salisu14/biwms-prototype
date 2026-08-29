<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payment Receipt {{ $payment->payment_number }}</title>
    @php
        $widthMm = ($format ?? '80mm') === '58mm' ? 58 : 80;
        $heightMm = ($format ?? '80mm') === '58mm' ? max(220, 140 + ($applications->count() * 38)) : max(260, 160 + ($applications->count() * 34));
    @endphp
    @include('pdf.partials.thermal-css', [
        'width_mm' => $widthMm,
        'height_mm' => $heightMm,
        'margin_mm' => $widthMm === 58 ? 3 : 4,
        'body_font_size' => $widthMm === 58 ? 8 : 9,
        'title_font_size' => $widthMm === 58 ? 11 : 12,
        'meta_font_size' => $widthMm === 58 ? 7 : 8,
        'section_title_font_size' => $widthMm === 58 ? 7 : 8,
        'table_header_font_size' => $widthMm === 58 ? 6 : 7,
    ])
</head>
<body>
    <div class="page">
        <div class="header">
            @include('pdf.partials.company-header', ['company' => $company ?? []])
            <div class="title">Payment Receipt</div>
            <table class="meta">
                <tr><td><span class="strong">Payment:</span> {{ $payment->payment_number }}</td></tr>
                <tr><td><span class="strong">Party:</span> {{ $payment->party_name ?: $payment->vendor?->vendor_name ?: 'Unknown Vendor' }}</td></tr>
                <tr><td><span class="strong">Party No.:</span> {{ $payment->party?->vendor_code ?? $payment->party_id ?? '—' }}</td></tr>
                <tr><td><span class="strong">Method:</span> {{ $payment->payment_method ?: '—' }}</td></tr>
                <tr><td><span class="strong">Date:</span> {{ $payment->posting_date?->format('d/m/Y') ?? '—' }}</td></tr>
                <tr><td><span class="strong">Status:</span> {{ $payment->status ?? '—' }}</td></tr>
            </table>
        </div>

        <div class="section card">
            <div class="row">
                <span class="label">Amount</span>
                <span class="value strong">{{ number_format((float) $payment->payment_amount, 2) }} {{ $payment->currency_code ?: '' }}</span>
            </div>
            <div class="row">
                <span class="label">Applied</span>
                <span class="value">{{ number_format((float) $payment->applied_amount, 2) }} {{ $payment->currency_code ?: '' }}</span>
            </div>
            <div class="row">
                <span class="label">Unapplied</span>
                <span class="value">{{ number_format((float) $payment->unapplied_amount, 2) }} {{ $payment->currency_code ?: '' }}</span>
            </div>
            <div class="row">
                <span class="label">Bank / Cash</span>
                <span class="value">{{ $payment->bankAccount?->account_name ?: '—' }}</span>
            </div>
        </div>

        <div class="section">
            <div class="card-title">Applications</div>
            @forelse($applications as $application)
                <div class="card">
                    <div class="row">
                        <span class="label">Document</span>
                        <span class="value">{{ $application['document_number'] ?: '—' }}</span>
                    </div>
                    <div class="row">
                        <span class="label">Type</span>
                        <span class="value">{{ $application['document_type'] ?: '—' }}</span>
                    </div>
                    <div class="row">
                        <span class="label">Applied</span>
                        <span class="value">{{ number_format((float) $application['amount_applied'], 2) }}</span>
                    </div>
                    <div class="row">
                        <span class="label">Remaining</span>
                        <span class="value">{{ number_format((float) $application['remaining_after'], 2) }}</span>
                    </div>
                </div>
            @empty
                <div class="card">
                    <div class="muted">No applications recorded for this payment.</div>
                </div>
            @endforelse
        </div>

        <div class="footer">
            {{ $company['invoice_footer'] ?? '' }}<br>
            Printed {{ now()->format('d/m/Y H:i') }}
        </div>
    </div>
</body>
</html>
