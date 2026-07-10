<x-filament-panels::page>
    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @forelse ($balances as $balance)
            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <div class="text-sm text-gray-500">{{ $balance->leaveType?->name }}</div>
                <div class="mt-2 text-2xl font-bold">{{ number_format((float) $balance->balance, 2) }}</div>
                <div class="mt-1 text-xs text-gray-500">Leave year {{ $balance->leave_year }}</div>
            </div>
        @empty
            <div class="rounded-lg border border-gray-200 bg-white p-6 text-sm text-gray-500 dark:border-gray-700 dark:bg-gray-900">
                No leave balances found.
            </div>
        @endforelse
    </div>
</x-filament-panels::page>
