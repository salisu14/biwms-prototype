<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Purchase Three-Way Match</title>
    <style>
        @page { margin: 14mm 10mm 16mm; size: A4 landscape; }
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 8.5px; color: #111827; margin: 0; line-height: 1.35; }
        .header { margin-bottom: 10px; padding-bottom: 8px; border-bottom: 1px solid #d1d5db; }
        .title { font-size: 16px; font-weight: 700; margin: 0 0 3px; }
        .meta { font-size: 8.5px; color: #4b5563; }
        .company-header { margin-bottom: 10px; padding-bottom: 8px; border-bottom: 1px solid #d1d5db; }
        .company-logo { margin-bottom: 4px; }
        .company-logo img { max-height: 42px; max-width: 180px; }
        .company-name { font-size: 16px; font-weight: 700; margin: 0 0 2px; }
        .company-trading { font-size: 9px; color: #374151; margin-bottom: 2px; }
        .company-meta { font-size: 8px; color: #4b5563; line-height: 1.4; }
        .company-meta .divider { padding: 0 4px; }
        .summary { margin: 8px 0 10px; padding: 8px 10px; border: 1px solid #d1d5db; border-radius: 6px; background: #f9fafb; }
        .summary strong { display: inline-block; min-width: 120px; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        thead { display: table-header-group; }
        tbody tr { page-break-inside: avoid; }
        th, td { border: 1px solid #d1d5db; padding: 4px 5px; vertical-align: top; overflow-wrap: break-word; word-break: normal; }
        thead th { background: #f3f4f6; font-size: 7px; text-transform: uppercase; letter-spacing: .01em; text-align: left; line-height: 1.12; white-space: normal; word-break: break-word; overflow-wrap: break-word; }
        .right { text-align: right; white-space: nowrap; }
        .wrap { white-space: normal; word-wrap: break-word; overflow-wrap: break-word; word-break: break-word; }
        .status { font-weight: 700; }
        .status-matched { color: #047857; }
        .status-warning { color: #b45309; }
        .status-critical { color: #b91c1c; }
        .empty { text-align: center; color: #6b7280; padding: 20px 8px; }
    </style>
</head>
<body>
<div class="company-header">
    @include('pdf.partials.company-header', ['company' => $company ?? []])
    <div class="title">{{ $report['title'] }}</div>
    <div class="meta">Generated {{ now()->format('Y-m-d H:i') }}</div>
</div>

<div class="summary">
    <div><strong>Total Rows:</strong> {{ number_format((int) $report['summary']['total_rows']) }}</div>
    <div><strong>Matched:</strong> {{ number_format((int) $report['summary']['matched_count']) }}</div>
    <div><strong>Exceptions:</strong> {{ number_format((int) $report['summary']['exception_count']) }}</div>
    <div><strong>Ordered Value:</strong> NGN {{ number_format((float) $report['summary']['ordered_value'], 2) }}</div>
    <div><strong>Received Value:</strong> NGN {{ number_format((float) $report['summary']['received_value'], 2) }}</div>
    <div><strong>Invoiced Value:</strong> NGN {{ number_format((float) $report['summary']['invoiced_value'], 2) }}</div>
</div>

<table>
    <thead>
    <tr>
        <th style="width: 10%">Reference</th>
        <th style="width: 10%">Vendor</th>
        <th style="width: 9%">Item</th>
        <th style="width: 6%">UOM</th>
        <th class="right" style="width: 6%">Ordered</th>
        <th class="right" style="width: 6%">Received</th>
        <th class="right" style="width: 6%">Invoiced</th>
        <th class="right" style="width: 6%">Remain Rec.</th>
        <th class="right" style="width: 6%">Remain Inv.</th>
        <th class="right" style="width: 7%">PO Cost</th>
        <th class="right" style="width: 7%">Invoice Cost</th>
        <th class="right" style="width: 7%">Amount Var.</th>
        <th style="width: 11%">Status</th>
        <th style="width: 10%">Receipts / Invoices</th>
    </tr>
    </thead>
    <tbody>
    @forelse($report['rows'] as $row)
        <tr>
            <td class="wrap">
                <div class="status">{{ $row->reference_number ?? '—' }}</div>
                <div>{{ $row->reference_type }}</div>
            </td>
            <td class="wrap">{{ trim(($row->vendor_number ? $row->vendor_number.' - ' : '').($row->vendor_name ?? '—')) }}</td>
            <td class="wrap">
                <div class="status">{{ $row->item_code ?? '—' }}</div>
                <div>{{ $row->description ?? '—' }}</div>
            </td>
            <td class="wrap">{{ $row->unit_of_measure_code ?? '—' }}</td>
            <td class="right">{{ $row->ordered_quantity === null ? '—' : number_format((float) $row->ordered_quantity, 4) }}</td>
            <td class="right">{{ $row->received_quantity === null ? '—' : number_format((float) $row->received_quantity, 4) }}</td>
            <td class="right">{{ $row->invoiced_quantity === null ? '—' : number_format((float) $row->invoiced_quantity, 4) }}</td>
            <td class="right">{{ $row->remaining_to_receive === null ? '—' : number_format((float) $row->remaining_to_receive, 4) }}</td>
            <td class="right">{{ $row->remaining_to_invoice === null ? '—' : number_format((float) $row->remaining_to_invoice, 4) }}</td>
            <td class="right">{{ $row->po_unit_cost === null ? '—' : number_format((float) $row->po_unit_cost, 2) }}</td>
            <td class="right">{{ $row->invoice_unit_cost === null ? '—' : number_format((float) $row->invoice_unit_cost, 2) }}</td>
            <td class="right">{{ $row->amount_variance === null ? '—' : number_format((float) $row->amount_variance, 2) }}</td>
            <td class="wrap">
                <span class="status @if($row->match_status === 'Matched') status-matched @elseif(in_array($row->match_status, ['Partially Received', 'Partially Invoiced'], true)) status-warning @else status-critical @endif">
                    {{ $row->match_status }}
                </span>
            </td>
            <td class="wrap">
                <div>{{ $row->receipt_document_number ?: '—' }}</div>
                <div>{{ $row->invoice_document_number ?: '—' }}</div>
            </td>
        </tr>
    @empty
        <tr><td colspan="14" class="empty">No purchase lines match the selected filters.</td></tr>
    @endforelse
    </tbody>
</table>
</body>
</html>
