<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cash Flow Statement Print</title>
    <style>
        @page { size: A4 portrait; margin: 12mm; }
        body { font-family: Arial, sans-serif; color: #000; background: #fff; margin: 0; }
        h1 { margin: 0 0 6px; font-size: 20px; }
        p { margin: 0 0 8px; font-size: 12px; color: #333; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; font-size: 12px; }
        colgroup col:first-child { width: 72%; }
        colgroup col:nth-child(2) { width: 28%; }
        th, td { padding: 8px 10px; border: 1px solid #cfcfcf; vertical-align: top; }
        th { text-align: left; background: #f3f3f3; }
        .num { text-align: right; white-space: nowrap; }
        .bold { font-weight: 700; }
        .section { background: #f7f7f7; font-weight: 700; }
    </style>
</head>
<body onload="window.print()">
<div style="margin-bottom: 10px; font-size: 12px;">
    <strong>{{ $company['trading_name'] ?: ($company['name'] ?? config('app.name')) }}</strong>
    @if(!empty($company['address_lines']))
        <div>{{ implode(', ', $company['address_lines']) }}</div>
    @endif
</div>

<h1>Cash Flow Statement</h1>
<p>
    Period: {{ $reportData['period']['start'] }} to {{ $reportData['period']['end'] }}
    | Method: {{ ucfirst($reportData['method']) }}
</p>
@if(isset($reportData['compare_period']))
    <p>
        Comparison: {{ $reportData['compare_period']['start'] }} to {{ $reportData['compare_period']['end'] }}
    </p>
@endif
<p>
    Mapping:
    @if(($reportData['mapping']['mode'] ?? null) === 'cash_flow_schedule')
        Cash Flow Schedule-driven
        @if(!empty($reportData['mapping']['cash_flow_schedule']))
            | CF: {{ $reportData['mapping']['cash_flow_schedule'] }}
        @endif
        @if(!empty($reportData['mapping']['profit_and_loss_schedule']))
            | P&L: {{ $reportData['mapping']['profit_and_loss_schedule'] }}
        @endif
        @if(!empty($reportData['mapping']['balance_sheet_schedule']))
            | BS: {{ $reportData['mapping']['balance_sheet_schedule'] }}
        @endif
    @elseif(($reportData['mapping']['mode'] ?? null) === 'schedule')
        Schedule-driven
        @if(!empty($reportData['mapping']['profit_and_loss_schedule']))
            | P&L: {{ $reportData['mapping']['profit_and_loss_schedule'] }}
        @endif
        @if(!empty($reportData['mapping']['balance_sheet_schedule']))
            | BS: {{ $reportData['mapping']['balance_sheet_schedule'] }}
        @endif
    @else
        COA category fallback
    @endif
</p>
<p>
    Cash accounts:
    {{ ! empty($reportData['cash_accounts']) ? implode(', ', $reportData['cash_accounts']) : 'None configured' }}
</p>

<table>
    <colgroup>
        <col><col>
        @if(isset($reportData['compare_period']))
            <col><col>
        @endif
    </colgroup>
    <thead>
    <tr>
        <th>Description</th>
        <th class="num">Amount</th>
        @if(isset($reportData['compare_period']))
            <th class="num">Prior Period</th>
            <th class="num">Variance</th>
            <th class="num">Variance %</th>
        @endif
    </tr>
    </thead>
    <tbody>
    <tr class="bold">
        <td>Beginning Cash Balance</td>
        <td class="num">NGN {{ number_format((float) $reportData['opening_cash'], 2) }}</td>
        @if(isset($reportData['comparison_summary']))
            <td class="num">NGN {{ number_format((float) $reportData['comparison_summary']['opening_cash'], 2) }}</td>
            <td class="num">NGN {{ number_format((float) $reportData['comparison_summary']['opening_cash_variance_amount'], 2) }}</td>
            <td class="num">{{ $reportData['comparison_summary']['opening_cash_variance_percent'] !== null ? number_format((float) $reportData['comparison_summary']['opening_cash_variance_percent'], 1).'%' : '—' }}</td>
        @endif
    </tr>

    @foreach($reportData['sections'] as $section)
        <tr class="section">
            <td>{{ $section['label'] }}</td>
            <td></td>
            @if(isset($reportData['compare_period']))
                <td></td>
                <td></td>
                <td></td>
            @endif
        </tr>

        @forelse($section['lines'] as $line)
            <tr>
                <td style="padding-left: 24px;">{{ $line['label'] }}</td>
                <td class="num">NGN {{ number_format((float) $line['amount'], 2) }}</td>
                @if(isset($reportData['compare_period']))
                    <td class="num">NGN {{ number_format((float) ($line['compare_amount'] ?? 0), 2) }}</td>
                    <td class="num">NGN {{ number_format((float) ($line['variance_amount'] ?? 0), 2) }}</td>
                    <td class="num">{{ ($line['variance_percent'] ?? null) !== null ? number_format((float) $line['variance_percent'], 1).'%' : '—' }}</td>
                @endif
            </tr>
        @empty
            <tr>
                <td style="padding-left: 24px;">No movements for this section.</td>
                <td class="num">NGN 0.00</td>
                @if(isset($reportData['compare_period']))
                    <td class="num">NGN 0.00</td>
                    <td class="num">NGN 0.00</td>
                    <td class="num">—</td>
                @endif
            </tr>
        @endforelse

        <tr class="bold">
            <td>Net Cash from {{ $section['label'] }}</td>
            <td class="num">NGN {{ number_format((float) $section['total'], 2) }}</td>
            @if(isset($reportData['compare_period']))
                <td class="num">NGN {{ number_format((float) ($section['compare_total'] ?? 0), 2) }}</td>
                <td class="num">NGN {{ number_format((float) ($section['variance_amount'] ?? 0), 2) }}</td>
                <td class="num">{{ ($section['variance_percent'] ?? null) !== null ? number_format((float) $section['variance_percent'], 1).'%' : '—' }}</td>
            @endif
        </tr>
    @endforeach
    </tbody>
    <tfoot>
    <tr class="bold">
        <td>Net Change in Cash</td>
        <td class="num">NGN {{ number_format((float) $reportData['net_change_in_cash'], 2) }}</td>
        @if(isset($reportData['comparison_summary']))
            <td class="num">NGN {{ number_format((float) $reportData['comparison_summary']['net_change_in_cash'], 2) }}</td>
            <td class="num">NGN {{ number_format((float) $reportData['comparison_summary']['net_change_in_cash_variance_amount'], 2) }}</td>
            <td class="num">{{ $reportData['comparison_summary']['net_change_in_cash_variance_percent'] !== null ? number_format((float) $reportData['comparison_summary']['net_change_in_cash_variance_percent'], 1).'%' : '—' }}</td>
        @endif
    </tr>
    <tr class="bold">
        <td>Ending Cash Balance</td>
        <td class="num">NGN {{ number_format((float) $reportData['ending_cash'], 2) }}</td>
        @if(isset($reportData['comparison_summary']))
            <td class="num">NGN {{ number_format((float) $reportData['comparison_summary']['ending_cash'], 2) }}</td>
            <td class="num">NGN {{ number_format((float) $reportData['comparison_summary']['ending_cash_variance_amount'], 2) }}</td>
            <td class="num">{{ $reportData['comparison_summary']['ending_cash_variance_percent'] !== null ? number_format((float) $reportData['comparison_summary']['ending_cash_variance_percent'], 1).'%' : '—' }}</td>
        @endif
    </tr>
    </tfoot>
</table>
</body>
</html>
