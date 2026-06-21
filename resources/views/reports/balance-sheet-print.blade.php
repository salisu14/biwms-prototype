<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Balance Sheet Print</title>
    <style>
        @page { size: A4 portrait; margin: 12mm; }
        body { font-family: Arial, sans-serif; color: #000; background: #fff; margin: 0; }
        h1 { margin: 0 0 6px; font-size: 20px; }
        p { margin: 0 0 12px; font-size: 12px; color: #333; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; font-size: 12px; }
        colgroup col:first-child { width: 70%; }
        colgroup col:nth-child(2) { width: 30%; }
        th, td { padding: 8px 10px; border: 1px solid #cfcfcf; }
        th { text-align: left; background: #f3f3f3; }
        .num { text-align: right; white-space: nowrap; }
        .bold { font-weight: 700; }
    </style>
</head>
<body onload="window.print()">
<div style="margin-bottom: 10px; font-size: 12px;">
    <strong>{{ $company['name'] ?? config('app.name') }}</strong>
    @if(!empty($company['address_lines']))
        <div>{{ implode(', ', $company['address_lines']) }}</div>
    @endif
</div>
<h1>Balance Sheet</h1>
<p>As of {{ $reportData['as_of_date'] ?? now()->toDateString() }}</p>

<table>
    <colgroup><col><col></colgroup>
    <thead>
    <tr>
        <th>Account</th>
        <th class="num">Amount</th>
    </tr>
    </thead>
    <tbody>
    @foreach($reportData['lines'] as $line)
        <tr class="{{ !empty($line['bold']) ? 'bold' : '' }}">
            <td style="padding-left: {{ 10 + ((int) ($line['indentation'] ?? 0) * 16) }}px;">
                {{ $line['account_no'] }} - {{ $line['description'] }}
            </td>
            <td class="num">NGN {{ number_format((float) ($line['amount'] ?? 0), 2) }}</td>
        </tr>
    @endforeach
    </tbody>
    <tfoot>
    <tr class="bold"><td>Total Assets</td><td class="num">NGN {{ number_format((float) $reportData['totals']['assets'], 2) }}</td></tr>
    <tr class="bold"><td>Total Liabilities</td><td class="num">NGN {{ number_format((float) $reportData['totals']['liabilities'], 2) }}</td></tr>
    <tr class="bold"><td>Total Equity</td><td class="num">NGN {{ number_format((float) $reportData['totals']['equity'], 2) }}</td></tr>
    <tr class="bold"><td>Total Liabilities + Equity</td><td class="num">NGN {{ number_format((float) $reportData['totals']['liabilities_and_equity'], 2) }}</td></tr>
    <tr class="bold"><td>Balance Difference</td><td class="num">NGN {{ number_format((float) $reportData['totals']['difference'], 2) }}</td></tr>
    </tfoot>
</table>
</body>
</html>
