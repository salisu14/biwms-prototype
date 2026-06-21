<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        /* Document Setup */
        @page {
            size: A4;
            margin: 15mm;
        }

        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 12px;
            line-height: 1.5;
            color: #1f2937;
            margin: 0;
            padding: 0;
        }

        /* Header Layout */
        .header {
            display: table;
            width: 100%;
            border-bottom: 2px solid #1a56db;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }

        .company-info {
            display: table-cell;
            vertical-align: top;
        }

        .company-info h1 {
            margin: 0;
            color: #1a56db;
            font-size: 24px;
            letter-spacing: -0.5px;
        }

        .company-details {
            color: #4b5563;
            font-size: 11px;
            margin-top: 5px;
        }

        .quote-meta {
            display: table-cell;
            text-align: right;
            vertical-align: top;
        }

        .quote-title {
            font-size: 24px;
            font-weight: bold;
            color: #111827;
            margin: 0;
        }

        .status-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
            background: #f3f4f6;
            color: #374151;
            margin-top: 5px;
            border: 1px solid #d1d5db;
        }

        /* Information Grid */
        .details-table {
            width: 100%;
            margin-bottom: 30px;
        }

        .details-column {
            width: 50%;
            vertical-align: top;
        }

        .section-label {
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
            color: #6b7280;
            margin-bottom: 5px;
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 2px;
            width: 90%;
        }

        /* Line Items Table */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        .items-table th {
            background-color: #f9fafb;
            color: #374151;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 10px;
            padding: 10px;
            border-bottom: 2px solid #e5e7eb;
            text-align: left;
        }

        .items-table td {
            padding: 10px;
            border-bottom: 1px solid #f3f4f6;
            vertical-align: top;
        }

        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .font-bold { font-weight: bold; }

        /* Summary Section */
        .summary-wrapper {
            width: 100%;
        }

        .summary-table {
            width: 250px;
            margin-left: auto;
            border-collapse: collapse;
        }

        .summary-table td {
            padding: 6px 0;
            font-size: 12px;
        }

        .total-row td {
            border-top: 2px solid #111827;
            font-weight: bold;
            font-size: 15px;
            color: #1a56db;
            padding-top: 10px;
        }

        .footer-notes {
            margin-top: 40px;
            padding: 15px;
            background-color: #f9fafb;
            border-radius: 8px;
            font-size: 11px;
            color: #4b5563;
        }

        .revision-text {
            color: #dc2626;
            font-weight: bold;
            font-size: 11px;
        }
    </style>
    <title>Quote {{ $quote->quote_no }}</title>
</head>
<body>

<div class="header">
    <div class="company-info">
        @if(!empty($company['logo_data_uri']))
            <div style="margin-bottom: 8px;">
                <img src="{{ $company['logo_data_uri'] }}" alt="Company Logo" style="max-height: 48px;">
            </div>
        @endif
        <h1>{{ $company['name'] ?? config('app.name') }}</h1>
        <div class="company-details">
            @if(!empty($company['address_lines']))
                {{ implode(', ', $company['address_lines']) }}<br>
            @endif
            @if(!empty($company['phone']))<strong>Tel:</strong> {{ $company['phone'] }} @endif
            @if(!empty($company['email'])) | <strong>Email:</strong> {{ $company['email'] }} @endif
            @if(!empty($company['website'])) | <strong>Web:</strong> {{ $company['website'] }} @endif
        </div>
    </div>
    <div class="quote-meta">
        <h2 class="quote-title">SALES QUOTE</h2>
        <div class="font-bold">#{{ $quote->quote_no }}</div>
        @if(isset($revision))
            <div class="revision-text">Revision: v{{ $revision->version }}</div>
        @endif
        <div class="status-badge">{{ $quote->status->name ?? 'Draft' }}</div>
    </div>
</div>

<table class="details-table">
    <tr>
        <td class="details-column">
            <div class="section-label">Customer Information</div>
            <div style="font-size: 13px; font-weight: bold;">{{ $quote->customer->name }}</div>
            <div>{{ $quote->customer->address ?? 'No address provided' }}</div>
            <div>{{ $quote->customer->email ?? '' }}</div>
            @if($quote->customer->phone)
                <div>Tel: {{ $quote->customer->phone }}</div>
            @endif
        </td>
        <td class="details-column">
            <div class="section-label">Quote Details</div>
            <table style="width: 100%;">
                <tr>
                    <td style="color: #6b7280;">Date Issued:</td>
                    <td class="text-right">{{ $quote->quote_date->format('M d, Y') }}</td>
                </tr>
                <tr>
                    <td style="color: #6b7280;">Valid Until:</td>
                    <td class="text-right">
                        {{ $quote->valid_until ? $quote->valid_until->format('M d, Y') : 'N/A' }}
                    </td>
                </tr>
                @if($quote->reference_no)
                    <tr>
                        <td style="color: #6b7280;">Reference:</td>
                        <td class="text-right">{{ $quote->reference_no }}</td>
                    </tr>
                @endif
            </table>
        </td>
    </tr>
</table>

<table class="items-table">
    <thead>
    <tr>
        <th style="width: 45%;">Item Description</th>
        <th class="text-center">Qty</th>
        <th class="text-right">Unit Price</th>
        <th class="text-right">Discount</th>
        <th class="text-right">Line Total</th>
    </tr>
    </thead>
    <tbody>
    @foreach($items as $line)
        <tr>
            <td>
                <div class="font-bold">{{ $line->item->name }}</div>
                @if($line->item->description)
                    <div style="font-size: 10px; color: #6b7280; margin-top: 2px;">{{ $line->item->description }}</div>
                @endif
            </td>
            <td class="text-center">{{ number_format($line->quantity, 0) }}</td>
            <td class="text-right">{{ number_format($line->unit_price, 2) }}</td>
            <td class="text-right" style="color: #dc2626;">
                {{ $line->discount > 0 ? '-' . number_format($line->discount, 2) : '0.00' }}
            </td>
            <td class="text-right font-bold">{{ number_format($line->line_total, 2) }}</td>
        </tr>
    @endforeach
    </tbody>
</table>

<div class="summary-wrapper">
    <table class="summary-table">
        <tr>
            <td style="color: #6b7280;">Subtotal</td>
            <td class="text-right">{{ number_format($items->sum(fn($i) => $i->quantity * $i->unit_price), 2) }}</td>
        </tr>
        @if($items->sum('discount') > 0)
            <tr>
                <td style="color: #6b7280;">Total Discount</td>
                <td class="text-right" style="color: #dc2626;">-{{ number_format($items->sum('discount'), 2) }}</td>
            </tr>
        @endif
        <tr class="total-row">
            <td>Total Amount</td>
            <td class="text-right">{{ number_format($totalAmount, 2) }}</td>
        </tr>
    </table>
</div>

<div class="footer-notes">
    <div class="section-label">Terms & Conditions</div>
    <div style="margin-top: 5px;">
        1. This quote is valid until the date indicated above.<br>
        2. Payment terms: 50% upfront, 50% upon delivery unless otherwise agreed.<br>
        3. Please reference quote <strong>#{{ $quote->quote_no }}</strong> when accepting this offer.<br>
        4. Goods supplied remain property of {{ $company['name'] ?? config('app.name') }} until full payment is received.
    </div>
</div>

</body>
</html>
