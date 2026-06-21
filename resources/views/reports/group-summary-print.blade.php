<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Group Summary Print</title>
    <style>
        @page { size: A4 portrait; margin: 12mm; }
        body { font-family: Arial, sans-serif; color: #000; background: #fff; margin: 0; }
        h1 { margin: 0 0 6px; font-size: 20px; }
        p { margin: 0 0 12px; font-size: 12px; color: #333; }
        .meta { display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; font-size: 12px; }
        colgroup col:first-child { width: 56%; }
        colgroup col:nth-child(2), colgroup col:nth-child(3) { width: 22%; }
        th, td { padding: 8px 10px; border: 1px solid #cfcfcf; }
        th { text-align: left; background: #f3f3f3; }
        .num { text-align: right; white-space: nowrap; }
        .group-row { background: #f7f7f7; font-weight: 700; }
        .ledger { color: #222; padding-left: 26px; }
        .spacer td { border: none; height: 12px; padding: 0; }
        .grand { background: #ececec; font-weight: 800; font-size: 14px; }
    </style>
</head>
<body onload="window.print()">
    <div style="margin-bottom: 10px; font-size: 12px;">
        <strong>{{ $company['name'] ?? config('app.name') }}</strong>
        @if(!empty($company['address_lines']))
            <div>{{ implode(', ', $company['address_lines']) }}</div>
        @endif
    </div>
    <div class="meta">
        <div>
            <h1>{{ $report['report_type'] === 'GROUP_SUMMARY' ? 'Group Summary' : 'Trial Balance' }}</h1>
            <p>Closing balance ({{ $report['period']['start'] }} - {{ $report['period']['end'] }})</p>
        </div>
        <div style="font-size: 12px;">{{ $report['is_balanced'] ? 'Balanced' : 'Unbalanced' }}</div>
    </div>

    <table>
        <colgroup><col><col><col></colgroup>
        <thead>
        <tr>
            <th>Group / Ledger</th>
            <th class="num">Debit</th>
            <th class="num">Credit</th>
        </tr>
        </thead>
        <tbody>
        @foreach($report['groups'] as $group)
            <tr class="group-row">
                <td>{{ $group['label'] }}</td>
                <td class="num">NGN {{ number_format((float) $group['debit'], 2) }}</td>
                <td class="num">NGN {{ number_format((float) $group['credit'], 2) }}</td>
            </tr>
            @foreach($group['ledgers'] as $ledger)
                <tr>
                    <td class="ledger">{{ $ledger['account_no'] }} - {{ $ledger['name'] }}</td>
                    <td class="num">NGN {{ number_format((float) $ledger['display_debit'], 2) }}</td>
                    <td class="num">NGN {{ number_format((float) $ledger['display_credit'], 2) }}</td>
                </tr>
            @endforeach
        @endforeach
        </tbody>
        <tfoot>
            <tr class="spacer"><td colspan="3"></td></tr>
            <tr class="grand">
                <td>Grand Total</td>
                <td class="num">NGN {{ number_format((float) $report['grand_total']['debit'], 2) }}</td>
                <td class="num">NGN {{ number_format((float) $report['grand_total']['credit'], 2) }}</td>
            </tr>
        </tfoot>
    </table>
</body>
</html>
