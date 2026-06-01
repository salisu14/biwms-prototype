<x-filament-panels::page>
    @php($report = $this->reportData())

    <div class="space-y-6">
        <x-filament::section class="print:hidden">
            <form wire:submit="generateReport" class="space-y-4">
                {{ $this->form }}
                <div class="flex justify-end gap-2">
                    <x-filament::button
                        tag="a"
                        color="info"
                        icon="heroicon-o-printer"
                        :href="route('reports.group-summary.print', [
                            'startDate' => $this->formData['startDate'] ?? null,
                            'endDate' => $this->formData['endDate'] ?? null,
                            'category' => $this->formData['category'] ?? null,
                            'includeSubLedgers' => ($this->formData['includeSubLedgers'] ?? true) ? 1 : 0,
                        ])"
                        target="_blank"
                    >
                        Print
                    </x-filament::button>
                    <x-filament::button type="submit">Update Statement</x-filament::button>
                </div>
            </form>
        </x-filament::section>

        <x-filament::section>
            <div class="mb-4 flex items-center justify-between">
                <div>
                    <h2 class="text-lg font-semibold">{{ $report['report_type'] === 'GROUP_SUMMARY' ? 'Group Summary' : 'Trial Balance' }}</h2>
                    <p class="text-sm text-gray-500">Closing balance ({{ $report['period']['start'] }} - {{ $report['period']['end'] }})</p>
                </div>
                <div class="text-sm {{ $report['is_balanced'] ? 'text-success-600' : 'text-danger-600' }}">
                    {{ $report['is_balanced'] ? 'Balanced' : 'Unbalanced' }}
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="report-table w-full table-fixed text-sm">
                    <colgroup>
                        <col style="width: 56%;">
                        <col style="width: 22%;">
                        <col style="width: 22%;">
                    </colgroup>
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-gray-700">
                            <th class="px-5 py-3 text-left font-semibold">Group / Ledger</th>
                            <th class="border-l border-gray-200 px-8 py-3 pr-12 text-right font-semibold dark:border-gray-700">Debit</th>
                            <th class="border-l-2 border-gray-300 py-3 pl-12 pr-8 text-right font-semibold dark:border-gray-600">Credit</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($report['groups'] as $group)
                            <tr class="border-b border-gray-100 bg-gray-50 font-semibold dark:border-gray-800 dark:bg-gray-900/40">
                                <td class="px-6 py-3">{{ $group['label'] }}</td>
                                <td class="whitespace-nowrap border-l border-gray-100 px-8 py-3 pr-12 text-right tabular-nums dark:border-gray-800">NGN {{ number_format((float) $group['debit'], 2) }}</td>
                                <td class="whitespace-nowrap border-l-2 border-gray-200 py-3 pl-12 pr-8 text-right tabular-nums dark:border-gray-700">NGN {{ number_format((float) $group['credit'], 2) }}</td>
                            </tr>

                            @foreach($group['ledgers'] as $ledger)
                                <tr class="border-b border-gray-100 dark:border-gray-800">
                                    <td class="px-6 py-3 pl-12 text-gray-600 dark:text-gray-300">{{ $ledger['account_no'] }} - {{ $ledger['name'] }}</td>
                                    <td class="whitespace-nowrap border-l border-gray-100 px-8 py-3 pr-12 text-right tabular-nums dark:border-gray-800">NGN {{ number_format((float) $ledger['display_debit'], 2) }}</td>
                                    <td class="whitespace-nowrap border-l-2 border-gray-200 py-3 pl-12 pr-8 text-right tabular-nums dark:border-gray-700">NGN {{ number_format((float) $ledger['display_credit'], 2) }}</td>
                                </tr>
                            @endforeach
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="3" class="py-5"></td>
                        </tr>
                        <tr class="bg-primary-50 text-primary-900 ring-1 ring-primary-200 dark:bg-primary-900/20 dark:text-primary-100 dark:ring-primary-700/40">
                            <td class="px-6 py-5 text-base font-black uppercase tracking-wide">Grand Total</td>
                            <td class="whitespace-nowrap border-l border-primary-200 px-8 py-5 pr-12 text-right text-lg font-black tabular-nums dark:border-primary-700/40">NGN {{ number_format((float) $report['grand_total']['debit'], 2) }}</td>
                            <td class="whitespace-nowrap border-l-2 border-primary-300 py-5 pl-12 pr-8 text-right text-lg font-black tabular-nums dark:border-primary-600/50">NGN {{ number_format((float) $report['grand_total']['credit'], 2) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
<style>
    .report-table {
        border-collapse: collapse;
    }

    .report-table th,
    .report-table td {
        border: 1px solid #94a3b8 !important;
        padding: 10px 14px !important;
        line-height: 1.45 !important;
    }

    .dark .report-table th,
    .dark .report-table td {
        border-color: #64748b !important;
    }

    .report-table th {
        font-weight: 600;
    }

    @media print {
        @page { size: A4 portrait; margin: 12mm; }

        .fi-sidebar,
        .fi-topbar,
        .fi-header,
        .fi-breadcrumbs,
        .fi-global-search,
        .print\:hidden {
            display: none !important;
            visibility: hidden !important;
        }

        .fi-main,
        .fi-page,
        .fi-page-content,
        .fi-section,
        .fi-section-content,
        .fi-ta,
        .fi-ta-content {
            display: block !important;
            visibility: visible !important;
            width: 100% !important;
            max-width: 100% !important;
            margin: 0 !important;
            padding: 0 !important;
            overflow: visible !important;
        }

        body {
            background: #fff !important;
            color: #000 !important;
        }

        .fi-page *,
        .fi-page-content *,
        .fi-section *,
        .fi-section-content * {
            color: #000 !important;
            -webkit-text-fill-color: #000 !important;
            text-shadow: none !important;
            background-image: none !important;
        }

        table {
            border-collapse: collapse !important;
            border-spacing: 0 !important;
        }

        tr, td, th {
            page-break-inside: avoid;
        }
    }
</style>
