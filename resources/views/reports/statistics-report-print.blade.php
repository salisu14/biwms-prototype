<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $report['title'] }} Print</title>
    <style>
        @page { size: A4 landscape; margin: 10mm; }
        body { font-family: Arial, sans-serif; color: #000; background: #fff; margin: 0; }
        h1 { margin: 0 0 4px; font-size: 20px; }
        p { margin: 0 0 10px; font-size: 12px; color: #333; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; font-size: 11px; }
        th, td { padding: 6px 8px; border: 1px solid #cfcfcf; }
        th { text-align: left; background: #f3f3f3; }
        .num { text-align: right; white-space: nowrap; }
        .company { margin-bottom: 10px; font-size: 12px; }
        .summary { margin-bottom: 12px; }
        .bold { font-weight: 700; }
    </style>
</head>
<body onload="window.print()">
<div class="company">
    <strong>{{ $company['name'] ?? config('app.name') }}</strong>
    @if(!empty($company['address_lines']))
        <div>{{ implode(', ', $company['address_lines']) }}</div>
    @endif
</div>

<h1>{{ $report['title'] }}</h1>
<p>
    Period: {{ $report['period']['date_from'] }} - {{ $report['period']['date_to'] }}
    @if(filled($report['period']['posting_group'] ?? null))
        | Posting Group: {{ $report['period']['posting_group'] }}
    @else
        | Posting Group: All
    @endif
</p>
<p class="summary">
    {{ $report['amount_label'] }}: NGN {{ number_format((float) $report['summary']['total_amount'], 2) }}
    | Transactions: {{ number_format($report['summary']['total_transactions']) }}
    | Average: NGN {{ number_format((float) $report['summary']['average_amount'], 2) }}
    | Groups: {{ number_format($report['summary']['groups_count']) }}
</p>

<table>
    <thead>
    <tr>
        <th style="width: 22%">Posting Group</th>
        <th class="num" style="width: 13%">{{ $report['amount_label'] }}</th>
        <th class="num" style="width: 10%">Transactions</th>
        <th class="num" style="width: 12%">Average</th>
        <th class="num" style="width: 12%">Maximum</th>
        <th class="num" style="width: 12%">Minimum</th>
        <th class="num" style="width: 9%">Accounts</th>
        <th class="num" style="width: 10%">% Total</th>
    </tr>
    </thead>
    <tbody>
    @forelse($report['rows'] as $row)
        <tr>
            <td>{{ $row['group_code'] }} - {{ $row['group_name'] }}</td>
            <td class="num">NGN {{ number_format((float) $row['amount'], 2) }}</td>
            <td class="num">{{ number_format($row['transaction_count']) }}</td>
            <td class="num">NGN {{ number_format((float) $row['average_amount'], 2) }}</td>
            <td class="num">NGN {{ number_format((float) $row['max_amount'], 2) }}</td>
            <td class="num">NGN {{ number_format((float) $row['min_amount'], 2) }}</td>
            <td class="num">{{ number_format($row['accounts_used']) }}</td>
            <td class="num">{{ number_format((float) $row['percentage_of_total'], 2) }}%</td>
        </tr>
    @empty
        <tr>
            <td colspan="8">No entries found for the selected filters.</td>
        </tr>
    @endforelse
    </tbody>
    @if(!empty($report['rows']))
        <tfoot>
        <tr class="bold">
            <td>Total</td>
            <td class="num">NGN {{ number_format((float) $report['summary']['total_amount'], 2) }}</td>
            <td class="num">{{ number_format($report['summary']['total_transactions']) }}</td>
            <td class="num">NGN {{ number_format((float) $report['summary']['average_amount'], 2) }}</td>
            <td colspan="3"></td>
            <td class="num">100.00%</td>
        </tr>
        </tfoot>
    @endif
</table>
</body>
</html>
