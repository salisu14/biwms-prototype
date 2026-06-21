<x-filament-panels::page>
    @php
        $currencyCode = $this->record->currency_code ?: 'NGN';
        $tableWrapper = 'overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900';
        $tableClass = 'min-w-full border-collapse text-sm';
        $headClass = 'bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-600 dark:bg-gray-800 dark:text-gray-300';
        $cellLabelClass = 'w-48 whitespace-nowrap border border-gray-200 bg-gray-50 px-4 py-3 align-top text-sm font-medium text-gray-600 dark:border-gray-700 dark:bg-gray-800/70 dark:text-gray-300';
        $cellValueClass = 'border border-gray-200 px-4 py-3 align-top text-sm text-gray-900 dark:border-gray-700 dark:text-gray-100';
        $headCellClass = 'border border-gray-200 px-4 py-3 dark:border-gray-700';
        $bodyCellClass = 'border border-gray-200 px-4 py-3 align-top text-sm text-gray-900 dark:border-gray-700 dark:text-gray-100';
        $bodyCellMutedClass = 'border border-gray-200 bg-gray-50 px-4 py-3 align-top text-sm font-medium text-gray-600 dark:border-gray-700 dark:bg-gray-800/70 dark:text-gray-300';
        $numericCellClass = $bodyCellClass.' whitespace-nowrap text-right tabular-nums';
        $sectionHeaderClass = 'border-b border-gray-200 bg-gray-50/70 px-4 py-3 dark:border-gray-700 dark:bg-gray-800/50';
        $money = fn ($value, int $precision = 2): string => $currencyCode.' '.number_format((float) $value, $precision);
        $date = fn ($value): string => optional($value)->format('Y-m-d') ?: '—';
        $dateTime = fn ($value): string => optional($value)->format('Y-m-d H:i') ?: '—';
        $invoiceStatus = $this->record->paid_in_full ? 'Paid in Full' : ((float) $this->record->amount_paid > 0 ? 'Partially Paid' : 'Open');
        $invoiceStatusColor = match ($invoiceStatus) {
            'Paid in Full' => 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300',
            'Partially Paid' => 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-300',
            default => 'border-sky-200 bg-sky-50 text-sky-700 dark:border-sky-800 dark:bg-sky-950/40 dark:text-sky-300',
        };
    @endphp

    <div class="space-y-6">
        <section class="{{ $tableWrapper }}">
            <div class="{{ $sectionHeaderClass }}">
                <h3 class="text-base font-semibold text-gray-950 dark:text-white">PSI Summary</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="{{ $tableClass }}">
                    <tbody>
                        <tr>
                            <td class="{{ $bodyCellMutedClass }}">Invoice Number</td>
                            <td class="{{ $bodyCellClass }} font-semibold">{{ $this->record->document_number }}</td>
                            <td class="{{ $bodyCellMutedClass }}">Customer</td>
                            <td class="{{ $bodyCellClass }}">{{ $this->record->customer_name }}</td>
                        </tr>
                        <tr>
                            <td class="{{ $bodyCellMutedClass }}">Grand Total</td>
                            <td class="{{ $numericCellClass }} font-semibold">{{ $money($this->record->grand_total) }}</td>
                            <td class="{{ $bodyCellMutedClass }}">Amount Paid</td>
                            <td class="{{ $numericCellClass }}">{{ $money($this->record->amount_paid) }}</td>
                        </tr>
                        <tr>
                            <td class="{{ $bodyCellMutedClass }}">Remaining Amount</td>
                            <td class="{{ $numericCellClass }} font-semibold">{{ $money($this->record->remaining_amount) }}</td>
                            <td class="{{ $bodyCellMutedClass }}">Payment Status</td>
                            <td class="{{ $bodyCellClass }}">
                                <span class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-medium {{ $invoiceStatusColor }}">
                                    {{ $invoiceStatus }}
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="{{ $tableWrapper }}">
            <div class="{{ $sectionHeaderClass }}">
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
                            <td class="{{ $cellValueClass }}">{{ $date($this->record->posting_date) }}</td>
                            <td class="{{ $cellLabelClass }}">Posted At</td>
                            <td class="{{ $cellValueClass }}">{{ $dateTime($this->record->posted_at) }}</td>
                        </tr>
                        <tr>
                            <td class="{{ $cellLabelClass }}">Document Date</td>
                            <td class="{{ $cellValueClass }}">{{ $date($this->record->document_date) }}</td>
                            <td class="{{ $cellLabelClass }}">Due Date</td>
                            <td class="{{ $cellValueClass }}">{{ $date($this->record->due_date) }}</td>
                        </tr>
                        <tr>
                            <td class="{{ $cellLabelClass }}">Order Number</td>
                            <td class="{{ $cellValueClass }}">{{ $this->record->order_number ?: '—' }}</td>
                            <td class="{{ $cellLabelClass }}">Currency</td>
                            <td class="{{ $cellValueClass }}">{{ $currencyCode }}</td>
                        </tr>
                        <tr>
                            <td class="{{ $cellLabelClass }}">Related Shipment</td>
                            <td class="{{ $cellValueClass }}">
                                @if($this->relatedShipment)
                                    <a href="{{ \App\Filament\Resources\SalesShipmentHeaders\SalesShipmentHeaderResource::getUrl('view', ['record' => $this->relatedShipment]) }}" class="font-medium text-primary-600 hover:underline dark:text-primary-400">
                                        {{ $this->relatedShipment->document_no }}
                                    </a>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="{{ $cellLabelClass }}">Waybill</td>
                            <td class="{{ $cellValueClass }}">
                                @if($this->relatedShipment)
                                    <a href="{{ route('waybill.print', $this->relatedShipment) }}" target="_blank" class="inline-flex items-center rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-xs font-medium text-emerald-700 hover:bg-emerald-100 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300 dark:hover:bg-emerald-950/60">
                                        Print Waybill
                                    </a>
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="{{ $tableWrapper }}">
            <div class="{{ $sectionHeaderClass }}">
                <h3 class="text-base font-semibold text-gray-950 dark:text-white">Customer & Shipping</h3>
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
            <div class="{{ $sectionHeaderClass }}">
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
                            <td class="{{ $numericCellClass }}">{{ $money($this->record->subtotal) }}</td>
                        </tr>
                        <tr>
                            <td class="{{ $bodyCellClass }}">Line Discount</td>
                            <td class="{{ $numericCellClass }}">{{ $money($this->record->line_discount_total) }}</td>
                        </tr>
                        <tr>
                            <td class="{{ $bodyCellClass }}">Invoice Discount</td>
                            <td class="{{ $numericCellClass }}">{{ $money($this->record->invoice_discount_amount) }}</td>
                        </tr>
                        <tr>
                            <td class="{{ $bodyCellClass }}">Total Amount</td>
                            <td class="{{ $numericCellClass }}">{{ $money($this->record->total_amount) }}</td>
                        </tr>
                        <tr>
                            <td class="{{ $bodyCellClass }}">VAT</td>
                            <td class="{{ $numericCellClass }}">{{ $money($this->record->total_vat) }}</td>
                        </tr>
                        <tr>
                            <td class="{{ $bodyCellClass }} font-semibold">Grand Total</td>
                            <td class="{{ $numericCellClass }} font-semibold">{{ $money($this->record->grand_total) }}</td>
                        </tr>
                        <tr>
                            <td class="{{ $bodyCellClass }}">Amount Paid</td>
                            <td class="{{ $numericCellClass }}">{{ $money($this->record->amount_paid) }}</td>
                        </tr>
                        <tr>
                            <td class="{{ $bodyCellClass }} font-semibold">Remaining Amount</td>
                            <td class="{{ $numericCellClass }} font-semibold">{{ $money($this->record->remaining_amount) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="{{ $tableWrapper }}">
            <div class="{{ $sectionHeaderClass }}">
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
                        @forelse($this->applications as $application)
                            <tr>
                                <td class="{{ $bodyCellClass }} whitespace-nowrap">{{ $dateTime($application['applied_at']) }}</td>
                                <td class="{{ $bodyCellClass }}">
                                    @php
                                        $applicationStatusClass = $application['source_type'] === 'Credit Memo'
                                            ? 'border-violet-200 bg-violet-50 text-violet-700 dark:border-violet-800 dark:bg-violet-950/40 dark:text-violet-300'
                                            : 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300';
                                    @endphp
                                    <span class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-medium {{ $applicationStatusClass }}">
                                        {{ $application['source_type'] === 'Credit Memo' ? 'Credit Applied' : 'Payment Applied' }}
                                    </span>
                                </td>
                                <td class="{{ $bodyCellClass }}">{{ $application['source_type'] }}</td>
                                <td class="{{ $bodyCellClass }}">
                                    @if (! empty($application['source_url']))
                                        <a href="{{ $application['source_url'] }}" class="font-medium text-primary-600 hover:underline dark:text-primary-400">
                                            {{ $application['source_document'] ?: '—' }}
                                        </a>
                                    @else
                                        {{ $application['source_document'] ?: '—' }}
                                    @endif
                                </td>
                                <td class="{{ $bodyCellClass }}">{{ $application['reference'] ?: '—' }}</td>
                                <td class="{{ $numericCellClass }}">{{ $money($application['amount']) }}</td>
                                <td class="{{ $numericCellClass }}">
                                    @if (is_null($application['balance_after']))
                                        —
                                    @else
                                        {{ $money($application['balance_after']) }}
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="border border-gray-200 px-4 py-6 text-center text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">No payment or credit memo applications recorded yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="{{ $tableWrapper }}">
            <div class="{{ $sectionHeaderClass }}">
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
                            <th class="{{ $headCellClass }}">UOM</th>
                            <th class="{{ $headCellClass }} text-right">Unit Price</th>
                            <th class="{{ $headCellClass }} text-right">Disc. %</th>
                            <th class="{{ $headCellClass }} text-right">Discount</th>
                            <th class="{{ $headCellClass }} text-right">Line Amount</th>
                            <th class="{{ $headCellClass }} text-right">VAT %</th>
                            <th class="{{ $headCellClass }} text-right">VAT Amount</th>
                            <th class="{{ $headCellClass }} text-right">Incl. VAT</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($this->record->lines as $line)
                            <tr>
                                <td class="{{ $bodyCellClass }} whitespace-nowrap">{{ $line->line_number }}</td>
                                <td class="{{ $bodyCellClass }}">{{ $line->item_code ?: '—' }}</td>
                                <td class="{{ $bodyCellClass }}">{{ $line->item_description }}</td>
                                <td class="{{ $numericCellClass }}">{{ number_format((float) $line->quantity, 4) }}</td>
                                <td class="{{ $bodyCellClass }} whitespace-nowrap">{{ $line->unit_of_measure_code ?: '—' }}</td>
                                <td class="{{ $numericCellClass }}">{{ $money($line->unit_price, 4) }}</td>
                                <td class="{{ $numericCellClass }}">{{ number_format((float) $line->line_discount_percent, 2) }}%</td>
                                <td class="{{ $numericCellClass }}">{{ $money($line->line_discount_amount, 4) }}</td>
                                <td class="{{ $numericCellClass }}">{{ $money($line->line_amount, 4) }}</td>
                                <td class="{{ $numericCellClass }}">{{ number_format((float) $line->vat_percentage, 2) }}%</td>
                                <td class="{{ $numericCellClass }}">{{ $money($line->vat_amount, 4) }}</td>
                                <td class="{{ $numericCellClass }} font-semibold">{{ $money($line->amount_including_vat, 4) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="12" class="border border-gray-200 px-4 py-6 text-center text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">No posted lines available.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-filament-panels::page>
