<x-filament-panels::page>
    <div class="space-y-3">
        @forelse ($requests as $request)
            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <div class="font-semibold">{{ $request->employee?->full_name }} · {{ $request->leaveType?->name }}</div>
                        <div class="text-sm text-gray-500">{{ $request->employee?->department?->name ?: 'No department' }}</div>
                    </div>
                    <div class="text-sm font-semibold">{{ $request->start_date?->format('M d') }} - {{ $request->end_date?->format('M d, Y') }}</div>
                </div>
            </div>
        @empty
            <div class="rounded-lg border border-gray-200 bg-white p-6 text-sm text-gray-500 dark:border-gray-700 dark:bg-gray-900">
                No approved leave is scheduled for this month.
            </div>
        @endforelse
    </div>
</x-filament-panels::page>
