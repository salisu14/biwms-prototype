<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Waybill</title>
    <style>
        body { font-family: sans-serif; font-size: 12px; margin: 0; padding: 20px; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #000; padding-bottom: 10px; }
        .company-name { font-size: 24px; font-weight: bold; margin-bottom: 5px; }
        .doc-title { font-size: 18px; font-weight: bold; text-transform: uppercase; letter-spacing: 2px; }
        .copy-type { font-size: 14px; font-weight: bold; margin-top: 5px; padding: 4px; border: 1px solid #000; display: inline-block; }
        
        .info-grid { width: 100%; margin-bottom: 20px; border-collapse: collapse; }
        .info-grid td { padding: 5px; vertical-align: top; }
        .info-label { font-weight: bold; width: 120px; display: inline-block; }
        
        table.lines { width: 100%; border-collapse: collapse; margin-top: 10px; }
        table.lines th, table.lines td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        table.lines th { background-color: #f4f4f4; border-bottom: 2px solid #000; }
        
        .footer { margin-top: 40px; }
        .signature-grid { width: 100%; margin-top: 50px; }
        .signature-grid td { text-align: center; vertical-align: bottom; height: 80px; }
        .sig-line { border-top: 1px solid #000; width: 80%; margin: 0 auto; padding-top: 5px; }
        
        .page-break { page-break-after: always; }
    </style>
</head>
<body>

@foreach($copies as $index => $copy)
    <div class="header">
        <div class="company-name">{{ $company['name'] ?? config('app.name') }}</div>
        @if(!empty($company['address_lines']))
            <div>{{ implode(', ', $company['address_lines']) }}</div>
        @endif
        <div>
            @if(!empty($company['phone'])) Tel: {{ $company['phone'] }} @endif
            @if(!empty($company['email'])) | Email: {{ $company['email'] }} @endif
            @if(!empty($company['website'])) | Web: {{ $company['website'] }} @endif
        </div>
        <div class="doc-title">SALES WAYBILL / DISPATCH NOTE</div>
        <div class="copy-type">{{ strtoupper($copy) }}</div>
    </div>

    <table class="info-grid">
        <tr>
            <td style="width: 50%;">
                <div><span class="info-label">Customer:</span> {{ $shipment->sell_to_customer_name }}</div>
                <div><span class="info-label">Customer No:</span> {{ $shipment->sell_to_customer_no }}</div>
                <div><span class="info-label">Ship-To Code:</span> {{ $shipment->ship_to_code ?? 'Default' }}</div>
            </td>
            <td style="width: 50%;">
                <div><span class="info-label">Waybill No:</span> {{ $shipment->document_no }}</div>
                <div><span class="info-label">Order No:</span> {{ $shipment->order_no }}</div>
                <div><span class="info-label">Shipment Date:</span> {{ $shipment->shipment_date?->format('d/m/Y') }}</div>
                <div><span class="info-label">Location:</span> {{ $shipment->location_code }}</div>
            </td>
        </tr>
    </table>

    <table class="lines">
        <thead>
            <tr>
                <th style="width: 5%;">No.</th>
                <th style="width: 15%;">Item No.</th>
                <th style="width: 50%;">Description</th>
                <th style="width: 15%;">Qty Shipped</th>
                <th style="width: 15%;">UOM</th>
            </tr>
        </thead>
        <tbody>
            @foreach($shipment->lines as $i => $line)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $line->no }}</td>
                    <td>{{ $line->description }}</td>
                    <td>{{ number_format($line->quantity, 2) }}</td>
                    <td>{{ $line->unit_of_measure_code }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        <table class="signature-grid">
            <tr>
                <td><div class="sig-line">Prepared By (Store)</div></td>
                <td><div class="sig-line">Checked By (Security)</div></td>
                <td><div class="sig-line">Received By (Driver/Cust)</div></td>
            </tr>
        </table>
    </div>

    @if(!$loop->last)
        <div class="page-break"></div>
    @endif
@endforeach

</body>
</html>
