<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #111827; }
        .header { margin-bottom: 16px; }
        .title { font-size: 18px; font-weight: 700; margin: 0; }
        .muted { color: #6b7280; }
        .meta { width: 100%; border-collapse: collapse; margin: 12px 0 18px; }
        .meta td { padding: 4px 0; vertical-align: top; }
        .lines { width: 100%; border-collapse: collapse; }
        .lines th, .lines td { border: 1px solid #d1d5db; padding: 6px; }
        .lines th { background: #f3f4f6; text-align: left; }
        .right { text-align: right; }
    </style>
</head>
<body>
    <div class="header">
        <p class="title">{{ $company['name'] ?? config('app.name') }}</p>
        <div class="muted">
            @foreach(($company['address_lines'] ?? []) as $line)
                <div>{{ $line }}</div>
            @endforeach
            @if(!empty($company['phone']))
                <div>Phone: {{ $company['phone'] }}</div>
            @endif
            @if(!empty($company['email']))
                <div>Email: {{ $company['email'] }}</div>
            @endif
        </div>
    </div>

    <h2 style="margin: 0 0 6px;">Physical Inventory Counting Sheet</h2>
    <div class="muted">{{ $journal->journal_batch_name }} — {{ $journal->description }}</div>

    <table class="meta">
        <tr>
            <td><strong>Location:</strong> {{ $journal->location?->name ?? $journal->location_code }}</td>
            <td><strong>Bin:</strong> {{ $journal->bin_code ?: '—' }}</td>
        </tr>
        <tr>
            <td><strong>Status:</strong> {{ $journal->status }}</td>
            <td><strong>Posting Date:</strong> {{ optional($journal->posting_date)->format('d/m/Y') }}</td>
        </tr>
    </table>

    <table class="lines">
        <thead>
            <tr>
                <th>#</th>
                <th>Item</th>
                <th>Location</th>
                <th>Bin</th>
                <th class="right">System Qty</th>
                <th class="right">Physical Qty</th>
                <th class="right">Variance</th>
                <th>UoM</th>
            </tr>
        </thead>
        <tbody>
            @forelse($journal->lines as $line)
                <tr>
                    <td>{{ $line->line_no }}</td>
                    <td>{{ $line->item?->item_code }} - {{ $line->item_description }}</td>
                    <td>{{ $line->location_code }}</td>
                    <td>{{ $line->bin_code ?: '—' }}</td>
                    <td class="right">{{ number_format((float) $line->quantity_base, 2) }}</td>
                    <td class="right">{{ number_format((float) $line->qty_physical_inventory, 2) }}</td>
                    <td class="right">{{ number_format((float) $line->qty_calculated, 2) }}</td>
                    <td>{{ $line->unit_of_measure_code ?: '—' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="8">No lines found.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
