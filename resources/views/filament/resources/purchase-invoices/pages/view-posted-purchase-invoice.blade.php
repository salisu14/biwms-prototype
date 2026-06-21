<x-filament-panels::page>
    @php
        $currencyCode = $this->record->currency_code ?: 'NGN';
        $tableWrapper = 'overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900';
        $tableClass = 'min-w-full border-collapse text-sm';
        $headClass = 'bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-600 dark:bg-gray-800 dark:text-gray-300';
        $cellLabelClass = 'w-48 border border-gray-200 px-4 py-3 text-sm font-medium text-gray-500 dark:border-gray-700 dark:text-gray-400';
        $cellValueClass = 'border border-gray-200 px-4 py-3 text-sm text-gray-900 dark:border-gray-700 dark:text-gray-100';
        $headCellClass = 'border border-gray-200 px-4 py-3 dark:border-gray-700';
        $bodyCellClass = 'border border-gray-200 px-4 py-3 text-sm text-gray-900 dark:border-gray-700 dark:text-gray-100';
        $invoiceStatus = $this->record->paid_in_full ? 'Paid in Full' : ((float) $this->record->amount_paid > 0 ? 'Partially Paid' : 'Open');
        $invoiceStatusColor = match ($invoiceStatus) {
            'Paid in Full' => 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300',
            'Partially Paid' => 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-300',
            default => 'border-sky-200 bg-sky-50 text-sky-700 dark:border-sky-800 dark:bg-sky-950/40 dark:text-sky-300',
        };
    @endphp

    <div class="space-y-6">
        <div class="grid gap-4 md:grid-cols-3">
            <section class="{{ $tableWrapper }}">
                <div class="px-4 py-4">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Grand Total</p>
                    <p class="mt-1 text-2xl font-semibold text-gray-950 dark:text-white">{{ $currencyCode }} {{ number_format((float) $this->record->grand_total, 2) }}</p>
                </div>
            </section>
            <section class="{{ $tableWrapper }}">
                <div class="px-4 py-4">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Applied Amount</p>
                    <p class="mt-1 text-2xl font-semibold text-gray-950 dark:text-white">{{ $currencyCode }} {{ number_format((float) $this->record->amount_paid, 2) }}</p>
                </div>
            </section>
            <section class="{{ $tableWrapper }}">
                <div class="px-4 py-4">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Remaining Amount</p>
                    <p class="mt-1 text-2xl font-semibold text-gray-950 dark:text-white">{{ $currencyCode }} {{ number_format((float) $this->record->remaining_amount, 2) }}</p>
                </div>
            </section>
        </div>

        <section class="{{ $tableWrapper }}">
            <div class="border-b border-gray-200 px-4 py-3 dark:border-gray-700">
                <h3 class="text-base font-semibold text-gray-950 dark:text-white">Document Overview</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="{{ $tableClass }}">
                    <tbody>
                        <tr>
                            <td class="{{ $cellLabelClass }}">Invoice Number</td>
                            <td class="{{ $cellValueClass }}">{{ $this->record->document_number }}</td>
                            <td class="{{ $cellLabelClass }}">Status</td>
                            <td class="{{ $cellValueClass }}">
                                <div class="flex items-center gap-2">
                                    <span>{{ $this->record->status }}</span>
                                    <span class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-medium {{ $invoiceStatusColor }}">
                                        {{ $invoiceStatus }}
                                    </span>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td class="{{ $cellLabelClass }}">Posting Date</td>
                            <td class="{{ $cellValueClass }}">{{ optional($this->record->posting_date)->format('Y-m-d') }}</td>
                            <td class="{{ $cellLabelClass }}">Posted At</td>
                            <td class="{{ $cellValueClass }}">{{ optional($this->record->posted_at)->format('Y-m-d H:i') }}</td>
                        </tr>
                        <tr>
                            <td class="{{ $cellLabelClass }}">Document Date</td>
                            <td class="{{ $cellValueClass }}">{{ optional($this->record->document_date)->format('Y-m-d') }}</td>
                            <td class="{{ $cellLabelClass }}">Due Date</td>
                            <td class="{{ $cellValueClass }}">{{ optional($this->record->due_date)->format('Y-m-d') }}</td>
                        </tr>
                        <tr>
                            <td class="{{ $cellLabelClass }}">Order Number</td>
                            <td class="{{ $cellValueClass }}">{{ $this->record->order_number ?: '—' }}</td>
                            <td class="{{ $cellLabelClass }}">Currency</td>
                            <td class="{{ $cellValueClass }}">{{ $currencyCode }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="{{ $tableWrapper }}">
            <div class="border-b border-gray-200 px-4 py-3 dark:border-gray-700">
                <h3 class="text-base font-semibold text-gray-950 dark:text-white">Vendor Details</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="{{ $tableClass }}">
                    <tbody>
                        <tr>
                            <td class="{{ $cellLabelClass }}">Vendor</td>
                            <td class="{{ $cellValueClass }}">{{ $this->record->vendor_name }}</td>
                            <td class="{{ $cellLabelClass }}">Vendor Address</td>
                            <td class="{{ $cellValueClass }}">{{ $this->record->vendor_address ?: '—' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="{{ $tableWrapper }}">
            <div class="border-b border-gray-200 px-4 py-3 dark:border-gray-700">
                <h3 class="text-base font-semibold text-gray-950 dark:text-white">Amounts</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="{{ $tableClass }}">
                    <thead class="{{ $headClass }}">
                        <tr>
                            <th class="{{ $headCellClass }}">Metric</th>
                            <th class="{{ $headCellClass }} text-right">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="{{ $bodyCellClass }}">Total Amount</td>
                            <td class="{{ $bodyCellClass }} text-right">{{ $currencyCode }} {{ number_format((float) $this->record->total_amount, 2) }}</td>
                        </tr>
                        <tr>
                            <td class="{{ $bodyCellClass }}">VAT</td>
                            <td class="{{ $bodyCellClass }} text-right">{{ $currencyCode }} {{ number_format((float) $this->record->total_vat, 2) }}</td>
                        </tr>
                        <tr>
                            <td class="{{ $bodyCellClass }} font-semibold">Grand Total</td>
                            <td class="{{ $bodyCellClass }} text-right font-semibold">{{ $currencyCode }} {{ number_format((float) $this->record->grand_total, 2) }}</td>
                        </tr>
                        <tr>
                            <td class="{{ $bodyCellClass }}">Applied Amount</td>
                            <td class="{{ $bodyCellClass }} text-right">{{ $currencyCode }} {{ number_format((float) $this->record->amount_paid, 2) }}</td>
                        </tr>
                        <tr>
                            <td class="{{ $bodyCellClass }} font-semibold">Remaining Amount</td>
                            <td class="{{ $bodyCellClass }} text-right font-semibold">{{ $currencyCode }} {{ number_format((float) $this->record->remaining_amount, 2) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="{{ $tableWrapper }}">
            <div class="border-b border-gray-200 px-4 py-3 dark:border-gray-700">
                <h3 class="text-base font-semibold text-gray-950 dark:text-white">Applications</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="{{ $tableClass }}">
                    <thead class="{{ $headClass }}">
                        <tr>
                            <th class="{{ $headCellClass }}">Applied At</th>
                            <th class="{{ $headCellClass }}">Status</th>
                            <th class="{{ $headCellClass }}">Source Type</th>
                            <th class="{{ $headCellClass }}">Source Document</th>
                            <th class="{{ $headCellClass }}">Reference</th>
                            <th class="{{ $headCellClass }} text-right">Amount</th>
                            <th class="{{ $headCellClass }} text-right">Balance After</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($this->paymentApplications as $application)
                            <tr>
                                <td class="{{ $bodyCellClass }}">{{ optional($application['applied_at'])->format('Y-m-d H:i') }}</td>
                                <td class="{{ $bodyCellClass }}">
                                    <span class="inline-flex items-center rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-0.5 text-xs font-medium text-emerald-700 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300">
                                        Payment Applied
                                    </span>
                                </td>
                                <td class="{{ $bodyCellClass }}">{{ $application['source_type'] }}</td>
                                <td class="{{ $bodyCellClass }}">
                                    @if(!empty($application['source_url']))
                                        <a href="{{ $application['source_url'] }}" class="font-medium text-primary-600 hover:underline dark:text-primary-400">
                                            {{ $application['source_document'] ?: '—' }}
                                        </a>
                                    @else
                                        {{ $application['source_document'] ?: '—' }}
                                    @endif
                                </td>
                                <td class="{{ $bodyCellClass }}">{{ $application['reference'] ?: '—' }}</td>
                                <td class="{{ $bodyCellClass }} text-right">{{ $currencyCode }} {{ number_format((float) $application['amount_applied'], 2) }}</td>
                                <td class="{{ $bodyCellClass }} text-right">{{ $currencyCode }} {{ number_format((float) $application['document_remaining_after'], 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="border border-gray-200 px-4 py-6 text-center text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">No payment applications recorded yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="{{ $tableWrapper }}">
            <div class="border-b border-gray-200 px-4 py-3 dark:border-gray-700">
                <h3 class="text-base font-semibold text-gray-950 dark:text-white">Posted Lines</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="{{ $tableClass }}">
                    <thead class="{{ $headClass }}">
                        <tr>
                            <th class="{{ $headCellClass }}">Line</th>
                            <th class="{{ $headCellClass }}">Type</th>
                            <th class="{{ $headCellClass }}">Code</th>
                            <th class="{{ $headCellClass }}">Description</th>
                            <th class="{{ $headCellClass }} text-right">Qty</th>
                            <th class="{{ $headCellClass }} text-right">Unit Cost</th>
                            <th class="{{ $headCellClass }} text-right">Discount</th>
                            <th class="{{ $headCellClass }} text-right">Line Total</th>
                            <th class="{{ $headCellClass }} text-right">VAT</th>
                            <th class="{{ $headCellClass }} text-right">Incl. VAT</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($this->record->lines as $line)
                            <tr>
                                <td class="{{ $bodyCellClass }}">{{ $line->line_number }}</td>
                                <td class="{{ $bodyCellClass }}">{{ $line->type?->value ?? $line->type ?? '—' }}</td>
                                <td class="{{ $bodyCellClass }}">{{ $line->item_code ?: $line->gl_account_number ?: '—' }}</td>
                                <td class="{{ $bodyCellClass }}">{{ $line->item_description ?: $line->gl_account_name ?: '—' }}</td>
                                <td class="{{ $bodyCellClass }} text-right">{{ number_format((float) $line->quantity, 4) }}</td>
                                <td class="{{ $bodyCellClass }} text-right">{{ number_format((float) $line->unit_cost, 4) }}</td>
                                <td class="{{ $bodyCellClass }} text-right">{{ number_format((float) $line->line_discount_amount, 4) }}</td>
                                <td class="{{ $bodyCellClass }} text-right">{{ number_format((float) $line->line_total, 4) }}</td>
                                <td class="{{ $bodyCellClass }} text-right">{{ number_format((float) $line->vat_amount, 4) }}</td>
                                <td class="{{ $bodyCellClass }} text-right">{{ number_format((float) $line->amount_including_vat, 4) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="border border-gray-200 px-4 py-6 text-center text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">No posted lines available.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-filament-panels::page>
