<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Customer Settlement History</title>
    <style>
        @page { margin: 14mm 12mm 18mm; size: A4 landscape; }
        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 9px;
            color: #111827;
            margin: 0;
            line-height: 1.35;
        }
        .header {
            margin-bottom: 12px;
            padding-bottom: 10px;
            border-bottom: 1px solid #d1d5db;
        }
        .title {
            font-size: 18px;
            font-weight: 700;
            margin: 0 0 4px;
        }
        .meta {
            font-size: 9px;
            color: #4b5563;
            line-height: 1.5;
        }
        .filters {
            margin: 10px 0 14px;
            padding: 8px 10px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            background: #f9fafb;
            page-break-inside: avoid;
        }
        .filters strong {
            display: inline-block;
            min-width: 90px;
        }
        .report-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        thead {
            display: table-header-group;
        }
        tbody tr {
            page-break-inside: avoid;
        }
        th, td {
            border: 1px solid #d1d5db;
            padding: 4px 5px;
            vertical-align: top;
            overflow-wrap: break-word;
            word-break: normal;
        }
        thead th {
            background: #f3f4f6;
            font-size: 7.5px;
            text-transform: uppercase;
            letter-spacing: .01em;
            text-align: left;
            page-break-inside: avoid;
            line-height: 1.15;
            white-space: normal;
            word-break: break-word;
            overflow-wrap: break-word;
        }
        .header-stack {
            display: block;
            white-space: normal;
            line-height: 1.1;
        }
        .header-stack .line {
            display: block;
        }
        .right { text-align: right; white-space: nowrap; }
        .muted { color: #6b7280; font-size: 8px; }
        .nowrap { white-space: nowrap; }
        .wrap {
            white-space: normal;
            word-wrap: break-word;
            overflow-wrap: break-word;
            word-break: break-word;
        }
        .col-date { width: 5%; }
        .col-customer { width: 11%; }
        .col-type { width: 6%; }
        .col-source { width: 9%; }
        .col-original { width: 10%; }
        .col-target { width: 10%; }
        .col-amount { width: 7%; }
        .col-currency { width: 4%; }
        .col-source-before,
        .col-source-after,
        .col-target-before,
        .col-target-after { width: 5.5%; }
        .col-applied { width: 7%; }
        .col-trace { width: 9%; }
        .empty {
            text-align: center;
            color: #6b7280;
            padding: 18px 8px;
        }
        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            padding: 6px 12mm 0;
            border-top: 1px solid #d1d5db;
            font-size: 8px;
            color: #6b7280;
        }
        .page-number:after {
            content: counter(page);
        }
    </style>
</head>
<body>
@php
    $filterLabels = [
        'customer_id' => 'Customer',
        'settlement_type' => 'Settlement Type',
        'source_document_number' => 'Source Document',
        'target_document_number' => 'Target Invoice',
        'date_from' => 'Date From',
        'date_to' => 'Date To',
        'currency_code' => 'Currency',
        'business_id' => 'Business',
    ];
@endphp

<div class="header">
    <div class="title">Customer Settlement History</div>
    <div class="meta">
        Generated {{ optional($generatedAt)->format('Y-m-d H:i') ?? now()->format('Y-m-d H:i') }} |
        {{ config('app.name', 'BIWMS') }}
    </div>
</div>

<div class="filters">
    @forelse($filterLabels as $key => $label)
        @if(filled($filters[$key] ?? null))
            <div><strong>{{ $label }}:</strong> {{ $filters[$key] }}</div>
        @endif
    @empty
    @endforelse
    @if(empty(array_filter($filters ?? [], fn ($value) => filled($value))))
        <div class="muted">No filters applied.</div>
    @endif
</div>

<table class="report-table">
    <thead>
        <tr>
            <th class="col-date"><span class="header-stack"><span class="line">Date</span></span></th>
            <th class="col-customer"><span class="header-stack"><span class="line">Customer</span></span></th>
            <th class="col-type"><span class="header-stack"><span class="line">Type</span></span></th>
            <th class="col-source"><span class="header-stack"><span class="line">Source</span></span></th>
            <th class="col-original"><span class="header-stack"><span class="line">Original</span><span class="line">Invoice</span></span></th>
            <th class="col-target"><span class="header-stack"><span class="line">Settlement</span><span class="line">Target</span></span></th>
            <th class="col-amount right"><span class="header-stack"><span class="line">Amount</span></span></th>
            <th class="col-currency"><span class="header-stack"><span class="line">Currency</span></span></th>
            <th class="col-source-before right"><span class="header-stack"><span class="line">Source</span><span class="line">Before</span></span></th>
            <th class="col-source-after right"><span class="header-stack"><span class="line">Source</span><span class="line">After</span></span></th>
            <th class="col-target-before right"><span class="header-stack"><span class="line">Target</span><span class="line">Before</span></span></th>
            <th class="col-target-after right"><span class="header-stack"><span class="line">Target</span><span class="line">After</span></span></th>
            <th class="col-applied"><span class="header-stack"><span class="line">Applied</span><span class="line">By</span></span></th>
            <th class="col-trace"><span class="header-stack"><span class="line">Trace</span></span></th>
        </tr>
    </thead>
    <tbody>
        @forelse($rows as $row)
            <tr>
                <td class="wrap">{{ optional($row->application_date)->format('Y-m-d H:i') ?: $row->application_date }}</td>
                <td class="wrap">{{ trim(($row->customer_number ? $row->customer_number.' - ' : '').($row->customer_name ?? 'Unknown Customer')) }}</td>
                <td class="wrap">{{ $row->settlement_type === 'PAYMENT_APPLICATION' ? 'Payment' : 'Sales Credit Memo' }}</td>
                <td class="wrap">
                    <div class="wrap">{{ $row->source_document_number ?? '—' }}</div>
                    <div class="muted">{{ str_replace('_', ' ', $row->source_document_type) }}</div>
                </td>
                <td class="wrap">{{ $row->original_invoice_number ?? '—' }}</td>
                <td class="wrap">
                    <div class="wrap">{{ $row->target_document_number ?? '—' }}</div>
                    <div class="muted">{{ str_replace('_', ' ', $row->target_document_type) }}</div>
                </td>
                <td class="right nowrap">{{ number_format((float) $row->amount_applied, 2) }}</td>
                <td class="nowrap">{{ $row->currency_code ?? '—' }}</td>
                <td class="right nowrap">{{ $row->source_remaining_before === null ? '—' : number_format((float) $row->source_remaining_before, 2) }}</td>
                <td class="right nowrap">{{ $row->source_remaining_after === null ? '—' : number_format((float) $row->source_remaining_after, 2) }}</td>
                <td class="right nowrap">{{ $row->target_remaining_before === null ? '—' : number_format((float) $row->target_remaining_before, 2) }}</td>
                <td class="right nowrap">{{ $row->target_remaining_after === null ? '—' : number_format((float) $row->target_remaining_after, 2) }}</td>
                <td class="wrap">{{ $row->applied_by_name ?? '—' }}</td>
                <td class="wrap">
                    <div>#{{ $row->settlement_id }}</div>
                    <div class="muted">{{ $row->reference_key ?? '—' }}</div>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="14" class="empty">No settlement applications match the selected filters.</td>
            </tr>
        @endforelse
    </tbody>
</table>

<div class="footer">
    <span>{{ config('app.name', 'BIWMS') }}</span>
    <span style="float:right;">Page <span class="page-number"></span></span>
</div>
</body>
</html>
