<x-filament-panels::page>
    @php($reportData = $this->reportData())
    @php($setupWarning = $this->setupWarning())
    @php($activeFilters = $this->activeFilters())

    <div class="space-y-6">
        <x-filament::section class="print:hidden">
            <form wire:submit="generateReport" class="space-y-4">
                {{ $this->form }}
                <div class="flex justify-end gap-2">
                    <x-filament::button
                        tag="a"
                        color="gray"
                        icon="heroicon-o-arrow-down-tray"
                        :href="route('reports.depreciation-book.print', array_merge($this->formData ?? [], ['format' => 'csv']))"
                    >
                        CSV
                    </x-filament::button>
                    <x-filament::button
                        tag="a"
                        color="info"
                        icon="heroicon-o-printer"
                        :href="route('reports.depreciation-book.print', $this->formData ?? [])"
                        target="_blank"
                    >
                        Print
                    </x-filament::button>
                    <x-filament::button type="submit">Update Report</x-filament::button>
                </div>
            </form>
        </x-filament::section>

        @if($setupWarning['show'])
            <x-filament::section class="border-warning-200 bg-warning-50 dark:border-warning-900 dark:bg-warning-950/20 print:hidden">
                <div class="space-y-1 text-sm">
                    <p class="font-semibold text-warning-800 dark:text-warning-200">Fixed Asset Setup Incomplete</p>
                    <p class="text-warning-700 dark:text-warning-300">
                        {{ $setupWarning['message'] }}
                    </p>
                </div>
            </x-filament::section>
        @endif

        <div class="grid gap-4 md:grid-cols-4 print:hidden">
            <x-filament::section><div class="space-y-1"><p class="text-sm text-gray-500">Assets</p><p class="text-2xl font-semibold tabular-nums">{{ $reportData['summary']['asset_count'] }}</p></div></x-filament::section>
            <x-filament::section><div class="space-y-1"><p class="text-sm text-gray-500">Annual Depreciation</p><p class="text-2xl font-semibold tabular-nums">{{ number_format((float) $reportData['summary']['annual_depreciation'], 2) }}</p></div></x-filament::section>
            <x-filament::section><div class="space-y-1"><p class="text-sm text-gray-500">Accumulated Depreciation</p><p class="text-2xl font-semibold tabular-nums">{{ number_format((float) $reportData['summary']['accumulated_depreciation'], 2) }}</p></div></x-filament::section>
            <x-filament::section><div class="space-y-1"><p class="text-sm text-gray-500">Remaining Book Value</p><p class="text-2xl font-semibold tabular-nums">{{ number_format((float) $reportData['summary']['remaining_book_value'], 2) }}</p></div></x-filament::section>
        </div>

        <x-filament::section>
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-lg font-semibold">Depreciation Book Report</h2>
                <p class="text-sm text-gray-500">Snapshot as at {{ $reportData['as_of_date'] ?? $reportData['printed_at'] }}</p>
            </div>

            @if($activeFilters !== [])
                <div class="mb-4 flex flex-wrap gap-2 print:hidden">
                    @foreach($activeFilters as $filter)
                        <span class="inline-flex items-center rounded-full border border-gray-200 bg-gray-50 px-3 py-1 text-xs font-medium text-gray-700 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200">
                            {{ $filter['label'] }}: {{ $filter['value'] }}
                        </span>
                    @endforeach
                </div>
            @endif

            <div class="overflow-x-auto">
                <table class="report-table w-full text-sm">
                    <thead>
                        <tr>
                            <th class="px-3 py-2 text-left font-semibold">Asset</th>
                            <th class="px-3 py-2 text-left font-semibold">Book Code</th>
                            <th class="px-3 py-2 text-left font-semibold">Method</th>
                            <th class="px-3 py-2 text-left font-semibold">Class</th>
                            <th class="px-3 py-2 text-left font-semibold">Location</th>
                            <th class="px-3 py-2 text-right font-semibold">Annual Depreciation</th>
                            <th class="px-3 py-2 text-right font-semibold">Accum. Depreciation</th>
                            <th class="px-3 py-2 text-right font-semibold">Remaining Book Value</th>
                            <th class="px-3 py-2 text-left font-semibold">Next Depreciation Date</th>
                            <th class="px-3 py-2 text-left font-semibold">Status</th>
                            <th class="px-3 py-2 text-left font-semibold print:hidden">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($reportData['rows'] as $row)
                            <tr>
                                <td class="px-3 py-2">{{ $row['fa_no'] }} - {{ $row['description'] }}</td>
                                <td class="px-3 py-2">{{ $row['book_code'] ?? '—' }}</td>
                                <td class="px-3 py-2">{{ $row['method'] ?? '—' }}</td>
                                <td class="px-3 py-2">{{ $row['class'] ?? '—' }}</td>
                                <td class="px-3 py-2">{{ $row['location'] ?? '—' }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ number_format((float) $row['annual_depreciation'], 2) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ number_format((float) $row['accumulated_depreciation'], 2) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ number_format((float) $row['remaining_book_value'], 2) }}</td>
                                <td class="px-3 py-2">{{ $row['next_depreciation_date'] ?? '—' }}</td>
                                <td class="px-3 py-2">
                                    <div class="space-y-1">
                                        <x-filament::badge :color="$row['status_color']">
                                            {{ $row['status'] ?? '—' }}
                                        </x-filament::badge>
                                        @if(($row['status'] ?? null) === 'disposed' && filled($row['disposal_date'] ?? null))
                                            <p class="text-xs text-gray-500">Disposed on {{ $row['disposal_date'] }}</p>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-3 py-2 print:hidden">
                                    <div class="flex gap-2">
                                        <x-filament::button
                                            tag="a"
                                            size="sm"
                                            color="gray"
                                            icon="heroicon-o-arrow-top-right-on-square"
                                            :href="\App\Filament\Resources\FixedAssets\FixedAssetResource::getUrl('view', ['record' => $row['id']])"
                                        >
                                            Open Card
                                        </x-filament::button>
                                        <x-filament::button
                                            tag="a"
                                            size="sm"
                                            color="info"
                                            icon="heroicon-o-list-bullet"
                                            :href="\App\Filament\Pages\Finance\FixedAssetLedgerEntries::getUrl(['fixedAssetId' => $row['id'], 'asOfDate' => $reportData['as_of_date'] ?? null])"
                                        >
                                            View Ledger
                                        </x-filament::button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="11" class="px-3 py-6 text-center text-gray-500">No depreciation book data found for the selected filters.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>

@include('filament.components.report-table-styles')
