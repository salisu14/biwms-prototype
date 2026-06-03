<x-filament-panels::page>
    @include('filament.components.report-table-styles')
    @php($report = $this->reportData())

    <div class="space-y-6">
        <x-filament::section>
            <form wire:submit="generateReport" class="space-y-4">
                {{ $this->form }}
                <div class="flex justify-end">
                    <x-filament::button type="submit">Refresh</x-filament::button>
                </div>
            </form>
        </x-filament::section>

        <x-filament::section>
            <div class="mb-4">
                <h2 class="text-lg font-semibold">Expense Snapshot</h2>
                <p class="text-sm text-gray-500">
                    {{ $report['period']['start'] }} to {{ $report['period']['end'] }} ({{ ucfirst($report['period']['mode']) }})
                    @if(filled($report['period']['category_code'] ?? null))
                        · Category: {{ $report['period']['category_code'] }}
                    @endif
                    @if(filled($report['period']['expense_type'] ?? null))
                        · Type: {{ ucfirst($report['period']['expense_type']) }}
                    @endif
                </p>
            </div>
            <div class="overflow-x-auto">
                <table class="expense-report-table w-full table-auto text-sm">
                    <thead>
                        <tr class="bg-gray-50 dark:bg-gray-800/60">
                            <th class="border border-gray-200 px-3 py-2 text-left dark:border-gray-700">Metric</th>
                            <th class="border border-gray-200 px-3 py-2 text-right dark:border-gray-700">Value</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="border border-gray-200 px-3 py-2 dark:border-gray-700">Total Expense</td>
                            <td class="border border-gray-200 px-3 py-2 text-right dark:border-gray-700">NGN {{ number_format($report['summary']['total_amount'], 2) }}</td>
                        </tr>
                        <tr>
                            <td class="border border-gray-200 px-3 py-2 dark:border-gray-700">Total VAT</td>
                            <td class="border border-gray-200 px-3 py-2 text-right dark:border-gray-700">NGN {{ number_format($report['summary']['total_vat'], 2) }}</td>
                        </tr>
                        <tr>
                            <td class="border border-gray-200 px-3 py-2 dark:border-gray-700">Transactions</td>
                            <td class="border border-gray-200 px-3 py-2 text-right dark:border-gray-700">{{ number_format($report['summary']['count']) }}</td>
                        </tr>
                        <tr>
                            <td class="border border-gray-200 px-3 py-2 dark:border-gray-700">Average Per Transaction</td>
                            <td class="border border-gray-200 px-3 py-2 text-right dark:border-gray-700">NGN {{ number_format($report['summary']['average'], 2) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </x-filament::section>

        <x-filament::section>
            <h3 class="mb-3 text-base font-semibold">By Category</h3>
            <div class="overflow-x-auto">
                <table class="expense-report-table w-full table-auto text-sm">
                    <thead>
                        <tr class="bg-gray-50 dark:bg-gray-800/60">
                            <th class="border border-gray-200 px-3 py-2 text-left dark:border-gray-700">Category</th>
                            <th class="border border-gray-200 px-3 py-2 text-right dark:border-gray-700">Count</th>
                            <th class="border border-gray-200 px-3 py-2 text-right dark:border-gray-700">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($report['by_category'] as $row)
                            <tr>
                                <td class="border border-gray-200 px-3 py-2 dark:border-gray-700">{{ $row['category_code'] }}</td>
                                <td class="border border-gray-200 px-3 py-2 text-right dark:border-gray-700">{{ number_format($row['count']) }}</td>
                                <td class="border border-gray-200 px-3 py-2 text-right dark:border-gray-700">NGN {{ number_format($row['amount'], 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="border border-gray-200 px-3 py-3 text-center text-gray-500 dark:border-gray-700">No posted expenses in this period.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>

        <x-filament::section>
            <h3 class="mb-3 text-base font-semibold">By Type</h3>
            <div class="overflow-x-auto">
                <table class="expense-report-table w-full table-auto text-sm">
                    <thead>
                        <tr class="bg-gray-50 dark:bg-gray-800/60">
                            <th class="border border-gray-200 px-3 py-2 text-left dark:border-gray-700">Type</th>
                            <th class="border border-gray-200 px-3 py-2 text-right dark:border-gray-700">Count</th>
                            <th class="border border-gray-200 px-3 py-2 text-right dark:border-gray-700">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($report['by_type'] as $row)
                            <tr>
                                <td class="border border-gray-200 px-3 py-2 dark:border-gray-700">{{ ucfirst($row['expense_type']) }}</td>
                                <td class="border border-gray-200 px-3 py-2 text-right dark:border-gray-700">{{ number_format($row['count']) }}</td>
                                <td class="border border-gray-200 px-3 py-2 text-right dark:border-gray-700">NGN {{ number_format($row['amount'], 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="border border-gray-200 px-3 py-3 text-center text-gray-500 dark:border-gray-700">No posted expenses in this period.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>

        <x-filament::section>
            <h3 class="mb-3 text-base font-semibold">Trend (Bucketed)</h3>
            <div class="overflow-x-auto">
                <table class="expense-report-table w-full table-auto text-sm">
                    <thead>
                        <tr class="bg-gray-50 dark:bg-gray-800/60">
                            <th class="border border-gray-200 px-3 py-2 text-left dark:border-gray-700">Bucket</th>
                            <th class="border border-gray-200 px-3 py-2 text-right dark:border-gray-700">Count</th>
                            <th class="border border-gray-200 px-3 py-2 text-right dark:border-gray-700">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($report['trend'] as $row)
                            <tr>
                                <td class="border border-gray-200 px-3 py-2 dark:border-gray-700">{{ $row['bucket'] }}</td>
                                <td class="border border-gray-200 px-3 py-2 text-right dark:border-gray-700">{{ number_format($row['count']) }}</td>
                                <td class="border border-gray-200 px-3 py-2 text-right dark:border-gray-700">NGN {{ number_format($row['amount'], 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="border border-gray-200 px-3 py-3 text-center text-gray-500 dark:border-gray-700">No trend data for this period.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>

        <x-filament::section>
            <h3 class="mb-3 text-base font-semibold">Latest Posted Expenses</h3>
            <div class="overflow-x-auto">
                <table class="expense-report-table w-full table-auto text-sm">
                    <thead>
                        <tr class="bg-gray-50 dark:bg-gray-800/60">
                            <th class="border border-gray-200 px-3 py-2 text-left dark:border-gray-700">Doc No.</th>
                            <th class="border border-gray-200 px-3 py-2 text-left dark:border-gray-700">Date</th>
                            <th class="border border-gray-200 px-3 py-2 text-left dark:border-gray-700">Category</th>
                            <th class="border border-gray-200 px-3 py-2 text-left dark:border-gray-700">Type</th>
                            <th class="border border-gray-200 px-3 py-2 text-right dark:border-gray-700">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($report['latest'] as $row)
                            <tr>
                                <td class="border border-gray-200 px-3 py-2 dark:border-gray-700">
                                    <a href="{{ $row['source_url'] }}" class="font-medium text-primary-600 hover:underline dark:text-primary-400">
                                        {{ $row['document_no'] }}
                                    </a>
                                </td>
                                <td class="border border-gray-200 px-3 py-2 dark:border-gray-700">{{ $row['posting_date'] }}</td>
                                <td class="border border-gray-200 px-3 py-2 dark:border-gray-700">{{ $row['category_code'] ?? '-' }}</td>
                                <td class="border border-gray-200 px-3 py-2 dark:border-gray-700">{{ ucfirst($row['expense_type'] ?? 'unspecified') }}</td>
                                <td class="border border-gray-200 px-3 py-2 text-right dark:border-gray-700">NGN {{ number_format((float) $row['amount'], 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="border border-gray-200 px-3 py-3 text-center text-gray-500 dark:border-gray-700">No posted expenses found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
