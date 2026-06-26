<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Summary Stats Cards --}}
        @if(!empty($summary))
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 border border-gray-200">
                    <div class="text-sm text-gray-500 uppercase tracking-wider">Total Revenue</div>
                    <div class="text-3xl font-bold text-green-600 mt-2">
                        ₦{{ number_format($summary['total_revenue'], 2) }}
                    </div>
                    <div class="text-xs text-gray-400 mt-1">{{ $summary['groups_count'] }} posting groups</div>
                </div>

                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 border border-gray-200">
                    <div class="text-sm text-gray-500 uppercase tracking-wider">Transactions</div>
                    <div
                        class="text-3xl font-bold text-blue-600 mt-2">{{ number_format($summary['total_transactions']) }}</div>
                    <div class="text-xs text-gray-400 mt-1">Avg:
                        ₦{{ number_format($summary['avg_transaction'], 2) }}</div>
                </div>

                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 border border-gray-200">
                    <div class="text-sm text-gray-500 uppercase tracking-wider">Avg Transaction</div>
                    <div class="text-3xl font-bold text-amber-600 mt-2">
                        ₦{{ number_format($summary['avg_transaction'], 2) }}</div>
                    <div class="text-xs text-gray-400 mt-1">Per transaction</div>
                </div>

                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 border border-gray-200">
                    <div class="text-sm text-gray-500 uppercase tracking-wider">Period</div>
                    <div class="text-sm font-medium mt-2 text-gray-700">{{ $filters['date_from'] }}
                        → {{ $filters['date_to'] }}</div>
                </div>
            </div>
        @endif

        {{-- Detail Table --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center bg-gray-50">
                <h3 class="text-lg font-semibold text-gray-900">Revenue by Posting Group</h3>
                <span class="text-sm text-gray-500">{{ count($report_data) }} records</span>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Posting Group
                        </th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Revenue
                        </th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Transactions
                        </th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Avg
                            Amount
                        </th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">% of
                            Total
                        </th>
                    </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($report_data as $row)
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="font-medium text-gray-900">{{ $row['group_name'] }}</div>
                                <div class="text-sm text-gray-500">{{ $row['group_code'] }}</div>
                            </td>

                            <td class="px-6 py-4 whitespace-nowrap text-right font-semibold text-green-600">
                                ₦{{ number_format($row['total_revenue'], 2) }}
                            </td>

                            <td class="px-6 py-4 whitespace-nowrap text-right text-gray-900">
                                {{ number_format($row['transaction_count']) }}
                            </td>

                            <td class="px-6 py-4 whitespace-nowrap text-right text-gray-700">
                                ₦{{ number_format($row['avg_transaction'], 2) }}
                            </td>

                            <td class="px-6 py-4 whitespace-nowrap text-right">
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                @if($row['percentage_of_total'] >= 30) bg-green-100 text-green-800
                @elseif($row['percentage_of_total'] >= 15) bg-yellow-100 text-yellow-800
                @else bg-gray-100 text-gray-800 @endif
            ">
                {{ $row['percentage_of_total'] }}%
            </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-8 text-center text-gray-500">
                                No data available. Generate report with filters above.
                            </td>
                        </tr>
                    @endforelse

                    @if(!empty($report_data))
                        <tr class="bg-gray-100 font-semibold">
                            <td class="px-6 py-4">TOTAL</td>
                            <td class="px-6 py-4 text-right text-green-700 font-bold">
                                ₦{{ number_format($summary['total_revenue'] ?? 0, 2) }}
                            </td>
                            <td class="px-6 py-4 text-right">
                                {{ number_format($summary['total_transactions'] ?? 0) }}
                            </td>
                            <td class="px-6 py-4 text-right">
                                ₦{{ number_format($summary['avg_transaction'] ?? 0, 2) }}
                            </td>
                            <td class="px-6 py-4 text-right">100%</td>
                        </tr>
                    @endif
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-filament-panels::page>
