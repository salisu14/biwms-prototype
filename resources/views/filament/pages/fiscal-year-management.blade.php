<x-filament-panels::page>
    <div class="space-y-6">
        <x-filament::section heading="General Ledger Fiscal Setup" description="Current posting window and retained earnings configuration.">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Allow Posting From</p>
                    <p class="mt-1 text-sm font-semibold text-gray-900 dark:text-gray-100">{{ optional($setup->allow_posting_from)?->toDateString() ?? 'Not set' }}</p>
                </div>
                <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Allow Posting To</p>
                    <p class="mt-1 text-sm font-semibold text-gray-900 dark:text-gray-100">{{ optional($setup->allow_posting_to)?->toDateString() ?? 'Not set' }}</p>
                </div>
                <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Retained Earnings</p>
                    <p class="mt-1 text-sm font-semibold text-gray-900 dark:text-gray-100">
                        {{ $setup->retainedEarningsAccount?->account_number ?? '-' }} {{ $setup->retainedEarningsAccount?->name ?? 'Not set' }}
                    </p>
                </div>
            </div>
        </x-filament::section>

        <x-filament::section heading="Accounting Periods" description="Latest accounting periods and current open/closed status.">
            <div class="overflow-x-auto">
                <table class="w-full text-sm align-middle">
                    <thead>
                    <tr class="border-b border-gray-200 text-left dark:border-gray-700">
                        <th class="px-3 py-3 font-semibold">Name</th>
                        <th class="px-3 py-3 font-semibold">Start</th>
                        <th class="px-3 py-3 font-semibold">End</th>
                        <th class="px-3 py-3 font-semibold">Closed</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($periods as $period)
                        <tr class="border-b border-gray-100 dark:border-gray-800">
                            <td class="px-3 py-2">{{ $period->name }}</td>
                            <td class="px-3 py-2">{{ optional($period->start_date)?->toDateString() }}</td>
                            <td class="px-3 py-2">{{ optional($period->end_date)?->toDateString() }}</td>
                            <td class="px-3 py-2">{{ $period->is_closed ? 'Yes' : 'No' }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </x-filament::section>

        <x-filament::section heading="Fiscal Reopen Audit Log" description="Tracked history of posting-window reopen actions.">
            <div class="overflow-x-auto">
                <table class="w-full text-sm align-middle">
                    <thead>
                    <tr class="border-b border-gray-200 text-left dark:border-gray-700">
                        <th class="px-3 py-3 font-semibold">When</th>
                        <th class="px-3 py-3 font-semibold">By</th>
                        <th class="px-3 py-3 font-semibold">Previous Window</th>
                        <th class="px-3 py-3 font-semibold">New Window</th>
                        <th class="px-3 py-3 font-semibold">Reason</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($reopenLogs as $log)
                        <tr class="border-b border-gray-100 dark:border-gray-800">
                            <td class="px-3 py-2">{{ optional($log->created_at)?->format('Y-m-d H:i') }}</td>
                            <td class="px-3 py-2">{{ $log->requester?->name }}</td>
                            <td class="px-3 py-2">{{ optional($log->previous_allow_posting_from)?->toDateString() }} → {{ optional($log->previous_allow_posting_to)?->toDateString() }}</td>
                            <td class="px-3 py-2">{{ optional($log->new_allow_posting_from)?->toDateString() }} → {{ optional($log->new_allow_posting_to)?->toDateString() }}</td>
                            <td class="px-3 py-2">{{ $log->reason }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
