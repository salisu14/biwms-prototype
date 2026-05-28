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
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b">
                            <th class="py-2 pr-4">Date</th>
                            <th class="py-2 pr-4">In</th>
                            <th class="py-2 pr-4">Out</th>
                            <th class="py-2 pr-4">Hours</th>
                            <th class="py-2 pr-4">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recentEntries as $entry)
                            <tr class="border-b">
                                <td class="py-2 pr-4">{{ $entry->attendance_date?->format('Y-m-d') }}</td>
                                <td class="py-2 pr-4">{{ $entry->clock_in_at?->format('H:i') ?? '—' }}</td>
                                <td class="py-2 pr-4">{{ $entry->clock_out_at?->format('H:i') ?? '—' }}</td>
                                <td class="py-2 pr-4">{{ number_format((float) $entry->worked_hours, 2) }}</td>
                                <td class="py-2 pr-4">{{ $entry->status }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-4 text-gray-500">No attendance records yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>

