<x-filament-panels::page>
    <div class="space-y-6">
        <x-filament::section>
            <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                <div class="space-y-1">
                    <h2 class="text-lg font-semibold">Item Ledger Summary</h2>
                    <p class="text-sm text-gray-500">Inventory movement drilldown by type and month.</p>
                    @if($entryTypeFilter || $monthFilter || $itemId || $locationId)
                        <p class="text-sm text-gray-500">
                            Active filters:
                            <span class="inline-flex items-center rounded-full border border-primary-200 bg-primary-50 px-2 py-0.5 text-xs font-medium text-primary-700 dark:border-primary-800 dark:bg-primary-950/40 dark:text-primary-300">
                                {{ $activeFilterCount }} applied
                            </span>
                            @if($entryTypeFilter)
                                Type = {{ $entryTypeFilter }}
                            @endif
                            @if($entryTypeFilter && $monthFilter)
                                |
                            @endif
                            @if($monthFilter)
                                Month = {{ $monthFilter }}
                            @endif
                            @if(($entryTypeFilter || $monthFilter) && $itemId)
                                |
                            @endif
                            @if($itemId)
                                Item selected
                            @endif
                            @if(($entryTypeFilter || $monthFilter || $itemId) && $locationId)
                                |
                            @endif
                            @if($locationId)
                                Location selected
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
                    @if($entryTypeFilter || $monthFilter || $itemId || $locationId)
                        <x-filament::button
                            tag="a"
                            color="gray"
                            icon="heroicon-o-x-mark"
                            :href="\App\Filament\Pages\Finance\ItemLedgerSummary::getUrl()"
                        >
                            Clear Filters
                        </x-filament::button>
                    @endif
                </div>
            </div>
        </x-filament::section>

        <div class="grid gap-4 md:grid-cols-4">
            <x-filament::section><div class="space-y-1"><p class="text-sm text-gray-500">Entries</p><p class="text-2xl font-semibold tabular-nums">{{ $summary['count'] }}</p></div></x-filament::section>
            <x-filament::section><div class="space-y-1"><p class="text-sm text-gray-500">Quantity</p><p class="text-2xl font-semibold tabular-nums">{{ number_format((float) $summary['quantity'], 2) }}</p></div></x-filament::section>
            <x-filament::section><div class="space-y-1"><p class="text-sm text-gray-500">Remaining Qty</p><p class="text-2xl font-semibold tabular-nums">{{ number_format((float) $summary['remaining_quantity'], 2) }}</p></div></x-filament::section>
            <x-filament::section><div class="space-y-1"><p class="text-sm text-gray-500">Actual Cost</p><p class="text-2xl font-semibold tabular-nums">{{ number_format((float) $summary['cost'], 2) }}</p></div></x-filament::section>
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            <x-filament::section>
                <div class="mb-4">
                    <h3 class="text-base font-semibold">Entry Type Summary</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="report-table w-full text-sm">
                        <thead>
                            <tr>
                                <th class="px-3 py-2 text-left font-semibold">Type</th>
                                <th class="px-3 py-2 text-right font-semibold">Entries</th>
                                <th class="px-3 py-2 text-right font-semibold">Quantity</th>
                                <th class="px-3 py-2 text-right font-semibold">Remaining Qty</th>
                                <th class="px-3 py-2 text-right font-semibold">Cost</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($entryTypeSummary as $row)
                                <tr>
                                    <td class="px-3 py-2">
                                        <a
                                            href="{{ \App\Filament\Pages\Finance\ItemLedgerSummary::getUrl(['entryTypeFilter' => $row['type'], 'monthFilter' => $monthFilter, 'itemId' => $itemId, 'locationId' => $locationId]) }}"
                                            @class([
                                                'font-medium hover:underline',
                                                'text-primary-600 dark:text-primary-400' => $entryTypeFilter !== $row['type'],
                                                'rounded-md bg-primary-50 px-2 py-1 text-primary-700 dark:bg-primary-950/40 dark:text-primary-300' => $entryTypeFilter === $row['type'],
                                            ])
                                        >
                                            {{ $row['type'] }}
                                        </a>
                                    </td>
                                    <td class="px-3 py-2 text-right tabular-nums">{{ $row['count'] }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums">{{ number_format((float) $row['quantity'], 2) }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums">{{ number_format((float) $row['remaining_quantity'], 2) }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums">{{ number_format((float) $row['cost'], 2) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="px-3 py-6 text-center text-gray-500">No entry summary available.</td></tr>
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
                                <th class="px-3 py-2 text-right font-semibold">Quantity</th>
                                <th class="px-3 py-2 text-right font-semibold">Cost</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($monthBuckets as $row)
                                <tr>
                                    <td class="px-3 py-2">
                                        <a
                                            href="{{ \App\Filament\Pages\Finance\ItemLedgerSummary::getUrl(['entryTypeFilter' => $entryTypeFilter, 'monthFilter' => $row['bucket'], 'itemId' => $itemId, 'locationId' => $locationId]) }}"
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
                                    <td class="px-3 py-2 text-right tabular-nums">{{ number_format((float) $row['quantity'], 2) }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums">{{ number_format((float) $row['cost'], 2) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="px-3 py-6 text-center text-gray-500">No monthly buckets available.</td></tr>
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
                            <th class="px-3 py-2 text-left font-semibold">Entry No.</th>
                            <th class="px-3 py-2 text-left font-semibold">Type</th>
                            <th class="px-3 py-2 text-left font-semibold">Document No.</th>
                            <th class="px-3 py-2 text-left font-semibold">Item</th>
                            <th class="px-3 py-2 text-left font-semibold">Location</th>
                            <th class="px-3 py-2 text-right font-semibold">Quantity</th>
                            <th class="px-3 py-2 text-right font-semibold">Remaining Qty</th>
                            <th class="px-3 py-2 text-right font-semibold">Cost</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($entries as $entry)
                            <tr>
                                <td class="px-3 py-2">{{ optional($entry->posting_date)->toDateString() ?? '—' }}</td>
                                <td class="px-3 py-2">
                                    <a href="{{ \App\Filament\Resources\ItemLedgerEntries\ItemLedgerEntryResource::getUrl('view', ['record' => $entry]) }}" class="font-medium text-primary-600 hover:underline dark:text-primary-400">
                                        {{ $entry->entry_number }}
                                    </a>
                                </td>
                                <td class="px-3 py-2">{{ $entry->entry_type->value }}</td>
                                <td class="px-3 py-2">
                                    <a href="{{ \App\Filament\Resources\ItemLedgerEntries\ItemLedgerEntryResource::getUrl('view', ['record' => $entry]) }}" class="font-medium text-primary-600 hover:underline dark:text-primary-400">
                                        {{ $entry->document_number }}
                                    </a>
                                </td>
                                <td class="px-3 py-2">
                                    @if($entry->item_id)
                                        <a
                                            href="{{ \App\Filament\Resources\ItemLedgerEntries\ItemLedgerEntryResource::getUrl('index', ['tableFilters' => ['item_id' => ['value' => $entry->item_id]]]) }}"
                                            class="font-medium text-primary-600 hover:underline dark:text-primary-400"
                                        >
                                            {{ $entry->item?->item_code ?? '—' }} - {{ $entry->item?->description ?? '—' }}
                                        </a>
                                    @else
                                        {{ $entry->item?->item_code ?? '—' }} - {{ $entry->item?->description ?? '—' }}
                                    @endif
                                </td>
                                <td class="px-3 py-2">
                                    @if($entry->location_id)
                                        <a
                                            href="{{ \App\Filament\Resources\ItemLedgerEntries\ItemLedgerEntryResource::getUrl('index', ['tableFilters' => ['location_id' => ['value' => $entry->location_id]]]) }}"
                                            class="font-medium text-primary-600 hover:underline dark:text-primary-400"
                                        >
                                            {{ $entry->location?->name ?? '—' }}
                                        </a>
                                    @else
                                        {{ $entry->location?->name ?? '—' }}
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ number_format((float) $entry->quantity, 2) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ number_format((float) $entry->remaining_quantity, 2) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ number_format((float) $entry->cost_amount_actual, 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="9" class="px-3 py-6 text-center text-gray-500">No item ledger entries were found for this view.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>

@include('filament.components.report-table-styles')
