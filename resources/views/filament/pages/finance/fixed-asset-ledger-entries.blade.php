<x-filament-panels::page>
    <div class="space-y-6">
        <x-filament::section>
            <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                <div class="space-y-1">
                    <h2 class="text-lg font-semibold">Fixed Asset Ledger Entries</h2>
                    @if($asset)
                        <p class="text-sm text-gray-500">
                            {{ $asset->fa_no }} - {{ $asset->description }}
                            @if($asset->faClass?->name)
                                | Class: {{ $asset->faClass->name }}
                            @endif
                            @if($asset->location?->name)
                                | Location: {{ $asset->location->name }}
                            @endif
                        </p>
                    @endif
                    <p class="text-sm text-gray-500">
                        @if($asOfDate)
                            Entries up to {{ $asOfDate }}
                        @else
                            Full ledger history
                        @endif
                    </p>
                    @if($typeFilter || $monthFilter)
                        <p class="text-sm text-gray-500">
                            Active filters:
                            <span class="inline-flex items-center rounded-full border border-primary-200 bg-primary-50 px-2 py-0.5 text-xs font-medium text-primary-700 dark:border-primary-800 dark:bg-primary-950/40 dark:text-primary-300">
                                {{ $activeFilterCount }} applied
                            </span>
                            @if($typeFilter)
                                Type = {{ $typeFilter }}
                            @endif
                            @if($typeFilter && $monthFilter)
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
                        color="info"
                        icon="heroicon-o-printer"
                        :href="$printExportUrl"
                        target="_blank"
                    >
                        Print
                    </x-filament::button>
                    <x-filament::button
                        tag="a"
                        color="gray"
                        icon="heroicon-o-arrow-down-tray"
                        :href="$csvExportUrl"
                    >
                        CSV
                    </x-filament::button>
                    @if($fixedAssetViewUrl)
                        <x-filament::button
                            tag="a"
                            color="gray"
                            icon="heroicon-o-arrow-top-right-on-square"
                            :href="$fixedAssetViewUrl"
                        >
                            Open Fixed Asset Card
                        </x-filament::button>
                    @endif
                    @if($typeFilter || $monthFilter)
                        <x-filament::button
                            tag="a"
                            color="gray"
                            icon="heroicon-o-x-mark"
                            :href="\App\Filament\Pages\Finance\FixedAssetLedgerEntries::getUrl(['fixedAssetId' => $asset?->id, 'asOfDate' => $asOfDate])"
                        >
                            Clear Ledger Filters
                        </x-filament::button>
                    @endif
                </div>
            </div>
        </x-filament::section>

        <div class="grid gap-4 md:grid-cols-4">
            <x-filament::section><div class="space-y-1"><p class="text-sm text-gray-500">Entries</p><p class="text-2xl font-semibold tabular-nums">{{ $summary['count'] }}</p></div></x-filament::section>
            <x-filament::section><div class="space-y-1"><p class="text-sm text-gray-500">Net Amount</p><p class="text-2xl font-semibold tabular-nums">{{ number_format((float) $summary['amount'], 2) }}</p></div></x-filament::section>
            <x-filament::section><div class="space-y-1"><p class="text-sm text-gray-500">Depreciation Amount</p><p class="text-2xl font-semibold tabular-nums">{{ number_format((float) $summary['depreciation_amount'], 2) }}</p></div></x-filament::section>
            <x-filament::section><div class="space-y-1"><p class="text-sm text-gray-500">Latest Book Value</p><p class="text-2xl font-semibold tabular-nums">{{ number_format((float) $summary['book_value_after'], 2) }}</p></div></x-filament::section>
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            <x-filament::section>
                <div class="mb-4">
                    <h3 class="text-base font-semibold">Movement Summary by Type</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="report-table w-full text-sm">
                        <thead>
                            <tr>
                                <th class="px-3 py-2 text-left font-semibold">Type</th>
                                <th class="px-3 py-2 text-right font-semibold">Entries</th>
                                <th class="px-3 py-2 text-right font-semibold">Amount</th>
                                <th class="px-3 py-2 text-right font-semibold">Depreciation</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($movementSummary as $row)
                                <tr>
                                    <td class="px-3 py-2">
                                        <a
                                            href="{{ \App\Filament\Pages\Finance\FixedAssetLedgerEntries::getUrl(['fixedAssetId' => $asset?->id, 'asOfDate' => $asOfDate, 'typeFilter' => $row['type'], 'monthFilter' => $monthFilter]) }}"
                                            @class([
                                                'font-medium hover:underline',
                                                'text-primary-600 dark:text-primary-400' => $typeFilter !== $row['type'],
                                                'rounded-md bg-primary-50 px-2 py-1 text-primary-700 dark:bg-primary-950/40 dark:text-primary-300' => $typeFilter === $row['type'],
                                            ])
                                        >
                                            {{ $row['type'] }}
                                        </a>
                                    </td>
                                    <td class="px-3 py-2 text-right tabular-nums">{{ $row['count'] }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums">{{ number_format((float) $row['amount'], 2) }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums">{{ number_format((float) $row['depreciation_amount'], 2) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="px-3 py-6 text-center text-gray-500">No movement summary available.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-filament::section>

            <x-filament::section>
                <div class="mb-4">
                    <h3 class="text-base font-semibold">Monthly Movement Buckets</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="report-table w-full text-sm">
                        <thead>
                            <tr>
                                <th class="px-3 py-2 text-left font-semibold">Month</th>
                                <th class="px-3 py-2 text-right font-semibold">Entries</th>
                                <th class="px-3 py-2 text-right font-semibold">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($dateBuckets as $row)
                                <tr>
                                    <td class="px-3 py-2">
                                        <a
                                            href="{{ \App\Filament\Pages\Finance\FixedAssetLedgerEntries::getUrl(['fixedAssetId' => $asset?->id, 'asOfDate' => $asOfDate, 'typeFilter' => $typeFilter, 'monthFilter' => $row['bucket']]) }}"
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
                                    <td class="px-3 py-2 text-right tabular-nums">{{ number_format((float) $row['amount'], 2) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="px-3 py-6 text-center text-gray-500">No monthly buckets available.</td></tr>
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
                            <th class="px-3 py-2 text-left font-semibold">Posting Date</th>
                            <th class="px-3 py-2 text-left font-semibold">Entry No.</th>
                            <th class="px-3 py-2 text-left font-semibold">Type</th>
                            <th class="px-3 py-2 text-left font-semibold">Document No.</th>
                            <th class="px-3 py-2 text-left font-semibold">Book</th>
                            <th class="px-3 py-2 text-right font-semibold">Amount</th>
                            <th class="px-3 py-2 text-right font-semibold">Depreciation</th>
                            <th class="px-3 py-2 text-right font-semibold">Accum. Depreciation</th>
                            <th class="px-3 py-2 text-right font-semibold">Book Value After</th>
                            <th class="px-3 py-2 text-left font-semibold">Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($entries as $entry)
                            <tr>
                                <td class="px-3 py-2">{{ optional($entry->posting_date)->toDateString() ?? '—' }}</td>
                                <td class="px-3 py-2">{{ $entry->entry_no }}</td>
                                <td class="px-3 py-2">{{ $entry->fa_posting_type }}</td>
                                <td class="px-3 py-2">{{ $entry->document_no ?? '—' }}</td>
                                <td class="px-3 py-2">{{ $entry->depreciationBook?->code ?? '—' }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ number_format((float) $entry->amount, 2) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ number_format((float) $entry->depreciation_amount, 2) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ number_format((float) $entry->accumulated_depreciation, 2) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ number_format((float) $entry->book_value_after, 2) }}</td>
                                <td class="px-3 py-2">{{ $entry->description }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="px-3 py-6 text-center text-gray-500">
                                    No fixed asset ledger entries were found for this view.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>

@include('filament.components.report-table-styles')
