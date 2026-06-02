<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Fixed Asset Ledger Entries</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 18mm;
        }

        body {
            font-family: Arial, sans-serif;
            color: #111827;
            font-size: 12px;
            margin: 0;
        }

        .header {
            margin-bottom: 18px;
        }

        .title {
            font-size: 20px;
            font-weight: 700;
            margin: 0 0 6px;
        }

        .meta {
            color: #4b5563;
            margin: 2px 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            border: 1px solid #d1d5db;
            padding: 8px 10px;
            vertical-align: top;
        }

        th {
            text-align: left;
            background: #f3f4f6;
            font-weight: 700;
        }

        .text-right {
            text-align: right;
        }
    </style>
</head>
<body>
    <div class="header">
        <p class="title">Fixed Asset Ledger Entries</p>
        @if(! empty($company['name'] ?? null))
            <p class="meta">{{ $company['name'] }}</p>
        @endif
        @if($asset)
            <p class="meta">Asset: {{ $asset->fa_no }} - {{ $asset->description }}</p>
        @endif
        <p class="meta">As of Date: {{ $asOfDate ?? 'Full history' }}</p>
        <p class="meta">Type Filter: {{ $typeFilter ?? 'All' }}</p>
        <p class="meta">Month Filter: {{ $monthFilter ?? 'All' }}</p>
        <p class="meta">Printed At: {{ now()->format('Y-m-d H:i') }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th>Posting Date</th>
                <th>Entry No.</th>
                <th>Type</th>
                <th>Document No.</th>
                <th>Book</th>
                <th class="text-right">Amount</th>
                <th class="text-right">Depreciation</th>
                <th class="text-right">Accum. Depreciation</th>
                <th class="text-right">Book Value After</th>
                <th>Description</th>
            </tr>
        </thead>
        <tbody>
            @forelse($entries as $entry)
                <tr>
                    <td>{{ optional($entry->posting_date)->toDateString() ?? '—' }}</td>
                    <td>{{ $entry->entry_no }}</td>
                    <td>{{ $entry->fa_posting_type }}</td>
                    <td>{{ $entry->document_no ?? '—' }}</td>
                    <td>{{ $entry->depreciationBook?->code ?? '—' }}</td>
                    <td class="text-right">{{ number_format((float) $entry->amount, 2) }}</td>
                    <td class="text-right">{{ number_format((float) $entry->depreciation_amount, 2) }}</td>
                    <td class="text-right">{{ number_format((float) $entry->accumulated_depreciation, 2) }}</td>
                    <td class="text-right">{{ number_format((float) $entry->book_value_after, 2) }}</td>
                    <td>{{ $entry->description }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="10">No fixed asset ledger entries were found for this view.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
