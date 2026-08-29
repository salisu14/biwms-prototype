<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Posted Purchase Credit Memo {{ $memo->document_number }}</title>
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
        <div class="title">POSTED PURCHASE CREDIT MEMO</div>
    </div>

    <table class="meta">
        <tr>
            <td width="60%">
                <strong>Vendor:</strong> {{ $memo->vendor_name ?: $memo->vendor?->vendor_name ?: 'Unknown Vendor' }}<br>
                <strong>Corrects Invoice:</strong> {{ $memo->corrects_invoice_number ?: '—' }}<br>
                <strong>Location:</strong> {{ $memo->location?->code ? "{$memo->location->code} - {$memo->location->name}" : ($memo->location?->name ?? '—') }}
            </td>
            <td width="40%">
                <strong>Credit Memo No:</strong> {{ $memo->document_number }}<br>
                <strong>Posting Date:</strong> {{ $memo->posting_date?->format('d/m/Y') ?? '—' }}<br>
                <strong>Status:</strong> {{ $memo->posted ? 'Posted' : 'Open' }}
            </td>
        </tr>
    </table>

    <table class="lines">
        <thead>
            <tr>
                <th>Item</th>
                <th>Description</th>
                <th class="right">Qty</th>
                <th>UOM</th>
                <th class="right">Unit Cost</th>
                <th class="right">Line Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($memo->lines as $line)
                <tr>
                    <td>{{ trim(($line->item_code ?: '—').($line->description ? ' - '.$line->description : '')) }}</td>
                    <td>{{ $line->description ?: '—' }}</td>
                    <td class="right">{{ number_format((float) $line->quantity, 2) }}</td>
                    <td>{{ $line->unit_of_measure_code ?: '—' }}</td>
                    <td class="right">{{ number_format((float) ($line->unit_price ?? $line->unit_cost ?? 0), 2) }}</td>
                    <td class="right">{{ number_format((float) $line->line_total, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table style="margin-top: 14px;">
        <tr>
            <td width="60%"></td>
            <td width="40%" class="right">
                <strong>Subtotal:</strong> {{ number_format((float) $memo->subtotal, 2) }}<br>
                <strong>VAT:</strong> {{ number_format((float) $memo->tax_amount, 2) }}<br>
                <strong>Grand Total:</strong> {{ number_format((float) $memo->grand_total, 2) }}
            </td>
        </tr>
    </table>

    @if(!empty($company['invoice_footer']))
        <div class="muted" style="margin-top: 18px;">{{ $company['invoice_footer'] }}</div>
    @endif
</body>
</html>
