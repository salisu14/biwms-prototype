<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $title }} - {{ $order_number }}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 0;
            font-family: 'Helvetica Neue', Arial, sans-serif;
            color: #1f2937;
            font-size: 12px;
            line-height: 1.45;
            background: #fff;
        }
        .page {
            padding: 10mm 14mm 16mm;
        }
        .muted { color: #6b7280; }

        .top {
            width: 100%;
            border-bottom: 2px solid #111827;
            padding-bottom: 12px;
            margin-bottom: 14px;
        }
        .left { float: left; width: 62%; }
        .right { float: right; width: 36%; }
        .clearfix::after { content: ""; display: table; clear: both; }

        .brand-row { display: table; width: 100%; }
        .logo-wrap { display: table-cell; width: 96px; vertical-align: top; text-align: right; }
        .logo {
            width: 86px;
            height: 86px;
            object-fit: contain;
            border-radius: 0;
        }
        .logo-fallback {
            width: 86px;
            height: 86px;
            border: 1.5px solid #111827;
            border-radius: 4px;
            text-align: center;
            font-size: 11px;
            font-weight: 700;
            padding-top: 28px;
            line-height: 1.2;
        }
        .brand-copy { display: table-cell; vertical-align: top; padding-right: 12px; }
        .brand-name {
            font-size: 22px;
            font-weight: 800;
            letter-spacing: .2px;
            color: #15803d;
            line-height: 1.15;
            margin-bottom: 2px;
            text-transform: uppercase;
        }
        .tagline {
            font-style: italic;
            font-size: 11px;
            color: #6b7280;
            margin-bottom: 8px;
        }
        .company-meta { font-size: 11px; }

        .right-logo {
            text-align: right;
            margin-bottom: 6px;
            min-height: 86px;
            padding-top: 2px;
        }
        .doc-meta {
            text-align: right;
            padding-top: 2px;
        }
        .doc-title {
            font-size: 27px;
            font-weight: 800;
            letter-spacing: 2px;
            color: #111827;
            margin: 0 0 8px 0;
            line-height: 1.1;
        }
        .doc-number {
            font-size: 18px;
            font-weight: 800;
            color: #111827;
            margin: 0 0 8px 0;
            line-height: 1.15;
        }
        .doc-line {
            font-size: 12px;
            margin: 3px 0;
            color: #374151;
            line-height: 1.3;
        }
        .doc-line .k {
            font-weight: 700;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: .6px;
            margin-right: 6px;
        }
        .doc-line .v {
            font-weight: 700;
            color: #111827;
        }

        .parties {
            width: 100%;
            margin: 10px 0 14px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            border-collapse: separate;
            border-spacing: 0;
            overflow: hidden;
        }
        .parties td {
            width: 50%;
            vertical-align: top;
            padding: 9px 10px;
        }
        .parties td + td {
            border-left: 1px solid #d1d5db;
        }
        .party-label {
            font-size: 10px;
            font-weight: 700;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: .8px;
            margin-bottom: 5px;
        }
        .party-name {
            font-size: 12px;
            font-weight: 700;
            color: #111827;
            margin-bottom: 2px;
            text-transform: uppercase;
        }
        .party-addr {
            font-size: 11px;
            color: #374151;
            white-space: pre-line;
        }

        .items {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            overflow: hidden;
            margin-bottom: 14px;
        }
        .items thead th {
            background: #111827;
            color: #fff;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: .7px;
            font-weight: 700;
            padding: 9px 8px;
            border: none;
            text-align: left;
        }
        .items thead th + th {
            border-left: 1px solid rgba(255, 255, 255, 0.18);
        }
        .items thead th.r,
        .items tbody td.r { text-align: right; }
        .items thead th.c,
        .items tbody td.c { text-align: center; }
        .items tbody td {
            border: none;
            border-bottom: 1px solid #eef2f7;
            padding: 10px 8px;
            vertical-align: top;
            font-size: 11.5px;
        }
        .items tbody tr:last-child td {
            border-bottom: none;
        }
        .items tbody tr:nth-child(even) { background: #f9fafb; }
        .desc { font-weight: 600; color: #111827; }
        .sub { display: block; color: #6b7280; font-size: 10px; margin-top: 2px; }

        .bottom { width: 100%; margin-top: 10px; }
        .note {
            float: left;
            width: 52%;
            font-size: 10.5px;
            color: #6b7280;
            line-height: 1.55;
        }
        .totals {
            float: right;
            width: 42%;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            overflow: hidden;
        }
        .totals .row {
            display: table;
            width: 100%;
            table-layout: fixed;
            padding: 8px 10px;
            border-bottom: 1px solid #e5e7eb;
        }
        .totals .row:last-child { border-bottom: none; }
        .totals .k {
            display: table-cell;
            width: 52%;
            font-size: 10px;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: .7px;
            white-space: nowrap;
        }
        .totals .v {
            display: table-cell;
            width: 48%;
            text-align: right;
            font-weight: 700;
            color: #111827;
            white-space: nowrap;
            padding-left: 10px;
        }
        .totals .grand {
            background: #111827;
        }
        .totals .grand .k,
        .totals .grand .v {
            color: #fff;
            font-size: 12px;
            font-weight: 800;
        }

        .footer {
            margin-top: 18px;
            border-top: 1px solid #e5e7eb;
            padding-top: 8px;
            text-align: center;
            font-size: 10px;
            color: #6b7280;
        }
    </style>
</head>
<body>
@php
    $logoSrc = $company['logo_data_uri'] ?? $company['logo_url'] ?? null;
    $displayName = $company['trading_name'] ?? $company['name'] ?? 'BIFLI GLOBAL RESOURCES LTD.';
    $amountPaid = (float) ($totals['amount_paid'] ?? 0);
    $balanceDue = (float) ($totals['balance_due'] ?? (($totals['grand_total'] ?? 0) - $amountPaid));
@endphp
<div class="page">
    <div class="top clearfix">
        <div class="left">
            <div class="brand-row">
                <div class="brand-copy">
                    <div class="brand-name">{{ $displayName }}</div>
                    <div class="tagline">A choice for better living</div>
                    <div class="company-meta">
                        @if(!empty($company['address_lines']))
                            {{ implode(', ', $company['address_lines']) }}<br>
                        @elseif(!empty($company['address']))
                            {{ $company['address'] }}<br>
                        @endif
                        {{ $company['phone'] ?? '' }} @if(!empty($company['phone']) && !empty($company['email']))|@endif {{ $company['email'] ?? '' }}
                    </div>
                </div>
                <div class="logo-wrap"></div>
            </div>
        </div>

        <div class="right">
            <div class="right-logo">
                @if($logoSrc)
                    <img src="{{ $logoSrc }}" alt="Company Logo" class="logo">
                @else
                    <div class="logo-fallback" style="display:inline-block;">BIFLI<br>GLOBAL</div>
                @endif
            </div>
            <div class="doc-meta">
                <p class="doc-title">PROFORMA INVOICE</p>
                <p class="doc-number">{{ $order_number }}</p>
                <p class="doc-line"><span class="k">Date:</span><span class="v">{{ $date }}</span></p>
                <p class="doc-line"><span class="k">Currency:</span><span class="v">{{ $currency }}</span></p>
                <p class="doc-line"><span class="k">Document:</span><span class="v">{{ $title }}</span></p>
                <p class="doc-line"><span class="k">Type:</span><span class="v">{{ $type ?? 'Sales' }}</span></p>
            </div>
        </div>
    </div>

    <table class="parties">
        <tr>
            <td>
            <div class="party-label">Seller</div>
            <div class="party-name">{{ $displayName }}</div>
            <div class="party-addr">@if(!empty($company['address_lines'])){{ implode(', ', $company['address_lines']) }}@else{{ $company['address'] ?? '' }}@endif</div>
            </td>
            <td>
            <div class="party-label">{{ $client_label }}</div>
            <div class="party-name">{{ $client_name }}</div>
            <div class="party-addr">{!! nl2br(e($client_address)) !!}</div>
            </td>
        </tr>
    </table>

    <table class="items">
        <thead>
        <tr>
            <th class="c" style="width:8%;">#</th>
            <th style="width:44%;">Description</th>
            <th class="c" style="width:16%;">Quantity</th>
            <th class="r" style="width:16%;">Unit Price</th>
            <th class="r" style="width:16%;">Amount</th>
        </tr>
        </thead>
        <tbody>
        @forelse($lines as $index => $line)
            <tr>
                <td class="c">{{ $index + 1 }}</td>
                <td>
                    <span class="desc">{{ $line['description'] }}</span>
                    @if(!empty($line['item_code']))<span class="sub">Item: {{ $line['item_code'] }}</span>@endif
                </td>
                <td class="c">{{ number_format((float) $line['qty'], 2) }} {{ $line['uom'] }}</td>
                <td class="r">{{ number_format((float) $line['price'], 2) }}</td>
                <td class="r">{{ number_format((float) $line['net_amount'], 2) }}</td>
            </tr>
        @empty
            <tr>
                <td class="c">1</td>
                <td><span class="desc">No line items</span></td>
                <td class="c">0.00</td>
                <td class="r">0.00</td>
                <td class="r">0.00</td>
            </tr>
        @endforelse
        </tbody>
    </table>

    <div class="bottom clearfix">
        <div class="note">
            <strong>Note:</strong> This proforma is issued for review and confirmation.
            @if(!empty($company['invoice_footer']))
                <br>{{ $company['invoice_footer'] }}
            @endif
        </div>
        <div class="totals">
            <div class="row"><span class="k">Subtotal</span><span class="v">{{ $currency }} {{ number_format((float) $totals['subtotal'], 2) }}</span></div>
            <div class="row"><span class="k">Discount</span><span class="v">{{ $currency }} {{ number_format((float) $totals['discount'], 2) }}</span></div>
            <div class="row"><span class="k">VAT</span><span class="v">{{ $currency }} {{ number_format((float) $totals['vat'], 2) }}</span></div>
            <div class="row grand"><span class="k">Grand Total</span><span class="v">{{ $currency }} {{ number_format((float) $totals['grand_total'], 2) }}</span></div>
            <div class="row"><span class="k">Amount Paid</span><span class="v">{{ $currency }} {{ number_format($amountPaid, 2) }}</span></div>
            <div class="row"><span class="k">Balance Due</span><span class="v">{{ $currency }} {{ number_format($balanceDue, 2) }}</span></div>
        </div>
    </div>

    <div class="footer">
        {{ $company['name'] ?? config('app.name') }}
    </div>
</div>
</body>
</html>
