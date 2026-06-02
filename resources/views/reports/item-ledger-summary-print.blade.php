<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Item Ledger Summary</title>
    <style>
        body { font-family: Arial, sans-serif; color: #111827; margin: 24px; }
        h1, h2, p { margin: 0 0 8px; }
        .meta { margin-bottom: 18px; color: #4b5563; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th, td { border: 1px solid #d1d5db; padding: 8px 10px; font-size: 12px; vertical-align: top; }
        th { background: #f3f4f6; text-align: left; }
        .num { text-align: right; font-variant-numeric: tabular-nums; }
        @page { size: A4 landscape; margin: 12mm; }
    </style>
</head>
<body>
    <h1>{{ $company['name'] ?? 'Company' }}</h1>
    <h2>Item Ledger Summary</h2>
    <p class="meta">
        Type: {{ $entryTypeFilter ?? 'All' }}
        | Month: {{ $monthFilter ?? 'All' }}
        | Item: {{ $item?->description ?? 'All items' }}
        | Location: {{ $location?->name ?? 'All locations' }}
    </p>

    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Entry No.</th>
                <th>Type</th>
                <th>Document No.</th>
                <th>Item</th>
                <th>Location</th>
                <th class="num">Quantity</th>
                <th class="num">Remaining Qty</th>
                <th class="num">Cost</th>
            </tr>
        </thead>
        <tbody>
            @foreach($report['entries'] as $entry)
                <tr>
                    <td>{{ optional($entry->posting_date)->toDateString() }}</td>
                    <td>{{ $entry->entry_number }}</td>
                    <td>{{ $entry->entry_type->value }}</td>
                    <td>{{ $entry->document_number }}</td>
                    <td>{{ trim(($entry->item?->item_code ?? '').' - '.($entry->item?->description ?? ''), ' -') }}</td>
                    <td>{{ $entry->location?->name }}</td>
                    <td class="num">{{ number_format((float) $entry->quantity, 2) }}</td>
                    <td class="num">{{ number_format((float) $entry->remaining_quantity, 2) }}</td>
                    <td class="num">{{ number_format((float) $entry->cost_amount_actual, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
