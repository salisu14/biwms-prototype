<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Purchase Receipt {{ $receipt->document_number }}</title>
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
        <div class="title">PURCHASE RECEIPT</div>
    </div>

    <table class="meta">
        <tr>
            <td width="60%">
                <strong>Vendor:</strong> {{ $receipt->vendor?->vendor_name ?: $receipt->buy_from_vendor_name ?: 'Unknown Vendor' }}<br>
                <strong>Purchase Order:</strong> {{ $receipt->purchase_order_no ?: '—' }}<br>
                <strong>Location:</strong> {{ $receipt->receivingLocation?->code ? "{$receipt->receivingLocation->code} - {$receipt->receivingLocation->name}" : ($receipt->receivingLocation?->name ?? '—') }}
            </td>
            <td width="40%">
                <strong>Receipt No:</strong> {{ $receipt->document_number }}<br>
                <strong>Posting Date:</strong> {{ $receipt->posting_date?->format('d/m/Y') ?? '—' }}<br>
                <strong>Status:</strong> {{ $receipt->posted ? 'Posted' : 'Open' }}
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
                <th class="right">Line Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach($receipt->lines as $line)
                <tr>
                    <td>{{ trim(($line->item?->item_code ?: $line->no ?: '—').($line->item?->description ? ' - '.$line->item->description : '')) }}</td>
                    <td>{{ $line->description ?: '—' }}</td>
                    <td class="right">{{ number_format((float) $line->quantity, 2) }}</td>
                    <td>{{ $line->unit_of_measure_code ?: '—' }}</td>
                    <td class="right">{{ number_format((float) $line->direct_unit_cost, 2) }}</td>
                    <td class="right">{{ number_format((float) $line->line_amount, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table style="margin-top: 14px;">
        <tr>
            <td width="60%"></td>
            <td width="40%" class="right">
                <strong>Total Lines:</strong> {{ number_format((float) $receipt->lines->sum('line_amount'), 2) }}
            </td>
        </tr>
    </table>

    @if(!empty($company['invoice_footer']))
        <div class="muted" style="margin-top: 18px;">{{ $company['invoice_footer'] }}</div>
    @endif
</body>
</html>
