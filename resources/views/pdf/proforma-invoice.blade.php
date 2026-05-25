<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        body {
            font-family: 'Helvetica', sans-serif;
            font-size: 11pt;
            color: #333;
            margin: 0;
            padding: 0;
        }
        .header {
            margin-bottom: 30px;
        }
        .company-info {
            float: left;
            width: 50%;
        }
        .doc-info {
            float: right;
            width: 40%;
            text-align: right;
        }
        .doc-title {
            font-size: 18pt;
            font-weight: bold;
            color: #1a56db;
            margin-bottom: 10px;
        }
        .section-header {
            clear: both;
            border-bottom: 2px solid #1a56db;
            margin-bottom: 15px;
            padding-top: 20px;
        }
        .client-info {
            margin-bottom: 30px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th {
            background-color: #f3f4f6;
            text-align: left;
            padding: 8px;
            font-size: 9pt;
            text-transform: uppercase;
            border-bottom: 1px solid #d1d5db;
        }
        td {
            padding: 8px;
            border-bottom: 1px solid #f3f4f6;
            font-size: 10pt;
            vertical-align: top;
        }
        .text-right {
            text-align: right;
        }
        .footer {
            margin-top: 30px;
        }
        .totals-table {
            float: right;
            width: 40%;
        }
        .totals-table td {
            border: none;
            padding: 4px 8px;
        }
        .grand-total {
            font-weight: bold;
            font-size: 12pt;
            border-top: 2px solid #333 !important;
        }
        .page-break {
            page-break-after: always;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="company-info">
            <div style="font-size: 16pt; font-weight: bold;">{{ $company['name'] }}</div>
            <div>{{ $company['address'] }}</div>
            <div>Email: {{ $company['email'] }}</div>
            <div>Tel: {{ $company['phone'] }}</div>
        </div>
        <div class="doc-info">
            <div class="doc-title">{{ $title }}</div>
            <div>No: <strong>{{ $order_number }}</strong></div>
            <div>Date: {{ $date }}</div>
            <div>Currency: {{ $currency }}</div>
        </div>
    </div>

    <div class="section-header"></div>

    <div class="client-info">
        <div style="font-weight: bold; text-transform: uppercase; color: #6b7280; font-size: 9pt;">{{ $client_label }} Details</div>
        <div style="font-size: 12pt; font-weight: bold;">{{ $client_name }}</div>
        <div>{!! nl2br(e($client_address)) !!}</div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Item Code</th>
                <th>Description</th>
                <th class="text-right">Qty</th>
                <th>UoM</th>
                <th class="text-right">Price</th>
                <th class="text-right">Disc %</th>
                <th class="text-right">Disc Amnt</th>
                <th class="text-right">VAT</th>
                <th class="text-right">Net Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach($lines as $line)
                <tr>
                    <td>{{ $line['item_code'] }}</td>
                    <td>{{ $line['description'] }}</td>
                    <td class="text-right">{{ number_format($line['qty'], 2) }}</td>
                    <td>{{ $line['uom'] }}</td>
                    <td class="text-right">{{ number_format($line['price'], 2) }}</td>
                    <td class="text-right">{{ number_format($line['discount_pct'], 2) }}%</td>
                    <td class="text-right">{{ number_format($line['discount_amount'], 2) }}</td>
                    <td class="text-right">{{ number_format($line['vat_amount'], 2) }}</td>
                    <td class="text-right">{{ number_format($line['net_amount'], 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        <div style="float: left; width: 50%; font-size: 9pt; color: #6b7280;">
            <p><strong>Note:</strong> This is a proforma invoice for information purposes only and does not constitute a legal invoice for payment until confirmed.</p>
        </div>
        
        <table class="totals-table">
            <tr>
                <td>Subtotal</td>
                <td class="text-right">{{ number_format($totals['subtotal'], 2) }}</td>
            </tr>
            @if($totals['discount'] > 0)
            <tr>
                <td>Discount Total</td>
                <td class="text-right">-{{ number_format($totals['discount'], 2) }}</td>
            </tr>
            @endif
            <tr>
                <td>VAT Total</td>
                <td class="text-right">{{ number_format($totals['vat'], 2) }}</td>
            </tr>
            <tr>
                <td>Total Qty</td>
                <td class="text-right">{{ $totals['total_qty_display'] ?? number_format($totals['total_qty'], 2) }}</td>
            </tr>
            <tr class="grand-total">
                <td>GRAND TOTAL ({{ $currency }})</td>
                <td class="text-right">{{ number_format($totals['grand_total'], 2) }}</td>
            </tr>
        </table>
    </div>
</body>
</html>
