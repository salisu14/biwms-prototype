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
            :href="$xlsxUrl"
            icon="heroicon-o-document-arrow-down"
            color="gray"
        >
            Export XLSX
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
            Read-only trace of payment applications and sales credit memo applications. Original invoice linkage is shown separately from the settlement target.
        </x-slot>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
                <thead>
                    <tr class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:bg-white/5 dark:text-gray-400">
                        <th class="px-3 py-3">Date</th>
                        <th class="px-3 py-3">Customer</th>
                        <th class="px-3 py-3">Type</th>
                        <th class="px-3 py-3">Source</th>
                        <th class="px-3 py-3">Original Invoice</th>
                        <th class="px-3 py-3">Settlement Target</th>
                        <th class="px-3 py-3 text-right">Amount</th>
                        <th class="px-3 py-3">Currency</th>
                        <th class="px-3 py-3 text-right">Source Before</th>
                        <th class="px-3 py-3 text-right">Source After</th>
                        <th class="px-3 py-3 text-right">Target Before</th>
                        <th class="px-3 py-3 text-right">Target After</th>
                        <th class="px-3 py-3">Applied By</th>
                        <th class="px-3 py-3">Trace</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                    @forelse ($rows as $row)
                        <tr>
                            <td class="whitespace-nowrap px-3 py-3 text-gray-700 dark:text-gray-200">
                                {{ optional($row->application_date)->format('Y-m-d H:i') ?: $row->application_date }}
                            </td>
                            <td class="px-3 py-3">
                                @if ($url = $this->customerUrl($row->customer_id))
                                    <a href="{{ $url }}" class="font-medium text-primary-600 hover:underline dark:text-primary-400">
                                        {{ trim(($row->customer_number ? $row->customer_number.' - ' : '').($row->customer_name ?? 'Unknown Customer')) }}
                                    </a>
                                @else
                                    <span class="text-gray-700 dark:text-gray-200">{{ $row->customer_name ?? '—' }}</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-3 py-3">
                                <x-filament::badge :color="$row->settlement_type === 'PAYMENT_APPLICATION' ? 'success' : 'warning'">
                                    {{ $row->settlement_type === 'PAYMENT_APPLICATION' ? 'Payment' : 'Sales Credit Memo' }}
                                </x-filament::badge>
                            </td>
                            <td class="whitespace-nowrap px-3 py-3">
                                @if ($url = $this->sourceDocumentUrl($row))
                                    <a href="{{ $url }}" class="font-medium text-primary-600 hover:underline dark:text-primary-400">
                                        {{ $row->source_document_number }}
                                    </a>
                                @else
                                    <span>{{ $row->source_document_number ?? '—' }}</span>
                                @endif
                                <div class="text-xs text-gray-500">{{ str_replace('_', ' ', $row->source_document_type) }}</div>
                            </td>
                            <td class="whitespace-nowrap px-3 py-3 text-gray-700 dark:text-gray-200">
                                {{ $row->original_invoice_number ?? '—' }}
                            </td>
                            <td class="whitespace-nowrap px-3 py-3">
                                @if ($url = $this->targetDocumentUrl($row))
                                    <a href="{{ $url }}" class="font-medium text-primary-600 hover:underline dark:text-primary-400">
                                        {{ $row->target_document_number }}
                                    </a>
                                @else
                                    <span>{{ $row->target_document_number ?? '—' }}</span>
                                @endif
                                <div class="text-xs text-gray-500">{{ str_replace('_', ' ', $row->target_document_type) }}</div>
                            </td>
                            <td class="whitespace-nowrap px-3 py-3 text-right font-semibold">
                                {{ number_format((float) $row->amount_applied, 2) }}
                            </td>
                            <td class="whitespace-nowrap px-3 py-3">{{ $row->currency_code ?? '—' }}</td>
                            <td class="whitespace-nowrap px-3 py-3 text-right">{{ $row->source_remaining_before === null ? '—' : number_format((float) $row->source_remaining_before, 2) }}</td>
                            <td class="whitespace-nowrap px-3 py-3 text-right">{{ $row->source_remaining_after === null ? '—' : number_format((float) $row->source_remaining_after, 2) }}</td>
                            <td class="whitespace-nowrap px-3 py-3 text-right">{{ $row->target_remaining_before === null ? '—' : number_format((float) $row->target_remaining_before, 2) }}</td>
                            <td class="whitespace-nowrap px-3 py-3 text-right">{{ $row->target_remaining_after === null ? '—' : number_format((float) $row->target_remaining_after, 2) }}</td>
                            <td class="whitespace-nowrap px-3 py-3">{{ $row->applied_by_name ?? '—' }}</td>
                            <td class="whitespace-nowrap px-3 py-3">
                                <div>#{{ $row->settlement_id }}</div>
                                <div class="max-w-44 truncate text-xs text-gray-500">{{ $row->reference_key ?? '—' }}</div>
                                <div class="text-xs text-gray-500">
                                    Ledger {{ $row->source_ledger_entry_id ?? '—' }} → {{ $row->target_ledger_entry_id ?? '—' }}
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="14" class="px-3 py-10 text-center text-gray-500">
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
