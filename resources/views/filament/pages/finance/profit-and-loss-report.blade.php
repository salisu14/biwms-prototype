<x-filament-panels::page>
    <div class="space-y-6 fi-report-container">
        <!-- Filter Section: Hidden on Print -->
        <x-filament::section class="print:hidden">
            <form wire:submit="generateReport">
                {{ $this->form }}
                <div class="mt-4 flex justify-end gap-x-3">
                    <x-filament::button color="gray" wire:click="form.fill" type="button">
                        Reset
                    </x-filament::button>
                    <x-filament::button type="submit">
                        Update Report
                    </x-filament::button>
                </div>
            </form>
        </x-filament::section>

        @if($reportData)
            <!-- Executive Summary Cards: Visible on Screen, Hidden on Print (usually) -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 print:hidden">
                <x-filament::section>
                    <div class="flex flex-col">
                        <span class="text-sm text-gray-500 font-medium">Total Revenue</span>
                        <span class="text-2xl font-bold text-gray-950 dark:text-white">
                            {{ $reportData['totals']['revenue'] ?? $reportData['summary']['total_revenue'] ?? '0.00' }}
                        </span>
                    </div>
                </x-filament::section>

                <x-filament::section>
                    <div class="flex flex-col">
                        <span class="text-sm text-gray-500 font-medium">Gross Profit</span>
                        <span class="text-2xl font-bold text-emerald-600">
                            {{ $reportData['totals']['gross_profit'] ?? $reportData['summary']['gross_profit'] ?? '0.00' }}
                        </span>
                    </div>
                </x-filament::section>

                <x-filament::section>
                    <div class="flex flex-col">
                        <span class="text-sm text-gray-500 font-medium">Operating Expenses</span>
                        <span class="text-2xl font-bold text-amber-600">
                            {{ $reportData['totals']['operating_expenses'] ?? $reportData['summary']['operating_expenses'] ?? '0.00' }}
                        </span>
                    </div>
                </x-filament::section>

                <x-filament::section>
                    <div class="flex flex-col">
                        <span class="text-sm text-gray-500 font-medium">Net Income</span>
                        @php
                            $netIncome = $reportData['totals']['net_income'] ?? $reportData['summary']['net_income'] ?? 0;
                            $isLoss = str_contains((string)$netIncome, '-');
                        @endphp
                        <span class="text-2xl font-bold {{ $isLoss ? 'text-ruby-600' : 'text-emerald-600' }}">
                            {{ $netIncome }}
                        </span>
                    </div>
                </x-filament::section>
            </div>

            <!-- Main Report -->
            <x-filament::section>
                <!-- Print Header -->
                <div class="hidden print:block mb-8 border-b pb-4">
                    <div class="flex justify-between items-start">
                        <div>
                            <h1 class="text-2xl font-bold tracking-tight text-gray-950">
                                {{ $reportData['report_name'] ?? 'Profit & Loss Statement' }}
                            </h1>
                            <p class="text-lg font-medium text-gray-700">{{ config('app.company_name', 'BIWMS') }}</p>
                        </div>
                        <div class="text-right text-sm text-gray-500 space-y-1">
                            <p><strong>Period:</strong> {{ $reportData['period'] }}</p>
                            @if(isset($formData['dimension1']))
                                <p><strong>Department:</strong> {{ $formData['dimension1'] }}</p>
                            @endif
                            <p><strong>Currency:</strong> LCY</p>
                        </div>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left border-collapse min-w-[600px]">
                        <thead>
                            <tr class="border-b-2 border-gray-950 dark:border-white print:border-black">
                                <th class="py-3 px-4 font-bold uppercase tracking-wider text-gray-950 dark:text-white">Description</th>
                                <th class="py-3 px-4 text-right font-bold uppercase tracking-wider text-gray-950 dark:text-white">Amount</th>
                                @if(isset($reportData['lines'][0]['compare_amount']))
                                    <th class="py-3 px-4 text-right font-bold uppercase tracking-wider text-gray-900 dark:text-gray-300">Prior Period</th>
                                    <th class="py-3 px-4 text-right font-bold uppercase tracking-wider text-gray-900 dark:text-gray-300">Variance</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-white/5 print:divide-gray-200">
                            @foreach($reportData['lines'] as $line)
                                @php
                                    $isBold = $line['bold'] ?? false;
                                    $indent = ($line['indentation'] ?? 0);
                                    $amount = $line['amount'] ?? '0.00';
                                    $isNegative = str_contains((string)$amount, '-');
                                @endphp
                                <tr class="group hover:bg-gray-50 dark:hover:bg-white/5 transition-colors {{ $isBold ? 'font-bold bg-gray-50/30 dark:bg-white/5' : '' }}">
                                    <td class="py-2 px-4 relative">
                                        @if($indent > 0)
                                            <div class="absolute left-0 top-0 bottom-0 border-l border-gray-200 dark:border-gray-700 print:border-gray-300" style="margin-left: {{ $indent * 0.75 }}rem"></div>
                                        @endif
                                        <span style="padding-left: {{ $indent * 1.5 }}rem" class="inline-block">
                                            {{ $line['description'] }}
                                        </span>
                                    </td>
                                    <td class="py-2 px-4 text-right {{ $isNegative ? 'text-ruby-600 font-medium' : 'text-gray-900 dark:text-gray-100' }}">
                                        {{ $isNegative ? '(' . ltrim($amount, '-') . ')' : $amount }}
                                    </td>
                                    @if(isset($line['compare_amount']))
                                        <td class="py-2 px-4 text-right text-gray-500 dark:text-gray-400">
                                            {{ $line['compare_amount'] }}
                                        </td>
                                        <td class="py-2 px-4 text-right">
                                            @if($line['variance_percent'])
                                                <div class="flex items-center justify-end gap-x-1">
                                                    @php
                                                        $varPercent = (float) rtrim($line['variance_percent'], '%');
                                                        $isVarNegative = $varPercent < 0;
                                                    @endphp
                                                    <span class="text-xs font-semibold px-2 py-0.5 rounded-full {{ $isVarNegative ? 'bg-ruby-50 text-ruby-700 dark:bg-ruby-500/10 dark:text-ruby-400' : 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400' }}">
                                                        {{ $line['variance_percent'] }}
                                                    </span>
                                                </div>
                                            @endif
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            @if(isset($reportData['totals']['net_income']))
                                <tr class="border-t-2 border-gray-950 dark:border-white print:border-black">
                                    <td class="py-4 px-4 font-black uppercase text-base text-gray-950 dark:text-white">Net Income / (Loss)</td>
                                    <td class="py-4 px-4 text-right font-black text-lg text-gray-950 dark:text-white border-b-4 border-double border-gray-950 dark:border-white print:border-black">
                                        {{ $reportData['totals']['net_income'] }}
                                    </td>
                                    @if(isset($reportData['lines'][0]['compare_amount']))
                                        <td colspan="2"></td>
                                    @endif
                                </tr>
                            @endif
                        </tfoot>
                    </table>
                </div>

                <div class="mt-12 pt-4 border-t border-gray-100 dark:border-white/5 flex justify-between items-center text-[10px] text-gray-400 uppercase tracking-widest print:mt-8">
                    <div>
                        Printed on {{ $reportData['printed_at'] ?? now()->format('Y-m-d H:i') }}
                    </div>
                    <div>
                        {{ config('app.name', 'BIWMS ERP') }} | Financial Statement
                    </div>
                    <div>
                        Page 1 of 1
                    </div>
                </div>
            </x-filament::section>
        @else
            <x-filament::section>
                <div class="py-12 flex flex-col items-center justify-center text-gray-400">
                    <x-filament::icon icon="heroicon-o-document-chart-bar" class="w-12 h-12 mb-4 opacity-20" />
                    <p>Select report parameters and click "Update Report" to view data.</p>
                </div>
            </x-filament::section>
        @endif
    </div>

    <style>
        @media print {
            body { 
                background: white !important;
                color: black !important;
            }
            .fi-main-ctn { padding: 0 !important; }
            .fi-sidebar, .fi-topbar, .fi-header, .print\:hidden, x-filament-actions { 
                display: none !important; 
            }
            .fi-section {
                border: none !important;
                box-shadow: none !important;
                background: transparent !important;
                padding: 0 !important;
            }
            table { 
                font-size: 10pt !important; 
                width: 100% !important;
            }
            th { border-bottom: 2px solid black !important; }
            td { border-bottom: 1px solid #eee !important; }
            .border-double { border-bottom-style: double !important; }
            
            /* Ensure colors remain in print */
            .text-ruby-600 { color: #dc2626 !important; }
            .text-emerald-600 { color: #059669 !important; }
            .bg-ruby-50 { background-color: #fef2f2 !important; }
            .bg-emerald-50 { background-color: #ecfdf5 !important; }
        }

        /* Hierarchy Guides */
        .indent-guide {
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 1px;
            background-color: currentColor;
            opacity: 0.1;
        }
    </style>
</x-filament-panels::page>
