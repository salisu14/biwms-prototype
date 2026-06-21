<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Fixed Asset List Print</title>
    <style>
        @page { size: A4 landscape; margin: 10mm; }
        body { font-family: Arial, sans-serif; color: #000; background: #fff; margin: 0; }
        h1 { margin: 0 0 6px; font-size: 20px; }
        p { margin: 0 0 10px; font-size: 12px; color: #333; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; font-size: 11px; }
        th, td { padding: 6px 8px; border: 1px solid #cfcfcf; }
        th { text-align: left; background: #f3f3f3; }
        .num { text-align: right; white-space: nowrap; }
    </style>
</head>
<body onload="window.print()">
<div style="margin-bottom: 10px; font-size: 12px;">
    <strong>{{ $company['name'] ?? config('app.name') }}</strong>
    @if(!empty($company['address_lines']))<div>{{ implode(', ', $company['address_lines']) }}</div>@endif
</div>
<h1>Fixed Asset List</h1>
<p>As of {{ $reportData['as_of_date'] ?? $reportData['printed_at'] }}</p>
<table>
    <thead><tr><th>Asset No.</th><th>Description</th><th>Class</th><th>Location</th><th>Acq. Date</th><th class="num">Acq. Cost</th><th class="num">Accum. Dep.</th><th class="num">NBV</th><th>Method</th><th>Life Remaining</th></tr></thead>
    <tbody>
    @foreach($reportData['rows'] as $row)
        <tr>
            <td>{{ $row['fa_no'] }}</td><td>{{ $row['description'] }}</td><td>{{ $row['class'] ?? '—' }}</td><td>{{ $row['location'] ?? '—' }}</td><td>{{ $row['acquisition_date'] ?? '—' }}</td>
            <td class="num">NGN {{ number_format((float) $row['acquisition_cost'], 2) }}</td>
            <td class="num">NGN {{ number_format((float) $row['accumulated_depreciation'], 2) }}</td>
            <td class="num">NGN {{ number_format((float) $row['net_book_value'], 2) }}</td>
            <td>{{ $row['depreciation_method'] ?? '—' }}</td><td>{{ $row['useful_life_remaining_label'] }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
</body>
</html>
