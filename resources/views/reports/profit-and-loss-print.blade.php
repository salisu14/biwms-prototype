<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Profit and Loss Print</title>
    <style>
        @page { size: A4 portrait; margin: 10mm; }
        body { font-family: Arial, sans-serif; color: #000; background: #fff; margin: 0; }
        h1 { margin: 0 0 4px; font-size: 20px; }
        p { margin: 0 0 10px; font-size: 12px; color: #333; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; font-size: 11px; }
        th, td { padding: 6px 8px; border: 1px solid #cfcfcf; }
        th { text-align: left; background: #f3f3f3; }
        .num { text-align: right; white-space: nowrap; }
        .bold { font-weight: 700; }
    </style>
</head>
<body onload="window.print()">
<h1>{{ $reportData['report_name'] ?? 'Profit & Loss Statement' }}</h1>
<p>Period: {{ $reportData['period'] ?? '' }}</p>

<table>
    <thead>
    <tr>
        <th style="width: 44%">G/L Description</th>
        <th class="num" style="width: 18%">Current Amount</th>
        @if(isset($reportData['compare_period']))
            <th class="num" style="width: 18%">Prior Period</th>
            <th class="num" style="width: 20%">Variance</th>
        @endif
    </tr>
    </thead>
    <tbody>
    @foreach($reportData['lines'] as $line)
        <tr class="{{ !empty($line['bold']) ? 'bold' : '' }}">
            <td style="padding-left: {{ 8 + ((int) ($line['indentation'] ?? 0) * 14) }}px;">{{ $line['description'] }}</td>
            <td class="num">NGN {{ $line['amount'] ?? '0.00' }}</td>
            @if(isset($reportData['compare_period']))
                <td class="num">NGN {{ $line['compare_amount'] ?? '0.00' }}</td>
                <td class="num">{{ $line['variance_percent'] ?? '—' }}</td>
            @endif
        </tr>
    @endforeach
    </tbody>
    <tfoot>
    <tr class="bold">
        <td>Net Income / (Loss)</td>
        <td class="num">NGN {{ $reportData['totals']['net_income'] ?? '0.00' }}</td>
        @if(isset($reportData['compare_period']))
            <td class="num">NGN {{ $reportData['totals']['compare_net_income'] ?? '0.00' }}</td>
            <td></td>
        @endif
    </tr>
    </tfoot>
</table>
</body>
</html>

