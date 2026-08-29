<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Vendor Settlement History</title>
    <style>
        @page { margin: 14mm 12mm 18mm; size: A4 landscape; }
        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 9px;
            color: #111827;
            margin: 0;
            line-height: 1.35;
        }
        .header { margin-bottom: 12px; padding-bottom: 10px; border-bottom: 1px solid #d1d5db; }
        .title { font-size: 18px; font-weight: 700; margin: 0 0 4px; }
        .meta { font-size: 9px; color: #4b5563; line-height: 1.5; }
        .company-header { margin-bottom: 12px; padding-bottom: 10px; border-bottom: 1px solid #d1d5db; }
        .company-logo { margin-bottom: 4px; }
        .company-logo img { max-height: 42px; max-width: 180px; }
        .company-name { font-size: 18px; font-weight: 700; margin: 0 0 2px; }
        .company-trading { font-size: 10px; color: #374151; margin-bottom: 2px; }
        .company-meta { font-size: 8.5px; color: #4b5563; line-height: 1.45; }
        .company-meta .divider { padding: 0 4px; }
        .filters { margin: 10px 0 14px; padding: 8px 10px; border: 1px solid #d1d5db; border-radius: 6px; background: #f9fafb; page-break-inside: avoid; }
        .filters strong { display: inline-block; min-width: 90px; }
        .report-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        thead { display: table-header-group; }
        tbody tr { page-break-inside: avoid; }
        th, td { border: 1px solid #d1d5db; padding: 4px 5px; vertical-align: top; overflow-wrap: break-word; word-break: normal; }
        thead th { background: #f3f4f6; font-size: 7.5px; text-transform: uppercase; letter-spacing: .01em; text-align: left; page-break-inside: avoid; line-height: 1.15; white-space: normal; word-break: break-word; overflow-wrap: break-word; }
        .header-stack { display: block; white-space: normal; line-height: 1.1; }
        .header-stack .line { display: block; }
        .right { text-align: right; white-space: nowrap; }
        .nowrap { white-space: nowrap; }
        .wrap { white-space: normal; word-wrap: break-word; overflow-wrap: break-word; word-break: break-word; }
        .badge { display: inline-block; padding: 2px 6px; border-radius: 999px; font-size: 8px; font-weight: 700; background: #eef2ff; color: #3730a3; }
        .empty { text-align: center; color: #6b7280; padding: 24px 8px; }
        .foot { margin-top: 8px; font-size: 8px; color: #6b7280; }
    </style>
</head>
<body>
<div class="company-header">
    @include('pdf.partials.company-header', ['company' => $company ?? []])
    <div class="title">Vendor Settlement History</div>
    <div class="meta">Generated {{ optional($generatedAt ?? null)->format('Y-m-d H:i') ?? now()->format('Y-m-d H:i') }}</div>
</div>

@if(!empty($filters))
    <div class="filters">
        @foreach($filters as $key => $value)
            <div><strong>{{ str_replace('_', ' ', ucfirst($key)) }}:</strong> {{ is_scalar($value) ? $value : json_encode($value) }}</div>
        @endforeach
    </div>
@endif

<table class="report-table">
    <thead>
    <tr>
        <th style="width: 7%"><span class="header-stack"><span class="line">Date</span></span></th>
        <th style="width: 10%"><span class="header-stack"><span class="line">Vendor</span></span></th>
        <th style="width: 8%"><span class="header-stack"><span class="line">Type</span></span></th>
        <th style="width: 8%"><span class="header-stack"><span class="line">Source</span></span></th>
        <th style="width: 9%"><span class="header-stack"><span class="line">Original</span><span class="line">Invoice</span></span></th>
        <th style="width: 9%"><span class="header-stack"><span class="line">Settlement</span><span class="line">Target</span></span></th>
        <th class="right" style="width: 7%"><span class="header-stack"><span class="line">Amount</span></span></th>
        <th style="width: 6%"><span class="header-stack"><span class="line">Currency</span></span></th>
        <th class="right" style="width: 7%"><span class="header-stack"><span class="line">Source</span><span class="line">Before</span></span></th>
        <th class="right" style="width: 7%"><span class="header-stack"><span class="line">Source</span><span class="line">After</span></span></th>
        <th class="right" style="width: 7%"><span class="header-stack"><span class="line">Target</span><span class="line">Before</span></span></th>
        <th class="right" style="width: 7%"><span class="header-stack"><span class="line">Target</span><span class="line">After</span></span></th>
        <th style="width: 8%"><span class="header-stack"><span class="line">Applied</span><span class="line">By</span></span></th>
        <th style="width: 10%"><span class="header-stack"><span class="line">Trace</span></span></th>
    </tr>
    </thead>
    <tbody>
    @forelse($rows as $row)
        <tr>
            <td class="nowrap">{{ optional($row->application_date)->format('Y-m-d H:i') ?: $row->application_date }}</td>
            <td class="wrap">{{ trim(($row->vendor_number ? $row->vendor_number.' - ' : '').($row->vendor_name ?? '—')) }}</td>
            <td class="wrap">{{ $row->settlement_type === 'PAYMENT_APPLICATION' ? 'Payment' : 'Purchase Credit Memo' }}</td>
            <td class="wrap">{{ $row->source_document_number ?? '—' }}</td>
            <td class="wrap">{{ $row->original_invoice_number ?? '—' }}</td>
            <td class="wrap">{{ $row->target_document_number ?? '—' }}</td>
            <td class="right nowrap">{{ number_format((float) $row->amount_applied, 2) }}</td>
            <td class="nowrap">{{ $row->currency_code ?? '—' }}</td>
            <td class="right nowrap">{{ $row->source_remaining_before === null ? '—' : number_format((float) $row->source_remaining_before, 2) }}</td>
            <td class="right nowrap">{{ $row->source_remaining_after === null ? '—' : number_format((float) $row->source_remaining_after, 2) }}</td>
            <td class="right nowrap">{{ $row->target_remaining_before === null ? '—' : number_format((float) $row->target_remaining_before, 2) }}</td>
            <td class="right nowrap">{{ $row->target_remaining_after === null ? '—' : number_format((float) $row->target_remaining_after, 2) }}</td>
            <td class="wrap">{{ $row->applied_by_name ?? '—' }}</td>
            <td class="wrap">
                <div>#{{ $row->settlement_id }}</div>
                <div>Ledger {{ $row->source_ledger_entry_id ?? '—' }} → {{ $row->target_ledger_entry_id ?? '—' }}</div>
            </td>
        </tr>
    @empty
        <tr><td colspan="14" class="empty">No settlement applications match the selected filters.</td></tr>
    @endforelse
    </tbody>
</table>

<div class="foot">
    Page rendered for vendor settlement history export only.
</div>
</body>
</html>
