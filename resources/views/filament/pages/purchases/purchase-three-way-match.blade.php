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

        <x-filament::button tag="a" :href="$pdfUrl" icon="heroicon-o-document-arrow-down" color="gray" target="_blank">
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
        <x-slot name="heading">Purchase Three-Way Match</x-slot>
        <x-slot name="description">
            Compare ordered, received, and invoiced quantities side by side and highlight matching or exception rows.
        </x-slot>

        <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <table class="min-w-full border-collapse text-sm">
                <thead>
                <tr class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-600 dark:bg-gray-800 dark:text-gray-300">
                    <th class="border border-gray-200 px-4 py-3 dark:border-gray-700">Reference</th>
                    <th class="border border-gray-200 px-4 py-3 dark:border-gray-700">Vendor</th>
                    <th class="border border-gray-200 px-4 py-3 dark:border-gray-700">Item</th>
                    <th class="border border-gray-200 px-4 py-3 dark:border-gray-700">UOM</th>
                    <th class="border border-gray-200 px-4 py-3 text-right dark:border-gray-700">Ordered</th>
                    <th class="border border-gray-200 px-4 py-3 text-right dark:border-gray-700">Received</th>
                    <th class="border border-gray-200 px-4 py-3 text-right dark:border-gray-700">Invoiced</th>
                    <th class="border border-gray-200 px-4 py-3 text-right dark:border-gray-700">Remaining Receive</th>
                    <th class="border border-gray-200 px-4 py-3 text-right dark:border-gray-700">Remaining Invoice</th>
                    <th class="border border-gray-200 px-4 py-3 text-right dark:border-gray-700">PO Cost</th>
                    <th class="border border-gray-200 px-4 py-3 text-right dark:border-gray-700">Invoice Cost</th>
                    <th class="border border-gray-200 px-4 py-3 dark:border-gray-700">Status</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($report['rows'] as $row)
                    <tr>
                        <td class="border border-gray-200 px-4 py-3 align-top dark:border-gray-700">
                            <div class="font-medium text-gray-900 dark:text-gray-100">{{ $row->reference_number ?? '—' }}</div>
                            <div class="text-xs text-gray-500">{{ $row->reference_type }}</div>
                        </td>
                        <td class="border border-gray-200 px-4 py-3 align-top dark:border-gray-700">
                            <div class="font-medium text-gray-900 dark:text-gray-100">{{ trim(($row->vendor_number ? $row->vendor_number.' - ' : '').($row->vendor_name ?? '—')) }}</div>
                        </td>
                        <td class="border border-gray-200 px-4 py-3 align-top dark:border-gray-700">
                            <div class="font-medium text-gray-900 dark:text-gray-100">{{ $row->item_code ?? '—' }}</div>
                            <div class="text-xs text-gray-500">{{ $row->description ?? '—' }}</div>
                        </td>
                        <td class="whitespace-nowrap border border-gray-200 px-4 py-3 align-top dark:border-gray-700">{{ $row->unit_of_measure_code ?? '—' }}</td>
                        <td class="whitespace-nowrap border border-gray-200 px-4 py-3 align-top text-right dark:border-gray-700">{{ $row->ordered_quantity === null ? '—' : number_format((float) $row->ordered_quantity, 4) }}</td>
                        <td class="whitespace-nowrap border border-gray-200 px-4 py-3 align-top text-right dark:border-gray-700">{{ $row->received_quantity === null ? '—' : number_format((float) $row->received_quantity, 4) }}</td>
                        <td class="whitespace-nowrap border border-gray-200 px-4 py-3 align-top text-right dark:border-gray-700">{{ $row->invoiced_quantity === null ? '—' : number_format((float) $row->invoiced_quantity, 4) }}</td>
                        <td class="whitespace-nowrap border border-gray-200 px-4 py-3 align-top text-right dark:border-gray-700">{{ $row->remaining_to_receive === null ? '—' : number_format((float) $row->remaining_to_receive, 4) }}</td>
                        <td class="whitespace-nowrap border border-gray-200 px-4 py-3 align-top text-right dark:border-gray-700">{{ $row->remaining_to_invoice === null ? '—' : number_format((float) $row->remaining_to_invoice, 4) }}</td>
                        <td class="whitespace-nowrap border border-gray-200 px-4 py-3 align-top text-right dark:border-gray-700">{{ $row->po_unit_cost === null ? '—' : number_format((float) $row->po_unit_cost, 2) }}</td>
                        <td class="whitespace-nowrap border border-gray-200 px-4 py-3 align-top text-right dark:border-gray-700">{{ $row->invoice_unit_cost === null ? '—' : number_format((float) $row->invoice_unit_cost, 2) }}</td>
                        <td class="border border-gray-200 px-4 py-3 align-top dark:border-gray-700">{{ $row->match_status }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="12" class="border border-gray-200 px-4 py-10 text-center text-gray-500 dark:border-gray-700">
                            No purchase lines match the selected filters.
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-panels::page>
