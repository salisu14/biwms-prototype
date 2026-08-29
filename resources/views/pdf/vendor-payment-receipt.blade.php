<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payment Receipt {{ $payment->payment_number }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111827; }
        .header { margin-bottom: 14px; }
        .title { font-size: 19px; font-weight: 700; margin-bottom: 6px; }
        .logo { max-height: 50px; margin-bottom: 6px; }
        table { width: 100%; border-collapse: collapse; }
        .meta td { vertical-align: top; padding: 2px 0; }
        .lines { margin-top: 14px; }
        .lines th, .lines td { border: 1px solid #d1d5db; padding: 6px; }
        .lines th { background: #f3f4f6; text-align: left; }
        .right { text-align: right; }
        .muted { color: #6b7280; }
        .company-header { margin-bottom: 10px; }
        .company-logo { margin-bottom: 4px; }
        .company-logo img { max-height: 46px; max-width: 180px; }
        .company-name { font-size: 18px; font-weight: 700; margin: 0 0 2px; }
        .company-trading { font-size: 10px; color: #374151; margin-bottom: 2px; }
        .company-meta { font-size: 8.5px; color: #4b5563; line-height: 1.45; }
        .company-meta .divider { padding: 0 4px; }
    </style>
</head>
<body>
    <div class="header">
        @include('pdf.partials.company-header', ['company' => $company ?? []])
        <div class="title">PAYMENT RECEIPT</div>
    </div>

    <table class="meta">
        <tr>
            <td width="60%">
                <strong>Party:</strong> {{ $payment->party_name ?: $payment->vendor?->vendor_name ?: 'Unknown Vendor' }}<br>
                <strong>Payment Method:</strong> {{ $payment->payment_method ?: '—' }}<br>
                <strong>Bank Account:</strong> {{ $payment->bankAccount?->account_name ?: '—' }}
            </td>
            <td width="40%">
                <strong>Payment No:</strong> {{ $payment->payment_number }}<br>
                <strong>Posting Date:</strong> {{ $payment->posting_date?->format('d/m/Y') ?? '—' }}<br>
                <strong>Status:</strong> {{ $payment->status ?? '—' }}
            </td>
        </tr>
    </table>

    <table class="lines">
        <thead>
            <tr>
                <th>Applied At</th>
                <th>Document</th>
                <th>Type</th>
                <th class="right">Applied</th>
                <th class="right">Balance After</th>
            </tr>
        </thead>
        <tbody>
            @forelse($applications as $application)
                <tr>
                    <td>{{ $application['applied_at'] ?: '—' }}</td>
                    <td>{{ $application['document_number'] ?: '—' }}</td>
                    <td>{{ $application['document_type'] ?: '—' }}</td>
                    <td class="right">{{ number_format((float) $application['amount_applied'], 2) }}</td>
                    <td class="right">{{ number_format((float) $application['remaining_after'], 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="muted">No applications recorded for this payment.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <table style="margin-top: 14px;">
        <tr>
            <td width="60%"></td>
            <td width="40%" class="right">
                <strong>Amount:</strong> {{ number_format((float) $payment->payment_amount, 2) }}<br>
                <strong>Applied:</strong> {{ number_format((float) $payment->applied_amount, 2) }}<br>
                <strong>Unapplied:</strong> {{ number_format((float) $payment->unapplied_amount, 2) }}
            </td>
        </tr>
    </table>

    @if(!empty($company['invoice_footer']))
        <div class="muted" style="margin-top: 18px;">{{ $company['invoice_footer'] }}</div>
    @endif
</body>
</html>
