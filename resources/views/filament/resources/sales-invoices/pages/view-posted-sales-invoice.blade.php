<x-filament-panels::page>
    <div class="space-y-6">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                <p class="text-sm text-gray-500">Invoice No.</p>
                <p class="font-semibold">{{ $this->record->document_number }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                <p class="text-sm text-gray-500">Customer</p>
                <p class="font-semibold">{{ $this->record->customer_name }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                <p class="text-sm text-gray-500">Status</p>
                <p class="font-semibold">{{ $this->record->status }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                <p class="text-sm text-gray-500">Posted Date</p>
                <p class="font-semibold">{{ optional($this->record->posted_at)->format('Y-m-d H:i') }}</p>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
            <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                <p class="text-sm text-gray-500">Grand Total</p>
                <p class="font-semibold">{{ $this->record->currency_code ?: 'NGN' }} {{ number_format((float) $this->record->grand_total, 2) }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                <p class="text-sm text-gray-500">Amount Paid</p>
                <p class="font-semibold">{{ $this->record->currency_code ?: 'NGN' }} {{ number_format((float) $this->record->amount_paid, 2) }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                <p class="text-sm text-gray-500">Remaining</p>
                <p class="font-semibold">{{ $this->record->currency_code ?: 'NGN' }} {{ number_format((float) $this->record->remaining_amount, 2) }}</p>
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
            <h3 class="mb-3 text-base font-semibold">Lines</h3>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                    <thead>
                        <tr>
                            <th class="px-3 py-2 text-left">Line</th>
                            <th class="px-3 py-2 text-left">Item</th>
                            <th class="px-3 py-2 text-left">Description</th>
                            <th class="px-3 py-2 text-right">Qty</th>
                            <th class="px-3 py-2 text-right">Unit Price</th>
                            <th class="px-3 py-2 text-right">Line Amount</th>
                            <th class="px-3 py-2 text-right">VAT</th>
                            <th class="px-3 py-2 text-right">Incl. VAT</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse($this->record->lines as $line)
                            <tr>
                                <td class="px-3 py-2">{{ $line->line_number }}</td>
                                <td class="px-3 py-2">{{ $line->item_code }}</td>
                                <td class="px-3 py-2">{{ $line->item_description }}</td>
                                <td class="px-3 py-2 text-right">{{ number_format((float) $line->quantity, 4) }}</td>
                                <td class="px-3 py-2 text-right">{{ number_format((float) $line->unit_price, 4) }}</td>
                                <td class="px-3 py-2 text-right">{{ number_format((float) $line->line_amount, 4) }}</td>
                                <td class="px-3 py-2 text-right">{{ number_format((float) $line->vat_amount, 4) }}</td>
                                <td class="px-3 py-2 text-right">{{ number_format((float) $line->amount_including_vat, 4) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-3 py-4 text-center text-gray-500">No posted lines available.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-filament-panels::page>
