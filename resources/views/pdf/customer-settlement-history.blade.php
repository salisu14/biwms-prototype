<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Customer Settlement History</title>
    <style>
        @page { margin: 20px 22px 28px; }
        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 10px;
            color: #111827;
            margin: 0;
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
        }
        .filters strong {
            display: inline-block;
            min-width: 90px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            border: 1px solid #d1d5db;
            padding: 6px 7px;
            vertical-align: top;
        }
        thead th {
            background: #f3f4f6;
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: .04em;
            text-align: left;
        }
        .right { text-align: right; white-space: nowrap; }
        .muted { color: #6b7280; font-size: 9px; }
        .nowrap { white-space: nowrap; }
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
            padding: 8px 22px 0;
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

<table>
    <thead>
        <tr>
            <th>Date</th>
            <th>Customer</th>
            <th>Type</th>
            <th>Source</th>
            <th>Original Invoice</th>
            <th>Settlement Target</th>
            <th class="right">Amount</th>
            <th>Currency</th>
            <th class="right">Source Before</th>
            <th class="right">Source After</th>
            <th class="right">Target Before</th>
            <th class="right">Target After</th>
            <th>Applied By</th>
            <th>Trace</th>
        </tr>
    </thead>
    <tbody>
        @forelse($rows as $row)
            <tr>
                <td class="nowrap">{{ optional($row->application_date)->format('Y-m-d H:i') ?: $row->application_date }}</td>
                <td>{{ trim(($row->customer_number ? $row->customer_number.' - ' : '').($row->customer_name ?? 'Unknown Customer')) }}</td>
                <td>{{ $row->settlement_type === 'PAYMENT_APPLICATION' ? 'Payment' : 'Sales Credit Memo' }}</td>
                <td>
                    <div class="nowrap">{{ $row->source_document_number ?? '—' }}</div>
                    <div class="muted">{{ str_replace('_', ' ', $row->source_document_type) }}</div>
                </td>
                <td>{{ $row->original_invoice_number ?? '—' }}</td>
                <td>
                    <div class="nowrap">{{ $row->target_document_number ?? '—' }}</div>
                    <div class="muted">{{ str_replace('_', ' ', $row->target_document_type) }}</div>
                </td>
                <td class="right">{{ number_format((float) $row->amount_applied, 2) }}</td>
                <td>{{ $row->currency_code ?? '—' }}</td>
                <td class="right">{{ $row->source_remaining_before === null ? '—' : number_format((float) $row->source_remaining_before, 2) }}</td>
                <td class="right">{{ $row->source_remaining_after === null ? '—' : number_format((float) $row->source_remaining_after, 2) }}</td>
                <td class="right">{{ $row->target_remaining_before === null ? '—' : number_format((float) $row->target_remaining_before, 2) }}</td>
                <td class="right">{{ $row->target_remaining_after === null ? '—' : number_format((float) $row->target_remaining_after, 2) }}</td>
                <td>{{ $row->applied_by_name ?? '—' }}</td>
                <td>
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
