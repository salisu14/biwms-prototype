<x-filament-panels::page>
    {{-- ✅ SINGLE ROOT ELEMENT - REQUIRED BY LIVEWIRE --}}
    <div class="space-y-6">

        {{-- ════════════════════════════════════════════════════════════════ --}}
        {{-- SECTION 1: Empty State (Before Generating)                         --}}
        {{-- ════════════════════════════════════════════════════════════════ --}}
        @if(!$has_generated && empty($data))
            <div
                class="bg-gradient-to-br from-blue-50 via-white to-purple-50 rounded-2xl shadow-lg border border-gray-200 p-12 text-center">
                <div class="max-w-md mx-auto">
                    <!-- Icon -->
                    <div
                        class="w-24 h-24 bg-gradient-to-br from-blue-400 to-purple-500 rounded-full flex items-center justify-center mx-auto mb-6 shadow-lg">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-12 h-12 text-white" fill="none"
                             viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                        </svg>
                    </div>

                    <!-- Text -->
                    <h3 class="text-3xl font-bold text-gray-900 mb-3">Ready to Generate Report</h3>
                    <p class="text-gray-600 mb-8 leading-relaxed text-lg">
                        Configure your filters above and click <strong class="text-blue-600">"Generate Report"</strong>
                        to view purchase statistics by posting group.
                    </p>

                    <!-- Quick Tips -->
                    <div
                        class="bg-white/80 backdrop-blur-sm rounded-xl p-5 text-left space-y-3 text-sm text-gray-700 border border-gray-200 shadow-sm">
                        <p class="font-bold text-gray-900 text-base flex items-center gap-2 mb-3">
                            <svg class="w-5 h-5 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd"
                                      d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z"
                                      clip-rule="evenodd"/>
                            </svg>
                            Quick Start Guide
                        </p>
                        <ul class="space-y-2 ml-5 list-disc marker:text-blue-500">
                            <li><strong>Default Range:</strong> Current month (1st to today)</li>
                            <li><strong>All Groups:</strong> Leave "Posting Group" blank</li>
                            <li><strong>Export:</strong> Click "Export CSV" after generating</li>
                            <li><strong>Print:</strong> Use "Print" button for clean PDF output</li>
                        </ul>
                    </div>
                </div>
            </div>
        @else

            {{-- ════════════════════════════════════════════════════════════════ --}}
            {{-- SECTION 2: Widgets Area (Stats + Chart)                           --}}
            {{-- ════════════════════════════════════════════════════════════════ --}}
            {{ $this->widget }}

            {{-- ════════════════════════════════════════════════════════════════ --}}
            {{-- SECTION 3: Detail Table                                           --}}
            {{-- ════════════════════════════════════════════════════════════════ --}}
            {{-- ✅ FIXED: Using plain div instead of <x-filament-section> --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md overflow-hidden border border-gray-200">
                <!-- Table Header -->
                <div class="px-6 py-4 border-b border-gray-200 bg-slate-50">
                    <div class="flex flex-col lg:flex-row lg:justify-between lg:items-center gap-3">
                        <div class="flex items-center gap-3">
                            <h3 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                                <svg class="w-5 h-5 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                     stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                          d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                                Cost Breakdown by Posting Group
                            </h3>

                            <span
                                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-700 ring-1 ring-red-600/20">
                                G/L Entries Analysis
                            </span>
                        </div>

                        <div class="flex flex-wrap items-center gap-x-3 gap-y-2 text-xs sm:text-sm text-gray-600">
                            <span
                                class="inline-flex items-center gap-1.5 px-2.5 py-1.5 bg-white rounded-lg border border-gray-200 shadow-sm">
                                <svg class="w-4 h-4 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                     stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                          d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                                <strong>From:</strong> {{ $filters['date_from'] ?? 'N/A' }}
                            </span>

                            <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                 stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                            </svg>

                            <span
                                class="inline-flex items-center gap-1.5 px-2.5 py-1.5 bg-white rounded-lg border border-gray-200 shadow-sm">
                                <strong>To:</strong> {{ $filters['date_to'] ?? 'N/A' }}
                            </span>

                            @if(!empty($filters['gen_bus_posting_group_id']))
                                <span
                                    class="inline-flex items-center gap-1.5 px-2.5 py-1.5 bg-blue-50 text-blue-700 rounded-lg border border-blue-200 font-medium">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                         stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                              d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
                                    </svg>
                                    {{ \App\Models\GeneralBusinessPostingGroup::find($filters['gen_bus_posting_group_id'])?->description ?? 'Selected Group' }}
                                </span>
                            @endif

                            <span
                                class="ml-auto inline-flex items-center px-2.5 py-1.5 bg-gray-100 text-gray-700 rounded-full font-semibold text-xs">
                                📊 {{ count($data ?? []) }} records
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Table Body -->
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50 sticky top-0 z-10">
                        <tr>
                            <th scope="col"
                                class="px-6 py-3 text-left text-xs font-bold text-gray-600 uppercase tracking-wider">#
                            </th>
                            <th scope="col"
                                class="px-6 py-3 text-left text-xs font-bold text-gray-600 uppercase tracking-wider">
                                Posting Group
                            </th>
                            <th scope="col"
                                class="px-6 py-3 text-right text-xs font-bold text-gray-600 uppercase tracking-wider">
                                Total Cost
                            </th>
                            <th scope="col"
                                class="px-6 py-3 text-right text-xs font-bold text-gray-600 uppercase tracking-wider">
                                Txns
                            </th>
                            <th scope="col"
                                class="px-6 py-3 text-right text-xs font-bold text-gray-600 uppercase tracking-wider">
                                Avg
                            </th>
                            <th scope="col"
                                class="px-6 py-3 text-right text-xs font-bold text-gray-600 uppercase tracking-wider">
                                Max
                            </th>
                            <th scope="col"
                                class="px-6 py-3 text-right text-xs font-bold text-gray-600 uppercase tracking-wider">
                                Min
                            </th>
                            <th scope="col"
                                class="px-6 py-3 text-right text-xs font-bold text-gray-600 uppercase tracking-wider w-40">
                                % Share
                            </th>
                        </tr>
                        </thead>

                        <tbody class="bg-white divide-y divide-gray-200">
                        @forelse(($data ?? []) as $index => $row)
                            <tr class="hover:bg-red-50/40 transition-colors duration-150 {{ $loop->first ? 'border-t-2 border-t-yellow-400' : '' }}">
                                <!-- Rank Badge -->
                                <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex items-center justify-center w-8 h-8 rounded-full text-sm font-bold
                                            {{ match(true) {
                                                $index === 0 => 'bg-yellow-100 text-yellow-800 ring-2 ring-yellow-400/30 shadow-sm',
                                                $index === 1 => 'bg-gray-200 text-gray-800 ring-2 ring-gray-400/30 shadow-sm',
                                                $index === 2 => 'bg-orange-100 text-orange-800 ring-2 ring-orange-400/30 shadow-sm',
                                                default => 'bg-gray-100 text-gray-600 shadow-sm',
                                                }
                                            }}
                                        ">
                                            {{ $index + 1 }}
                                        </span>
                                </td>

                                <!-- Group Info -->
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center gap-3">
                                        <div class="flex-shrink-0 w-10 h-10 rounded-lg flex items-center justify-center text-xs font-bold shadow-sm
                                                {{ match(true) {
                                                    ($row['percentage_of_total'] ?? 0) >= 25 => 'bg-red-100 text-red-600',
                                                    ($row['percentage_of_total'] ?? 0) >= 15 => 'bg-orange-100 text-orange-600',
                                                    ($row['percentage_of_total'] ?? 0) >= 10 => 'bg-amber-100 text-amber-600',
                                                    default => 'bg-gray-100 text-gray-600',
                                                    }
                                                }}"
                                        >
                                            {{ substr($row['group_code'] ?? '??', 0, 3) }}
                                        </div>
                                        <div>
                                            <div class="font-semibold text-gray-900">{{ $row['group_name'] }}</div>
                                            <div class="text-xs text-gray-500 font-mono">{{ $row['group_code'] }}</div>
                                        </div>
                                    </div>
                                </td>

                                <!-- Total Cost -->
                                <td class="px-6 py-4 whitespace-nowrap text-right">
                                    <div class="text-base font-bold text-red-700 tabular-nums">
                                        ₦{{ number_format($row['total_cost'], 2) }}
                                    </div>
                                    <div class="text-xs text-gray-400 tabular-nums hidden xl:block">
                                        {{ number_format($row['total_cost'], 0) }} entries
                                    </div>
                                </td>

                                <!-- Transactions -->
                                <td class="px-6 py-4 whitespace-nowrap text-right text-gray-900 font-semibold tabular-nums">
                                    {{ number_format($row['transaction_count']) }}
                                </td>

                                <!-- Avg Cost -->
                                <td class="px-6 py-4 whitespace-nowrap text-right text-gray-700 tabular-nums">
                                    ₦{{ number_format($row['avg_cost'], 2) }}
                                </td>

                                <!-- Max -->
                                <td class="px-6 py-4 whitespace-nowrap text-right text-red-600 font-semibold tabular-nums">
                                    ₦{{ number_format($row['max_single_cost'], 2) }}
                                </td>

                                <!-- Min -->
                                <td class="px-6 py-4 whitespace-nowrap text-right text-emerald-600 font-semibold tabular-nums">
                                    ₦{{ number_format($row['min_single_cost'], 2) }}
                                </td>

                                <!-- Percentage Bar -->
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center justify-end gap-3">
                                        <div
                                            class="flex-1 max-w-[120px] h-2.5 bg-gray-200 rounded-full overflow-hidden shadow-inner">
                                            <div
                                                class="h-full rounded-full transition-all duration-1000
                                                        {{ match(true) {
                                                            ($row['percentage_of_total'] ?? 0) >= 25 => 'bg-gradient-to-r from-red-500 to-red-600',
                                                            ($row['percentage_of_total'] ?? 0) >= 15 => 'bg-gradient-to-r from-orange-500 to-orange-600',
                                                            ($row['percentage_of_total'] ?? 0) >= 10 => 'bg-gradient-to-r from-amber-500 to-amber-600',
                                                            default => 'bg-gradient-to-r from-gray-400 to-gray-500',
                                                            }
                                                        }}"
                                                style="width: {{ min(100, $row['percentage_of_total'] ?? 0) }}%"
                                            ></div>
                                        </div>
                                        <span class="text-sm font-bold tabular-nums w-14 text-right
                                                {{ match(true) {
                                                    ($row['percentage_of_total'] ?? 0) >= 25 => 'text-red-700',
                                                    ($row['percentage_of_total'] ?? 0) >= 15 => 'text-orange-700',
                                                    ($row['percentage_of_total'] ?? 0) >= 10 => 'text-amber-700',
                                                    default => 'text-gray-600',
                                                    }
                                                }}"
                                        >
                                                {{ $row['percentage_of_total'] }}%
                                            </span>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-6 py-16 text-center">
                                    <div class="flex flex-col items-center justify-center">
                                        <div
                                            class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mb-4">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8 text-gray-400"
                                                 fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                                 stroke-width="1.5">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                      d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
                                            </svg>
                                        </div>
                                        <p class="text-gray-900 font-semibold text-lg">No data found</p>
                                        <p class="text-sm text-gray-500 mt-1 max-w-sm">
                                            Try adjusting your filters or select a different date range.
                                        </p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse

                        <!-- TOTALS ROW -->
                        @if(!empty($data))
                            <tr class="bg-gradient-to-r from-red-50 via-orange-50 to-amber-50 font-bold text-gray-900 border-t-2 border-b-2 border-red-300">
                                <td colspan="2" class="px-6 py-4 pl-8">
                                    <div class="flex items-center gap-2 text-red-700">
                                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                             stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                  d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                        </svg>
                                        GRAND TOTALS
                                    </div>
                                </td>
                                <td class="px-6 py-4 pr-8 text-right text-lg tabular-nums text-red-700">
                                    ₦{{ number_format($summary['total_cost'] ?? 0, 2) }}
                                </td>
                                <td class="px-6 py-4 text-right tabular-nums text-gray-900">
                                    {{ number_format($summary['total_transactions'] ?? 0) }}
                                </td>
                                <td class="px-6 py-4 text-right tabular-nums text-gray-900">
                                    ₦{{ number_format($summary['avg_cost'] ?? 0, 2) }}
                                </td>
                                <td class="px-6 py-4"></td>
                                <td class="px-6 py-4"></td>
                                <td class="px-6 py-4 text-right text-lg font-bold tabular-nums text-red-700">100%</td>
                            </tr>
                        @endif
                        </tbody>
                    </table>
                </div>

                <!-- Table Footer -->
                <div class="px-6 py-4 bg-gray-50 border-t border-gray-200">
                    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                        <div class="text-xs text-gray-500 flex items-center gap-2 flex-wrap">
                            <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                 stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                      d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            Generated on {{ now()->format('F j, Y \a\t g:i A') }}
                            <span class="text-gray-300 mx-1">|</span>
                            <span>📊 {{ count($data ?? []) }} posting groups analyzed</span>
                            <span class="text-gray-300 mx-1">|</span>
                            <span>💰 ₦{{ number_format($summary['total_cost'] ?? 0, 0) }} total costs</span>
                        </div>

                        <div class="flex items-center gap-2 text-xs text-gray-400 no-print">
                            <span>💡 Tip:</span>
                            <span>Use header actions to export CSV or print</span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ════════════════════════════════════════════════════════════════ --}}
            {{-- SECTION 4: Quick Stats Cards (Alternative View)                     --}}
            {{-- ════════════════════════════════════════════════════════════════ --}}
            @if(!empty($data) && count($data) <= 6)
                <div
                    class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-{{ min(count($data), 4) }} gap-4">
                    @foreach($data as $item)
                        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md hover:shadow-xl transition-all duration-300 p-5 border-l-4 cursor-pointer group
                            {{ match(true) {
                                ($item['percentage_of_total'] ?? 0) >= 25 => 'border-red-500 hover:border-red-600',
                                ($item['percentage_of_total'] ?? 0) >= 15 => 'border-orange-500 hover:border-orange-600',
                                ($item['percentage_of_total'] ?? 0) >= 10 => 'border-amber-500 hover:border-amber-600',
                                default => 'border-gray-400 hover:border-gray-500',
                                }
                            }}
                        ">
                            <div class="flex items-start justify-between mb-3">
                                <div class="p-2.5 rounded-lg
                                    {{ match(true) {
                                        ($item['percentage_of_total'] ?? 0) >= 25 => 'bg-red-100 text-red-600 group-hover:bg-red-200',
                                        ($item['percentage_of_total'] ?? 0) >= 15 => 'bg-orange-100 text-orange-600 group-hover:bg-orange-200',
                                        ($item['percentage_of_total'] ?? 0) >= 10 => 'bg-amber-100 text-amber-600 group-hover:bg-amber-200',
                                        default => 'bg-gray-100 text-gray-600 group-hover:bg-gray-200',
                                        }
                                    }} transition-colors
                                ">
                                    <span class="text-xs font-bold tracking-wide">{{ $item['group_code'] }}</span>
                                </div>

                                <span
                                    class="text-2xl font-black text-gray-900 tabular-nums group-hover:text-red-600 transition-colors">
                                    ₦{{ number_format($item['total_cost'], 0) }}
                                </span>
                            </div>

                            <div class="space-y-2 text-sm">
                                <div class="flex justify-between items-center">
                                    <span class="text-gray-500">Group Name</span>
                                    <span class="font-semibold text-gray-900 truncate ml-2 max-w-[140px]"
                                          title="{{ $item['group_name'] }}">
                                        {{ $item['group_name'] }}
                                    </span>
                                </div>

                                <div class="flex justify-between items-center">
                                    <span class="text-gray-500">Transactions</span>
                                    <span
                                        class="font-medium text-gray-700 tabular-nums">{{ number_format($item['transaction_count']) }}</span>
                                </div>

                                <div class="flex justify-between items-center">
                                    <span class="text-gray-500">Avg Cost</span>
                                    <span
                                        class="font-medium text-gray-700 tabular-nums">₦{{ number_format($item['avg_cost'], 0) }}</span>
                                </div>

                                <div class="pt-2 border-t border-gray-100 mt-2">
                                    <div class="flex justify-between items-center mb-1">
                                        <span class="text-gray-600 font-medium text-xs">Market Share</span>
                                        <span class="font-bold tabular-nums text-lg
                                            {{ match(true) {
                                                ($item['percentage_of_total'] ?? 0) >= 25 => 'text-red-600',
                                                ($item['percentage_of_total'] ?? 0) >= 15 => 'text-orange-600',
                                                ($item['percentage_of_total'] ?? 0) >= 10 => 'text-amber-600',
                                                default => 'text-gray-600',

                                                }
                                            }}
                                        ">
                                            {{ $item['percentage_of_total'] }}%
                                        </span>
                                    </div>

                                    <!-- Progress Bar -->
                                    <div class="mt-1.5 h-2 bg-gray-200 rounded-full overflow-hidden">
                                        <div class="h-full rounded-full transition-all duration-1000 group-hover:opacity-90
                                            {{ match(true) {
                                                ($item['percentage_of_total'] ?? 0) >= 25 => 'bg-gradient-to-r from-red-500 to-red-600 group-hover:from-red-600 group-hover:to-red-700',
                                                ($item['percentage_of_total'] ?? 0) >= 15 => 'bg-gradient-to-r from-orange-500 to-orange-600 group-hover:from-orange-600 group-hover:to-orange-700',
                                                ($item['percentage_of_total'] ?? 0) >= 10 => 'bg-gradient-to-r from-amber-500 to-amber-600 group-hover:from-amber-600 group-hover:to-amber-700',
                                                default => 'bg-gradient-to-r from-gray-400 to-gray-500 group-hover:from-gray-500 group-hover:to-gray-600',
                                                }
                                            }}"
                                             style="width: {{ min(100, $item['percentage_of_total']) }}%"
                                        ></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        @endif

    </div>{{-- END SINGLE ROOT --}}
</x-filament-panels::page>
