<x-filament::section>
    <div class="mb-5 flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h2 class="text-lg font-semibold">{{ $report['title'] }}</h2>
            <p class="text-sm text-gray-500">
                {{ $report['period']['date_from'] }} to {{ $report['period']['date_to'] }}
                @if(filled($report['period']['posting_group'] ?? null))
                    · {{ $report['period']['posting_group'] }}
                @else
                    · All posting groups
                @endif
            </p>
        </div>
        <div class="text-sm text-gray-500">
            {{ number_format($report['summary']['groups_count']) }} groups
        </div>
    </div>

    <div class="mb-6 grid gap-4 md:grid-cols-4">
        <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
            <p class="text-xs font-medium uppercase text-gray-500">{{ $report['amount_label'] }}</p>
            <p class="mt-2 text-2xl font-semibold tabular-nums">NGN {{ number_format((float) $report['summary']['total_amount'], 2) }}</p>
        </div>
        <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
            <p class="text-xs font-medium uppercase text-gray-500">Transactions</p>
            <p class="mt-2 text-2xl font-semibold tabular-nums">{{ number_format($report['summary']['total_transactions']) }}</p>
        </div>
        <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
            <p class="text-xs font-medium uppercase text-gray-500">Average</p>
            <p class="mt-2 text-2xl font-semibold tabular-nums">NGN {{ number_format((float) $report['summary']['average_amount'], 2) }}</p>
        </div>
        <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
            <p class="text-xs font-medium uppercase text-gray-500">Posting Groups</p>
            <p class="mt-2 text-2xl font-semibold tabular-nums">{{ number_format($report['summary']['groups_count']) }}</p>
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="report-table w-full table-auto text-sm">
            <thead>
                <tr class="bg-gray-50 dark:bg-gray-800/60">
                    <th class="text-left">Posting Group</th>
                    <th class="text-right">{{ $report['amount_label'] }}</th>
                    <th class="text-right">Transactions</th>
                    <th class="text-right">Average</th>
                    <th class="text-right">Maximum</th>
                    <th class="text-right">Minimum</th>
                    <th class="text-right">Accounts</th>
                    <th class="text-right">% of Total</th>
                </tr>
            </thead>
            <tbody>
                @forelse($report['rows'] as $row)
                    <tr>
                        <td>
                            <div class="font-medium">{{ $row['group_name'] }}</div>
                            <div class="text-xs text-gray-500">{{ $row['group_code'] }}</div>
                        </td>
                        <td class="text-right tabular-nums">NGN {{ number_format((float) $row['amount'], 2) }}</td>
                        <td class="text-right tabular-nums">{{ number_format($row['transaction_count']) }}</td>
                        <td class="text-right tabular-nums">NGN {{ number_format((float) $row['average_amount'], 2) }}</td>
                        <td class="text-right tabular-nums">NGN {{ number_format((float) $row['max_amount'], 2) }}</td>
                        <td class="text-right tabular-nums">NGN {{ number_format((float) $row['min_amount'], 2) }}</td>
                        <td class="text-right tabular-nums">{{ number_format($row['accounts_used']) }}</td>
                        <td class="text-right tabular-nums">{{ number_format((float) $row['percentage_of_total'], 2) }}%</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="py-6 text-center text-gray-500">No entries found for the selected filters.</td>
                    </tr>
                @endforelse
            </tbody>
            @if(!empty($report['rows']))
                <tfoot>
                    <tr class="bg-primary-50 font-semibold text-primary-900 dark:bg-primary-900/20 dark:text-primary-100">
                        <td>Total</td>
                        <td class="text-right tabular-nums">NGN {{ number_format((float) $report['summary']['total_amount'], 2) }}</td>
                        <td class="text-right tabular-nums">{{ number_format($report['summary']['total_transactions']) }}</td>
                        <td class="text-right tabular-nums">NGN {{ number_format((float) $report['summary']['average_amount'], 2) }}</td>
                        <td colspan="3"></td>
                        <td class="text-right tabular-nums">100.00%</td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>
</x-filament::section>
