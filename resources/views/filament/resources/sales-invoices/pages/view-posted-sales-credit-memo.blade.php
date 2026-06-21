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
        $creditStatus = $this->record->refunded
            ? 'Refunded'
            : ((float) $this->record->remaining_amount <= 0.0001 ? 'Fully Applied' : ((float) $this->record->amount_applied > 0 ? 'Partially Applied' : 'Open'));
        $creditStatusColor = match ($creditStatus) {
            'Refunded' => 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-300',
            'Fully Applied' => 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300',
            'Partially Applied' => 'border-violet-200 bg-violet-50 text-violet-700 dark:border-violet-800 dark:bg-violet-950/40 dark:text-violet-300',
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
                    <p class="mt-1 text-2xl font-semibold text-gray-950 dark:text-white">{{ $currencyCode }} {{ number_format((float) $this->record->amount_applied, 2) }}</p>
                </div>
            </section>
            <section class="{{ $tableWrapper }}">
                <div class="px-4 py-4">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Remaining Credit</p>
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
                            <td class="{{ $cellLabelClass }}">Credit Memo Number</td>
                            <td class="{{ $cellValueClass }}">{{ $this->record->document_number }}</td>
                            <td class="{{ $cellLabelClass }}">Status</td>
                            <td class="{{ $cellValueClass }}">
                                <div class="flex items-center gap-2">
                                    <span>{{ $this->record->status }}</span>
                                    <span class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-medium {{ $creditStatusColor }}">
                                        {{ $creditStatus }}
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
                            <td class="{{ $cellLabelClass }}">Corrected Invoice</td>
                            <td class="{{ $cellValueClass }}">{{ $this->record->corrected_invoice_number ?: '—' }}</td>
                            <td class="{{ $cellLabelClass }}">Credit Memo Type</td>
                            <td class="{{ $cellValueClass }}">{{ $this->record->credit_memo_type ?: '—' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="{{ $tableWrapper }}">
            <div class="border-b border-gray-200 px-4 py-3 dark:border-gray-700">
                <h3 class="text-base font-semibold text-gray-950 dark:text-white">Customer Details</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="{{ $tableClass }}">
                    <tbody>
                        <tr>
                            <td class="{{ $cellLabelClass }}">Customer</td>
                            <td class="{{ $cellValueClass }}">{{ $this->record->customer_name }}</td>
                            <td class="{{ $cellLabelClass }}">Customer Address</td>
                            <td class="{{ $cellValueClass }}">{{ $this->record->customer_address ?: '—' }}</td>
                        </tr>
                        <tr>
                            <td class="{{ $cellLabelClass }}">Ship-to Name</td>
                            <td class="{{ $cellValueClass }}">{{ $this->record->ship_to_name ?: '—' }}</td>
                            <td class="{{ $cellLabelClass }}">Ship-to Address</td>
                            <td class="{{ $cellValueClass }}">{{ $this->record->ship_to_address ?: '—' }}</td>
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
                            <td class="{{ $bodyCellClass }}">Subtotal</td>
                            <td class="{{ $bodyCellClass }} text-right">{{ $currencyCode }} {{ number_format((float) $this->record->subtotal, 2) }}</td>
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
                            <td class="{{ $bodyCellClass }} text-right">{{ $currencyCode }} {{ number_format((float) $this->record->amount_applied, 2) }}</td>
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
            <div class="flex items-center justify-between gap-3 border-b border-gray-200 px-4 py-3 dark:border-gray-700">
                <h3 class="text-base font-semibold text-gray-950 dark:text-white">Applications</h3>
                <div class="flex items-center gap-2">
                    @if($this->applications->isNotEmpty())
                        <span class="inline-flex items-center rounded-full border border-success-200 bg-success-50 px-2.5 py-0.5 text-xs font-medium text-success-700 dark:border-success-800 dark:bg-success-950/40 dark:text-success-300">
                            Credit Applications
                        </span>
                    @endif
                    @if($this->record->refunded)
                        <span class="inline-flex items-center rounded-full border border-warning-200 bg-warning-50 px-2.5 py-0.5 text-xs font-medium text-warning-700 dark:border-warning-800 dark:bg-warning-950/40 dark:text-warning-300">
                            Refunded
                        </span>
                    @endif
                </div>
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
                        @forelse($this->applications as $application)
                            <tr>
                                <td class="{{ $bodyCellClass }}">{{ \Illuminate\Support\Carbon::parse($application['applied_at'])->format('Y-m-d H:i') }}</td>
                                <td class="{{ $bodyCellClass }}">
                                    <span class="inline-flex items-center rounded-full border border-violet-200 bg-violet-50 px-2.5 py-0.5 text-xs font-medium text-violet-700 dark:border-violet-800 dark:bg-violet-950/40 dark:text-violet-300">
                                        Credit Applied
                                    </span>
                                </td>
                                <td class="{{ $bodyCellClass }}">Sales Invoice</td>
                                <td class="{{ $bodyCellClass }}">
                                    @if(!empty($application['invoice_record_id']))
                                        <a href="{{ \App\Filament\Resources\SalesInvoices\SalesInvoiceResource::getUrl('view-posted', ['record' => $application['invoice_record_id']]) }}" class="font-medium text-primary-600 hover:underline dark:text-primary-400">
                                            {{ $application['document_number'] ?? '—' }}
                                        </a>
                                    @else
                                        {{ $application['document_number'] ?? '—' }}
                                    @endif
                                </td>
                                <td class="{{ $bodyCellClass }}">Credit memo application</td>
                                <td class="{{ $bodyCellClass }} text-right">{{ $currencyCode }} {{ number_format((float) ($application['amount'] ?? 0), 2) }}</td>
                                <td class="{{ $bodyCellClass }} text-right">—</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="border border-gray-200 px-4 py-6 text-center text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">No applications recorded for this posted sales credit memo yet.</td>
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
                            <th class="{{ $headCellClass }}">Item</th>
                            <th class="{{ $headCellClass }}">Description</th>
                            <th class="{{ $headCellClass }} text-right">Qty</th>
                            <th class="{{ $headCellClass }} text-right">Unit Price</th>
                            <th class="{{ $headCellClass }} text-right">Discount</th>
                            <th class="{{ $headCellClass }} text-right">Line Amount</th>
                            <th class="{{ $headCellClass }} text-right">VAT</th>
                            <th class="{{ $headCellClass }} text-right">Incl. VAT</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($this->record->lines as $line)
                            <tr>
                                <td class="{{ $bodyCellClass }}">{{ $line->line_number }}</td>
                                <td class="{{ $bodyCellClass }}">{{ $line->item_code ?: '—' }}</td>
                                <td class="{{ $bodyCellClass }}">{{ $line->item_description }}</td>
                                <td class="{{ $bodyCellClass }} text-right">{{ number_format((float) $line->quantity, 4) }}</td>
                                <td class="{{ $bodyCellClass }} text-right">{{ number_format((float) $line->unit_price, 4) }}</td>
                                <td class="{{ $bodyCellClass }} text-right">{{ number_format((float) $line->line_discount_amount, 4) }}</td>
                                <td class="{{ $bodyCellClass }} text-right">{{ number_format((float) $line->line_amount, 4) }}</td>
                                <td class="{{ $bodyCellClass }} text-right">{{ number_format((float) $line->vat_amount, 4) }}</td>
                                <td class="{{ $bodyCellClass }} text-right">{{ number_format((float) $line->amount_including_vat, 4) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="border border-gray-200 px-4 py-6 text-center text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">No posted lines available.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        @if($this->record->refunded)
            <section class="{{ $tableWrapper }}">
                <div class="flex items-center justify-between gap-3 border-b border-gray-200 px-4 py-3 dark:border-gray-700">
                    <h3 class="text-base font-semibold text-gray-950 dark:text-white">Refund</h3>
                    <span class="inline-flex items-center rounded-full border border-warning-200 bg-warning-50 px-2.5 py-0.5 text-xs font-medium text-warning-700 dark:border-warning-800 dark:bg-warning-950/40 dark:text-warning-300">
                        Refunded
                    </span>
                </div>
                <div class="overflow-x-auto">
                    <table class="{{ $tableClass }}">
                        <tbody>
                            <tr>
                                <td class="{{ $cellLabelClass }}">Refund Reference</td>
                                <td class="{{ $cellValueClass }}">{{ $this->record->refund_reference ?: '—' }}</td>
                                <td class="{{ $cellLabelClass }}">Refunded At</td>
                                <td class="{{ $cellValueClass }}">{{ optional($this->record->refunded_at)->format('Y-m-d H:i') ?: '—' }}</td>
                            </tr>
                            <tr>
                                <td class="{{ $cellLabelClass }}">Refund Amount</td>
                                <td class="{{ $cellValueClass }}">{{ $currencyCode }} {{ number_format((float) $this->record->refund_amount, 2) }}</td>
                                <td class="{{ $cellLabelClass }}">Refunded</td>
                                <td class="{{ $cellValueClass }}">Yes</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        @endif
    </div>
</x-filament-panels::page>
