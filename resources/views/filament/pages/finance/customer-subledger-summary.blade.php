<x-filament-panels::page>
    <div class="space-y-6">
        <x-filament::section>
            <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                <div class="space-y-1">
                    <h2 class="text-lg font-semibold">Customer Subledger Summary</h2>
                    @if($customer)
                        <p class="text-sm text-gray-500">{{ $customer->customer_number ?? 'Customer' }} - {{ $customer->name }}</p>
                    @else
                        <p class="text-sm text-gray-500">All customers</p>
                    @endif
                    @if($documentTypeFilter || $monthFilter)
                        <p class="text-sm text-gray-500">
                            Active filters:
                            <span class="inline-flex items-center rounded-full border border-primary-200 bg-primary-50 px-2 py-0.5 text-xs font-medium text-primary-700 dark:border-primary-800 dark:bg-primary-950/40 dark:text-primary-300">
                                {{ $activeFilterCount }} applied
                            </span>
                            @if($documentTypeFilter)
                                Type = {{ $documentTypeFilter }}
                            @endif
                            @if($documentTypeFilter && $monthFilter)
                                |
                            @endif
                            @if($monthFilter)
                                Month = {{ $monthFilter }}
                            @endif
                        </p>
                    @endif
                </div>

                <div class="flex gap-2">
                    <x-filament::button
                        tag="a"
                        color="gray"
                        icon="heroicon-o-arrow-down-tray"
                        :href="$csvUrl"
                    >
                        CSV
                    </x-filament::button>
                    <x-filament::button
                        tag="a"
                        color="gray"
                        icon="heroicon-o-printer"
                        :href="$printUrl"
                        target="_blank"
                    >
                        Print
                    </x-filament::button>
                    <x-filament::button
                        tag="a"
                        color="gray"
                        icon="heroicon-o-bars-3-bottom-left"
                        :href="$detailUrl"
                    >
                        Open Detailed Ledger
                    </x-filament::button>
                    @if($documentTypeFilter || $monthFilter)
                        <x-filament::button
                            tag="a"
                            color="gray"
                            icon="heroicon-o-x-mark"
                            :href="\App\Filament\Pages\Finance\CustomerSubledgerSummary::getUrl(['customerId' => $customer?->id])"
                        >
                            Clear Filters
                        </x-filament::button>
                    @endif
                </div>
            </div>
        </x-filament::section>

        <div class="grid gap-4 md:grid-cols-5">
            <x-filament::section><div class="space-y-1"><p class="text-sm text-gray-500">Current</p><p class="text-2xl font-semibold tabular-nums">{{ number_format((float) ($aging['current'] ?? 0), 2) }}</p></div></x-filament::section>
            <x-filament::section><div class="space-y-1"><p class="text-sm text-gray-500">1-30 Days</p><p class="text-2xl font-semibold tabular-nums">{{ number_format((float) ($aging['1_30'] ?? 0), 2) }}</p></div></x-filament::section>
            <x-filament::section><div class="space-y-1"><p class="text-sm text-gray-500">31-60 Days</p><p class="text-2xl font-semibold tabular-nums">{{ number_format((float) ($aging['31_60'] ?? 0), 2) }}</p></div></x-filament::section>
            <x-filament::section><div class="space-y-1"><p class="text-sm text-gray-500">61-90 Days</p><p class="text-2xl font-semibold tabular-nums">{{ number_format((float) ($aging['61_90'] ?? 0), 2) }}</p></div></x-filament::section>
            <x-filament::section><div class="space-y-1"><p class="text-sm text-gray-500">Over 90 Days</p><p class="text-2xl font-semibold tabular-nums">{{ number_format((float) ($aging['over_90'] ?? 0), 2) }}</p></div></x-filament::section>
        </div>

        <div class="grid gap-4 md:grid-cols-4">
            <x-filament::section><div class="space-y-1"><p class="text-sm text-gray-500">Entries</p><p class="text-2xl font-semibold tabular-nums">{{ $summary['count'] }}</p></div></x-filament::section>
            <x-filament::section><div class="space-y-1"><p class="text-sm text-gray-500">Debit</p><p class="text-2xl font-semibold tabular-nums">{{ number_format((float) $summary['debit'], 2) }}</p></div></x-filament::section>
            <x-filament::section><div class="space-y-1"><p class="text-sm text-gray-500">Credit</p><p class="text-2xl font-semibold tabular-nums">{{ number_format((float) $summary['credit'], 2) }}</p></div></x-filament::section>
            <x-filament::section><div class="space-y-1"><p class="text-sm text-gray-500">Open Remaining</p><p class="text-2xl font-semibold tabular-nums">{{ number_format((float) $summary['open_remaining'], 2) }}</p></div></x-filament::section>
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            <x-filament::section>
                <div class="mb-4">
                    <h3 class="text-base font-semibold">Document Type Summary</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="report-table w-full text-sm">
                        <thead>
                            <tr>
                                <th class="px-3 py-2 text-left font-semibold">Type</th>
                                <th class="px-3 py-2 text-right font-semibold">Entries</th>
                                <th class="px-3 py-2 text-right font-semibold">Debit</th>
                                <th class="px-3 py-2 text-right font-semibold">Credit</th>
                                <th class="px-3 py-2 text-right font-semibold">Net</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($documentTypeSummary as $row)
                                <tr>
                                    <td class="px-3 py-2">
                                        <a
                                            href="{{ \App\Filament\Pages\Finance\CustomerSubledgerSummary::getUrl(['customerId' => $customer?->id, 'documentTypeFilter' => $row['type'], 'monthFilter' => $monthFilter]) }}"
                                            @class([
                                                'font-medium hover:underline',
                                                'text-primary-600 dark:text-primary-400' => $documentTypeFilter !== $row['type'],
                                                'rounded-md bg-primary-50 px-2 py-1 text-primary-700 dark:bg-primary-950/40 dark:text-primary-300' => $documentTypeFilter === $row['type'],
                                            ])
                                        >
                                            {{ $row['type'] }}
                                        </a>
                                    </td>
                                    <td class="px-3 py-2 text-right tabular-nums">{{ $row['count'] }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums">{{ number_format((float) $row['debit'], 2) }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums">{{ number_format((float) $row['credit'], 2) }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums">{{ number_format((float) $row['net'], 2) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="px-3 py-6 text-center text-gray-500">No document summary available.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-filament::section>

            <x-filament::section>
                <div class="mb-4">
                    <h3 class="text-base font-semibold">Monthly Buckets</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="report-table w-full text-sm">
                        <thead>
                            <tr>
                                <th class="px-3 py-2 text-left font-semibold">Month</th>
                                <th class="px-3 py-2 text-right font-semibold">Entries</th>
                                <th class="px-3 py-2 text-right font-semibold">Debit</th>
                                <th class="px-3 py-2 text-right font-semibold">Credit</th>
                                <th class="px-3 py-2 text-right font-semibold">Net</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($monthBuckets as $row)
                                <tr>
                                    <td class="px-3 py-2">
                                        <a
                                            href="{{ \App\Filament\Pages\Finance\CustomerSubledgerSummary::getUrl(['customerId' => $customer?->id, 'documentTypeFilter' => $documentTypeFilter, 'monthFilter' => $row['bucket']]) }}"
                                            @class([
                                                'font-medium hover:underline',
                                                'text-primary-600 dark:text-primary-400' => $monthFilter !== $row['bucket'],
                                                'rounded-md bg-primary-50 px-2 py-1 text-primary-700 dark:bg-primary-950/40 dark:text-primary-300' => $monthFilter === $row['bucket'],
                                            ])
                                        >
                                            {{ $row['bucket'] }}
                                        </a>
                                    </td>
                                    <td class="px-3 py-2 text-right tabular-nums">{{ $row['count'] }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums">{{ number_format((float) $row['debit'], 2) }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums">{{ number_format((float) $row['credit'], 2) }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums">{{ number_format((float) $row['net'], 2) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="px-3 py-6 text-center text-gray-500">No monthly buckets available.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-filament::section>
        </div>

        <x-filament::section>
            <div class="overflow-x-auto">
                <table class="report-table w-full text-sm">
                    <thead>
                        <tr>
                            <th class="px-3 py-2 text-left font-semibold">Date</th>
                            <th class="px-3 py-2 text-left font-semibold">Customer</th>
                            <th class="px-3 py-2 text-left font-semibold">Document Type</th>
                            <th class="px-3 py-2 text-left font-semibold">Document No.</th>
                            <th class="px-3 py-2 text-left font-semibold">Description</th>
                            <th class="px-3 py-2 text-right font-semibold">Debit</th>
                            <th class="px-3 py-2 text-right font-semibold">Credit</th>
                            <th class="px-3 py-2 text-right font-semibold">Balance</th>
                            <th class="px-3 py-2 text-right font-semibold">Remaining</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($entries as $entry)
                            <tr>
                                <td class="px-3 py-2">{{ optional($entry->posting_date)->toDateString() ?? '—' }}</td>
                                <td class="px-3 py-2">{{ $entry->customer?->name ?? '—' }}</td>
                                <td class="px-3 py-2">{{ $entry->document_type }}</td>
                                <td class="px-3 py-2">
                                    @php($sourceUrl = $this->resolveEntrySourceUrl($entry))
                                    @if($sourceUrl)
                                        <a href="{{ $sourceUrl }}" class="font-medium text-primary-600 hover:underline dark:text-primary-400">
                                            {{ $entry->document_number }}
                                        </a>
                                    @else
                                        {{ $entry->document_number }}
                                    @endif
                                </td>
                                <td class="px-3 py-2">{{ $entry->description }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ number_format((float) $entry->debit_amount, 2) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ number_format((float) $entry->credit_amount, 2) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ number_format((float) $entry->running_balance, 2) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ number_format((float) $entry->remaining_amount, 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="9" class="px-3 py-6 text-center text-gray-500">No customer ledger entries were found for this view.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>

@include('filament.components.report-table-styles')
