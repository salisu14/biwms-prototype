<x-filament-panels::page>
    <div class="space-y-6">
        @php
            $tableOuterClass = 'overflow-x-auto rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900';
            $tableHeaderCellClass = 'border-b border-r border-gray-200 px-3 py-3 font-semibold last:border-r-0 dark:border-gray-700';
            $tableBodyCellClass = 'border-b border-r border-gray-100 px-3 py-2 last:border-r-0 dark:border-gray-800';
        @endphp

        <x-filament::section
            heading="Quick Actions"
            description="Common fiscal setup and year-end tasks, grouped the same way we manage BC-style workflows."
        >
            <div class="grid gap-4 xl:grid-cols-2">
                <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                    <div class="space-y-4">
                        <div>
                            <h3 class="text-base font-semibold text-gray-950 dark:text-white">Period Setup</h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Manage setup, accounting periods, and posting windows.</p>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <x-filament::button color="primary" icon="heroicon-o-cog-6-tooth" wire:click="mountAction('setup')">
                                GL Fiscal Setup
                            </x-filament::button>
                            <x-filament::button color="gray" icon="heroicon-o-plus" wire:click="mountAction('createPeriod')">
                                Create Period
                            </x-filament::button>
                            <x-filament::button color="warning" icon="heroicon-o-lock-open" wire:click="mountAction('reopenPeriod')">
                                Reopen Period
                            </x-filament::button>
                            <x-filament::button color="danger" icon="heroicon-o-lock-closed" wire:click="mountAction('closePeriod')">
                                Close Period
                            </x-filament::button>
                        </div>
                    </div>
                </div>

                <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                    <div class="space-y-4">
                        <div>
                            <h3 class="text-base font-semibold text-gray-950 dark:text-white">Year-End Actions</h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Finish the fiscal year and adjust the posting window safely.</p>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <x-filament::button color="danger" icon="heroicon-o-lock-closed" wire:click="mountAction('closeIncomeStatement')">
                                Close Income Statement
                            </x-filament::button>
                            <x-filament::button color="warning" icon="heroicon-o-lock-open" wire:click="mountAction('reopenWindow')">
                                Reopen Window
                            </x-filament::button>
                        </div>
                    </div>
                </div>
            </div>
        </x-filament::section>

        <x-filament::section
            heading="General Ledger Fiscal Setup"
            description="Current posting window, retained earnings account, and setup defaults."
        >
            <div class="grid gap-4 md:grid-cols-2">
                <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Posting Window</p>
                    <dl class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div class="rounded-lg border border-gray-200 p-3 dark:border-gray-700">
                            <dt class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Allow Posting From</dt>
                            <dd class="mt-1 text-sm font-semibold text-gray-900 dark:text-gray-100">{{ optional($setup->allow_posting_from)?->toDateString() ?? 'Not set' }}</dd>
                        </div>
                        <div class="rounded-lg border border-gray-200 p-3 dark:border-gray-700">
                            <dt class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Allow Posting To</dt>
                            <dd class="mt-1 text-sm font-semibold text-gray-900 dark:text-gray-100">{{ optional($setup->allow_posting_to)?->toDateString() ?? 'Not set' }}</dd>
                        </div>
                    </dl>
                </div>

                <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Ledger Accounts</p>
                    <dl class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div class="rounded-lg border border-gray-200 p-3 dark:border-gray-700">
                            <dt class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Retained Earnings</dt>
                            <dd class="mt-1 text-sm font-semibold text-gray-900 dark:text-gray-100">
                                {{ $setup->retainedEarningsAccount?->account_number ?? '-' }} {{ $setup->retainedEarningsAccount?->name ?? 'Not set' }}
                            </dd>
                        </div>
                        <div class="rounded-lg border border-gray-200 p-3 dark:border-gray-700">
                            <dt class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Default Expense Offset</dt>
                            <dd class="mt-1 text-sm font-semibold text-gray-900 dark:text-gray-100">
                                {{ $setup->defaultExpenseOffsetAccount?->account_number ?? '-' }} {{ $setup->defaultExpenseOffsetAccount?->name ?? 'Not set' }}
                            </dd>
                        </div>
                    </dl>
                </div>
            </div>
        </x-filament::section>

        <x-filament::section
            heading="Accounting Periods"
            description="Latest periods with their date range and current close status."
        >
            <div class="{{ $tableOuterClass }}">
                <table class="w-full border-separate border-spacing-0 text-sm align-middle">
                    <thead>
                        <tr class="bg-gray-50 text-left dark:bg-gray-800/60">
                            <th class="{{ $tableHeaderCellClass }}">Name</th>
                            <th class="{{ $tableHeaderCellClass }}">Start</th>
                            <th class="{{ $tableHeaderCellClass }}">End</th>
                            <th class="{{ $tableHeaderCellClass }}">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($periods as $period)
                            <tr>
                                <td class="{{ $tableBodyCellClass }} font-medium text-gray-900 dark:text-gray-100">{{ $period->name }}</td>
                                <td class="{{ $tableBodyCellClass }} text-gray-700 dark:text-gray-300">{{ optional($period->start_date)?->toDateString() ?? '—' }}</td>
                                <td class="{{ $tableBodyCellClass }} text-gray-700 dark:text-gray-300">{{ optional($period->end_date)?->toDateString() ?? '—' }}</td>
                                <td class="{{ $tableBodyCellClass }}">
                                    <span @class([
                                        'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium',
                                        'border border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300' => $period->is_closed,
                                        'border-sky-200 bg-sky-50 text-sky-700 dark:border-sky-800 dark:bg-sky-950/40 dark:text-sky-300' => ! $period->is_closed,
                                    ])>
                                        {{ $period->is_closed ? 'Closed' : 'Open' }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-3 py-6 text-center text-sm text-gray-500 dark:text-gray-400">No accounting periods found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>

        <x-filament::section
            heading="Fiscal Reopen Audit Log"
            description="Tracked history of posting-window reopen actions."
        >
            <div class="{{ $tableOuterClass }}">
                <table class="w-full border-separate border-spacing-0 text-sm align-middle">
                    <thead>
                        <tr class="bg-gray-50 text-left dark:bg-gray-800/60">
                            <th class="{{ $tableHeaderCellClass }}">When</th>
                            <th class="{{ $tableHeaderCellClass }}">By</th>
                            <th class="{{ $tableHeaderCellClass }}">Previous Window</th>
                            <th class="{{ $tableHeaderCellClass }}">New Window</th>
                            <th class="{{ $tableHeaderCellClass }}">Reason</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($reopenLogs as $log)
                            <tr>
                                <td class="{{ $tableBodyCellClass }} text-gray-700 dark:text-gray-300">{{ optional($log->created_at)?->format('Y-m-d H:i') ?? '—' }}</td>
                                <td class="{{ $tableBodyCellClass }} font-medium text-gray-900 dark:text-gray-100">{{ $log->requester?->name ?? '—' }}</td>
                                <td class="{{ $tableBodyCellClass }} text-gray-700 dark:text-gray-300">
                                    {{ optional($log->previous_allow_posting_from)?->toDateString() ?? '—' }} → {{ optional($log->previous_allow_posting_to)?->toDateString() ?? '—' }}
                                </td>
                                <td class="{{ $tableBodyCellClass }} text-gray-700 dark:text-gray-300">
                                    {{ optional($log->new_allow_posting_from)?->toDateString() ?? '—' }} → {{ optional($log->new_allow_posting_to)?->toDateString() ?? '—' }}
                                </td>
                                <td class="{{ $tableBodyCellClass }} text-gray-700 dark:text-gray-300">{{ $log->reason ?: '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-3 py-6 text-center text-sm text-gray-500 dark:text-gray-400">No reopen actions recorded yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
