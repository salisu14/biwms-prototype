<x-filament-panels::page>
    @php($reportData = $this->reportData())
    <div class="space-y-6">
        <x-filament::section class="print:hidden">
            <form wire:submit="generateReport" class="space-y-4">
                {{ $this->form }}
                <div class="flex justify-end gap-2">
                    <x-filament::button
                        tag="a"
                        color="info"
                        icon="heroicon-o-printer"
                        :href="route('reports.balance-sheet.print', [
                            'asOfDate' => $this->formData['asOfDate'] ?? null,
                        ])"
                        target="_blank"
                    >
                        Print
                    </x-filament::button>
                    <x-filament::button type="submit">Update Report</x-filament::button>
                </div>
            </form>
        </x-filament::section>

        @if(!empty($reportData))
            <x-filament::section>
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="text-lg font-semibold">Balance Sheet</h2>
                    <p class="text-sm text-gray-500">As of {{ $reportData['as_of_date'] }}</p>
                </div>

                <div class="overflow-x-auto">
                    <table class="report-table w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 dark:border-gray-700">
                                <th class="px-3 py-2 text-left font-semibold">Account</th>
                                <th class="px-3 py-2 text-right font-semibold">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($reportData['lines'] as $line)
                                <tr class="border-b border-gray-100 dark:border-gray-800 {{ $line['bold'] ? 'font-semibold' : '' }}">
                                    <td class="px-3 py-2" style="padding-left: {{ 12 + (($line['indentation'] ?? 0) * 16) }}px;">
                                        {{ $line['account_no'] }} - {{ $line['description'] }}
                                    </td>
                                    <td class="px-3 py-2 text-right tabular-nums">
                                        {{ number_format((float) ($line['amount'] ?? 0), 2) }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="border-t-2 border-gray-300 dark:border-gray-600 font-semibold">
                                <td class="px-3 py-2">Total Assets</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ number_format((float) $reportData['totals']['assets'], 2) }}</td>
                            </tr>
                            <tr class="font-semibold">
                                <td class="px-3 py-2">Total Liabilities</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ number_format((float) $reportData['totals']['liabilities'], 2) }}</td>
                            </tr>
                            <tr class="font-semibold">
                                <td class="px-3 py-2">Total Equity</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ number_format((float) $reportData['totals']['equity'], 2) }}</td>
                            </tr>
                            <tr class="border-t-2 border-gray-300 dark:border-gray-600 font-semibold">
                                <td class="px-3 py-2">Total Liabilities + Equity</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ number_format((float) $reportData['totals']['liabilities_and_equity'], 2) }}</td>
                            </tr>
                            <tr class="font-semibold {{ abs((float) $reportData['totals']['difference']) > 0.01 ? 'text-danger-600' : 'text-success-600' }}">
                                <td class="px-3 py-2">Balance Difference</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ number_format((float) $reportData['totals']['difference'], 2) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>
@include('filament.components.report-table-styles')
<style>
    @media print {
        @page { size: A4 portrait; margin: 12mm; }
        .fi-sidebar,
        .fi-topbar,
        .fi-header,
        .fi-breadcrumbs,
        .print\:hidden {
            display: none !important;
        }
        .fi-main,
        .fi-page,
        .fi-page-content {
            margin: 0 !important;
            padding: 0 !important;
            max-width: 100% !important;
            width: 100% !important;
        }
    }
</style>
