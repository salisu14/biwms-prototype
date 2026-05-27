<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $title }} - {{ $invoice_number }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111827; }
        .header { margin-bottom: 16px; }
        .title { font-size: 20px; font-weight: 700; margin-bottom: 8px; }
        .logo { max-height: 52px; margin-bottom: 6px; }
        .grid { width: 100%; }
        .grid td { vertical-align: top; padding: 2px 0; }
        table.lines { width: 100%; border-collapse: collapse; margin-top: 16px; }
        table.lines th, table.lines td { border: 1px solid #d1d5db; padding: 6px; }
        table.lines th { background: #f3f4f6; text-align: left; }
        .text-right { text-align: right; }
    </style>
</head>
<body>
    <div class="header">
        <div class="title">{{ $title }}</div>
        <table class="grid">
            <tr>
                <td width="60%">
                    @if(!empty($company['logo_data_uri']))
                        <img src="{{ $company['logo_data_uri'] }}" alt="Company Logo" class="logo">
                    @endif
                    <strong>{{ $company['name'] }}</strong><br>
                    @if(!empty($company['address_lines']))
                        {{ implode(', ', $company['address_lines']) }}<br>
                    @elseif(!empty($company['address']))
                        {{ $company['address'] }}<br>
                    @endif
                    @if(!empty($company['email'])){{ $company['email'] }}@endif
                    @if(!empty($company['phone'])) | {{ $company['phone'] }}@endif
                    @if(!empty($company['website'])) | {{ $company['website'] }}@endif
                    @if(!empty($company['tax_no']))<br><strong>Tax No:</strong> {{ $company['tax_no'] }}@endif
                </td>
                <td width="40%">
                    <strong>Invoice No:</strong> {{ $invoice_number }}<br>
                    <strong>Order No:</strong> {{ $order_number }}<br>
                    <strong>Posting Date:</strong> {{ $posting_date }}<br>
                    <strong>Document Date:</strong> {{ $document_date }}
                </td>
            </tr>
        </table>
    </div>

    <div>
        <strong>Bill To:</strong><br>
        {{ $customer_name }}<br>
        {{ $customer_address }}
    </div>

    <table class="lines">
        <thead>
            <tr>
                <th>Item</th>
                <th>Description</th>
                <th class="text-right">Qty</th>
                <th>UOM</th>
                <th class="text-right">Unit Price</th>
                <th class="text-right">Discount</th>
                <th class="text-right">VAT</th>
                <th class="text-right">Line Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($lines as $line)
                <tr>
                    <td>{{ $line['item_code'] }}</td>
                    <td>{{ $line['description'] }}</td>
                    <td class="text-right">{{ number_format($line['qty'], 2) }}</td>
                    <td>{{ $line['uom'] }}</td>
                    <td class="text-right">{{ number_format($line['unit_price'], 2) }}</td>
                    <td class="text-right">{{ number_format($line['discount_amount'], 2) }}</td>
                    <td class="text-right">{{ number_format($line['vat_amount'], 2) }}</td>
                    <td class="text-right">{{ number_format($line['line_total'], 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="grid" style="margin-top: 16px;">
        <tr>
            <td width="65%"></td>
            <td width="35%">
                <strong>Subtotal:</strong> {{ $currency }} {{ number_format($totals['subtotal'], 2) }}<br>
                <strong>Discount:</strong> {{ $currency }} {{ number_format($totals['discount'], 2) }}<br>
                <strong>VAT:</strong> {{ $currency }} {{ number_format($totals['vat'], 2) }}<br>
                <strong>Grand Total:</strong> {{ $currency }} {{ number_format($totals['grand_total'], 2) }}
            </td>
        </tr>
    </table>
    @if(!empty($company['invoice_footer']))
        <div style="margin-top: 18px; font-size: 11px; color: #4b5563;">
            {{ $company['invoice_footer'] }}
        </div>
    @endif
</body>
</html>
