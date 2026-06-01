<x-filament-panels::page>
    <div class="space-y-8 fi-report-container font-sans antialiased">
        <!-- Premium Filter Section -->
        <x-filament::section class="print:hidden border-none shadow-xl bg-white/50 dark:bg-gray-900/50 backdrop-blur-md rounded-2xl overflow-hidden transition-all duration-300 hover:shadow-2xl">
            <form wire:submit="generateReport" class="p-2">
                {{ $this->form }}
                <div class="mt-6 flex justify-end items-center gap-x-4 border-t border-gray-100 dark:border-white/5 pt-6">
                    <x-filament::button
                        tag="a"
                        color="info"
                        icon="heroicon-o-printer"
                        :href="route('reports.profit-and-loss.print', [
                            'startDate' => $this->formData['startDate'] ?? null,
                            'endDate' => $this->formData['endDate'] ?? null,
                            'compareStartDate' => $this->formData['compareStartDate'] ?? null,
                            'compareEndDate' => $this->formData['compareEndDate'] ?? null,
                            'dimension1' => $this->formData['dimension1'] ?? null,
                            'dimension2' => $this->formData['dimension2'] ?? null,
                            'showBudget' => ($this->formData['showBudget'] ?? false) ? 1 : 0,
                        ])"
                        target="_blank"
                    >
                        Print
                    </x-filament::button>
                    <button type="button" wire:click="form.fill" class="px-4 py-2 text-sm font-medium text-gray-500 hover:text-gray-700 dark:hover:text-gray-300 transition-colors">
                        Reset Filters
                    </button>
                    <x-filament::button type="submit" size="lg" class="rounded-xl shadow-lg shadow-primary-500/20 px-8 transition-transform active:scale-95">
                        Update Analysis
                    </x-filament::button>
                </div>
            </form>
        </x-filament::section>

        @if($reportData)
            <!-- Executive Analytics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 print:hidden">
                @php
                    $indicators = [
                        ['label' => 'Total Revenue', 'value' => $reportData['totals']['revenue'], 'compare' => $reportData['totals']['compare_revenue'] ?? null, 'color' => 'indigo'],
                        ['label' => 'Gross Profit', 'value' => $reportData['totals']['gross_profit'], 'compare' => $reportData['totals']['compare_gross_profit'] ?? null, 'color' => 'emerald'],
                        ['label' => 'Operating Expenses', 'value' => $reportData['totals']['operating_expenses'], 'compare' => $reportData['totals']['compare_operating_expenses'] ?? null, 'color' => 'amber'],
                        ['label' => 'Net Income', 'value' => $reportData['totals']['net_income'], 'compare' => $reportData['totals']['compare_net_income'] ?? null, 'color' => 'ruby'],
                    ];
                @endphp

                @foreach($indicators as $card)
                    @php
                        $isLoss = $card['label'] === 'Net Income' && str_contains((string)$card['value'], '-');
                        $cardColor = $isLoss ? 'ruby' : $card['color'];
                        $val = (float) str_replace(',', '', $card['value']);
                        $compVal = $card['compare'] ? (float) str_replace(',', '', $card['compare']) : null;
                        $growth = $compVal && $compVal != 0 ? (($val - $compVal) / abs($compVal)) * 100 : null;
                    @endphp
                    <div class="relative group">
                        <div class="absolute -inset-0.5 bg-gradient-to-r from-{{ $cardColor }}-500 to-{{ $cardColor }}-600 rounded-2xl blur opacity-20 group-hover:opacity-40 transition duration-500"></div>
                        <div class="relative flex flex-col p-6 bg-white dark:bg-gray-900 rounded-2xl shadow-sm border border-gray-100 dark:border-white/5 transition-transform duration-300 group-hover:-translate-y-1">
                            <span class="text-xs font-bold uppercase tracking-widest text-{{ $cardColor }}-600 mb-1">{{ $card['label'] }}</span>
                            <div class="flex items-baseline gap-x-2">
                                <span class="text-3xl font-black text-gray-900 dark:text-white tabular-nums">
                                    {{ $card['value'] }}
                                </span>
                            </div>
                            @if($growth !== null)
                                <div class="mt-2 flex items-center gap-x-1 text-xs">
                                    <span class="font-bold {{ $growth >= 0 ? ($card['label'] === 'Operating Expenses' ? 'text-ruby-600' : 'text-emerald-600') : ($card['label'] === 'Operating Expenses' ? 'text-emerald-600' : 'text-ruby-600') }}">
                                        {{ $growth >= 0 ? '+' : '' }}{{ number_format($growth, 1) }}%
                                    </span>
                                    <span class="text-gray-400">vs prior</span>
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            <!-- Premium Financial Statement -->
            <x-filament::section class="border-none shadow-2xl rounded-3xl overflow-hidden bg-white dark:bg-gray-900 p-0">
                <!-- Specialized Header -->
                <div class="p-8 md:p-12 border-b border-gray-100 dark:border-white/5 bg-gradient-to-b from-gray-50/50 to-transparent dark:from-white/5">
                    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-6">
                        <div>
                            <div class="flex items-center gap-x-3 mb-2">
                                <div class="w-10 h-10 rounded-xl bg-primary-600 flex items-center justify-center text-white shadow-lg shadow-primary-500/30">
                                    <x-filament::icon icon="heroicon-m-document-chart-bar" class="w-6 h-6" />
                                </div>
                                <h1 class="text-3xl font-black tracking-tight text-gray-900 dark:text-white">
                                    {{ $reportData['report_name'] ?? 'Profit & Loss Statement' }}
                                </h1>
                            </div>
                            <p class="text-lg font-semibold text-gray-400 leading-none uppercase tracking-widest pl-1">{{ config('app.company_name', 'BIWMS ERP') }}</p>
                        </div>
                        <div class="flex flex-col items-end gap-y-1 text-right">
                            <div class="px-4 py-2 rounded-full bg-gray-100 dark:bg-white/5 text-xs font-bold text-gray-600 dark:text-gray-400 flex items-center gap-x-2">
                                <span class="w-2 h-2 rounded-full bg-primary-500 animate-pulse"></span>
                                PERIOD: {{ $reportData['period'] }}
                            </div>
                            @if(isset($formData['dimension1']))
                                <span class="text-xs font-bold text-gray-400 uppercase tracking-wide">DEPT: {{ $formData['dimension1'] }}</span>
                            @endif
                            @if(isset($formData['dimension2']))
                                <span class="text-xs font-bold text-gray-400 uppercase tracking-wide">PROJ: {{ $formData['dimension2'] }}</span>
                            @endif
                            <span class="text-[10px] font-bold text-gray-300 uppercase tracking-[0.2em] mt-1 italic">CURRENCY: LCY</span>
                        </div>
                    </div>
                </div>

                <!-- Interactive Data Table -->
                <div class="overflow-x-auto">
                    <table class="report-table w-full text-sm text-left border-collapse">
                        <thead>
                            <tr class="border-b border-gray-200 dark:border-white/10 bg-gray-50/50 dark:bg-white/5">
                                <th class="py-5 px-4 font-black uppercase tracking-[0.15em] text-gray-400 text-[11px]">Heading</th>
                                <th class="py-5 px-4 font-black uppercase tracking-[0.15em] text-gray-400 text-[11px]">Posting</th>
                                <th class="py-5 px-4 font-black uppercase tracking-[0.15em] text-gray-400 text-[11px]">Start Total</th>
                                <th class="py-5 px-4 font-black uppercase tracking-[0.15em] text-gray-400 text-[11px]">End Total</th>
                                <th class="py-5 px-4 font-black uppercase tracking-[0.15em] text-gray-400 text-[11px]">Style</th>
                                <th class="py-5 px-8 font-black uppercase tracking-[0.15em] text-gray-400 text-[11px]">G/L Description</th>
                                <th class="py-5 px-8 text-right font-black uppercase tracking-[0.15em] text-gray-900 dark:text-white text-[11px]">Current Amount</th>
                                @if(isset($reportData['compare_period']))
                                    <th class="py-5 px-8 text-right font-black uppercase tracking-[0.15em] text-gray-400 text-[11px]">Prior Period</th>
                                    <th class="py-5 px-8 text-right font-black uppercase tracking-[0.15em] text-gray-400 text-[11px]">Variance</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50 dark:divide-white/5">
                            @foreach($reportData['lines'] as $line)
                                @php
                                    $isBold = $line['bold'] ?? false;
                                    $indent = ($line['indentation'] ?? 0);
                                    $amount = $line['amount'] ?? '0.00';
                                    $isNegative = str_contains((string)$amount, '-');
                                @endphp
                                <tr class="group transition-all duration-200 {{ $isBold ? 'bg-gray-50/30 dark:bg-white/[0.02] font-extrabold' : 'hover:bg-primary-500/5 cursor-default' }}">
                                    <td class="py-3 px-4 text-gray-500">{{ $line['heading'] ?? '—' }}</td>
                                    <td class="py-3 px-4 text-gray-500">{{ $line['posting'] ?? '—' }}</td>
                                    <td class="py-3 px-4 text-gray-500">{{ $line['start_total'] ?? '—' }}</td>
                                    <td class="py-3 px-4 text-gray-500">{{ $line['end_total'] ?? '—' }}</td>
                                    <td class="py-3 px-4 text-gray-500">{{ $line['style'] ?: '—' }}</td>
                                    <td class="py-3 px-8 relative">
                                        {{-- Indentation Guides --}}
                                        @for($i = 0; $i < $indent; $i++)
                                            <div class="absolute top-0 bottom-0 border-l border-gray-200 dark:border-gray-800" style="left: {{ 2 + ($i * 1.5) }}rem"></div>
                                        @endfor
                                        <div style="padding-left: {{ $indent * 1.5 }}rem" class="flex items-center gap-x-2">
                                            @if($isBold)
                                                <div class="w-1.5 h-1.5 rounded-full bg-primary-500 mr-1"></div>
                                            @endif
                                            <span class="{{ $isBold ? 'text-gray-900 dark:text-white' : 'text-gray-600 dark:text-gray-400' }} tracking-wide">
                                                {{ $line['description'] }}
                                            </span>
                                        </div>
                                    </td>
                                    <td class="py-3 px-8 text-right tabular-nums font-semibold {{ $isNegative ? 'text-ruby-600' : 'text-gray-900 dark:text-white' }}">
                                        {{ $isNegative ? '(' . ltrim($amount, '-') . ')' : $amount }}
                                    </td>
                                    @if(isset($reportData['compare_period']))
                                        <td class="py-3 px-8 text-right tabular-nums text-gray-500 dark:text-gray-500 italic">
                                            {{ $line['compare_amount'] ? (str_contains($line['compare_amount'], '-') ? '(' . ltrim($line['compare_amount'], '-') . ')' : $line['compare_amount']) : '—' }}
                                        </td>
                                        <td class="py-3 px-8 text-right">
                                            @if($line['variance_percent'])
                                                @php
                                                    $varVal = (float) rtrim($line['variance_percent'], '%');
                                                    $isBad = ($varVal > 0 && str_contains(strtolower($line['description']), 'expense')) || ($varVal < 0 && !str_contains(strtolower($line['description']), 'expense'));
                                                @endphp
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-black uppercase tracking-widest {{ $isBad ? 'bg-ruby-100 text-ruby-700 dark:bg-ruby-900/30 dark:text-ruby-400' : 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400' }}">
                                                    {{ $line['variance_percent'] }}
                                                </span>
                                            @endif
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            @if(isset($reportData['totals']['net_income']))
                                @php
                                    $netInc = $reportData['totals']['net_income'];
                                    $isNetNegative = str_contains((string)$netInc, '-');
                                @endphp
                                <tr class="bg-gray-950 dark:bg-black group border-t-4 border-primary-500">
                                    <td colspan="5"></td>
                                    <td class="py-6 px-8 font-black uppercase tracking-[0.25em] text-white text-base">Net Income / (Loss)</td>
                                    <td class="py-6 px-8 text-right font-black text-xl text-white tabular-nums border-b-[6px] border-double border-white/30">
                                        {{ $isNetNegative ? '(' . ltrim($netInc, '-') . ')' : $netInc }}
                                    </td>
                                    @if(isset($reportData['compare_period']))
                                        <td class="py-6 px-8 text-right font-bold text-gray-400 tabular-nums">
                                            {{ str_contains($reportData['totals']['compare_net_income'], '-') ? '(' . ltrim($reportData['totals']['compare_net_income'], '-') . ')' : $reportData['totals']['compare_net_income'] }}
                                        </td>
                                        <td></td>
                                    @endif
                                </tr>
                            @endif
                        </tfoot>
                    </table>
                </div>

                <!-- Professional Footer -->
                <div class="p-8 flex flex-col md:flex-row justify-between items-center gap-4 text-[10px] font-black uppercase tracking-[0.3em] text-gray-400 bg-gray-50/50 dark:bg-white/5">
                    <div>
                        GENERATED ON {{ $reportData['printed_at'] ?? now()->format('Y-m-d H:i') }}
                    </div>
                    <div class="flex items-center gap-x-4">
                        <span>CONFIDENTIAL FINANCIAL DATA</span>
                        <span class="w-1 h-1 rounded-full bg-gray-300"></span>
                        <span>{{ config('app.name', 'BIWMS') }}</span>
                    </div>
                    <div>
                        PAGE 01 / 01
                    </div>
                </div>
            </x-filament::section>
        @else
            <!-- Empty State -->
            <div class="py-24 flex flex-col items-center justify-center animate-pulse">
                <div class="w-24 h-24 rounded-3xl bg-gray-100 dark:bg-white/5 flex items-center justify-center mb-6">
                    <x-filament::icon icon="heroicon-o-presentation-chart-line" class="w-12 h-12 text-gray-300 dark:text-gray-700" />
                </div>
                <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-2">Awaiting Analysis Parameters</h3>
                <p class="text-gray-500 dark:text-gray-400 text-center max-w-sm">Configure your reporting period and dimensions above to generate a comprehensive financial breakdown.</p>
            </div>
        @endif
    </div>

    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&display=swap');
        
        .fi-report-container {
            font-family: 'Inter', system-ui, sans-serif;
        }

        .tabular-nums {
            font-variant-numeric: tabular-nums;
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
            @page {
                size: A4 portrait;
                margin: 12mm;
            }
            .fi-sidebar,
            .fi-topbar,
            .fi-header,
            .fi-breadcrumbs,
            .fi-global-search {
                display: none !important;
            }
            .fi-main,
            .fi-page,
            .fi-page-content {
                margin: 0 !important;
                padding: 0 !important;
                width: 100% !important;
                max-width: 100% !important;
            }
            body { 
                background: white !important;
                color: black !important;
            }
            .print\:hidden { display: none !important; }
            .fi-section {
                border: none !important;
                box-shadow: none !important;
                background: transparent !important;
                padding: 0 !important;
            }
            .rounded-3xl { border-radius: 0 !important; }
            .bg-gray-950 { background-color: transparent !important; color: black !important; }
            .text-white { color: black !important; }
            .border-white\/30 { border-color: black !important; }
            tr { page-break-inside: avoid; }
            
            table { font-size: 9pt !important; }
            h1 { font-size: 18pt !important; }
        }

        .overflow-x-auto::-webkit-scrollbar {
            height: 4px;
        }
        .overflow-x-auto::-webkit-scrollbar-track {
            background: transparent;
        }
        .overflow-x-auto::-webkit-scrollbar-thumb {
            background: rgba(0,0,0,0.1);
            border-radius: 10px;
        }
    </style>
</x-filament-panels::page>
