<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Purchase Receipt {{ $receipt->document_number }}</title>
    @php
        $widthMm = ($format ?? '80mm') === '58mm' ? 58 : 80;
        $heightMm = ($format ?? '80mm') === '58mm' ? max(220, 130 + ($receipt->lines->count() * 34)) : max(260, 150 + ($receipt->lines->count() * 30));
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
            <div class="title">Purchase Receipt</div>
            <table class="meta">
                <tr><td><span class="strong">Receipt:</span> {{ $receipt->document_number }}</td></tr>
                <tr><td><span class="strong">Vendor:</span> {{ $receipt->vendor?->vendor_name ?: $receipt->buy_from_vendor_name ?: 'Unknown Vendor' }}</td></tr>
                <tr><td><span class="strong">PO:</span> {{ $receipt->purchase_order_no ?: '—' }}</td></tr>
                <tr><td><span class="strong">Location:</span> {{ $receipt->receivingLocation?->code ? "{$receipt->receivingLocation->code} - {$receipt->receivingLocation->name}" : ($receipt->receivingLocation?->name ?? '—') }}</td></tr>
                <tr><td><span class="strong">Date:</span> {{ $receipt->posting_date?->format('d/m/Y') ?? '—' }}</td></tr>
                <tr><td><span class="strong">Status:</span> {{ $receipt->posted ? 'Posted' : 'Open' }}</td></tr>
            </table>
        </div>

        <div class="section">
            <div class="card-title">Items Received</div>
            @foreach($receipt->lines as $line)
                <div class="card">
                    <div class="row">
                        <span class="label">Item</span>
                        <span class="value">{{ trim(($line->item?->item_code ?: $line->no ?: '—').($line->item?->description ? ' - '.$line->item->description : '')) }}</span>
                    </div>
                    <div class="row">
                        <span class="label">Description</span>
                        <span class="value">{{ $line->description ?: '—' }}</span>
                    </div>
                    <div class="row">
                        <span class="label">Qty / UOM</span>
                        <span class="value">{{ number_format((float) $line->quantity, 2) }} {{ $line->unit_of_measure_code ?: '' }}</span>
                    </div>
                    <div class="row">
                        <span class="label">Unit Cost</span>
                        <span class="value">{{ number_format((float) $line->direct_unit_cost, 2) }}</span>
                    </div>
                    <div class="row">
                        <span class="label">Line Amount</span>
                        <span class="value">{{ number_format((float) $line->line_amount, 2) }}</span>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="section card">
            <div class="row">
                <span class="label">Total Lines</span>
                <span class="value strong">{{ number_format((float) $receipt->lines->sum('line_amount'), 2) }}</span>
            </div>
        </div>

        <div class="footer">
            {{ $company['invoice_footer'] ?? '' }}<br>
            Printed {{ now()->format('d/m/Y H:i') }}
        </div>
    </div>
</body>
</html>
