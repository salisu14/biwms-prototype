<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Customer Subledger Summary</title>
    <style>
        body { font-family: Arial, sans-serif; color: #111827; margin: 24px; }
        h1, h2, h3, p { margin: 0 0 8px; }
        .meta { margin-bottom: 18px; color: #4b5563; font-size: 12px; }
        .grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 12px; margin: 16px 0; }
        .card { border: 1px solid #d1d5db; padding: 12px; border-radius: 6px; }
        .label { color: #6b7280; font-size: 12px; }
        .value { font-size: 20px; font-weight: 700; margin-top: 4px; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th, td { border: 1px solid #d1d5db; padding: 8px 10px; font-size: 12px; vertical-align: top; }
        th { background: #f3f4f6; text-align: left; }
        .num { text-align: right; font-variant-numeric: tabular-nums; }
        @page { size: A4 landscape; margin: 12mm; }
    </style>
</head>
<body>
    <h1>{{ $company['name'] ?? 'Company' }}</h1>
    <h2>Customer Subledger Summary</h2>
    <p class="meta">
        Customer: {{ $customer?->name ?? 'All customers' }}
        | Document Type: {{ $documentTypeFilter ?? 'All' }}
        | Month: {{ $monthFilter ?? 'All' }}
    </p>

    <div class="grid">
        <div class="card"><div class="label">Current</div><div class="value">{{ number_format((float) ($report['aging']['current'] ?? 0), 2) }}</div></div>
        <div class="card"><div class="label">1-30 Days</div><div class="value">{{ number_format((float) ($report['aging']['1_30'] ?? 0), 2) }}</div></div>
        <div class="card"><div class="label">31-60 Days</div><div class="value">{{ number_format((float) ($report['aging']['31_60'] ?? 0), 2) }}</div></div>
        <div class="card"><div class="label">61-90 Days</div><div class="value">{{ number_format((float) ($report['aging']['61_90'] ?? 0), 2) }}</div></div>
        <div class="card"><div class="label">Over 90 Days</div><div class="value">{{ number_format((float) ($report['aging']['over_90'] ?? 0), 2) }}</div></div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Customer</th>
                <th>Document Type</th>
                <th>Document No.</th>
                <th>Description</th>
                <th class="num">Debit</th>
                <th class="num">Credit</th>
                <th class="num">Balance</th>
                <th class="num">Remaining</th>
            </tr>
        </thead>
        <tbody>
            @foreach($report['entries'] as $entry)
                <tr>
                    <td>{{ optional($entry->posting_date)->toDateString() }}</td>
                    <td>{{ $entry->customer?->name }}</td>
                    <td>{{ $entry->document_type }}</td>
                    <td>{{ $entry->document_number }}</td>
                    <td>{{ $entry->description }}</td>
                    <td class="num">{{ number_format((float) $entry->debit_amount, 2) }}</td>
                    <td class="num">{{ number_format((float) $entry->credit_amount, 2) }}</td>
                    <td class="num">{{ number_format((float) $entry->running_balance, 2) }}</td>
                    <td class="num">{{ number_format((float) $entry->remaining_amount, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
