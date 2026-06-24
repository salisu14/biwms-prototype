<x-filament-panels::page>
    <div class="grid gap-6">
        <x-filament::section heading="Today">
            @php
                $canClockIn = ! $todayEntry || ! $todayEntry->clock_in_at;
                $canClockOut = $todayEntry && $todayEntry->clock_in_at && ! $todayEntry->clock_out_at && $todayEntry->status === 'OPEN';
            @endphp

            <div class="grid gap-4 md:grid-cols-4">
                <div class="rounded-xl border p-4">
                    <div class="text-sm text-gray-500">Clock In</div>
                    <div class="mt-1 text-lg font-semibold">
                        {{ $todayEntry?->clock_in_at?->format('H:i') ?? '—' }}
                    </div>
                </div>
                <div class="rounded-xl border p-4">
                    <div class="text-sm text-gray-500">Clock Out</div>
                    <div class="mt-1 text-lg font-semibold">
                        {{ $todayEntry?->clock_out_at?->format('H:i') ?? '—' }}
                    </div>
                </div>
                <div class="rounded-xl border p-4">
                    <div class="text-sm text-gray-500">Worked Hours</div>
                    <div class="mt-1 text-lg font-semibold">
                        {{ number_format((float) ($todayEntry?->worked_hours ?? 0), 2) }}
                    </div>
                </div>
                <div class="rounded-xl border p-4">
                    <div class="text-sm text-gray-500">Status</div>
                    <div class="mt-1 text-lg font-semibold">
                        {{ $todayEntry?->status ?? 'OPEN' }}
                    </div>
                </div>
            </div>

            <div class="mt-4 flex gap-3">
                <x-filament::button
                    color="success"
                    wire:click="clockIn"
                    :disabled="! $canClockIn"
                >
                    Clock In
                </x-filament::button>

                <x-filament::button
                    color="gray"
                    wire:click="clockOut"
                    :disabled="! $canClockOut"
                >
                    Clock Out
                </x-filament::button>
            </div>
        </x-filament::section>

        <x-filament::section heading="Recent Attendance">
            <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                <table class="w-full text-left text-sm text-gray-600 dark:text-gray-300">
                    <thead class="bg-gray-50 dark:bg-gray-800/50">
                    <tr>
                        <th class="px-4 py-3 font-semibold uppercase tracking-wider text-gray-700 dark:text-gray-200">
                            Date
                        </th>
                        <th class="px-4 py-3 font-semibold uppercase tracking-wider text-gray-700 dark:text-gray-200 text-center">
                            Clock In
                        </th>
                        <th class="px-4 py-3 font-semibold uppercase tracking-wider text-gray-700 dark:text-gray-200 text-center">
                            Clock Out
                        </th>
                        <th class="px-4 py-3 font-semibold uppercase tracking-wider text-gray-700 dark:text-gray-200 text-right">
                            Hours
                        </th>
                        <th class="px-4 py-3 font-semibold uppercase tracking-wider text-gray-700 dark:text-gray-200 text-center">
                            Status
                        </th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700 bg-white dark:bg-gray-800">
                    @forelse($recentEntries as $entry)
                        <tr class="transition-colors hover:bg-gray-50 dark:hover:bg-gray-700/50">
                            <td class="px-4 py-3 whitespace-nowrap font-medium text-gray-900 dark:text-white">
                                {{ $entry->attendance_date?->format('D, M j, Y') }}
                            </td>
                            <td class="px-4 py-3 text-center">
                                {{ $entry->clock_in_at?->format('h:i A') ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-center">
                                {{ $entry->clock_out_at?->format('h:i A') ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-right font-semibold text-gray-900 dark:text-white">
                                {{ number_format((float) $entry->worked_hours, 2) }} hrs
                            </td>
                            <td class="px-4 py-3 text-center">
                                @php
                                    $color = match($entry->status) {
                                        'APPROVED' => 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-400',
                                        'REJECTED' => 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-400',
                                        default => 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/40 dark:text-yellow-400',
                                    };
                                @endphp
                                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-bold {{ $color }}">
                                        {{ ucfirst(strtolower($entry->status)) }}
                                    </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
                                No recent attendance records found.
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
