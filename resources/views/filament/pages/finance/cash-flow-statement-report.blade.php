<x-filament-panels::page>
    @php($reportData = $this->reportData())
    @php($cashFlowScheduleId = $reportData['mapping']['cash_flow_schedule_id'] ?? null)

    <div class="space-y-6">
        <x-filament::section class="print:hidden">
            <form wire:submit="generateReport" class="space-y-4">
                {{ $this->form }}

                <div class="flex justify-end gap-2">
                    <x-filament::button
                        tag="a"
                        color="gray"
                        icon="heroicon-o-arrow-down-tray"
                        :href="route('reports.cash-flow.export', [
                            'format' => 'csv',
                            'startDate' => $this->formData['startDate'] ?? null,
                            'endDate' => $this->formData['endDate'] ?? null,
                            'compareStartDate' => $this->formData['compareStartDate'] ?? null,
                            'compareEndDate' => $this->formData['compareEndDate'] ?? null,
                            'method' => $this->formData['method'] ?? 'indirect',
                            'cashFlowScheduleId' => $this->formData['cashFlowScheduleId'] ?? null,
                            'profitAndLossScheduleId' => $this->formData['profitAndLossScheduleId'] ?? null,
                            'balanceSheetScheduleId' => $this->formData['balanceSheetScheduleId'] ?? null,
                        ])"
                    >
                        CSV
                    </x-filament::button>
                    <x-filament::button
                        tag="a"
                        color="info"
                        icon="heroicon-o-printer"
                        :href="route('reports.cash-flow.print', [
                            'startDate' => $this->formData['startDate'] ?? null,
                            'endDate' => $this->formData['endDate'] ?? null,
                            'compareStartDate' => $this->formData['compareStartDate'] ?? null,
                            'compareEndDate' => $this->formData['compareEndDate'] ?? null,
                            'method' => $this->formData['method'] ?? 'indirect',
                            'cashFlowScheduleId' => $this->formData['cashFlowScheduleId'] ?? null,
                            'profitAndLossScheduleId' => $this->formData['profitAndLossScheduleId'] ?? null,
                            'balanceSheetScheduleId' => $this->formData['balanceSheetScheduleId'] ?? null,
                        ])"
                        target="_blank"
                    >
                        Print
                    </x-filament::button>

                    <x-filament::button type="submit">
                        Update Report
                    </x-filament::button>
                </div>
            </form>
        </x-filament::section>

        <x-filament::section class="print:hidden">
            <div class="space-y-2 text-sm text-gray-600 dark:text-gray-300">
                <h3 class="font-semibold text-gray-900 dark:text-white">Account Schedule Usage Guide</h3>
                <p>Use the Cash Flow Schedule to define which account ranges drive receivables, inventory, payables, capital expenditures, debt, and equity classification.</p>
                <p>The report prefers `Default Cash Flow Statement`, then falls back to Balance Sheet and Profit & Loss schedules, then to COA category mapping.</p>
                <p>If the layout is altered accidentally, use `Run/Repair Default Cash Flow` from Account Schedules to restore the seeded default rows.</p>
                <p>Comparison columns show current period, prior period, variance amount, and variance percent so Finance can spot liquidity movement faster.</p>
            </div>
        </x-filament::section>

        @if(! empty($reportData))
            @if(($reportData['mapping']['mode'] ?? null) !== 'cash_flow_schedule')
                <x-filament::section class="border-warning-200 bg-warning-50 dark:border-warning-900 dark:bg-warning-950/20 print:hidden">
                    <div class="space-y-1 text-sm">
                        <p class="font-semibold text-warning-800 dark:text-warning-200">Cash Flow Schedule Missing</p>
                        <p class="text-warning-700 dark:text-warning-300">
                            No dedicated cash flow schedule is driving this report yet. The statement is using
                            {{ ($reportData['mapping']['mode'] ?? null) === 'schedule' ? 'Balance Sheet / Profit & Loss schedule fallback' : 'Chart of Accounts fallback' }}.
                            Create or select <strong>Default Cash Flow Statement</strong> to make Finance mapping explicit.
                        </p>
                        @if($cashFlowScheduleId)
                            <div class="pt-2">
                                <x-filament::button
                                    tag="a"
                                    size="sm"
                                    color="warning"
                                    icon="heroicon-o-arrow-top-right-on-square"
                                    :href="\App\Filament\Resources\AccountSchedules\AccountScheduleResource::getUrl('edit', ['record' => $cashFlowScheduleId])"
                                >
                                    Open Cash Flow Schedule
                                </x-filament::button>
                            </div>
                        @endif
                    </div>
                </x-filament::section>
            @endif

            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4 print:hidden">
                <x-filament::section>
                    <div class="space-y-1">
                        <p class="text-sm text-gray-500">Opening Cash</p>
                        <p class="text-2xl font-semibold tabular-nums">{{ number_format((float) $reportData['opening_cash'], 2) }}</p>
                        @if(isset($reportData['comparison_summary']))
                            <p class="text-xs text-gray-500">Prior: {{ number_format((float) $reportData['comparison_summary']['opening_cash'], 2) }}</p>
                        @endif
                    </div>
                </x-filament::section>

                <x-filament::section>
                    <div class="space-y-1">
                        <p class="text-sm text-gray-500">Net Cash from Operations</p>
                        <p class="text-2xl font-semibold tabular-nums">{{ number_format((float) ($reportData['sections']['operating']['total'] ?? 0), 2) }}</p>
                        @if(isset($reportData['compare_period']))
                            <p class="text-xs text-gray-500">Prior: {{ number_format((float) ($reportData['sections']['operating']['compare_total'] ?? 0), 2) }}</p>
                        @endif
                    </div>
                </x-filament::section>

                <x-filament::section>
                    <div class="space-y-1">
                        <p class="text-sm text-gray-500">Net Cash from Investing</p>
                        <p class="text-2xl font-semibold tabular-nums">{{ number_format((float) ($reportData['sections']['investing']['total'] ?? 0), 2) }}</p>
                        @if(isset($reportData['compare_period']))
                            <p class="text-xs text-gray-500">Prior: {{ number_format((float) ($reportData['sections']['investing']['compare_total'] ?? 0), 2) }}</p>
                        @endif
                    </div>
                </x-filament::section>

                <x-filament::section>
                    <div class="space-y-1">
                        <p class="text-sm text-gray-500">Ending Cash</p>
                        <p class="text-2xl font-semibold tabular-nums">{{ number_format((float) $reportData['ending_cash'], 2) }}</p>
                        @if(isset($reportData['comparison_summary']))
                            <p class="text-xs text-gray-500">Prior: {{ number_format((float) $reportData['comparison_summary']['ending_cash'], 2) }}</p>
                        @endif
                    </div>
                </x-filament::section>
            </div>

            <x-filament::section>
                <div class="mb-4 flex flex-col gap-2 md:flex-row md:items-end md:justify-between">
                    <div>
                        <h2 class="text-lg font-semibold">Cash Flow Statement</h2>
                        <p class="text-sm text-gray-500">
                            Period: {{ $reportData['period']['start'] }} to {{ $reportData['period']['end'] }}
                        </p>
                        <p class="text-sm text-gray-500">
                            Method: {{ ucfirst($reportData['method']) }}
                        </p>
                        @if(isset($reportData['compare_period']))
                            <p class="text-sm text-gray-500">
                                Comparison: {{ $reportData['compare_period']['start'] }} to {{ $reportData['compare_period']['end'] }}
                            </p>
                        @endif
                        <p class="text-sm text-gray-500">
                            Mapping:
                            @if(($reportData['mapping']['mode'] ?? null) === 'cash_flow_schedule')
                                Cash Flow Schedule-driven
                                @if(!empty($reportData['mapping']['cash_flow_schedule']))
                                    | CF: {{ $reportData['mapping']['cash_flow_schedule'] }}
                                @endif
                                @if(!empty($reportData['mapping']['profit_and_loss_schedule']))
                                    | P&L: {{ $reportData['mapping']['profit_and_loss_schedule'] }}
                                @endif
                                @if(!empty($reportData['mapping']['balance_sheet_schedule']))
                                    | BS: {{ $reportData['mapping']['balance_sheet_schedule'] }}
                                @endif
                            @elseif(($reportData['mapping']['mode'] ?? null) === 'schedule')
                                Schedule-driven
                                @if(!empty($reportData['mapping']['profit_and_loss_schedule']))
                                    | P&L: {{ $reportData['mapping']['profit_and_loss_schedule'] }}
                                @endif
                                @if(!empty($reportData['mapping']['balance_sheet_schedule']))
                                    | BS: {{ $reportData['mapping']['balance_sheet_schedule'] }}
                                @endif
                            @else
                                COA category fallback
                            @endif
                        </p>
                        @if($cashFlowScheduleId)
                            <div class="mt-2">
                                <x-filament::button
                                    tag="a"
                                    size="sm"
                                    color="gray"
                                    icon="heroicon-o-arrow-top-right-on-square"
                                    :href="\App\Filament\Resources\AccountSchedules\AccountScheduleResource::getUrl('edit', ['record' => $cashFlowScheduleId])"
                                >
                                    Open Cash Flow Schedule
                                </x-filament::button>
                            </div>
                        @endif
                    </div>

                    <div class="text-sm text-gray-500">
                        Cash accounts:
                        {{ ! empty($reportData['cash_accounts']) ? implode(', ', $reportData['cash_accounts']) : 'None configured' }}
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="report-table w-full text-sm">
                        <thead>
                            <tr>
                                <th class="px-3 py-2 text-left font-semibold">Description</th>
                                <th class="px-3 py-2 text-right font-semibold">Amount</th>
                                @if(isset($reportData['compare_period']))
                                    <th class="px-3 py-2 text-right font-semibold">Prior Period</th>
                                    <th class="px-3 py-2 text-right font-semibold">Variance</th>
                                    <th class="px-3 py-2 text-right font-semibold">Variance %</th>
                                @endif
                            </tr>
                        </thead>

                        <tbody>
                            <tr class="font-semibold">
                                <td class="px-3 py-2">Beginning Cash Balance</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ number_format((float) $reportData['opening_cash'], 2) }}</td>
                                @if(isset($reportData['comparison_summary']))
                                    <td class="px-3 py-2 text-right tabular-nums">{{ number_format((float) $reportData['comparison_summary']['opening_cash'], 2) }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums">{{ number_format((float) $reportData['comparison_summary']['opening_cash_variance_amount'], 2) }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums">
                                        {{ $reportData['comparison_summary']['opening_cash_variance_percent'] !== null ? number_format((float) $reportData['comparison_summary']['opening_cash_variance_percent'], 1).'%' : '—' }}
                                    </td>
                                @endif
                            </tr>

                            @foreach($reportData['sections'] as $section)
                                <tr class="bg-gray-50 dark:bg-gray-900/40 font-semibold">
                                    <td class="px-3 py-2">{{ $section['label'] }}</td>
                                    <td class="px-3 py-2 text-right"></td>
                                    @if(isset($reportData['compare_period']))
                                        <td class="px-3 py-2 text-right"></td>
                                        <td class="px-3 py-2 text-right"></td>
                                        <td class="px-3 py-2 text-right"></td>
                                    @endif
                                </tr>

                                @forelse($section['lines'] as $line)
                                    <tr>
                                        <td class="px-3 py-2 pl-8">{{ $line['label'] }}</td>
                                        <td class="px-3 py-2 text-right tabular-nums">{{ number_format((float) $line['amount'], 2) }}</td>
                                        @if(isset($reportData['compare_period']))
                                            <td class="px-3 py-2 text-right tabular-nums">{{ number_format((float) ($line['compare_amount'] ?? 0), 2) }}</td>
                                            <td class="px-3 py-2 text-right tabular-nums">{{ number_format((float) ($line['variance_amount'] ?? 0), 2) }}</td>
                                            <td class="px-3 py-2 text-right tabular-nums">
                                                {{ ($line['variance_percent'] ?? null) !== null ? number_format((float) $line['variance_percent'], 1).'%' : '—' }}
                                            </td>
                                        @endif
                                    </tr>
                                @empty
                                    <tr>
                                        <td class="px-3 py-2 pl-8 text-gray-500">No movements for this section.</td>
                                        <td class="px-3 py-2 text-right text-gray-500">0.00</td>
                                        @if(isset($reportData['compare_period']))
                                            <td class="px-3 py-2 text-right text-gray-500">0.00</td>
                                            <td class="px-3 py-2 text-right text-gray-500">0.00</td>
                                            <td class="px-3 py-2 text-right text-gray-500">—</td>
                                        @endif
                                    </tr>
                                @endforelse

                                <tr class="font-semibold">
                                    <td class="px-3 py-2">Net Cash from {{ $section['label'] }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums">{{ number_format((float) $section['total'], 2) }}</td>
                                    @if(isset($reportData['compare_period']))
                                        <td class="px-3 py-2 text-right tabular-nums">{{ number_format((float) ($section['compare_total'] ?? 0), 2) }}</td>
                                        <td class="px-3 py-2 text-right tabular-nums">{{ number_format((float) ($section['variance_amount'] ?? 0), 2) }}</td>
                                        <td class="px-3 py-2 text-right tabular-nums">
                                            {{ ($section['variance_percent'] ?? null) !== null ? number_format((float) $section['variance_percent'], 1).'%' : '—' }}
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>

                        <tfoot>
                            <tr class="border-t-2 font-semibold">
                                <td class="px-3 py-2">Net Change in Cash</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ number_format((float) $reportData['net_change_in_cash'], 2) }}</td>
                                @if(isset($reportData['comparison_summary']))
                                    <td class="px-3 py-2 text-right tabular-nums">{{ number_format((float) $reportData['comparison_summary']['net_change_in_cash'], 2) }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums">{{ number_format((float) $reportData['comparison_summary']['net_change_in_cash_variance_amount'], 2) }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums">
                                        {{ $reportData['comparison_summary']['net_change_in_cash_variance_percent'] !== null ? number_format((float) $reportData['comparison_summary']['net_change_in_cash_variance_percent'], 1).'%' : '—' }}
                                    </td>
                                @endif
                            </tr>
                            <tr class="font-semibold">
                                <td class="px-3 py-2">Ending Cash Balance</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ number_format((float) $reportData['ending_cash'], 2) }}</td>
                                @if(isset($reportData['comparison_summary']))
                                    <td class="px-3 py-2 text-right tabular-nums">{{ number_format((float) $reportData['comparison_summary']['ending_cash'], 2) }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums">{{ number_format((float) $reportData['comparison_summary']['ending_cash_variance_amount'], 2) }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums">
                                        {{ $reportData['comparison_summary']['ending_cash_variance_percent'] !== null ? number_format((float) $reportData['comparison_summary']['ending_cash_variance_percent'], 1).'%' : '—' }}
                                    </td>
                                @endif
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
