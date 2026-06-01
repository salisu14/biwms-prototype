<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Expense Report Print</title>
    <style>
        @page { size: A4 portrait; margin: 12mm; }
        body { font-family: Arial, sans-serif; color: #000; background: #fff; margin: 0; }
        h1 { margin: 0 0 6px; font-size: 20px; }
        p { margin: 0 0 12px; font-size: 12px; color: #333; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; font-size: 12px; }
        th, td { padding: 8px 10px; border: 1px solid #cfcfcf; }
        th { text-align: left; background: #f3f3f3; }
        .num { text-align: right; white-space: nowrap; }
    </style>
</head>
<body onload="window.print()">
<div style="margin-bottom: 10px; font-size: 12px;">
    <strong>{{ $company['name'] ?? config('app.name') }}</strong>
    @if(!empty($company['address_lines']))
        <div>{{ implode(', ', $company['address_lines']) }}</div>
    @endif
</div>

<div>
    <h1>Expense Report</h1>
    <p>Period: {{ ucfirst($report['period']['mode']) }} ({{ $report['period']['start'] }} - {{ $report['period']['end'] }})</p>
    <p>Total Amount: NGN {{ number_format((float) $report['summary']['total_amount'], 2) }} | Total VAT: NGN {{ number_format((float) $report['summary']['total_vat'], 2) }} | Count: {{ $report['summary']['count'] }}</p>
</div>

<table>
    <thead>
    <tr>
        <th>Document No</th>
        <th>Posting Date</th>
        <th>Category</th>
        <th>Type</th>
        <th class="num">Amount</th>
        <th class="num">VAT</th>
        <th>Status</th>
    </tr>
    </thead>
    <tbody>
    @forelse($report['rows'] as $row)
        <tr>
            <td>{{ $row->document_no }}</td>
            <td>{{ optional($row->posting_date)->toDateString() }}</td>
            <td>{{ $row->category_code }}</td>
            <td>{{ $row->expense_type }}</td>
            <td class="num">{{ number_format((float) $row->amount, 2) }}</td>
            <td class="num">{{ number_format((float) $row->vat_amount, 2) }}</td>
            <td>{{ $row->status }}</td>
        </tr>
    @empty
        <tr><td colspan="7">No posted expenses for selected period.</td></tr>
    @endforelse
    </tbody>
</table>
</body>
</html>
