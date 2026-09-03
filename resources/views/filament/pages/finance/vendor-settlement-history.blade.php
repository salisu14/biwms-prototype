<x-filament-panels::page>
    <form wire:submit="refreshReport" class="space-y-6">
        {{ $this->form }}
    </form>

    <div class="flex flex-wrap items-center gap-3">
        <x-filament::button
            tag="a"
            :href="$csvUrl"
            icon="heroicon-o-arrow-down-tray"
            color="success"
        >
            Export CSV
        </x-filament::button>

        <x-filament::button
            tag="a"
            :href="$pdfUrl"
            icon="heroicon-o-document-arrow-down"
            color="gray"
            target="_blank"
        >
            Export PDF
        </x-filament::button>

        <x-filament::button
            wire:click="resetFilters"
            icon="heroicon-o-x-mark"
            color="gray"
        >
            Clear Filters
        </x-filament::button>
    </div>

    <x-filament::section>
        <x-slot name="heading">Settlement Applications</x-slot>
        <x-slot name="description">
            Read-only trace of vendor payments and purchase credit memo applications. Original invoice linkage is shown separately from the settlement target.
        </x-slot>

        <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <table class="min-w-[1200px] w-full table-fixed border-collapse text-sm">
                <thead>
                    <tr class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-600 dark:bg-gray-800 dark:text-gray-300">
                        <th class="border border-gray-200 px-4 py-3 dark:border-gray-700">Date</th>
                        <th class="border border-gray-200 px-4 py-3 dark:border-gray-700">Vendor</th>
                        <th class="border border-gray-200 px-4 py-3 dark:border-gray-700">Type</th>
                        <th class="border border-gray-200 px-4 py-3 dark:border-gray-700">Source</th>
                        <th class="border border-gray-200 px-4 py-3 dark:border-gray-700">Original Invoice</th>
                        <th class="border border-gray-200 px-4 py-3 dark:border-gray-700">Settlement Target</th>
                        <th class="border border-gray-200 px-4 py-3 text-right dark:border-gray-700">Amount</th>
                        <th class="border border-gray-200 px-4 py-3 dark:border-gray-700">Currency</th>
                        <th class="border border-gray-200 px-4 py-3 text-right dark:border-gray-700">Source Before</th>
                        <th class="border border-gray-200 px-4 py-3 text-right dark:border-gray-700">Source After</th>
                        <th class="border border-gray-200 px-4 py-3 text-right dark:border-gray-700">Target Before</th>
                        <th class="border border-gray-200 px-4 py-3 text-right dark:border-gray-700">Target After</th>
                        <th class="border border-gray-200 px-4 py-3 dark:border-gray-700">Applied By</th>
                        <th class="border border-gray-200 px-4 py-3 dark:border-gray-700">Trace</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td class="whitespace-nowrap border border-gray-200 px-4 py-3 align-top text-gray-700 dark:border-gray-700 dark:text-gray-200">
                                {{ optional($row->application_date)->format('Y-m-d H:i') ?: $row->application_date }}
                            </td>
                            <td class="border border-gray-200 px-4 py-3 align-top dark:border-gray-700">
                                @if ($url = $this->vendorUrl($row->vendor_id))
                                    <a href="{{ $url }}" class="font-medium text-primary-600 hover:underline dark:text-primary-400">
                                        {{ trim(($row->vendor_number ? $row->vendor_number.' - ' : '').($row->vendor_name ?? 'Unknown Vendor')) }}
                                    </a>
                                @else
                                    <span class="text-gray-700 dark:text-gray-200">{{ $row->vendor_name ?? '—' }}</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap border border-gray-200 px-4 py-3 align-top dark:border-gray-700">
                                <x-filament::badge :color="$row->settlement_type === 'PAYMENT_APPLICATION' ? 'success' : 'warning'">
                                    {{ $row->settlement_type === 'PAYMENT_APPLICATION' ? 'Payment' : 'Purchase Credit Memo' }}
                                </x-filament::badge>
                            </td>
                            <td class="border border-gray-200 px-4 py-3 align-top dark:border-gray-700">
                                @if ($url = $this->sourceDocumentUrl($row))
                                    <a href="{{ $url }}" class="font-medium text-primary-600 hover:underline dark:text-primary-400">
                                        {{ $row->source_document_number }}
                                    </a>
                                @else
                                    <span>{{ $row->source_document_number ?? '—' }}</span>
                                @endif
                                <div class="text-xs text-gray-500">{{ str_replace('_', ' ', $row->source_document_type) }}</div>
                            </td>
                            <td class="whitespace-nowrap border border-gray-200 px-4 py-3 align-top text-gray-700 dark:border-gray-700 dark:text-gray-200">
                                {{ $row->original_invoice_number ?? '—' }}
                            </td>
                            <td class="border border-gray-200 px-4 py-3 align-top dark:border-gray-700">
                                @if ($url = $this->targetDocumentUrl($row))
                                    <a href="{{ $url }}" class="font-medium text-primary-600 hover:underline dark:text-primary-400">
                                        {{ $row->target_document_number }}
                                    </a>
                                @else
                                    <span>{{ $row->target_document_number ?? '—' }}</span>
                                @endif
                                <div class="text-xs text-gray-500">{{ str_replace('_', ' ', $row->target_document_type) }}</div>
                            </td>
                            <td class="whitespace-nowrap border border-gray-200 px-4 py-3 align-top text-right font-semibold dark:border-gray-700">
                                {{ number_format((float) $row->amount_applied, 2) }}
                            </td>
                            <td class="whitespace-nowrap border border-gray-200 px-4 py-3 align-top dark:border-gray-700">{{ $row->currency_code ?? '—' }}</td>
                            <td class="whitespace-nowrap border border-gray-200 px-4 py-3 align-top text-right dark:border-gray-700">{{ $row->source_remaining_before === null ? '—' : number_format((float) $row->source_remaining_before, 2) }}</td>
                            <td class="whitespace-nowrap border border-gray-200 px-4 py-3 align-top text-right dark:border-gray-700">{{ $row->source_remaining_after === null ? '—' : number_format((float) $row->source_remaining_after, 2) }}</td>
                            <td class="whitespace-nowrap border border-gray-200 px-4 py-3 align-top text-right dark:border-gray-700">{{ $row->target_remaining_before === null ? '—' : number_format((float) $row->target_remaining_before, 2) }}</td>
                            <td class="whitespace-nowrap border border-gray-200 px-4 py-3 align-top text-right dark:border-gray-700">{{ $row->target_remaining_after === null ? '—' : number_format((float) $row->target_remaining_after, 2) }}</td>
                            <td class="whitespace-nowrap border border-gray-200 px-4 py-3 align-top dark:border-gray-700">{{ $row->applied_by_name ?? '—' }}</td>
                            <td class="whitespace-nowrap border border-gray-200 px-4 py-3 align-top dark:border-gray-700">
                                <div>#{{ $row->settlement_id }}</div>
                                <div class="max-w-44 truncate text-xs text-gray-500">{{ $row->reference_key ?? '—' }}</div>
                                <div class="text-xs text-gray-500">
                                    Ledger {{ $row->source_ledger_entry_id ?? '—' }} → {{ $row->target_ledger_entry_id ?? '—' }}
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="14" class="border border-gray-200 px-4 py-10 text-center text-gray-500 dark:border-gray-700">
                                No settlement applications match the selected filters.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $rows->links() }}
        </div>
    </x-filament::section>
</x-filament-panels::page>
